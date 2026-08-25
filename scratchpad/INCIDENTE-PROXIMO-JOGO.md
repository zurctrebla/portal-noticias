# "Próximo jogo" do Bahia sumiu em produção — diagnóstico

2026-08-25, ~18:30 UTC. **Nada foi alterado.** Levantamento apenas.

## Não foi o deploy de hoje

Os quatro commits de hoje tocaram `bahia-cores-ui.php`, `bahia-image-size-fallback.php` e
documentos de `scratchpad/`. **Nenhum arquivo de futebol.**
`bahia-futebol-display.php` e `bahia-clubes-sidebar.php` não mudam desde `e66f2d01`, de 11/08.

## O que está acontecendo

| | produção | homolog |
|---|---|---|
| box do EC Bahia | "Último jogo" apenas | "Último jogo" + "Próximo jogo" |
| box do EC Vitória | completo | completo |

Estado dos transients em **produção**:

```
_transient_bahia_fut_ecvitoria_v1   ultimo=OK   proximo=OK
_transient_bahia_fut_ecbahia_v1     ultimo=OK   proximo=NULL   <---
```

E a API tem o jogo:

```
GET /v4/teams/1777/matches?status=SCHEDULED&limit=1   ->  HTTP 200
Bahia x Internacional — 2026-08-30T22:30:00Z — Campeonato Brasileiro Série A
```

Ou seja: **o dado existe na fonte; o que está no cache de produção é uma falha, gravada como se
fosse "não há jogo".**

## A causa: falha de API cacheada como ausência de dado

Em `bahia-futebol-display.php`, `bahia_fut_clube_jogos_dados()`:

```php
$fetch = function ($status) use ($api_token, $team_id) {
    $resp = wp_remote_get(...);
    if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
        return null;                      // <-- falha vira null
    }
    ...
};

$ultimo_raw  = $fetch('FINISHED');
$proximo_raw = $fetch('SCHEDULED');       // <-- se ESTA falhar, so ela vira null

$dados = array('ultimo' => $format($ultimo_raw), 'proximo' => $format($proximo_raw));
set_transient($cache_key, $dados, 30 * MINUTE_IN_SECONDS);   // <-- e o null e CACHEADO
```

São **duas chamadas independentes** por clube. Quando a segunda falha e a primeira não, sai
exatamente o que se vê: último jogo presente, próximo jogo ausente — e o resultado ruim fica
**30 minutos** no ar.

O render trata `null` como "não há jogo" e simplesmente não desenha a seção. Não há erro em log,
não há aviso: **a falha é indistinguível de um clube sem próximo jogo marcado.**

## Por que a chamada falha: um token de 10 req/min para tudo

```
x-requests-available-minute: 9      (após 1 chamada — o teto é 10/min)
```

O mesmo token `f5c2e920…` está em **três** mu-plugins:

```
api_brasileirao.php
api_copa_mundo.php
bahia-futebol-display.php
```

e roda nos **dois ambientes**, prod e homolog, contra o mesmo teto. Uma renovação completa dos
boxes gasta 4 chamadas (2 clubes × 2 status). Não há trava de concorrência: quando o transient
expira, **todo request que chega ao PHP naquele instante dispara o próprio conjunto de chamadas**
— com 5 pods em produção, é fácil passar de 10 num minuto e receber 429.

Isso também explica a divergência com homolog: os dois ambientes têm transients independentes,
que expiram em momentos diferentes. Homolog renovou numa janela em que havia cota; produção não.

## Situação agora

O transient do Bahia expira **19:00:03 UTC** (16:00 em Salvador). **O box volta sozinho** na
próxima renovação — desde que a chamada tenha sucesso. Se falhar de novo, são mais 30 minutos.
É por isso que o problema parece intermitente.

## Correções propostas — nenhuma aplicada

### 1. Não cachear falha (é o defeito de verdade)

Distinguir "a API respondeu que não há jogo" de "a chamada falhou". Fazer `$fetch` devolver
`false` em falha e `null` em ausência legítima, e então:

- se **qualquer** das duas chamadas falhou, **não gravar transient longo** — gravar 2 minutos,
  para não martelar a API e tentar de novo logo;
- melhor ainda: **manter o último valor bom**. Guardar uma cópia sem expiração
  (`bahia_fut_ecbahia_v1_ultimo_bom`) e servir ela quando a chamada falhar. O box nunca some por
  falha de rede; no pior caso mostra o jogo anterior.

### 2. Uma chamada em vez de duas por clube

`?status=FINISHED` e `?status=SCHEDULED` podem virar uma consulta só por clube com janela de
datas, cortando o consumo pela metade (de 4 para 2 chamadas por renovação).

### 3. Trava contra estouro simultâneo

Um `add_option`/lock curto antes de chamar a API, para que só um processo renove e os demais
sirvam o valor velho enquanto isso.

### 4. Separar o token de homolog

Homolog consome a mesma cota de produção sem necessidade. Ou um token próprio, ou homolog usando
dado congelado.

## Remédio imediato, se não quiser esperar os 23 minutos

Apagar só o transient ruim faz a próxima carga refazer a chamada:

```php
delete_transient('bahia_fut_ecbahia_v1');
```

É operação de cache, reversível por natureza e sem efeito em banco de conteúdo. **Não executei —
produção só com autorização explícita.** E vale lembrar que, sem a correção 1, isso é paliativo:
o próximo 429 repõe o problema.

---

# Correção aplicada em HOMOLOG (produção intocada)

`mu-plugins/bahia-futebol-display.php`. Quatro mudanças, todas na obtenção do dado — o render
não foi tocado.

## 1. Falha deixou de ser confundida com ausência

`$fetch` agora tem três retornos, e a distinção é o ponto da correção:

```
array -> a API devolveu um jogo
null  -> a API respondeu 200 e disse que NÃO há jogo
false -> a CHAMADA FALHOU (rede, 429, ou 200 com corpo inesperado)
```

Antes, os dois últimos casos eram o mesmo `null`, e esse `null` ia para um transient de 30 min.

## 2. Cache de emergência: o último valor bom

Quando as duas chamadas dão certo, o resultado é gravado também em
`<chave>_bom` (`update_option`, autoload `no`), sem expiração. Se depois uma chamada falhar, o
box passa a servir esse valor em vez de sumir.

## 3. TTL curto em caso de falha

Falha grava transient de **2 minutos**, não 30. O site tenta de novo logo, em vez de congelar o
erro por meia hora.

## 4. Trava contra renovação simultânea

`bahia_fut_trava()` usa `add_option()` — atômico pelo índice único em `option_name`. Quem não
pega a trava serve o último bom em vez de chamar a API. Trava presa há mais de 60 s é assumida,
para um processo morto não travar a renovação para sempre.

## E uma proteção que a correção 2 exigiu

Servir valor velho traz um risco novo: o "próximo jogo" guardado pode já ter sido disputado, e
mostrar jogo passado como futuro seria pior que não mostrar nada. Por isso o formato agora grava
`ts` (timestamp UTC) e `bahia_fut_descarta_proximo_vencido()` remove o "próximo" com mais de 3 h
de atraso — a margem existe para o box não sumir durante a partida.

Valores gravados antes desta versão não têm `ts`; nesses casos o dado é mantido, que é o
comportamento antigo.

## Validação em homolog

| teste | resultado |
|---|---|
| caminho normal | `ultimo=OK proximo=OK` — Bahia × Internacional, 30/08 19:30, com `ts` |
| `_bom` gravado | sim, `autoload=off` |
| trava liberada no fim | sim |
| **falha de API com `_bom`** | **`ultimo=OK proximo=OK`** servidos do cache de emergência; TTL 119 s |
| falha de API sem `_bom` | degrada para NULL, mas com TTL de 120 s (antes: 1800 s) |
| trava: 2º processo concorrente | bloqueado |
| trava presa há 120 s | assumida |
| descarte: jogo futuro | mantido |
| descarte: jogo começou há 1 h | mantido (margem de 3 h) |
| descarte: jogo há 2 dias | descartado |
| descarte: valor antigo sem `ts` | mantido |
| página de homolog | 2 boxes, **os dois com "Próximo jogo"** |

O teste de falha foi feito trocando o token por um inválido no pod e restaurando em seguida.

## O que NÃO foi feito

- **Uma chamada por clube em vez de duas.** Exigiria trocar o filtro de `status` por janela de
  datas e mexer na normalização — mudança maior, sem relação com o defeito. A trava já corta o
  grosso do consumo.
- **Token próprio para homolog.** Continua dividindo a cota de 10/min com produção. Vale fazer,
  mas é decisão de configuração, não de código.
- **Nada em produção.** O transient ruim de lá vence às 19:00:03 UTC e o box volta sozinho.
