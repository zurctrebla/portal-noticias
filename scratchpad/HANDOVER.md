# Handover técnico — bahia.ba / tema Newspaper (Magazine PRO)

**Escrito em:** 11/08/2026, ao fim da rodada 5
**Para:** quem for mexer neste site depois, incluindo eu mesmo daqui a três meses

Isto reúne o que foi aprendido nas rodadas 2 a 5 e **não está escrito em nenhum outro lugar** —
nem no código, nem no histórico do git. Documentos irmãos:

- `AUDITORIA-templates.md` — o que renderiza cada página
- `MIGRACAO-homolog-para-prod.md` — o que precisa viajar para produção
- `PENDENCIAS-gestores.md` — o que depende de decisão de negócio
- `REVERSAO-adrotate-homolog.md` — anúncios de teste em homologação

---

## 1. A regra que evita perder uma rodada de trabalho

`td-composer` registra `template_include` com **prioridade 99** e desvia vários contextos para
PHP dentro de `plugins/`. Consequência: **um `tdb_templates` publicado e configurado pode ser
código morto**, e `plugins/` não é versionado — o que for editado ali some no próximo deploy.

**Toda alteração em single, archive, home ou página vai por hook em mu-plugin.** Consulte
`AUDITORIA-templates.md` antes de tocar em qualquer coisa. Já custou trabalho perdido uma vez
(a remoção do Pinterest foi feita no template errado e "voltou").

---

## 2. Os 28.379 posts sem termo de autor

**O dado:** 11,7% do acervo publicado (28.379 de 242.862 posts) **não tem o termo da taxonomia
`author`** correspondente ao seu autor primário. É resíduo da importação — o Co-Authors Plus
grava esse termo quando o post é salvo pela interface, e a massa importada nunca passou por lá.

Concentrado em alguns autores: `agencia-brasil` (3.690), `levi-vasconcelos` (3.463),
`agencia-estado` (2.499), `mateus-soares` (1.762), `rodrigo-aguiar` (1.753)…

**Por que não quebra nada hoje:** `mu-plugins/bahia-autor-archive.php` monta a listagem como
UNION de dois ramos — (A) autor primário em posts **sem nenhum** termo `author`, e (B) posts
que carregam o termo da pessoa. O ramo A cobre exatamente essa lacuna. É também o fallback do
próprio CAP (`get_coauthors()` usa `post_author` quando não há termo).

**Onde vai reaparecer:** em qualquer funcionalidade nova que consulte a taxonomia `author`
diretamente — página "todos os autores", contagem de matérias por repórter, filtro por autor,
feed por autor, relatório de produtividade. Quem escrever isso pensando que a taxonomia é
completa vai subcontar em ~12%, silenciosamente.

**As duas saídas, quando chegar a hora:**

1. Replicar a lógica do UNION (nunca só a taxonomia).
2. Fazer o *backfill*: gravar o termo faltante para cada post. São ~28 mil escritas em
   `wp_term_relationships` + recontagem de termos. Resolve de vez, mas é alteração de dados
   que precisa de janela, backup e — atenção — **teria que ser feita também em produção**.

Como conferir o tamanho da lacuna hoje:

```sql
SELECT COUNT(*) FROM wp_posts p
  JOIN wp_users u ON u.ID = p.post_author
 WHERE p.post_status='publish'
   AND NOT EXISTS (
     SELECT 1 FROM wp_term_relationships tr
       JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id=tr.term_taxonomy_id AND tt.taxonomy='author'
       JOIN wp_terms t ON t.term_id=tt.term_id AND t.slug = CONCAT('cap-', u.user_nicename)
      WHERE tr.object_id = p.ID);
```

> Cuidado: essa consulta levou **126 s** em homolog. Não rodar em horário de pico.

### 2.1 E por que o archive de autor não usa o SQL do Co-Authors Plus

O CAP resolve "autor primário OU coautor" com `LEFT JOIN` em `wp_term_relationships` (253 mil
linhas) + `GROUP BY` + `HAVING` sobre `wp_posts` inteiro. O `OR` impede o MySQL de usar índice
nos dois lados e a consulta vira varredura: **31 a 39 s por autor**, medido.

Alternativas testadas: `EXISTS` também deu ~35 s (mesmo motivo); só taxonomia deu 0,37 s mas
**esconde os 28 mil**. O UNION de dois ramos indexados deu **~0,6 s** com contagem idêntica à
do CAP.

**Uma armadilha que já foi paga:** a primeira versão limpava os query vars de autor para evitar
o `AND post_author = X` que o WordPress acrescenta. Funcionou para a listagem, mas levou junto
o `$authordata` global — e o título da página passou a ser o autor do **primeiro post da
lista** (`/author/lula-bonfim/` saiu como "André Souza"). A versão final mantém os query vars e
remove a cláusula por regex no `posts_where`. **Não "simplifique" isso de volta.**

---

## 3. O buffer de saída (`bahia-html-saida.php`)

**Por que existe:** três correções precisam acontecer no HTML já montado, porque a origem está
dentro de `plugins/` e não passa por filtro nenhum do WordPress.

**O que reescreve:**

1. **Byline de coautoria** — `td_module_single_base::get_author()` monta **um** `<a>` só,
   envolvendo os dois nomes; clicar em "Neison Cerqueira" levava ao perfil do outro autor. A
   reescrita troca por um link por autor. **Ancorada** em `.td-post-author-name`, só em
   `is_singular()`, só com 2+ coautores, uma substituição.
2. **Selo EXCLUSIVO** (`bahia-exclusivo.php`, via filtro `bahia_hs_html`) — **ancorado** em
   `<a class="td-image-wrap">` e casado contra um registro de URLs.
3. **`aria-label` em inglês** — `aria-label="Search"` está cravado à mão em
   `td-composer/legacy/Newspaper/header.php`, sem passar por `__td()`.

**A ressalva, registrada de propósito:** o item 3 é um `str_replace` **na página inteira**.
Não é busca por palavra solta — são strings de atributo exatas (`aria-label="Search"`), então
a colisão só aconteceria num artigo que citasse literalmente esse markup (um tutorial de
acessibilidade, por exemplo). Risco baixo, mas é o único dos três que não é dirigido a um
trecho específico. Se um dia der problema estranho de conteúdo alterado, **comece por aqui**.

**Custo medido** (rodada 4): 0,6 a 1,24 ms por requisição, contra 2,2 a 3,9 s de geração de
página — cerca de **0,03% de um MISS**. Não é gargalo.

**Regra:** só pode existir **um** buffer de saída no site. Quem precisar de outra reescrita
pendura no filtro `bahia_hs_html`, não abre um `ob_start()` novo.

**O admin-ajax fica de fora** (o buffer não cobre `wp_doing_ajax()`). Os cards que chegam por
"Ver mais" precisam de tratamento próprio: `bahia-exclusivo.php` abre um buffer nas ações
`td_ajax_block`, `td_ajax_loop` e `bahia_scroll_infinito`, **decodifica o JSON**, injeta e
recodifica. Decodificar em vez de aplicar regex no JSON escapado é o que torna isso confiável.

---

## 4. `ajax_pagination="next_prev"`: o padrão a evitar

**O que aconteceu:** na rodada 4, o bloco de notícias do template 404 recebeu
`installed_post_types` com as 18 editorias (antes listava só `post`, e por isso mostrava um
post de 2019). **A página saltou de 2,2 s para 36 s.**

**A causa não é a query** — medida isoladamente, custa 0,36 s. É o `ajax_pagination="next_prev"`
do bloco, que faz o tagDiv **pré-renderizar a página seguinte dentro do mesmo request**. Com
18 CPTs, isso ficou caro. Trocar para `ajax_pagination=""` devolveu a página a ~2 s.

**Por que era grave:** **404 não entra no fastcgi_cache.** O custo era pago em *toda*
requisição — qualquer varredura de bot em URLs inexistentes derrubaria o site.

**Regra para blocos novos:** ao adicionar `installed_post_types` a um bloco do tagDiv, conferir
também o `ajax_pagination` **e medir o tempo do request depois**. `load_more` não tem esse
comportamento — `/ultimas-noticias/`, com os mesmos 18 CPTs e 12 cards, responde em ~3 s.

E, de modo geral: **meça páginas que não entram em cache** (404, busca com termo raro,
`/author/`). São as que pagam o custo integral sempre.

---

## 5. Sitemap: 504 em homolog é *sizing*, não código

`/sitemap_index.xml` responde **504 em homologação** e **200 em produção**. Não é defeito de
código.

**Causa:** o RDS de homolog tem `innodb_buffer_pool_size` de **256 MB** contra uma `wp_posts`
de **1,1 GB**. A consulta do Yoast varre mais do que cabe em memória e vai a disco.

**O que já foi testado e NÃO resolve:** reduzir `entries_per_page` do Yoast.

**Baseline de produção**, medido em 11/08/2026 — guardar para comparar depois da migração:

| URL | Status | Tempo |
|-----|--------|-------|
| `/sitemap_index.xml` | 200 | **0,62 s** a quente (9,95 s no primeiro acesso frio) |
| `/post-sitemap.xml` | 200 | 1,62 s |
| `/bahia-sitemap.xml` | 200 | 5,17 s |
| `/bahia-sitemap2.xml` | 200 | 3,24 s |

> O briefing da rodada 5 citava 1,95 s. A medição de 11/08 deu 0,62 s a quente. Trate ~10 s
> como custo de cache frio, não como degradação.

**Decisão registrada:** aceitar o 504 em homolog. Se produção degradar após a migração, o
primeiro remédio é o índice de cobertura — SQL pronto em `MIGRACAO-homolog-para-prod.md`,
seção 6.3.

---

## 6. Outras armadilhas que custaram tempo

**O `.tdi_NN` é volátil.** O tagDiv **renumera** os ids `tdi_NN` a cada edição de template.
Qualquer CSS ou JS que dependa deles quebra silenciosamente no próximo salvamento. Foi por isso
que a correção das datas (rodada 5) usa **repetição de classe** para ganhar especificidade
(`.entry-date.entry-date.entry-date.entry-date.entry-date`) em vez de mirar os ids: o tagDiv
emite as regras com `!important` em seletores de especificidade (0,4,1), e um `!important`
simples **perde**.

**`td_011` é reescrito inteiro a cada save do painel.** Ajuste feito pela interface volta
atrás sozinho. Por isso `bahia-td-opcoes.php` fixa os valores em runtime, via
`add_filter('option_td_011', ...)`, **registrado no corpo do mu-plugin** — em
`plugins_loaded` ou `after_setup_theme` já é tarde, porque `td_options::read_from_db()` guarda
o resultado num `static`.

Consequência para a migração: no banco, `td_translation_map_user` está **ausente** e
`pinterest` está **`true`** — e mesmo assim o site mostra as traduções e esconde o Pinterest,
porque o código injeta em runtime. **Não replique isso no banco de produção.**

**`is_admin()` é verdadeiro em `admin-ajax`.** Guardas do tipo `if (is_admin()) return;` desligam
o código nas requisições de "Ver mais" sem que ninguém perceba.

**`__td()` não usa gettext.** Traduzir string do tagDiv por arquivo `.po` não funciona; tem que
ser pelo `td_translation_map_user`, dentro do filtro de `td_011`.

**Datas em português dependem de dois lugares:** o formato (`tds_data_time_format` =
`l, j \d\e F \d\e Y`, gravado no banco) e a pasta `/languages` **presente na imagem** — ela já
esteve no `.gitignore` e as datas saíam em inglês.

**As URLs de categoria e tag das editorias são 404** — em homolog **e em produção**. As 18
editorias registram `{slug}_cat` e `{slug}_tag` todas com o mesmo slug de reescrita
(`categoria` / `tag`), e só a última registrada vence. Detalhe e comprovação em
`AUDITORIA-templates.md`, seção 3.1. **Pré-existente**, não corrigido.

---

## 7. Backups criados nas rodadas 2 a 5

Todos em `wp_options` do banco de **homolog**, todos com `autoload = off`. Guardam o
`post_content` anterior do objeto citado no nome.

| Chave | Conteúdo | Bytes |
|-------|----------|-------|
| `bahia_rodada2_backup` | Retrato amplo antes da rodada 2 | 135.084 |
| `bahia_predemo_backup_20260805-092906` | Antes da troca para o demo Magazine PRO | 122.951 |
| `bahia_predemo_backup_20260805-092804` | idem, primeira parte | 74.785 |
| `bahia_predemo_backup_latest` | Ponteiro para a chave acima | 36 |
| `bahia_header_547414_backup` | Header, antes da rodada 3 | 36.948 |
| `bahia_header547414_backup_20260810-195048` | Header, rodada 3 | 34.608 |
| `bahia_header547414_mobile_backup_20260810-200627` | Header, parte mobile | 2.611 |
| `bahia_home_547432_backup_20260810-191418` | Home, último da rodada 3 | 32.530 |
| `bahia_home_547432_backup_20260810-191206` | Home, rodada 3 | 32.531 |
| `bahia_home_547432_backup_20260810-191025` | Home, rodada 3 | 33.478 |
| `bahia_home547432_backup_20260810-141305` | Home, rodada 3 (início) | 33.456 |
| `bahia_home_content_backup_r3` | Home, conteúdo rodada 3 | 31.401 |
| `bahia_footer_547416_backup_20260810-190732` | Rodapé, rodada 3 | 6.178 |
| `bahia_footer_547416_backup` | Rodapé, rodada 3 | 5.986 |
| `bahia_wpseo_titles_backup_r3` | `wpseo_titles` antes da rodada 3 | 53.865 |
| `bahia_wpseo_titles_backup_20260730-134337` | `wpseo_titles` antes da rodada 2 | 52.529 |
| `bahia_quemsomos_backup_20260803-143248` | Quem Somos | 12.043 |
| `bahia_full_magpro_backup_20260805-192103` | Conjunto Magazine PRO | 24.408 |
| `bahia_prereinstall_backup_20260805-185927` | Antes de reinstalar o demo | 24.614 |
| **`bahia_404_backup_20260811_131028`** | **404, imediatamente antes do estado atual** | **4.982** |
| `bahia_404_backup_20260811_124605` | 404, rodada 4 (etapa 2) | 5.131 |
| `bahia_404_backup_20260811_124335` | 404, **original do demo** (com link para `demo.tagdiv.com`) | 4.925 |
| `bahia_404_backup_latest` | Ponteiro para o backup 404 mais recente | 32 |

Outros de rodadas anteriores, menores: `bahia_blocktitles_backup_*` (7 chaves),
`bahia_footer_*` (5), `bahia_header_tpl_backup_*` (2), `bahia_cat_tpl_backup_*`,
`bahia_search_tpl_backup_*`, `bahia_searchform_backup_*`, `bahia_hdrsearchmsg_backup_*`,
`bahia_404desc_backup_*`, `bahia_footer_menu_backup_*`, `bahia_xyz_credit_link_backup_*`.

**Como restaurar qualquer um:**

```php
$anterior = get_option('bahia_404_backup_20260811_124335');
wp_update_post(array('ID' => 547430, 'post_content' => $anterior));
```

**Nenhum destes deve ser migrado para produção.** São histórico de homologação. Vale uma
limpeza depois que a migração estiver consolidada.

---

## 8. Como trabalhar neste ambiente

**Não existe WP-CLI no pod.** O padrão usado nas quatro rodadas é: escrever um script PHP,
`kubectl cp` para `/tmp` no container `wordpress`, executar com `php`.

```bash
POD=$(kubectl get pod -n bahia-wordpress -l app=wordpress -o jsonpath='{.items[0].metadata.name}')
kubectl cp script.php bahia-wordpress/$POD:/tmp/script.php -c wordpress
kubectl exec -n bahia-wordpress $POD -c wordpress -- php /tmp/script.php
```

Todo script que escreve no banco deve: (1) abortar se `get_option('siteurl')` não for
`https://hml.bahia.ba`; (2) ter modo `--dry-run` como padrão; (3) gravar backup em
`wp_options` antes de escrever, imprimindo a chave.

> O contexto do `kubectl` **já trocou sozinho de homolog para produção** no meio de uma sessão.
> A checagem de `siteurl` dentro do script é o que impede o acidente. Não é paranoia.

**Purgar o cache antes de validar** (o nginx é um sidecar no mesmo pod):

```bash
kubectl exec -n bahia-wordpress $POD -c nginx -- sh -lc 'rm -rf /tmp/nginx-cache/*'
```

**Validar em 390px de verdade:** Chrome headless local com `--window-size=390,<altura>` renderiza
na largura real — `resize_window` e `Emulation.setDeviceMetricsOverride` não são equivalentes.
Uma altura grande (2000–3400) serve de "página inteira".

**Atenção:** o macOS **não tem o comando `timeout`**. Um wrapper que o use falha em silêncio,
sem gerar o PNG. É preciso watchdog em shell:

```zsh
"$CHROME" --headless=new --disable-gpu --hide-scrollbars --no-sandbox \
  --user-data-dir="$(mktemp -d)" --window-size="390,2200" \
  --virtual-time-budget=15000 --run-all-compositor-stages-before-draw \
  --screenshot="saida.png" "$URL" >/dev/null 2>&1 &
PID=$!; N=0
while kill -0 $PID 2>/dev/null; do sleep 1; N=$((N+1)); [[ $N -gt 40 ]] && kill -9 $PID; done
```

Aquecer a URL com `curl` antes da captura reduz muito a chance de estourar o watchdog.

---

## 9. Estado do repositório

> **Desatualizado desde a rodada 6.** Os commits abaixo foram enviados para `origin/staging`
> em 11/08/2026 — ver a seção 10.1. O estado corrente está no `git log`.

Branch `staging`, **quatro commits locais, nenhum enviado**:

```
<rodada 5>  feat(homolog): rodada 5 — datas sem capitalize e documentacao de fechamento
a9f6bde0    feat(homolog): rodada 4 de ajustes no Newspaper/Magazine PRO
e66f2d01    feat(homolog): rodada 3 de ajustes no Newspaper/Magazine PRO
0f6711d3    feat(homolog): rodada 2 de ajustes no Newspaper/Magazine PRO
```

Lembrete de infraestrutura: `push` em `staging` publica em homolog (EKS); `main` publica em
produção. **Commit em `main` que toque `plugins/` quebra o deploy** (`git reset --hard` como
usuário `admin`, "Permission denied", exit 128) — commitar apenas `themes/` e `mu-plugins/`,
que são graváveis.

---

## 10. Rodada 6 — o que ficou sabido

### 10.1 O desacoplamento staging/prod está PROVADO, não só desenhado

O primeiro `push` em `staging` depois de os pipelines terem sido separados foi feito em
11/08/2026 e medido dos dois lados. Produção não se mexeu em nenhum eixo: imagem
`prod-3622e1b28f…`, revisão 26, geração 286 e os horários de início dos três pods
**idênticos** antes e depois; nenhum workflow de produção disparou.

O que sustenta isso, para quem for conferir de novo:

- `deploy-homolog.yml` dispara só em `staging` e publica `<sha>` + `homolog-latest`;
- o workflow de produção **existe apenas na `main`** — um push em `staging` não tem como
  alcançá-lo;
- produção roda em `prod-<sha>` **fixo**, não em tag flutuante.

### 10.2 O pod de homolog é descartável, e isso tem de ser checado ANTES de cada push

`wp-content` é `emptyDir` em homolog: o `push` reconstrói a imagem e **tudo que estiver no
pod só por `kubectl cp` desaparece**. Antes de empurrar, comparar:

```bash
kubectl exec -n bahia-wordpress $POD -c wordpress -- \
  sh -c 'cd /var/www/html/wp-content/mu-plugins && md5sum *.php' | sort -k2 > /tmp/pod.txt
(cd mu-plugins && md5sum *.php) | sort -k2 > /tmp/local.txt
diff /tmp/local.txt /tmp/pod.txt
```

Na rodada 6 esse diff revelou que havia **três commits a mais** do que o briefing supunha
(10/08: `bahia-header-ad`, `bahia-limites-texto`, cores de tag) — eram exatamente o que
estava vivo no pod e fora da imagem. Enviar só os "quatro da rodada" teria apagado esse
trabalho no rebuild.

Não há `git` nem `wp-cli` dentro do container. Há `php`, e é por ele que se faz tudo
(seção 8).

### 10.3 Badge de editoria ausente nos cards de Salvador — RESOLVIDO (rodada 7)

Os três cards do bloco de Salvador na home não exibiam o badge colorido da editoria,
enquanto os demais blocos exibiam.

**A hipótese registrada aqui — empilhamento ou `overflow` — estava errada.** Fica o
registro porque o erro é instrutivo: procurava-se um defeito de RENDERIZAÇÃO quando o
problema era de CASCATA, e nenhuma inspeção de `z-index` ia encontrar isso.

**A causa real.** O demo escreve, só para aquele bloco:

```css
.tdi_NN .td_module_flex_1 .td-module-thumb a:after{content:'';...width:100%;height:100%}
```

Naquele markup **o `<a>` É a `.td-image-wrap`**. Ou seja: `.td-module-thumb a:after` e
`.td-cpt-salvador .td-image-wrap::after` são **o mesmo pseudo-elemento**. O do demo tem
especificidade (0,3,2); o do badge, na forma simples, (0,2,1). O demo vencia e o badge
nunca chegava a existir — um elemento tem um `::after` só, e quem ganha a cascata leva.

Não havia como ter os dois: ou o véu do demo, ou o badge.

**A correção** (`bahia-editoria-tags.php`): a classe vai repetida 3x
(`.td-cpt-x.td-cpt-x.td-cpt-x .td-image-wrap::after`), o que leva a regra a (0,4,1) e
vence, sem citar `.tdi_NN`. A leitura sobre a foto não se perde porque
`bahia-hover-editoria.php` desenha o véu em `:before`, que estava livre.

De passagem: o véu da rodada 6 também usava `.td-module-thumb a:after` e era um segundo
ocupante do mesmo pseudo-elemento. Passou a usar só `:before`.

> **A lição, que vale além deste caso:** antes de procurar defeito de empilhamento,
> verifique se dois seletores diferentes não estão apontando para o MESMO
> pseudo-elemento. Em markup do tagDiv, `.td-module-thumb a` e `.td-image-wrap`
> costumam ser o mesmo nó.

Conferido depois da correção: **40 de 40 cards da home com badge, em 10 blocos.**

**Contraste dos badges** — RESOLVIDO na rodada 8. Quatro reprovavam AA 4,5:1 para texto
pequeno: Salvador (branco sobre `#4db2ec`) **2,36:1**, Esporte **2,56:1**, Dendê e Poder
**3,09:1**, Justiça **3,56:1**.

O fundo passou a ser escurecido até 4,5:1 preservando o matiz (multiplicação dos canais em
passos de 5%, por `bahia_hover_ed_cor_legivel()`): `#357ca5` (4,59:1), `#008309` (4,94:1),
`#b95a08` (4,64:1) e `#d83127` (4,79:1). O texto continua branco.

**O ajuste NÃO está em `bahia_editoria_tags_colors()`**, e isso é deliberado: aquele mapa é a
fonte única de cor de editoria do site, e escurecê-lo levaria junto a linha das seções, as
setas e o overlay de hover — que não têm texto branco por cima e não tinham problema. O
escurecimento vive na montagem do CSS do badge (`bahia_editoria_tags_bg_legivel()`), e só ali.

Municípios (`#e49600` + `#222222`, 6,56:1) e Mundo (`#ededed` + `#13182b`, 15,03:1) ficam de
fora por terem texto escuro — escurecer o fundo delas pioraria a razão.

### 10.4 Quem manda no tamanho da logo do rodapé é o CSS, não o atributo `width`

Ao trocar a logo do rodapé (rodada 6), o `width` da tag não teve efeito: o contêiner do
bloco tem ~260px e o `max-width:100%` do tema vence sempre. O resultado só apareceu ao
**medir a captura** — a marca havia crescido 4% porque a imagem antiga carregava 12px de
margem dentro do arquivo e a nova sangra até a borda.

A lição vale para qualquer troca de imagem em bloco do tagDiv: **medir o render, não
confiar no atributo**. A correção foi um `max-width` em porcentagem (96,76% = 717/741), que
reproduz a proporção antiga em qualquer largura de contêiner.

### 10.5 A home em 390px não chega ao rodapé numa captura

O scroll infinito continua carregando enquanto o `--virtual-time-budget` corre, e o rodapé
é empurrado para além de 15.000px. Para validar cabeçalho ou rodapé em mobile, capturar uma
**página curta** (`/quem-somos/`), que compartilha os dois e fecha em ~3.700px.

---

## 11. Rodada 8 — publicidade

### 11.1 O bug de fuso do AdRotate: 3 horas de anúncio invisível, sem erro

**Sintoma:** cadastra-se um anúncio com data de início "agora", ele aparece como `active` no
painel, o agendamento parece correto — e ele simplesmente não é exibido pelas 3 horas
seguintes. Nenhum erro, nenhum aviso, nada no log.

**Causa:** o plugin grava e lê o mesmo instante em duas escalas de tempo diferentes.

- Na **gravação**, o AdRotate monta o `starttime` com `mktime()`. O `mktime()` usa o timezone
  default do PHP, e o WordPress força esse default para **UTC** (`date_default_timezone_set('UTC')`
  em `wp-settings.php`). O que o gestor digitou como 14h46 local é gravado como se fosse
  14h46 **UTC**.
- Na **exibição**, o filtro de elegibilidade compara com `current_time('timestamp')`, que
  devolve a hora **local** do site (America/Bahia, UTC−3).

O resultado é um deslocamento fixo de 3 horas, sempre na direção que atrasa a estreia.

**Comprovação medida em homolog (11/08/2026).** O mesmo anúncio foi salvo duas vezes, com
poucos minutos de diferença, e gerou dois agendamentos:

| schedule | ad | `starttime` | `FROM_UNIXTIME` (UTC) | hora local pretendida |
|----------|-----|-------------|------------------------|-----------------------|
| 2311 | 1728 | 1786459560 | 2026-08-11 14:46 | 14:46 — funcionou |
| 2309 | 1728 | 1786470600 | 2026-08-11 17:50 | 14:50 — **inerte por 3h** |

`1786470600 − 1786459560 = 11.040s = 3h04min` — as 3 horas do fuso mais os 4 minutos entre
os dois salvamentos. É a assinatura do defeito, não coincidência.

### 11.2 Por que NÃO foi corrigido

O patch teria de mudar `plugins/adrotate/`. Duas razões independentes impedem:

1. **`plugins/` não é versionado** e some no próximo deploy — a correção duraria até o
   próximo build.
2. **O deploy de produção quebra** se um commit na `main` tocar `plugins/`: o
   `deploy-prod.yml` faz `git reset --hard` como usuário `admin`, sem permissão de escrita
   ali, e sai com *Permission denied* (exit 128) **antes** do build. Ver a memória
   `deploy-prod-git-reset-plugins-perm`.

**O caminho seguro, se um dia for corrigir:** um mu-plugin que intercepte o `INSERT`/`UPDATE`
de `wp_adrotate_schedule` e converta `starttime`/`stoptime` de UTC para a escala local antes
de gravar — isto é, somar o offset que o `mktime()` perdeu. Não se mexe no plugin; corrige-se
o dado na entrada. **Não implementado nesta rodada**, por estar fora do escopo e por exigir
decisão sobre o que fazer com os agendamentos já gravados errado (que estão todos 3h
adiantados e cuja correção em massa mudaria janelas comerciais já contratadas).

### 11.3 Agendamentos 2309 e 2310 — apagados

Eram duplicatas inertes: os anúncios 1728 e 1729 tinham **dois** agendamentos cada, e só o
segundo par (2311/2312) estava em escala local e vigente. Os 2309/2310 nunca exibiriam nada
até as 20:50 UTC e só serviam para confundir a leitura do inventário.

Aplicado em **homolog**:

```sql
DELETE FROM wp_adrotate_linkmeta WHERE id IN (8248,8253);
DELETE FROM wp_adrotate_schedule WHERE id IN (2309,2310);
```

**SQL de reversão** (retrato exato tirado antes de apagar):

```sql
INSERT INTO wp_adrotate_schedule
  (id, name, starttime, stoptime, maxclicks, maximpressions, spread, spread_all,
   daystarttime, daystoptime, day_mon, day_tue, day_wed, day_thu, day_fri, day_sat,
   day_sun, autodelete)
VALUES
  (2309, 'Schedule for ad 1728', 1786470600, 1787702340, 0, 0, 'N', 'N',
   '0000', '0000', 'Y','Y','Y','Y','Y','Y','Y', 'N'),
  (2310, 'Schedule for ad 1729', 1786470600, 1787702340, 0, 0, 'N', 'N',
   '0000', '0000', 'Y','Y','Y','Y','Y','Y','Y', 'N');

INSERT INTO wp_adrotate_linkmeta (id, ad, `group`, user, schedule) VALUES
  (8248, 1728, 0, 0, 2309),
  (8253, 1729, 0, 0, 2310);
```

Os anúncios 1728 e 1729 continuam no ar pelos agendamentos 2311 e 2312, que não foram
tocados.

### 11.4 O inventário publicitário estava sendo entregue no contexto errado

Até esta rodada o Newspaper renderizava **um único slot**, com o grupo 3 cravado em
`bahia-header-ad.php`, em **todas** as páginas. O grupo 3 chama-se "Home - Leader Board 2":
inventário de home era servido em internas e em municípios.

O tema legado nunca fez isso. `themes/bahia_refactor/header.php:200-207` escolhe o grupo
pelo contexto, e é essa lógica que o `bahia-publicidade.php` reproduz. Detalhes e a tabela
dos 7 grupos com inventário ativo estão em `PUBLICIDADE-slots.md`.
