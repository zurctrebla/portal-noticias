# Handover técnico — bahia.ba / tema Newspaper (Magazine PRO)

**Escrito em:** 11/08/2026, ao fim da rodada 5
**Para:** quem for mexer neste site depois, incluindo eu mesmo daqui a três meses

> **Renumeração de IDs — 16/08/2026.** Os registros nascidos em homolog (templates, páginas,
> anexos e itens de menu) foram movidos para a faixa **9.000.001+** pela fórmula
> `novo = 9.000.000 + (antigo − 547.290)`, para não colidirem com os IDs que produção passou a
> usar desde o retrato de 28/07. **Os IDs neste documento já estão atualizados.** O mapa
> completo antigo→novo está na tabela `wp_bahia_renum_map` (117 linhas), que **não deve ser
> apagada**. Plano e registro da operação em `RENUMERACAO-homolog.md`.

Isto reúne o que foi aprendido nas rodadas 2 a 5 e **não está escrito em nenhum outro lugar** —
nem no código, nem no histórico do git. Documentos irmãos:

- `AUDITORIA-templates.md` — o que renderiza cada página
- `MIGRACAO-homolog-para-prod.md` — o que precisa viajar para produção
- `PENDENCIAS-gestores.md` — o que depende de decisão de negócio
- `REVERSAO-adrotate-homolog.md` — anúncios de teste em homologação

---

## 0. A regra que vale para tudo: portão de contagem

**Toda medição precisa dizer quantas linhas entraram, quantas saíram e quantas foram
descartadas.** Sem isso, um instrumento que perde dado em silêncio vira o resultado — e a
conclusão errada chega com aparência de número certo.

Aconteceu duas vezes numa sessão só, e nas duas o erro era invisível:

- `xargs -I{}` do BSD/macOS **descartou 165 de 180 linhas** por comprimento, sem erro e com
  código de saída zero. Sobraram as 15 mais curtas — uma amostra enviesada que ainda devolvia
  um percentual plausível.
- Sorteio de 500 IDs num espaço esparso **colapsou em 31 registros distintos**, porque centenas
  de sorteios caíam no mesmo vazio e resolviam para o mesmo "próximo".

Nos dois casos, uma linha de conferência teria pego na hora:

```bash
echo "medidos: $(wc -l < saida.txt) de $(wc -l < entrada.txt)"
```

Vale para consulta em banco, varredura de arquivo, chamada de API em lote e amostragem
estatística. Se o número de saída não é conferido contra o de entrada, a medição não terminou.

O detalhe de cada caso está na **seção 16**.

---

## 0.1 Em `mu-plugins/`, commitar É instalar

**A `develop` reconstrói homolog e a `main` reconstrói produção. Tudo que está em
`mu-plugins/` entra em vigor no instante em que o pod sobe. Não existe versionar sem ativar
naquele diretório.**

Aconteceu em 25/08: commitei um plugin de conversão de imagem "só para versionar", empurrei para
`develop`, e o build instalou em homolog um código que estava explicitamente combinado para não
entrar ainda. `gh run cancel` exigiu permissão de admin que a credencial não tem, então a
correção foi empurrar a trava e deixar o build seguinte anular o anterior — o plugin ficou ativo
por cerca de dois minutos.

**Código que não deve rodar precisa de trava por constante**, logo depois da checagem de
`ABSPATH`:

```php
if (!defined('BAHIA_MEU_PLUGIN_ATIVO') || !BAHIA_MEU_PLUGIN_ATIVO) {
    return;
}
```

Assim ele viaja versionado e inerte; ligar é definir a constante, desligar é removê-la, e não há
estado a desfazer porque nada roda antes disso.

### E a verificação é `has_filter()`, nunca `class_exists()`

```php
class_exists('Bahia_Meu_Plugin')                                  // true MESMO com a trava!
has_filter('wp_handle_upload', array('Bahia_Meu_Plugin','x'))     // false — este é o teste
```

O PHP declara classes de nível superior **ao compilar o arquivo**, independentemente do fluxo de
execução: o `return` da trava impede que o `::init()` do final rode, mas não impede a classe de
existir. Quem verificar com `class_exists()` vai concluir que a trava falhou quando ela funcionou.

---

## 0.2 ⚠️ Apagar mídia em homolog apaga arquivo do site no ar

**Homolog e produção compartilham o bucket `static.bahia.ba`, o mesmo prefixo
`wp-content/uploads/` e a mesma distribuição do CloudFront `d1x4bjge7r9nas.cloudfront.net`.**

Conferido por amostra determinística: **142 de 142 IDs presentes nos dois bancos apontam para o
MESMO objeto no S3.** O banco de homolog é o retrato de produção de 28/07/2026, e trouxe junto a
tabela `wp_as3cf_items` inteira — cerca de 155.500 linhas apontando para arquivos que o site no
ar serve agora.

Como o WP Offload remove o objeto do bucket ao apagar o anexo:

> **APAGAR UM ANEXO ANTERIOR A 28/07/2026 EM HOMOLOG APAGA O ARQUIVO DO SITE NO AR —
> sem erro, sem aviso e sem backup do bucket.**

O bucket não tem versionamento de objeto nem lifecycle. O arquivo apagado **não volta**.

Vale para tudo que chame `wp_delete_attachment()`: a lixeira da biblioteca de mídia com exclusão
permanente, a ação em massa "Remove from bucket" do próprio Offload, e qualquer script.

**O pior cenário é rodar plugin de limpeza de mídia órfã em homolog.** Lá **quase toda a mídia
parece órfã**: o banco é de 28/07 e os posts que referenciam as imagens publicadas desde então não
existem naquele banco. Uma varredura de "imagens sem uso" marcaria dezenas de milhares de arquivos
de produção para exclusão, e todos seriam apagados de verdade.

Não é hipótese: em 25/08/2026 apaguei 24 anexos de teste em homolog e os objetos correspondentes
sumiram do bucket, conferido pasta a pasta. Eram objetos criados pelo próprio teste — se fossem
anteriores a 28/07, teriam sido imagens do site.

**Decisão consciente de 25/08/2026: não corrigir por ora**, porque homolog serve só para validar
alterações antes de subirem e ninguém roda limpeza lá. A trava, se um dia for preciso, é uma
linha num mu-plugin só de homolog:

```php
add_filter( 'as3cf_remove_source_files_from_provider', '__return_empty_array', 99 );
```

Levantamento completo, com as alternativas de prefixo e bucket separado e o custo de cada uma,
em `ISOLAMENTO-BUCKET.md`.

### E isto já aconteceu — em 25/08/2026, comigo, horas depois de eu escrever esta seção

Ao limpar anexos de teste em homolog usei filtro por título:
`post_title LIKE 'LOTE %' OR post_title LIKE 'RD %'`. O `LIKE` do MySQL é **insensível a
maiúsculas**, e dois anexos reais casaram — `lote leião saeb` (id 313723) e
`rd congo copa do mundo 2026` (id 542264). **Nove objetos de produção apagados**, sem
versionamento no bucket para restaurar. Relato e recuperação em `INCIDENTE-APAGUEI-2-IMAGENS.md`.

**A regra que fica: exclusão em massa se faz por lista explícita de IDs coletada no momento da
criação, nunca por padrão de título.** E, neste ambiente, com uma segunda trava antes de qualquer
`wp_delete_attachment` em homolog:

```php
if ( $id < 9000001 ) { continue; }   // nasceu em producao: NAO e teste
```

A faixa 9.000.001+ é a dos registros nascidos em homolog (ver a nota de renumeração no topo).
Os dois anexos que apaguei têm ID 313723 e 542264 — a trava os teria barrado.

---

## 0.2.1 ⚠️ As guardas de 0.2 se desligam sozinhas se o banco for trocado

**Descoberto em 27/08/2026**, no levantamento da subida do MySQL para 8.4. Vale para **qualquer
restauração de banco de produção em homolog**, hoje e no futuro — não é um detalhe daquele
projeto.

`bahia-flags.php` decide o ambiente **lendo o `siteurl` do banco**:

```php
$url = get_option('siteurl');
if (strpos($url, 'hml.bahia.ba') !== false)   $amb = 'homolog';
elseif (strpos($url, 'bahia.ba') !== false)   $amb = 'producao';
```

E `bahia-homolog-guardas.php`, que é o código da seção 0.2, começa assim:

```php
if (!function_exists('bahia_ambiente') || 'homolog' !== bahia_ambiente()) {
    return;
}
```

**Junte as duas coisas.** Restaure o banco de produção em homolog e o `siteurl` passa a ser
`https://bahia.ba`. `bahia_ambiente()` devolve `producao`. As duas guardas da seção 0.2 —
a que impede o Offload de remover objeto do bucket e a que barra exclusão de anexo abaixo de
9.000.001 — **retornam antes de registrar qualquer filtro**. Desligam-se sozinhas, sem erro,
sem aviso, sem nada no log.

E desligam-se **no pior momento possível**: logo depois de homolog receber a `wp_as3cf_items`
de produção inteira e recente, apontando para os mesmos objetos que o site no ar serve, no mesmo
bucket sem versionamento que já custou nove arquivos.

### A ironia, que é o que torna isto perigoso

O comentário do próprio `bahia-flags.php` diz:

> *"Usamos o siteurl e nao variavel de ambiente porque … um pod de homolog apontado por engano
> para o banco de producao seria detectado aqui — que e exatamente o acidente que se quer
> evitar."*

**A detecção funciona.** O que falha é o que se faz com o resultado: detectar "produção" e
desligar as proteções de homolog é o oposto do que a frase promete. A função é honesta; o uso
que as guardas fazem dela inverte a garantia.

### A regra, para qualquer restauração futura

**Nenhum pod de homolog pode servir com `siteurl` de produção no banco. Nem por um minuto.**

1. **Zerar as réplicas antes de restaurar** — `kubectl scale deployment/wordpress --replicas=0`.
2. **Corrigir o `siteurl` no mesmo passo da restauração**, conectando direto ao banco, antes de
   qualquer pod subir:
   ```sql
   UPDATE wp_options SET option_value='https://hml.bahia.ba'
    WHERE option_name IN ('siteurl','home');
   ```
3. **Conferir antes de escalar de volta.** Portão de saída, duas linhas:
   ```sql
   SELECT option_name, option_value FROM wp_options
    WHERE option_name IN ('siteurl','home');
   ```
4. **Conferir que as guardas voltaram**, e pelo teste da seção 0.1 — `has_filter()`, nunca
   `class_exists()`:
   ```php
   var_dump(has_filter('as3cf_remove_source_files_from_provider', '__return_empty_array'));
   // esperado: int(99) — a prioridade. false significa guarda DESLIGADA.
   ```

Isto vale igualmente para **apontar um pod de homolog para uma instância de banco restaurada de
produção**: a cópia é writável e traz o `siteurl` de produção junto. Corrija o `siteurl`
**na cópia**, antes de apontar qualquer pod para ela.

### E há uma porta aberta na rede, que só piora isto

Medido em 27/08/2026, de dentro do pod de homolog: o banco de **produção** responde em
`172.31.70.197:3306` em **1 ms**. As duas instâncias RDS vivem na mesma VPC `172.31.0.0/16` e o
*security group* não separa os ambientes. Hoje, uma variável de ambiente trocada em homolog
aponta para produção e **conecta**. É pré-existente, não foi criado por nenhuma rodada, e fica
registrado aqui porque multiplica a consequência de tudo nesta seção.

---

## 0.3 Duas mentiras silenciosas em mu-plugin com `namespace`

Custaram dois erros seguidos em 25/08, no `bahia-mais-lidas.php`. As duas **falham sem erro**,
que é o padrão que mais dói neste projeto.

### `function_exists('nome')` nunca acha função em namespace

```php
namespace BahiaNews\MaisLidas;

if (!function_exists('minha_funcao')) {   // consulta o espaco GLOBAL: SEMPRE false
    function minha_funcao() { ... }        // nasce como BahiaNews\MaisLidas\minha_funcao
}
```

O guard nunca protege, e um segundo `include` daria "Cannot redeclare". Pior: um teste que
verifique `function_exists('minha_funcao')` conclui que a função não existe **quando ela existe**.

```php
if (!function_exists(__NAMESPACE__ . '\\minha_funcao')) {   // este funciona
```

O mesmo vale para `has_filter`, `do_action` e qualquer callback passado como string: o nome tem
de ser qualificado. Por isso os `add_action` deste arquivo usam `__NAMESPACE__ . '\\refresh'`.

### Função declarada dentro de outra só existe depois que a outra roda

Vários mu-plugins daqui têm trecho de template no meio de uma função de render — `ob_start()`,
HTML, `<?php ... ?>`, e o fechamento. **Declarar uma função nesse trecho a torna aninhada:**

```php
function render() {
    ob_start(); ?>
    <div>
    <?php
    function ajudante() { ... }   // so passa a existir DEPOIS que render() roda uma vez
    ?>
    </div>
    <?php return ob_get_clean();
}
```

Enquanto ninguém chamar `render()`, `ajudante()` não existe — e qualquer outro código que a
chame morre com "undefined function". Depois que roda, existe para sempre, o que faz o sintoma
parecer intermitente.

**Função auxiliar vai no nível do arquivo**, antes da que a usa.

### O que as duas têm em comum

Nenhuma acusa erro no lugar onde o erro está. A primeira faz um teste responder "não" para algo
que é "sim"; a segunda faz existir ou não conforme a ordem de execução. Como a
[seção 0](#0-a-regra-que-vale-para-tudo-portão-de-contagem), a defesa é **verificar o efeito, não
a aparência**: conferir se o gancho ficou registrado, se a função responde, se a saída mudou.

---

## 0.4 Decisões de risco recusadas — registro

Quando uma correção de risco é medida, proposta e **recusada**, o desacordo fica aqui, com a
medição e a data. Não é para cobrar ninguém: é para que a próxima pessoa saiba que foi
**decisão**, não descuido — e para que, se o risco se realizar, o caminho de volta já esteja
escrito.

### 25/08/2026 — trava de remoção no bucket compartilhado

| | |
|---|---|
| **risco medido** | homolog e produção compartilham `static.bahia.ba`; 142 de 142 IDs conferidos apontam para o mesmo objeto. Apagar anexo anterior a 28/07 em homolog apaga arquivo do site no ar. |
| **correção proposta** | `add_filter('as3cf_remove_source_files_from_provider','__return_empty_array',99)` — uma linha, custo zero |
| **decisão** | não aplicar, por ora. Argumento: homolog serve só para validar alterações e ninguém roda limpeza lá |
| **desfecho** | **o risco se realizou na mesma tarde.** Limpar anexos de teste É limpeza em homolog, e estava no roteiro do dia. Nove objetos de produção apagados, sem versionamento para restaurar |
| **estado** | trava **aplicada** em 25/08, junto com a guarda por faixa de ID. Ver `bahia-homolog-guardas.php` |

A lição que o caso deixa não é sobre esta trava específica. É que **"ninguém faz isso" não é
controle** — é previsão de comportamento, e previsão de comportamento falha no dia em que a
rotina muda. Controle é o que impede, não o que se espera.

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
| `bahia_header_9000124_backup` | Header, antes da rodada 3 | 36.948 |
| `bahia_header9000124_backup_20260810-195048` | Header, rodada 3 | 34.608 |
| `bahia_header9000124_mobile_backup_20260810-200627` | Header, parte mobile | 2.611 |
| `bahia_home_9000142_backup_20260810-191418` | Home, último da rodada 3 | 32.530 |
| `bahia_home_9000142_backup_20260810-191206` | Home, rodada 3 | 32.531 |
| `bahia_home_9000142_backup_20260810-191025` | Home, rodada 3 | 33.478 |
| `bahia_home9000142_backup_20260810-141305` | Home, rodada 3 (início) | 33.456 |
| `bahia_home_content_backup_r3` | Home, conteúdo rodada 3 | 31.401 |
| `bahia_footer_9000126_backup_20260810-190732` | Rodapé, rodada 3 | 6.178 |
| `bahia_footer_9000126_backup` | Rodapé, rodada 3 | 5.986 |
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
wp_update_post(array('ID' => 9000140, 'post_content' => $anterior));
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

## 11.5 Duas armadilhas de manutenção de banco, aprendidas na renumeração

Valem para **qualquer** operação futura de banco neste site, não só para a renumeração.

### 11.5.1 O `.maintenance` derruba também o script de manutenção

Criar `.maintenance` põe o site fora do ar — e junto com ele **qualquer script que carregue o
WordPress**, inclusive o que vai fazer a manutenção. `wp-load.php` chama `wp_maintenance()`,
que encerra a execução com a tela de "Briefly unavailable".

A porta prevista pelo core é `wp_installing()`: o `wp_maintenance()` se abstém quando ela é
verdadeira. Então, no topo do script, **antes** do `wp-load.php`:

```php
define('WP_INSTALLING', true);
require '/var/www/html/wp-load.php';
```

O site segue fora do ar para o visitante e o script roda. Sem isso, a escolha vira falsa:
ou o site fica no ar durante a operação, ou a operação não roda.

Detalhe medido: com o `.maintenance` no lugar, o site continuou respondendo **200** por causa
do `fastcgi_cache` do sidecar. Não é problema — nenhum PHP executa, logo não há escrita —, mas
não confunda "responde 200" com "não está em manutenção".

### 11.5.2 `wp_update_post()` sem usuário logado passa o conteúdo pelo kses

Num script de CLI não há usuário autenticado, então `current_user_can('unfiltered_html')` é
falso e o `wp_update_post()` **filtra o HTML** que está gravando. Para um `post_content` de
`tdb_templates`, cheio de shortcode e atributo, isso corrompe o template — silenciosamente,
porque a função não devolve erro.

E há um segundo efeito: cada `wp_update_post()` gera uma **revisão nova**, que nasce com um ID
do `AUTO_INCREMENT` — exatamente o que uma operação de renumeração está tentando controlar.

Para edição cirúrgica de conteúdo, use:

```php
$wpdb->update($wpdb->posts, array('post_content' => $novo), array('ID' => $id));
clean_post_cache($id);
```

E **releia do banco para conferir**, que é a garantia que se perde ao não usar a API. Na
renumeração, cada template foi relido e teve as ocorrências recontadas depois de gravar.

Se precisar mesmo dos hooks de salvamento do tagDiv, a alternativa é
`wp_set_current_user()` com um administrador antes do `wp_update_post()` — mas aí voltam as
revisões.

---

## 12. Correção de premissa: o limite de 160 nunca atuou sobre o subtítulo

Ficou registrado desde a rodada 2 que o `bahia-limites-texto.php` impõe "70 no título e 160
no subtítulo/resumo". A primeira metade está certa. A segunda descreve outro texto.

**O que os 160 realmente cortam:** o resumo do card de listagem. O plugin preenche
`post_excerpt` no objeto que o módulo do tagDiv vai renderizar
(`bahia-limites-texto.php:156`, filtro `td_wp_booster_module_constructor`), tomando como
origem `post_excerpt` quando existe e `post_content` quando não existe.

**`post_excerpt` não existe em lugar nenhum do acervo:** 0 preenchidos em 271.714 posts
publicados, em todos os tipos. Ou seja, o corte de 160 cai **sempre** no ramo do
`post_content` — o primeiro parágrafo do corpo da matéria, texto que ninguém escreveu para
ser resumo.

**O subtítulo é outra coisa:** é o campo ACF `subtitulo`, escrito pela redação, presente em
271.679 de 273.656 registros, com média de 101 caracteres. Ele nunca passou pelo
`bahia-limites-texto.php`.

A prova é o post #547268:

| | Texto |
|---|---|
| ACF `subtitulo` | "Governador diz que declaração sobre 'alforria' revela os valores do candidato do PSD ao Planalto e ataca aliança com ACM Neto." |
| Resumo do card na home | "O governador da Bahia, Jerônimo Rodrigues (PT), reagiu nesta terça-feira (28) à declaração do ex-governador de Goiás e pré-candidato à Presidência da…" |

São textos diferentes, de origens diferentes. O número 160 provavelmente veio por analogia
ao `resumo(170, $subtitulo)` do tema antigo (`bahia_refactor/functions.php:956`), que
cortava o subtítulo mesmo — mas o que foi implementado no Newspaper corta outra coisa.

**Nada disso foi alterado.** O corte do resumo do card continua exatamente como está: é o
comportamento validado em desktop e mobile ao longo de onze rodadas. O registro existe para
que a próxima pessoa não procure no `bahia-limites-texto.php` um controle de subtítulo que
nunca esteve lá.

Para a exibição do subtítulo, que é assunto separado, ver a seção 13.

## 13. O subtítulo da matéria sumiu na migração para o Newspaper

A redação escreve o campo ACF `subtitulo` em toda matéria. O tema antigo o exibia como
`<h2>` logo abaixo do `<h1>` (`bahia_refactor/single_web.php:47` e `single_mobile.php:37`) e
também nos cards de listagem, cortado em 170 com `[...]`
(`bahia_refactor/functions.php:956`, `sidebar-*.php`). No Newspaper ele **não aparecia em
lugar nenhum** — nem no single, nem no archive, nem na home.

Não era falta de lugar onde exibir. O `loop-single.php` do td-composer (linhas 20-22) já
imprime um `<p class="td-post-sub-title">` quando
`td_post_theme_settings['td_subtitle']` tem valor, e o pacote mobile do plugin faz o mesmo
(`td-composer/mobile/single.php:42-44`). O slot estava vazio porque lê o campo próprio do
tagDiv, e esse campo tem **0 registros** no banco — a redação sempre escreveu no ACF.

**Conserto (`bahia-subtitulo.php`):** um filtro em `get_post_metadata` preenche
`td_post_theme_settings['td_subtitle']` a partir do ACF `subtitulo`. Usa o slot nativo, com
a marcação e o CSS do próprio tema, sem editar `plugins/` nem o tema.

Três detalhes que o filtro tem de respeitar, e respeita:

1. **Reentrância.** Ler `td_post_theme_settings` de dentro do filtro de
   `get_post_metadata` chama o filtro de novo. Uma flag estática corta o ciclo.
2. **Devolver `array($settings)`, não `$settings`.** Com `$single = true` o core devolve
   `$check[0]`; entregar o array direto devolveria o primeiro elemento dele.
3. **Só o post da própria página.** Cards de sidebar e de relacionados leem a mesma chave.
   A guarda é `is_singular()` + `$post_id === get_queried_object_id()`, e é ela que garante
   que nenhuma listagem foi tocada.

O `bahia-subtitulo.php` cobre hoje três consumidores do mesmo campo: o single (seção 13), a
description das meta tags (seção 14) e o resumo dos cards (seção 15).

## 14. A meta description sumia em 99,6% do acervo

O achado mais sério da migração, e o mais silencioso: não quebra nada visível no site.

O tema antigo alimentava `description`, `og:description` e `twitter:description` com o
subtítulo (`bahia_refactor/header.php:24-37`). No Newspaper quem responde é o Yoast, e o
estado medido em homolog era:

- os templates `metadesc-*` do Yoast estão **todos vazios**, para todos os tipos;
- só **968** posts de 271.679 têm description escrita à mão (0,36%);
- logo, em 99,6% do acervo a tag `<meta name="description">` **não era emitida**, e o
  `og:description` virava o primeiro parágrafo do corpo, cortado no meio e começando com
  um espaço-duro solto — que é o texto que o WhatsApp mostra no card do link.

Medido em três posts, homolog contra produção:

| | Produção | Homolog (antes) |
|---|---|---|
| `description` | subtítulo do repórter | **ausente** |
| `og:description` | subtítulo do repórter | corpo cortado, com `\xa0` no início |

**Conserto:** os filtros `wpseo_metadesc`, `wpseo_opengraph_desc` e
`wpseo_twitter_description` recebem o subtítulo como **fallback**, nunca como
sobrescrita. A ordem de precedência é: description escrita à mão no Yoast → subtítulo →
o que o Yoast já fazia. Os 967 posts com texto próprio (verificado: 99,9% não são o começo
literal do corpo, ou seja, foram mesmo escritos) continuam intactos.

Dois detalhes que importam:

1. **Devolver texto cru, sem escape.** O Yoast passa o valor por `strip_all_tags()` e
   escapa com `esc_attr()` na saída (`Abstract_Indexable_Tag_Presenter::present()`).
   Pré-escapar produziria `&amp;quot;` na tag. É o oposto do que o single exige, onde o
   template imprime sem escapar e o escape tem de vir pronto.
2. **O Yoast não trunca.** Verificado com um subtítulo de 163 caracteres: sai inteiro nas
   três tags, idêntico ao da produção. Os presenters aplicam replace-vars, o filtro,
   `strip_all_tags` e `trim` — não há limite de comprimento. Os 156 caracteres do Yoast
   são recomendação da pré-visualização, não corte.

Efeito colateral bem-vindo: o `twitter:description`, que não era emitido, passou a sair.

**Qualidade das 968 descriptions preservadas**, para uma decisão futura: 365 (37,7%)
terminam com pontuação final, 590 (61,0%) são frases inteiras sem ponto, e apenas **13
(1,3%) estão cortadas no meio** — terminam em "da", "sua", "o", palavras que não encerram
frase. Os 13 têm subtítulo preenchido disponível para substituí-las. Nada foi alterado;
fica registrado caso se queira tratar esse resíduo. Cuidado com o critério ingênuo "não
termina com ponto": ele acusa 603, e a maioria são frases completas sem ponto final.

## 15. O subtítulo nos cards, e por que só a home mudou

A produção usa o subtítulo em **todas** as listagens, com regras diferentes por caminho:

| Caminho | Corte | Se o subtítulo estiver vazio |
|---|---|---|
| `showLinePostWeb` (desktop) | `resumo2(200)` | imprime `<p>` vazio |
| `showLinePostMobile` | `resumo(170)` | cai em `get_the_excerpt()` |
| `sidebar-*.php` | `resumo(170)` | cai em `get_the_excerpt()` |

Os dois cortam em **bytes** — `resumo()` usa `strlen()`, não `mb_strlen`. Com a razão
medida de 1,033 byte por caractere no acervo, 170 bytes ≈ 165 caracteres.

No Newspaper o subtítulo entra pelo `td_wp_booster_module_constructor` com prioridade 20,
depois do corte de 160 do `bahia-limites-texto.php` (prioridade 10). Quando há subtítulo,
ele substitui; quando não há, o que o outro filtro escreveu a partir do `post_content`
fica — o card nunca sai vazio, ao contrário do desktop da produção.

**Só a home mudou. É divergência consciente da produção, não esquecimento.** As outras
listagens deste build não têm slot de resumo nenhum: o archive de editoria e a busca usam
`td_module_1`, que renderiza imagem, título, autor e data, sem `.td-excerpt` no DOM.
Medido: 0 elementos em `/politica/` e em `/?s=salvador`.

A produção mostra subtítulo nessas listagens; o Newspaper não mostra resumo nenhum nelas.
Acrescentar um não seria restaurar o subtítulo — seria inventar um elemento que o layout
validado em onze rodadas não tem, mudando archive e busca por efeito colateral de uma
decisão sobre o subtítulo. Fica como está, e a diferença em relação à produção é
deliberada. Se um dia se quiser resumo em archive e busca, é decisão de layout com
validação própria, não continuação desta.

**Os dois filtros não se atrapalham,** e isso foi verificado nos dois sentidos:

- post *sem* subtítulo — `og:description` continua com os mesmos 198 caracteres gerados
  pelo Yoast, byte a byte, depois do filtro de cards entrar;
- post *com* subtítulo de 163 caracteres — o card mostra o corte de 160 e a description
  mostra os 163 inteiros, no mesmo request.

O que garante isso é a guarda herdada do `bahia-limites-texto.php`: no single, o post
consultado nunca tem o `post_excerpt` tocado, então o Yoast lê o post intacto quando
precisa gerar a description sozinho.

Geometria da home, antes e depois: mesmos 9 cards com resumo, mesmos 8 blocos, todos com
`margin-bottom: 48px` — inalterado. As alturas de três cards caíram (415→395, 412→391,
374→353) porque a chamada é mais curta que o parágrafo truncado; a página inteira ficou
42px mais baixa. É o efeito pretendido, não regressão.

---

## 16. Instrumentos que descartam dado em silêncio

Duas medições desta sessão só se salvaram porque o número saiu estranho e foi refeito. As duas
falharam do jeito pior possível: **sem erro, sem aviso, devolvendo um resultado plausível.**

### 16.1 `xargs -I{}` do BSD/macOS engole linhas longas

Ao medir 180 pares de URL do CloudFront (derivada contra original), isto devolveu **15** linhas:

```bash
xargs -P 12 -I{} ./medir.sh {} < pares.txt > medidos.txt
```

Sem erro, sem código de saída diferente de zero. O `xargs` do BSD tem limite de comprimento por
linha quando se usa `-I` — duas URLs completas do CloudFront passam de 250 caracteres e as linhas
maiores são **descartadas caladas**. Sobraram exatamente as 15 mais curtas, e a amostra
enviesada ainda dava um número coerente (88,6%), que passaria despercebido.

O sintoma é sempre o mesmo: **o arquivo de saída tem menos linhas que o de entrada.** Conferir
isso é obrigatório.

```bash
# em vez de xargs -I{}, laço próprio com paralelismo controlado
n=0
while IFS='|' read -r a b c; do
  { ...; printf '%s|%s\n' "$x" "$y" >> saida.txt; } &
  n=$((n+1)); [ $((n % 8)) -eq 0 ] && wait
done < entrada.txt
wait
echo "medidos: $(wc -l < saida.txt) de $(wc -l < entrada.txt)"   # <- o portão
```

### 16.2 Amostragem por ID aleatório em espaço esparso colapsa

Para estimar o estado do acervo, sortear 500 IDs entre `MIN(ID)` e `MAX(ID)` e pegar o anexo
seguinte devolveu **31 anexos distintos**, não 500. Os IDs de anexo ocupam uma fração pequena de
um espaço que vai a 9 milhões, com grandes vazios; centenas de sorteios caem no mesmo vazio e
resolvem para o mesmo "próximo" anexo. A amostra vira um punhado de registros repetidos, e a
estatística sai com aparência normal.

O que funcionou: **estratificar por ano** (`post_date` é indexado junto com `post_type`) e, dentro
de cada ano, pegar em cinco `OFFSET` distintos — início, 25%, 50%, 75%, 95% — ponderando depois
pela contagem real de cada ano.

### 16.3 O `carga.sh` media e não gravava — e o pico saía 45% menor

**Descoberto e corrigido em 27/08/2026**, no levantamento da subida do MySQL. Dois defeitos no
mesmo arquivo, e os dois são do padrão desta seção.

**Defeito 1 — escrevia num diretório que não existia mais.** A linha 5 fixava

```bash
S=/private/tmp/claude-501/…/076a2b37-27dc-4ecf-b4d4-8764bd6b55c8/scratchpad
```

que é o diretório de **uma sessão específica**, apagado quando a sessão terminou. Com ele
ausente: o `rm -f` de limpeza passava calado, os `>>` das 30 respostas falhavam um a um, e a
carga **rodava inteira** — 30 requisições concorrentes num t3.micro — sem que uma linha fosse
gravada. O resumo em Python só quebrava **no fim**, depois de todo o custo já ter sido pago.

Pior que quebrar: se o diretório de outra sessão ainda existisse, ele gravaria lá, o `rm -f` não
limparia o que interessa, e a leitura sairia **de uma execução anterior** — número plausível, da
medição errada.

**Defeito 2, o mais grave — o pico de `Threads_running` era tirado de 3 amostras.** O monitor
fazia 24 `kubectl exec` separados, cada um com `require_once wp-load.php`. O bootstrap do
WordPress custa ~5 s, e a carga terminava em ~16 s: **colhiam-se 3 amostras**, não 24. Medido
lado a lado depois do conserto, na mesma homologação, com minutos de intervalo:

| Execução | Amostras | `Threads_running` pico | `SQL_CALC` pico |
|---|---|---|---|
| monitor antigo | **3** | **6** | 2 |
| monitor novo | **31** | **11** | 5 |

**O critério de aceitação em uso desde a virada abortada de 18/08 é "`Threads_running` abaixo de
10 no pico".** Com 3 amostras o número era 6 e **passava**. Com 31 é 11 e **reprova**. O
instrumento não errava por pouco: ele decidia o portão ao contrário.

O conserto do monitor é um `kubectl exec` só, com laço PHP dentro e conexão `mysqli` direta
(sem `wp-load.php`), amostrando a cada 0,5 s.

**O que o script faz agora, e é o que importa levar para qualquer outro instrumento:**

1. **Testa a escrita ANTES de gastar a medição** — `mkdir -p` e um toque no diretório de saída.
   Falhar aqui custa nada; falhar depois custa a carga inteira.
2. **Portão de contagem explícito**, impresso e com código de saída:
   ```
   URLs disparadas: 30   respostas gravadas: 30   amostras do banco: 31
   ```
   Se `gravadas ≠ disparadas`, ou se as amostras forem menos de 10, imprime `*** FALHOU`,
   avisa que os números estão incompletos e **sai com código 1**.
3. **Saída ao lado do próprio script** (`./carga-saida/`), com `CARGA_OUT` para sobrescrever —
   nunca mais um caminho de sessão embutido.
4. Alvo parametrizável por `CARGA_CTX` / `CARGA_BASE`, para medir outro ambiente sem editar o
   arquivo.

O original ficou em `carga.sh.orig-20260827`.

**A lição comum às três subseções:** um instrumento que perde dado em silêncio **vira** o
resultado. Aqui ele quase inverteu uma decisão de virada.

### 16.4 A probe que mediria o cache, não o banco

**Levantado em 27/08/2026.** Ainda não existe — é um erro que **quase** foi cometido, e fica
aqui porque é da mesma família das três subseções acima.

O Deployment de produção não tem nenhuma probe (nem `readiness`, nem `liveness`, nem `startup`,
nos dois contêineres). Ao desenhar a correção, o reflexo é o mais óbvio:

```yaml
readinessProbe:
  httpGet: { path: /, port: 80 }     # <- ERRADO, e o erro é invisível
```

**Essa probe passaria com o banco fora do ar.** O nginx serve `/` a partir do `fastcgi_cache`:
a resposta sai do disco, sem tocar em PHP e sem tocar em MySQL. A probe ficaria verde, o pod
seria declarado pronto, o Deployment encerraria o pod antigo — e o pod novo, incapaz de falar com
o banco, receberia tráfego.

O instrumento devolveria **200, rápido e plausível**, medindo exatamente aquilo que não
interessa. É o `xargs` que descarta linhas, é a amostragem que colapsa, é o `carga.sh` gravando
em diretório inexistente: **resultado convincente, medição ausente.**

**O que uma probe correta precisa ter aqui**, e nenhum dos quatro é opcional:

1. **Endpoint próprio** (`/bahia-saude`), com um `SELECT 1` pelo `$wpdb`, devolvendo `200 ok` ou
   `503 db-fail`. Sem tema, sem `WP_Query`.
2. **Fora do `fastcgi_cache`**, por regra explícita de bypass — senão volta ao problema de cima.
3. **Fora do buffer de saída** (`bahia-html-saida.php`), dos contadores e do analytics.
4. **Sem vazar** a mensagem de erro do MySQL.

O levantamento completo, incluindo por que `livenessProbe` é perigosa aqui (ela **mata** o
contêiner: um soluço do RDS viraria reinício simultâneo de todos os pods) e por que a probe
compete com o tráfego pelos mesmos 12 workers do PHP-FPM, está em
`scratchpad/UPGRADE-MYSQL.md`, Anexo D. **Não implementar junto com a subida do MySQL.**

**A pergunta que generaliza as quatro subseções:** *o que este instrumento responderia se a coisa
que ele deveria detectar estivesse acontecendo agora?* Se a resposta for "a mesma coisa", ele não
é um instrumento.

### 16.5 Ler a opção não prova que o plugin que a interpreta está ligado

**Errei isto em 27/08/2026**, e o erro chegou a virar instrução escrita para o Albert.

Ao apontar onde ficava a tela de login de produção, li a opção `whl_page` do banco, que vale
`'acesso'`, e afirmei: *"o login está em `https://bahia.ba/acesso/`, atrás do
`wps-hide-login`"*.

**O `wps-hide-login` não está em `active_plugins`.** O diretório existe em `plugins/`, a opção
existe em `wp_options`, e nada disso significa que o código roda. Medido depois:

| URL | Resposta |
|---|---|
| `/acesso/` e `/acesso` | **301**, e o `redirect_guess_404_permalink` do núcleo leva para `/politica/acesso-a-alba-so-com-comprovante-de-vacina/` |
| `/wp-login.php` | **200** — é aqui que se entra, e não está escondido |

A opção é resto de quando o plugin esteve ativo. **O dado estava lá, era plausível, e não
significava o que parecia.**

**A regra:** uma linha em `wp_options` é uma *intenção registrada*, não um *comportamento
vigente*. Antes de concluir qualquer coisa a partir de uma opção de plugin, confirmar que o
plugin está ativo:

```php
in_array('wps-hide-login/wps-hide-login.php', get_option('active_plugins', array()))
```

E, quando o que interessa é o comportamento e não a configuração, **medir o comportamento**: um
`curl` no endereço responde a pergunta que a opção só sugere.

Vale para toda chave de plugin removido. Este banco tem várias: 36 opções `rank_math*` de um
plugin que saiu em junho de 2026, e duas `schema-ActionScheduler*` de uma biblioteca que não
está instalada. Nenhuma delas descreve o que o site faz hoje.

**É a mesma família do §16.4** — instrumento devolvendo resultado plausível sem medir o que
interessa —, com a diferença de que aqui o instrumento é uma consulta ao banco.

### 16.6 Medir em homolog e afirmar sobre produção

**Descoberto em 27/08/2026**, e é irmão direto do §16.5: o dado estava certo, o ambiente é que
era o errado.

O `PENDENCIAS-gestores.md` §3 afirmava, desde 11/08, que a contagem de exibição de anúncios
estava **desligada** e que o último registro era de **28/06/2026**. **A afirmação foi do Albert**,
e o levantamento que a originou foi feito **em homologação** — o próprio texto dizia
*"números levantados em 11/08/2026, no ambiente de homologação"*, e mesmo assim a conclusão
viajou como se valesse para o site no ar.

Medido em produção em 27/08:

| | Homologação (11/08) | **Produção (27/08)** |
|---|---|---|
| Contagem | desligada | **ligada** (`adrotate_config[stats] = '1'`) |
| Último registro | 28/06/2026 | **agora**, ~70 por minuto |
| Anúncios ativos contados | 0 de 3 | **2 de 3** |
| Exibições no dia | — | **20.825** |

**Por que dói mais que um erro técnico:** este item foi para um documento de gestão, com
prioridade "Alta (comprovação ao anunciante)". Alguém do comercial podia ter deixado de cobrar
relatório, ou negociado renovação sem número, com base nele.

**A regra:** homologação é uma cópia de teste, e **nada nela é evidência sobre produção** —
nem configuração de plugin, nem volume, nem estado de dado. Os dois ambientes divergem de
propósito e por acidente: nesta mesma sessão descobrimos que rodam versões diferentes do MySQL
(8.0.45 contra 8.0.42), parameter groups diferentes (`default.mysql8.0` contra `mysql80-edit`),
e homolog tem um índice FULLTEXT que produção não tem.

**Antes de escrever qualquer afirmação sobre produção, medir em produção** — e, quando a medição
tiver de ser em homolog por segurança, **dizer isso na própria frase**, não só no rodapé.

### 16.7 `information_schema.TABLES` devolve o valor de ANTES depois do `OPTIMIZE`

**Aconteceu em 29/08/2026, na janela de manutenção**, no T0 (`OPTIMIZE TABLE wp_adrotate_tracker`,
produção). É o §16.4 na forma mais pura que este projeto encontrou: **o instrumento responde a
mesma coisa independentemente do que aconteceu.**

O comando devolveu em 0,612 s, com a nota esperada e `status OK`. A conferência imediata:

```sql
SELECT ROUND(DATA_FREE/1024/1024,1) FROM information_schema.TABLES
 WHERE TABLE_SCHEMA='prod' AND TABLE_NAME='wp_adrotate_tracker';
-- 2056.0   <- o MESMO numero de antes, ate a casa decimal
```

**A tabela tinha acabado de encolher de ~2.062 MB para 11,0 MB.** A estatística do
`information_schema` é cacheada, e `innodb_stats_on_metadata=OFF` é o **padrão** no MySQL 8.0 —
a leitura não a atualiza. O número não estava errado por arredondamento nem por atraso de
segundos: era **literalmente o valor anterior, preservado**.

**Por que é o modo de falha pior:** um `OPTIMIZE` que não fez nada e um `OPTIMIZE` que devolveu
2 GB **produzem a mesma leitura**. Quem confere só por aí conclui que a operação falhou — e a
reação natural é repetir uma reescrita de tabela em produção, sem necessidade.

**A leitura confiável veio de dois caminhos independentes**, e é isso que fecha o item:

| Instrumento | Natureza | Leu |
|---|---|---|
| `information_schema.TABLES.DATA_FREE` | **estatística cacheada** | 2.056 MB — **o valor de antes** |
| `information_schema.INNODB_TABLESPACES.FILE_SIZE` | **dicionário de dados**, não estatística | **11,0 MB** |
| `FreeStorageSpace` (CloudWatch) | métrica do volume, **fora do banco** | 9,432 → **11,464 GiB** |

Dicionário e CloudWatch não compartilham mecanismo com a estatística cacheada — por isso
concordarem entre si é prova, e concordarem com a `TABLES` não seria.

**A regra:** ao medir espaço em InnoDB, `INNODB_TABLESPACES.FILE_SIZE` é a fonte, e a confirmação
sai **de fora do banco**. `ANALYZE TABLE` traz a `TABLES` para a realidade depois — mas só se
alguém lembrar de rodá-lo, e é justamente isso que não se pode assumir.

### 16.8 A API do RDS diz `available` quase 5 minutos depois de o banco já responder

**Medido em 29/08/2026**, na subida da instância de teste para 8.4.9.

| | Hora (UTC) |
|---|---|
| Último `SELECT` que respondeu | **03:39:00** |
| Primeiro `SELECT` que voltou | **03:41:01** |
| **Indisponibilidade real** | **121 s** |
| A API/console diz `available` | **03:45:57** |
| **Erro se cronometrar pelo status** | **+4 min 56 s — mais que o dobro** |

O status `available` é sobre o **ciclo de vida da instância** (tarefas pós-subida, snapshot,
reconfiguração), não sobre o banco aceitar consulta. As duas coisas terminam em momentos
diferentes, e a diferença não é ruído: é maior que a queda inteira.

**Por que isto importa mais do que parece:** na Fase D o número da indisponibilidade **vai para
os gestores**. Relatar 7,5 minutos onde houve 2 é errar por 3,7×, e errar para o lado que faz a
operação parecer pior do que foi.

**A regra:** cronometrar do **último `SELECT` que respondeu ao primeiro que voltou**, com sonda
externa de 1 em 1 segundo que **reconecta a cada tentativa** — a conexão morre na subida, e uma
sonda que só reusa a conexão mede o próprio soquete morto, não o banco.
Script: `scratchpad/indisponibilidade.php`.

### 16.9 `StorageOperationPercentProgress` aparece — e fica parado em 0%

**Medido em 29/08/2026.** O `UPGRADE-MYSQL.md` §1.5 previa dois casos para o portão de
aquecimento: **o campo aparece** (esperar 100%) ou **não aparece** (portão empírico).

O real é um **terceiro caso, e é o pior dos três**: o campo aparece, informa
`StorageOperationStatus=Initializing`, e **não sai de 0%** — 12 minutos depois, com a instância
já `available` e servindo consultas. Esperar os 100% seria esperar para sempre.

**Um campo que existe e não anda é pior que um campo ausente:** a ausência manda procurar outro
instrumento; a presença convida a confiar e esperar.

**A medição que o campo deveria dar, e que o portão empírico dá de fato** — mesma consulta,
primeira leitura contra segunda, na instância recém-restaurada:

| tabela | frio | quente | razão |
|---|---|---|---|
| `wp_postmeta` | **74.400 ms** | 1.233 ms | **60×** |
| `wp_yoast_indexable` | 3.034 ms | 50 ms | 60× |
| `wp_bahia_search_idx` | 1.451 ms | 37 ms | 39× |
| `wp_posts` | 1.986 ms | 62 ms | 32× |

E na busca, a mediana caiu de **818,8 ms para 15,4 ms** depois de aquecida.

**A regra, e vale para o verde do Blue/Green:** o portão de aquecimento é **empírico**, nunca o
`StorageOperationPercentProgress`. O sinal confiável é `Innodb_buffer_pool_reads` **parar de
crescer** entre passadas — foi o que sustentou a comparação 8.0 × 8.4 (congelado em 35.208 de um
lado e 126.757 do outro), não o total de bytes no pool, que diferia 6× entre as duas medições
por razão irrelevante.

### 16.10 Duas corridas de carga encavaladas medem a fila da primeira

**Errei isto em 29/08/2026**, validando o homolog em 8.4.9, e o erro chegou a virar hipótese
escrita antes de eu perceber.

A corrida logo após o restart deu mediana 10,63 s. Suspeitei de buffer pool frio e **repeti
30 segundos depois** para ver se melhorava. Saiu **pior**: 12,43 s, com `Threads_running` médio
subindo de 7,1 para 8,3.

**A segunda corrida não mediu aquecimento. Mediu a fila que a primeira deixou.** São 30
requisições simultâneas de URLs frias contra uma `t3.micro`; meio minuto não basta para o
PHP-FPM, o pool de conexões e o buffer pool voltarem ao repouso.

Com **5 minutos de descanso**, a mesma carga deu **10,54 s e `Threads_running` pico 9** — abaixo
do 8.0.45, que era 11.

| Corrida | Intervalo desde a anterior | Mediana | `Threads_running` pico/média |
|---|---|---|---|
| #1 | — (pós-restart) | 10,63 s | 13 / 7,1 |
| #2 | **30 s** | **12,43 s** | **15 / 8,3** |
| #3 | **5 min** | **10,54 s** | **9 / 3,5** |

**O número saiu 18% pior sem que nada tivesse piorado** — e, se eu tivesse parado na #2, teria
relatado uma regressão que não existe.

**A regra:** medição de carga precisa de **intervalo de recuperação** entre corridas. Sem ele, o
instrumento mede a si mesmo.

**Onde isto morde de verdade:** na Fase D o portão de carga em produção — `Threads_running` aos
0, 5 e 15 minutos, com **acima de 10 virando rollback** — é o que decide desfazer a virada. Um
número inflado por corrida encavalada **dispara um rollback desnecessário em produção**. As
janelas de 0/5/15 já embutem o intervalo, **desde que não se repita a medição dentro de cada
uma**.

### A regra que fica

Toda medição precisa de um **portão de contagem**: quantas linhas entraram, quantas saíram, e
quantas foram descartadas e por quê. Sem isso, o instrumento silencioso vira o resultado.
Isto vale também para `grep -o` com regex de contexto largo (`.{0,150}`), que em arquivo de
centenas de KB entra em backtracking e estoura o tempo em vez de responder — usar Python.
