#!/bin/bash
# Teste de carga: URLs FRIAS, 30 simultaneas, medindo Threads_running DURANTE.
#
# uso:  carga.sh <rotulo>
#
# Alvo: por padrao homolog. Para medir outro ambiente (a instancia de teste 8.4,
# o verde do Blue/Green), sobrescrever por variavel de ambiente:
#   CARGA_CTX="arn:...:cluster/..."  CARGA_BASE="https://..."  ./carga.sh depois-84
#
# Saida: por padrao ./carga-saida/, ao lado deste script. CARGA_OUT sobrescreve.
#
# PORTAO DE CONTAGEM (HANDOVER secao 0 e 16): este script confere que o numero de
# respostas GRAVADAS bate com o numero de URLs disparadas, e sai com codigo 1 se nao
# bater. Ate 27/08/2026 ele nao fazia isso e a variavel de saida apontava para um
# diretorio de sessao que ja nao existia: a carga rodava e nada era gravado. Ver
# HANDOVER secao 16.3.

set -uo pipefail

ROT="${1:-teste}"
CTX="${CARGA_CTX:-arn:aws:eks:us-east-1:774710032593:cluster/bahia-eks-homolog}"
NS="${CARGA_NS:-bahia-wordpress}"
B="${CARGA_BASE:-https://hml.bahia.ba}"
S="${CARGA_OUT:-$(cd "$(dirname "$0")" && pwd)/carga-saida}"

# ---------------------------------------------------------------------------
# PORTAO 1 — o diretorio de saida existe e ACEITA ESCRITA, conferido ANTES da carga.
# Falhar aqui custa nada; falhar depois custa a carga inteira e nao deixa numero.
# ---------------------------------------------------------------------------
mkdir -p "$S" || { echo "ERRO: nao consegui criar $S"; exit 1; }
if ! : > "$S/.carga-teste-escrita" 2>/dev/null; then
  echo "ERRO: $S existe mas nao aceita escrita. Nada foi medido."; exit 1
fi
rm -f "$S/.carga-teste-escrita"

POD=$(kubectl --context "$CTX" -n "$NS" get pods -l app=wordpress \
        -o jsonpath='{.items[0].metadata.name}' 2>/dev/null)
if [ -z "$POD" ]; then
  echo "ERRO: nenhum pod app=wordpress em $NS no contexto $CTX. Nada foi medido."; exit 1
fi

STAMP=$RANDOM$RANDOM
HTTP="$S/carga-$ROT-http.txt"
DB="$S/carga-$ROT-db.txt"

# 30 URLs frias: home, archives de editoria e busca, cada uma com cache-buster proprio
URLS=()
for i in $(seq 1 10);  do URLS+=("$B/?cb=$STAMP-h$i"); done
for e in politica salvador esporte entretenimento economia municipios justica mundo bahia brasil; do
  URLS+=("$B/$e/?cb=$STAMP-a"); done
for t in bahia salvador carnaval eleicao praia lula chuva festa saude escola; do
  URLS+=("$B/?s=$t&cb=$STAMP-s"); done
ESPERADO=${#URLS[@]}

echo "=== CARGA [$ROT] — $ESPERADO requisicoes simultaneas, URLs frias ==="
echo "    alvo:  $B"
echo "    pod:   $POD"
echo "    saida: $S"
rm -f "$HTTP" "$DB"

# monitor do banco DURANTE.
#
# UM unico `kubectl exec` com um laco PHP dentro, amostrando a cada 0,5 s. Antes eram 24
# execs separados com `require wp-load.php` em cada um: o bootstrap do WordPress custava
# ~5 s por amostra e a carga terminava com 3 amostras colhidas — um "pico" de
# Threads_running tirado de 3 pontos, que e o mesmo erro de amostra pequena da secao 16
# do HANDOVER. Com conexao mysqli direta e laco interno saem ~120 amostras no mesmo tempo.
( kubectl --context "$CTX" -n "$NS" exec "$POD" -c wordpress -- php -r '
    $h=getenv("WORDPRESS_DB_HOST"); $u=getenv("WORDPRESS_DB_USER");
    $p=getenv("WORDPRESS_DB_PASSWORD"); $d=getenv("WORDPRESS_DB_NAME");
    mysqli_report(MYSQLI_REPORT_OFF);
    $c=@new mysqli($h,$u,$p,$d,3306);
    if ($c->connect_errno) { fwrite(STDERR,"monitor: sem conexao\n"); exit(1); }
    $fim = microtime(true) + 120;
    while (microtime(true) < $fim) {
      $r=$c->query("SHOW GLOBAL STATUS LIKE \"Threads_running\"");
      $tr=$r ? $r->fetch_assoc()["Value"] : "?";
      $calc=0;
      if ($pl=$c->query("SHOW FULL PROCESSLIST")) {
        while ($row=$pl->fetch_assoc()) {
          if (stripos((string)$row["Info"],"SQL_CALC_FOUND_ROWS")!==false) $calc++;
        }
      }
      printf("%s|%s\n", $tr, $calc);
      usleep(500000);
    }' 2>/dev/null ) > "$DB" 2>&1 &
MONPID=$!

# a carga — PIDs coletados um a um, sem depender de `jobs -p`
PIDS=()
for u in "${URLS[@]}"; do
  ( code=$(curl -s -o /dev/null -w "%{http_code} %{time_total}" --max-time 70 "$u"); \
    echo "$code $u" >> "$HTTP" ) &
  PIDS+=($!)
done
for p in "${PIDS[@]}"; do wait "$p" 2>/dev/null; done
sleep 3
kill $MONPID 2>/dev/null; wait $MONPID 2>/dev/null

# ---------------------------------------------------------------------------
# PORTAO 2 — quantas entraram, quantas sairam. Sem isto o instrumento vira o resultado.
# ---------------------------------------------------------------------------
MEDIDOS=0; [ -f "$HTTP" ] && MEDIDOS=$(grep -c . "$HTTP")
AMOSTRAS=0; [ -f "$DB" ] && AMOSTRAS=$(grep -c '|' "$DB")
echo "--- PORTAO DE CONTAGEM ---"
echo "  URLs disparadas: $ESPERADO   respostas gravadas: $MEDIDOS   amostras do banco: $AMOSTRAS"
FALHOU=0
if [ "$MEDIDOS" -ne "$ESPERADO" ]; then
  echo "  *** FALHOU: $((ESPERADO - MEDIDOS)) resposta(s) nao chegaram ao arquivo."
  echo "  *** Os numeros abaixo estao INCOMPLETOS. Nao usar para comparacao."
  FALHOU=1
fi
if [ "$AMOSTRAS" -lt 10 ]; then
  echo "  *** FALHOU: so $AMOSTRAS amostra(s) de Threads_running (minimo 10)."
  echo "  *** Um pico tirado de poucas amostras nao mede pico. Ver HANDOVER secao 16.3."
  FALHOU=1
fi

echo "--- HTTP ---"
python3 - "$HTTP" <<'PY'
import sys,collections,os
p=sys.argv[1]
if not os.path.exists(p):
    print("  (arquivo de saida nao existe — nada foi gravado)"); raise SystemExit
ls=[l.split() for l in open(p) if l.strip()]
codes=collections.Counter(l[0] for l in ls)
ts=sorted(float(l[1]) for l in ls)
print("  respostas:", dict(codes))
if ts:
    print(f"  tempo  min={ts[0]:.2f}s  mediana={ts[len(ts)//2]:.2f}s  p90={ts[int(len(ts)*0.9)]:.2f}s  max={ts[-1]:.2f}s")
    print(f"  acima de 5s: {sum(1 for t in ts if t>5)} de {len(ts)}")
PY
echo "--- BANCO (durante) ---"
python3 - "$DB" <<'PY'
import sys,os
p=sys.argv[1]
if not os.path.exists(p):
    print("  (arquivo de saida nao existe — nada foi gravado)"); raise SystemExit
tr=[];calc=[]
for l in open(p):
    l=l.strip()
    if '|' in l:
        a,b=l.split('|');
        try: tr.append(int(a)); calc.append(int(b))
        except: pass
if tr:
    print(f"  Threads_running: pico={max(tr)}  media={sum(tr)/len(tr):.1f}  amostras={len(tr)}")
    print(f"  consultas com SQL_CALC_FOUND_ROWS vistas: pico={max(calc)}  total_amostrado={sum(calc)}")
else:
    print("  (sem amostras)")
PY
echo "--- arquivos ---"
ls -l "$HTTP" "$DB" 2>/dev/null | awk '{print "  "$5" bytes  "$9}'
exit $FALHOU
