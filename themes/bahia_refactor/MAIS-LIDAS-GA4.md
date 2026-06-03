# Mais Lidas — Integração com Google Analytics 4

Documentação da mudança que passou a alimentar a seção **"+ mais lidas"** da
home/sidebar com dados reais do GA4 (via Site Kit), substituindo o contador
interno acumulado.

---

## 1. Motivação

A função `mais_lidas2()` (`functions.php:1079`) usava o meta `views`
incrementado em `single_web.php:15` e `single_mobile.php:15`. Problemas:

- `views` é **acumulado vitalício** — favorece matérias antigas com muita
  audiência prévia.
- O `WHERE post_date BETWEEN -2 days AND now()` filtra apenas a **data de
  publicação**, não a leitura.
- Sem filtro de bots, sem deduplicação, sem janela temporal real de leitura.
- Resultado: ranking divergente do GA4.

Decisão (conversa de 2026-06-03): usar o **GA4 via Site Kit** como fonte
primária, janela de **48h**, mantendo o SQL legado como fallback.

---

## 2. Arquivos alterados

| Arquivo | Mudança |
|---|---|
| `themes/bahia_refactor/mais-lidas-ga4.php` | **Novo.** Cron + fetch GA4 + cache em transient. |
| `themes/bahia_refactor/functions.php:2` | `require_once` do novo arquivo. |
| `themes/bahia_refactor/functions.php:1079` (`mais_lidas2`) | Lê transient GA4 primeiro; cai no SQL legado se vazio. |

`mais_lidas()` (linha 1041) **não foi alterada** — só `mais_lidas2()` é usada
pela sidebar (`sidebar-home2.php:7`). Se houver outros pontos consumindo
`mais_lidas()`, avaliar replicar o mesmo padrão.

---

## 3. Como funciona

```
[WP-Cron a cada 30 min]
        │
        ▼
  refresh() em mais-lidas-ga4.php
        │
        │ 1. Instancia Site Kit Context/Options/Auth
        │ 2. Pega owner_id do Site Kit
        │ 3. switch_user(owner) → carrega OAuth token
        │ 4. Modules->get_module('analytics-4')->get_data('report', …)
        │    - métrica: screenPageViews
        │    - dimensão: pagePath
        │    - janela: últimos 2 dias (48h)
        │    - ordem: screenPageViews DESC
        │    - limite: 80 linhas
        │ 5. url_to_postid() em cada pagePath
        │ 6. Filtra publish, descarta page/attachment/acf/mais_noticias
        │ 7. set_transient('bahia_mais_lidas_ga4_v1', $ids, 6h)
        ▼
[Sidebar carrega]
  mais_lidas2() → get_transient → WP_Query post__in (orderby=post__in)
        │
        └─ se transient vazio → SQL legado (fallback)
```

### Constantes (em `mais-lidas-ga4.php`)

| Constante | Valor | Significado |
|---|---|---|
| `TRANSIENT_KEY` | `bahia_mais_lidas_ga4_v1` | Chave do cache. |
| `CRON_HOOK` | `bahia_mais_lidas_ga4_refresh` | Nome do evento de cron. |
| `CRON_INTERVAL` | `bahia_thirty_minutes` (30 min) | Frequência da atualização. |
| `WINDOW_DAYS` | `2` | Janela de leitura no GA4 (48h). |
| `FETCH_LIMIT` | `80` | Linhas pedidas ao GA4. |
| `STORE_LIMIT` | `30` | IDs guardados no transient. |
| `TTL_SECONDS` | `21600` (6h) | TTL do transient (tolera falha do cron por algumas horas). |

---

## 4. Verificação em produção

### 4.1 Pré-requisitos

1. **Site Kit autenticado** e módulo **Analytics_4 conectado** com
   `propertyID` válido. Conferir em `WP Admin → Site Kit → Settings`.
2. **WP-Cron funcionando** (ou cron de sistema chamando `wp-cron.php`).
3. Plugin Site Kit ativo (a integração detecta `\Google\Site_Kit\Plugin` —
   se ausente, cai silenciosamente no fallback).

### 4.2 Verificar se a cron foi agendada

```bash
wp cron event list | grep bahia_mais_lidas_ga4_refresh
```

Deve listar o evento com schedule `bahia_thirty_minutes`. Se não aparecer,
acessar qualquer URL do site (o `add_action('init', …)` agenda na primeira
requisição).

### 4.3 Forçar uma atualização

```bash
wp cron event run bahia_mais_lidas_ga4_refresh
```

### 4.4 Conferir o cache

```bash
wp transient get bahia_mais_lidas_ga4_v1
```

Deve retornar um array serializado de IDs de post (ex.: `[12345, 12340, …]`),
ordenado por audiência decrescente.

### 4.5 Conferir visualmente

Recarregar a home e inspecionar a sidebar "+ mais lidas". Comparar os títulos
com o relatório do GA4 (Reports → Engagement → Pages and screens, últimas
48h). Devem coincidir (descontando home e páginas excluídas).

### 4.6 Logs de erro

Se `WP_DEBUG_LOG = true`, falhas são logadas com prefixo `[mais-lidas-ga4]`
em `wp-content/debug.log`. Falhas comuns:

| Sintoma | Causa provável |
|---|---|
| Transient nunca aparece, sem erro | Site Kit sem owner / Analytics_4 não conectado. |
| `invalid_grant` no log | OAuth token expirado e sem refresh. Reconectar Site Kit. |
| Transient aparece mas vazio (`[]`) | Nenhuma pagePath bateu com `url_to_postid` — verificar permalinks. |
| Sidebar continua mostrando SQL antigo | Cron não rodou / transient ainda não populado / Site Kit ausente. |

---

## 5. Pontos de ajuste comuns

### 5.1 Mudar a janela (24h, 48h, 7 dias, …)

Em `mais-lidas-ga4.php`, alterar `WINDOW_DAYS`. Para janela de horas
(ex.: 24h exatas), trocar `startDate`/`endDate` por timestamps no formato
GA4 e usar a propriedade `hour`/`dateHour` se necessário.

### 5.2 Mudar a frequência da cron

Alterar o `interval` em `cron_schedules` (linha ~25) e o `CRON_INTERVAL`.
Após alterar, **desagendar e reagendar** o evento:

```bash
wp cron event delete bahia_mais_lidas_ga4_refresh
# próxima requisição reagenda no novo intervalo
```

### 5.3 Mudar tipos de post excluídos

Editar o array `$excluded` em `refresh()`.

### 5.4 Forçar invalidação manual

```bash
wp transient delete bahia_mais_lidas_ga4_v1
wp cron event run bahia_mais_lidas_ga4_refresh
```

### 5.5 Desativar a integração e voltar ao SQL antigo

Comentar a linha `require_once __DIR__ . '/mais-lidas-ga4.php';` em
`functions.php:2` e rodar `wp transient delete bahia_mais_lidas_ga4_v1`.
A função `mais_lidas2()` cai automaticamente no SQL legado.

---

## 6. Riscos conhecidos e próximos passos sugeridos

- **Dependência de WP-Cron**: se o site tem cron desativado
  (`DISABLE_WP_CRON`), agendar via cron de sistema.
- **Quotas GA4**: 30 min × 1 requisição/dia = ~48 req/dia, bem abaixo do
  limite. Cache de 6h garante operação mesmo sob bloqueio temporário.
- **`mais_lidas()`** (linha 1041) ainda usa o contador antigo. Se for usada
  em algum template, replicar o mesmo padrão.
- **Contador `views`**: continua sendo incrementado em `single_web.php` /
  `single_mobile.php`. Pode ser removido futuramente se nenhum outro
  consumo depender dele (verificar antes).
- A página `/` (home) é descartada pelo filtro `'/' === $path`. Se o GA4
  reportar caminhos com querystring, eles caem em `url_to_postid` e podem
  não resolver — observar e, se necessário, normalizar (`strtok($path,'?')`).
