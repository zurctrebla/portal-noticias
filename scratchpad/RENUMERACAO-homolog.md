# Renumeração dos registros nascidos em homolog

**Estado: EXECUTADA em 16/08/2026, validada. Etapa 5 (documentos) pendente.**

> **Registro da execução.** 117 registros movidos para 9.000.001–9.000.188 pela fórmula
> `novo = 9.000.000 + (antigo − 547.290)`; os 7 rascunhos de teste foram apagados antes.
> Mapa completo em `wp_bahia_renum_map` (117 linhas) — **não apagar**.
> Backup anterior: `~/BAHIABA-backups/backup-hml-pre-import-20260816-1603.sql.gz`,
> SHA-256 `d3c0e9ad…f349a6bd`. Procedimento de volta em `RESTAURACAO-20260816.md`.
>
> Correspondências mais consultadas:
>
> | Antes | Depois | O que é |
> |---|---|---|
> | 547414 | **9000124** | Header Template - Magazine PRO |
> | 547416 | **9000126** | Footer - Magazine PRO |
> | 547430 | **9000140** | 404 Template |
> | 547432 | **9000142** | página Home (`page_on_front`) |
> | 547369 | **9000079** | página Quem Somos |
> | 547365 | **9000075** | logo colorida |
> | 547458 | **9000168** | logo branca |
> | 547478 | **9000188** | favicon (`site_icon`) |
>
> Duas notas do que foi aprendido na execução:
>
> 1. **O `.maintenance` derruba também o script de manutenção**, porque ele carrega o
>    WordPress. A porta prevista pelo core é `define('WP_INSTALLING', true)` antes do
>    `wp-load.php`: `wp_maintenance()` se abstém quando `wp_installing()` é verdadeiro, e o
>    site segue fora do ar para o visitante.
> 2. **`$wpdb->update` em vez de `wp_update_post`** para o `post_content` dos templates.
>    Sem usuário logado, o `wp_update_post` passa o conteúdo pelo kses e filtraria o HTML
>    do template; além disso geraria revisão nova a cada gravação. A troca foi reconferida
>    lendo de volta do banco, que é a garantia que o `wp_update_post` daria.
>
> O texto abaixo é o plano como foi aprovado, com os IDs **antigos**, de propósito: é o
> registro histórico da operação.

Etapas 2 e 3 da ordem definida em `IMPORT-prod-para-homolog.md` §0.1. A renumeração é
operação separada do import, com validação própria entre as duas.

Levantamento feito em 16/08/2026 no pod `wordpress-8f6498988-ch5gd`, namespace
`bahia-wordpress`, banco `prod` no RDS `rds-bahiaba-hml` — que é o banco de **homolog**.

---

## 1. O que muda, e a fórmula

**`novo_id = 9.000.000 + (id_atual − 547.290)`**

Aplica-se apenas aos registros nascidos em homolog. 547291 vira 9000001; 547478 vira
9000188. Invertível de cabeça, preserva a ordem, auditável linha a linha.

Justificativa da faixa e do `AUTO_INCREMENT` em `IMPORT-prod-para-homolog.md` §0.

---

## 2. Os 124 registros

Fonte da verdade, sempre — a tabela abaixo é resumo, não substituto:

```sql
SELECT ID, post_type, post_status, post_title FROM wp_posts WHERE ID >= 547291 ORDER BY ID;
```

| Tipo | Qtd. | O que é |
|---|---|---|
| `revision` | 40 | revisões dos templates e da Quem Somos |
| `attachment` | 37 | 22 fotos da equipe, 2 colunistas, logos, favicons, fundos, 2 peças de anúncio |
| `nav_menu_item` | 22 | menus principal e do rodapé, remontados aqui |
| `tdb_templates` | 13 | 9 Magazine PRO em uso + 4 Default PRO (código morto, mas migram junto) |
| `page` | 5 | Brasileirão 2026, **Quem Somos**, Home, Home-2 (`page_on_front`), bahia.ba |
| `politica` | 7 | 5 rascunhos automáticos e 2 rascunhos de teste |

Os 7 `politica` são lixo de teste. **Sugestão: apagar antes de renumerar**, em vez de
carregá-los para a faixa reservada. Reduz a operação a 117 registros. Decisão de quem
aprova.

### Os que não podem quebrar, nominalmente

| ID | O que é | Consequência de errar |
|---|---|---|
| 547432 | página Home — é o `page_on_front` | home do site fora do ar |
| 547414 | Header Template - Magazine PRO | cabeçalho some de todas as páginas |
| 547416 | Footer - Magazine PRO | rodapé some |
| 547430 | 404 Template | página de erro sem template |
| 547369 | página Quem Somos | página institucional com as 22 fotos |
| 547478 | favicon (`site_icon`) | ícone da aba |
| 547365 / 547458 | logo do cabeçalho / logo branca do rodapé | logo quebrada nos dois lugares |

---

## 3. Mapa de dependências — varrido no banco, não herdado

O mapa do `MIGRACAO-homolog-para-prod.md` §2 foi o ponto de partida. **Ele estava
incompleto**: não citava `wp_as3cf_items`, `wp_yoast_indexable`, `site_icon`,
`tdc_header_template_id`/`tdc_footer_template_id`, nem o fato de os IDs no `td_011` virem
com prefixo. Tudo abaixo foi medido.

### 3a. Chaves estrangeiras — atualização por coluna

| Tabela / coluna | Linhas | Observação |
|---|---|---|
| `wp_posts.ID` | 124 | os próprios registros |
| `wp_posts.post_parent` | 39 | revisões apontando para templates e páginas |
| `wp_postmeta.post_id` | 473 | todas as metas dos 124 |
| `wp_term_relationships.object_id` | 22 | itens de menu ↔ taxonomia `nav_menu` |
| `wp_yoast_indexable.object_id` | 20 | + 20 linhas em `wp_yoast_indexable_hierarchy` |
| **`wp_as3cf_items.source_id`** | **37** | **offload S3 — sem isto as 22 fotos da equipe perdem o mapeamento para o CloudFront** |
| `wp_comments.comment_post_ID` | 0 | nada a fazer |
| `wp_bahia_search_idx.ID` | 0 | nada a fazer |
| `wp_historico_destaques.post_id` | 0 | nada a fazer |

### 3b. Referências por valor — cada uma tem formato próprio

| Onde | Valor hoje | Formato |
|---|---|---|
| `wp_options.page_on_front` | `547432` | ID puro |
| `wp_options.site_icon` | `547478` | ID puro |
| `wp_options.td_011` — 9 chaves | `tdb_template_547414` etc. | **prefixo + ID**, não ID puro |
| `wp_postmeta.tdc_header_template_id` | 547301, 547414 | auto-referência (o template guarda o próprio ID) |
| `wp_postmeta.tdc_footer_template_id` | 547299, 547416 | auto-referência |
| `wp_postmeta._menu_item_object_id` | 547304, 547330 | auto-referência de link personalizado |
| `post_content` de 547416 | `547458` | **texto puro** |
| `post_content` de 547301 e 547414 | `547365` | **dentro de bloco base64** |
| `wp_options.rewrite_rules` | 547432 | regenerável — `flush_rewrite_rules()` no fim |

As 9 chaves do `td_011`: `tdb_header_template`, `tdb_footer_template`, `tdb_404_template`,
`tdb_author_template`, `tdb_search_template`, `tdb_category_template`, `tdb_tag_template`,
`tdb_date_template`, `td_default_site_post_template`.

O caso do base64 é o que o `MIGRACAO.md` já avisava: **um ID dentro de bloco base64 não é
encontrado por SQL.** É preciso ler o `post_content`, localizar o bloco, decodificar,
trocar, recodificar e gravar. Foi assim que os textos do 404 foram corrigidos na rodada 4.

### 3c. O que NÃO precisa de ação — verificado, não presumido

- **Os menus não apontam para as páginas por ID.** Os itens "Quem Somos" (547304, 547330)
  são links do tipo `custom`, com URL `https://hml.bahia.ba/quem-somos`. Renumerar a página
  547369 não os afeta, desde que o slug não mude.
- **A Quem Somos referencia as 22 fotos por caminho de arquivo**, não por ID de anexo. O
  `post_content` não tem `wp-image-NNN` nem base64. Renumerar os anexos não mexe no
  conteúdo — mas `as3cf_items` (3a) continua obrigatório.
- `theme_mods_Newspaper`: `nav_menu_locations` usa `term_id` (78521/78522), não ID de post;
  `custom_css_post_id` = −1.
- **Backups em `wp_options`** (`bahia_predemo_backup_*`, `bahia_rodada2_backup`,
  `bahia_full_magpro_backup_*`, `bahia_footer_menu_backup_*`, `bahia_r10_siteicon_backup_*`):
  são retratos históricos e **não devem ser atualizados**. Registre a armadilha: **restaurar
  qualquer um deles depois da renumeração reintroduz os IDs antigos** e desfaz esta operação
  em silêncio.
- `wp-smush-error-items-list`: estado do plugin de compressão, cosmético.

### 3d. Sete números que parecem ID e não são

Esta é a razão de o método ser por coluna enumerada e **nunca** busca-e-troca global:

| Onde | Número | O que é de verdade |
|---|---|---|
| `_wp_attachment_metadata` do post 350121 | 547460 | `filesize` em bytes |
| `_wp_attachment_metadata` do post 437640 | 547383 | `filesize` em bytes |
| `_wp_attachment_metadata` do post 455110 | 547430 | `filesize` em bytes |
| `wp-smpro-smush-data` do post 361839 | 547381 | bytes economizados |
| `response_body` do post 462087 | 547414 | trecho de um UUID (`95ad5f547414`) |
| `rank_math_og_content_image` do post 234898 | 547407 | trecho de um hash MD5 |
| `post_content` do post 19267 | 547469 | `id="dv-547469"` de um `<div>` de 2019 |

Um `UPDATE ... REPLACE(campo,'547430','9000140')` global transformaria o tamanho de um
arquivo em outro número, corromperia um UUID e quebraria dois hashes. **Nenhum comando
deste plano pode ser um REPLACE cego sobre a tabela inteira.**

---

## 3.5 O código — porque o site não vive só no banco

Varredura dos 124 IDs em `mu-plugins/`, `themes/` (inclusive `bahia_refactor`), `php/`,
`.github/`, `Dockerfile` e `w3tc-config/`. Os `mu-plugins/` do pod são idênticos aos do git
(`diff` vazio), então uma varredura cobre os dois.

**23 ocorrências no total. 21 são comentário. Duas são código executável.**

### 3.5a O único caso funcional: `bahia-logo-rodape.php`

```php
define('BAHIA_LOGO_RODAPE_ATUAL', 547458);   // linha 74 — logo branca, a que sai
define('BAHIA_LOGO_RODAPE_NOVA',  547365);   // linha 78 — logo colorida, a que entra
```

O plugin faz `wp_get_attachment_url(BAHIA_LOGO_RODAPE_ATUAL)` para localizar a logo no HTML
e trocá-la pela colorida. Depois da renumeração esses IDs deixam de existir,
`wp_get_attachment_url()` devolve `false`, e a guarda do próprio plugin

```php
if (!$antiga || !$nova || strpos($html, $antiga) === false) { return $html; }
```

faz ele **não fazer nada, em silêncio**. O site continua no ar, o rodapé continua com logo —
só que a branca, a antiga, e ninguém recebe erro nenhum. É exatamente a falha silenciosa que
motivou esta varredura.

**Ação:** atualizar as duas linhas para `9000168` e `9000075` **na mesma janela da
renumeração**, e conferir o rodapé na validação visual (item 3 do checklist 6b).

### 3.5b Os que foram descartados, e por quê

| Arquivo | Por que não quebra |
|---|---|
| `bahia-quem-somos.php` | usa `is_page('quem-somos')` — **slug**, não ID |
| `bahia-futebol-display.php` | não cita ID do WordPress; 1777/1782 são IDs de time da API |
| `bahia-home-destaques.php` | lê IDs das ACF Options em runtime, não crava nenhum |
| `bahia-scroll-infinito.php` | cita 547291 e 547281 só em comentário |
| `bahia-mobile-r11.php`, `bahia-header-ad.php`, `bahia-cabecalho-r10.php`, `bahia-datas-minusculas.php`, `bahia-social-nova-aba.php`, `bahia-publicidade.php` | citam IDs apenas em comentário de cabeçalho |
| `themes/`, `php/`, `.github/`, `Dockerfile`, `w3tc-config/` | nenhuma ocorrência |

Os 21 comentários não quebram nada, mas ficam mentindo. Atualizá-los junto com os documentos
(seção 7).

---

## 3.6 Os 7 rascunhos de teste — seguro apagar

Aprovado apagar antes, o que reduz a operação a **117 registros**. Conferido que nada aponta
para eles:

| Verificação | Resultado |
|---|---|
| `wp_posts.post_parent` | 0 |
| `wp_term_relationships` | 0 |
| `wp_comments` | 0 |
| `wp_yoast_indexable` | 2 (linhas **deles**, removidas junto) |
| `wp_as3cf_items` | 0 |
| `wp_historico_destaques` | 0 |
| ACF Options dos destaques da home | **nenhum dos 7 aparece** |
| `noticias_relacionadas` de qualquer post | 0 ocorrências |
| `wp_postmeta` | 8 linhas, metas **deles próprios** |

São: #547447 (rascunho sem título), #547476 (rascunho "Jerônimo é o 6º governador mais
rico…") e 5 rascunhos automáticos (#547459, #547460, #547463, #547475, #547477).

Apagar com `wp_delete_post($id, true)`, que remove metas e relações junto — não com `DELETE`
cru, que deixaria as 8 metas órfãs.

---

## 3.7 O logo em base64 — método definido e testado

**Decodificar, substituir no nível 1 e recodificar. Testado, é confiável.**

O que foi medido, sem gravar nada:

| | #547414 (header vivo) | #547301 (header morto) | #547416 (rodapé) |
|---|---|---|---|
| `post_content` | blob base64 único, 34.476 ch | blob base64, 19.628 ch | **texto puro**, 4.694 ch |
| Decodificado | 25.857 bytes de shortcode | — | n/a |
| Ida e volta `base64_encode(base64_decode(x)) === x` | **SIM** | **SIM** | n/a |
| Ocorrências do ID no nível 1 | 4 | 4 | 1 |
| Candidatos base64 aninhados | 51 | 43 | 11 |
| **Aninhados contendo o ID** | **0** | **0** | **0** |

Os 4 usos no header são todos `[tdb_header_logo image="547365" …]`, em texto legível depois
de uma única decodificação. Varri os 51 blocos aninhados, e mais um nível abaixo deles:
**nenhum contém o ID**. Ou seja, a troca no nível 1 cobre 100% das ocorrências — não há
referência escondida.

Simulação da troca (sem gravar): 4 substituições, `post_content` de 34.476 → 34.484 bytes,
e ao decodificar o resultado ele bate exatamente com o texto substituído. Nenhum resquício
do ID antigo.

**Procedimento, com trava:**

1. Ler `post_content`.
2. Se casar `^[A-Za-z0-9+/]+={0,2}$`, decodificar com `base64_decode($c, true)`.
3. **Abortar se `base64_encode($decodificado) !== $original`.** Sem ida e volta idêntica,
   não se grava nada.
4. `str_replace` do ID antigo pelo novo no texto decodificado.
5. Conferir que o número de substituições é o esperado (4, 4 e 1).
6. Recodificar e gravar via `wp_update_post()` com `$post_content` — **não** via SQL cru, para
   os hooks do tagDiv rodarem.
7. Reler do banco, decodificar e conferir que o ID antigo não sobrou.

O #547301 é código morto e poderia ser ignorado, mas o custo de tratá-lo é zero e deixá-lo
com um ID inexistente só criaria confusão depois. **Vai junto.**

**A validação visual do cabeçalho continua obrigatória** (item 2 do checklist 6b), não como
plano B, mas porque a substituição acertar o banco não prova que o tagDiv renderiza — o
template também guarda CSS compilado e pode ter cache próprio.

### 3.7b A logo do cabeçalho MOBILE está dentro das mesmas 4 ocorrências

Verificado, porque uma chave mobile fora do blob quebraria em silêncio como o
`bahia-logo-rodape.php`. **Não está fora.**

O `post_content` do 547414, depois de uma decodificação, é **JSON com quatro chaves de
texto**, e o 547365 aparece **exatamente uma vez em cada**:

| Chave do JSON | Tamanho | Ocorrências de 547365 |
|---|---|---|
| `tdc_header_desktop` | 12.235 bytes | 1 |
| `tdc_header_desktop_sticky` | 7.063 bytes | 1 |
| **`tdc_header_mobile`** | 2.617 bytes | **1** |
| **`tdc_header_mobile_sticky`** | 2.684 bytes | **1** |

Somam as 4 ocorrências já contadas. **Uma única passada de decodificar → substituir →
recodificar cobre desktop, desktop fixo, celular e celular fixo de uma vez.**

Onde a chave mobile **não** está, conferido: não há option com `tdc_header` ou
`header_mobile` no nome; as únicas metas do 547414 são `tdc_header_template_id` (já mapeada)
e `header_mobile_menu_id`, que está **vazia**; e nenhuma chave do `td_011` com "mobile" no
nome contém o ID.

### 3.7c Favicon, logos e as duas fotos avulsas — situação de cada um

| ID | O que é | Onde é referenciado | Ação |
|---|---|---|---|
| 547365 | logo colorida | base64 de **547301 (4x)** e **547414 (4x)**; constante em `bahia-logo-rodape.php:78` | troca no conteúdo + constante |
| 547458 | logo branca | **texto puro** de 547416 (1x); constante em `bahia-logo-rodape.php:74` | troca no conteúdo + constante |
| 547478 | favicon atual | `wp_options.site_icon` | `update_option` |
| 547407 | favicon transparente | só a option `bahia_r10_siteicon_backup_20260814-111724` — **backup histórico, não atualizar** | só o próprio ID |
| 547366 | favicon antigo | **nenhuma referência** | só o próprio ID |
| 547455 | foto Lizandra Capistrano | **nenhuma** — a Quem Somos cita por caminho de arquivo | só o próprio ID |
| 547456 | foto Tauany Alves | **nenhuma** — idem | só o próprio ID |

Os sete têm **uma linha cada em `wp_as3cf_items`**, o que confirma que a atualização de
`source_id` vale para os 37 anexos sem exceção. O `MIGRACAO.md` §2 dizia que 547455 e 547456
estavam no `post_content` da Quem Somos: **não estão** — a página referencia todas as imagens
por caminho de arquivo, sem `wp-image-NNN`.

---

## 4. Método

### 4.0 Pré-condições

1. Backup da seção 1 do `IMPORT-prod-para-homolog.md` **concluído e verificado**.
2. `SELECT option_value FROM wp_options WHERE option_name='siteurl'` = `https://hml.bahia.ba`.
   Todo script começa com a guarda que aborta se não for.
3. Confirmar que as tabelas envolvidas são InnoDB — sem isso não há transação:
   ```sql
   SELECT table_name, engine FROM information_schema.TABLES
   WHERE table_schema=DATABASE()
     AND table_name IN ('wp_posts','wp_postmeta','wp_term_relationships','wp_options');
   ```
4. Site em manutenção durante a operação, para não haver escrita concorrente.

### 4.1 Gravar o mapa antes de mexer em qualquer coisa

```sql
CREATE TABLE wp_bahia_renum_map (
  id_antigo BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  id_novo   BIGINT UNSIGNED NOT NULL,
  post_type VARCHAR(20) NOT NULL,
  criado_em DATETIME NOT NULL,
  UNIQUE KEY (id_novo)
) ENGINE=InnoDB;

INSERT INTO wp_bahia_renum_map (id_antigo, id_novo, post_type, criado_em)
SELECT ID, 9000000 + (ID - 547290), post_type, NOW()
FROM wp_posts WHERE ID >= 547291;
```

O mapa é o que torna a operação auditável e permite conferir qualquer coisa depois. **Ele
não substitui o backup** — o rollback é pelo backup.

### 4.2 Ordem das atualizações

A faixa nova (9.000.001+) está muito acima da atual, então **não há colisão intermediária** e
a ordem não precisa de artifício. Ainda assim, dentro de **uma transação**:

1. `wp_posts.post_parent` → pelo mapa
2. `wp_postmeta.post_id` → pelo mapa
3. `wp_term_relationships.object_id` → pelo mapa
4. `wp_yoast_indexable.object_id` (só `object_type='post'`) → pelo mapa
5. `wp_as3cf_items.source_id` → pelo mapa
6. `wp_posts.ID` → pelo mapa (por último, para que os passos acima ainda achem o valor antigo)
7. `COMMIT`

Cada passo é um `UPDATE ... JOIN wp_bahia_renum_map`, restrito às linhas do mapa. Exemplo:

```sql
UPDATE wp_postmeta pm JOIN wp_bahia_renum_map m ON m.id_antigo = pm.post_id
SET pm.post_id = m.id_novo;
```

### 4.3 Referências por valor — fora da transação, em PHP, uma a uma

Cada uma tem formato próprio; não existe comando único que sirva para todas.

1. `update_option('page_on_front', 9000142)` — conferir pelo mapa, não digitar à mão.
2. `update_option('site_icon', <novo de 547478>)`.
3. `td_011`: ler a opção, trocar as 9 chaves de `tdb_template_<antigo>` para
   `tdb_template_<novo>`, gravar. **Registrar o filtro em runtime no corpo do mu-plugin
   não vale aqui** — é gravação em banco, não filtro.
4. `tdc_header_template_id` e `tdc_footer_template_id`: 4 linhas, auto-referências.
5. `_menu_item_object_id` dos itens 547304 e 547330: auto-referências.
6. `post_content` de 547416: trocar `547458` pelo novo — **texto puro, mas com verificação
   de contexto**, não `REPLACE` cego.
7. `post_content` de 547301 e 547414: localizar o bloco base64, decodificar, trocar 547365,
   recodificar, gravar. **Conferir que o bloco decodifica e recodifica idêntico antes de
   trocar qualquer coisa** — se o `base64_decode` estrito falhar, pare.

### 4.4 Fechamento

1. `ALTER TABLE wp_posts AUTO_INCREMENT = 9000189;`
2. `flush_rewrite_rules()` — regenera `rewrite_rules`, que cita 547432.
3. Limpar: cache de objeto, FastCGI cache do nginx, opcache do PHP-FPM.
4. Tirar o site da manutenção.

---

## 5. Ponto de rollback

**O ponto de rollback é o backup completo do banco**, feito na seção 1 do
`IMPORT-prod-para-homolog.md`. Restaurar o banco inteiro é o caminho primário e o único
testado.

O `wp_bahia_renum_map` permite reverter programaticamente, invertendo o mapa. **Não conte
com isso como plano principal**: se a falha for no meio das referências por valor (4.3), a
inversão do mapa devolve os IDs mas não desfaz um `post_content` gravado errado.

Regra: **se algo sair do previsto, restaure o banco.** Não corrija por cima.

Não apague `wp_bahia_renum_map` depois. Ela é o registro de que a renumeração aconteceu e a
chave para entender qualquer coisa estranha meses depois — e será necessária na virada, para
conferir que os IDs de homolog e de produção não voltaram a colidir.

---

## 6. Validação depois da renumeração, antes do import

Nenhum item é opcional. Comparar com o estado validado, não com "parece bem".

### 6a. Banco

```sql
-- 124 (ou 117, se os rascunhos forem apagados antes)
SELECT COUNT(*) FROM wp_posts WHERE ID >= 9000001;
-- 0: nada pode ter sobrado na faixa antiga
SELECT COUNT(*) FROM wp_posts WHERE ID BETWEEN 547291 AND 547478;
-- 13
SELECT COUNT(*) FROM wp_posts WHERE post_type='tdb_templates';
-- tem de bater com o mapa
SELECT option_value FROM wp_options WHERE option_name='page_on_front';
-- 37, todas na faixa nova
SELECT COUNT(*) FROM wp_as3cf_items WHERE source_id >= 9000001;
-- 0: nenhuma meta órfã
SELECT COUNT(*) FROM wp_postmeta pm LEFT JOIN wp_posts p ON p.ID=pm.post_id WHERE p.ID IS NULL;
-- 9000189
SELECT AUTO_INCREMENT FROM information_schema.TABLES
 WHERE table_schema=DATABASE() AND table_name='wp_posts';
```

### 6b. Visual — o site tem de estar idêntico

| # | O que | Como saber |
|---|---|---|
| 1 | Home | abre, é a página certa, não o WordPress padrão |
| 2 | Cabeçalho | logo, menu, barra de busca, cores |
| 3 | **Rodapé com a logo COLORIDA** | **obrigatório.** É o sintoma direto do caso `bahia-logo-rodape.php` (§3.5a): se a troca das constantes não pegou, o rodapé volta para a logo **branca** antiga — sem erro nenhum. Logo branca no rodapé = a etapa falhou |
| 3b | Rodapé, o resto | menu de editorias, redes sociais, copyright |
| 4 | Single | subtítulo em Roboto 20px, autor, data, compartilhamento |
| 5 | Archive de editoria | cards, botão "Ver mais", scroll infinito no celular |
| 6 | Busca | resultados, template próprio |
| 7 | 404 | template próprio, não o do tema |
| 8 | Página de autor | lista as matérias |
| 9 | **Quem Somos** | **as 22 fotos carregando** — é o teste do `as3cf_items` |
| 10 | Brasileirão 2026 | boxes do Bahia e do Vitória |
| 11 | Favicon | ícone certo na aba |
| 12 | Publicidade | slots com `data-grupo="N"`, leaderboard no celular |
| 13 | Celular | comparar com as capturas das rodadas 9 e 11 |

O item 9 é o mais informativo: se `as3cf_items` não tiver sido atualizado, as fotos da
equipe são o primeiro lugar onde isso aparece.

### 6c. Parada obrigatória

Reportar o resultado das 6 consultas e dos 13 itens visuais. **Não encadear com o import.**

---

## 7. Atualizar os documentos e os comentários — etapa 5 da ordem

Os documentos do `scratchpad/` são o artefato que alguém vai seguir na virada. Com IDs
antigos, a pessoa procura o que não existe mais — e, pior, encontra em produção um ID igual
apontando para outra matéria.

**Depois da renumeração validada, atualizar:**

| Documento | Ocorrências | O que cita |
|---|---|---|
| `MIGRACAO-homolog-para-prod.md` | **79** | §2 é um mapa inteiro de IDs: 547414, 547416, 547430, 547422, 547428, 547418, 547420, 547424, 547426, 547432, 547369, 547455, 547456, 547458 |
| `AUDITORIA-templates.md` | **42** | inventário dos 13 templates por ID |
| `CHECKPOINT-pre-rodada10.md` | **20** | retrato com IDs |
| `HANDOVER.md` | **10** | §13 e §15 citam os templates; outras seções citam 547432 e 547369 |
| `PUBLICIDADE-slots.md` | **4** | templates onde ficam os slots |
| `IMPORT-prod-para-homolog.md` | 23 | este roteiro |
| `RENUMERACAO-homolog.md` | 82 | este documento |

Os dois últimos são os roteiros da própria operação: atualizá-los apagaria o registro do que
foi feito. Neles, **manter os IDs antigos** e acrescentar a nota — são documentação
histórica da renumeração, não instruções para o futuro.

**Mais os 21 comentários em `mu-plugins/`** listados em §3.5b — mesma razão.

Em cada documento, acrescentar uma nota curta no topo:

> **Renumeração de IDs — DD/MM/2026.** Os registros nascidos em homolog foram movidos para a
> faixa 9.000.001+ pela fórmula `novo = 9.000.000 + (antigo − 547.290)`. Os IDs neste
> documento já estão atualizados. O mapa completo antigo→novo está na tabela
> `wp_bahia_renum_map`, que não deve ser apagada.

Fazer isto **depois** da renumeração, não antes: se ela for revertida pelo backup, os
documentos não podem ter ficado à frente do banco.

### 7b. Só depois de tudo isso, o import

`IMPORT-prod-para-homolog.md` §2, dry-run.
