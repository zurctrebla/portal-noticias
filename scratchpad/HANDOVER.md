# Handover técnico — bahia.ba / tema Newspaper (Magazine PRO)

**Escrito em:** 11/08/2026, ao fim da rodada 5
**Para:** quem for mexer neste site depois, incluindo eu mesmo daqui a três meses

> **Renumeração de IDs — 16/08/2026.** Os registros nascidos em homolog (templates, páginas,
> anexos e itens de menu) foram movidos para a faixa **9.000.001+** pela fórmula
> `novo = 9.000.000 + (antigo − 547.290)`, para não colidirem com os IDs que produção passou a
> usar desde o retrato de 28/07. **Os IDs neste documento já estão atualizados.** O mapa
> completo antigo→novo está na tabela `wp_bahia_renum_map` (117 linhas), que **não deve ser
> apagada**. Plano e registro da operação em `RENUMERACAO-homolog.md`.

---

## 🧭 A regra que mais se pagou neste projeto

> ### Sinais independentes confirmam. Sinais irmãos só ecoam.

Antes de tratar **concordância** como confirmação, pergunte se as fontes **podem errar de formas
diferentes**. Se não podem, você tem **uma fonte só**, lida várias vezes — e a repetição não
acrescenta nada além de confiança indevida.

**O caso que a formulou**, em 02/09/2026: para saber se a atualização do Yoast tinha disparado
reindexação, li três opções — `wpseo_indexation_started`, `wpseo_indexables_indexation_reason`,
`wpseo_unindexed_post_count`. **Todas em `false`, todas concordando.** Escrevi *"nenhuma
reindexação foi disparada"*.

A indexação estava rodando o tempo todo. As três marcam a reindexação **iniciada pela interface**
e nenhuma fala do **cron de fundo** — respondiam com exatidão a uma pergunta que eu não estava
fazendo. Horas depois, aquilo derrubou homolog.

**E o pior é o mecanismo:** se eu não tivesse encontrado opção nenhuma, teria ido atrás de outra
evidência — o `PROCESSLIST`, o agendamento, o log. **A concordância entre as três substituiu a
busca por evidência.** Um sinal ausente teria me servido melhor que três sinais irmãos.

Detalhamento no **§16.12**, e o §16 inteiro é a família: instrumentos que respondem com confiança
a outra pergunta.

---

## 🧭 A segunda regra: guardas que viajam junto com o perigo

> ### A guarda depende de algo que viaja junto com o dado que ela protege?

Se depende, ela **se desliga exatamente quando é mais necessária** — porque o gesto que traz o
perigo é o mesmo que apaga a guarda. **Duas vezes este projeto encontrou isso, nas duas direções
opostas**, e é o contraste que ensina.

### Caso A — a guarda que se desliga sozinha (25 a 27/08/2026)

`bahia-homolog-guardas.php` protege o bucket compartilhado, e começa assim:

```php
if (!function_exists('bahia_ambiente') || 'homolog' !== bahia_ambiente()) return;
```

E `bahia_ambiente()` decide **lendo o `siteurl` do banco**.

**Junte as duas coisas.** Restaure o banco de produção em homolog — que é a operação de rotina
deste projeto — e o `siteurl` passa a ser `https://bahia.ba`. A função devolve `producao`. **As
guardas retornam antes de registrar qualquer filtro: desligam-se sozinhas, sem erro, sem aviso,
sem nada no log.**

E desligam-se **no pior instante possível**: logo depois de homolog receber a `wp_as3cf_items` de
produção inteira, apontando para os mesmos objetos que o site no ar serve, no mesmo bucket sem
versionamento que já custou nove arquivos.

**A guarda dependia do banco. O perigo chega pelo banco.**

### Caso B — a guarda que não podia viajar (02/09/2026)

Para tirar homolog dos buscadores, o caminho óbvio era gravar `blog_public = 0` em `wp_options`.
**Não foi o que fizemos:**

```php
add_filter('pre_option_blog_public', '__return_zero');   // filtro, nao UPDATE
```

Porque o dado protegido — o valor da opção — **viaja no dump**. Um dump de homolog restaurado em
produção levaria `blog_public = 0` junto e **tiraria o bahia.ba do Google, em silêncio**. Por
filtro isso é impossível: o valor no banco continua `1`, e o comportamento vive no **código**, que
não viaja em dump nenhum.

### O critério, e como usá-lo

| | Caso A | Caso B |
|---|---|---|
| Onde mora a guarda | **no banco** (`siteurl`) | **no código** (filtro em mu-plugin) |
| Onde mora o perigo | no banco (dump restaurado) | no banco (valor da opção) |
| Viajam juntos? | **sim** → guarda falha **aberta** | **não** → guarda se mantém |

**Antes de escrever qualquer guarda, pergunte por onde o perigo chega — e ponha a guarda em outro
lugar.** Guarda e perigo no mesmo veículo não é redundância: é uma guarda que só funciona
enquanto não é necessária.

---

## 🔁 Um mecanismo contraintuitivo que vai ser esquecido: `noindex` antes de `Disallow`

> ### `Disallow` impede o rastreador de **ver** o `noindex`.

Para tirar um site do índice de busca, o instinto é `Disallow: /` no `robots.txt`. **Numa página
que já está indexada, isso congela o problema em vez de resolver.**

```
Disallow    -> proibe BUSCAR a pagina
                    |
                    v
            o rastreador nunca carrega a pagina
                    |
                    v
            nunca ve o <meta robots="noindex">
                    |
                    v
            o que ja esta no indice FICA — as vezes por meses
```

**A ordem correta, e ela é sequencial:**

1. **`noindex` primeiro**, com o rastreamento **ainda permitido** — para o buscador visitar, ler a
   diretiva e **remover** o que já indexou;
2. **`Disallow` depois**, quando as páginas tiverem saído do índice, aí sim para cortar a carga de
   rastreamento.

**Aplicado em 02/09/2026 em homolog**, que já tinha home, `/entretenimento/`, `/justica/`,
`/politica/` e `/dende-e-poder/` indexadas. O passo 1 está feito
(`mu-plugins/bahia-homolog-noindex.php`); **o passo 2 é tarefa própria**, e exige mexer no nginx —
`location = /robots.txt` não tem `try_files`, então a URL nunca chega ao PHP.

> **Só se pula o passo 1 quando nada da origem foi indexado ainda.** Aí `Disallow` sozinho basta,
> porque não há o que remover.

---

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

### 16.11 🔴 Contar não é conferir — e o filtro de ESCOPO nunca é o filtro de SELEÇÃO

**Registrado em 02/09/2026**, na limpeza dos arquivos de teste no bucket `static.bahia.ba`, que é
**compartilhado entre homolog e produção**. É o §16 na forma mais perigosa desta lista: **os
outros descartam dado; este teria apagado dado de produção.**

#### O erro de contagem

No fechamento do lote 3 anotei, com números:

> *"Em 01/09 o lote anterior deixou 13 objetos"* — prefixo `2026/09/01213929`.

**Não era.** Aquele prefixo é `Congresso-Nacional-MP-das-blusinhas.png` — **matéria publicada**.
Eu tinha rodado `aws s3 ls | wc -l`, visto **13**, e casado com o "13 objetos de teste" que o
registro de 01/09 mencionava. **O número batia por coincidência**: uma imagem editorial gera as
mesmas 13 derivadas que a minha imagem de teste.

O resíduo verdadeiro estava em `2026/09/01053739`, e só apareceu quando **li os nomes**.

> **Uma contagem que confere não prova que você olhou o que contou.** O `wc -l` respondeu a
> pergunta *"quantos objetos há aqui?"* com exatidão — e eu usei a resposta para decidir
> *"isto é meu?"*, que é outra pergunta.

#### A regra que sai disso, e ela é operacional

Na hora de apagar, o critério óbvio era **a data**: *"os prefixos de hoje são os meus"*. Medido
antes de executar:

```
prefixos sob 2026/09/ com a data de HOJE : 19
   meus (100% dos objetos com nome teste-*)  : 11
   upload editorial publicado no mesmo dia   :  8   <- WhatsApp, desembargador-2/3/4,
                                                      MERETRIZES-..., Maglore_..., image-...
```

**"De hoje" teria destruído 99 objetos de mídia de produção.**

> ### Em remoção, a DATA é filtro de ESCOPO. O NOME é filtro de SELEÇÃO.
>
> A data limita onde procurar. **Quem decide o que entra é uma propriedade do próprio objeto** —
> aqui, o prefixo do nome de arquivo, exigindo **100% dos objetos do prefixo** batendo com o
> padrão. Prefixo com um único arquivo fora do padrão **não entra inteiro**.

#### O portão que fechou

Mesmo com o critério certo, a execução passa por contagem declarada antes:

```
esperado apagar : 146 objetos, 11 prefixos, 4.208.529 bytes
apagado         : 146
restante nos 11 : 0
producao (8)    : 99 -> 99, INTACTOS      <- recontar o que devia FICAR, nao so o que saiu
```

E depois, conferir no **efeito**, não no bucket: site em 200 e imagens de produção respondendo no
CloudFront, uma a uma.

**O passo que mais importa é o penúltimo.** Contar o que saiu prova que você apagou; **recontar o
que ficou é o único passo que prova que você não apagou demais.**

#### E a autorização é por operação, nunca por padrão

Acrescentado depois do lote 5, e é regra, não preferência:

> ### Autorização para apagar em bucket de PRODUÇÃO vale para **aquela** remoção. Não vira padrão.

Ao longo do dia foram **três** remoções — os prefixos de teste do fim do lote 4, o resíduo de
01/09, e o do lote 5 — e **cada uma foi pedida e concedida separadamente**, com o portão inteiro
refeito. A tentação de tratar a terceira como "já autorizado, é o mesmo gesto" é exatamente o que
transforma um procedimento conferido num hábito não conferido.

**O que sustenta a regra é o §16.11 acima:** o critério que parecia óbvio (a data) estava errado,
e só o inventário item a item mostrou isso. **Um gesto que precisou de inventário para estar
certo não pode ser repetido de memória.**

### 16.12 🔴 Três sinais concordantes, e todos irrelevantes — a concordância dá a confiança

**Errei isto em 02/09/2026, no lote 6, e a correção só apareceu no lote 7 — depois de o erro ter
derrubado homolog.**

Ao validar o Yoast 28.4, precisava responder *"a atualização disparou reindexação em segundo
plano?"*. Li três opções:

```
wpseo_indexation_started            : false
wpseo_indexables_indexation_reason  : false
wpseo_unindexed_post_count          : false
```

**Três sinais, todos negativos, todos concordando.** Escrevi *"nenhuma reindexação foi
disparada"* e segui.

**A indexação estava rodando o tempo todo.** Encontrada no `PROCESSLIST` do lote 7: cinco cópias
empilhadas do anti-join do `wpseo_indexable_index_batch`, de 13 a 28 minutos cada, varrendo
`wp_posts` (435 mil linhas, 1,1 GB) num `buffer_pool` de **128 MB**.

#### Por que as três estavam certas e mesmo assim eu errei

As três opções marcam **uma reindexação em massa iniciada pela interface** — o botão "otimizar
dados de SEO" do painel. Nenhuma delas fala do **cron de fundo**, que é outro caminho, tem outro
gatilho e outro estado. **Elas respondiam com exatidão a uma pergunta que eu não estava fazendo.**

> ### É pior que um sinal ausente, e é por isto:
>
> Se eu não tivesse achado nenhuma opção, teria procurado outra evidência — o `PROCESSLIST`, o
> agendamento, o log. **A concordância entre três leituras substituiu a busca por evidência.**
> Três respostas iguais parecem confirmação cruzada; aqui eram **a mesma resposta repetida três
> vezes**, porque as três vinham da mesma origem e do mesmo conceito.
>
> **Sinais independentes confirmam. Sinais irmãos só ecoam.** Antes de tratar concordância como
> confirmação, perguntar se as fontes podem errar de formas diferentes — se não podem, é uma
> fonte só.

#### O que teria respondido a pergunta certa

```sql
SELECT ID, TIME, INFO FROM information_schema.PROCESSLIST WHERE COMMAND <> 'Sleep';
```

E, mais barato ainda, duas linhas de PHP:

```php
wp_next_scheduled('wpseo_indexable_index_batch');   // o evento esta na fila?
has_action('wpseo_indexable_index_batch');          // e tem quem o execute?
```

**Eu chequei o estado declarado; a pergunta era sobre o trabalho em execução.** Ver o §16.4 — a
mesma família, mecanismo novo.

### 16.13 🔴 Interromper o `kubectl exec` NÃO mata a consulta no servidor

**Descoberto no mesmo incidente.** Duas consultas minhas, `post_title LIKE 'Teste%'` sobre 435 mil
linhas, foram interrompidas quando o `kubectl exec` estourou o tempo. **Elas continuaram
rodando** — 20 e 23 minutos, achadas depois no `PROCESSLIST`, arrastando 1,1 GB por um
`buffer_pool` de 128 MB e esfomeando o resto do banco.

**O cliente sai; o trabalho fica.** É o mesmo padrão do túnel SSH que cai no meio da migração: a
sessão morre, o processo remoto não. E há um agravante — **ao perder o terminal, perdi o
identificador da consulta**, que só se recupera pelo `PROCESSLIST`.

| | |
|---|---|
| **O que não funciona** | `Ctrl-C`, `timeout`, o estouro do `kubectl exec`, fechar o terminal |
| **O que funciona** | `KILL QUERY <id>`, com o `id` lido do `information_schema.PROCESSLIST` |

**A prevenção é anterior:** não rodar varredura de tabela grande num banco pequeno. As duas
consultas existiam só para achar um post pelo título — havia o `ID`, que é chave primária, e eu
usei o `LIKE`. **A consulta certa teria custado milissegundos.**

### 16.14 🔴 O `200 OK` mentiu — e o título correto mentiu junto

**03/09/2026, lote 10.** O update do tema Newspaper apagou do disco os três plugins do tagDiv.
Restaurei o tema e as opções, medi, e **declarei o site restaurado**. Os plugins ainda estavam
apagados.

O que eu vi, e por que cada sinal era compatível com um site quebrado:

| Sinal | Leitura | Por que não provava nada |
|---|---|---|
| `http 200` | ✅ | O WordPress **ignora** `include` de plugin ausente — não é erro, é ausência |
| `h1` = "Política" | ✅ | Vem do `archive.php` do **tema**, que eu tinha acabado de restaurar |
| tempos normais | ✅ | Página menor renderiza **mais rápido**, não mais devagar |

O real só apareceu com um número absoluto:

```
quebrado :  131.475 bytes,  24 td_block
inteiro  :  572.257 bytes, 241 td_block
```

**A home servia 23% do seu tamanho respondendo 200.** E note o terceiro sinal: a velocidade
melhorou **por causa** do defeito. Um site quebrado é mais rápido — a métrica não só falhou em
acusar, ela apontou para o lado errado.

> **Para página montada por blocos, a medida é o TAMANHO DO HTML contra a linha de base. O código
> HTTP não mede montagem, mede entrega.**

É o §16.12 outra vez — sinais concordantes e todos irrelevantes — com o agravo de que **a regra já
estava escrita no topo deste documento e eu ainda parei no primeiro verde**. A diferença entre ter
a regra e aplicá-la é ter, antes de medir, decidido **qual número refutaria** o que eu quero
concluir.

---

### A regra que fica

Toda medição precisa de um **portão de contagem**: quantas linhas entraram, quantas saíram, e
quantas foram descartadas e por quê. Sem isso, o instrumento silencioso vira o resultado.
Isto vale também para `grep -o` com regex de contexto largo (`.{0,150}`), que em arquivo de
centenas de KB entra em backtracking e estoura o tempo em vez de responder — usar Python.

**E o §16.11 acrescenta a metade que faltava:** o portão conta **o que saiu** e **o que ficou**.
Numa medição, contar errado dá um número errado. **Numa remoção, contar errado apaga o que não
devia** — e é por isso que ali o critério de seleção tem de ser uma propriedade do objeto (o
nome), nunca o filtro que apenas delimitou onde olhar (a data).

---

## 17. Fixar configuração para controlar um risco pode criar outro

**Aprendido em 29/08/2026, no encadeamento que quase entrou em produção sem ser visto.**

A sequência, em quatro passos, cada um defensável sozinho:

1. A instância de teste subiu para 8.4.9 e mediu **empate** com o 8.0.42. Bom resultado.
2. Só que quatro padrões tinham mudado e o parameter group de 4 linhas não cobria. O mais sério:
   **`innodb_buffer_pool_instances` caiu de 8 para 1** — disputa de mutex sob concorrência alta,
   que a sonda de 10 conexões não pegaria.
3. **Fixamos `instances=8`.** Decisão certa e pelo motivo certo: as 4 linhas existiam para deixar
   **só a versão** como variável, e deixar esse parâmetro mudar seria testar duas coisas ao mesmo
   tempo.
4. **E foi aí que a fixação criou um risco novo.** O MySQL exige que `buffer_pool_size` seja
   múltiplo de `chunk_size × instances`. Com `instances=8` isso vira 1 GiB, e o pool de
   **11,25 GiB não é múltiplo de 1 GiB** — então o MySQL **arredondou para cima, sozinho, até
   12,00 GiB**.

**O verde nasceria com 1 GiB de buffer pool a mais que a produção** — numa instância que opera
com **1,97 GiB de memória livre**. O parâmetro posto para *remover* uma variável teria comido
**metade da folga de memória de produção**, e a comparação 8.0 × 8.4 passaria a falar de cache,
não de motor.

### O que fez a diferença: medir o efeito, não presumir a intenção

O `+0,75 GiB` **não aparece em lugar nenhum da API**. `describe-db-parameters` mostra
`innodb_buffer_pool_instances = 8`, que foi o que se pediu, e nada mais. O
`ParameterApplyStatus` diz `in-sync`. **Tudo indica sucesso.**

Só apareceu porque a instância foi **reiniciada e o valor em execução foi lido**:

```
antes  (instances=1):  12.079.595.520 = 11,25 GiB
depois (instances=8):  12.884.901.888 = 12,00 GiB   <- ninguem pediu isto
```

E o motivo de haver reboot foi outro: verificar se o parâmetro **estático** aplicava mesmo. A
descoberta veio de uma verificação feita por desconfiança de outra coisa.

### A regra

> **Fixar configuração não é automaticamente conservador.** Um parâmetro fixado interage com
> outros, e o motor pode ajustar um terceiro valor **sem erro, sem aviso e sem aparecer na API**
> para satisfazer a restrição nova.
>
> **Toda fixação de parâmetro precisa do seu próprio "depois":** reiniciar e ler os valores **em
> execução**, não os declarados. `in-sync` responde "o grupo foi aplicado", que é uma pergunta
> diferente de "o motor está rodando com os valores que eu quis" — é o §16 outra vez, agora em
> configuração em vez de medição.

A correção foi fixar também `innodb_buffer_pool_size = 11811160064` (11,00 GiB, o valor exato da
produção — e múltiplo de 1 GiB, então não arredonda). **Verificada do mesmo jeito:** reboot, e
leitura do valor em execução. Assentou exato.

**E a correção trouxe a sua própria contrapartida**, registrada em destaque no §1.2 do
`UPGRADE-MYSQL.md`: um valor absoluto em bytes **amarra o parameter group à `db.m5.xlarge`**.
Três passos, três riscos, cada correção com o seu. O que não se pode é parar de medir no meio.

---

## 18. `COUNT(*)` não aquece tabela no InnoDB

**Descoberto em 29/08/2026**, aquecendo o verde do Blue/Green. É o padrão do §16 aplicado a
**preparação** em vez de medição: um instrumento que parece fazer o trabalho e não faz.

```sql
SELECT COUNT(*) FROM wp_posts;     -- NAO aquece a tabela
```

**O InnoDB conta pelo MENOR índice disponível**, que quase sempre é um secundário. Ele lê as
folhas desse índice e **nunca toca as páginas de dado**. Num banco restaurado de snapshot, onde
os blocos vêm do S3 sob demanda, isso significa que o dado continua frio depois de a passada de
aquecimento ter "terminado com sucesso".

**O sintoma:** o buffer pool empacou em **0,56 GiB e não subiu**, por três passadas seguidas.
Nenhum erro. As consultas responderam. Os tempos até melhoraram entre a 1ª e a 2ª passada — o
suficiente para parecer que tinha aquecido.

### O que aquece de verdade

```sql
-- 1) as paginas de DADO: o indice agrupado E a tabela
SELECT COUNT(*) FROM `t` FORCE INDEX (PRIMARY);

-- 2) os caminhos ate ela: cada indice secundario, um a um
SELECT COUNT(`primeira_coluna_do_indice`) FROM `t` FORCE INDEX (`nome_do_indice`);
```

Medido no verde, mesma instância, passadas em sequência:

| Passada | Pool depois | Leituras físicas |
|---|---|---|
| `COUNT(*)` simples, 3× | **0,566 GiB** — empacado | — |
| `FORCE INDEX (PRIMARY)` nas tabelas > 1 MB | **2,686 GiB** | 72.422 |
| 47 índices secundários, um a um | **2,956 GiB** | 13.110 |
| repetição das duas anteriores | 2,956 GiB (**+0,000**) | **0** |

**Sem a passada com `FORCE INDEX (PRIMARY)`, o verde iria para a troca com o dado ainda no S3 e a
ilusão de estar aquecido** — que é exatamente o modo de falha da virada de 18/08.

**Os secundários importam tanto quanto:** a busca, o archive e a ordenação por data passam por
eles. Um pool com o dado e sem os caminhos até ele ainda paga E/S na primeira consulta real.

Scripts: `scratchpad/aquece-total.php` e `scratchpad/aquece-indices.php`.

**E o portão que confirma não é percentual nenhum:** é **repetir a passada e ver as leituras
físicas irem a zero**. Percentual exige escolher um número e um denominador — e o denominador
errado já custou um portão inteiro (ver `UPGRADE-MYSQL.md`, Fase D, o portão dos 95%).

---

## 19. CIDR de VPC pode colidir com a rede interna da AWS — e o `PROCESSLIST` não distingue

**Quase custou caro em 29/08/2026**, ao desenhar o security group novo do banco de produção,
minutos antes da troca do Blue/Green.

O `PROCESSLIST` do banco azul mostrava:

```
10.1.4.241    rdsrepladmin    <- a replicacao vinda do verde
```

E a regra do security group era:

```
tcp 3306 <- 10.1.0.0/16, 10.2.0.0/16, sg-0614f9d2cf0b6c697
```

**`10.1.0.0/16` é o CIDR do EKS de homolog.** A leitura óbvia — *"homolog está conectado ao banco
de produção"* — está errada, e a leitura seguinte — *"então posso remover essa regra, homolog não
precisa disto"* — poderia ter cortado a replicação do verde **no meio da operação**.

### A prova está no ENI, não no IP da conexão

| Instância | ENI na VPC | `@@hostname` |
|---|---|---|
| Produção (azul) | **172.31.70.197** | `ip-10-1-4-202` |
| Verde | **172.31.70.50** | — |
| Homolog | **172.31.50.61** | `ip-10-1-1-218` |

**Prod e homolog estão os dois na `vpc-4c49202b` (172.31.0.0/16) e ambos reportam `@@hostname` em
`10.1.x`.** Esse `10.1.x` é a **rede de gestão da AWS**, não a VPC de homolog. O CIDR escolhido
para o EKS de homolog caiu em cima dela por coincidência.

### As duas lições

1. **O IP que aparece numa conexão não identifica de qual rede ela veio.** Bancos gerenciados
   apresentam endereços da infraestrutura do provedor. **Conferir o ENI** (`describe-network-interfaces`)
   antes de concluir qualquer coisa sobre origem.
2. **Escolher CIDR de VPC sem verificar colisão com o provedor cria ambiguidade permanente.**
   Não quebra nada sozinho — cria a condição para alguém ler errado, e errar numa regra de firewall.

### E o que se faz quando a dúvida não se resolve a tempo

**Não se mexe durante a operação.** Não deu para determinar de que lado o security group avalia a
conexão de replicação, e a janela não é hora de descobrir. A mudança de SG foi adiada para
**depois da troca**, quando a replicação já cumpriu o papel e o comando ficou sem consequência.

Aplicada às 06:08:19 e concluída às 06:09:32 — **sem uma falha na sonda**. Adiar custou 19 minutos
e removeu o risco inteiro.

---

## 20. Para cobrir um intervalo, sonda contínua vale mais que amostra em marcos

**29/08/2026.** O plano da virada previa portão de carga **aos 0, 5 e 15 minutos**. O marco dos 5
minutos passou enquanto se aguardava uma decisão, e a medição saiu aos 12.

**O intervalo não ficou descoberto** — porque havia uma sonda rodando o tempo todo, de segundo em
segundo, com reconexão a cada tentativa:

```
cobertura : 05:48:48 -> 06:12:14 UTC   (23,4 minutos)
amostras  : 1.392      ok : 1.392      FALHAS : 0
```

Ela cobriu a troca do Blue/Green, os três portões de carga **e** a alteração de security group.

**Um marco prova o instante dele e mais nada.** Se a indisponibilidade tivesse acontecido aos 3
minutos, ou aos 8, os portões de 0/5/15 passariam todos e não veriam. A sonda contínua vê.

**A regra:** marcos servem para **decidir** (portão que dispara rollback, com carga sintética e
critério em número). Cobertura de intervalo é da **sonda contínua**, que roda do antes ao depois
e não depende de o relógio da operação bater com o relógio de quem mede.

E a sonda contínua é barata: um laço de `SELECT 1` com reconexão, um pod, um arquivo TSV.
`scratchpad/indisponibilidade.php`.

---

## 21. `imagePullPolicy: Always` com tag flutuante torna a troca de código invisível ao Kubernetes

**Descoberto em 29/08/2026**, ao preparar a subida do PHP — e é o achado mais importante da
rodada.

> **`imagePullPolicy: Always` com tag flutuante não significa apenas que o pod pode puxar código
> novo — significa que o CONTEÚDO pode trocar sem o Kubernetes registrar nada. Sem `generation`,
> sem `ReplicaSet` novo, sem histórico de rollout. A mudança é invisível para o próprio
> orquestrador, e é por isso que fixar SHA obriga a um rollout: é a primeira vez que o cluster
> está sendo informado de que o conteúdo mudou.**

### Como isso apareceu

O `Deployment` de produção declarava `bahia-wordpress:prod-latest` nos dois contêineres, com
`imagePullPolicy: Always`. Ao propor trocar para o SHA imutável equivalente
(`prod-804c68f0…`, que é **exatamente a mesma imagem**, resolvida por digest no ECR), eu afirmei
que não haveria rollout, "porque a imagem resultante é idêntica à que já está no ar".

**Errado.** `kubectl diff` contra os dois clusters, antes de qualquer push:

```
homolog:  generation 121  -> 122
prod:     generation 3697 -> 3698
```

**O Kubernetes não compara conteúdo — compara a string da imagem.** Mesma imagem, string
diferente, `podTemplate` diferente, `pod-template-hash` diferente, `ReplicaSet` novo, rollout.

### E a inversão é o que assusta

| | Muda o conteúdo? | O Kubernetes registra? |
|---|---|---|
| Novo build empurrado para `prod-latest`, pod reinicia | **SIM** | **NÃO** — nenhum evento, nenhuma `generation` |
| Trocar a tag para o SHA da **mesma** imagem | **NÃO** | **SIM** — rollout completo |

**O orquestrador registra exatamente o caso errado.** A troca real de código passa sem rastro; a
troca cosmética gera evento. Quem auditar `kubectl rollout history` para saber "o que mudou e
quando" vai ler a lista errada.

### A regra

**Tag mutável em produção não é conveniência, é perda de rastreabilidade.** Com ela:

- `kubectl rollout history` não sabe o que rodou;
- `kubectl rollout undo` volta para um `ReplicaSet` cuja tag pode apontar para outra coisa hoje;
- e um `kubectl apply` desfaz em silêncio qualquer `set image` por SHA.

**Endereçar por SHA — ou por digest — é o que faz o cluster saber o que está rodando.** O preço é
um rollout na transição, e ele se paga uma vez só.

### O corolário operacional que ficou

**Fixar o SHA no manifesto obriga a escolher a hora.** Em 29/08 a mudança foi feita **só em
homolog**: produção tinha acabado de virar o banco para o MySQL 8.4.9, as cinco réplicas estavam
com `fastcgi_cache` quente (`emptyDir`, perdido em qualquer rollout), e jogá-las em cache frio
pela família do modo de falha de 18/08 não se pagava por uma proteção que só age se alguém
empurrar em `kubernetes/**`.

**Produção é fixada no momento do deploy do PHP — que vai rolar os pods de qualquer jeito — e com
o SHA ANTERIOR ao PHP**, para que um `apply` acidental durante a validação seja um caminho de
volta, e não de ida.

---

## 22. Agregação que apaga a estrutura — o total mente quando a informação está na distribuição

**Errei isto em 29/08/2026**, medindo a indisponibilidade de um rollout em homolog. Variante
própria do §16, com mecanismo novo: o instrumento **não descarta dado** — ele **resume** dado, e
o resumo apaga a estrutura que continha a resposta.

O script pegava o primeiro erro e o último restabelecimento e chamava a diferença de
indisponibilidade:

```
ultima resposta boa: 06:59:59
primeira que voltou: 07:01:18
INDISPONIBILIDADE REAL DO ROLLOUT: 79s     <- ERRADO
```

**79 s é um número perfeitamente plausível** para um rollout com `maxSurge: 0`. Nada nele chama
atenção. A verdade estava na distribuição:

| | janela | duração |
|---|---|---|
| Queda 1 | 07:00:04 → 07:00:04 | **1 s** |
| *(serviço normal)* | | *44 s* |
| Queda 2 | 07:00:49 → 07:01:13 | **25 s** |
| **Soma real** | | **26 s** |

**Foram duas quedas, com 44 segundos de serviço normal entre elas.** O "79 s" somava o intervalo
em que o site estava no ar.

E a diferença não é acadêmica: 26 s de queda em dois blocos e 79 s de queda contínua são
**eventos operacionais diferentes**. Um deles indica que a queda de 1 s às 07:00:04 é anterior ao
deploy e não faz parte dele — informação que o total destrói.

### Por que escapou, e o que salvou

**Escapou** porque o cálculo `primeiro_erro → último_restabelecimento` está certo *quando há uma
queda só*, que é o caso comum. Ele só mente quando há mais de uma — e ninguém testa o caso raro.

**Salvou** porque a saída também listava os blocos, e dava para ver que os dois não eram
contíguos. **Se o script imprimisse só o total, eu teria reportado 79 s e ninguém saberia.**

### A regra

> **Antes de somar, mostre a distribuição.** Toda métrica agregada — total, média, "tempo até" —
> precisa vir acompanhada da estrutura que a gerou: quantos blocos, de que tamanho, com que
> intervalo entre eles.
>
> A pergunta do §16 era *"o que este instrumento responderia se a coisa que ele deveria detectar
> estivesse acontecendo agora?"*. A desta seção é outra: **"que estruturas diferentes produzem
> este mesmo número?"** Se mais de uma, o número sozinho não é resposta.

Vale para tudo que este projeto mede: pico de `Threads_running` (um pico de 3 s e um platô de
3 min dão o mesmo "pico"), mediana de tempo de resposta, e contagem de erro em janela.

---

## 23. Aviso em ambiente novo não significa nada sem contrafactual

**Método aplicado em 29/08/2026**, validando o PHP 8.3 em homolog.

Depois da subida, o log do pod acusou **6 `PHP Warning`** em 25 minutos:

```
PHP Warning: Attempt to read property "user_nicename" on false
  em co-authors-plus/php/class-coauthors-plus.php:1193
PHP Warning: Cannot modify header information - headers already sent
  em puredevs-gdpr-compliance/public/class-pd-gdpr-public.php:356
```

**Sozinhos, esses seis avisos são indistinguíveis de regressão.** Apareceram *depois* da mudança,
citam plugins reais, e o PHP 8.3 é exatamente o tipo de coisa que produz aviso novo. A conclusão
"o 8.3 quebrou o Co-Authors Plus" é a leitura natural — e está errada.

**O que resolveu foi olhar o mesmo aviso em PRODUÇÃO, que ainda estava em PHP 8.2:**

| Origem | Homolog (PHP **8.3**, 25 min) | **Produção (PHP 8.2, 60 min)** |
|---|---|---|
| `co-authors-plus:1193` | 2 | **90** |
| `puredevs-gdpr:356` | 2 | **5** |
| `wp-smushit:171` | 0 | 3 |
| **Fatais / depreciações** | 0 / 0 | 0 / 0 |

**Produção, na versão ANTIGA do PHP, tem mais avisos que homolog na nova** — proporcional ao
tráfego. Os avisos são pré-existentes, e o 8.3 não introduziu um único novo.

### A regra

> **Um aviso em ambiente novo só significa alguma coisa comparado com o mesmo ambiente ANTES, ou
> com o outro ambiente AGORA.** Sem um dos dois, ele é ruído com aparência de sinal.

**O contrafactual mais barato é o ambiente que ainda não mudou.** Numa subida faseada — homolog
primeiro, produção depois — o ambiente atrasado *é* o grupo de controle, e ele existe de graça
por algumas horas. **Colher a linha de base dele antes de subir custa um comando.**

### O caso simétrico, que é pior

Se o ambiente antigo tem **muitos** avisos e o novo tem **poucos**, a leitura ingênua é "melhorou".
Pode ser só que o novo recebeu menos tráfego. **Normalizar por volume** — aqui, 90 em 60 min de
produção contra 2 em 25 min de homolog, com ordens de grandeza de tráfego diferentes — é o que
impede a conclusão fácil nos dois sentidos.

---

## 24. Limiar que converte latência em falsa indisponibilidade — e apaga a prova

**Errei isto em 29/08/2026**, medindo o rollout do pino de SHA em produção. É o pior tipo de
defeito de instrumento catalogado até aqui: **ele não erra o número — ele destrói o dado que
mostraria o erro.**

A sonda usava `curl --max-time 5` e classificava qualquer estouro como falha:

```
07:36:47  http=502  tempo=0.99s     <- erro de verdade
07:39:56  http=000  tempo=5.01s     ┐
07:40:10  http=000  tempo=5.00s     │
   ... mais 9 iguais ...            │  TODAS coladas no teto de 5s
07:44:53  http=000  tempo=5.01s     ┘
```

Reportei **12 falhas** num rollout que, por desenho (`maxSurge: 1` / `maxUnavailable: 0`), não
deveria ter indisponibilidade nenhuma.

**Onze das doze eram requisições mais lentas que 5 segundos**, não requisições sem resposta. O
site estava no ar, com cache frio depois de o HPA escalar de 3 para 5 pods. A mediana das que
responderam era 2,34 s, com p90 de 2,73 s — o teto de 5 s parecia folgado e não era, na janela
de aquecimento.

### O que torna este defeito pior que os outros

Os defeitos do §16 **descartam** dado: o `xargs` engole linhas, a amostragem colapsa, o
`carga.sh` gravava em diretório inexistente. O dado some, mas o mundo continua lá para ser
medido de novo.

**Este apaga a informação na origem.** A sonda desistiu aos 5 s, então **não existe forma de
saber, depois, se aquelas requisições responderiam em 6 s ou nunca responderiam.** A pergunta
"foi lentidão ou foi queda?" ficou permanentemente sem resposta para aquela janela.

E o número que ele produz é **plausível**: "12 falhas durante um rollout" não chama atenção. Só
apareceu porque a saída trazia o campo `tempo`, e os onze estavam todos em 5,00–5,01 s — um
agrupamento que não acontece por acaso. **Se a sonda gravasse só o código, teria passado.**

### O desenho certo, e a regra

```bash
# --max-time GENEROSO: define o que e "sem resposta"
# LIMIAR separado: define o que e "lento", e lento continua sendo RESPOSTA
--max-time 30        limiar_lento=5
classes: ok | lento | erro | timeout
```

> **Timeout de cliente é uma decisão sobre o que se considera queda, não sobre paciência.**
> Se o limiar de "lento" e o limite de "sem resposta" forem o mesmo número, o instrumento
> deixa de distinguir degradação de indisponibilidade — e, pior, **destrói a evidência** que
> permitiria separá-las depois.
>
> Sempre que houver um limiar que descarta a observação, pergunte: **o que eu perco para sempre
> quando ele dispara?**

Corrigido em `scratchpad/sonda-http.sh`. Em homolog o defeito não tinha aparecido porque lá as
falhas eram `503` genuínos do ALB sem alvo saudável (`maxSurge: 0`, uma réplica); em produção,
com `maxSurge: 1`, sempre há alvo saudável — então o que a sonda encontrou foi latência.
**O mesmo instrumento, correto num ambiente e enganoso no outro, pela diferença da estratégia de
rollout.**

---

## 25. "Zero" numa métrica gerenciada é zero **do que ela conta**

**Onde apareceu.** Auditoria do `rds-bahiaba-2023-old1` antes de removê-lo, 01/09/2026.

A métrica `DatabaseConnections` do CloudWatch deu **0 em 871 de 871 amostras** cobrindo as 72 h
desde a troca. Máximo global zero. É um número muito confortável, e eu quase o reportei como
"ninguém conectou".

**O contador do próprio servidor discordava.** Numa sonda de conexão única — em que qualquer
incremento é, por construção, de terceiros — o `Connections` global subiu **+3 em 300 s**, e
repetiu **+3 em 315 s** numa segunda corrida independente. Cerca de 860 conexões por dia que o
CloudWatch simplesmente não mostra.

**As duas medidas estão certas.** Elas contam populações diferentes: o CloudWatch não inclui as
conexões de gerência da própria RDS (`rdsadmin@localhost`), e eram exatamente essas. A
consequência prática ficou visível também na escrita: o `WriteIOPS` nunca era zero — 0,567 de
média — porque a AWS atualiza `mysql.rds_heartbeat2` continuamente. O valor da tabela avançou
`1788244346032 → 1788244661032`, **exatamente os 315 s decorridos**.

> **Uma métrica gerenciada responde a pergunta dela, não à sua.** "Zero conexões" no painel
> significa "zero conexões da categoria que este gráfico desenha". Antes de tratar um zero como
> ausência, pergunte **quem foi excluído da contagem** — e confirme no contador do servidor, que
> não filtra nada.

O que fechou a questão não foi o gráfico: foi o `PROCESSLIST` amostrado a 1 Hz por 300 s, que
mostrou **300/300 amostras com apenas `rdsadmin@localhost` e `event_scheduler@localhost`, e
nenhum cliente externo**. Esse instrumento vê tudo, inclusive o que a métrica esconde.

---

## 26. Tabela vazia não é ausência — pode ser instrumento desligado

**Onde apareceu.** Mesma auditoria, ao responder "quem conectou".

Consultei `performance_schema.accounts`, que acumula `TOTAL_CONNECTIONS` por usuário e host. Ela
respondeu **sem erro** e voltou **vazia**. A leitura tentadora é "nenhuma conta conectou".

A leitura certa estava a uma variável de distância:

```
performance_schema               = 0     <- DESLIGADO
performance_schema_accounts_size = 0
linhas em performance_schema.accounts = 0
```

**O Performance Schema está desligado nesta instância** (parameter group antigo, `mysql80-edit`).
A tabela existe, aceita `SELECT`, devolve zero linhas — e zero linhas ali significa "não há
instrumentação", não "não há conexões". Pior: `performance_schema.global_status` **continua
funcionando** com o P_S desligado, porque é alimentada pelas variáveis de estado do servidor e não
pela instrumentação. Então parte das consultas ao mesmo schema respondia com dado bom, o que
reforça a impressão errada de que o subsistema está de pé.

> **Resultado vazio é uma afirmação sobre o instrumento antes de ser sobre o mundo.** Antes de
> concluir "não houve", verifique se o que mediria "houve" estava ligado. Um `SELECT` que devolve
> zero linhas sem erro é o formato mais convincente que a falta de medição pode assumir.

Parente do §16.7 (`DATA_FREE` devolvendo o valor de antes do `OPTIMIZE`) e do §24 (o limiar que
apaga a prova): as três são falhas em que **o instrumento respondeu, e a resposta não era sobre o
que eu perguntei**.

---

## 27. Omitir uma flag destrutiva pode ser a escolha destrutiva

**Onde apareceu.** Remoção do `rds-bahiaba-2023-old1`, 01/09/2026.

Rodei `delete-db-instance --skip-final-snapshot` **sem** `--delete-automated-backups`, e anunciei
em voz alta que a omissão manteria os três snapshots automáticos como segunda rede de
recuperação.

**Estava errado. O padrão da API é `DeleteAutomatedBackups = true`.** Não passar a flag **executa**
a remoção dos backups. Os três snapshots do azul saíram junto com a instância:

```
describe-db-instance-automated-backups -> DBInstanceAutomatedBackupNotFound
describe-db-snapshots                  -> DBSnapshotNotFound
snapshots restantes com "old1" no nome: 0
```

**O modelo mental que falhou:** "se eu não passar a flag, nada a mais acontece". Numa API de
remoção esse modelo é perigoso, porque o verbo já é destrutivo e as flags frequentemente escolhem
**o que preservar**, não o que destruir. `--skip-final-snapshot` e `--delete-automated-backups`
parecem simétricas e não são: a primeira precisa ser pedida, a segunda vem ligada.

> **Antes de rodar um comando destrutivo, leia o padrão documentado de cada parâmetro que você
> NÃO está passando.** O nome da flag descreve o que ela faz quando presente — não diz nada sobre
> o que acontece na ausência dela.

**E o segundo erro, que é o que dói:** eu não só apliquei o padrão errado, eu **narrei** a
garantia errada no mesmo fôlego em que rodava o comando, sem ter verificado. Uma afirmação sobre
um ativo de recuperação é do tipo que só é conferida no momento em que se precisa dele — e aí é
tarde. **O que salvou foi verificar depois**, por `describe-db-instance-automated-backups`, que
eu só rodei porque quis confirmar a rede antes de reportar.

Parente do §16.7, §24 e §26: em todos, o instrumento (ou a suposição) respondeu com confiança
sobre algo que não tinha medido. Aqui a lição específica é mais dura, porque **a afirmação não
verificada entrou no relatório antes da medição** — a ordem certa é medir e depois afirmar,
inclusive quando a afirmação parece trivial.

### A regra operacional que sai daqui

> **Em qualquer remoção de instância, LISTAR explicitamente o que sobrevive e o que sai — com o
> comando que prova cada item — ANTES de executar.** Não depois, não "a gente confere no fim".

Não é uma lista mental nem uma frase no relatório: é um bloco escrito, item a item, com o comando
ao lado. O que deveria ter existido antes do `delete-db-instance`:

```bash
# SOBREVIVE (provar antes):
aws rds describe-db-snapshots --snapshot-type manual        # snapshots manuais: permanentes
ls -la ~/BAHIABA-backups/dump-PRODUCAO-*.sql.gz             # dumps logicos locais

# SAI (provar que se sabe que sai):
aws rds describe-db-instance-automated-backups \
    --db-instance-identifier <id>                           # PITR: sai por PADRAO da API
aws rds describe-db-snapshots | grep <id>                   # snapshots automaticos: saem junto
```

**Padrões de API que apagam por omissão são a pior categoria de armadilha, porque o silêncio
significa "sim".** Um comando destrutivo sem flag não é um comando conservador — é um comando que
aceitou todos os padrões de quem escreveu a API, e esses padrões foram escolhidos para o caso
comum, não para o seu. A revisão tem que ser dos parâmetros **ausentes**, que são invisíveis na
linha de comando e por isso não aparecem em nenhuma leitura do que foi digitado.

---

## 28. Um evento isolado durante uma operação parece causado por ela

**Onde apareceu.** Remoção do `rds-bahiaba-2023-old1`, 01/09/2026.

A sonda contínua sobre produção registrou **um 502 às 06:51:16**, dezoito segundos antes do
`delete-db-instance` e três segundos depois do `modify-db-instance`. Isolado, com esse
posicionamento, ele tem todos os traços de causa: proximidade temporal, operação em andamento,
única anomalia da janela.

**A sonda continuou rodando depois que a operação terminou.** E às **07:01:33**, com a instância
removida havia cinco minutos e nada acontecendo, veio **o segundo 502**.

```
DURANTE a remoção (136 amostras):   135 ok  |  1 erro
DEPOIS, sem operação (277 amostras): 276 ok  |  1 erro
```

**O segundo evento é o contrafactual do primeiro.** Ele converte "a remoção causou um 502" em
"produção tem uma taxa de fundo de 0,48% de 502, e um deles calhou de cair durante a operação".
São conclusões opostas a partir do mesmo primeiro dado.

> **A janela de medição tem que ultrapassar a operação, e por uma margem que permita ver o fundo.**
> Uma sonda que começa com a operação e para com ela produz, por construção, um conjunto de dados
> em que **tudo o que aparece está correlacionado com a operação** — não porque seja causado por
> ela, mas porque não há nada fora dela para comparar.

O erro que isto evita é caro nos dois sentidos: atribuir à mudança um defeito que já existia leva
a reverter algo que estava certo; e o inverso, atribuir ao fundo um defeito real, deixa passar
uma regressão. **A única defesa é ter medido o fundo — e o momento mais barato de fazer isso é
deixar a sonda correndo mais quinze minutos.**

Parente do §20 (sonda contínua vence amostra em marcos) e do §23 (aviso em ambiente novo não
significa nada sem contrafactual). O §20 diz *como* medir; este diz *por quanto tempo*.

---

## 29. `maxUnavailable: 0` sem `readinessProbe` não promete o que parece prometer

**Onde apareceu.** Subida do PHP 8.3 em produção, 01/09/2026.

A estratégia do Deployment de produção é `maxSurge: 1, maxUnavailable: 0`. Lida ao pé da letra,
ela diz: *nunca haverá menos réplicas disponíveis que o desejado*. Foi com base nisso que a
expectativa era **zero indisponibilidade** no rollout.

**Medido:**

| Janela | Requisições | Falhas | Taxa |
|---|---|---|---|
| Durante o rollout (3 min 28 s) | 1.459 | **35** | **2,40%** |
| Fora dele | 3.991 | 4 | **0,10%** |

**24× a taxa de fundo**, e a sonda externa a 1 Hz deu 0 erros nas 78 amostras antes e 0 nas 432
depois — os erros são **exclusivos** da janela.

**Por que a promessa não vale.** `maxUnavailable` conta pods **disponíveis** no sentido do
Kubernetes, e sem `readinessProbe` um pod é considerado pronto **assim que o contêiner sobe** —
não quando o processo aceita conexão. O contrato que o campo expressa é sobre a contagem de pods,
e o que o leitor precisa é sobre a capacidade de servir. **Os dois só coincidem se existir uma
prova de prontidão.** Sem ela, `maxUnavailable: 0` é uma afirmação sobre um número, não sobre o
serviço.

E há a outra ponta, igualmente aberta: sem `preStop`, o pod que está sendo derrubado continua
recebendo tráfego enquanto a desregistração no balanceador não termina.

**A prova de que a falha é de conexão, e não da aplicação**, veio de separar quem gerou o erro:

```
HTTPCode_ELB_502_Count     29   <- o balanceador nao obteve resposta do alvo
HTTPCode_Target_5XX_Count   6   <- um pod respondeu 5xx
5xx no log de nginx dos pods novos: 0
```

> **Cuidado com a leitura do log limpo:** nginx **não registra conexão recusada**. O log vazio dos
> pods novos é **consistente** com a falha, não a refuta. Quando o erro é de conexão, a ausência
> de registro é o que se espera — e tratá-la como prova de inocência inverte o sinal.

Foi preciso a métrica do balanceador para atribuir. **`HTTPCode_ELB_5XX` e `HTTPCode_Target_5XX`
respondem perguntas diferentes**, e só a primeira enxerga o erro que nunca chegou à aplicação.

**Regra:** antes de afirmar que um rollout não causa indisponibilidade, verifique se existe
`readinessProbe`. Se não existir, a estratégia declarada no manifesto **não descreve o
comportamento** — e a única medição válida é a sonda externa, com a janela ultrapassando a
operação dos dois lados (§28).

Parente do §21 (o orquestrador registra exatamente o caso errado) e do §26 (resultado vazio é uma
afirmação sobre o instrumento antes de ser sobre o mundo).

---

## 30. Ausência de erro num log que não registra aquele erro não é evidência de ausência

**Onde apareceu.** Atribuição dos 502 do rollout de produção, 01/09/2026.

A sonda externa viu três 502. Fui aos logs do nginx dos pods novos procurando a origem:

```
5xx no log de nginx dos pods novos: 0   (em 3.877 linhas)
```

**Zero.** A leitura tentadora — e eu cheguei a formulá-la — é "não foram os pods novos".

**Mas nginx não registra conexão recusada.** O log de acesso descreve requisições que o nginx
**aceitou**. Uma conexão TCP que nunca foi estabelecida, porque o processo ainda não escutava na
porta, não produz linha nenhuma. **Quando o erro é de conexão, a ausência de registro é
exatamente o que se espera.**

O que resolveu foi a métrica do balanceador, que conta os dois casos separadamente:

```
HTTPCode_ELB_502_Count     29   <- o ALB nao obteve resposta do alvo
HTTPCode_Target_5XX_Count   6   <- um pod respondeu 5xx
```

**29 de 35 nunca chegaram à aplicação.** O log limpo era **consistente** com a falha, não a
refutava.

> **Antes de usar um log vazio como prova, pergunte: este log registraria o evento que estou
> procurando?** Se a resposta for não — ou "depende do modo de falha" —, o vazio não é dado, é
> silêncio. E silêncio não decide nada.

Irmão do §26 (tabela vazia porque o instrumento estava desligado): lá o instrumento não media;
aqui ele mede outra coisa. **Nos dois, o resultado vazio é uma afirmação sobre o instrumento antes
de ser sobre o mundo.**

---

## 31. A mensagem do aviso muda com a versão da linguagem; o defeito não

**Onde apareceu.** Subida do PHP 8.3 em produção, 01/09/2026.

O aviso mais frequente do site, nas duas janelas medidas:

```
PHP 8.2:  Attempt to read property "user_nicename" on bool
PHP 8.3:  Attempt to read property "user_nicename" on false
```

**Mesmo arquivo, mesma linha, mesmo defeito.** O PHP 8.3 passou a nomear o valor (`false`) em vez
do tipo (`bool`).

**A consequência é operacional:** qualquer alerta, filtro de log, painel ou `grep` de rotina
escrito contra a string antiga **para de encontrar o problema no dia da atualização** — e o
silêncio novo parece melhoria. Um defeito que sumiu do painel sem ter sido corrigido é pior do
que um defeito visível, porque ninguém volta a olhar.

> **Ao subir versão de runtime, trate o texto das mensagens de diagnóstico como interface que
> pode quebrar.** Compare as **origens** dos avisos entre as duas versões, não as strings; e
> revise todo alerta que casa por texto literal.

Da mesma família do §16.7 e do §26: o instrumento respondeu, e a resposta não era sobre o que eu
perguntei. Aqui a diferença é que a mudança está do lado do **emissor**, não do medidor.

---

## 32. `kubectl diff` valida o `apply`, não o pipeline

**Onde apareceu.** Fixação do SHA no manifesto de produção, 01/09/2026 — logo depois de escrever
o §27, que é sobre exatamente este erro.

Antes de empurrar a mudança rodei `kubectl diff -f kubernetes/prod/wordpress/deployment.yaml`:
**saída vazia, código 0**. Reportei que aplicar o manifesto não mudaria nada, e empurrei.

**Produção reiniciou inteira.** ReplicaSet novo, e o diff dos dois templates mostrou **um único
campo diferente**: `kubectl.kubernetes.io/restartedAt` — assinatura de `kubectl rollout restart`,
um passo **incondicional** do job de prod no `tf-apply.yml`, que existe para fazer valer mudança
de ConfigMap/Secret e dispara em qualquer push a `kubernetes/**`.

**A afirmação era verdadeira e a unidade era errada.** `kubectl diff` responde "o que este
`apply` mudaria" — e o que eu precisava saber era "o que este **push** vai causar". Entre os dois
existe um workflow com passos que o diff não enxerga.

> **Verifique a unidade que você vai executar.** Se o gesto é um `push`, a pergunta é o que o
> pipeline inteiro faz — não o que faz o comando que você imagina no meio dele. Leia os passos do
> workflow que o caminho alterado dispara, incluindo os **incondicionais**.

Custo medido: **5 requisições de leitor perdidas** (`HTTPCode_ELB_502_Count = 5`,
`Target_5XX = 0`), contra 0 em 1.365 fora da janela.

**É a segunda vez na mesma sessão** que afirmo o efeito de uma operação a partir de um substituto
em vez de medir a operação — a primeira foi o `--delete-automated-backups` do §27. Lá o substituto
foi o nome da flag; aqui, um comando parecido com o que o pipeline roda. **O padrão é o mesmo, e
reconhecê-lo escrito não impediu a repetição: a defesa tem que ser procedimento, não memória.**

---

## 33. Referência de imagem que se move sozinha — a lição vale para TODA camada

**Onde apareceu.** Separação do `Dockerfile` por ambiente, 01/09/2026.

O `FROM` era `wordpress:6.8-php8.3-fpm`. **`6.8` é tag flutuante de minor**: aponta para a última
6.8.x que o mantenedor publicar. É **exatamente o mesmo mecanismo** do `prod-latest` que
corrigimos no §21 — só que **uma camada abaixo**, na imagem base, onde ninguém tinha olhado.

**A simetria é o ponto.** No §21 o problema era nosso: nossa tag flutuante fazia o Kubernetes não
enxergar troca de código. Aqui o problema é de terceiro: a tag do mantenedor pode entregar um core
diferente amanhã, sem que nada no nosso repositório mude. **Um rollback para "o commit de ontem"
reconstrói a imagem e pode trazer um core que ontem não existia.** O git fica idêntico e o
artefato não.

> **Toda referência de imagem que não seja imutável é uma dependência que muda sem commit.**
> Isso vale para a tag que você publica, para a imagem base que você consome, e para qualquer
> `:latest`, `:stable` ou minor flutuante no caminho. A pergunta não é "de quem é a tag" — é
> "esta referência pode apontar para outro conteúdo amanhã?".

**O que fizemos:** fixar a patch exata (`6.8.3`, `7.1.0`), depois de **medir** que os digests eram
idênticos aos das flutuantes no dia (`sha256:906c2572…` e `sha256:5a9cee04…`) — então a fixação
foi no-op provado, não aposta.

**E uma defesa que a fixação sozinha não dá:** uma guarda de build que lê a versão do core dentro
da imagem e falha se não bater com a pedida.

```
=== core na imagem: 7.1 (db_version 61833) — pedido: 7.1.0 ===
=== core na imagem: 6.8.3 (db_version 60421) — pedido: 6.8.3 ===
```

Testada nos três casos antes de entrar: default, homolog, e **divergência forçada** — que falhou o
build com código 1, como devia. **A guarda responde no log de todo build a pergunta que a Tarefa B
mostrou que ninguém sabia responder: qual core esta imagem entrega?**

A fixação impede a deriva; a guarda **detecta** a deriva que a fixação não cobrir — tag reescrita
no registro, cache envenenado, engano de digitação no `--build-arg`. São defesas de camadas
diferentes e não substituem uma à outra.

---

## 34. Qual editor está ativo não se determina por CLI

**Onde apareceu.** Teste da 7.1 em homolog (29/08) e a correção pelo navegador (01/09/2026).

Em 29/08 rodei PHP por `kubectl exec` para saber qual editor o site usa. A resposta foi
`editor_classico: true`, `editor_blocos: false`, `iframe_canvas: 0`, e eu construí em cima dela a
frase central do relatório: *"o maior risco previsto da 7.1 — o iframe obrigatório — não se
aplica"*.

**No navegador, com sessão real, o site usa o editor de BLOCOS, e o canvas ESTÁ em iframe.**

**Por quê.** `use_block_editor_for_post()` e o contexto de tela dependem de `is_admin()`, da tela
corrente (`get_current_screen()`) e de filtros que só são registrados numa requisição de admin.
Em `php -r` por `exec` nada disso existe — e a função **não erra: ela responde outra pergunta**,
com a confiança de sempre.

> **Qualquer coisa que dependa do contexto de admin — editor ativo, metaboxes registradas,
> capacidades de tela, o que um plugin enfileira — só é verdadeira medida numa requisição real do
> painel.** CLI serve para dados; não serve para "o que o usuário vê".

Parente do `is_admin()` valendo `true` em `admin-ajax` (rodada 3). **A família é: a função
responde, a resposta é sobre um contexto que não é o seu.**

### O corolário do instrumento: o console só conta a partir da chamada

No mesmo dia relatei **"zero erros e 1 aviso"** no editor. Medido corretamente, são **11 avisos por
carga** — três deles nomeando blocos legados que dependem do caminho de compatibilidade **sem
iframe** que o WordPress já anunciou que vai remover.

A diferença não foi o ambiente: **o rastreador de console começa quando a ferramenta é chamada**, e
eu li **depois** da página carregar. Os avisos de carregamento nunca estiveram disponíveis.

> **Ordem correta: iniciar o rastreamento, recarregar, e só então ler.** Um contador que começa
> depois do evento relata zero — e zero é a resposta mais convincente que a falta de medição pode
> dar. Mesma família do §26 e do §30.

### A generalização, que é maior que o caso

**Função de framework chamada fora do contexto que ela pressupõe é a versão mais traiçoeira
deste problema — porque não há sinal nenhum de que a resposta é de outra pergunta.**

Compare com os vizinhos desta seção:

| | O sintoma | O sinal disponível |
|---|---|---|
| §16.7 `DATA_FREE` | valor velho | nenhum — mas há uma fonte autoritativa ao lado |
| §26 `performance_schema.accounts` | tabela vazia | uma variável a distância dizia `= 0` |
| §30 log do nginx | zero 5xx | o tipo de falha não passa por aquele log |
| **§34 `use_block_editor_for_post()`** | **`false`, com confiança total** | **NENHUM** |

Nos três primeiros havia uma pista: um zero suspeito, um contador desligado, um tipo de erro que
não se registra. **Aqui não há.** A função existe, é a função certa, recebe o argumento certo,
devolve um booleano bem formado no tempo esperado. Ela responde **exatamente** o que foi
perguntado — só que "este post usa o editor de blocos?" é, em CLI, uma pergunta sem o contexto que
lhe dá sentido: não há `is_admin()`, não há tela corrente, não há os filtros que os plugins
registram só no painel.

> **Antes de usar o retorno de uma função de framework como fato, pergunte de que contexto ela
> depende — e se você está nele.** `is_admin()`, `get_current_screen()`, `wp_doing_ajax()`,
> capacidades, hooks de admin, tema ativo, locale: todos mudam a resposta sem mudar a assinatura.
>
> **E a regra prática que resolve o caso:** o que descreve *o que o usuário vê* mede-se **onde o
> usuário está** — no navegador, com sessão real. CLI serve para dado, não para experiência.

**O custo real deste erro nesta sessão:** a frase central de um relatório — *"o maior risco
previsto da 7.1 não se aplica"* — era falsa, e ficou três dias de pé sustentando um
dimensionamento de migração.

---

## 35. O autoscaler não enxerga esgotamento de pool de workers

**Onde apareceu.** Incidente de 01/09/2026: a redação não conseguiu usar o painel de produção
entre ~09:31 e ~09:36 UTC, enquanto o site público seguiu normal.

**O que foi medido:**

```
DatabaseConnections (prod)  09:32  60
                            09:33  60      <- teto exato, tres minutos travado
                            09:34  60
TargetResponseTime medio    09:33  11,41 s   (normal: 0,30 s)
2XX por minuto              09:33  44        (normal: ~230)
CPU do banco                09:33  16,4%     ReadLatency 0   DiskQueue 0,23
```

**60 = 5 pods × `pm.max_children` 12.** Todo worker do PHP-FPM segura uma conexão. Os sessenta
estavam ocupados ao mesmo tempo: as requisições novas ficaram na fila do FPM, a latência subiu 38×
e a vazão caiu 80%. **O banco estava ocioso** — o gargalo era a camada de aplicação.

**Por que só a redação sentiu.** Quem está logado **não usa o `fastcgi_cache`**: cada clique vai a
PHP. O leitor anônimo é servido pelo cache e não percebe nada. **Medir o site público não revela
este defeito** — e foi exatamente o que o painel mostrava: tudo verde.

### O ponto estrutural: o HPA é cego para este modo de falha

```
HPA: min=2 max=5, metricas cpu@70% e memory@80%
Durante a saturacao: cpu=38%, memory=38%
```

**Nenhuma das duas métricas chega perto do gatilho, porque os workers não estão gastando CPU —
estão bloqueados esperando I/O.** Um pool esgotado tem CPU baixa por definição: os processos
existem, estão ocupados, e não fazem nada. O autoscaler vê um serviço folgado.

E, mesmo que visse, **o Deployment já estava em 5 de 5**, o teto do próprio HPA.

> **Escalar por CPU protege contra o gargalo de CPU, e só contra ele.** Quando a capacidade é
> medida em *slots* — workers de FPM, conexões de banco, threads de fila —, a métrica que revela a
> exaustão é a **ocupação do pool** ou o **tempo na fila**, nunca a CPU. Um sistema pode estar 100%
> saturado com 38% de CPU, e o gráfico que o time olha vai dizer que está tudo bem.

### O contrafactual, que é o que separa causa de coincidência

Mesma janela (09:00–10:10 UTC), dias anteriores:

| Dia | Conexões máx | Minutos ≥55 | Resp. média máx | PHP |
|---|---|---|---|---|
| 28/08 | 25 | 0 | 2,43 s | 8.2 |
| 29/08 | 29 | 0 | 2,93 s | 8.2 |
| **31/08** | **60** | **1** | 3,61 s | **8.2** |
| **01/09** | **60** | **3** | **11,41 s** | **8.3** |

**31/08 bateu o mesmo teto, ainda em PHP 8.2.** A saturação **não** foi causada pela subida do PHP
8.3 — é anterior a ela. Sem esta comparação, o dia da mudança seria culpado por associação.

**E 28 e 29/08 param em 25-29 conexões porque o teto era outro**: 5 pods × `max_children` **5**.
A correção para 12 subiu o teto de 25 para 60 — e o teto de 60 também é alcançado. **Aumentar um
limite sem medir a demanda troca o valor do teto, não a existência dele.**

**O que não sei:** por que hoje foi pior que 31/08 (3 minutos contra 1; 11,4 s contra 3,6 s).
Segunda-feira contra domingo é o candidato óbvio, e não tenho como separar isso do PHP 8.3 com os
dados desta janela.

### A incerteza que fica em aberto, escrita como incerteza

Hoje (01/09, PHP 8.3) foi **3× pior** que 31/08 (PHP 8.2): 3 minutos no teto contra 1, e resposta
média de 11,41 s contra 3,61 s.

**Não consigo separar "segunda-feira contra domingo" de "PHP 8.3" com os dados desta janela.**
As duas variáveis mudaram juntas. Segunda de manhã é o pico natural da redação; e foi também o
primeiro dia em 8.3.

> **O teste que decide já está marcado, e é o próprio calendário:**
> - se **amanhã (02/09, terça) repetir na mesma magnitude** de hoje → é **dia da semana**, e o PHP
>   está fora
> - se **piorar** → o PHP 8.3 **volta à mesa** como suspeito
>
> Medir: `DatabaseConnections` máximo, minutos em ≥55, e `TargetResponseTime` médio máximo na
> janela 09:00–10:10 UTC. Comparar com 28/08 (25), 29/08 (29), 31/08 (60/1min/3,61s) e
> 01/09 (60/3min/11,41s).

**Isto é registro de incerteza, não de conclusão.** A tentação é fechar a causa no dia da mudança,
porque é a explicação mais disponível — e foi exatamente contra isso que o contrafactual de 31/08
serviu.

---

## 36. Não atribua capacidade sem medir quem a consome

**Onde apareceu.** 01/09/2026. Eu tinha diagnosticado o incidente da redação como saturação de
PHP-FPM e preparado a correção — `max_children` 12→20 e `minReplicas` 2→6. **O Albert parou a
aplicação com uma pergunta de uma linha:** *60 conexões simultâneas não batem com o número de
pessoas trabalhando no painel.*

**Ele estava certo.** Os logs do nginx da janela 09:31–09:36, dos pods que viveram o incidente:

| Classe | Requisições | % das dinâmicas |
|---|---|---|
| público | 2.428 | 83,6% |
| **busca `/?s=`** | **400** | **13,8%** |
| wp-json | 46 | 1,6% |
| **wp-admin + login + admin-ajax** | **27** | **0,9%** |

**A redação era 0,9%.** A correção que eu ia aplicar teria dado mais capacidade para servir mais
depressa um tráfego que não era dela — **exatamente o erro de quando 5 virou 12**, que trocou o
valor do teto sem mudar sua existência.

### O que o tráfego era

```
2.259 IPs distintos em 2.905 requisicoes dinamicas
2.194 deles fizeram UMA requisicao so
400 buscas vindas de 399 IPs distintos — 398 com uma busca cada
user-agents: Chrome/Safari/Firefox comuns, de Mac e Windows
```

**Uma busca por IP, quatrocentos IPs, em seis minutos.** Não é rastreador — é **pool distribuído**,
e a distribuição existe justamente para que nenhum limite por IP alcance. As defesas que o site já
tem não pegam: o mapa `bad_bot` casa por user-agent (Ahrefs, Semrush, Bytespider…) e estes se
apresentam como navegador; e os `limit_req_zone` existentes cobrem **só** `login` e `xmlrpc`.

E o padrão **se repete**: 80 buscas às 08:12, 111 às 08:30, **436 às 09:30**, 239 às 10:12 —
contra média de 45 por bloco de 6 minutos. O pico foi **9,8× a média**.

### O que eu ainda NÃO consigo responder, e é o ponto

A pergunta era *"dos 60 workers, quantos eram a redação e quantos outra coisa"*. **A primeira
metade está respondida — quase nenhum. A segunda não.**

Com os custos medidos em repouso, a janela inteira precisa de **~3,5 workers de média**. Foram
**60**. **Um fator de 17 que não fecha.**

**Sei onde estão os pontos cegos, e são todos de instrumentação:**

| Falta | Consequência |
|---|---|
| `$request_time` no `log_format` | uso custo em repouso, não o custo durante a saturação |
| `$upstream_cache_status` no `log_format` | infiro HIT/MISS por unicidade de URL em vez de medir — **e o header `X-FastCGI-Cache` existe na resposta, só não é registrado** |
| `request_slowlog_timeout` do FPM desligado | nenhum registro de em que função um worker lento parou |
| média de 6 minutos | esconde rajada de segundos, que é onde 60 acontece |

> **Antes de aumentar um limite, meça quem o está consumindo.** Um teto alcançado prova que a
> demanda passou da oferta — **não prova de quem é a demanda**. E sem saber de quem é, aumentar a
> oferta é comprar capacidade para um consumidor que você não escolheu.

### Achado colateral, este sim acionável

```nginx
if ($query_string != "") { set $skip_cache 1; }
```

**Qualquer query string desativa o cache.** Confirmado pelo header: a mesma URL dá `HIT` limpa e
`BYPASS` com `?utm_source=facebook`. **A busca, portanto, nunca é cacheada** — e são 238 termos
distintos em 436 requisições, então cachear removeria ~45% delas.

Na janela do incidente, 520 das 2.905 dinâmicas (17,9%) tinham query string. **Verifiquei se eram
`utm_*`/`fbclid` — não eram**: 401 são o `s=` da busca, o resto é `q`, `url` (oEmbed) e parâmetros
de REST. **A hipótese do rastreamento de campanha não se sustentou, e é bom que eu tenha
conferido antes de construir a história em cima dela.**

### E um erro meu de instrumento, no meio disto

Cheguei a anunciar *"está acontecendo agora"* com base numa **única** medição de 25,8 s. Quatro
medições seguidas depois deram **0,93 s**. Pior: cronometrando do meu computador, um `HIT` mediu
**1,45 s** e um `MISS` **0,83 s** — porque ~0,9 s é rede até `us-east-1`. **Tempo medido da ponta
errada não atribui nada**; o que atribui é o header, que estava lá o tempo todo.

---

## 37. Latência medida da ponta errada não mede o servidor

**Onde apareceu.** Investigação do incidente de 01/09/2026.

Cronometrando `https://bahia.ba/` **do meu computador**, um `HIT` de cache mediu **1,45 s** e um
`MISS` mediu **0,83 s**. O HIT — que o servidor entrega do disco em milissegundos — apareceu
**mais lento** que o MISS.

**A causa é trivial e por isso perigosa:** ~0,9 s de cada medição é rede até `us-east-1`
(`dns=0,003 + conexao=0,10 + tls=0,31 + primeiro byte=0,52`). O sinal do servidor — dezenas de
milissegundos — fica **abaixo do ruído do transporte**, e a ordenação entre duas medidas passa a
ser aleatória.

Medido do lugar certo, os mesmos pods respondiam em **10 a 30 ms**.

> **Tempo de resposta de servidor mede-se de dentro do cluster, ou pelo cabeçalho que o próprio
> servidor emite.** Deste site: `X-FastCGI-Cache: HIT|MISS|BYPASS` já existia na resposta o tempo
> todo, e é categórico — não depende de latência, de rota, nem de qual continente mede.
>
> Cronômetro na ponta do cliente serve para responder *"como está para o usuário"*. **Não serve
> para atribuir custo a componente.**

### E o falso positivo: "está acontecendo agora" com uma amostra

No meio da mesma investigação anunciei que produção estava degradando **naquele instante**, com
base em **uma** medição de 25,8 s. Quatro medições seguidas, minutos depois, deram **0,93 s**.

É o §22 outra vez — agora na direção oposta. Lá a agregação inventou uma indisponibilidade que não
existiu; aqui **uma amostra única inventou um incidente em curso**. A mesma disciplina resolve os
dois casos: **nenhuma afirmação sobre estado a partir de um ponto — nem para o bem, nem para o
mal.** E o custo do falso positivo não é zero: ele desvia a atenção de quem está lendo o relatório.

---

## 38. Qualquer query string desliga o cache — e o quanto isso custa é medível

**Onde apareceu.** Mesma investigação. Regra encontrada no `default.conf` do nginx:

```nginx
if ($query_string != "") { set $skip_cache 1; }
```

Confirmado pelo cabeçalho, na mesma URL: limpa dá `HIT`; com `?utm_source=facebook` dá **`BYPASS`**,
três vezes seguidas. **Não é heurística — é categórico.**

**A consequência estrutural é real:** todo link que chega com parâmetro — `utm_*`, `fbclid`,
`gclid`, qualquer coisa — **renderiza em PHP**. E link compartilhado em rede social costuma ter
parâmetro.

**Mas o volume, medido em 3 horas de log dos três pods, é modesto:**

| | |
|---|---|
| requisições totais | 33.883 |
| dinâmicas | 22.659 |
| **com query string (bypass garantido)** | **3.452 — 15,2% das dinâmicas** |
| **parâmetros de rastreio (`fbclid` + `gclid`)** | **18 ocorrências** |

**Os 3.452 são quase todos internos, não campanha:** `s` (busca, 1.166), `q` (827), `url` (oEmbed,
737), `redirect_to`/`reauth` (276 cada), `format` (184).

> **A hipótese que eu ia defender — "o tráfego de rede social não cacheia" — não se sustenta neste
> site: são dezoito requisições em três horas.** O que de fato não cacheia é a **busca** e o
> **oEmbed**. Vale registrar a regra pelo que ela é, e o volume pelo que ele é: **eram coisas
> diferentes, e só a medição separou.**

**O que sobra de acionável:** 1.817 requisições em 3 h (8,0% das dinâmicas) têm query string que
**não precisaria** desligar o cache — sobretudo `oembed` com `url=` e `format=`. Uma regra que
liste os parâmetros que realmente exigem bypass (`s`, `doing_wp_cron`, `redirect_to`, `preview`,
`p`, `page_id`, `replytocom`) em vez de negar tudo recuperaria essas 8%.

---

## 39. Meça o entorno, não só o alvo

**Onde apareceu.** 01/09/2026, ao calcular quanto a raspagem de busca custava em dólar.

A pergunta era estreita: *quanto custa em banda a raspagem?* A resposta é **US$ 3,65/mês** — 41 GB,
1,7% da saída. Quase nada.

**Mas para responder foi preciso medir a banda total.** E ali, ao lado, estava isto:

```
saida do ALB          : 2.355 GB/mes  ->  ~USD 212/mes
compressao            : DESLIGADA  (#gzip on; comentado no nginx.conf)
home                  : 575.135 bytes que caberiam em 93.784  (-83,7%)
economia ao ligar     : ~USD 170/mes = USD 2.034/ano
```

**O achado colateral vale 46× o achado que eu procurava.**

> **Ao investigar um custo, meça a categoria inteira à qual ele pertence, não só a fatia
> suspeita.** A fatia só ganha sentido contra o total — e o total costuma ter dentro dele coisas
> que ninguém foi procurar, porque ninguém tinha motivo para olhar ali.

E não foi sorte: a mesma varredura produziu **três** achados que não eram o alvo —
`robots.txt` respondendo **404** (com o sitemap existente e invisível), a busca já sendo
`noindex` (o que barateia a defesa), e os 8% de `oembed` que não precisavam sair do cache.
**Nenhum deles apareceria numa consulta que perguntasse só pela raspagem.**

**O contraponto honesto:** medir o entorno custa tempo e produz distração. A disciplina não é
"meça tudo" — é **medir o denominador de qualquer fração que se vá reportar**. "1,7% da banda"
obriga a conhecer a banda; e é ao conhecer a banda que se vê o que há nela.

---

## 40. O instrumento respondeu — e desmentiu a correção que eu ia aplicar

**Onde apareceu.** 01/09/2026, primeira leitura do log instrumentado (99 minutos, 29.483
requisições, formato novo com `rt=`, `urt=` e `cache=`).

### A atribuição que faltava

`urt` é o tempo do PHP-FPM, ou seja, **worker-segundos**:

| Classe | Reqs | Worker-seg | % | s/req |
|---|---|---|---|---|
| **público** | 15.856 | **10.113** | **81,3%** | 0,638 |
| **sitemap** | 771 | **1.224** | **9,8%** | **1,588** |
| admin-ajax | 933 | 568 | 4,6% | 0,608 |
| wp-json/oEmbed | 990 | 230 | 1,9% | 0,233 |
| wp-admin | 370 | 130 | 1,0% | 0,352 |
| **BUSCA** | 149 | **104** | **0,8%** | 0,700 |
| estático | 10.007 | 2,6 | 0,0% | — |

**Média de 2,10 workers ocupados, de 60 disponíveis.** Vinte e oito vezes de folga.

> **A busca é 0,8% dos worker-segundos.** O `limit_req` global que eu desenhei — e que o Albert
> aprovou — atacaria menos de um por cento do consumo. **Mesmo a rajada do incidente (436 buscas
> em 6 min × 0,7 s) dá 0,85 worker.** A raspagem de busca não é o que esgotou o pool.

### O que o instrumento mostrou no lugar

**`/wp-sitemap.xml?q=<editoria>`: 771 requisições, 1,588 s cada — a classe MAIS CARA por
requisição, 9,8% de todo o consumo.** E:

```
431 das 771 respondem 404   (um 404 que custa 1,6 s de PHP e devolve 60 KB)
o parametro ?q= faz BYPASS do cache -> render completo, toda vez
vem de 5 IPs, nao do pool disperso: 78.47.42.23 (183), 186.232.82.83 (125),
46.225.122.219 (118), 142.132.174.203 (84), 159.69.206.158 (44)
```

**Custa mais que a busca, é mais concentrado, e é mais fácil de conter.** Não estava em nenhuma
hipótese minha antes de o log existir.

---

## 41. Cache fragmentado além do que o tráfego amortiza

**Taxa de acerto medida com pods quentes: 17,6%** (HIT 2.213 contra MISS+EXPIRED 10.386).

**E não é defeito de configuração.** Testado num pod só, mesmo user-agent: **12 chamadas, 12 HIT,
1 ms cada**. O TTL segura além de 130 s. `fastcgi_ignore_headers Set-Cookie` está lá.

**São duas causas, as duas medidas:**

**1. Quase metade do tráfego é de URL que aparece uma vez.**

```
6.504 URLs distintas em 12.772 requisicoes cacheaveis
5.933 URLs (91,2%) pedidas UMA vez  ->  46,5% das requisicoes
```

É a assinatura de rastreador percorrendo um acervo de 435 mil posts: cada matéria é pedida uma
vez, renderizada, guardada, e nunca mais pedida. **Cache não ajuda o que não repete.**

**2. A chave fragmenta em 15 fatias independentes.**

```
5 pods (cada um com seu /tmp/nginx-cache em emptyDir)
x 3 variantes de dispositivo na chave  (|d=$bahia_mobile$bahia_ipad)
= 15 fatias que precisam ser preenchidas SEPARADAMENTE
```

**Das 571 URLs que repetem, 481 (84%) recebem menos de 15 requisições** — mediana de **4**.
**Elas nunca chegam a encher todas as fatias**, e por isso continuam dando MISS mesmo repetindo.

> **Cada dimensão na chave de cache multiplica o volume necessário para amortizá-la.** Três
> variantes de dispositivo × cinco réplicas exigem quinze vezes mais repetição da mesma URL para
> o cache render o mesmo. Num tráfego cuja URL mediana repete quatro vezes, **a chave está
> dimensionada para um tráfego que este site não tem.**

O caminho não é mais worker: é **menos fatias** — cache compartilhado entre réplicas, ou menos
dimensões na chave. As duas são mudanças de porte e nenhuma cabe numa janela de correção.

---

## 42. Aprovação não sobrevive ao dado que ela estava esperando

**Onde apareceu.** 01/09/2026. O `limit_req` global na busca foi **desenhado, defendido e
aprovado** — sobre a hipótese de que a raspagem de busca esgotava os workers. O log instrumentado
chegou horas depois e mostrou que **a busca é 0,8% dos worker-segundos**.

A correção foi retirada. Mas o que interessa é o mecanismo que quase a manteve:

> **Uma correção aprovada com o dado a caminho deve ser REEXAMINADA quando o dado chega — não
> aplicada por já estar aprovada.**

A aprovação é sobre o raciocínio disponível no momento em que foi dada. Quando a medição que a
motivou finalmente existe, ela **substitui** o raciocínio; e se o contradiz, a aprovação anterior
perde o objeto. **Executar assim mesmo é confundir "combinado" com "verdadeiro".**

**O sinal de alerta é reconhecível:** a frase *"isso já está aprovado"* aparecendo entre a
chegada do dado e a aplicação. Toda vez que ela for a justificativa, a pergunta certa é: *aprovado
com base em quê, e esse "quê" ainda vale?*

Parente do §23 e do §28 — em todos, a conclusão foi formada antes de a medição existir, e a
tentação foi tratá-la como estabelecida.

---

## 43. O 404 é uma superfície de amplificação: 7,5% do consumo, e ninguém o media

**Onde apareceu.** Mesma leitura do log instrumentado.

```
1.618 requisicoes 404 em 99 min
935 worker-segundos  =  7,5% de TODO o consumo de PHP
0,578 s e 55 KB por 404 (313 KB antes do gzip de hoje)
404 NAO entra no fastcgi_cache — cada um e render completo
```

**O 404 renderiza o tema inteiro.** Uma URL inexistente custa quase o mesmo que uma matéria real
— e como não cacheia, custa isso **todas as vezes**. Qualquer um pode gerar 404 à vontade, de
graça, e o custo é nosso.

**É o resíduo do defeito da rodada 4** — lá o 404 chegou a 36 s por causa da pré-renderização do
`next_prev` do tagDiv, e o `bahia-td-query-perf.php` derrubou para ~1,5 s. **A catástrofe foi
corrigida; a amplificação continua.**

### De onde vêm, medido

| Origem | Reqs | Worker-seg | O que pede |
|---|---|---|---|
| **Googlebot** | 563 | **326** | **540 são `/listing-sell/…`** — rastro de injeção de spam |
| outro | 365 | 255 | diversos, inclui `/categoria/` (defeito de taxonomia conhecido) |
| **Hetzner (raspador)** | 426 | 214 | `/wp-sitemap.xml?q=<editoria>` — parâmetro **inventado** |
| Bingbot | 243 | 135 | diversos |
| ClaudeBot | 21 | 7 | diversos |

**O maior gerador de 404 do site é o Googlebot rastreando URLs de spam** — `/listing-sell/`,
`/craigslist/`, `/near-me/`, caminhos em base64 referenciando o IMDb. Alguém injetou isso em
algum momento, o Google indexou, e **continua voltando**. Não é ataque em curso: é dívida.

> **Corrigi minha própria leitura no meio disto:** vi `66.249.72.x` gerando 404 e concluí que era
> o Googlebot batendo em `/categoria/`, o defeito de taxonomia já documentado. **Não era** — os
> `/categoria/` vieram de outra origem, e os do Google são 96% `/listing-sell/`. A conclusão
> parecia coerente porque encaixava num defeito conhecido, **e é justamente aí que encaixar é
> perigoso.**

---

## 44. Encaixar num defeito conhecido dá coerência à conclusão errada

**Onde apareceu.** 01/09/2026, ao decompor os 404 por origem.

Vi `66.249.72.x` — Googlebot — no topo dos geradores de 404, e a conclusão veio pronta: *o Google
está batendo em `/categoria/`, que é o defeito de taxonomia que já documentamos* (18 CPTs
disputando o mesmo slug de reescrita). **Encaixava perfeitamente.** Havia um defeito conhecido,
documentado, com sintoma exatamente igual, e um agente conhecido por insistir em URLs quebradas.

**Medido: 540 dos 563 404 do Googlebot são `/listing-sell/…`. Os `/categoria/` vieram de outra
origem.** A conclusão estava errada em quase 100%.

> **Um defeito já documentado é a explicação mais barata que existe — e por isso a mais
> perigosa.** Ele dispensa investigação: já tem nome, já tem causa, já foi aceito antes. Quando um
> sintoma novo encaixa nele, a tentação é fechar sem medir, porque *fechar* parece confirmar o
> que já se sabia.
>
> **O sinal é a sensação de coerência chegando antes do dado.** Se a explicação apareceu completa
> antes de você contar as ocorrências, ela não veio da evidência — veio da memória.

É a família do §16 numa forma nova. Nas outras, o instrumento respondia outra pergunta. **Aqui o
instrumento estava certo e disponível; quem respondeu antes dele fui eu.**

O que corrigiu foi trivial: agrupar por prefixo de URL em vez de aceitar a primeira hipótese —
três linhas de código que eu só rodei porque decidi mostrar exemplos no relatório.

### Acréscimo ao §43 — o rastreador não é teimoso, está sendo confirmado

Descoberto ao montar o diff: **as URLs de spam não devolvem 404 — devolvem `301` para matérias
reais**, adivinhadas pelo `redirect_guess_404_permalink()` do núcleo a partir do último segmento.

```
/listing-sell/qualquer/coisa -> 301 -> /entretenimento/coisa-atipica-diz-equipe-de-roberto-carlos.../
/craigslist/x                -> 301 -> /economia/x-afirma-que-pagou-todas-as-multas.../
/near-me/y                   -> 301 -> /entretenimento/yacoce-simoes-celebra-35-anos.../
```

**Isso muda a leitura inteira do item.** Eu tinha descrito o Googlebot como rastreador insistindo
em URLs mortas — dívida de índice, custo de worker. **Não é isso.**

> **Do ponto de vista do Google, aquelas URLs FUNCIONAM.** Ele pede, recebe `301`, chega em
> conteúdo real e relevante. É a confirmação de que a URL vale — e por isso ele volta. **O
> comportamento que eu li como teimosia é, na verdade, o crawler respondendo corretamente a um
> sinal que nós emitimos.**

E o dano não é só nosso worker: **é o domínio emprestando autoridade a URLs de spam.** Cada 301
diz ao Google que `bahia.ba/listing-sell/<lixo>` é um endereço legítimo deste site que leva a
conteúdo bom. **É vetor de SEO trabalhando contra o site**, não apenas consumo de CPU.

**A lição de método:** ao medir um custo, eu classifiquei o agente (*"rastreador teimoso"*) antes
de verificar **o que ele recebia de volta**. O status da resposta estava no mesmo log que eu já
estava lendo. **Um padrão de requisição só se interpreta junto com a resposta que ele obtém** —
sozinho, ele descreve metade da conversa, e a metade que falta é a nossa.

---

## 45. Duas falhas na mesma mudança: uma que não pegou, e uma que cegou a medição

**Onde apareceu.** Aplicação das três correções de 404 em produção, 01/09/2026.

### A) `fastcgi_cache_valid 404 5m` não teve efeito nenhum

Medido depois: **`cache=MISS` em todos os 404**, e o tempo continuou em 1,4 s.

**A causa, provada:**

```
wp_get_nocache_headers() do WordPress devolve:
  Expires: Wed, 11 Jan 1984 05:00:00 GMT
  Cache-Control: no-cache, must-revalidate, max-age=0, no-store, private

e a config tem:
  fastcgi_ignore_headers Set-Cookie;      <- SO Set-Cookie
```

**O nginx obedece ao `no-store` do upstream e recusa guardar.** O `fastcgi_cache_valid 404 5m`
nunca chega a ser consultado — ele diz *por quanto tempo* guardar, não *se pode* guardar.

> **`fastcgi_cache_valid` não vence `Cache-Control` do upstream.** Quem decide se pode guardar é
> `fastcgi_ignore_headers`. **Eu escrevi uma diretiva que responde a segunda pergunta enquanto o
> bloqueio estava na primeira** — e o `nginx -t` passou, porque a sintaxe estava certa.

**O que faltou verificar antes:** eu havia testado o TTL na home (§ da compressão) e o cache
segurou além de 130 s — e concluí que o upstream não interferia. **Mas o WordPress só emite
`nocache_headers` em respostas de erro e páginas privadas, não na home.** Testei o caso onde o
problema não existe e generalizei para o caso onde ele existe.

**A correção seria `fastcgi_ignore_headers Cache-Control Expires Set-Cookie;` — e ela é mais larga
do que parece**: passaria a ignorar o `Cache-Control` de **todas** as respostas, inclusive as que
o WordPress marca como privadas por um motivo que o `$skip_cache` não cobre. **Não aplicada:
precisa de análise própria, não de mais um rollout no fim de um dia com cinco.**

### B) `access_log off` apagou a prova do que a mudança fez

Nos dois blocos novos escrevi `access_log off`, para não inflar o log com tráfego de raspador.

**Consequência: as requisições que passaram a ser 410 e 301 sumiram do log — e eram exatamente as
que eu precisava contar para medir a redução.** Ao medir depois, os caminhos de spam e o sitemap
aparecem com **zero requisições**, não porque pararam, mas porque não são mais registrados.

> **Não desligue o registro do que você acabou de mudar.** O log de uma classe de tráfego é caro
> só enquanto ela é grande — e ela ser grande é precisamente a razão de tê-la mudado. **Desligar
> o log no mesmo gesto em que se corrige é destruir a linha de base do "depois".**

Parente do §24 (o limiar que apaga a prova) e do §16.7. **Em todos, a informação foi descartada
por uma decisão de eficiência tomada sem perguntar o que se perde.**
