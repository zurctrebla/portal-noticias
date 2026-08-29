#!/bin/bash
# Driver da sonda SQL — UPGRADE-MYSQL.md §1.6.
#
# uso:  sonda-sql.sh <rotulo> <host-do-banco>
#   ex: sonda-sql.sh antes-80 rds-bahiaba-teste84.xxxx.us-east-1.rds.amazonaws.com
#
# Dispara N conexoes concorrentes contra o banco alvo, de dentro dos pods de PRODUCAO
# (unica rede que alcanca a instancia de teste, por desenho do security group), e mede
# mediana, p90, maximo e Threads_running. Sem PHP do WordPress, sem nginx, sem cache.
#
# A SENHA NUNCA SAI DO POD: os workers leem $WORDPRESS_DB_PASSWORD la dentro.
#
# PORTAO DE CONTAGEM (HANDOVER secao 0 e 16): confere que o numero de medicoes gravadas
# bate com o esperado (workers x rodadas x 22 consultas) e sai 1 se nao bater.

set -uo pipefail

ROT="${1:?rotulo}"
HOST="${2:?host do banco}"
CTX="${SONDA_CTX:-arn:aws:eks:us-east-1:774710032593:cluster/bahia-eks-prod}"
NS="${SONDA_NS:-bahia-wordpress}"
W="${SONDA_WORKERS:-10}"
R="${SONDA_ROUNDS:-3}"
DIR="$(cd "$(dirname "$0")" && pwd)"
S="${SONDA_OUT:-$DIR/carga-saida}"
PHP="$DIR/sonda-sql.php"

mkdir -p "$S" || { echo "ERRO: nao criei $S"; exit 1; }
: > "$S/.escrita-teste" 2>/dev/null || { echo "ERRO: $S nao aceita escrita. Nada medido."; exit 1; }
rm -f "$S/.escrita-teste"
[ -r "$PHP" ] || { echo "ERRO: nao achei $PHP"; exit 1; }

# bash 3.2 do macOS nao tem mapfile — laco portatil.
#
# ORDENADO POR IDADE, MAIS VELHO PRIMEIRO, e usando so os N mais estaveis. O HPA de
# producao (min2/max5) troca pod no meio da corrida: quando isso acontece o `kubectl exec`
# morre, o worker some e a saida dele desaparece SEM erro de consulta — o portao acusa
# contagem baixa e a causa fica invisivel. Pod recem-nascido e o primeiro a ser cortado
# num scale-down, entao os velhos sao os seguros.
PODS=()
while IFS= read -r linha; do
  [ -n "$linha" ] && PODS+=("${linha%% *}")
done < <(kubectl --context "$CTX" -n "$NS" get pods -l app=wordpress \
           --sort-by=.metadata.creationTimestamp \
           -o jsonpath='{range .items[*]}{.metadata.name}{"\n"}{end}' 2>/dev/null)
[ "${#PODS[@]}" -gt 0 ] || { echo "ERRO: nenhum pod app=wordpress. Nada medido."; exit 1; }
MAXPODS="${SONDA_MAXPODS:-3}"
[ "${#PODS[@]}" -gt "$MAXPODS" ] && PODS=("${PODS[@]:0:$MAXPODS}")

OUT="$S/sonda-$ROT.tsv"; MON="$S/sonda-$ROT-threads.txt"
rm -f "$OUT" "$MON"
echo "=== SONDA SQL [$ROT] — $W conexoes x $R rodadas ==="
echo "    alvo:  $HOST"
echo "    pods:  ${#PODS[@]} (${PODS[0]} ...)"

# Monitor de Threads_running, de dentro de um pod, amostrando ~2x/s.
kubectl --context "$CTX" -n "$NS" exec "${PODS[0]}" -c wordpress -- sh -c "
  SONDA_HOST='$HOST' php -r '
    \$m=new mysqli(getenv(\"SONDA_HOST\"),getenv(\"WORDPRESS_DB_USER\"),
                   getenv(\"WORDPRESS_DB_PASSWORD\"),getenv(\"WORDPRESS_DB_NAME\"));
    if(\$m->connect_errno){exit(1);}
    \$fim=microtime(true)+180;
    while(microtime(true)<\$fim){
      \$r=\$m->query(\"SHOW GLOBAL STATUS LIKE \\\"Threads_running\\\"\");
      \$v=\$r->fetch_row()[1]; echo \$v.\"\n\"; usleep(500000);
    }' " > "$MON" 2>/dev/null &
MONPID=$!

WPIDS=()
for i in $(seq 1 "$W"); do
  POD="${PODS[$(( (i-1) % ${#PODS[@]} ))]}"
  kubectl --context "$CTX" -n "$NS" exec -i "$POD" -c wordpress -- sh -c "
      SONDA_HOST='$HOST' SONDA_USER=\$WORDPRESS_DB_USER SONDA_PASS=\$WORDPRESS_DB_PASSWORD \
      SONDA_DB=\$WORDPRESS_DB_NAME SONDA_ROUNDS=$R SONDA_ID=$i php" < "$PHP" > "$OUT.w$i" 2>"$OUT.e$i" &
  WPIDS+=("$!")
done
for p in "${WPIDS[@]}"; do wait "$p"; done
kill "$MONPID" 2>/dev/null; wait "$MONPID" 2>/dev/null
# Cada worker escreve o seu arquivo: `>>` concorrente de 10 processos pode entrelacar
# linha, e uma linha partida vira contagem errada — exatamente o que o portao deveria pegar.
ESPERADO_W=$(( R * 22 ))
for i in $(seq 1 "$W"); do
  n=$(grep . "$OUT.w$i" 2>/dev/null | wc -l | tr -d ' ')
  [ "$n" -ne "$ESPERADO_W" ] && echo "  worker $i: $n de $ESPERADO_W linhas — TRUNCADO ($(head -1 "$OUT.e$i" 2>/dev/null))"
done
cat "$OUT".w* > "$OUT" 2>/dev/null; rm -f "$OUT".w* "$OUT".e*

# ---- PORTAO DE CONTAGEM ----
ESPERADO=$(( W * R * 22 ))   # 10 buscas + 10 archives + 1 home + 1 contagem = 22
# `grep -c` sai com codigo 1 quando nao acha nada, entao `|| echo 0` acrescenta um SEGUNDO
# zero e a variavel vira "0\n0" — que quebra o teste numerico logo abaixo. Contar com wc.
GRAVADO=$(grep . "$OUT" 2>/dev/null | wc -l | tr -d ' ')
ERROS=$(grep 'ERRO' "$OUT" 2>/dev/null | wc -l | tr -d ' ')
AMOSTRAS=$(grep . "$MON" 2>/dev/null | wc -l | tr -d ' ')
echo "--- portao: esperado=$ESPERADO gravado=$GRAVADO erros=$ERROS amostras_threads=$AMOSTRAS"

python3 - "$OUT" "$MON" <<'PY'
import sys, collections
med = collections.defaultdict(list)
for ln in open(sys.argv[1]):
    p = ln.rstrip("\n").split("\t")
    if len(p) == 3 and p[1] != "ERRO":
        med[p[0]].append(float(p[1]))
def q(v, f):
    v = sorted(v); k = (len(v)-1)*f; lo = int(k)
    return v[lo] if lo+1 >= len(v) else v[lo] + (k-lo)*(v[lo+1]-v[lo])
print(f"{'classe':10} {'n':>5} {'mediana':>10} {'p90':>10} {'maximo':>10}")
for c in ("busca","archive","home","contagem"):
    if med[c]:
        v = med[c]
        print(f"{c:10} {len(v):5d} {q(v,.5):9.1f}ms {q(v,.9):9.1f}ms {max(v):9.1f}ms")
try:
    t = [int(x) for x in open(sys.argv[2]) if x.strip().isdigit()]
    if t: print(f"\nThreads_running: amostras={len(t)} pico={max(t)} mediana={sorted(t)[len(t)//2]}")
except FileNotFoundError:
    pass
PY

[ "$GRAVADO" -eq "$ESPERADO" ] || { echo "PORTAO REPROVADO: contagem nao bate."; exit 1; }
[ "$AMOSTRAS" -ge 10 ] || { echo "PORTAO REPROVADO: menos de 10 amostras de Threads_running."; exit 1; }
echo "PORTAO OK"
