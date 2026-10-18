#!/bin/bash
# Teste de carga em homolog: URLs FRIAS, 30 simultaneas, medindo Threads_running DURANTE.
# uso: carga.sh <rotulo>
ROT="${1:-teste}"
S=/private/tmp/claude-501/-Users-albertcruz-Projects-BAHIABA-wp-content/076a2b37-27dc-4ecf-b4d4-8764bd6b55c8/scratchpad
CTX="arn:aws:eks:us-east-1:774710032593:cluster/bahia-eks-homolog"
NS=bahia-wordpress
POD=$(kubectl --context "$CTX" -n $NS get pods -l app=wordpress -o jsonpath='{.items[0].metadata.name}')
B=https://hml.bahia.ba
STAMP=$RANDOM$RANDOM

# 30 URLs frias: home, archives de editoria e busca, cada uma com cache-buster proprio
URLS=()
for i in $(seq 1 10);  do URLS+=("$B/?cb=$STAMP-h$i"); done
for e in politica salvador esporte entretenimento economia municipios justica mundo bahia brasil; do
  URLS+=("$B/$e/?cb=$STAMP-a"); done
for t in bahia salvador carnaval eleicao praia lula chuva festa saude escola; do
  URLS+=("$B/?s=$t&cb=$STAMP-s"); done

echo "=== CARGA [$ROT] — ${#URLS[@]} requisicoes simultaneas, URLs frias ==="
rm -f $S/carga-$ROT-*.txt

# monitor do banco DURANTE
( for i in $(seq 1 24); do
    kubectl --context "$CTX" -n $NS exec $POD -c wordpress -- php -r '
      require_once "/var/www/html/wp-load.php"; global $wpdb;
      $r=$wpdb->get_row("SHOW GLOBAL STATUS LIKE \"Threads_running\"");
      $pl=$wpdb->get_results("SHOW FULL PROCESSLIST");
      $calc=0; foreach($pl as $p) if (stripos((string)$p->Info,"SQL_CALC_FOUND_ROWS")!==false) $calc++;
      printf("%s|%s\n", $r->Value, $calc);' 2>/dev/null
    sleep 2
  done ) > $S/carga-$ROT-db.txt 2>&1 &
MONPID=$!

# a carga
for u in "${URLS[@]}"; do
  ( code=$(curl -s -o /dev/null -w "%{http_code} %{time_total}" --max-time 70 "$u"); echo "$code $u" ) >> $S/carga-$ROT-http.txt &
done
wait $(jobs -p | grep -v $MONPID) 2>/dev/null
sleep 3
kill $MONPID 2>/dev/null; wait $MONPID 2>/dev/null

echo "--- HTTP ---"
python3 - "$S/carga-$ROT-http.txt" <<'PY'
import sys,collections
ls=[l.split() for l in open(sys.argv[1]) if l.strip()]
codes=collections.Counter(l[0] for l in ls)
ts=sorted(float(l[1]) for l in ls)
print("  respostas:", dict(codes))
if ts:
    print(f"  tempo  min={ts[0]:.2f}s  mediana={ts[len(ts)//2]:.2f}s  p90={ts[int(len(ts)*0.9)]:.2f}s  max={ts[-1]:.2f}s")
    print(f"  acima de 5s: {sum(1 for t in ts if t>5)} de {len(ts)}")
PY
echo "--- BANCO (durante) ---"
python3 - "$S/carga-$ROT-db.txt" <<'PY'
import sys
tr=[];calc=[]
for l in open(sys.argv[1]):
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
