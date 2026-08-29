#!/bin/bash
# Sonda de indisponibilidade HTTP, de 1 em 1 segundo, com timeout curto.
#
# uso: sonda-http.sh <url> <segundos> <arquivo-de-saida>
#
# Saida TSV: <epoch>\t<0|1>\t<codigo>\t<tempo>
# Um rollout com maxSurge=0 derruba o pod antes de subir o novo: e essa janela
# que esta sonda mede. Timeout de 5s para nao mascarar queda como lentidao.

URL="${1:?url}"
DUR="${2:-1200}"
OUT="${3:?arquivo de saida}"

: > "$OUT" || { echo "ERRO: nao consigo escrever em $OUT"; exit 1; }
FIM=$(( $(date +%s) + DUR ))

while [ "$(date +%s)" -lt "$FIM" ]; do
  T0=$(date +%s.%N)
  R=$(curl -s -o /dev/null -w "%{http_code} %{time_total}" --max-time 5 "$URL" 2>/dev/null)
  CODE="${R%% *}"; TEMPO="${R##* }"
  case "$CODE" in
    200|301|302) OK=1 ;;
    *)           OK=0 ;;
  esac
  printf "%s\t%s\t%s\t%s\n" "$(date +%s)" "$OK" "${CODE:-000}" "${TEMPO:-0}" >> "$OUT"
  # completa 1 segundo
  GASTO=$(python3 -c "print(max(0,1-( $(date +%s.%N) - $T0 )))" 2>/dev/null || echo 0)
  python3 -c "import time;time.sleep($GASTO)" 2>/dev/null
done
