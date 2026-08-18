# Import de conteúdo — produção → homolog

**Estado: PRONTO PARA EXECUTAR, NÃO EXECUTADO.**
Levantamento feito em 16/08/2026 sobre o pod `wordpress-8f6498988-ch5gd`, namespace
`bahia-wordpress`, cluster `bahia-eks-homolog`.

Este documento é o roteiro de execução. Leia a seção 0 antes de qualquer coisa: há um
achado que muda o desenho do import e exige decisão antes de começar.

---

## 0. BLOQUEIO — os IDs de produção colidem com o trabalho de homolog

**Não execute nenhum import que preserve os IDs de produção acima de 547290 sem resolver
isto primeiro. Um import "aditivo" que mantém IDs sobrescreve onze rodadas de trabalho, e o
`ON DUPLICATE KEY UPDATE` faz isso sem erro nenhum.**

### O que foi medido

O retrato que originou o banco de homolog termina no post **547278** (economia, 28/07/2026).
A partir de **547291**, todo ID em homolog é conteúdo **nascido aqui**: 121 registros.

Produção não parou: continuou publicando a partir de 547279 e hoje está em torno de
**550428**. Ou seja, produção usou para matérias reais exatamente os IDs que homolog usou
para templates e páginas.

Verificado um a um contra `https://bahia.ba/?p=ID`:

| ID | Em homolog | O mesmo ID em produção |
|---|---|---|
| 547358 | página Brasileirão 2026 | matéria de justiça (Embasa / Caturama) |
| **547369** | **página Quem Somos** | matéria de política (Bruno Reis) |
| 547408 | página Home | matéria de esporte |
| **547414** | **Header Template - Magazine PRO** | matéria de salvador (rodoviária) |
| 547420 | Single Post Template | matéria de política (pesquisa Quaest) |
| **547430** | **404 Template** | matéria de justiça |
| **547432** | **página Home — é o `page_on_front`** | um anexo de imagem |
| 547477 | post de política de teste | matéria de bahia (BR-324) |

`page_on_front` vale **547432**. Em produção esse ID é um anexo. Um import que preserve IDs
troca a home do site por uma imagem.

### Os 121 registros nascidos em homolog

Query que os lista (é a fonte da verdade, não a tabela acima):

```sql
SELECT ID, post_type, post_status, post_title, post_date
FROM wp_posts WHERE ID >= 547291 ORDER BY ID;
```

Composição: 13 `tdb_templates` (mais revisões), 5 `page` (Brasileirão 2026, Quem Somos,
Home ×2, bahia.ba), ~30 `attachment` (as 22 fotos da equipe, logos, favicons, fundos) e
~22 `nav_menu_item`.

### Decisão tomada: renumerar homolog antes, importar preservando os IDs de produção

**APROVADO em 16/08/2026.** Os registros nascidos em homolog saem da faixa de colisão para
uma faixa reservada; os IDs de produção permanecem canônicos.

Três razões, em ordem de peso:

1. **Essa renumeração vai ter de acontecer de qualquer forma na migração.** O banco de
   produção já tem 547369, 547414, 547432 e os demais ocupados por matérias reais — os
   templates nunca poderiam manter esses IDs lá. Fazendo agora, a migração deixa de precisar
   de remapeamento e vira cópia direta. Um trabalho resolve dois problemas.
2. **Manter os IDs de produção canônicos torna a virada previsível:** permalinks, `?p=`,
   referências em ACF e qualquer link externo apontam para o mesmo conteúdo dos dois lados.
3. **A alternativa é pior.** Remapear IDs no import esbarra em `noticias_relacionadas`,
   preenchido em **915 posts**, que guarda IDs de post: os valores vindos de produção
   apontam para IDs de produção e precisariam ser traduzidos pelo mesmo mapa. Qualquer falha
   aí produz "notícias relacionadas" apontando para a matéria errada — erro silencioso.

### A faixa reservada: 9.000.001 em diante

Fórmula: **`novo_id = 9.000.000 + (id_atual − 547.290)`**, aplicada aos registros nascidos
em homolog. Assim 547291 vira 9000001 e 547478 vira 9000188. A conversão é invertível de
cabeça, preserva a ordem e deixa o mapa auditável linha a linha.

**Por que 9.000.000 e não 600.000.** Produção consumiu **3.150 IDs em 19 dias** — de 547278
em 28/07 a ~550428 em 16/08 — o que dá **166 IDs por dia**, contando posts, anexos e
revisões. Nesse ritmo:

| Faixa | Folga | Tempo até produção alcançar |
|---|---|---|
| 600.000 | 49.572 IDs | **10 meses** — inaceitável |
| 1.000.000 | 449.572 IDs | 7,4 anos |
| **9.000.000** | **8.449.572 IDs** | **~140 anos** |

600.000 seria alcançado antes da próxima virada de ano. `wp_posts.ID` é `bigint(20)
unsigned`, então 9 milhões não custa nada em espaço nem em desempenho.

**O `AUTO_INCREMENT` fica em 9.000.189 e não deve ser rebaixado.** Depois da renumeração o
contador sobe para essa faixa, e é assim que se quer: todo conteúdo criado em homolog daí
em diante nasce acima de 9 milhões, e **todo import futuro fica livre de colisão por
construção**, sem precisar refazer este levantamento. Rebaixar o contador para continuar a
sequência natural reintroduziria exatamente o problema desta seção no próximo import.

**O plano detalhado da renumeração está em `RENUMERACAO-homolog.md`.** Ela é uma operação
separada, com validação própria entre ela e o import.

---

## 0.1 Ordem de execução — são duas operações, não uma

Renumeração e import têm riscos diferentes e falham de formas diferentes. Se forem feitas
na mesma janela, um site quebrado no fim não diz qual das duas o quebrou.

| # | Etapa | Onde está | Portão de saída |
|---|---|---|---|
| 1 | Backup completo do banco de homolog, fora do próprio banco | seção 1 deste documento | dump verificado e documento de restauração escrito |
| 2 | **Varredura de código, reportada** | `RENUMERACAO-homolog.md` §3.5 | **CONCLUÍDA** — 2 linhas de código a ajustar |
| 3 | Renumeração, com o site em manutenção | `RENUMERACAO-homolog.md` §4 | reportar ao final |
| 4 | Validação: 6 consultas + 13 itens visuais, **Quem Somos com as 22 fotos primeiro** | `RENUMERACAO-homolog.md` §6 | reportar ao final |
| 5 | Atualização dos documentos e dos comentários | `RENUMERACAO-homolog.md` §7 | reportar ao final |
| 5b | **Dump de produção gerado e entregue** | seção 1.5 | arquivo verificado em `~/BAHIABA-backups/` |
| 6 | Import: dry-run reportado, depois import, depois os 12 itens | seções 2, 3 e 4 deste documento | reportar ao final |

**Reportar ao final de cada etapa. Não encadear 3, 4 e 6 sem parada.**

---

## 1. Backup completo, antes de tudo

O banco tem **6,5 GB**. O pod tem **3,3 GB livres** em disco e **não tem cliente mysql
nem `mysqldump`**. O dump não pode ficar no pod: tem de sair por streaming.

Alvo: RDS `rds-bahiaba-hml.cr9zu4ke1bev.us-east-1.rds.amazonaws.com`, banco `prod`.

> **Cuidado com o nome: o banco de homolog se chama `prod`.** Ler `DB_NAME` e concluir que
> se está em produção é erro fácil. A identidade correta se confirma por
> `SELECT option_value FROM wp_options WHERE option_name='siteurl'` — tem de devolver
> `https://hml.bahia.ba`.
>
> **Confirmar em TODAS as etapas, não só na primeira.** O contexto do `kubectl` já trocou
> sozinho de homolog para produção uma vez neste projeto. Todo script deste roteiro começa
> com a verificação e aborta se ela falhar:
>
> ```php
> $site = get_option('siteurl');
> if ($site !== 'https://hml.bahia.ba') { die("ABORTADO: siteurl inesperado ({$site})\n"); }
> ```
>
> Um script que escreve no banco sem essa guarda no topo não deve ser executado.

### 1a. Snapshot do RDS — primeira cópia, se houver acesso

É a cópia mais rápida e mais completa, e é nativa: restaura o banco inteiro a um ponto no
tempo, sem depender de arquivo nenhum.

```
aws rds create-db-snapshot \
  --db-instance-identifier rds-bahiaba-hml \
  --db-snapshot-identifier hml-pre-import-YYYYMMDD-HHMM
```

**As credenciais de pipeline usadas hoje NÃO têm permissão de RDS** (`AccessDenied` em
`rds:DescribeDBInstances`). Este passo precisa do console da AWS ou de credenciais com
permissão. Se não houver acesso, o item 1b passa a ser a única cópia e **não é opcional**.

### 1b. Dump lógico, transmitido para fora do pod

Sobe um pod temporário com cliente MySQL na mesma VPC e transmite o dump para a máquina
local, comprimido. As credenciais saem do `wp-config.php` do pod — não as escreva em
arquivo nem neste documento.

> **Use `mysql:8.0.31`, não `mysql:8`.** O cliente 8.4 aborta com
> `Couldn't execute 'FLUSH /*!40101 LOCAL */ TABLES WITH READ LOCK': Access denied (1045)`:
> desde o 8.0.32 o `mysqldump` pede o privilégio `RELOAD` quando se usa
> `--single-transaction`, e o RDS não concede `RELOAD` ao usuário mestre. O 8.0.31 é
> anterior à mudança e faz o mesmo dump consistente sem pedir esse privilégio. Medido em
> 16/08/2026 — a primeira tentativa deste roteiro morreu exatamente aí.
>
> `--events` também foi retirado: exige privilégio próprio e o banco não tem eventos.

As credenciais saem do ambiente do pod (`WORDPRESS_DB_HOST`, `_USER`, `_PASSWORD`,
`_NAME`), capturadas em variável de shell e nunca impressas:

```bash
NS=bahia-wordpress; POD=<pod do wordpress>
DBH=$(kubectl exec -n $NS $POD -c wordpress -- printenv WORDPRESS_DB_HOST | tr -d '\r\n')
DBU=$(kubectl exec -n $NS $POD -c wordpress -- printenv WORDPRESS_DB_USER | tr -d '\r\n')
DBP=$(kubectl exec -n $NS $POD -c wordpress -- printenv WORDPRESS_DB_PASSWORD | tr -d '\r\n')
DBN=$(kubectl exec -n $NS $POD -c wordpress -- printenv WORDPRESS_DB_NAME | tr -d '\r\n')

kubectl run mysqldump-full-$$ -n $NS --rm -i --restart=Never --image=mysql:8.0.31 \
  --env="MYSQL_PWD=${DBP}" --command -- \
  mysqldump -h "$DBH" -u "$DBU" "$DBN" \
    --single-transaction --quick --no-tablespaces --routines --triggers \
    --default-character-set=utf8mb4 --hex-blob --set-gtid-purged=OFF \
  2> backup.err | gzip -6 > backup-hml-pre-import-YYYYMMDD-HHMM.sql.gz
```

`MYSQL_PWD` via `--env` mantém a senha fora da linha de comando dentro do contêiner, mas ela
fica no *spec* do pod enquanto ele existe. O pod é efêmero (`--rm`) e o banco é o de homolog;
para produção, use um Secret.

**Redirecione o stderr para arquivo** (`2> backup.err`): sem isso, uma mensagem de erro entra
no `stdout`, o `gzip` a comprime, e sobra um `.gz` de poucas centenas de bytes com cara de
arquivo válido. Guarde o dump **fora do cluster** e confira o tamanho antes de seguir.

Se o tempo do dump completo for proibitivo, `wp_actionscheduler_actions` (1,8 GB) e
`wp_actionscheduler_logs` (562 MB) são fila de tarefas, não conteúdo: excluí-las corta 2,4
GB. **Só faça isso se o item 1a tiver sido concluído**, porque aí deixa de ser backup
completo.

### 1b-2. Verificar o dump — quatro conferências, nenhuma opcional

```bash
ls -lh backup-*.sql.gz            # tamanho plausível, não centenas de bytes
gzip -t backup-*.sql.gz           # integridade do compactado, silêncio = OK
gunzip -c backup-*.sql.gz | tail -5 | grep -c "Dump completed"   # tem de ser 1
gunzip -c backup-*.sql.gz | grep -m1 "'siteurl'"                 # hml.bahia.ba
```

A terceira é a que pega o dump **truncado por queda de conexão**: o `mysqldump` só escreve
`-- Dump completed on ...` na última linha se chegou ao fim. Um arquivo grande, que abre,
que descompacta — e sem essa linha — é um backup pela metade.

A quarta é a guarda contra dumpar o banco errado: o banco de homolog **se chama `prod`**, e
só o `siteurl` distingue.

### 1c. Marco no git

```
git tag -a pre-import-conteudo-YYYYMMDD -m "Estado do código antes do import de conteúdo de produção"
git push origin pre-import-conteudo-YYYYMMDD
```

O git versiona código, não banco. A tag serve para amarrar o backup do banco a um estado
conhecido do código — anote no documento de restauração qual tag corresponde a qual arquivo
de dump.

### 1d. Documento de restauração

Antes do import, escreva `RESTAURACAO-import-YYYYMMDD.md` com, no mínimo:

1. Identificador do snapshot do RDS e/ou caminho e soma de verificação do `.sql.gz`;
2. A tag do git correspondente;
3. Comando exato de restauração, já preenchido, pronto para colar;
4. Como confirmar que a restauração deu certo: `siteurl` = `https://hml.bahia.ba`,
   `SELECT COUNT(*) FROM wp_posts WHERE post_type='tdb_templates'` = **13**,
   `page_on_front` = **547432**, home renderizando;
5. Quem executa e quem valida.

---

## 1.5 A ORIGEM: dump de produção entregue, não leitura ao vivo

**Decidido em 16/08/2026.** Este documento especificava tudo sobre o destino e **nada sobre a
origem** — falha registrada aqui para não se repetir na virada.

Produção **não é lida ao vivo**. Gera-se um dump uma vez, e todas as fases (dry-run,
import, conferência) leem **desse arquivo**.

> **Quem executa:** o dump é gerado por `kubectl` contra o cluster de produção, mediante
> autorização explícita e pontual — decidido em 17/08/2026. É a **única** operação deste
> roteiro que toca produção, e é somente leitura: `mysqldump --single-transaction`, sem
> `--lock-all-tables` e sem `--master-data`, portanto sem travar tabela num banco em
> operação. Nada é escrito lá em nenhuma hipótese.

### Por que, e este é o motivo mais forte

**Dry-run e import precisam ler o mesmo estado.** Produção publica ~166 IDs por dia. Lendo ao
vivo, o dry-run mediria um estado e o import escreveria a partir de outro, com matérias novas
no meio — e o relatório do dry-run, que existe para prever exatamente o que vai acontecer,
chegaria desatualizado ao momento de valer. Com o dump, as duas fases leem o mesmo estado
congelado.

Somam-se: nenhuma consulta no banco de um portal em operação (que já foi derrubado por uma
consulta uma vez), e o arquivo vira o **registro do que exatamente foi importado**.

**Isto vale para a virada também.** Qualquer sincronismo futuro prod→homolog usa dump, não
leitura ao vivo, pelo mesmo motivo.

### O que se pede: dump COMPLETO, não filtrado

Contraintuitivo, e é de propósito. Um dump filtrado teria de acertar, **numa única tentativa e
numa máquina em que não se pode testar**: a lista de post types, o corte de ID, os anexos
referenciados, a subconsulta dos `postmeta`, as relações de taxonomia e os termos novos.
Qualquer omissão é **silenciosa** — é a falha do `_subtitulo`, multiplicada por seis.

E se o filtro sair errado, o dump tem de ser refeito contra uma produção que já andou —
destruindo exatamente a propriedade de estado congelado que justifica o método.

O recorte é feito **no destino**, onde é gratuito, repetível e testável. Custo: um arquivo de
~600 MB comprimido e uma execução mais longa, uma única vez.

### O comando, para rodar contra produção

Cluster `bahia-eks-prod`, namespace `bahia-wordpress`, pods `wordpress-6b79965656-*`.
**Use `--context` explícito**, sem trocar o contexto ativo — nesta sessão o contexto já
mudou sozinho de homolog para prod uma vez.

```bash
CTX="arn:aws:eks:us-east-1:774710032593:cluster/bahia-eks-prod"
NS=bahia-wordpress
POD=$(kubectl --context "$CTX" -n $NS get pods -l app=wordpress \
        -o jsonpath='{.items[0].metadata.name}')
STAMP=$(date "+%Y%m%d-%H%M")
OUT=~/BAHIABA-backups/dump-PRODUCAO-$STAMP.sql.gz
ERR=~/BAHIABA-backups/dump-PRODUCAO-$STAMP.err

DBH=$(kubectl --context "$CTX" exec -n $NS $POD -c wordpress -- printenv WORDPRESS_DB_HOST | tr -d '\r\n')
DBU=$(kubectl --context "$CTX" exec -n $NS $POD -c wordpress -- printenv WORDPRESS_DB_USER | tr -d '\r\n')
DBP=$(kubectl --context "$CTX" exec -n $NS $POD -c wordpress -- printenv WORDPRESS_DB_PASSWORD | tr -d '\r\n')
DBN=$(kubectl --context "$CTX" exec -n $NS $POD -c wordpress -- printenv WORDPRESS_DB_NAME | tr -d '\r\n')

kubectl --context "$CTX" run mysqldump-prod-$$ -n $NS --rm -i --restart=Never \
  --image=mysql:8.0.31 --env="MYSQL_PWD=${DBP}" --command -- \
  mysqldump -h "$DBH" -u "$DBU" "$DBN" \
    --single-transaction --quick --no-tablespaces --routines --triggers \
    --default-character-set=utf8mb4 --hex-blob --set-gtid-purged=OFF \
  2> "$ERR" | gzip -6 > "$OUT"
```

Os quatro cuidados, todos já pagos em homolog:

1. **`mysql:8.0.31`, nunca `mysql:8`.** Desde o 8.0.32 o `mysqldump` exige `RELOAD` com
   `--single-transaction`, e o RDS não concede. A imagem `mysql:8` traz o 8.4 e falha com
   `Access denied ... FLUSH TABLES WITH READ LOCK (1045)`.
2. **`--single-transaction`** dá leitura consistente **sem travar tabela** — obrigatório num
   banco em operação. **Nunca** `--lock-all-tables` nem `--master-data`.
3. **`2> arquivo.err`.** Sem isso, a mensagem de erro entra no `stdout`, o `gzip` a comprime,
   e sobra um `.gz` de poucas centenas de bytes com cara de arquivo válido.
4. **Sem `--events`**: exige privilégio próprio e o banco não tem eventos.

### Verificação antes de entregar

As mesmas quatro de homolog, **com o `siteurl` invertido**, mais duas próprias da origem:

```bash
cd ~/BAHIABA-backups; F=dump-PRODUCAO-<STAMP>.sql.gz
ls -lh  $F                                             # centenas de MB, não de bytes
gzip -t $F                                             # silêncio = íntegro
gunzip -c $F | tail -3 | grep "Dump completed"         # prova que chegou ao fim
gunzip -c $F | grep -m1 "'siteurl'"                    # tem de ser https://bahia.ba
gunzip -c $F | grep -c "^CREATE TABLE"                 # anotar, para conferir depois
gunzip -c $F | grep -c "'_subtitulo'"                  # > 0: o par do ACF veio junto
```

A terceira pega o dump truncado por queda de conexão. A quarta é a guarda de identidade — e
aqui ela é **invertida**: se aparecer `hml.bahia.ba`, dumpou o banco errado.

### Onde deixar o arquivo

`~/BAHIABA-backups/`, junto dos dumps de homolog. Nome com **`PRODUCAO`** em maiúsculas, para
que ninguém o confunda com um backup de homolog num momento de pressa.

> **Este arquivo contém `DROP TABLE` de todas as tabelas de produção.** Canalizado para um
> cliente `mysql` apontado para o host errado, destrói aquele banco. Depois de verificar,
> deixe-o somente-leitura: `chmod 444`.

### O recorte: só matérias NOVAS

O import traz **apenas o que não existe aqui** — `ID > 547278`, o último ID do retrato de
28/07. Matérias que já existem em homolog e foram **editadas** em produção desde então
**não** são atualizadas: isso seria `UPDATE`, ou seja, replace, que está vedado.

Consequência aceita: homolog fica com a versão de 28/07 dessas matérias. Para o propósito —
validar o tema novo contra conteúdo real — não faz diferença. Na virada, o sincronismo é
completo e o problema não existe.

---

## 1.6 BLOQUEIO: os IDs de TERMO também colidem

Mesma família do `_subtitulo` e do `page_on_front`: o post vem, a dependência não vem, e a
falha é silenciosa. Medido em homolog em 16/08/2026.

### O que foi medido

`wp_terms.MAX(term_id)` = **78523**, `AUTO_INCREMENT` = **78524**. Os quatro IDs do topo
**nasceram em homolog**, não vieram do retrato:

| term_id | tt_id | Taxonomia | Nome | Relações |
|---|---|---|---|---|
| 78520 | 78520 | `category` | Featured (resíduo do demo Magazine PRO) | 0 |
| **78521** | 78521 | `nav_menu` | **Principal** | **10 itens** |
| **78522** | 78522 | `nav_menu` | **Rodapé** | **10 itens** |
| 78523 | 78523 | `nav_menu` | Rodapé Legal | 2 itens |

Abaixo de 78520 tudo é tag editorial vinda do retrato (78519 é
`municipios_tag "esgotamento sanitário"`).

Os dois bancos seguiram de 78519 **de forma independente**. Produção vem criando tags desde
28/07 a partir de 78520 — exatamente onde homolog pôs os menus.

**Um import que preserve `term_id` sobrescreve os três menus e a categoria do demo.** O
cabeçalho e o rodapé perdem a navegação, e nada acusa erro. É o cenário do `page_on_front`
por outro caminho.

`term_taxonomy_id` tem os mesmos valores e o mesmo `AUTO_INCREMENT`, e é ele que
`wp_term_relationships` referencia — os dois colidem.

### Dependências dos quatro, levantadas

- `wp_terms.term_id` e `wp_term_taxonomy.term_id` / `term_taxonomy_id`
- `wp_term_relationships.term_taxonomy_id` — **22 linhas** (10 + 10 + 2)
- `wp_options.theme_mods_Newspaper[nav_menu_locations]` = `{"header-menu":78521,"footer-menu":78522}`
- `wp_termmeta`: **0** · filhos por `parent`: **0** · referências no `td_011`: **0**
- Backups históricos em `wp_options` citam os IDs — **não atualizar**, mesma regra da
  renumeração de posts

### O conserto, antes do import

Mesma solução dos posts, em escala mínima: mover os quatro para faixa reservada.

**`novo = 9.100.000 + (antigo − 78.519)`** → 78520→9100001, 78521→**9100002**,
78522→**9100003**, 78523→9100004.

Sub-faixa própria (9.1xx) para manter legível de que objeto se fala: **≥ 9.000.000 nasceu em
homolog**, 9.0xx é post, 9.1xx é termo. `term_id` é `bigint(20) unsigned`, então não custa
nada.

São 4 termos, 4 linhas de `term_taxonomy`, 22 relações e 1 chave de option. Ordem, transação
e travas idênticas às de `RENUMERACAO-homolog.md` §4, com `AUTO_INCREMENT` de `wp_terms` e
`wp_term_taxonomy` fixado em **9100005** depois.

**Validação própria:** menu do cabeçalho e do rodapé renderizando com os itens certos, e
`nav_menu_locations` apontando para os IDs novos.

---

## 1.7 `wp_as3cf_items` entra no import — sem ela o anexo existe e a imagem não carrega

As matérias novas referenciam imagens enviadas em produção depois de 28/07. Esses anexos são
posts com `ID > 547278` e entram no import; mas o caminho no S3 mora em `wp_as3cf_items`, e
**sem essa linha o anexo existe e a imagem não aparece**.

Estado em homolog: **155.595 linhas** para 155.670 anexos; `MAX(id)` = 157.752,
`AUTO_INCREMENT` = 157.753; `MAX(source_id)` = 9.000.188 (os anexos já renumerados).

**Como inserir**, e o detalhe que evita repetir a colisão por um terceiro caminho:

- **Não preservar a coluna `id`** — é chave primária própria da tabela, com
  `AUTO_INCREMENT` próprio, e colidiria com as 155 mil linhas daqui. Omitir a coluna e
  deixar o MySQL atribuir.
- **Preservar `source_id`**, que é o ID do anexo — esse é o vínculo que importa.
- Trazer `provider`, `region`, `bucket`, `path`, `original_path`, `source_type`,
  `source_path`, `original_source_path`, `extra_info`, `is_private`, `is_verified`.
- A tabela tem índice único **`uidx_source (source_type, source_id)`**, que serve de chave
  natural de idempotência: reexecutar o import não duplica.

**Verificação pós-import:** as fotos das matérias novas carregando pelo CloudFront, e

```sql
SELECT COUNT(*) FROM wp_posts p
 LEFT JOIN wp_as3cf_items a ON a.source_id=p.ID AND a.source_type='media-library'
 WHERE p.post_type='attachment' AND p.ID > 547278 AND a.id IS NULL;
```

que tem de voltar **0** — anexo importado sem linha de offload.

---

## 2. Dry-run — obrigatório, antes de escrever qualquer linha

O dry-run **não escreve nada**. Produz um relatório com:

1. **Quantos registros entrariam, por post type.** Confrontar com o inventário abaixo.
2. **A lista de IDs em conflito** — todo ID de produção que já exista em homolog. Se essa
   lista incluir qualquer um dos 121 da seção 0, **pare**.
3. **Quantos `wp_postmeta` viriam**, e quantos deles são `subtitulo` e `_subtitulo`. Os dois
   números têm de ser **iguais**.
4. **Anexos referenciados** pelos posts que entram e que ainda não existem aqui.
5. **Termos e taxonomias** que precisariam ser criados.
6. **O que seria ignorado**, e por quê — esta lista é tão importante quanto a do que entra.
7. **Quantos anexos entram**, e quantos deles têm linha em `wp_as3cf_items` no dump (§1.7).
   Os dois números têm de ser iguais.
8. **Quantos termos novos entram**, por taxonomia, e **qual o menor `term_id` deles** — se
   for ≤ 78523, a colisão de §1.6 é real e a renumeração de termos vem antes.
9. **Quantas `term_relationships` apontariam para `term_taxonomy_id` inexistente aqui** se os
   termos não viessem junto. Este número é o que diz se §1.6 é problema concreto ou teórico:
   se for maior que zero, tags de matérias novas ficariam órfãs, e as matérias apareceriam
   sem editoria e sem tag.

### Tipos que ENTRAM

```
politica, salvador, bahia, municipios, justica, especial, exclusivo, esporte,
brasil, entretenimento, mais_gente, entrevista, economia, mundo, mais_noticias,
artigo, carnaval, dende_poder, covid19, saude, social,
eleicoes2016, eleicoes2018, eleicoes2020, eleicoes2022, eleicoes2024,
carnaval2017, carnaval2018, carnaval2019
```

Mais `attachment`, restrito aos anexos referenciados pelos posts que entram.

`guest-author` (259 registros, Co-Authors Plus) **ENTRA** — decidido em 16/08/2026. Sem
eles as matérias novas chegam sem assinatura, e assinatura é conteúdo editorial. Junto com
os posts do tipo, trazer as relações de taxonomia `author` que ligam matéria e autor: um
`guest-author` importado sem a relação não aparece na matéria nem na página de autor.

### Tipos que NÃO ENTRAM, em hipótese alguma

| Tipo | Registros em homolog | Motivo |
|---|---|---|
| `tdb_templates` | 13 | os templates do Newspaper — onze rodadas de trabalho |
| `tdc-review`, `tdc-review-email` | 0 | tipos internos do tagDiv |
| `page` | 17 | inclui Quem Somos (547369), Home (547432, 547408), Brasileirão 2026 (547358) |
| `acf`, `acf-field`, `acf-field-group` | 14 / 36 / 14 | **as definições de campo do ACF moram em `wp_posts`.** Import aditivo duplicaria os grupos e a tela de edição passaria a mostrar cada campo duas vezes |
| `nav_menu_item` | 22 | os menus foram remontados aqui |
| `wp_navigation`, `wp_block` | 1 / 0 | internos do WordPress |
| `revision` | 3696 | não é conteúdo publicado |
| `foogallery`, `envira`, `ngg_*` | — | galerias legadas, sem uso no tema novo |

A página **Quem Somos (547369)** está na exclusão por pedido explícito: ela foi construída
em homolog, com as 22 fotos da equipe, e não existe em produção nessa forma.

---

## 3. Import

**Só depois da decisão da seção 0 e do backup da seção 1.**

Regras que não se negociam:

- **Nunca** `DROP TABLE`, `TRUNCATE`, `REPLACE INTO`, `--add-drop-table` ou
  `mysqldump | mysql` de tabela inteira. O import é linha a linha, filtrado.
- **Aditivo.** Registro que já existe em homolog não é alterado.
- **Filtrado por post type**, pela lista da seção 2 — nunca por "tudo menos alguns".
  Lista de inclusão, não de exclusão: um post type novo criado em produção não deve entrar
  por acidente.
- **`_subtitulo` sempre junto com `subtitulo`.** São 273.656 linhas de cada em homolog, e o
  ponteiro vale `field_55f56f4d66ac0`. Trazendo só o valor, o site funciona — os filtros do
  `bahia-subtitulo.php` leem a meta key simples, sem API do ACF — mas **o campo aparece
  vazio na tela de edição** e o repórter reescreve ou perde o texto ao salvar. É a falha
  mais silenciosa deste import. O mesmo vale para os pares `_imagem`/`imagem`,
  `_exclusivo`/`exclusivo` e demais campos ACF.
- Trazer também `_yoast_wpseo_metadesc` quando existir. Os filtros novos respeitam a
  description escrita à mão e usam o subtítulo só como reserva.

---

## 4. Depois do import

### 4a. Duplicação de meta — o teste que pega o erro mais provável

```sql
SELECT post_id, meta_key, COUNT(*) c
FROM wp_postmeta
WHERE meta_key IN ('subtitulo','_subtitulo','imagem','_imagem','_thumbnail_id','_yoast_wpseo_metadesc')
GROUP BY post_id, meta_key HAVING c > 1
LIMIT 50;
```

**Tem de voltar vazio.** `get_post_meta($id, $chave, true)` devolve a primeira linha, então
uma duplicata faz o site exibir o subtítulo antigo sem erro nenhum, indefinidamente.

Confirmar também que a paridade se manteve:

```sql
SELECT
 (SELECT COUNT(*) FROM wp_postmeta WHERE meta_key='subtitulo')  AS valor,
 (SELECT COUNT(*) FROM wp_postmeta WHERE meta_key='_subtitulo') AS ponteiro;
```

Os dois números têm de ser iguais. Hoje são 273.656.

### 4b. Reconstruir o índice do Yoast

Homolog **já tem 27.961 posts sem linha em `wp_yoast_indexable`** (302.510 linhas para
272.001 posts publicados, com sobreposição de outros tipos), e o import amplia isso.

Isso **não** afeta a description: foi testado no post #44811, que não tem indexable, e as
três meta tags saíram com o subtítulo — o Yoast constrói o indexable sob demanda. Mas afeta
sitemap e outros consumidores.

Rodar pelo painel: **Yoast SEO → Ferramentas → Otimizar dados de SEO**. Em base deste
tamanho o processo é longo; acompanhar até concluir.

### 4c. Verificação funcional

Nenhum destes itens é opcional. Comparar sempre com o estado validado, não com "parece bem".

| # | O que conferir | Como saber que está certo |
|---|---|---|
| 1 | Templates renderizando | `SELECT COUNT(*) FROM wp_posts WHERE post_type='tdb_templates'` = **13**; cabeçalho, rodapé, single, busca, 404 e archive de editoria abrindo |
| 2 | Quem Somos | `/quem-somos/` com as **22 fotos** da equipe |
| 3 | Home | `page_on_front` = **547432** e a home abrindo nela |
| 4 | Slots de publicidade | os `data-grupo="N"` presentes; grupos 1, 12 e 14 no topo, 8 e 9 na barra lateral |
| 5 | Imagens | miniaturas carregando nos cards, sem quebra; conferir um post importado e um antigo |
| 6 | Selo EXCLUSIVO | aparecendo nos posts com o campo marcado |
| 7 | Página de autor | `/author/<slug>/` listando matérias, inclusive as importadas |
| 8 | **Subtítulo no single** | `<p class="td-post-sub-title">` presente, **1** ocorrência, em Roboto 20px |
| 9 | **Subtítulo nos cards da home** | resumo do card = chamada do repórter, não o primeiro parágrafo |
| 10 | **Meta description** | `description`, `og:description` e `twitter:description` com o subtítulo, num post **importado** |
| 11 | Contadores do painel | 70 no título e 160 no subtítulo, numa matéria importada |
| 12 | Desktop e celular | idênticos ao estado validado; comparar com as capturas das rodadas 7 a 11 |

Os itens 8 a 11 exercitam os filtros novos sobre conteúdo importado — que é o ponto ainda
não exercitado na prática, ainda que o teste do post sem indexable indique que funciona.

### 4e. O que NÃO entra: `wp_historico_destaques`

É registro de auditoria da home **de produção**, não conteúdo — ver §5. E tem uma
**chave estrangeira real** para `wp_posts.ID` (`fk_posts_historico_destaques`, confirmada em
`information_schema`). Suas 405.319 linhas referenciam posts de todo o acervo; importá-las
com o recorte `ID > 547278` violaria a FK na primeira linha que apontasse para um post que
não veio.

A mesma FK é a razão de conferir, antes de qualquer exclusão em massa de posts em homolog,
se há linha correspondente aqui — o banco vai recusar o `DELETE`.

### 4d. Se algo der errado

**Restaurar pelo backup da seção 1. Não consertar por cima de um import malsucedido.**

Um import parcial deixa o banco num estado que ninguém mapeou: metas órfãs, anexos sem
post, termos sem relação, IDs meio trocados. Consertar por cima cria uma segunda camada de
suposições sobre a primeira, e o que se ganha em tempo se perde na primeira coisa
inexplicável que aparecer semanas depois.

Restaurar, entender o que falhou, corrigir o procedimento e repetir do zero.

---

## 5. Fase 7 — PARIDADE DO CAMINHO DE PUBLICAÇÃO

**Só depois do import**, porque comparar fluxo exige matéria real de produção em homolog.

**É análise e relatório. Não altera nada.** As divergências evitáveis viram decisão do
responsável, não ajuste automático.

### 5.1 Por que esta fase existe

O motivo principal de trazer conteúdo de produção não é validar exibição — isso os 12 itens
da §4c já cobrem. É validar que **publicar no Newspaper PRO se pareça com publicar no
bahia_refactor**, para a redação ter a menor curva de aprendizagem possível na virada.

Uma migração que preserva o site e troca o fluxo de trabalho da redação transfere o custo
para quem publica todo dia.

### 5.2 O que já se sabe, antes de olhar

Levantado em 16/08/2026, e serve de ponto de partida — não substitui o mapeamento.

| Etapa do repórter | Produção (bahia_refactor) | Homolog (Newspaper PRO) | Situação |
|---|---|---|---|
| Editor de texto | Clássico | **Clássico** (os 18 CPTs têm `show_in_rest=false`) | igual |
| Onde escreve o título | campo nativo | campo nativo | igual |
| **Subtítulo** | ACF `subtitulo` | **mesmo campo ACF** | igual |
| **Editoria** | o CPT escolhido | **mesmo CPT** | igual |
| **Posicionamento na home** | ACF Options (`options_slider_m1`, `options_semi_destaques_m1`) | **mesma Options page** — `bahia-home-destaques.php` lê exatamente essas chaves | igual |
| Selo EXCLUSIVO | ACF `exclusivo` | mesmo campo | igual |
| Imagem destacada | ACF `imagem` | **mesmo campo**, com `acf-imagem-featured.php` alimentando o `_thumbnail_id` que o tema novo lê | igual, com adaptador |
| Coautoria | Co-Authors Plus | Co-Authors Plus | igual |
| **Contadores de 70/160** | não existem | **novos** | acréscimo |

A hipótese de trabalho, portanto, é que o caminho é **majoritariamente o mesmo**, porque as
decisões de arquitetura preservaram os campos do fluxo antigo. A fase existe para
**confirmar isso com o repórter em mente**, e achar o que escapou.

### 5.3 O que mapear, dos dois lados, com o mesmo detalhamento

Do ponto de vista de quem publica, não de quem programa:

1. Quais campos preenche, **em que ordem** aparecem na tela
2. Quais são **obrigatórios** e o que acontece ao salvar sem eles
3. Onde define a **editoria**
4. Onde define o **posicionamento na home**
5. Onde marca **EXCLUSIVO**
6. Onde põe o **subtítulo**
7. Como escolhe a **imagem destacada**
8. Como atribui **coautoria**
9. Quantos cliques e rolagens da abertura da tela até publicar
10. O que a tela mostra de ruído — avisos de plugin, caixas irrelevantes

### 5.4 O comparativo

Lado a lado, apontando **cada** divergência: campo que mudou de lugar, campo que sumiu,
campo que apareceu, passo a mais, passo a menos, nome diferente para a mesma coisa.

Cada divergência recebe uma classificação:

- **(a) inevitável** — decorre de o tema ser outro
- **(b) evitável** — dá para aproximar do fluxo antigo com ajuste em homolog
- **(c) melhoria** — vale manter mesmo sendo diferente

**As do tipo (b) são decisão do responsável.** Reportar o comparativo e parar.

### 5.5 Uma observação já visível

A tela de edição carrega **três avisos de plugin** no topo — AdRotate ("105 adverts
expired", "banner folder not writable"), FooGallery (oferta de teste) e WP Twitter Auto
Publish (pedido de avaliação). Empurram o campo de título para baixo da dobra.

É ruído que existe **hoje em homolog** e que a redação vai encontrar na virada. Entra no
comparativo como divergência a classificar, não como defeito a corrigir por conta própria.

### 5.6 Os avisos de plugin na tela de edição — itens de AÇÃO, não de classificação

**Reclassificado em 16/08/2026, por decisão do responsável.** Não são "divergência a
classificar": são **atrito diário**, em cada matéria, do tipo que faz a equipe achar o tema
novo pior sem conseguir explicar por quê.

Registrar no comparativo que **esta é a divergência que a redação mais vai sentir na virada,
e ela não vem do tema — vem dos plugins.** O Newspaper PRO não tem culpa nenhuma; os avisos
existem hoje e estariam lá com qualquer tema.

São três, e o tratamento **não é o mesmo para os três**.

#### (1) e (3) Propaganda e ruído administrativo — suprimir para quem não é administrador

- **FooGallery** — oferta de teste grátis de recursos premium
- **WP Twitter Auto Publish** — pedido de avaliação com cinco estrelas e link de doação
- **AdRotate "105 adverts expired"** — informação **legítima**, mas endereçada ao comercial,
  não a quem escreve matéria

Abordagem proposta, **a reportar antes de aplicar**: mu-plugin que remove esses avisos
**apenas quando o usuário não tem `manage_options`**, e **apenas nas telas de edição de
post** (`post.php`, `post-new.php`). Administrador e comercial continuam vendo tudo, em
todas as outras telas.

O ponto delicado é o método: `admin_notices` é um `do_action`, e remover aviso alheio exige
saber o `callback` que cada plugin registrou. Um `remove_action` por nome é frágil — quebra
em silêncio na próxima atualização do plugin, e volta o ruído sem ninguém notar. A
alternativa é filtrar por conteúdo no buffer, o que é robusto mas é reescrita de HTML.
**A escolha entre as duas é o que precisa ser reportado antes.**

#### (2) AdRotate "banner folder not writable" — INVESTIGADO, é seguro esconder

Investigado antes de propor qualquer coisa, porque poderia ser configuração real quebrada
afetando o upload de criativo pelo comercial. **Não é.**

| Verificação | Resultado |
|---|---|
| `adrotate_config[banner_folder]` | `'banners'` → `/var/www/html/wp-content/banners` |
| A pasta existe? | **NÃO** — daí o aviso, que está tecnicamente correto |
| `wp-content/uploads` | existe, `755`, `www-data:www-data`, **gravável** |
| Anúncios ativos | **5** |
| Usando a pasta `banners` | **0** |
| Usando `uploads` (via CloudFront) | **5**, todos com `imagetype='field'` |

O fluxo real do comercial é: sobe o criativo na **Biblioteca de Mídia** (que é gravável e
vai para o S3/CloudFront) e cola a URL no campo do anúncio. A pasta própria do AdRotate
**nunca foi usada**.

Esconder o aviso, portanto, **não esconde fluxo quebrado** — esconde a reclamação de um
recurso que ninguém usa.

**Não criar a pasta.** Em `wp-content` só `uploads` é volume montado; o resto vem da imagem
Docker. Uma pasta criada em runtime some no próximo restart do pod, e o aviso volta.
Criá-la de verdade significaria alterar o `Dockerfile` para habilitar um recurso que não se
usa — custo sem benefício.

**Se um dia o comercial quiser usar o upload nativo do AdRotate**, aí sim: pasta no
`Dockerfile`, ou repontar `banner_folder` para dentro de `uploads`. Aí o aviso volta a ser
útil e não deve ser suprimido.
