# Rollback da atualização do WordPress 7.1 em HOMOLOG

**Escrito em 29/08/2026, ANTES de aplicar a atualização.** Só homolog. Produção não é tocada.

---

## Por que não existe downgrade limpo

**A `db_version` sobe de `60421` para `61833` — 1.412 revisões de esquema.** O WordPress aplica
migrações de banco na primeira carga após a troca dos arquivos, e **não tem caminho de volta**:
não existe "downgrade" no core, e o 6.8.3 não sabe desfazer o que a 7.1 fez.

**Consequência: trocar os arquivos de volta NÃO basta.** Um core 6.8.3 sobre um banco em `61833`
é um estado que nunca foi testado por ninguém — o WordPress vê uma `db_version` maior que a dele
e simplesmente não migra, deixando tabelas e opções em formato que o código antigo pode não
entender.

> **Voltar exige restaurar o banco E os arquivos. Os dois, sempre.**

---

## Estado registrado ANTES (o alvo do rollback)

```
siteurl      : https://hml.bahia.ba
wp_version   : 6.8.3
db_version   : 60421   (option e core, iguais)
PHP          : 8.3.28
MySQL        : 8.4.9
core mtime   : 2025-09-30 17:30:38
tema         : Newspaper
plugins ativos: 24
wp_posts     : 435.767
search_idx   : 242.864
pod          : wordpress-676cd994d6-qcfr7
imagem       : bahia-wordpress:fd15e6f3b93b… (PHP 8.3)
```

---

## Os dois ativos de recuperação

| | O quê | Onde |
|---|---|---|
| **1. Dump lógico** | `mysqldump` completo do schema `prod` de homolog | `~/BAHIABA-backups/dump-HOMOLOG-pre-wp71-20260829-0819-limpo.sql.gz` |
| **2. Backup automático do RDS** | retenção de **7 dias**, point-in-time | instância `rds-bahiaba-hml` |

**Não há snapshot manual:** `rds:CreateDBSnapshot` foi removido da política `RdsEscritaUpgrade84`
na revisão que abriu espaço para as ações do Blue/Green. **O backup automático cobre**, e é
point-in-time, o que é até melhor para este caso — dá para voltar ao minuto anterior à subida.

### O dump, verificado

```
bytes    : 550.405.652  (525 MiB)        duracao : 4 min 35 s, codigo 0
SHA-256  : 2beea8df45d0a9d5e717c3aaeed01392c886d9973915485b7df558bdcd6270d2
permissao: 444
```

| Portão | Resultado |
|---|---|
| `gzip -t` | **OK** |
| Rodapé `Dump completed` | **`-- Dump completed on 2026-08-29  8:23:37`** |
| `siteurl` de homolog dentro | **222 ocorrências** de `https://hml.bahia.ba` |
| `CREATE TABLE` no dump × tabelas no banco | **92 × 92 — bate** |
| Erros do `mysqldump` | nenhum |

> ⚠️ **Por que o arquivo tem `-limpo` no nome.** O primeiro dump saiu com uma linha do
> `kubectl` (`pod "mysqldump-hml-1335" deleted…`) **dentro do gzip** — o `kubectl run --rm`
> escreve essa mensagem em **stdout**, e o `stdout` era o próprio dump. Uma linha que não é SQL
> no fim do arquivo faria a restauração terminar com erro de sintaxe: o dado entraria todo, mas
> quem estivesse restaurando às três da manhã veria um erro e não saberia se podia confiar.
>
> **O arquivo com ruído foi apagado**, para não restar dúvida sobre qual usar. **Lição para o
> próximo dump por `kubectl run`: mandar a saída do `kubectl` para outro lugar, ou filtrar.**

---

## Procedimento de rollback

### Caminho A — restaurar o dump lógico (mais rápido, mantém a instância)

```bash
CTX=arn:aws:eks:us-east-1:774710032593:cluster/bahia-eks-homolog
NS=bahia-wordpress
POD=$(kubectl --context "$CTX" -n $NS get pods -l app=wordpress \
        -o jsonpath='{.items[0].metadata.name}')

# 1. Credenciais, do proprio pod
DBH=$(kubectl --context "$CTX" -n $NS exec $POD -c wordpress -- printenv WORDPRESS_DB_HOST | tr -d '\r\n')
DBU=$(kubectl --context "$CTX" -n $NS exec $POD -c wordpress -- printenv WORDPRESS_DB_USER | tr -d '\r\n')
DBP=$(kubectl --context "$CTX" -n $NS exec $POD -c wordpress -- printenv WORDPRESS_DB_PASSWORD | tr -d '\r\n')
DBN=$(kubectl --context "$CTX" -n $NS exec $POD -c wordpress -- printenv WORDPRESS_DB_NAME | tr -d '\r\n')

# 2. GUARDA: confirmar que e homolog antes de escrever
#    (o dump traz siteurl=https://hml.bahia.ba; se o alvo for outro, PARE)
kubectl --context "$CTX" -n $NS exec $POD -c wordpress -- php -r '
  $m=new mysqli(getenv("WORDPRESS_DB_HOST"),getenv("WORDPRESS_DB_USER"),
                getenv("WORDPRESS_DB_PASSWORD"),getenv("WORDPRESS_DB_NAME"));
  $u=$m->query("SELECT option_value FROM wp_options WHERE option_name=\"siteurl\"")->fetch_row()[0];
  echo $u, "\n"; exit($u==="https://hml.bahia.ba" ? 0 : 1);'

# 3. Restaurar (o dump tem DROP TABLE IF EXISTS + CREATE de cada tabela)
gunzip -c ~/BAHIABA-backups/dump-HOMOLOG-pre-wp71-20260829-0819-limpo.sql.gz | \
kubectl --context "$CTX" run mysql-restore-$$ -n $NS --rm -i --restart=Never \
  --image=mysql:8.0.31 --env="MYSQL_PWD=${DBP}" --command -- \
  mysql -h "$DBH" -u "$DBU" "$DBN"
```

### Caminho B — restaurar o banco por point-in-time (se o dump falhar)

```bash
aws rds restore-db-instance-to-point-in-time --region us-east-1 \
  --source-db-instance-identifier rds-bahiaba-hml \
  --target-db-instance-identifier rds-bahiaba-hml-rollback \
  --restore-time <ISO8601 imediatamente ANTES da subida> \
  --db-subnet-group-name default-vpc-4c49202b \
  --vpc-security-group-ids sg-0234245542eb43738 \
  --no-publicly-accessible
# depois: apontar WORDPRESS_DB_HOST do ConfigMap de homolog para a instancia nova
```

⚠️ **`rds:RestoreDBInstanceToPointInTime` NÃO está na política atual.** Só há
`RestoreDBInstanceFromDBSnapshot`. Se o caminho B for necessário, **pedir a ação ao Albert**.

### Passo 3, obrigatório nos dois caminhos — os ARQUIVOS

```bash
# O core da 7.1 vive num emptyDir. Basta recriar o pod: o initContainer copia o
# wp-content da imagem e o entrypoint copia o core 6.8.3 de /usr/src/wordpress.
kubectl --context "$CTX" -n $NS rollout restart deployment/wordpress
kubectl --context "$CTX" -n $NS rollout status deployment/wordpress --timeout=600s
```

**A efemeridade do `emptyDir`, que é problema em todo o resto deste projeto, aqui é a favor:**
o rollback dos arquivos é um `rollout restart`, e sai de graça.

### Portão de conferência do rollback

```bash
kubectl --context "$CTX" -n $NS exec $POD -c wordpress -- php -r '
define("WP_USE_THEMES",false); require "/var/www/html/wp-load.php";
global $wpdb;
printf("wp_version=%s db_version=%s wp_posts=%s search_idx=%s\n",
  get_bloginfo("version"), get_option("db_version"),
  $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}"),
  $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}bahia_search_idx"));'
```

**Esperado: `wp_version=6.8.3 db_version=60421 wp_posts=435767 search_idx=242864`.**
Se a `db_version` continuar em `61833`, **o banco não foi restaurado** — só os arquivos.

---

## O que NÃO precisa de rollback

- **Produção.** Não é tocada. Se algo lá mudar, é erro e deve ser reportado na hora.
- **A imagem e o Dockerfile.** A atualização é aplicada **no pod em execução**, não pela imagem —
  justamente para não deixar uma mudança de core no `Dockerfile`, que é **compartilhado com
  produção** e viraria armadilha no próximo merge para a `main`.
