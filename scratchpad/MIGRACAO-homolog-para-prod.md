# Migração de homolog para produção

**Escrito em:** 11/08/2026, ao fim da rodada 5
**Origem:** hml.bahia.ba (cluster `bahia-eks-homolog`, RDS de homolog)
**Destino:** bahia.ba (cluster de produção, **RDS separado**)

> **Renumeração de IDs — 16/08/2026.** Os registros nascidos em homolog (templates, páginas,
> anexos e itens de menu) foram movidos para a faixa **9.000.001+** pela fórmula
> `novo = 9.000.000 + (antigo − 547.290)`, para não colidirem com os IDs que produção passou a
> usar desde o retrato de 28/07. **Os IDs neste documento já estão atualizados.** O mapa
> completo antigo→novo está na tabela `wp_bahia_renum_map` (117 linhas), que **não deve ser
> apagada**. Plano e registro da operação em `RENUMERACAO-homolog.md`.

## Por que este documento existe

Quatro rodadas de ajuste (2 a 5) mexeram em duas camadas diferentes:

- **Código** (`mu-plugins/`, tema): viaja num `git push` para a branch `main`. Resolvido.
- **Banco** (templates do tagDiv, conteúdo de páginas, opções do tema, menus, anexos): **não viaja em git nenhum.** O RDS de produção é outro.

Se a janela de manutenção fizer só o merge `staging → main`, o site de produção sobe com o
código novo apontando para dados velhos: rodapé em inglês, blocos da home fora de ordem,
templates do demo Magazine PRO no lugar dos ajustados, e o 404 voltando a mandar o visitante
para `demo.tagdiv.com`.

Em 12/08/2026 apareceu uma **terceira camada**: a **infraestrutura** (`infra-bahiaba`), que
mudou de forma relevante para esta migração — a chave do cache do nginx passou a distinguir
dispositivo, e os manifestos foram separados por ambiente. **Ler a seção 8 antes de executar
qualquer coisa**: ela muda caminhos de arquivo citados em outros documentos e contém uma
verificação que só faz sentido no momento da virada do tema.

Este documento lista o que precisa ser transportado, em que ordem, e como voltar atrás.

---

## 1. Inventário do que precisa ser transportado

Levantado no banco de homolog em 11/08/2026. Os tamanhos ajudam a conferir se o transporte
chegou inteiro.

### 1.1 Templates do tagDiv (`post_type = tdb_templates`)

Todos são posts publicados. **Só quatro destes realmente renderizam** — ver
`AUDITORIA-templates.md`. Os demais viajam por precaução, ou não viajam, conforme a decisão
registrada na seção 5.

| ID | Título | Renderiza? | Modificado |
|----|--------|-----------|-----------|
| **9000124** | Header Template - Magazine PRO | **Sim** — cabeçalho de todo o site | 10/08 17:06 |
| **9000126** | Footer - Magazine PRO | **Sim** — rodapé de todo o site | 10/08 16:07 |
| **9000140** | 404 Template - Magazine PRO | **Sim** — página de erro | 11/08 10:10 |
| **9000132** | Author Template - Magazine PRO | **Sim** — /author/&lt;slug&gt;/ | 05/08 16:01 |
| 9000138 | Search Template - Magazine PRO | Sim — /?s= | 05/08 16:01 |
| 9000128 | Category Template - Magazine PRO | Não (archives usam PHP do tema) | 05/08 16:01 |
| 9000130 | Single Post Template - Magazine PRO | Não (single usa PHP do plugin) | 05/08 16:01 |
| 9000134 | Tag Template - Magazine PRO | Não (URLs de tag dão 404 — ver 6.3) | 05/08 16:01 |
| 9000136 | Date Template - Magazine PRO | Não (URLs de data dão 404) | 05/08 16:01 |
| 9000001 | Search Template - Default PRO | **Código morto** | 05/08 11:20 |
| 9000007 | Single Post Template - Default PRO | **Código morto** | 05/08 11:20 |
| 9000009 | Footer Template - Default PRO | **Código morto** | 05/08 11:20 |
| 9000011 | Header Template - Default PRO | **Código morto** | 05/08 11:20 |

O header **9000124** tem `postmeta` que precisa acompanhar o post:
`tdb_template_type`, `tdc_header_template_id`, `tdc_google_fonts_settings`, `tdc_icon_fonts`,
`header_mobile_menu_id` (hoje **vazio**). O conteúdo tem 34.096 bytes.

### 1.2 Páginas

| ID | O que é | Observação |
|----|---------|-----------|
| **9000142** | Home | É a *front page* (`page_on_front = 9000142`, `show_on_front = page`). Todo o layout da home é o `post_content` desta página. |
| **9000079** | Quem Somos | `/quem-somos/`. Contém a equipe, com fotos por ID de anexo. |
| **477** | Últimas Notícias | `/ultimas-noticias/`. Um único shortcode `[td_flex_block_1 ...]` com `installed_post_types`. |

### 1.3 Opções (`wp_options`)

| Opção | O que carrega | Migra? |
|-------|---------------|--------|
| **`td_011`** | Opções do tema Newspaper. **Crítica** — ver 1.3.1 | Sim, mas por chave, nunca inteira |
| **`wpseo_titles`** | Títulos e meta descrições do Yoast por tipo de conteúdo | Sim |
| `page_on_front`, `show_on_front` | Apontam a home para 9000142 | Sim (ajustar ao ID de destino) |
| `bahia_editorias_cpt_flushed` | Controle de flush do mu-plugin | **Não** — deixar o destino gerar |
| `bahia_logo_snapshot` | URLs do logo usadas pelo mu-plugin | Conferir; URLs de CDN são iguais nos dois ambientes |
| `bahia_*_backup_*` | Backups das rodadas | **Não migrar** |

#### 1.3.1 `td_011`: migrar por chave, nunca a opção inteira

`td_011` mistura três coisas. Copiar a opção inteira de homolog para produção sobrescreveria
licença, versão de tema e caches de atualização.

**Chaves que PRECISAM migrar** (conferidas no valor cru do banco, sem filtros):

```
tds_data_time_format   = l, j \d\e F \d\e Y     <- data em português
tdb_header_template    = tdb_template_9000124
tdb_footer_template    = tdb_template_9000126
tdb_404_template       = tdb_template_9000140
tdb_author_template    = tdb_template_9000132
tdb_search_template    = tdb_template_9000138
tdb_category_template  = tdb_template_9000128     (aponta para template que não renderiza)
td_default_site_post_template = tdb_template_9000130  (idem)
tdb_tag_template       = tdb_template_9000134     (idem)
tdb_date_template      = tdb_template_9000136     (idem)
tds_footer_page        = 861
tds_logo_alt           = bahia.ba
tds_logo_title         = bahia.ba
tds_weather_key_top_menu = a80176453466fcfb890fa454284a5a40
tds_category_td_grid_style = td-grid-style-1
```

**Chaves que NÃO devem ser copiadas** (são do ambiente): `td_011` (uuid de licença),
`td_011_tp`, `theme_update_*`, `td_version`, `td_latest_version`, `firstInstall`,
`td_remote_http`.

**Chaves que NÃO precisam migrar porque o código as injeta em runtime.** Conferido: no banco
de homolog `td_translation_map_user` está **ausente** e `td_social_drag_and_drop.pinterest`
está **`true`** — mesmo assim o site mostra as traduções e não mostra o Pinterest, porque
`mu-plugins/bahia-td-opcoes.php` e `mu-plugins/bahia-traducoes.php` filtram `option_td_011`
no carregamento. Isso viaja no git e **não** deve ser replicado no banco.

### 1.4 Anexos (mídia)

| ID | Arquivo | Onde é usado |
|----|---------|--------------|
| 9000165 | `lizandra-capistrano.png` | Quem Somos (9000079) |
| 9000166 | `tauany-alves.png` | Quem Somos (9000079) |
| 9000168 | `logo-bahia-ba-branco-transparente.png` | Rodapé (9000126) |

Os arquivos já estão no S3/CloudFront (`d1x4bjge7r9nas.cloudfront.net`), que é **compartilhado**
entre os ambientes. O que falta em produção é o **registro** do anexo (linha em `wp_posts` +
`postmeta` do WP Offload Media), não o binário.

### 1.5 Menus

> **Renumeração de TERMOS — 18/08/2026, fase 1 da virada.** Os IDs abaixo **já estão
> atualizados**. Fórmula: `novo = 9.100.000 + (antigo − 78.519)`. Sub-faixa 9.1xx = termo
> nascido em homolog (9.0xx = post). Mapa em `wp_bahia_renum_map_terms`, que **não deve ser
> apagada**. A categoria `Featured` (78520) foi **apagada**, não renumerada.

| ID | Nome | Itens | Local | ID anterior |
|----|------|-------|-------|-------------|
| 9100002 | Principal | 10 | `header-menu` | 78521 |
| 9100003 | Rodapé | 10 | `footer-menu` | 78522 |
| 9100004 | Rodapé Legal | 2 | — | 78523 |

Migram os termos, os itens (`nav_menu_item`) e o `theme_mods[nav_menu_locations]`.

> **O menu "Rodapé" (9100003) está ligado ao local `footer-menu`, mas nada o consome.**
> Medido em 18/08: o rodapé vivo (`9000126`) não tem bloco de menu nenhum — só logo, texto e
> redes sociais; o "MAIS LIDAS" vem do próprio tema. O único lugar que cita menu por ID é o
> template **morto** `9000009` (Footer - Default PRO). O "Rodapé Legal" (9100004), idem: não
> renderiza em lugar nenhum hoje.
>
> Não é defeito introduzido pela renumeração — é o estado desde que o rodapé foi refeito. Mas
> **muda a verificação da fase 5**: conferir "rodapé com os itens certos" em produção vai
> encontrar um rodapé sem menu, e isso é o comportamento atual e esperado.

### 1.6 O que NÃO deve viajar

- **Anúncios AdRotate 1724, 1725 e 1726.** Foram reativados em homolog só como inventário de
  teste. Produção tem o próprio calendário comercial. Ver `REVERSAO-adrotate-homolog.md`.
- **Anúncios AdRotate 1728 e 1729**, e os agendamentos 2311/2312. Mesma razão: inventário de
  teste da rodada 8. (Os agendamentos 2309 e 2310 já foram apagados — ver HANDOVER §11.3.)
- **Nada da tabela `wp_adrotate*`, em geral.** O inventário publicitário de produção é a fonte
  da verdade; a rodada 8 mexeu apenas em `mu-plugins/`, que viaja pelo git, e não no cadastro
  de anúncios. Os grupos e as posições já existem em produção com os mesmos IDs.
- Qualquer `bahia_*_backup_*`.
- Conteúdo editorial (posts). Produção é a fonte da verdade do acervo.

---

### 1.7 Título da home (rodada 8) — 3 linhas de banco

O `<title>` e o `og:title` da home saíam como **"Home - bahia.ba"**: o Yoast usa o título do
post quando a home é uma página estática, e a página 9000142 se chama literalmente "Home". Era
o único texto em inglês do site, na tag mais visível no Google.

Passou a **"bahia.ba - A notícia que conecta você à Bahia"**, escrito com as variáveis do
próprio Yoast, para continuar em sincronia com `blogname` e `blogdescription`:

```sql
-- 1) as duas metas da página da home (ajustar 9000142 para o ID de destino)
INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES
  (9000142, '_yoast_wpseo_title',           '%%sitename%% %%sep%% %%sitedesc%%'),
  (9000142, '_yoast_wpseo_opengraph-title', '%%sitename%% %%sep%% %%sitedesc%%');

-- 2) o Yoast serve o título a partir do CACHE dele, não do postmeta.
--    Sem este UPDATE a mudança não aparece.
UPDATE wp_yoast_indexable
   SET title = '%%sitename%% %%sep%% %%sitedesc%%',
       open_graph_title = '%%sitename%% %%sep%% %%sitedesc%%'
 WHERE object_id = 9000142 AND object_type = 'post';
```

**A armadilha aqui é a tabela `wp_yoast_indexable`.** Com apenas o `INSERT` no `wp_postmeta`,
o site continua servindo o título antigo indefinidamente — foi o que aconteceu na primeira
tentativa em homolog. Conferir depois de aplicar:

```bash
curl -s https://SEU-HOST/ | grep -o '<title>[^<]*</title>'
```

**Reversão:**

```sql
DELETE FROM wp_postmeta
 WHERE post_id = 9000142
   AND meta_key IN ('_yoast_wpseo_title','_yoast_wpseo_opengraph-title');

UPDATE wp_yoast_indexable SET title = NULL, open_graph_title = NULL
 WHERE object_id = 9000142 AND object_type = 'post';
```

Confere que `blogname` = `bahia.ba` e `blogdescription` = `A notícia que conecta você à Bahia`
no destino **antes** de aplicar — as variáveis leem de lá.

### 1.8 Títulos do Yoast (rodada 10) — dois scripts idempotentes, a EXECUTAR na produção

Mesma natureza da 1.7 e a mesma armadilha da `wp_yoast_indexable`, em mais dois lugares. As
duas mudanças estão feitas **no banco de homologação** e os scripts estão versionados, mas
**nada disso viaja na imagem**: são `UPDATE`s de banco. Se ninguém rodar, a produção estreia
o Newspaper com os títulos antigos.

| script | o que corrige | alcance |
|--------|---------------|---------|
| `scratchpad/titulos-editorias-apply.php` | `Política: últimas notícias` → `Política` | 25 CPTs de editoria |
| `scratchpad/titulo-quem-somos-apply.php` | `Quem Somos \| bahia.ba – Jornalismo confiável e contextualizado` → `Quem Somos` | 1 página |

Como rodar, de dentro de um pod de produção — **os dois rodam secos por padrão** e reconhecem
o ambiente sozinhos pelo `siteurl`, abortando se for outro:

```bash
php titulos-editorias-apply.php              # seco: mostra o que faria
php titulos-editorias-apply.php --aplicar
php titulo-quem-somos-apply.php              # seco
php titulo-quem-somos-apply.php --aplicar
```

Rodar de novo depois não muda mais nada; a conferência sai no fim da própria execução.

**Três coisas que o levantamento em homolog achou e que a produção também vai ter:**

1. **O Yoast copia o template do option para a linha da indexable** e serve a partir da cópia
   — é a lição da 1.7, e vale igual aqui. Por isso os scripts escrevem nos dois lugares.
2. **As 7 editorias que entraram no mapa em 18/08** (`covid19`, `eleicoes2024`, `saude`,
   `social`, `gente`, `investimentos`, `bombou`) nasceram com o padrão do Yoast, **em inglês**:
   `Covid-19 Archive - bahia.ba`. O alvo único corrige os dois estados de uma vez.
3. **Linhas de indexable DUPLICADAS** — 16 sub_types tinham duas, com templates diferentes;
   vence a de id menor. O script escreve nas duas, então qual vence deixa de importar.

Conferir depois de aplicar:

```bash
for s in politica covid19 social quem-somos; do
  printf "%-14s " "$s"
  curl -s "https://bahia.ba/$s/" | grep -o '<title>[^<]*</title>'
done
# esperado: "Política - bahia.ba", "Covid-19 - bahia.ba",
#           "Coluna do Ginno - bahia.ba", "Quem Somos - bahia.ba"
```

**Fora do escopo dos scripts, de propósito:** o CPT interno `tdc-review` do tagDiv, e as 73
chaves `title-tax-*` do option, que valem para os arquivos de taxonomia. Esses respondem 404
hoje nos dois ambientes (18 CPTs disputando o mesmo slug de reescrita), então não há título na
tela para corrigir — **mas se a virada consertar as taxonomias, isto volta à mesa junto**.

### 1.8-A A Fase 3 só trouxe a faixa 9.000.000+ — o que já existia nos dois ficou para trás

**Descoberto em 19/08/2026, depois da virada, por relato da redação:** `/ultimas-noticias/`
estava em branco em produção.

A causa não é o tema nem o bloco. É o recorte da Fase 3: ela importou o conteúdo **novo**,
criado em homolog e renumerado para 9.000.000+. Uma página que já existia nos DOIS ambientes
com o mesmo ID não entrava nesse recorte — e, se tivesse sido editada em homolog durante a
migração, a edição não viajava.

Foi o caso da página **477, "Últimas notícias"**:

| | conteúdo |
|---|---|
| homolog | 636 bytes — `[td_flex_block_1 limit="12" installed_post_types="…18 editorias…" …]` |
| produção | **0 bytes** |

Sob o `bahia_refactor` a página funcionava por template do tema; com o Newspaper, ela renderiza
o próprio conteúdo — que em produção era vazio. Blank page, sem erro.

**A pergunta que importava era se havia mais.** Comparei o `MD5(post_content)` de todas as
páginas com ID < 9.000.000 nos dois ambientes: **12 páginas em cada, e só a 477 diferia.** A
lacuna é isolada, não sistêmica. Corrigido copiando o conteúdo de homolog; o texto aplicado
está em `scratchpad/pagina-477-ultimas-noticias.txt`.

> **Para a próxima migração:** o recorte por faixa de ID é seguro para conteúdo novo e cego
> para conteúdo editado. Antes de declarar a fase concluída, comparar o hash do `post_content`
> das páginas e dos templates que existem nos dois lados — é uma consulta e pega exatamente
> este tipo de omissão silenciosa.

**Pendência menor, herdada:** o `installed_post_types` do bloco lista 18 editorias e não inclui
as 7 que entraram no mapa em 18/08 (`covid19`, `eleicoes2024`, `saude`, `social`, `gente`,
`investimentos`, `bombou`). Hoje é inerte — todas estão paradas desde 2025 ou antes, e não
apareceriam numa lista de "últimas" de qualquer forma. Corrigir **em homolog primeiro**, para
que viaje pela via normal em vez de virar outra divergência.

### 1.9 Os feeds RSS — quem os controla é o tema antigo, e ninguém tinha percebido

**Descoberto em 18/08/2026, por acaso, ao conferir outra coisa.** Não estava em nenhum
inventário. É a pendência mais consequente desta rodada, porque é uma perda silenciosa: nada
quebra, nada dá erro no painel — um endereço que hoje serve conteúdo simplesmente passa a
responder 404 no dia da virada.

`themes/bahia_refactor/functions.php:1253-1277` faz **três** coisas com feeds:

1. **mata todos os feeds padrão** — `turn_off_feed()` pendurada em `do_feed`, `do_feed_rdf`,
   `do_feed_rss`, `do_feed_rss2`, `do_feed_atom` e os dois de comentários, com um `wp_die()`;
2. **tira os `<link rel="alternate">` de feed do `<head>`** (`remove_action('wp_head',
   'feed_links', 2)` e `feed_links_extra`);
3. **registra UM feed próprio**: `add_feed('feedbahiaba', ...)`, renderizado pelo template
   `themes/bahia_refactor/rss-feedbahiaba.php`.

Medido nos dois ambientes em 18/08, depois do deploy `prod-de88838f`:

| endereço | produção (tema antigo) | homolog (Newspaper) |
|----------|------------------------|---------------------|
| `/feed/feedbahiaba/` | **200, 5 itens** | **404** |
| `/feed/` e `/politica/feed/` | **500** ("No feed available"), em ~1,5 s — **de propósito** | **sem resposta em 45 s** |

Leia a tabela na diagonal e o problema aparece sozinho: **a virada troca uma coisa que
funciona por duas que não funcionam.**

- O `feedbahiaba` **desaparece**. Ele é servido hoje, com conteúdo. Quem o consome não está
  documentado em lugar nenhum — vale olhar o log de acesso do nginx por `feedbahiaba` antes
  de decidir, porque pode haver agregador, parceiro ou app dependendo dele.
- Os feeds padrão **voltam a existir** — e, a julgar por homolog, não respondem. Hoje eles
  custam 1,5 s e devolvem 500; depois da virada passam a varrer o acervo até estourar. Pior:
  **504 não entra no `fastcgi_cache`**, então cada acesso de robô paga o custo inteiro, e
  endereço de feed é exatamente o que robô visita sem parar.
- Os `<link rel="alternate">` **voltam ao `<head>`**, anunciando esses endereços para quem
  ainda não os conhecia.

#### PORTADO em 18/08/2026 — `mu-plugins/bahia-feeds.php`

As três partes saíram do tema e viraram mu-plugin, inclusive o template `rss-feedbahiaba.php`,
que era `get_template_part` e por isso morreria junto. **Não há nada a executar na virada por
causa disto** — o código viaja na imagem. O que sobrou é conferência (6.1) e uma decisão.

Resultado medido em homolog, antes e depois:

| endereço | antes | depois |
|----------|-------|--------|
| `/feed/` , `/politica/feed/` , `/comments/feed/` | sem resposta em 45 s | **500 em 0,55 s** |
| `/feed/feedbahiaba/` | 404 | **200 em 0,69 s**, 5 itens, XML válido |

O porte não foi cópia. Quatro coisas mudaram, e cada uma por medição — o histórico completo
está nos comentários do arquivo, aqui fica o resumo:

1. **A recusa passou de `do_feed_*` para `parse_request`.** `do_feed()` roda no
   `template-loader.php`, depois de a consulta principal já ter sido executada: a recusa saía,
   mas o banco já tinha trabalhado. Portado tal e qual, os feeds desligados continuaram
   estourando 50 s em homolog. Como o motivo do porte era o custo com robô, recusar depois de
   pagar a conta não resolvia nada.
2. **`WP::send_headers()` chamava `get_lastpostmodified('GMT')`** (`class-wp.php:491-500`) em
   toda requisição de feed, para o `Last-Modified`. Custo: 0,67 s em produção, **59 s em
   homolog**. Curto-circuitado por `pre_get_lastpostmodified`, só nesta requisição, com
   transient de 5 min.
3. **A consulta do curto-circuito teve de ser refeita.** `MAX(post_modified_gmt)` levava
   **28,83 s**: o índice do WordPress é `(post_type, post_status, post_date)`, ordenado por
   post_*date*. Trocada por `ORDER BY post_date DESC LIMIT 1`, que casa com o índice.
4. **A consulta principal é moldada em `pre_get_posts`**, em vez de o template fazer um
   `query_posts()` próprio — eram duas consultas por requisição, e a principal (`post_type =
   'post'`, com `SQL_CALC_FOUND_ROWS`) era perda pura.

Duas diferenças de comportamento, deliberadas e registradas:

- **A lista de post types do feed** passou de `global $POST_TYPES` (a lista à mão do tema, 23
  únicos em 24 entradas) para `bahia_editorias_map()` (25). O feed **ganha** `dende_poder` e
  `mais_gente`.
- **`lastBuildDate`** passa a descrever este canal, e não um máximo global sobre todos os tipos
  públicos do site — incluindo `attachment` e `tdb_templates`, que o feed não publica.

Convivência com o tema antigo está resolvida: o mu-plugin registra o feed em `init` prioridade
99 e limpa o `do_feed_feedbahiaba` antes, senão os dois callbacks ficariam pendurados e o XML
sairia **duplicado** em produção enquanto o `bahia_refactor` estiver no ar.

#### As duas decisões, tomadas em 18/08/2026

**1. Os feeds respondem 410, e não o 500 do tema.** 500 diz "deu erro do meu lado, tente de
novo", e o robô obedece — para sempre. 410 diz "isto existiu e acabou": buscador tira do
índice, leitor de RSS para de tentar. A frase da resposta continua a mesma do tema, para quem
procurar pelo texto no histórico encontrar a continuidade.

**2. O `feedbahiaba` está DESLIGADO — ninguém o consumia.** Era o único feed vivo do portal,
mas sem consumidor um endereço que varre o acervo a cada visita de robô é só custo.

O código dele **não foi apagado**: está inteiro no mu-plugin, testado e inerte. O interruptor
é uma linha:

```php
// mu-plugins/bahia-feeds.php
define('BAHIA_FEEDS_PROPRIO_ATIVO', false);   // trocar para true religa
```

Religar não pede mais nada — nem flush de reescrita, nem bump de versão. O registro do
`add_feed` fica **fora** do interruptor de propósito: com o feed desligado, o que se quer em
`/feed/feedbahiaba/` é o 410 barato, e para o WordPress reconhecer aquilo como feed o nome
precisa estar registrado. Sem o registro a URL cairia no 404 — que neste site é o caminho mais
caro que existe (o `next_prev` do tagDiv pré-renderiza, e 404 não entra no `fastcgi_cache`).

Se religar um dia, confira junto o `BAHIA_FEEDS_ITENS` (5) e a lista de `bahia_feeds_tipos()`,
que publica **todas** as editorias do mapa — inclusive as ocultas no painel pela seção 6.4.

Estado medido em homolog depois das duas decisões: `/feed/`, `/politica/feed/`,
`/comments/feed/`, `/feed/rss2/` e `/feed/feedbahiaba/` todos em **410**, entre 0,53 s e 1,7 s.

### 1.10 A varredura do tema antigo — o inventário completo do que morre junto

O caso dos feeds (1.9) apareceu por acaso, e isso foi o alerta: se um comportamento global
desse tamanho estava fora de todo inventário, provavelmente havia outros. Esta seção é a
varredura que se seguiu, feita em 18/08/2026.

**Método**, porque ele importa para confiar no resultado: listar todos os `add_action`,
`add_filter`, `remove_action` e `remove_filter` do tema — 33 no `functions.php`, mais 3 em
cada `post-types/*.php` — e, para cada um que não seja de template, **medir o comportamento
nos dois ambientes em tempo de execução**, não ler o código e supor. Foi medindo que duas das
minhas hipóteses caíram (ver as notas de rodapé da tabela).

Os `post-types/*.php` ficam de fora da lista abaixo: são CPT + 2 taxonomias cada, e já foram
portados no `bahia-editorias-cpt.php` (commit `104be34f`).

| comportamento | produção (tema antigo) | homolog (Newspaper) | veredito |
|---------------|------------------------|---------------------|----------|
| `author_base` das URLs de autor | `colunistas` | `author` | 🔴 **quebra links publicados** |
| popup de anúncio (`adrotate_group(18)` no `wp_footer`) | presente | ausente | 🔴 **receita** |
| filtro OneSignal (push extra p/ Android e iOS) | pendurado | pendurado, com trava de ambiente | 🟢 **portado** — `bahia-onesignal-app-push.php` (⁵) |
| feeds (seção 1.9) | desligados + `feedbahiaba` | idem, via mu-plugin | 🟢 **portado** — `bahia-feeds.php` |
| `xmlrpc_enabled => false` | sim | sim, via mu-plugin | 🟢 **portado** — `bahia-xmlrpc.php` |
| `X-Pingback` removido do cabeçalho | sim | sim, via mu-plugin | 🟢 **portado** — `bahia-xmlrpc.php` |
| 6 tamanhos de imagem (`destaque_*`, `news_home`, `user_avatar`) | registrados | **ausentes** | 🟠 uploads novos |
| `posts_groupby` zerado globalmente | **sim** (¹) | não | 🟠 armadilha de medição |
| `target="_blank"` nos links do corpo | sim | não | 🟡 comportamento visível |
| seletor ACF de destaques só mostra publicados | sim | não (²) | 🟡 edição |
| `show_admin_bar(false)` no site | sim | não | 🟡 cosmético |
| páginas de opções ACF | `Opções`, `Home`, `Geral` | `Destaques da Home` | 🟢 **coberto** (³) |
| menu "Posts" oculto | `remove_menu_page('edit.php')` | filtro `register_post_type_args` | 🟢 **coberto** (⁴) |
| CPTs e taxonomias das editorias | tema | `bahia-editorias-cpt.php` | 🟢 coberto |
| rótulo de `economia` | `change_post_type_labels` | mapa do mu-plugin | 🟢 coberto |
| logo do login, rodapé do painel, barra do admin, `viewport`, build info | tema | Newspaper / irrelevante | 🟢 morre por projeto |

¹ **Hipótese minha que caiu.** Achei que estivesse coberto porque `has_filter('posts_groupby')`
devolve verdadeiro nos dois — mas isso só diz que *algum* callback existe. Medindo o valor:
`apply_filters('posts_groupby', 'wp_posts.ID')` devolve `''` em produção e `'wp_posts.ID'` em
homolog. Ou seja, **produção roda hoje TODAS as consultas sem `GROUP BY`**, por causa de um
`add_filter` global e incondicional em `functions.php:578`. Isto não é regressão — é um
problema que a virada resolve. Mas é uma diferença de comportamento entre os dois ambientes
que pode explicar divergência de contagem ou linha duplicada ao comparar um com o outro; quem
for medir paridade precisa saber disto antes, não depois.

² O `bahia-home-destaques.php` filtra `post_status === 'publish'` **na renderização**, então
não sai card quebrado. O que se perde é o filtro do *seletor*: o editor volta a poder escolher
um rascunho, e o card simplesmente não aparece, sem aviso. Vale notar que só 2 dos 6 campos
originais seguem em uso (`slider_m1` e `semi_destaques_m1`); os outros 4 eram da home antiga.

³ Coberto pelo `bahia-acf-options.php`. A ausência de "Geral" é **decisão registrada**, não
esquecimento: seus campos (`options_facebook`, `options_whatsapp`, `options_logo_login`) não
são lidos por nada no tema novo, e expor tela cuja edição não tem efeito é pior que não tê-la.

⁴ **Outra hipótese minha que caiu, e que corrige o que eu havia reportado.** O tema antigo já
escondia "Posts" com `remove_menu_page('edit.php')` (`functions.php:47`). Portanto o filtro
que entrou em 18/08 **não mudou nada no painel de produção** — só garantiu que a omissão
sobreviva à virada. Em produção, a única mudança real de menu foi `mais_gente` (seção 6.4).

⁵ **O porte do OneSignal leva uma trava que o tema nunca precisou.** Medido em 18/08:
homologação usa **o mesmo `app_id` e a mesma chave REST** da produção (`db07f370…2325`) — não
é app de teste, é o app real, com os assinantes reais. O tema só rodava em produção, então a
guarda era implícita; o mu-plugin roda em todo lugar, e sem ela um push disparado de
homologação chegaria ao celular do leitor. A trava também preserva o estado de hoje em
homolog, onde o push do app não sai. Foram corrigidos, de passagem, dois defeitos do original,
ambos no tratamento de erro: uma chamada a `$response->get_error_code()` num ramo em que
`$response` podia não ser `WP_Error` (fatal dentro do `save_post`, derrubando a publicação da
matéria), e um `return;` que devolvia null no lugar de `$fields` — fazendo com que a falha do
push do app levasse junto o push do navegador.

**Um detalhe de desempenho que a varredura achou de brinde:** o `add_action('init', ...)` de
`functions.php:1236` chama `$wp_rewrite->flush_rules()` **a cada requisição**, não uma vez.
Com 25+ CPTs e as taxonomias de cada um, é regeneração completa das regras de reescrita em
todo request de produção. Some com o tema, o que é bom — mas enquanto ele estiver no ar, é
custo pago em toda página, e ajuda a explicar o baseline de produção na seção 4.7.

**O caso mais urgente é o `author_base`.** Medido:

| endereço | produção | homolog |
|----------|----------|---------|
| `/colunistas/neison-cerqueira/` | **200** | 404 |
| `/author/neison-cerqueira/` | 404 | **200** |

Todo link de colunista publicado, indexado ou compartilhado aponta para `/colunistas/`. No dia
da virada, todos passam a 404 de uma vez. E há uma armadilha a mais: **a tabela de
verificações da 6.1 deste documento testa `/author/<slug>/`** — ela foi escrita contra homolog
e passaria com folga enquanto as URLs reais morriam. A linha foi corrigida.

Portar `author_base` é uma linha em mu-plugin; **mas isso não basta**, porque as regras de
reescrita precisam de flush depois. E fica a decisão de manter `colunistas` (preserva os links,
não custa nada) ou migrar para `author` com redirect 301 de `/colunistas/*` — o que é trabalho
de verdade e só se justifica se houver motivo editorial.

---

## 2. Mapa de dependências de ID

**Este é o ponto onde a migração falha silenciosamente.** Todo ID abaixo é do banco de
homolog. Em produção o mesmo número pode não existir, ou — pior — existir apontando para
outra coisa. Um ID errado não gera erro: gera uma foto trocada ou um bloco vazio.

| ID em homolog | O que é | Onde é referenciado | Como resolver no destino |
|---------------|---------|---------------------|--------------------------|
| 9000124 | Header | `td_011[tdb_header_template]` | Importar o post, anotar o **novo** ID, reescrever a chave |
| 9000126 | Rodapé | `td_011[tdb_footer_template]` | Idem |
| 9000140 | 404 | `td_011[tdb_404_template]` | Idem |
| 9000132 | Autor | `td_011[tdb_author_template]` | Idem |
| 9000138 | Busca | `td_011[tdb_search_template]` | Idem |
| 9000128 / 9000130 / 9000134 / 9000136 | Templates que não renderizam | chaves `td_011` correspondentes | Idem, ou decidir não migrar (seção 5) |
| 9000142 | Home | `page_on_front` | Importar, anotar novo ID, atualizar `page_on_front` |
| 9000079 | Quem Somos | Menus 78521/78522 | Importar, corrigir o item de menu |
| 477 | Últimas Notícias | Menu 78521; `mu-plugins/bahia-scroll-infinito.php` usa **`is_page('ultimas-noticias')`** | **Nada a fazer** — o código usa o *slug*, não o ID |
| 861 | `td_011[tds_footer_page]` | Opção do tema | **Verificar antes**: confirmar o que é esse ID em produção |
| 9000165 / 9000166 | Fotos da equipe | `post_content` de 9000079 | Importar anexo, substituir o ID no conteúdo |
| 9000168 | Logo branca do rodapé | `post_content` de 9000126 | Importar anexo, substituir o ID no conteúdo |
| 9100002 / 9100003 / 9100004 | Menus (eram 78521/78522/78523) | `theme_mods_Newspaper[nav_menu_locations]` | Importar os termos **com estes IDs**, religar os locais |
| 1777 / 1782 | EC Bahia / EC Vitória | `mu-plugins/bahia-futebol-display.php` | **Verificar**: são IDs de time da API, não do WordPress — conferir se o mu-plugin os trata como constantes |

Além dos IDs, o `post_content` dos templates do tagDiv guarda **blocos codificados em
base64** (`tdc_css`, `title_text`, `description`). Um ID de anexo dentro de um bloco desses
**não é encontrado por um SQL de `REPLACE`**: é preciso decodificar, trocar e recodificar —
foi assim que os textos do 404 foram corrigidos na rodada 4.

---

## 3. Método de transporte — três opções

### Opção A — Exportação/importação seletiva pelo WordPress (WXR)

Exportar em homolog os `tdb_templates` e as páginas; importar em produção.

- **A favor:** ferramenta nativa; traz `postmeta` junto; cria os anexos.
- **Contra:** o importador **atribui novos IDs**. Toda referência da seção 2 precisa ser
  reescrita depois, à mão. O importador também não toca em `wp_options`, então `td_011` e
  `wpseo_titles` ficam de fora.
- **Quando usar:** se o objetivo for trazer os anexos com o mínimo de esforço.

### Opção B — Script PHP idempotente (recomendado)

Um script que roda no pod de produção, resolve cada objeto **por slug** (não por ID), grava e
imprime o de/para.

- **A favor:** idempotente (rodar duas vezes não duplica); resolve o problema de ID na
  origem, porque procura `post_name = 'header-template-magazine-pro'` e usa o ID que
  encontrar; permite `--dry-run`; deixa registro do que mudou. É o padrão já usado nas
  rodadas 2 a 5 (`fix404.php` etc.), e funcionou.
- **Contra:** precisa ser escrito e testado antes. O conteúdo dos templates tem que ser
  embutido no script ou lido de um arquivo exportado.
- **Como lidar com base64:** o script decodifica, troca o ID de anexo, recodifica — mesma
  função `td_enc()`/`td_dec()` usada na rodada 4.

### Opção C — Recriação manual pelo painel

Refazer os ajustes pela interface do tagDiv em produção.

- **A favor:** não tem risco de ID errado; o painel resolve tudo.
- **Contra:** são quatro rodadas de ajuste fino (cores, espaçamentos, tipografia por
  breakpoint). Horas de trabalho, e o resultado dificilmente fica idêntico.
- **Quando usar:** só para o que for pequeno e visual.

**Recomendação:** **B para tudo, com A como plano B só para os três anexos.** Os anexos são o
único caso em que o importador nativo economiza trabalho real (ele cria as linhas de
`postmeta` do Offload Media). O resto é mais seguro por script, resolvendo por slug.

---

## 4. Verificações prévias — ANTES de importar qualquer coisa

Rodar tudo isto em **produção**, em modo leitura, e anotar os resultados. Se qualquer item
divergir do esperado, parar e reavaliar.

1. **Backup completo do RDS de produção** (snapshot). Sem isso, nada começa.
2. **Existe algum `tdb_templates` em produção?** Se existir, anotar IDs e títulos — pode
   haver colisão de slug.
   ```sql
   SELECT ID, post_title, post_name, post_status FROM wp_posts WHERE post_type='tdb_templates';
   ```
3. **O que é o ID 861 em produção?** É o `tds_footer_page` de homolog.
   ```sql
   SELECT ID, post_type, post_title, post_status FROM wp_posts WHERE ID=861;
   ```
4. **Os IDs 9000124/9000126/9000140/9000132/9000138/9000142/9000079/9000165/9000166/9000168 já existem
   em produção?** Se existirem, são outra coisa — confirma que a resolução por slug é
   obrigatória.
   ```sql
   SELECT ID, post_type, post_title FROM wp_posts
    WHERE ID IN (477,861,9000079,9000124,9000126,9000128,9000130,9000132,9000134,9000136,9000138,9000140,9000142,9000165,9000166,9000168);
   ```
5. **Valor atual de `td_011` em produção** — salvar em uma opção de backup antes de tocar:
   ```sql
   SELECT LENGTH(option_value) FROM wp_options WHERE option_name='td_011';
   ```
6. **Menus existentes em produção** e a que locais estão ligados.
7. **Tempo de resposta atual**, para comparar depois (baseline medido em produção em
   11/08/2026):

   | URL | Status | Tempo |
   |-----|--------|-------|
   | `/` | 200 | 0,75 s |
   | `/politica/` | 200 | 2,51 s |
   | `/sitemap_index.xml` | 200 | **0,62 s** (a quente) |
   | `/post-sitemap.xml` | 200 | 1,62 s |
   | `/bahia-sitemap.xml` | 200 | 5,17 s |
   | `/bahia-sitemap2.xml` | 200 | 3,24 s |
   | `/entrevista-sitemap.xml` | 200 | 2,04 s |

   > O briefing da rodada 5 citava 1,95 s para o sitemap. A medição de 11/08 deu **0,62 s a
   > quente** e **9,95 s no primeiro acesso frio**. Use 0,62 s como referência de regime e
   > trate ~10 s como o custo de cache frio, não como degradação.

8. **Confirmar que o CloudFront/S3 é o mesmo** nos dois ambientes (era, em 11/08). Se não
   for, os três anexos precisam de upload real, não só de registro.

---

## 5. Ordem de execução, com ponto de rollback

Cada etapa é reversível sozinha. Não avançar sem conferir a anterior.

| # | Etapa | Rollback |
|---|-------|----------|
| 0 | Snapshot do RDS de produção | — |
| 1 | Colocar o site em manutenção (ou usar a janela de DNS da seção 7) | Remover a manutenção |
| 2 | Salvar `td_011` e `wpseo_titles` atuais em opções de backup (`prod_td011_backup_<data>`) | — |
| 3 | Importar os **3 anexos** (9000165, 9000166, 9000168). Anotar os novos IDs | Excluir os anexos criados |
| 4 | Importar os **templates que renderizam**: 9000124, 9000126, 9000140, 9000132, 9000138. Anotar novos IDs | Excluir os posts criados; `td_011` ainda não aponta para eles |
| 5 | Substituir, no conteúdo de 9000126, o ID 9000168 pelo novo (decodificando base64 se necessário) | Restaurar o `post_content` do backup da etapa 4 |
| 6 | Importar **Home** e **Quem Somos**; trocar 9000165/9000166 pelos novos IDs no conteúdo de Quem Somos | Excluir os posts criados |
| 7 | Importar/ajustar os **menus** e religar `nav_menu_locations` | Restaurar `theme_mods` |
| 8 | **Só agora** reescrever as chaves de `td_011` (seção 1.3.1) com os novos IDs | Restaurar a opção do backup da etapa 2 |
| 9 | Apontar `page_on_front` para o novo ID da Home | Restaurar o valor anterior |
| 10 | Importar `wpseo_titles` | Restaurar do backup da etapa 2 |
| 11 | `flush_rewrite_rules()` e limpar cache (fastcgi + qualquer CDN/Varnish) | — |
| 12 | Rodar as verificações da seção 6 | Se falhar, voltar etapa a etapa; em último caso, restaurar o snapshot |
| 13 | Tirar da manutenção | — |

### 5.0 FASE 3 EXECUTADA — 18/08/2026. O que entrou, o que ficou de fora e por quê

Aplicada em quatro blocos, com conferência de HTML entre cada um: **o site de produção ficou
byte a byte idêntico depois de todos os quatro** (a única variação era rotação de anúncio do
AdRotate, normalizada no instrumento de comparação).

| Bloco | Gravado |
|---|---|
| anexos | 41 `attachment` + 174 `postmeta` + **41 linhas em `wp_as3cf_items`** |
| templates | 13 `tdb_templates` + 97 `postmeta` |
| páginas | **3** `page` + 19 `postmeta` |
| menus | 22 `nav_menu_item` + 176 `postmeta` + 3 `wp_terms` + 3 `wp_term_taxonomy` + 22 `wp_term_relationships` |

Total: **79 posts, 466 postmeta, 41 offload, 3 termos, 22 relações.** Zero órfãos em qualquer
direção. Os 41 anexos foram conferidos **respondendo 200 pelo CloudFront a partir de produção**
— não só a existência da linha de offload.

#### As duas páginas que ficaram de fora — de propósito, não por esquecimento

`9000118` (`home`) e `9000155` (`home-temporaria`) **não foram migradas**. São resíduo das
tentativas de montagem da home em homolog: medido, **nada aponta para elas** — 0 referências em
`wp_options`, 0 em `wp_postmeta`, 0 em `post_content` de qualquer post, e nenhuma é o
`page_on_front` (que é a `9000142`). Migrá-las criaria `/home/` e `/home-temporaria/` públicas e
indexáveis em produção, sem propósito.

Se um dia fizerem falta, entram depois: os IDs continuam livres em produção e o payload
`f3-payload.json` as contém.

#### O que NÃO entrou na fase 3, e para onde foi

| Item | Destino | Razão |
|---|---|---|
| **`wpseo_titles`** | **FASE 4** | Ver 5.0.1 abaixo — muda o que o Google vê, hoje |
| `td_011` | fase 4 | Já era o plano; ver 5.0.2 |
| `siteurl`, `home` | nunca | Estado do ambiente |
| `options_slider_m1`, `options_semi_destaques_m1` | **nunca** | Sobrescreveria os destaques atuais de produção pelos de 28/07. Conferidas intactas depois da fase: 1 e 4 itens |
| `blogdescription` | **nunca** | Produção tem *"A notícia que conecta você à Bahia"*, homolog tem *"A notícia no ponto certo"*. **O valor de produção é o certo** — é dele que o `%%sitedesc%%` do Yoast monta o título da home (§1.7) |
| `wp_historico_destaques` | nunca | FK real para `wp_posts.ID` |
| 45 `revision` | nunca | Não é conteúdo publicado |
| `#9000195`, **`#9000199`**, `#9000212` | **nunca** | Conteúdo editorial nascido em homolog em 16/08 — o 9000199 é uma matéria de esporte **publicada**. Produção é a fonte da verdade do acervo |

#### 5.0.1 Por que `wpseo_titles` saiu da fase 3

Comparação chave a chave: 1.065 em produção, 1.115 no payload, **0 seriam perdidas**. Mas das
78 chaves que mudam de valor, **4 são `breadcrumbs-*` e já são visíveis hoje**: o Yoast emite
`yoast-schema-graph` em produção e o `BreadcrumbList` usa `"name":"Home"`, que viraria
`"Início"`. É dado estruturado que o Google lê.

As outras 74 são `title-*` e **não** têm efeito hoje — o `bahia_refactor` fixa
`<title>Bahia.Ba</title>` em toda página e ignora o Yoast (conferido na home, em `/politica/`,
na busca e no 404). Passam a valer exatamente quando o tema trocar.

E entre as **50 chaves novas** há **5 `noindex-*`**, que são diretiva de indexação.

Aplicar **por união**, nunca gravando a opção inteira:

```php
$atual = get_option('wpseo_titles');
foreach ($payload['wpseo_titles'] as $k => $v) { $atual[$k] = $v; }
update_option('wpseo_titles', $atual);   // 1.065 -> 1.115, nada perdido
```

#### 5.0.2 `td_011` NÃO EXISTE em produção — isto muda a fase 4

Medido em 18/08: `SELECT option_value FROM wp_options WHERE option_name='td_011'` volta
**vazio**. A opção é criada pelo tema Newspaper na ativação, e o tema nunca esteve ativo lá.

Consequência: a fase 4 **não** vai "migrar chaves por cima de uma opção existente". A ordem
correta é trocar `template`/`stylesheet`, deixar o tema criar a `td_011` com os padrões dele, e
**só então** escrever as chaves da §1.3.1. O aviso original — "copiar a opção inteira
sobrescreveria licença e versão" — perde o objeto: não há o que sobrescrever.

#### 5.0.3 `tds_footer_page = 861` — **NÃO CORRIGIR NA VIRADA**

> ## ⚠️ O ID 861 aponta para uma MATÉRIA, não para uma página. ISSO É O ESPERADO.
>
> Em **produção** o 861 é `Cinco postos do SAC modificam horário de funcionamento`, post do
> tipo `bahia`, publicado. Em **homolog** é exatamente o mesmo post. O valor é **idêntico nos
> dois ambientes** e é **anterior a todo este trabalho**.
>
> **NÃO "conserte" isso no meio da virada.** É precisamente o tipo de coisa que parece um erro
> óbvio, tenta a mão de quem está executando, e vira uma mudança não planejada dentro da janela
> mais sensível do projeto. Se merece revisão, é depois, com o site estável e como decisão
> própria — não como remendo de madrugada.

---

### 5.0.4 A PRÓXIMA JANELA — cenário revisado em 18/08/2026

A primeira tentativa foi aplicada e revertida por saturação do banco (ver
`INCIDENTE-virada-abortada-20260818.md`). As quatro consultas caras foram corrigidas e validadas
sob carga (commit `49ee6cf6`). A próxima janela será **com a redação offline**.

#### O que sai do roteiro

**O bloqueio de `/wp-login.php` e `/wp-admin`.** Foi projetado e descartado: sem repórteres
trabalhando, não há publicação a impedir. Fica registrado que era viável — `/etc/nginx/conf.d` é
somente-leitura (montagem de ConfigMap), mas `/etc/nginx` é gravável, então o caminho seria
copiar a config para um diretório gravável, inserir o bloqueio e repontar o `include` do
`nginx.conf`, por pod, sem tocar em nada versionado.

#### O que CONTINUA, e por motivos que não dependem da redação

**O `.maintenance`**, por dois:

1. cobre o `flush_rewrite_rules()`, que é o **único passo fora da proteção da transação** — uma
   requisição que chegue com a opção `rewrite_rules` vazia pode dar **404 em matéria real**;
2. dá o **corte limpo do cache**: `fastcgi_cache_valid 200 10m` faz páginas cacheadas seguirem
   servindo o tema antigo por até 10 minutos, e sem o corte o visitante navega entre páginas nos
   dois temas.

> #### ⚠️ O `.maintenance` EXPIRA EM 600 SEGUNDOS
>
> `wp-includes/load.php:444`:
> ```php
> // If the $upgrading timestamp is older than 10 minutes, consider maintenance over.
> if ( ( time() - $upgrading ) >= 10 * MINUTE_IN_SECONDS ) { return false; }
> ```
>
> **Isso aconteceu na janela de 18/08**: o arquivo foi criado às 07:56:28 e a verificação correu
> até 08:28 — ou seja, a partir de ~08:06 a manutenção havia caído sozinha, sem ninguém tocar em
> nada.
>
> Com a redação offline não há risco de publicação indevida. O risco que **permanece** é outro:
> o **site público volta ao ar com o tema novo antes de a verificação terminar** — e portanto
> antes de se saber se ele vai ser mantido ou revertido.
>
> **Se a verificação passar de 10 minutos, RECRIAR o arquivo em todos os pods**, com timestamp
> novo, antes de continuar. Ele é só `<?php $upgrading = time(); ?>`.

**O congelamento do HPA**, lendo o número de réplicas **ANTES** e congelando **nesse número**.
Na janela de 18/08 ele foi congelado em 3 quando havia 5, o que forçou terminação de pods; a
correção para 5 criou dois pods novos **sem** `.maintenance`. A varredura os pegou, mas por
sorte. Restaurar depois para `min=2 / max=5`.

#### O portão novo, antes de declarar sucesso

Depois do purge, com cache frio e tráfego real, medir no banco de produção:

- **`Threads_running` acima de 10 → é o mesmo modo de falha → rollback**, sem investigar;
- contar `SQL_CALC_FOUND_ROWS` no `SHOW FULL PROCESSLIST` — tem de ser **zero**;
- repetir aos **0, 5 e 15 minutos**, porque o pior momento é o cache se enchendo.

---

### 5.1 Ativação de plugins — entra na virada atômica, não no deploy

**Medido em 18/08/2026**, comparando `active_plugins` dos dois ambientes: produção tem **21**
ativos, homolog tem **24**. A diferença é exatamente três, e todos os três são do tagDiv:

```
td-composer/td-composer.php
td-cloud-library/td-cloud-library.php
td-social-counter/td-social-counter.php
```

**Nenhum plugin está ativo em produção e inativo em homolog** — não há nada a desativar por
engano, e os 21 comuns são os mesmos.

**Por que isso é bloqueante e não cosmético:** o `td-composer` não é acessório do Newspaper —
é quem **renderiza o single e o archive**. O `template_include` desvia para
`plugins/td-composer/legacy/Newspaper/`, e o `loop-single.php` que vale é o do plugin, não o do
tema. Trocar `template`/`stylesheet` sem ativar o `td-composer` deixa o site com o tema novo e
sem o motor que desenha as páginas.

O deploy da fase 2 **não ativa nada**: colocar arquivo em `plugins/` não mexe em
`active_plugins`, que é opção de banco. A ativação é escrita de banco e pertence ao bloco
atômico da fase 4:

```php
$ativos = get_option('active_plugins');
$novos  = array('td-composer/td-composer.php',
                'td-cloud-library/td-cloud-library.php',
                'td-social-counter/td-social-counter.php');
update_option('active_plugins', array_values(array_unique(array_merge($ativos, $novos))));
```

**No rollback, desativar os três junto com a volta do tema** — na mesma transação lógica em que
`template`/`stylesheet` voltam para `bahia_refactor`. Um `td-composer` ativo com o tema antigo
sequestra o `template_include` e serve páginas do Newspaper sobre o tema errado:

```php
update_option('active_plugins', array_values(array_diff(get_option('active_plugins'), $novos)));
```

Anotar o valor de `active_plugins` **antes** de escrever, junto com os demais valores de
rollback da fase 4.

> A ordem importa por um motivo específico: **`td_011` é reescrito por último** (etapa 8).
> Enquanto as chaves ainda apontam para os templates antigos, o site continua servindo o
> layout velho — ou seja, todas as etapas de 3 a 7 podem ser feitas com o site no ar sem
> mudar nada para o visitante. A virada é atômica na etapa 8.

---

## 6. Verificações posteriores

### 6.1 Funcionais — conferir uma a uma

| Página | O que tem que estar certo |
|--------|---------------------------|
| `/` | Ordem dos blocos; selos de editoria coloridos; datas em minúsculas ("28 de julho de 2026") |
| Cabeçalho | Logo à esquerda, leaderboard 728x90 à direita; menu com 10 itens |
| Rodapé | Fundo azul, logo branca, "MAIS LIDAS", crédito bahia.ba, **sem** "XYZScripts" e **sem** texto em inglês |
| `/politica/` e outra editoria | Botão "VER MAIS NOTÍCIAS" azul; `/politica/page/3/` + clique carrega a página 4 |
| `/ultimas-noticias/page/2/` | Conteúdo **diferente** da página 1 |
| Post individual | Byline com dois autores linkados separadamente; sem Pinterest |
| `/colunistas/<slug>/` | **Responde 200** — é a URL real de produção (`author_base`, seção 1.10). Se der 404, os links de colunista publicados quebraram todos |
| `/author/<slug>/` | **Lista matérias** (inclusive as de coautoria); título com o nome certo; responde em ~3 s. **Atenção:** em produção este endereço é 404 hoje; conferir junto com o `/colunistas/` acima |
| Popup de anúncio | Aparece após ~2 s na home (grupo 18 do AdRotate, seção 1.10). Se sumir, é receita perdida sem aviso |
| Push do app | Publicar uma matéria de teste e confirmar que o **app Android/iOS** recebeu — não só o navegador (filtro OneSignal, seção 1.10) |
| `/?s=bahia` | Resultados + "Ver mais resultados" |
| 404 | Em português; botão azul apontando para a **home de produção**; lista de notícias recentes; **responde em ~2 s** |
| `/quem-somos/` | Fotos da equipe corretas (checar Lizandra e Tauany); `<title>` = **`Quem Somos - bahia.ba`**, sem "Jornalismo confiável e contextualizado" |
| `<title>` das editorias | `Política - bahia.ba` — **sem** ": últimas notícias" e **sem** "Archive" (seção 1.8) |
| Menu do painel | As **nove** ocultas sumiram: Posts, Bahia, Especial, Exclusivo, Mais Gente, Entrevistas, Economia, Mais Notícias, Carnaval (seção 6.4) |
| Feeds (`/feed/`, `/politica/feed/`, `/feed/feedbahiaba/`) | **Todos em 410, em menos de 2 s.** 404 = mu-plugin fora do ar; demora ou 504 = o corte em `parse_request` não agiu (seção 1.9) |

### 6.2 Desempenho — comparar com o baseline da seção 4.7

Medir logo depois e comparar:

```bash
for u in / /politica/ /author/neison-cerqueira/ /nao-existe-teste/ /sitemap_index.xml; do
  printf "%-32s " "$u"
  curl -s -o /dev/null -w "%{http_code} %{time_total}s\n" "https://bahia.ba$u"
done
```

Dois números merecem atenção especial:

- **404**: tem que ficar em ~2 s. Se subir para dezenas de segundos, é o `ajax_pagination`
  (ver `HANDOVER.md`). Lembrando que **404 não entra no fastcgi_cache**, então o custo é
  pago em toda requisição.
- **Sitemap**: baseline **0,62 s a quente**. Se passar de ~5 s em regime, aplicar a
  contingência da seção 6.3.

### 6.3 Contingência do sitemap

O sitemap de **homolog** responde 504 por *sizing* do RDS, não por defeito de código
(ver `HANDOVER.md`). Produção hoje responde bem. Se degradar após a migração, o primeiro
remédio é um índice de cobertura — **aditivo e reversível**, não altera dado:

```sql
-- Aplicar (fora do pico; em tabela grande pode levar minutos)
CREATE INDEX idx_bahia_sitemap
    ON wp_posts (post_type, post_status, post_modified_gmt);

-- Conferir se o otimizador passou a usá-lo
EXPLAIN SELECT ID, post_modified_gmt FROM wp_posts
 WHERE post_type='politica' AND post_status='publish'
 ORDER BY post_modified_gmt DESC LIMIT 1000;

-- Reverter, se não ajudar ou se atrapalhar a escrita
DROP INDEX idx_bahia_sitemap ON wp_posts;
```

Se o índice não resolver, os próximos passos, nesta ordem: (1) aumentar o
`innodb_buffer_pool_size` da instância; (2) reduzir `entries_per_page` do Yoast — **atenção**,
em homolog isso **não** resolveu; (3) desligar os sitemaps dos CPTs de menor tráfego.

### 6.4 O painel: nove editorias ocultas, que a virada resolve sozinha — mas confira

Em 18/08/2026 nove editorias saíram do menu do painel a pedido da redação: **Posts** (o tipo
nativo), **Bahia, Especial, Exclusivo, Mais Gente, Entrevistas, Economia, Mais Notícias e
Carnaval**. É omissão, não remoção: `public`, `publicly_queryable`, `show_ui` e `has_archive`
seguem ligados, os arquivos e as URLs continuam no ar, e quem precisar chega por
`edit.php?post_type=<slug>`.

**Hoje isso só vale por inteiro em homolog, e a virada é o que corrige.** O motivo:

- as 8 editorias saem por `'show_in_menu' => false` no mapa do `bahia-editorias-cpt.php`, que
  registra em `init` **prioridade 0**;
- o `bahia_refactor` registra os mesmos CPTs em `init` **prioridade 10** — depois, portanto — e
  devolve `show_in_menu => true`, desfazendo a omissão. Enquanto o tema antigo estiver no ar,
  as 8 continuam visíveis na produção;
- o **Posts** nativo não passa pelo mapa: vai por filtro em `register_post_type_args`, que pega
  qualquer registro, inclusive o do core. Esse já some nos dois ambientes.

Medido na produção em 18/08, logo depois do deploy `prod-de88838f`:

| tipo | `show_in_menu` na produção | por quê |
|------|---------------------------|---------|
| `post` | `false` | filtro, vence sempre |
| `mais_gente` | `false` | o tema **não registra** este CPT — ver abaixo |
| os outros 7 | `true` | o tema re-registra e sobrescreve |

O caso do `mais_gente` é a mesma podridão já documentada no commit `104be34f`: existe
`themes/bahia_refactor/post-types/mais_gente.php`, mas a lista escrita à mão em
`functions.php:56` inclui `'gente'` e **não** `'mais_gente'`. O tema nunca carrega o arquivo,
não registra o tipo, e o registro do mu-plugin prevalece.

**Na virada, portanto, não há nada a executar** — o `bahia_refactor` para de rodar, ninguém
mais sobrescreve, e as nove somem sozinhas. O que há é a **conferência** da tabela em 6.1.

Se em algum momento for preciso ocultá-las na produção **antes** da virada, o caminho é mover
as 8 do mapa para o mesmo filtro `register_post_type_args` que já cuida do `post` — ele roda
em qualquer registro e venceria o do tema. Não foi feito porque, no cenário de hoje, mudaria o
painel que a redação usa em produção, e o pedido nasceu no contexto de homolog.

**Para devolver uma editoria ao menu:** apagar o `'show_in_menu' => false` da linha dela em
`bahia_editorias_map()`. Não precisa de flush de rewrite — `show_in_menu` não entra em regra de
reescrita, e por isso a versão do plugin não foi bumpada.

Duas destas ainda publicavam quando foram ocultadas, e isso está registrado para não virar
mistério: **Bahia** (420 matérias em 90 dias, última em 28/07/2026) e **Economia** (53 em 90
dias, última em 24/07/2026). Não é editoria morta — é consolidação editorial.

---

## 7. Sugestão a discutir na ocasião: apontar o DNS para o Swarm antigo

**Ainda não é uma decisão. É uma opção com riscos reais, para ser avaliada na hora.**

> **18/08/2026 — a premissa mudou, a recomendação não. NÃO usada na virada de hoje.**
>
> Este documento e o comentário do `bahia-offload-reconciliation.php` partiam de que a VPS
> antiga estava fora de alcance do cluster ("em outra VPC e provavelmente inalcançável a
> partir do EKS"). **É falso, e foi medido do próprio pod de produção:**
>
> ```
> 172.31.0.178:80    -> porta 80 ABERTA
> 172.31.70.197:3306 -> ABERTA   (o RDS de produção)
> ```
>
> A faixa `172.31/16` é o VPC **default**, onde vive o próprio banco de produção — os pods
> falam com ele o dia inteiro. Existe rota, e o Swarm antigo está de pé.
>
> **Isso não promove a ideia a recomendável.** Os dois bloqueantes da lista abaixo continuam
> inteiros e nenhum é de rede: o **certificado HTTPS** válido para `bahia.ba` no IP antigo
> (item 2) e o **destino das publicações feitas durante a janela** (item 4), que iriam para o
> banco do Swarm e sumiriam na volta do DNS. Alcançabilidade nunca foi o obstáculo.
>
> Fica registrado para que ninguém descarte nem adote a opção pelo motivo errado.

**A ideia:** durante a janela, apontar o DNS de `bahia.ba` para o Docker Swarm antigo
(**54.243.117.103**), que ainda está no ar. O visitante continua vendo um site funcionando
enquanto a migração acontece no cluster novo. Terminada e conferida, o DNS volta — e só
então a VPS antiga é encerrada.

**O que isso resolve:** elimina o downtime visível da janela.

**Os riscos, que precisam estar resolvidos ANTES:**

1. **TTL do DNS.** Se o TTL do registro estiver em 3600 s, a troca leva até uma hora para
   propagar — e a volta, outra hora. O TTL precisa ser **baixado para 60 s com pelo menos 24 h
   de antecedência**. Sem isso, a ideia não funciona: parte dos usuários ficaria no destino
   errado por muito tempo, em ambas as direções.
2. **Certificado HTTPS no IP antigo.** O Swarm precisa ter certificado válido para
   `bahia.ba` **não expirado**. Se o certificado de lá venceu (é provável, se a renovação
   automática parou quando o tráfego saiu), o visitante recebe aviso de site inseguro — bem
   pior do que uma página de manutenção. **Conferir antes:**
   ```bash
   echo | openssl s_client -connect 54.243.117.103:443 -servername bahia.ba 2>/dev/null \
     | openssl x509 -noout -dates -subject
   ```
3. **Conteúdo desatualizado.** O Swarm tem o banco de antes da migração para o EKS. Durante
   a janela, o site mostra o acervo daquela data — sem as matérias publicadas desde então.
   Para uma janela curta e de madrugada, aceitável; para uma janela longa, não.
4. **O risco maior: publicação no banco errado.** Se a redação publicar (ou editar) enquanto
   o DNS aponta para o Swarm, a matéria vai para o **banco antigo** e **some** quando o DNS
   voltar. Não há merge automático possível.
   **Mitigação obrigatória:** congelamento editorial combinado por escrito, com hora de
   início e fim, e o painel do Swarm bloqueado para publicação durante a janela. Sem esse
   acordo, a opção não deve ser usada.
5. **Ordem de desligamento.** A VPS antiga só pode ser encerrada **depois** de o DNS ter
   voltado, propagado e o site novo ter sido validado. Desligar antes remove o plano B.

**Alternativa mais simples, se algum risco acima não puder ser eliminado:** fazer a janela de
madrugada com uma página de manutenção estática no próprio cluster novo. Como as etapas 3 a 7
da seção 5 não mudam nada para o visitante, a indisponibilidade real fica restrita às etapas
8 a 11 — poucos minutos.

---

## 8. Infraestrutura: cache por dispositivo e ambientes separados

**Escrito em 12/08/2026.** Nada aqui estava no documento original, e dois itens mudam o que
você faz na virada.

### 8.1 Os caminhos dos manifestos mudaram

O `kubernetes/` do `infra-bahiaba` virou uma árvore por ambiente (commit `1affeac`):

```
kubernetes/homolog/{namespace.yaml,nginx/,wordpress/,ingress/}
kubernetes/prod/{namespace.yaml,nginx/,wordpress/,ingress/,cluster-autoscaler/}
```

O sufixo `-prod` **saiu dos nomes**: é `kubernetes/prod/wordpress/deployment.yaml`, não
`deployment-prod.yaml`. Qualquer caminho `kubernetes/nginx/...` citado em documento antigo
está desatualizado.

Consequência prática: mexer em `kubernetes/prod/**` roda **só** o pipeline de produção;
mexer em `terraform/**` ou `.github/workflows/**` ainda roda **os dois**, porque ali o
acoplamento é real (mesmo módulo Terraform, mesmo arquivo de workflow).

### 8.2 A chave do cache distingue dispositivo — e tem que continuar distinguindo

Até 12/08 a chave era só `$scheme$request_method$host$request_uri`. Como o tema decide o HTML
no servidor, a mesma entrada guardava uma variante só e **37% dos acessos desktop recebiam o
HTML de celular**. A chave agora é:

```nginx
fastcgi_cache_key "$scheme$request_method$host$request_uri|d=$bahia_mobile$bahia_ipad";
```

> **Não remova essa dimensão achando que o Newspaper é responsivo e não precisa.** Ele
> também varia no servidor. Medido em homolog em 12/08, com o cache furado e variando só o
> User-Agent, a diferença sistemática entre desktop e celular (descontado o ruído de anúncio
> rotativo) é o widget "voltar ao topo": `<div class="td-scroll-up">` e o `tdToTop.js`, que
> só saem no desktop. Vem de `plugins/td-composer/legacy/Newspaper/header.php:20-24`.

A consequência de errar é menor do que hoje — um widget, não o layout inteiro — mas é a
mesma classe de bug.

### 8.3 O detalhe que só aparece na virada: a versão da Mobile-Detect troca

`header.php:20` do td-composer faz `if (class_exists('Mobile_Detect'))` e chama
`$mobile_detect->isMobile()`. **É a mesma biblioteca que o nginx espelha** — mas existem duas
cópias no repositório, e a que vale é a que for carregada:

| Quem carrega | Arquivo | Versão | Regras |
|---|---|---|---|
| tema legado (produção hoje) | `themes/bahia_refactor/Mobile-Detect/Mobile_Detect.php` | **2.8.41** | 183 |
| td-composer (depois da virada) | `plugins/td-composer/includes/Mobile_Detect.php` | **2.8.34** | 179 |

O regex do nginx foi gerado a partir da **2.8.41**. Quando o `bahia_refactor` sair de cena, a
classe passa a vir da **2.8.34**, que difere em 4 regras ausentes (`Pixel`, `Xiaomi`,
`XiaomiTablet`, `SailfishOS`) e em 14 com o mesmo nome e regex diferente — entre elas
`Chrome`, `Safari`, `Firefox` e `Edge`.

**Impacto medido, não estimado:** contra 33.660 requisições reais de produção (1.721
User-Agents distintos), o regex do nginx diverge da 2.8.34 em **1 requisição**. O caso é:

```
Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/145 Version/11.1.1 Safari/605.1.15
```

um UA que diz macOS e CriOS ao mesmo tempo. A regra `Chrome` da 2.8.34 é `CriOS` puro; a da
2.8.41 é `CriOS.*Mobile`. Aceitável — mas se quiser zero, regenere o regex a partir da cópia
do plugin (procedimento em 8.4) na janela da migração.

### 8.4 Como regenerar o regex, se precisar

O regex é um **subconjunto** das regras da própria biblioteca. É o subconjunto que dá a
garantia: o nginx nunca classifica como mobile algo que o PHP considera desktop. **Nunca
invente tokens fora da lib** (`mobile`, `tablet`) — aí aparece falso positivo e o bug volta
invertido.

1. `Mobile_Detect::getMobileDetectionRules()` = phoneDevices + tabletDevices +
   operatingSystems + browsers. É exatamente o que `isMobile()` consulta.
2. Junte as regras num alternado. **Não use o conjunto completo**: são 26 KB, e custam
   4,3 ms por requisição sem PCRE JIT (o nginx da imagem não declara `--with-pcre-jit`). O
   recorte de 30 regras em uso custa 3 µs.
3. Valide contra um corpus real antes de confiar:
   `kubectl logs <pod> -c nginx --tail=50000` (vai para stdout; um `--tail` muito grande
   volta vazio). O User-Agent é o **penúltimo** campo entre aspas.
4. Só publique com **zero divergência** no corpus.

### 8.5 Acrescentar às verificações

**Antes** (seção 4): anotar o comportamento atual, para comparar depois.

```bash
for ua in "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36" \
          "Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1"; do
  curl -sI https://bahia.ba/ -H "User-Agent: $ua" | grep -i 'x-bahia-device\|x-fastcgi-cache'
done
```

`X-Bahia-Device` é **00** desktop, **10** celular, **11** iPad.

**Depois** (seção 6.1), acrescentar à tabela:

| Verificação | O que tem que estar certo |
|---|---|
| Mesma URL, dois User-Agents | `X-Bahia-Device` muda de `00` para `10`; o HTML de desktop tem `td-scroll-up` e o de celular não |
| 20 acessos seguidos com UA de desktop | **todos** com `00` — se aparecer `10` no meio, a chave regrediu |
| Sitemap e `/feed/` | continuam em `BYPASS` (não têm `Cache-Control` nosso) |

> Ao conferir no navegador, lembre que agora existe `Cache-Control: public, max-age=60,
> must-revalidate` nas respostas cacheáveis. Uma página pode ficar até 60 s no cliente antes
> de revalidar — não confunda isso com cache do servidor.

### 8.6 Duas coisas que a virada vai esbarrar

- **Cache frio.** Todo `rollout restart` zera o cache: `/tmp/nginx-cache` é `emptyDir`. Já
  existe `fastcgi_cache_lock` e `use_stale updating` para segurar o estouro no PHP, mas a
  primeira janela depois da virada tem menos acerto de cache. O rollout é gradual
  (`maxSurge: 1 / maxUnavailable: 0`), então há sempre um pod quente durante parte dele.
- **Deployment `nginx` órfão.** O de réplica única não atende tráfego nenhum: o Service
  `nginx` seleciona `app: wordpress`, então quem serve é o sidecar dos pods do WordPress.
  Ele é aplicado e reiniciado a cada deploy à toa, reservando 256Mi/250m. Bom momento para
  aposentar, se for aposentar.

---

## 9. Resumo de uma linha

Migre por **script idempotente que resolve por slug**, deixe **`td_011` por último** para que
a virada seja atômica, e confirme depois que **404 responde em ~2 s** e que
**`/author/<slug>/` lista matérias** — são os dois indicadores que denunciam problema. Do lado
da infraestrutura, os manifestos agora vivem em `kubernetes/prod/**` (seção 8.1) e a chave do
cache distingue dispositivo: confira `X-Bahia-Device` em 20 acessos de desktop seguidos antes
de dar a virada por encerrada (seção 8.5).
