#!/bin/bash
# Sonda de indisponibilidade HTTP, de 1 em 1 segundo.
#
# uso: sonda-http.sh <url> <segundos> <arquivo-de-saida> [limiar_lento]
#
# Saida TSV: <epoch>\t<0|1>\t<codigo>\t<tempo>\t<ok|lento|erro|timeout>
#
# ############################################################################
# POR QUE O TIMEOUT E GENEROSO E O LIMIAR E SEPARADO  (HANDOVER secao 24)
# ############################################################################
# A versao anterior usava `--max-time 5` e classificava tudo que estourasse como
# falha. Em producao, em 29/08/2026, isso produziu 11 "indisponibilidades" que
# eram apenas requisicoes acima de 5s durante o aquecimento de cache — todas
# coladas em 5,00s, o proprio teto.
#
# O agravante: a sonda DESISTIA aos 5s, entao nao havia como saber depois se
# aquelas requisicoes responderiam em 6s ou nunca. O instrumento nao errava o
# numero — ele APAGAVA o dado que distinguiria "lento" de "fora".
#
# Agora: --max-time generoso (30s por padrao), e a lentidao vira uma CLASSE
# propria registrada a parte. Falha e so o que nao respondeu ou respondeu com
# codigo de erro. Lento continua sendo resposta.

URL="${1:?url}"
DUR="${2:-1200}"
OUT="${3:?arquivo de saida}"
LIMIAR="${4:-5}"          # segundos acima dos quais a resposta e "lenta"
MAXTIME="${SONDA_MAXTIME:-30}"

: > "$OUT" || { echo "ERRO: nao consigo escrever em $OUT"; exit 1; }
FIM=$(( $(date +%s) + DUR ))

while [ "$(date +%s)" -lt "$FIM" ]; do
  T0=$(date +%s.%N)
  R=$(curl -s -o /dev/null -w "%{http_code} %{time_total}" --max-time "$MAXTIME" "$URL" 2>/dev/null)
  CODE="${R%% *}"; TEMPO="${R##* }"
  [ -z "$CODE" ] && CODE=000
  [ -z "$TEMPO" ] && TEMPO=0

  case "$CODE" in
    200|301|302)
      OK=1
      CLASSE=$(awk -v t="$TEMPO" -v l="$LIMIAR" 'BEGIN{print (t>l)?"lento":"ok"}')
      ;;
    000)
      # Nao respondeu dentro de MAXTIME, que e generoso: aqui e queda de verdade.
      OK=0; CLASSE=timeout ;;
    *)
      OK=0; CLASSE=erro ;;
  esac

  printf "%s\t%s\t%s\t%s\t%s\n" "$(date +%s)" "$OK" "$CODE" "$TEMPO" "$CLASSE" >> "$OUT"

  GASTO=$(python3 -c "print(max(0,1-( $(date +%s.%N) - $T0 )))" 2>/dev/null || echo 0)
  python3 -c "import time;time.sleep($GASTO)" 2>/dev/null
done
