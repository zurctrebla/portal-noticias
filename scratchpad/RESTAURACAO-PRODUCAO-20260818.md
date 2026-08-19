# Restauração de PRODUÇÃO — backup da fase 0 da virada do tema

> ## ⚠️ REVISADO em 18/08/2026, fora de janela. Três correções que mudam o rollback.
>
> ### 1. O dump das 07:07 é ANTERIOR à fase 3
>
> Ele **não** contém a migração de banco. Restaurá-lo desfaz:
>
> | | |
> |---|---|
> | posts na faixa 9.000.000+ | **79** |
> | postmeta | **466** |
> | termos 9.100.000+ / relações | **3** / **22** |
> | `wp_as3cf_items` | **41** |
> | `wpseo_titles` | volta de **1.115** para **991** chaves |
>
> **Passo extra obrigatório depois de restaurar este dump:** reaplicar o payload congelado
> `scratchpad/f3-payload-20260818.json`, com o script de blocos da fase 3. Sem isso o banco fica
> sem os templates, as páginas, os anexos e os menus da virada — e a próxima tentativa começa do
> zero.
>
> A parte boa: **nenhum acervo editorial se perde**. Conferido em 18/08 — `MAX(wp_posts.ID)`
> continua 550709 e zero posts foram publicados desde o dump.
>
> ### 2. A imagem de rollback é OUTRA — e mudou de novo em 18/08 às 11:13
>
> | Tag | O que é | Quando usar |
> |---|---|---|
> | **`prod-7bd0c9f673eae50346c7e8c10eaec851a3c0d84e`** | **A que está no ar** (revisão 41, 19/08 08:3x, depois da virada). É a 40 mais `author_base = colunistas` e os portes de feeds, push do app e XML-RPC | **É esta a imagem atual.** |
> | **`prod-de88838fa1332f1d1ee1a665d95d9750e6267ff9`** | revisão 40, 18/08 22:13 — o estado imediatamente ANTES da virada de 19/08 | **É esta a imagem de ROLLBACK — ver a seção 3-A.** Voltar para ela devolve `/colunistas/` a 404 e religa os feeds padrão |
> | `prod-cce2320089df02bafaaf8af7e9a241d173646dfb` | revisão 39, 18/08 11:13 — a 38 mais a guarda de tema do `bahia-remove-dup-featured.php` | ⚠️ **devolve o defeito do "+ Mais Lidas"**, que só aparece com o Newspaper ativo |
> | `prod-5bf7a8ed…` | revisão 38, 10:23 — Dendê, a arte do título saindo do repositório para a Biblioteca de Mídia | ⚠️ **devolve o defeito da foto** (ver abaixo) |
> | `prod-a8def0c4…` | revisão 37, 10:12 — Dendê, o título do archive virando a arte da marca | ⚠️ **devolve o defeito da foto** |
> | `prod-19d16299…` | revisão 36, 09:32 — tema Newspaper no disco e **inativo**, os 4 mu-plugins de correção de consulta | ⚠️ **devolve o defeito da foto** |
> | `prod-071af82c…` | revisão 35, antes das correções de consulta | Só se as correções de consulta se mostrarem o problema. ⚠️ idem |
> | `prod-77b43a46…` | revisão 34, **anterior à fase 2** | Tira do disco o tema, os 31 mu-plugins e os plugins tagDiv — desfaz muito mais que a virada. Último recurso. **Não** tem o defeito, porque não tem os mu-plugins |
>
> **Por que voltar para 35, 36, 37 ou 38 quebra o site.** Nessa faixa o
> `bahia-remove-dup-featured.php` já está no disco e ainda **sem** a guarda de tema. Ele foi
> escrito para o Newspaper, que redesenha a imagem destacada no topo; com o `bahia_refactor`
> ativo, que não redesenha, ele apaga a única foto da matéria — e o crédito junto. Em 18/08 isso
> derrubou a foto de **todo** o acervo, em desktop e celular, até a revisão 39. Se a janela
> exigir uma dessas imagens, o passo seguinte é obrigatório: copiar o
> `mu-plugins/bahia-remove-dup-featured.php` da `main` para os pods, ou o defeito volta com ela.
>
> A seção 3 abaixo, escrita na fase 0, cita a `prod-77b43a46…` como "a" imagem de rollback.
> **Está desatualizada**: era verdade antes da fase 2.
>
> ### 3. Se a próxima janela for em outro dia, tire dump e snapshot NOVOS
>
> Um dump posterior à fase 3 já inclui os 79 posts e a `wpseo_titles` em 1.115, e o rollback
> volta a ser um passo só. Os atuais continuam válidos como rede de segurança mais antiga —
> **não apagar nenhum snapshot.**

**Gerado em:** 18/08/2026, 07:07–07:11 (horário local; o dump registra `6:11:11` em UTC)
**Contexto:** fase 0 da virada do tema `bahia_refactor` → `Newspaper` em produção.

---

## 0. Existem DUAS cópias, e elas servem a coisas diferentes

Não são redundantes. Escolher a errada custa tempo na hora errada.

| | **Snapshot do RDS** | **Dump lógico** |
|---|---|---|
| Identificador | `bahia-prod-pre-virada-newspaper-20260817` | `~/BAHIABA-backups/dump-PRODUCAO-20260818-0707.sql.gz` |
| Instância / banco | `rds-bahiaba-2023`, MySQL 8.0.42 | banco `prod` da mesma instância |
| Criado | 18/08/2026 07:14 (UTC+01:00) — status **Disponível** | 18/08/2026 07:07–07:11 (UTC+01:00) |
| Para que serve | **desastre completo** | **desfazer alteração pontual** |
| Como restaura | cria uma **instância NOVA**; não sobrescreve a atual | escreve **por cima** da instância existente |
| Consequência | a aplicação precisa ser **repontada para o novo endpoint** | endpoint não muda, nada a repontar |
| Velocidade | minutos, íntegro | dezenas de minutos |

> Os dois retratam praticamente o mesmo instante: o dump terminou 07:11 e o snapshot foi
> disparado 07:14 (UTC+01:00), com o site em operação normal e o tema ainda `bahia_refactor`.

### Qual usar em qual cenário — decidir por aqui, não na hora

| Cenário | Caminho | Por quê |
|---|---|---|
| A virada saiu errada: tema, `td_011`, `theme_mods`, `show_on_front` | **Rollback por chave** (fase 4) | Segundos. Nem toca no backup. É o caminho normal |
| Rollback por chave não resolveu, mas o banco está íntegro | **Dump lógico** | Restaura por cima da instância existente; **endpoint não muda**, nada a repontar |
| Banco corrompido, instância inacessível, ou a restauração lógica falhou no meio | **Snapshot** | Único caminho que não depende do banco atual estar são |

**Por que o snapshot NÃO é o caminho preferido, apesar de mais rápido para restaurar.**
Restaurar um snapshot **não sobrescreve `rds-bahiaba-2023`** — cria uma **instância nova**, com
**endereço novo**. O site só volta depois de a aplicação apontar para ela, e apontar significa:

1. editar `WORDPRESS_DB_HOST` no namespace `bahia-wordpress` — hoje o valor é o **IP puro
   `172.31.70.197`**, não um nome DNS, então não há CNAME para repontar: é o manifesto que muda;
2. `rollout restart` dos pods, esperando o rollout completo (`maxSurge 1 / maxUnavailable 0`);
3. cache frio em todos os sidecars, porque `/tmp/nginx-cache` é `emptyDir`;
4. e, terminada a emergência, **desfazer tudo isso** para voltar à instância original — ou
   assumir a nova como definitiva, o que arrasta o Terraform junto.

Ou seja: a restauração do snapshot é rápida, mas **voltar ao ar não é** — a instância nova
ainda precisa terminar de subir, e os passos 1 a 3 são manuais e sequenciais. O dump lógico
demora mais para carregar e devolve o site no mesmo endpoint, sem tocar em manifesto nenhum.

> **A consequência prática:** o snapshot é rede de segurança de baixo, não plano A. Se o banco
> estiver são, use o dump.

### Outros snapshots na conta — NÃO APAGAR NENHUM durante a janela

Há mais 4 snapshots manuais. Um deles, **`bahiaba-para-hml` (28/07/2026)**, é o retrato que
originou o banco de homolog — é a referência de onde vieram os IDs até 547278 e a base de toda
a análise de colisão. Apagá-lo destruiria a única prova do estado de partida.

---

## 1. O arquivo

> ### ⚠️ USE O DUMP DE 19/08. O de 18/08 é ANTERIOR À FASE 3.
>
> Restaurar o dump de 18/08 hoje **desfaria a Fase 3**: os posts, postmeta, termos, relações e
> linhas de offload importados de homolog, e o `wpseo_titles` voltaria de 1.115 para 991
> chaves. Seria preciso reaplicar o payload à mão depois — um passo a mais, no pior momento.
>
> O dump de 19/08 **já inclui a Fase 3**: conferido no próprio arquivo (1.207 linhas com ID na
> faixa 9000xxx, os 13 `tdb_templates`, e os posts 9000142/9000079/9000124 presentes). Com ele,
> o rollback de banco volta a ser um passo só.
>
> O de 18/08 fica no disco como rede de segurança de baixo, não como plano A.

### 1.1 O dump da janela de 19/08 — **este é o de usar**

| Campo | Valor |
|---|---|
| Caminho | `~/BAHIABA-backups/dump-PRODUCAO-20260819-0711.sql.gz` |
| Tamanho | 547,0 MB (**573.512.316 bytes**) |
| SHA-256 | `64b1878151f209059a830350ad87daeb5c8b83691a6b7468254df934d9afc188` |
| stderr | `~/BAHIABA-backups/dump-PRODUCAO-20260819-0711.err` (283 B, só avisos do `kubectl`) |
| Permissão | `444` |
| Duração | 2 min 26 s (07:11:42 → 07:14:08) |
| Pod de origem | `wordpress-65f758cd58-tdsmw` |
| Conferência | `cd ~/BAHIABA-backups && shasum -a 256 -c sha256-dump-PRODUCAO-20260819-0711.sql.gz.txt` |

Estado do banco **dentro deste dump** (é o "antes" da virada, lido do próprio arquivo):

| chave | valor no dump |
|---|---|
| `siteurl` | `https://bahia.ba` |
| `template` / `stylesheet` | `bahia_refactor` / `bahia_refactor` |
| `show_on_front` | `posts` |
| `page_on_front` | `0` |
| `site_icon` | `0` |

> **Nota sobre o roteiro da janela:** ele pede conferir `page_on_front = 9000142` no dump. Não é
> isso que se pode conferir antes da Fase 1 — quem grava essa opção é a própria Fase 1. O que a
> Fase 3 deixou no banco, e que **está** no dump, é a **página 9000142 existindo** como post,
> junto dos templates 9000xxx. Foi isso que se verificou.

### 1.2 O dump de 18/08 — anterior à Fase 3, mantido por segurança

| Campo | Valor |
|---|---|
| Caminho | `~/BAHIABA-backups/dump-PRODUCAO-20260818-0707.sql.gz` |
| Tamanho | 548,8 MB (**575.463.723 bytes**) |
| SHA-256 | `99ecf335a08cb4f536f83af450a5b0dd55806f6f26f5d2918b7fd772b0b7bbe4` |
| stderr | `~/BAHIABA-backups/dump-PRODUCAO-20260818-0707.err` (216 B, só o aviso de gravação do `kubectl`) |
| Permissão | `444` — somente leitura, de propósito |
| Duração | 3 min 16 s |

Conferência do arquivo antes de usar:

```bash
cd ~/BAHIABA-backups
shasum -a 256 -c sha256-dump-PRODUCAO-20260818-0707.sql.gz.txt
```

> **Este arquivo contém `DROP TABLE` de todas as tabelas de produção.** Canalizado para um
> cliente `mysql` apontado para o host errado, destrói aquele banco. É por isso que está `444`.

## 2. O banco de origem

| Campo | Valor |
|---|---|
| Instância | **`rds-bahiaba-2023`** |
| Motor | MySQL 8.0.42, **RDS** (ENI `RDSNetworkInterface`, owner `amazon-rds`) |
| Endereço usado pelo pod | `172.31.70.197` — **IP puro, não endpoint DNS** |
| VPC do banco | `vpc-4c49202b` — o VPC **default** (172.31/16), separado do VPC do EKS (10.x) |
| `@@hostname` | `ip-10-1-4-202` |
| `innodb_buffer_pool_size` | 11.264 MB |
| Nome do banco | `prod` |
| `siteurl` | `https://bahia.ba` — confirmado no pod **e** dentro do dump |

> O banco de homolog **também** se chama `prod`. O que distingue os dois é o `siteurl`.
> Toda operação de escrita deve começar pela guarda de identidade.

## 3-A. Estado do código na janela de 19/08 — **e o alvo de rollback CORRETO**

> ### 🔴 NÃO reverter para `prod-19d16299` (revisão 36). Isso é regressão, não rollback.
>
> O roteiro da janela de 19/08 declara que produção está na revisão **36**, tag
> `prod-19d16299`. **Não está.** Medido no cluster em 19/08, antes de qualquer ação:
>
> | | briefing da janela | medido no cluster |
> |---|---|---|
> | revisão | 36 | **40** |
> | imagem | `prod-19d16299…` | **`prod-de88838f…`** |
> | initContainer | — | `prod-de88838f…` (o mesmo) |
>
> A revisão 40 subiu em 18/08 às 22:13 e contém três coisas que a 36 **não** tem:
>
> 1. **a guarda do `bahia-remove-dup-featured.php`** (entrou na revisão 39). Sem ela, com o
>    `bahia_refactor` ativo, **toda matéria perde a foto** — foi o incidente de 3 horas da manhã
>    de 18/08;
> 2. **a correção do `bahia-privacy-link-perf.php`**. Sem ela, o bloco "+ Mais Lidas" some da
>    home — e isso passa a valer **justamente com o Newspaper ativo**, que é o estado em que a
>    virada deixa o site;
> 3. a omissão das nove editorias no menu do painel.
>
> Voltar para a 36 no meio de um rollback devolveria os dois defeitos, em cima de um incidente.
>
> **O alvo correto é `prod-de88838f` (revisão 40) — que é o que já está rodando.** Como a
> virada **não troca a imagem** (ela é só banco), o rollback de código normalmente é **um
> no-op**: não há o que reverter. A linha abaixo existe só para o caso de alguém ter mexido na
> imagem durante a janela.

```bash
CTX="arn:aws:eks:us-east-1:774710032593:cluster/bahia-eks-prod"
IMG=774710032593.dkr.ecr.us-east-1.amazonaws.com/bahia-wordpress:prod-de88838fa1332f1d1ee1a665d95d9750e6267ff9

kubectl --context "$CTX" -n bahia-wordpress set image deploy/wordpress wordpress=$IMG
kubectl --context "$CTX" -n bahia-wordpress patch deploy wordpress --type=json \
  -p "[{\"op\":\"replace\",\"path\":\"/spec/template/spec/initContainers/0/image\",\"value\":\"$IMG\"}]"
kubectl --context "$CTX" -n bahia-wordpress rollout status deploy/wordpress
```

## 3. Estado do código no momento do backup de 18/08 (histórico)

| Campo | Valor |
|---|---|
| Imagem dos pods (container `wordpress`) | `774710032593.dkr.ecr.us-east-1.amazonaws.com/bahia-wordpress:prod-77b43a469b43d997fbc5da20719c1ddcf4e26d7f` |
| Imagem do initContainer `copy-wp-files` | a mesma (`prod-77b43a...`) |
| Sidecar `nginx` | `nginx:alpine` |
| `deployment.kubernetes.io/revision` | 34 |
| Pods | `wordpress-6b79965656-46lg5`, `wordpress-6b79965656-h6s6t` (2/2, sem restarts) |
| HPA | min 2 / max 5, rodando com 2 |
| Branch local | `staging`, em `ce58e0fe` |

> O manifesto versionado declara `prod-latest`; o que está **rodando** é a tag por SHA acima.
> É essa tag por SHA que serve de rollback — `prod-latest` já terá sido reescrita pelo deploy
> da fase 2.

### Rollback de código

```bash
CTX="arn:aws:eks:us-east-1:774710032593:cluster/bahia-eks-prod"
IMG=774710032593.dkr.ecr.us-east-1.amazonaws.com/bahia-wordpress:prod-77b43a469b43d997fbc5da20719c1ddcf4e26d7f

kubectl --context "$CTX" -n bahia-wordpress set image deploy/wordpress \
  wordpress=$IMG
kubectl --context "$CTX" -n bahia-wordpress set image deploy/wordpress \
  --init-containers copy-wp-files=$IMG 2>/dev/null || \
kubectl --context "$CTX" -n bahia-wordpress patch deploy wordpress --type=json \
  -p "[{\"op\":\"replace\",\"path\":\"/spec/template/spec/initContainers/0/image\",\"value\":\"$IMG\"}]"

kubectl --context "$CTX" -n bahia-wordpress rollout status deploy/wordpress
```

**O initContainer precisa voltar junto.** É ele que copia o `/var/www/html` da imagem para o
`emptyDir` compartilhado; deixá-lo em `prod-latest` faria o pod servir o código novo mesmo com
o container principal revertido.

Alternativa nativa, se a revisão 34 ainda estiver no histórico:
`kubectl --context "$CTX" -n bahia-wordpress rollout undo deploy/wordpress --to-revision=34`

## 4. Restauração do banco — comando pronto

**Último recurso.** Só se o banco ficar inconsistente e o rollback por chave (fase 4) não
resolver. Restaura o estado de 18/08 07:11 e **descarta tudo publicado depois disso**.

```bash
CTX="arn:aws:eks:us-east-1:774710032593:cluster/bahia-eks-prod"
NS=bahia-wordpress
POD=$(kubectl --context "$CTX" -n $NS get pods -l app=wordpress -o jsonpath='{.items[0].metadata.name}')

DBH=$(kubectl --context "$CTX" exec -n $NS $POD -c wordpress -- printenv WORDPRESS_DB_HOST | tr -d '\r\n')
DBU=$(kubectl --context "$CTX" exec -n $NS $POD -c wordpress -- printenv WORDPRESS_DB_USER | tr -d '\r\n')
DBP=$(kubectl --context "$CTX" exec -n $NS $POD -c wordpress -- printenv WORDPRESS_DB_PASSWORD | tr -d '\r\n')
DBN=$(kubectl --context "$CTX" exec -n $NS $POD -c wordpress -- printenv WORDPRESS_DB_NAME | tr -d '\r\n')

# a linha final do arquivo NÃO é SQL — ver seção 5
# ATENÇÃO: use o dump de 19/08 (seção 1.1). O de 18/08 desfaz a Fase 3.
gunzip -c ~/BAHIABA-backups/dump-PRODUCAO-20260819-0711.sql.gz \
  | grep -v '^pod "mysqldump-prod-' \
  | kubectl --context "$CTX" run mysqlrestore-$$ -n $NS --rm -i --restart=Never \
      --image=mysql:8.0.31 --env="MYSQL_PWD=${DBP}" --command -- \
      mysql -h "$DBH" -u "$DBU" "$DBN" --default-character-set=utf8mb4
```

Antes de rodar, colocar o site em manutenção (`WP_INSTALLING`) e, depois, purgar o
`fastcgi_cache` de **todos** os pods.

### Como confirmar que a restauração deu certo

```sql
SELECT option_value FROM wp_options WHERE option_name='siteurl';      -- https://bahia.ba
SELECT option_value FROM wp_options WHERE option_name='template';     -- bahia_refactor
SELECT option_value FROM wp_options WHERE option_name='show_on_front';-- posts
SELECT option_value FROM wp_options WHERE option_name='page_on_front';-- 0
SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='prod'; -- 90
```

Mais: `https://bahia.ba/` em 200, uma matéria antiga abrindo, e uma editoria listando.

## 4-A. 🔴 O COMANDO DE RESTAURAÇÃO DA SEÇÃO 4 NÃO FUNCIONA. Corrigido aqui.

**Descoberto em 19/08/2026, ao restaurar o dump no ambiente local** — o primeiro uso real do
procedimento. Dois defeitos independentes, cada um capaz de arruinar um rollback, e o segundo
não tem conserto por comando.

### 4-A.1 O `grep -v` do macOS descarta metade do arquivo, em silêncio

O comando da seção 4 filtra a linha do `kubectl` com `grep -v '^pod "mysqldump-prod-'`. Medido:

| | bytes |
|---|---|
| `gunzip -c` do dump | 3.269.306.354 |
| depois do `grep -v` | 1.603.714.402 |
| **descartado** | **1.665.591.952 — 51% do arquivo** |

O `grep` do BSD (macOS) não lida com as linhas longas deste dump: a linha 143 sozinha tem
**1.371.246 bytes**. Ele não avisa, não retorna erro, e o `mysql` do outro lado do cano carrega
o que chegar. **Uma restauração feita assim produziria um banco pela metade e pareceria ter
dado certo.**

Filtro seguro, que remove exatamente os 66 bytes da linha do pod:

```bash
gunzip -c dump.sql.gz | python3 -c "
import sys
o = sys.stdout.buffer
for linha in sys.stdin.buffer:
    if not linha.startswith(b'pod \"mysqldump-prod-'):
        o.write(linha)
"
```

Ou, mais simples, fazendo tudo **dentro do container**, onde as ferramentas são GNU:
`gunzip -c /tmp/dump.sql.gz | head -n -1 | mysql ...`

### 4-A.2 O dump tem um statement que o MySQL recusa — e ele para a carga

Mesmo com o filtro correto, a carga morre sempre no mesmo ponto:

```
ERROR at line 143: Unknown command '\"'.
```

Não é o cliente sendo frágil: executando **a mesma linha por driver** (mysqli, sem o CLI), o
**servidor** responde erro de sintaxe. A linha é um `INSERT` em `wp_actionscheduler_actions`
com dados PHP-serializados contendo NUL escapado (`\0*\0scheduled_timestamp`) dentro de JSON
já escapado. O aspeamento sai de sincronia e o resto da linha vira lixo sintático.

Testado e descartado: `--binary-mode`, importar por arquivo em vez de cano, rodar tudo dentro
do container, `sql_mode=''`. Nenhum resolve. A origem **não** tem `NO_BACKSLASH_ESCAPES`
(sql_mode = `NO_ENGINE_SUBSTITUTION`).

**O escopo é o Action Scheduler**, e só ele. Excluindo os dados dessas tabelas, a carga
completa: 90 tabelas, 411 MB, saída 0, sem um erro. É uma fila de tarefas — perder o histórico
dela num rollback não custa nada.

### 4-A.3 O comando que FUNCIONA, verificado de ponta a ponta

```bash
CTX="arn:aws:eks:us-east-1:774710032593:cluster/bahia-eks-prod"
NS=bahia-wordpress
POD=$(kubectl --context "$CTX" -n $NS get pods -l app=wordpress \
        --field-selector=status.phase=Running -o jsonpath='{.items[0].metadata.name}')

DBH=$(kubectl --context "$CTX" exec -n $NS $POD -c wordpress -- printenv WORDPRESS_DB_HOST | tr -d '\r\n')
DBU=$(kubectl --context "$CTX" exec -n $NS $POD -c wordpress -- printenv WORDPRESS_DB_USER | tr -d '\r\n')
DBP=$(kubectl --context "$CTX" exec -n $NS $POD -c wordpress -- printenv WORDPRESS_DB_PASSWORD | tr -d '\r\n')
DBN=$(kubectl --context "$CTX" exec -n $NS $POD -c wordpress -- printenv WORDPRESS_DB_NAME | tr -d '\r\n')

gunzip -c ~/BAHIABA-backups/dump-PRODUCAO-20260819-0711.sql.gz \
  | python3 -c "
import sys
o = sys.stdout.buffer
for l in sys.stdin.buffer:
    if not l.startswith(b'pod \"mysqldump-prod-') and not l.startswith(b'INSERT INTO \`wp_actionscheduler_'):
        o.write(l)
" \
  | kubectl --context "$CTX" run mysqlrestore-$$ -n $NS --rm -i --restart=Never \
      --image=mysql:8.0.31 --env="MYSQL_PWD=${DBP}" --command -- \
      mysql -h "$DBH" -u "$DBU" "$DBN" --default-character-set=utf8mb4 \
        --init-command="SET SESSION sql_mode=''; SET SESSION innodb_strict_mode=0; SET SESSION foreign_key_checks=0; SET SESSION unique_checks=0"
```

**O `--init-command` não é enfeite.** Sem `innodb_strict_mode=0` a carga morre na linha 1019
com `ERROR 1118: Row size too large (> 8126)`. Foi o terceiro obstáculo, depois dos dois acima.

**Conferir DEPOIS**, e não confiar no código de saída: `SELECT COUNT(*)` de tabelas deve dar
**90**, e o tamanho ~**411 MB**. Um número menor significa carga truncada.

## 5. Uma armadilha do arquivo: a última linha não é SQL

O `kubectl run --rm` escreve `pod "mysqldump-prod-16226" deleted from bahia-wordpress
namespace` no **stdout**, e ela foi comprimida junto, **depois** do `-- Dump completed`.

Consequência: uma restauração ingênua (`gunzip -c ... | mysql`) carrega tudo corretamente e
**termina com `ERROR 1064` na última linha**, parecendo ter falhado quando não falhou. O
`grep -v` do comando da seção 4 remove a linha. Os dumps de homolog têm a mesma característica.

## 6. O que este backup NÃO cobre

- **Uploads.** Ficam no PVC `wordpress-uploads` (EFS) e no S3/CloudFront, não no dump nem no
  snapshot. A virada não escreve neles.
- **Manifestos de infraestrutura.** Vivem no repositório `infra-bahiaba`, em
  `kubernetes/prod/**`.

## 7. As seis verificações — resultado

| # | Verificação | Esperado | Resultado |
|---|---|---|---|
| 1 | Tamanho | centenas de MB | **548,8 MB** ✅ |
| 2 | `gzip -t` | silêncio | **sem erro** ✅ |
| 3 | `Dump completed` | presente no fim | **`-- Dump completed on 2026-08-18  6:11:11`** ✅ |
| 4 | `siteurl` | `https://bahia.ba` | **`'siteurl','https://bahia.ba'`** ✅ |
| 5 | `CREATE TABLE` | anotar | **90** |
| 6 | `_subtitulo` | pareado com `subtitulo` | **275.340 = 275.340 em `wp_postmeta`** ✅ |

Sobre a 6: a contagem bruta no arquivo inteiro dá 275.341 para `subtitulo` contra 275.340 para
`_subtitulo`. A diferença de 1 está em **`wp_posts`**, não em `wp_postmeta`: é o post
`acf-field` que **define** o campo. Dentro de `wp_postmeta` a paridade é exata.
