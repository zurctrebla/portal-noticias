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

| ID | Nome | Itens | Local |
|----|------|-------|-------|
| 78521 | Principal | 10 | `header-menu` |
| 78522 | Rodapé | 10 | `footer-menu` |
| 78523 | Rodapé Legal | 2 | — |

Migram os termos, os itens (`nav_menu_item`) e o `theme_mods[nav_menu_locations]`.

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
| 78521 / 78522 / 78523 | Menus | `theme_mods[nav_menu_locations]` | Recriar/importar, religar os locais |
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
| `/author/<slug>/` | **Lista matérias** (inclusive as de coautoria); título com o nome certo; responde em ~3 s |
| `/?s=bahia` | Resultados + "Ver mais resultados" |
| 404 | Em português; botão azul apontando para a **home de produção**; lista de notícias recentes; **responde em ~2 s** |
| `/quem-somos/` | Fotos da equipe corretas (checar Lizandra e Tauany) |

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

---

## 7. Sugestão a discutir na ocasião: apontar o DNS para o Swarm antigo

**Ainda não é uma decisão. É uma opção com riscos reais, para ser avaliada na hora.**

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
