# CHECKPOINT pré-rodada 10 — como voltar ao estado de hoje

**Tirado em:** 13/08/2026, ~09:05 UTC, em `hml.bahia.ba`
**Por quê:** os três itens da rodada 10 mexem no cabeçalho e no contêiner do site. Se a
mudança não for aprovada, este documento devolve o site exatamente ao estado validado ao
fim da rodada 9.

> **Leia a seção 1 antes de executar qualquer coisa.** São três peças e **nenhuma sozinha
> restaura o site**. Restaurar só o código deixa o banco com o layout novo; restaurar só o
> banco deixa o código novo no ar.

---

## 1. As três peças

| # | Peça | Onde está | O que devolve |
|---|------|-----------|---------------|
| 1 | **Tag no git** | `checkpoint-pre-rodada10` | O código (mu-plugins, tema, Dockerfile) |
| 2 | **Imagem no ECR** | digest `sha256:4a6ed616…` | O mesmo código, já construído — rollback sem rebuild |
| 3 | **Dump do banco** | `scratchpad/checkpoint-r10/` | Templates, home, `td_011`, `wpseo_titles` e afins |

O banco **não viaja no git**. É a peça que mais gente esquece.

---

## 2. Identificadores exatos

### 2.1 Git

```
tag:          checkpoint-pre-rodada10
commit:       72f02683f45ba0cb803cba2e240f170f0d96ed84
objeto da tag: 55a23e403a85ce8bd62b21f90c7a0b77f0de2cf8
branch:       staging   (já empurrada para origin)
```

> **Sobre o `50d64527` citado no briefing:** o commit da rodada 9 é o `50d64527`, mas o
> topo da `staging` é o `72f02683`. Os dois commits acima do `50d64527` (`cc2765f9` e
> `72f02683`) tocam **apenas `scratchpad/`** — documentação de migração, nenhuma linha de
> código. A tag foi criada no `72f02683` porque é **o SHA da imagem que está rodando em
> homolog**. O código é idêntico ao da rodada 9. Confira você mesmo:
>
> ```bash
> git diff --stat 50d64527..72f02683   # só scratchpad/
> ```

### 2.2 Imagem (a via mais rápida de rollback de código)

```
repositório: 774710032593.dkr.ecr.us-east-1.amazonaws.com/bahia-wordpress
tag por SHA: 72f02683f45ba0cb803cba2e240f170f0d96ed84
DIGEST:      sha256:4a6ed6160b0194980c5afc860230258c54af682fbc1a2def4f5ca246348995ee
pushed em:   2026-08-13T09:25:13+01:00
tamanho:     ~414 MB
```

> **Use o DIGEST, não a tag.** O repositório está com `imageTagMutability: MUTABLE`, e esta
> imagem também carrega a tag `homolog-latest` — que **vai se mover no próximo build**. A
> tag por SHA não deveria ser sobrescrita, mas com o repositório mutável nada garante isso.
> O digest garante.

### 2.3 Dump do banco

```
scratchpad/checkpoint-r10/checkpoint-r10-20260813-090422.json   3.000.503 bytes  md5 111ad764179d2119991099c249975c8e
scratchpad/checkpoint-r10/checkpoint-r10-20260813-090422.sql    1.752.961 bytes  md5 3c8f88feb39979514a6b0c1140adee21
scratchpad/checkpoint-r10/restaurar.php                         executor (não há cliente mysql no pod)
scratchpad/checkpoint-r10/gerar-dump.php                        gerador, para tirar outro checkpoint igual
```

O `.sql` é o que se aplica. O `.json` é o arquivo de fidelidade — traz todo valor em base64,
com `md5` e `meta_id` de cada linha, e serve para conferência ou restauração seletiva.

**O que está dentro:**

- **6 posts**, com `post_content` **e todas as 48 linhas de `postmeta`**:
  - `547432` home · `547414` cabeçalho · `547416` rodapé
  - `547422` autor · `547428` busca · `547430` 404
  - (são exatamente os **5 templates vivos** da `AUDITORIA-templates.md` + a home)
- **78 chaves de `wp_options`** + `td_011` à parte: `td_011_settings`, `td_011_generated_css`,
  `wpseo_titles`, `wpseo`, `wpseo_social`, `theme_mods_Newspaper`, `sidebars_widgets`,
  `adrotate_config`/`crawlers`/`advert_status`, formatos de data/hora, e **as 54 chaves
  `bahia_*`** (backups das rodadas 2 a 9).
- **Informativo** (não entra no SQL): os 3 menus com seus itens, e as tabelas do AdRotate
  (`adrotate` 153, `groups` 18, `linkmeta` 711, `schedule` 1359).

**O cabeçalho `547414` é `base64(JSON)`** com seis zonas — inclusive a **mobile**, que era o
ponto de atenção do briefing. Restaurar o `post_content` restaura as seis de uma vez:

| zona | bytes |
|------|-------|
| `tdc_header_desktop` | 12.504 |
| `tdc_header_desktop_sticky` | 6.601 |
| `tdc_header_mobile` | 2.617 |
| `tdc_header_mobile_sticky` | 2.678 |
| `tdc_is_header_sticky` | 4 |
| `tdc_is_mobile_header_sticky` | 4 |

### 2.4 md5 de referência — o alvo da restauração

Depois de restaurar, **estes valores têm que bater**:

| objeto | md5 | bytes |
|--------|-----|-------|
| post 547432 (home) | `7b93a5105a221fabbacbd333cffd9ff5` | 31.765 |
| post 547414 (cabeçalho) | `a8a73d321a2040e365975f417d23130a` | 34.096 |
| post 547416 (rodapé) | `1b2a8aeb6b5f4534cbb67f99a03863c9` | 4.694 |
| post 547422 (autor) | `240e75d570486ad548d8c2b2b58f10cb` | 4.522 |
| post 547428 (busca) | `11a3c14175f898edf822207ff4cd42d0` | 4.018 |
| post 547430 (404) | `f2b22a0118a6d8c113c74a444522e734` | 5.112 |
| `td_011` | `90b6230e27bdb43ab9d29c66d0fee553` | 24.169 |
| `wpseo_titles` | `a123b50b01ef92060348774ea1d8169c` | 54.706 |
| `td_011_settings` | `434a2cb5f76db80ac98384bd42186d8c` | 118.562 |

E ainda: **48 linhas** de `postmeta` nos 6 objetos, **0** chaves duplicadas.

### 2.5 O pod confere com o git

No momento do checkpoint, os **42 mu-plugins** do pod batem md5 a md5 com os do
repositório — nada vive só no pod por `kubectl cp`. Ou seja, **a tag e a imagem capturam
todo o código**. (A conferência cobre `mu-plugins/`, que é onde mora o código próprio;
o resto de `wp-content` vem da imagem, já que em homolog é `emptyDir`.)

---

## 3. RESTAURAÇÃO — o procedimento

### Passo 0 — descobrir o pod

```bash
kubectl config use-context arn:aws:eks:us-east-1:774710032593:cluster/bahia-eks-homolog
POD=$(kubectl get pod -n bahia-wordpress -l app=wordpress -o jsonpath='{.items[0].metadata.name}')
echo $POD
```

> **Confira o contexto.** Ele já trocou sozinho de homolog para produção no meio de uma
> sessão. Todos os scripts abortam se `siteurl` não for `https://hml.bahia.ba`, mas não
> confie só nisso.

### Passo 1 — voltar o CÓDIGO

Escolha **uma** das duas vias.

**Via A — só trocar a imagem (segundos, sem build).** Use esta se a pressa é grande:

```bash
kubectl set image deploy/wordpress -n bahia-wordpress \
  wordpress=774710032593.dkr.ecr.us-east-1.amazonaws.com/bahia-wordpress@sha256:4a6ed6160b0194980c5afc860230258c54af682fbc1a2def4f5ca246348995ee

kubectl rollout status deploy/wordpress -n bahia-wordpress --timeout=300s
```

> Homolog tem **um nó só** e 1 réplica. O deployment já está com
> `maxSurge: 0` / `maxUnavailable: 1` (conferido em 13/08/2026) — que é o que permite o
> rollout terminar num nó único. Se algum dia travar, confira isso primeiro.

> Isto deixa o cluster **fora de sincronia com o git** — o próximo push em `staging`
> reconstrói e traz o código novo de volta. É rollback de emergência, não estado final.
> Para consolidar, faça a via B.

**Via B — reverter o git (deixa tudo coerente).**

```bash
git checkout staging
git revert --no-edit <sha-dos-commits-da-rodada-10>   # ou: git reset --hard checkpoint-pre-rodada10
git push origin staging                               # dispara o build e o deploy de homolog
```

> `push` em `staging` **publica em homolog**. Não toca em produção: o workflow de produção
> só existe na `main`. Se for usar `reset --hard` numa branch já empurrada, o push será
> forçado — só faça com autorização explícita.

### Passo 2 — voltar o BANCO

```bash
kubectl cp scratchpad/checkpoint-r10/checkpoint-r10-20260813-090422.sql \
  bahia-wordpress/$POD:/tmp/restore.sql -c wordpress
kubectl cp scratchpad/checkpoint-r10/restaurar.php \
  bahia-wordpress/$POD:/tmp/restaurar.php -c wordpress

# 1) DRY-RUN — aplica cada instrução de verdade e dá ROLLBACK. Sempre primeiro.
kubectl exec -n bahia-wordpress $POD -c wordpress -- php /tmp/restaurar.php /tmp/restore.sql

# 2) PARA VALER — só se o dry-run terminar com "Banco intacto: sim" e 0 erros
kubectl exec -n bahia-wordpress $POD -c wordpress -- php /tmp/restaurar.php /tmp/restore.sql --apply
```

O script imprime o estado antes e depois e recusa-se a dar `COMMIT` se aparecer erro ou
`postmeta` duplicada. Ele **aborta** se a `siteurl` não for a de homolog.

**A ordem dentro do `.sql` importa e já está certa:** templates e opções primeiro,
**`td_011` por último**. Enquanto `td_011` não é reescrita o site segue servindo o layout
corrente — as etapas 1 a 3 podem rodar com o site no ar, e **a virada é atômica na etapa 4**.
É a mesma regra da `MIGRACAO-homolog-para-prod.md`.

### Passo 3 — purgar o cache

Sem isto você vai olhar para a página antiga e achar que a restauração falhou.

```bash
kubectl exec -n bahia-wordpress $POD -c nginx -- sh -lc 'rm -rf /tmp/nginx-cache/*'
```

### Passo 4 — conferir

```bash
kubectl exec -n bahia-wordpress $POD -c wordpress -- php -r '
require_once "/var/www/html/wp-load.php"; global $wpdb;
$IDS="547432,547414,547416,547422,547428,547430";
foreach ([547432,547414,547416,547422,547428,547430] as $id)
  printf("%d %s\n",$id,$wpdb->get_var($wpdb->prepare("SELECT MD5(post_content) FROM {$wpdb->posts} WHERE ID=%d",$id)));
printf("td_011 %s\n",$wpdb->get_var("SELECT MD5(option_value) FROM {$wpdb->options} WHERE option_name=\"td_011\""));
printf("postmeta %s (48)  duplicadas %s (0)\n",
  $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id IN ($IDS)"),
  $wpdb->get_var("SELECT COUNT(*) FROM (SELECT post_id,meta_key FROM {$wpdb->postmeta} WHERE post_id IN ($IDS) GROUP BY post_id,meta_key HAVING COUNT(*)>1) x"));'
```

Compare com a tabela da seção 2.4. Depois abra a home, uma editoria e um post, em 1920 e
em 390px.

---

## 4. Armadilhas deste banco — verificadas, não supostas

Três coisas foram descobertas **testando** o SQL de restauração. Estão corrigidas no
arquivo, mas quem for escrever outro script precisa saber:

**1. `wp_postmeta` não tem índice único em `(post_id, meta_key)`.**
Os índices são `PRIMARY(meta_id)`, `KEY post_id`, `KEY meta_key`, `idx_meta_value`. Logo,
`INSERT … ON DUPLICATE KEY UPDATE` **nunca casa** e cada execução **duplica todas as
linhas**. A primeira versão deste checkpoint tinha esse defeito e, no dry-run, criou 48
linhas duplicadas em homolog — detectadas e removidas na hora, com os `meta_id` originais
do `.json` como fonte da verdade (contagem de volta a 48, 0 duplicadas, todos os md5 de
`post_content` conferidos).
**O `.sql` de hoje usa `DELETE` por `post_id` + `INSERT` com `meta_id` explícito** — o que
também torna o restore idempotente e remove meta criada depois do checkpoint.
Em `wp_options` o `ON DUPLICATE KEY UPDATE` **está correto**: `option_name` é UNIQUE.

**2. As colunas de texto são `utf8mb3`, não `utf8mb4`.**
`post_content`, `meta_value` e `option_value` são `utf8mb3_general_ci`. Caractere de 4 bytes
(emoji) **não cabe e vira `?`** — na gravação normal do site, não só no restore. Os 132
valores deste checkpoint foram conferidos um a um: todos são UTF-8 válido, nenhum tem
caractere de 4 bytes, e todos voltam byte a byte pelo mesmo caminho SQL.

**3. `FROM_BASE64('')` devolve `NULL`, não string vazia.**
Por isso valor vazio é escrito como `''` literal. E `header_mobile_menu_id` do 547414 é
**`NULL` de verdade** (não vazio) — a distinção está preservada no `.json` (`eh_null`) e no
`.sql`.

> Detalhe de escrita, para quem for conferir scripts: `strtok($s, " ")` sobre `"COMMIT;"`
> devolve `"COMMIT;"` **com o ponto e vírgula**. Um filtro `=== 'COMMIT'` não casa, o COMMIT
> escapa e o "dry-run" grava de verdade. Foi assim que as 48 linhas foram parar no banco.
> O executor de hoje pega a palavra-chave por regex e **aborta** se não reconhecer o
> `START TRANSACTION`/`COMMIT` do arquivo.

---

## 5. O que este checkpoint NÃO cobre

Honestidade sobre o alcance, para ninguém contar com o que não existe:

- **Mídia e uploads** — ficam no S3 (WP Offload Media). Não são tocados pela rodada 10.
- **O resto de `wp_posts`** — os 242 mil posts de conteúdo não entram. A rodada 10 mexe em
  cabeçalho e contêiner; não altera matérias.
- **Tabelas do AdRotate** — estão no `.json` como retrato, mas **não** no `.sql`. A rodada 10
  não altera a lógica de grupos (item 2 mantém os grupos 1/12/14 da rodada 8). Se algum dia
  precisar, o retrato está lá para reconstruir à mão.
- **Menus** — idem: retrato no `.json`, fora do `.sql`.
- **Produção** — este checkpoint é **só de homolog**. Produção não é tocada por nada aqui.

---

## 6. Resumo de uma linha

> Código volta pelo digest `sha256:4a6ed616…` (ou pela tag `checkpoint-pre-rodada10`);
> banco volta com `restaurar.php checkpoint-r10-20260813-090422.sql --apply`;
> **depois purgue o cache do nginx** e confira os md5 da seção 2.4.
