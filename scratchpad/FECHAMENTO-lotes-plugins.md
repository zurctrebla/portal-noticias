# Fechamento dos 7 lotes de plugins — homolog sobre WordPress 7.1

**Executado em 01 e 02/09/2026.** Ambiente: `hml.bahia.ba`, WordPress **7.1**, PHP **8.3.33**.
Produção segue em WordPress 6.8.8 / PHP 8.3.28.

Este documento é o portão de leitura **antes** de levar qualquer coisa para produção.

---

## 1. Os 12 plugins — antes e depois

| Lote | Plugin | Antes | **Depois** | Salto |
|---|---|---|---|---|
| **1** | Post Type Switcher | 4.0.0 | **4.0.1** | patch |
| **1** | WP Twitter Auto Publish | 1.7.6 | **1.7.7** | patch |
| **1** | Site Kit by Google | 1.180.0 | **1.186.0** | 6 minors |
| **2** | Disable Comments | 2.5.3 | **2.8.0** | minor |
| **2** | Category Order and Taxonomy Terms Order | 1.9.1 | **2.0** | **major** |
| **2** | OneSignal Push Notifications | 3.5.0 | **3.9.2** | minor |
| **2** | FooGallery | 2.4.32 | **3.2.6** | **major** |
| **3** | WP Offload Media Lite | 3.2.11 | **3.3.1** | minor (com 3.3.0 no meio) |
| **4** | Smush | 3.22.1 | **4.3.2** | **major 3 → 4** |
| **5** | Co-Authors Plus | 3.6.6 | **4.1.1** | **major 3 → 4** |
| **6** | Yoast SEO | 27.7 | **28.4** | **major** ⚠️ o plano dizia 28.3 |
| **7** | PublishPress Capabilities | 2.21.0 | **2.50.1** | 29 minors |

> ⚠️ **O lote 6 foi para a 28.4, não para a 28.3.** O canal andou entre o levantamento e a
> execução, e o *updater* instala a versão atual. **Quem procurar por "Yoast 28.3" depois não
> acha — não é que o lote não foi feito.**

**Confirmado no pod que veio da imagem**, depois do último rollout (generation 135):
todas as 12 versões acima, ativas.

---

## 2. O que cada lote mudou no banco — e o que é irreversível

| Lote | Mudou no banco | Reverter sem restaurar dump? |
|---|---|---|
| **1** | **nada** | ✅ trivial — só arquivo |
| **2** | `Disable Comments` `db_version` **7 → 8** (preservou `remove_everywhere`) | ❌ **não** |
| **3** | `as3cf_schema_version` **3.2.11 → 3.3.1** | ❌ **não** |
| **4** | `wp-smush-version` **3.22.1 → 4.3.2** · **6 opções `-json` novas**, duplicando as antigas (~800 KB onde havia 400 KB, todas `autoload=off`) | ❌ **não** |
| **5** | **nada** — sem opção `coauthor*`, sem cron, sem migração | ✅ trivial |
| **6** | `yoast_migrations` **26 → 27** · **2 colunas novas** em `wp_yoast_indexable` (`seo_title_score`, `meta_description_score`) | ❌ **não** |
| **7** | `wp_user_roles`: administrator **+2**, editor **−2** capacidades **do próprio plugin** | ❌ **não** |

### O que NÃO mudou, e é o que mais importa

```
wp_as3cf_items      155.600 -> 155.600      (colunas identicas)
anexos              155.675 -> 155.675
wp_smush postmeta   5.844 e 5.827 linhas    intactos
taxonomia author    179 termos, 253.757 relacoes
wp_yoast_indexable  316 mil linhas, FILE_SIZE 388 MB — IGUAL
as 4 capacidades editoriais em todos os papeis
```

### 🔴 A regra do rollback

**Arquivo volta fácil; migração de banco, não.** Os lotes **2, 3, 4, 6 e 7** escreveram no banco,
e **não há rollback por plugin**. Desfazer qualquer um deles exige **restaurar o dump inteiro de
homolog** — e um dump restaurado apaga o trabalho de todos os lotes posteriores.

**Os 7 dumps existem, verificados** (`gzip -t`, primeira linha, rodapé, 92 `CREATE TABLE` × 92
tabelas, SHA-256 ao lado, arquivo em `444`), em `~/BAHIABA-backups/`:

```
dump-HOMOLOG-pre-lote2 .. pre-lote7    +  os tar de cada diretorio de plugin
```

---

## 3. As dependências que os lotes revelaram

**Nenhuma das três estava escrita em lugar nenhum.** As três são código nosso segurando
comportamento que parece vir do plugin.

### 3.1 `bahia-autor-archive.php` segura a página de autor — não é otimização

Medido **já sobre o Co-Authors Plus 4.1.1**, com o `pre_get_posts` do mu-plugin desligado:

| Autor | nosso UNION | SQL do CAP | contagem bate? | nosso | **CAP** |
|---|---|---|---|---|---|
| `mateus-soares` | 1.763 | 1.763 | ✅ | 2.055 ms | **38.865 ms** |
| `breno-cunha` | 991 | 991 | ✅ | 1.457 ms | **37.788 ms** |

**O major não consertou o desempenho.** Se alguém remover o mu-plugin achando que "o plugin novo
já resolve", a página de autor volta de ~1,8 s para ~38 s de consulta, **sem erro nenhum**.

⚠️ **Ponto frágil:** o mu-plugin desliga os filtros do CAP **pelo nome do método**
(`posts_where_filter`, `posts_join_filter`, `posts_groupby_filter`). `method_exists` devolvendo
`false` **não é erro — é `continue`**. Se uma atualização renomear qualquer um, o SQL lento volta
e o nosso UNION entra por cima. **Conferir os três nomes é a primeira coisa em toda atualização
do CAP.**

### 3.2 O *lazy loading* do site inteiro é do Smush

| Página | `<img>` | com `data-src` (Smush) | com `loading="lazy"` (nativo) |
|---|---|---|---|
| Home | 17 | **17** | **0** |
| `/economia/` | 18 | **17** | **0** |
| Matéria | 10 | **10** | **0** |

**Não há uma única imagem com o *lazy* nativo do WordPress.** Se o Smush sair ou for trocado, o
site passa a baixar todas as imagens de uma vez — 17 na home antes da primeira rolagem, num site
majoritariamente de celular.

**E a ironia:** o Smush está instalado pela **compressão**, que **não está ligada** (`lossy='0'`,
`webp_mod=false`, e só **5.844 dos 155.675** anexos têm dado de otimização). **Foi mantido por um
motivo e hoje é indispensável por outro.** Virou o item 14 do `PENDENCIAS-gestores.md`.

### 3.3 `bahia-subtitulo.php` lê a apresentação do Yoast

Engancha em `wpseo_metadesc`, `wpseo_opengraph_desc` e `wpseo_twitter_description` **lendo
`$presentation->model->object_type` e `->object_id`**. É o que põe o subtítulo da matéria na
`meta description` — em **99,6% do acervo** não há description escrita à mão.

**Se uma atualização do Yoast mudar a forma da apresentação, o subtítulo some das metatags sem
erro nenhum**, e o Yoast volta a servir o que servia antes. Na 28.4 sai; **conferir em toda
atualização**, amostrando uma matéria com `subtitulo` e sem `_yoast_wpseo_metadesc` próprio.

---

## 4. O que fica pendente

### 4.1 Os 4 inativos — decisão adiada de propósito

| Plugin | Versão | |
|---|---|---|
| Akismet | 5.3.1 | não roda |
| All-in-One WP Migration | 6.77 | não roda |
| NextScripts: Social Networks Auto-Poster | 4.4.6 | não roda |
| **WPS Hide Login** | 1.9.17.2 | não roda — **e deixou resíduo** |

Atualizar não muda comportamento; a escolha é **atualizar ou remover**.

> 🔗 **O WPS Hide Login tem um resíduo que precisa sair junto.** Foi ele que deixou a opção órfã
> `whl_page = 'acesso'`, que faz **`/acesso/` cair numa matéria por adivinhação do núcleo**.
> **Remover o plugin sem apagar a opção mantém o defeito sem o culpado à vista.**

Há ainda 3 inativos fora dessa lista: `CDN Enabler 2.0.8`, `Hello Dolly 1.7.2` e
`Push Notifications Bahia.ba 1.0` (nosso).

### 4.2 Os 3 do tagDiv — sem canal de atualização

`td-composer 5.4.5` · `td-cloud-library 3.9.5` · `td-social-counter 5.7`

**Nenhum oferece atualização.** Vieram com o tema e não têm por onde receber correção. Dois deles
aparecem no console em `added to the iframe incorrectly`, junto com o `global-styles` do núcleo.

### 4.3 ACF PRO — bloqueado por licença 🔴

```
advanced-custom-fields-pro   6.2.1.1  ->  6.8.9
pacote                       (VAZIO — exige licenca)
acf_pro_license              definida (176 chars)
acf_pro_license_status       (VAZIA)
```

**O servidor da ACF responde e sabe que existe a 6.8.9 — mas não entrega o pacote.**

### 🔴 E isto muda a economia da licença — não é "atualizar um plugin"

**Os 12 lotes foram todos validados sobre o ACF 6.2.1.1.** Quando o ACF subir, ele muda debaixo de
todos eles — é o plugin de dependência mais profunda do site (`subtitulo`, `imagem`, 5 grupos de
campos, e o modelo editorial inteiro).

> ### Subir o ACF não custa um lote. Custa **refazer o ciclo**.

| | |
|---|---|
| O que se pensa que custa | uma atualização de plugin |
| O que custa de fato | **revalidar os 12** — mídia, autoria, SEO, capacidades, editor, console, logs |
| Quanto isso levou desta vez | **dois dias**, 7 dumps, 7 rollouts |

**E a conta piora com o tempo.** Cada lote novo que entrar antes do ACF é mais um item na lista de
revalidação. **Quanto antes a licença aparecer, menor o retrabalho** — e essa é a variável que
deveria pesar na decisão de renovar, não o valor da anuidade isolado.

> **O certo teria sido o ACF primeiro**, para que os outros 12 fossem validados já sobre a versão
> final. Não deu, e a ordem inversa cobra o preço uma vez. **Cobrar duas vezes é opcional.**

### 4.4 AdRotate Professional — pago, sem licença, e agora com DOIS prazos

`adrotate-pro 5.13.1`, sem licença no `adrotate_config`, sem canal.

| Frente | O que é |
|---|---|
| **Editor isolado** | 2 blocos em `apiVersion 1`; o WordPress avisa que **todos os editores passarão a funcionar isolados** |
| **PHP 8.4** | **9 das 39** depreciações de tipo implicitamente nullable que restam |

**É o único ponto do portal fechado em duas frentes ao mesmo tempo.** Item 13 do
`PENDENCIAS-gestores.md`, com as três saídas (recuperar licença / trocar de plugin / aceitar
publicidade fora do editor).

### 4.5 Tarefa A — PHP 8.4 destravou, e mudou de natureza

O lote 3 derrubou as depreciações do Offload de **248 para 1**, e o total de **286 para 39**.
A tarefa saiu de *"depende de release de terceiro"* e virou:

- **2 são nossas** — `bahia_refactor` e `bahia_social`, `Mobile-Detect` vendorizada
- **28 chegam sozinhas** — plugins com canal aberto
- **9 são do AdRotate** — decisão comercial

### 4.6 Homolog fora dos buscadores — passo 1 feito, passo 2 pendente

Confirmado por busca em 02/09: **home, `/entretenimento/`, `/justica/`, `/politica/` e
`/dende-e-poder/` de `hml.bahia.ba` JÁ ESTÃO INDEXADAS.**

**Passo 1, aplicado:** `mu-plugins/bahia-homolog-noindex.php` — `noindex, nofollow` em toda página
mais `X-Robots-Tag`, **sem tocar no banco**.

**Passo 2, pendente e deliberado:** o `Disallow: /` **não** foi aplicado. Com páginas já no
índice, `Disallow` impede o Google de buscar a página — e, sem buscar, ele **nunca vê o
`noindex`**, então o que está indexado **fica**. O `Disallow` entra **depois** que as páginas
saírem, e exige mexer no nginx: `location = /robots.txt` não tem `try_files`, a URL nunca chega
ao PHP.

---

## 5. 🔴 O que produção vai receber que homolog tem — e o que NÃO vai agir lá

**Os dois mu-plugins novos viajam no merge `develop → main`. Os dois são inertes em produção**, e
isso é por construção, não por sorte.

| Arquivo | O que faz | Em produção |
|---|---|---|
| `bahia-yoast-indexacao-fundo.php` | desagenda `wpseo_indexable_index_batch` | **inerte** — `bahia_ambiente()` na 1ª linha do corpo |
| `bahia-homolog-noindex.php` | `noindex, nofollow` em toda página | **inerte** — mesma guarda |

### 🔴 As QUATRO verificações obrigatórias, no pod de produção, depois do deploy

**Não presumir do código. Rodar no pod, sobre o HTML e os cabeçalhos que produção está servindo.**
Se **qualquer uma** falhar: **rollback imediato.** Tirar o `bahia.ba` do Google é dano que leva
**semanas** para desfazer, e não aparece em log nenhum enquanto acontece.

| # | Verificação | Esperado | Como |
|---|---|---|---|
| **1** | a opção nativa | `get_option('blog_public')` **= 1** | `php -r` no pod |
| **2** | o HTML servido, em **três** telas | **nenhum** `noindex` — home, um archive e uma matéria | `curl … \| grep 'name="robots"'` |
| **3** | os cabeçalhos, **incluindo sitemap e feed** | **nenhum** `x-robots-tag` | `curl -I` em `/`, `/sitemap_index.xml` e `/feed/` |
| **4** | o cron do Yoast **continua agendado** | `wp_next_scheduled('wpseo_indexable_index_batch')` **≠ false** | `php -r` no pod |

```bash
POD=$(kubectl -n bahia-wordpress get pods -l app=wordpress -o jsonpath='{.items[0].metadata.name}')

# 1 e 4 — estado interno
kubectl -n bahia-wordpress exec $POD -c wordpress -- php -r '
define("WP_USE_THEMES",false); require_once "/var/www/html/wp-load.php";
echo "ambiente   : ".bahia_ambiente()."\n";                       # producao
echo "blog_public: ".var_export(get_option("blog_public"),true)."\n";   # 1
echo "cron Yoast : ".var_export(wp_next_scheduled("wpseo_indexable_index_batch"),true)."\n";'  # NAO false

# 2 — o HTML servido, nas tres telas
for u in "/" "/economia/" "/<uma-materia>/"; do
  curl -s "https://bahia.ba$u?cb=$RANDOM" | grep -o "<meta name=.robots. content=.[^'\"]*"
done            # esperado: index, follow ... em todas. Se aparecer noindex -> ROLLBACK

# 3 — os cabecalhos, inclusive os que nao sao HTML
for u in "/" "/sitemap_index.xml" "/feed/"; do
  curl -sI "https://bahia.ba$u" | grep -i x-robots-tag
done            # esperado: NENHUMA linha
```

> **A quarta é a que se esquece.** As três primeiras protegem contra o `noindex` vazar; a quarta
> protege contra o **outro** mu-plugin vazar. Em produção o `wpseo_indexable_index_batch` **deve
> continuar rodando** — é onde ele tem trabalho útil (e hoje já roda, a cada 15 minutos, na 27.7).
> Se ele aparecer desagendado, a guarda de ambiente do
> `bahia-yoast-indexacao-fundo.php` falhou.

**Nenhum dos dois escreve no banco**, de propósito — o `noindex` é filtro em
`pre_option_blog_public`, não `UPDATE`. Se fosse escrita, **um dump de homolog restaurado em
produção levaria o `blog_public = 0` junto** e tiraria o site do ar nos buscadores sem erro
nenhum.

**Reverter qualquer um dos dois é apagar o arquivo.** Não há estado guardado.

### E o que produção NÃO precisa

**O portão do Yoast 28.4 em produção está respondido e liberado** (seção 10 do
`MIGRACAO-homolog-para-prod.md`):

| | homolog | **produção** |
|---|---|---|
| `innodb_buffer_pool_size` | **128 MB** | **11.264 MB** |
| posts sem indexável `version=2` | ~138.000 | **ZERO** |
| o anti-join do Yoast, `LIMIT 15` | **13 a 28 min** | **~1,6 s** |
| `wpseo_indexable_index_batch` | desligado por mu-plugin | **já roda hoje, na 27.7, a cada 15 min** |

**A versão do construtor de indexável é idêntica na 27.7 e na 28.4** (`'post' => 2`) — nenhum
indexável existente vira desatualizado, **não há reindexação a disparar**. O mu-plugin de
indexação **não é necessário em produção**.

---

## 6. O que ficou medido, para comparar depois

| | valor |
|---|---|
| Console do editor | **7 advertências**, 0 erros — 2 do AdRotate, `wp.compose.pure`, `wp.compose.withState`, 3 de iframe |
| Blocos registrados | **126**, dos quais **2** em `apiVersion 1` (os dois do AdRotate) |
| Abertura do editor | **250 recursos · 178 arquivos de JS** — dos quais 32 do Yoast e 8 do CAP |
| Envio de mídia pelo navegador | **201**, 14 → 13 arquivos, offload verificado, 0 arquivos locais restantes |
| Logs, por janela de ~220 requisições | **0 fatais · 0 depreciações · 0 notices** |
| Avisos por janela | de **28** (lotes 3 e 4) para **1–3** — os de `user_nicename` do CAP foram a zero |
| Busca | **10 de 10** termos, 501 e 483 resultados |

**Duas advertências legadas eliminadas no caminho:** a do FooGallery (lote 2) e a de
`wp.editPost.PluginDocumentSettingPanel` (lote 5).
