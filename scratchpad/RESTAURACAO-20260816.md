# Restauração do banco de homolog — backups de 16/08/2026

Documento exigido pela etapa 1 de `IMPORT-prod-para-homolog.md` §0.1. Escrito **antes** da
renumeração, para que o caminho de volta exista antes de haver o que desfazer.

---

## 0. São DOIS pontos de retorno. Escolha pelo que deu errado.

| Cenário | Restaure | Estado a que se volta |
|---|---|---|
| **O import deu errado** | `backup-hml-pos-renumeracao-20260816-1636.sql.gz` | renumeração **preservada**, sem o conteúdo importado. É quase sempre este. |
| **A renumeração deu errado**, ou se quer desfazê-la | `backup-hml-pre-import-20260816-1603.sql.gz` | estado anterior a tudo: IDs antigos, `page_on_front` = 547432, sem `wp_bahia_renum_map` |

**Restaurar o dump errado custa caro.** O de 16:03 é anterior à renumeração: usá-lo para
consertar um import malsucedido desfaz a renumeração junto e obriga a refazer a operação
inteira. Confira a data no nome do arquivo antes de rodar.

**Manter os dois.** São pontos de retorno diferentes e servem a falhas diferentes.

| | pré-renumeração | pós-renumeração |
|---|---|---|
| Arquivo | `backup-hml-pre-import-20260816-1603.sql.gz` | `backup-hml-pos-renumeracao-20260816-1636.sql.gz` |
| Tamanho | 534 MB (560.357.311 bytes) | 540 MB (565.848.435 bytes) |
| SHA-256 | `d3c0e9adfdb5734b896449781ac329f52453bf37d4c9039d7ec0df36f349a6bd` | `cda74d8e04c7552e4f84a3ac1683178386ba45c9ea675dccd7e30ae05b2c9f64` |
| Concluído | 2026-08-16 15:05:54 | 2026-08-16 15:39:03 |
| Tabelas | 90 | **91** (mais a `wp_bahia_renum_map`) |
| `page_on_front` | 547432 | **9000142** |

As duas conferências que distinguem um do outro, feitas no dump de 16:36 e aprovadas:

```bash
gunzip -c backup-hml-pos-renumeracao-20260816-1636.sql.gz | grep -m1 "'page_on_front'"
#   (85,'page_on_front','9000142'

gunzip -c backup-hml-pos-renumeracao-20260816-1636.sql.gz \
  | awk '/INSERT INTO `wp_bahia_renum_map`/' | grep -oE '\([0-9]+,[0-9]+' | wc -l
#   117
```

As 117 tuplas do mapa foram conferidas uma a uma contra a fórmula
`novo = 9.000.000 + (antigo − 547.290)`: **todas obedecem**, da primeira
(547291 → 9000001) à última (547478 → 9000188). E nenhuma chave
`tdb_template_<ID antigo>` sobrou em `wp_options`.

---

## 1. O que o primeiro backup é

| | |
|---|---|
| **Arquivo** | `~/BAHIABA-backups/backup-hml-pre-import-20260816-1603.sql.gz` |
| **Máquina** | estação local do Albert, **fora do cluster e fora do RDS** |
| **Origem** | RDS `rds-bahiaba-hml.cr9zu4ke1bev.us-east-1.rds.amazonaws.com`, banco `prod` |
| **Confirmação de identidade** | `siteurl = https://hml.bahia.ba` — o banco de homolog **se chama `prod`**, só o `siteurl` distingue |
| **Tamanho** | 534 MB (560.357.311 bytes) compactados, de um banco de 6,5 GB |
| **SHA-256** | `d3c0e9adfdb5734b896449781ac329f52453bf37d4c9039d7ec0df36f349a6bd` |
| **Concluído em** | 2026-08-16 15:05:54 (horário do servidor) |
| **Estado do código** | tag `pre-import-conteudo-20260816`, sobre o commit `d691c906` — **criada localmente, sem push** |
| **Snapshot do RDS** | **não existe** — as credenciais de pipeline não têm permissão de RDS (`AccessDenied` em `rds:DescribeDBInstances`). Este dump é a **única** cópia. |

### Como foi gerado

Cliente `mysql:8.0.31` num pod efêmero, transmitindo para a estação local. **Não use
`mysql:8`**: desde o 8.0.32 o `mysqldump` exige o privilégio `RELOAD` com
`--single-transaction`, e o RDS não o concede — a primeira tentativa morreu assim.

```bash
mysqldump --single-transaction --quick --no-tablespaces --routines --triggers \
          --default-character-set=utf8mb4 --hex-blob --set-gtid-purged=OFF
```

Sem `--events` (exige privilégio próprio; o banco não tem eventos).

---

## 2. Verificação — as quatro conferências

```bash
cd ~/BAHIABA-backups
ls -lh  backup-hml-pre-import-20260816-1603.sql.gz
gzip -t backup-hml-pre-import-20260816-1603.sql.gz            # silêncio = íntegro
gunzip -c backup-hml-pre-import-20260816-1603.sql.gz | tail -3 | grep "Dump completed"
gunzip -c backup-hml-pre-import-20260816-1603.sql.gz | grep -m1 "'siteurl'"
shasum -a 256 backup-hml-pre-import-20260816-1603.sql.gz
```

| Conferência | Esperado | Resultado em 16/08/2026 |
|---|---|---|
| Tamanho | centenas de MB, **não** centenas de bytes | **534 MB** — OK |
| `gzip -t` | sem saída | **sem erro** — OK |
| `-- Dump completed on ...` | presente na última linha | **`-- Dump completed on 2026-08-16 15:05:54`** — OK |
| `siteurl` | `https://hml.bahia.ba` | **`'siteurl','https://hml.bahia.ba'`** — OK |
| `CREATE TABLE` no dump vs. tabelas no banco | iguais | **90 e 90** — OK |
| SHA-256 | — | `d3c0e9adfdb5734b896449781ac329f52453bf37d4c9039d7ec0df36f349a6bd` |

A terceira é a que pega o dump truncado por queda de conexão: o `mysqldump` só escreve essa
linha se chegou ao fim. Arquivo grande, que abre e descompacta, **sem** essa linha, é um
backup pela metade.

---

## 3. Como restaurar

### 3.1 Parar o tráfego antes

```bash
kubectl scale deploy/wordpress -n bahia-wordpress --replicas=0
```

Restaurar com o WordPress escrevendo por cima produz um banco meio novo meio velho — pior
que qualquer um dos dois.

### 3.2 Restaurar

```bash
NS=bahia-wordpress
DBH=rds-bahiaba-hml.cr9zu4ke1bev.us-east-1.rds.amazonaws.com
DBU=<WORDPRESS_DB_USER do pod>
DBP=<WORDPRESS_DB_PASSWORD do pod>

gunzip -c ~/BAHIABA-backups/backup-hml-pre-import-20260816-1603.sql.gz | \
kubectl run mysql-restore-$$ -n $NS --rm -i --restart=Never --image=mysql:8.0.31 \
  --env="MYSQL_PWD=${DBP}" --command -- \
  mysql -h "$DBH" -u "$DBU" --default-character-set=utf8mb4 prod
```

O dump traz `DROP TABLE IF EXISTS` / `CREATE TABLE` por tabela: ele **substitui** o que
existe. É o comportamento desejado numa restauração — e o motivo de nunca rodar isto por
engano.

### 3.3 Religar

```bash
kubectl scale deploy/wordpress -n bahia-wordpress --replicas=1
kubectl rollout status deploy/wordpress -n bahia-wordpress
```

Depois limpar o cache FastCGI do nginx e o opcache do PHP-FPM.

---

## 4. Como saber que a restauração deu certo

Os valores esperados **dependem de qual dump foi restaurado**:

| # | Verificação | Se restaurou o de **16:03** (pré) | Se restaurou o de **16:36** (pós) |
|---|---|---|---|
| 1 | `siteurl` | `https://hml.bahia.ba` | `https://hml.bahia.ba` |
| 2 | `COUNT(*) … post_type='tdb_templates'` | **13** | **13** |
| 3 | `page_on_front` | **547432** | **9000142** |
| 4 | `COUNT(*) … ID >= 547291` | **124** | **0** |
| 5 | `COUNT(*) … ID >= 9000001` | **0** | **117** |
| 6 | `SHOW TABLES LIKE 'wp_bahia_renum_map'` | **vazio** | **existe, 117 linhas** |
| 7 | `AUTO_INCREMENT` de `wp_posts` | 547479 | **9000189** |
| 8 | Home, cabeçalho, **rodapé com a logo colorida**, single, Quem Somos com as 22 fotos | renderizando | renderizando |

Os itens 3 a 7 são o que distingue "restaurou" de "restaurou o dump errado". Confira-os
**antes** de declarar a restauração concluída.

> **Ao ler o `AUTO_INCREMENT`, force a leitura sem cache.** O MySQL 8 guarda as estatísticas
> do `information_schema` por 24 horas, e a consulta devolve um valor velho:
> `SET SESSION information_schema_stats_expiry=0;` antes, ou use `SHOW CREATE TABLE wp_posts`.
> Foi exatamente essa leitura em cache que produziu um falso negativo na validação da
> renumeração.

> **Se restaurar o dump pré-renumeração**, lembre de reverter também as constantes do
> `mu-plugins/bahia-logo-rodape.php` para 547458 e 547365, e os IDs dos documentos do
> `scratchpad/`. Código e banco têm de contar a mesma história — senão o rodapé perde a logo
> colorida em silêncio, que é o modo de falha descrito em `RENUMERACAO-homolog.md` §3.5a.

---

## 5. Quando restaurar

**Sempre que a renumeração ou o import saírem do previsto.** Não corrigir por cima.

Um import ou uma renumeração parcial deixam o banco num estado que ninguém mapeou: metas
órfãs, anexos sem post, IDs meio trocados. Consertar por cima empilha suposições sobre
suposições, e o que se ganha em tempo se perde na primeira coisa inexplicável semanas
depois.

Restaurar, entender o que falhou, corrigir o procedimento, repetir do zero.

---

## 6. Responsáveis

| Papel | Quem |
|---|---|
| Executa | *(preencher)* |
| Valida | Albert |
| Autoriza a restauração | Albert |
