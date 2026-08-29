# Atualização do MySQL 8.0 → 8.4 — estratégia

**Revisão 3 — 27/08/2026.** A política de leitura do RDS foi aplicada e **a Fase 0 está
fechada**: parameter groups, verificação de pré-atualização, armazenamento e reservas, tudo
medido. Traz o roteiro fechado da instância de teste.

**Estado: uma única coisa foi executada nesta rodada — o conserto e a validação do
`carga.sh`** (§T-1), que você pôs antes de tudo. Nenhuma alteração em banco, em Kubernetes ou em
recurso da AWS. Todo o resto continua sendo leitura.

> ### Janela de manutenção de 29/08/2026 — o que aconteceu
>
> | Fase | Resultado |
> |---|---|
> | Snapshot manual de produção | ✅ `bahia-prod-pre-upgrade-84-20260828`, **sem suspensão de E/S** |
> | **Fase A** — instância de teste | ⛔ **PARADA no passo 1** — RDS somente leitura na credencial. **Não é incompatibilidade** |
> | **Fase B** — T0 `OPTIMIZE` | ✅ **+2,032 GiB**, bloqueio ≤ 0,129 s |
> | **Fase C** — homolog | ⏸ depende da Fase A |
> | **Fase D** — produção Blue/Green | ⏸ depende de A e C |
>
> O portão de incompatibilidade **não chegou a ser testado**. Ver a nota de método logo abaixo.

Repositório na revisão `a9c7d1ab`. Documentos irmãos: `HANDOVER.md` (§0.2.1 o achado do
`bahia_ambiente()`, §16.3 o do `carga.sh`), `IMPORT-prod-para-homolog.md` (comando de dump),
`RESTAURACAO-PRODUCAO-20260818.md` (números reais de dump),
`INCIDENTE-virada-abortada-20260818.md` (os números do cache frio).

---

## Placar de 27/08/2026 — o que foi executado

| Tarefa | Estado | Ganho medido |
|---|---|---|
| **T-1** `carga.sh` consertado e validado | ✅ | pico de `Threads_running` deixou de sair 45% menor |
| **T1** ConfigMap → DNS | ✅ | modo de falha silenciosa eliminado |
| **T3** `DROP DATABASE homolog` | ✅ | **+3,49 GiB** |
| **T2** 4 tabelas do Action Scheduler | ✅ | **+2,44 GiB** |
| **T4** 5 tabelas do Rank Math | ✅ | **+0,58 GiB** |
| **T0** `OPTIMIZE wp_adrotate_tracker` | ✅ **29/08, 03:10 UTC** | **+2,032 GiB medidos** |
| **Autoscaling de armazenamento** | 📋 avaliado, recomendado | ver §T-autoscaling |

| | Antes (13:15 UTC) | Depois (13:45 UTC) |
|---|---|---|
| **Espaço livre** | **2,976 GiB** (15%) | **9,430 GiB (47%)** |
| **Schema `prod`** | 90 tabelas / 6,441 GB | **81 tabelas / 3,589 GB** |
| Schemas na instância | `prod`, `homolog`, `test` | `prod`, `test` |
| Linhas com data zero | 1.885.398 | **0** |

**Ganho total do dia: +6,45 GiB.** O site respondeu **em 200 em todas as conferências**, depois
de cada uma das três remoções, e os pods não registraram uma única menção a tabela inexistente.

**Sobra de espaço morto: 2,169 GB, dos quais 2.056 MB são a `wp_adrotate_tracker` — o T0.**

---

## ⚠️ O fato que reenquadra o projeto inteiro: **NÃO HÁ PRAZO**

> **Com o Extended Support ligado, a AWS não nos empurra para o 8.4.** O texto da própria
> recomendação do painel, lido da conta em 27/08/2026:
>
> *"Databases without Extended Support enabled will be automatically upgraded to MySQL 8.4 during
> a scheduled maintenance window. **Databases with Extended Support enabled will be upgraded to
> the final MySQL 8.0 version (8.0.46).**"*
>
> **Não existe data em que alguém sobe o banco por nós.** A premissa inicial do projeto — "sem
> Extended Support, a AWS atualiza sozinha para 8.4 numa janela escolhida por ela" — está
> correta, mas **descreve o cenário em que não se paga**. Como se paga, o destino automático é
> 8.0.46, que é 8.0.
>
> **A pressão é inteiramente de custo:** US$ 241,20/mês hoje (produção sozinha), ~US$ 292-298 no
> mês fechado, **dobrando para ~US$ 584/mês em 01/08/2028**.
>
> **Consequência operacional, e é a que importa:** este projeto pode **parar em qualquer ponto
> sem consequência**. Entre uma tarefa e outra, entre a Fase 2 e a Fase 3, no meio da janela —
> parar custa a mensalidade de mais um mês, e nada mais. Nenhum portão deste documento precisa
> ser forçado por relógio. Se um critério não passar, a resposta certa é sempre parar.

---

## ⚠️ Nota de método — a política de leitura foi desenhada para o levantamento, não para a execução

**Descoberto na janela de 29/08/2026, ao tentar criar a instância de teste.** A Fase A parou no
primeiro comando de RDS, e não por incompatibilidade: a credencial `bahia-pipeline` tem **RDS
somente leitura**, pela política inline `RdsSomenteLeituraParaUpgrade84` — criada na Fase 0, com o
nome deste projeto, e correta para o que se pedia dela **naquele momento**.

Simulação de política (`iam:SimulatePrincipalPolicy`, sem efeito colateral):

```
allowed       rds:Describe*, rds:DownloadDBLogFilePortion, kms:DescribeKey
implicitDeny  CreateDBParameterGroup, ModifyDBParameterGroup, CopyDBParameterGroup,
              RestoreDBInstanceFromDBSnapshot, ModifyDBInstance, DeleteDBInstance,
              CreateDBSnapshot, AddTagsToResource, CreateBlueGreenDeployment,
              SwitchoverBlueGreenDeployment, DeleteBlueGreenDeployment, kms:CreateGrant
```

É `implicitDeny`, não deny explícito: **nenhuma SCP no caminho**, anexar política resolve.

> **A lição, e ela é geral:** *permissão mínima para investigar não é permissão mínima para agir.*
> As duas são legítimas e **são políticas diferentes**. A segunda precisa ser pedida **na hora de
> executar**, não antecipada no levantamento — antecipá-la teria deixado o projeto inteiro com
> permissão de apagar produção durante semanas de leitura.
>
> **O custo do desenho certo é este:** uma parada no início da janela. **É o custo barato.** O
> desenho errado não teria parada nenhuma e teria dado a um agente automatizado
> `DeleteDBInstance` sobre produção desde o primeiro dia de levantamento.

**Corolário registrado em 29/08:** a credencial já tem `IAMFullAccess`, ou seja, **conseguiria
autoconceder** o acesso que faltava. Não o fez, e a razão está aqui como regra: *uma trava
removida por quem ela restringe deixa de ser trava.* A ampliação é pedida ao Albert, aplicada por
ele, e removida no fechamento da janela.

### Limite de tamanho — por que a política nova não entrou como inline

O teto de **2.048 caracteres é agregado por usuário**, não por política. As quatro inline já
existentes somam **1.556**, então sobram **492**:

| Política inline | Caracteres |
|---|---|
| `bahia-pipeline-cloudformation` | 102 |
| `bahia-pipeline-eks-iam` | 375 |
| `EFSMinimalAccess` | 810 |
| `RdsSomenteLeituraParaUpgrade84` | 269 |
| **Total** | **1.556 / 2.048** |

A primeira redação, com bloco KMS e `Sid`, tinha **756** caracteres — cabe folgada em 2.048
sozinha, e **não cabe no que sobra**. Política gerenciada resolveria (teto de 6.144), mas o
usuário **já tem 11 anexadas com quota de 10** — anexar a 12ª pode ser recusada.

**Saída adotada:** política **nova e separada**, `RdsEscritaUpgrade84`, com as **12 ações
explícitas** e **428 caracteres** — cabe nos 492 que sobram, **sem curinga** e **sem tocar na
`RdsSomenteLeituraParaUpgrade84`**, que fica intacta para ser a única coisa a restar no
fechamento da janela.

O que saiu, e por quê:

- **`Sid` e indentação** — o IAM não conta espaço em branco, mas o `Sid` conta.
- **O bloco KMS** (~180 caracteres). O snapshot é cifrado, mas com a chave **gerida pela AWS**
  (`alias/aws/rds`), cuja política de chave concede `kms:CreateGrant` a
  `Principal: "*"` sob condição de `kms:CallerAccount` + `kms:ViaService` — **ela basta sozinha**,
  sem permissão de identidade. Não deu para confirmar lendo a política da chave (`kms:GetKeyPolicy`
  também é negado), então **fica como o único ponto a verificar na retomada**: se a restauração
  falhar por KMS, o remendo é substituir a política de leitura pela combinada com curingas
  (491 caracteres, orçamento de 761).

---

## 0. Decisões tomadas — registro

| # | Decisão | Data |
|---|---|---|
| 1 | **Fase 1 no desenho de instância separada**, classe igual à de produção. Razão decisiva: o teste passa a dizer algo sobre **desempenho** | 27/08 |
| 2 | **Blue/Green aprovado** para produção | 27/08 |
| 3 | **ConfigMap vai para DNS agora**, tarefa própria, com verificação que **escreve** | 27/08 |
| 4 | **Verde frio é item obrigatório**, com critério de aquecimento em número | 27/08 |
| 5 | Action Scheduler: **causa primeiro**, depois limpeza | 27/08 |
| 6 | **Schema `portal-noticias` sai**, com dump próprio e verificação de que nada aponta para ele | 27/08 |
| 7 | Política de leitura do RDS aplicada (versão condensada, `rds:Describe*`) | 27/08 |
| 8 | Achado do `bahia_ambiente()` registrado no `HANDOVER.md` §0.2.1 | 27/08 |
| 9 | **As 5 tabelas do Rank Math saem**, mesmo tratamento do schema | 27/08 |
| 10 | **Roteiro da instância de teste aprovado**, sequência T1 → T3 → T2 → snapshot → instância | 27/08 |
| 11 | **`carga.sh` consertado antes de tudo** e a lição registrada no `HANDOVER.md` §16.3 | 27/08 |

## 0.1 O que mudou nesta revisão

Cinco coisas, e três delas mudam o plano.

1. **O `carga.sh` estava pior do que se supunha, e o conserto provou por quanto.** Além de não
   gravar, o monitor de banco colhia **3 amostras** onde devia colher dezenas. Medido lado a
   lado: com 3 amostras o pico de `Threads_running` dava **6**; com 31 dá **11**. O critério de
   aceitação da virada é "abaixo de 10" — **o instrumento decidia o portão ao contrário.** §T-1.
2. **Produção tem ~2 GiB livres de 20 GiB, sem autoscaling.** Isso é um risco vivo,
   independente deste projeto, e **reordena as tarefas**: a pré-verificação da AWS tem um item
   chamado *"DB instance must have enough free disk space"* que **cancela a subida** se não
   passar. §0.9.
3. **O Action Scheduler não está "parado": ele não existe.** A biblioteca saiu do site junto com
   o Rank Math em junho. Não há classe, não há fila, não há executor. Isso muda T2 de "apagar
   1,9 milhão de linhas em lotes" para **"apagar 4 tabelas órfãs"** — que, com 2 GiB livres, é a
   diferença entre seguro e arriscado. §T2.
4. **Um terceiro item recuperável apareceu:** `wp_adrotate_tracker` tem **5 MB de dados e
   2.056 MB de espaço morto** dentro do arquivo. É a limpeza mais barata do lote e vai primeiro.
   §T0.
5. **A pré-verificação da AWS já rodou neste banco**, em maio de 2025, e o log está lá:
   **0 erros, 1 aviso**. Sabemos exatamente o que o `PrePatchCompatibility.log` de 8.4 vai
   dizer. §0.10.

**O banco vai de 18 GiB ocupados para ~9,8 GiB**, e o espaço livre de **2 GiB para ~10 GiB**.

---

# FASE 0 — Levantamento (FECHADA)

## 0.1 Portão de contagem

| Consulta | Homolog | Produção |
|---|---|---|
| Variáveis globais | 655 | 651 |
| Usuários e plugin de autenticação | 6 | 6 |
| Índices FULLTEXT | **2** | **1** |
| Chaves estrangeiras | 5 por schema | 5 por schema |
| Rotinas / gatilhos / views / partições | 0 / 0 / 0 / 0 | 0 / 0 / 0 / 0 |
| Opções não-transientes | 653 | 590 |
| Ações no Action Scheduler | — | 1.894.997 |
| Linhas de log do Action Scheduler | — | 3.799.533 |
| Recomendações no painel do RDS | 4 | 5 |
| Parâmetros alterados à mão no parameter group | **0** | **1** |

## 0.2 As duas instâncias — agora completo

| | **Produção** | **Homolog** |
|---|---|---|
| Identificador | `rds-bahiaba-2023` | `rds-bahiaba-hml` |
| Versão | **8.0.42** | **8.0.45** |
| Classe | db.m5.xlarge (4 vCPU) | db.t3.micro (2 vCPU) |
| **Parameter group** | **`mysql80-edit`** (in-sync) | **`default.mysql8.0`** |
| **Armazenamento** | **20 GiB gp2** | **20 GiB gp2** |
| **Autoscaling de storage** | **desligado** | **desligado** |
| **Espaço livre hoje** | **~2 GiB** | **~2 GiB** |
| Dados no banco | 9,56 GB em 2 schemas | 9,5 GB em 2 schemas |
| Buffer pool | 11 GB (8 instâncias) | 0,25 GB (1) |
| Multi-AZ | não | não |
| Retenção de backup | **7 dias** (04:00-04:30 UTC) | 7 dias |
| Janela de manutenção | **sáb 05:00-05:30 UTC** | sáb 05:00-05:30 UTC |
| Auto minor upgrade | **não** | **sim** |
| Proteção contra remoção | **sim** | não |
| Subnet group / VPC | `default-vpc-4c49202b` / `vpc-4c49202b` | **o mesmo** |
| Security groups | `sg-0234245542eb43738` (MySQL) + `sg-0e96076df475b4843` (AcessoRestrito) | **`sg-0234245542eb43738`** |
| AZ | us-east-1c | us-east-1d |
| Performance Insights | desligado | desligado |
| Criada em | 2023-01-05 | 2026-07-28 |
| Uptime na medição | **455 dias** | 26 dias |

Quatro leituras desta tabela:

**Por que as versões divergem, agora explicado:** `AutoMinorVersionUpgrade` é **`false` em
produção e `true` em homolog**. Homolog subiu sozinha para 8.0.45; produção ficou em 8.0.42. Não
é acaso nem descuido de alguém — é configuração, e é a configuração certa para produção.

**Por que homolog alcança o banco de produção:** as duas instâncias **compartilham o security
group `sg-0234245542eb43738`**, na mesma VPC e no mesmo subnet group. Medido: de dentro do pod de
homolog, `172.31.70.197:3306` responde em **1 ms**. Está registrado no `HANDOVER.md` §0.2.1.

**Os parameter groups diferem, e agora sabemos exatamente por quê** — ver §0.7. São **um
parâmetro** de diferença.

**A janela de manutenção é sábado 05:00-05:30 UTC**, ou seja **02:00-02:30 no horário de
Brasília**. Vale como referência para a janela da Fase 4.

## 0.3 O que a política destravou

As 12 chamadas conferidas passaram. **Nenhuma negada** — incluindo as duas que `rds:Describe*`
sozinho **não** cobriria:

| Chamada | Resultado |
|---|---|
| `DescribeDBInstances`, `DescribeDBEngineVersions`, `DescribeDBRecommendations` | OK |
| `DescribeDBParameters`, `DescribeEngineDefaultParameters` | OK |
| `DescribeDBSnapshots`, `DescribeDBLogFiles`, `DescribeBlueGreenDeployments`, `DescribeReservedDBInstances` | OK |
| **`DownloadDBLogFilePortion`** | **OK** — é `Download*`, não `Describe*` |
| **`ListTagsForResource`** | **OK** — é `ListTags*`, não `Describe*` |
| CloudWatch: `ListMetrics`, `GetMetricStatistics`, `GetMetricData` | OK |

Se a versão condensada fosse só `rds:Describe*`, as duas do meio teriam falhado — e a segunda
delas é justamente a que lê o `PrePatchCompatibility.log`. Como passaram, a política aplicada
cobre mais que o prefixo. **Nada a acrescentar.**

## 0.4 `mysql_native_password` — a própria AWS já respondeu, neste banco

Além da documentação, agora há a prova local: **a pré-verificação de maio de 2025 já sinalizou
este usuário**, e o texto dela diz o que fazer:

> `rootbahiaba@%` - The following users are using the 'mysql_native_password' authentication
> method which is deprecated as of MySQL 8.0.34 and will be removed in a future release.
> Consider switching the users to a different authentication method (i.e. caching_sha2_password).
> **The 'mysql_native_password' authentication type is disabled by default in MySQL 8.4, but can
> still be enabled by setting `loose_mysql_native_password=ON`.**

Isso dá o **nome exato do parâmetro** — `loose_mysql_native_password` — vindo do próprio banco,
e não de blog. É a rede de segurança, não o plano: a AWS mantém os usuários existentes
funcionando após a subida (*"your existing database users including the primary user will
continue to use `mysql_native_password`"*).

Em homolog a mesma verificação sinaliza **dois** usuários: `rootbahiaba@%` e
`rds_superuser_role@%`. O segundo é papel interno do RDS, travado e com senha expirada.

**Recomendação inalterada: não mexer na autenticação nesta operação.** PHP 8.2.29 com mysqlnd
aguentaria `caching_sha2_password` (mínimo da AWS: 7.4.4), mas trocar autenticação no meio de uma
subida de versão junta dois riscos sem ganho.

## 0.5 O FULLTEXT sobrevive — e a pré-verificação tem um item para isso

**Correção à revisão anterior:** eu havia escrito que a lista de incompatibilidades da AWS não
menciona FULLTEXT. A lista publicada não menciona, mas **a verificação real tem um item
dedicado**, e nas duas instâncias ele passou:

```
5) Tables with dangling FULLTEXT index reference        [produção]
	No issues found.
6) Tables with dangling FULLTEXT index reference        [homolog]
	No issues found.
```

Somado ao resto: formato de índice FULLTEXT inalterado entre 8.0 e 8.4; collation
`utf8mb4_0900_ai_ci` idêntica; **todos os `innodb_ft_*` no padrão** (conferidos um a um, nada
para transportar); e, pelo Blue/Green, o índice é copiado **fisicamente** por snapshot.

Estado: `wp_bahia_search_idx.ft` sobre `post_title, post_excerpt`, **256.725 linhas / 46,6 MB**
em produção, com 11 objetos auxiliares `fts_*` íntegros. Homolog tem um índice a mais
(`wp_posts.bahia_ft_search`), que produção não tem.

Se falhar mesmo assim, o conserto é uma linha sobre 46 MB:

```sql
ALTER TABLE wp_bahia_search_idx ADD FULLTEXT KEY ft (post_title, post_excerpt);
```

## 0.6 Charset e collation — utf8mb3 existe e não bloqueia

**59 das 90 tabelas do schema `prod` em `utf8mb3_general_ci`**, incluindo `wp_posts`,
`wp_postmeta`, `wp_options`, `wp_users`, `wp_term_relationships`. Mais 23 em utf8mb4 e 8 em
latin1. Conexão em utf8mb4, armazenamento em utf8mb3 — anterior a tudo que fizemos.

A AWS trata como recomendação: *"**Consider** converting…"*. Depreciado ≠ removido.
**Não converter agora** — reescrever `wp_posts` (1,1 GB) e `wp_postmeta` (1,7 GB) mexe em 21
índices com prefixo de 191 caracteres e tem risco de truncamento. Projeto próprio.

## 0.7 Parameter groups — a resposta é quase decepcionante, e isso é bom

**`mysql80-edit`, de produção, tem exatamente UM parâmetro alterado à mão:**

```
innodb_strict_mode    0    dynamic    modificável
```

**Nada mais.** Todo o resto é padrão do motor.

**`default.mysql8.0`, de homolog, é o parameter group padrão da AWS** — imutável, zero
parâmetros de usuário. Como o padrão do `innodb_strict_mode` é `1`, fica explicada, com uma
linha, toda a divergência de configuração entre os dois ambientes que eu havia inferido na
revisão 1.

**Consequência prática: montar o parameter group 8.4 é trivial.** Ele tem quatro linhas:

```
innodb_strict_mode         = 0      # o único parâmetro herdado de mysql80-edit
innodb_adaptive_hash_index = 1      # padrão do 8.4 seria OFF   — fixado
innodb_change_buffering    = all    # padrão do 8.4 seria none  — fixado
innodb_io_capacity         = 200    # padrão do 8.4 seria 10000 — fixado
```

Os três últimos são a decisão de desenho: **fixar os valores de hoje para que a subida mude só a
versão.** Cada mudança de padrão vira experimento separado depois — se o site ficar lento, o
número de suspeitos precisa ser um.

### O que some no 8.4 e está no nosso banco

Nenhum destes está no parameter group (que só tem um parâmetro), mas **os valores efetivos
existem** e é bom saber que somem sozinhos: `default_authentication_plugin`,
`transaction_write_set_extraction`, `master_info_repository`, `relay_log_info_repository`,
`expire_logs_days`, `slave_parallel_workers`, `binlog_transaction_dependency_tracking`.
Como nenhum está fixado à mão, **não há nada a transportar e nada a esquecer.**

## 0.8 Itens da pré-verificação conferidos por consulta

| Item | Resultado |
|---|---|
| Tipos de dado obsoletos | 0 colunas temporais em formato antigo |
| Gatilhos sem *definer* | 0 gatilhos |
| Palavras reservadas novas em 8.4 | 0 em nomes de tabela ou coluna |
| Tabelas do `mysql` colidindo com o dicionário 8.4 | 10 extras, todas `rds_*` / `*_backup` da AWS |
| `sql_mode` obsoleto | `NO_ENGINE_SUBSTITUTION` — limpo |
| `ENUM`/`SET` acima de 255 caracteres | 0 |
| Nome de chave estrangeira > 64 caracteres | maior tem 33 |
| Partição em engine sem suporte | 0 tabelas particionadas |
| Engine não-InnoDB | schema `prod`: 89 InnoDB, 1 `MEMORY` |
| `restrict_fk_on_non_standard_key` (novo no 8.4) | **as 5 chaves estrangeiras apontam para `PRIMARY`** — fechado |
| Datas zero | 1.885.398 linhas, **todas no lixo que T2 remove**; sobram 2 |

## 0.9 ⚠️ Armazenamento — o achado que reordena o projeto

**Produção: 20 GiB alocados, ~2 GiB livres, autoscaling DESLIGADO.** Homolog, idem.

A própria AWS já reclama disso: a recomendação `config_recommendation::storage_autoscaling_off`
está **ativa nas duas instâncias** — na de produção **desde 07/04/2025**.

### Por que isto é do projeto, e não uma nota de rodapé

A pré-verificação obrigatória da AWS tem este item, que li no log da subida anterior:

```
7) DB instance must have enough free disk space
	No issues found.
```

Em maio de 2025 passou. **Hoje, com 2 GiB livres, pode não passar — e um item reprovado
cancela a subida.** Sem contar que a reconstrução de tabela do plano antigo de T2 exigiria
~1,8 GiB de espaço temporário, que é quase tudo o que existe.

### Onde estão os 18 GiB, medido

| Ocupante | Tamanho |
|---|---|
| Schema `prod` — dados e índices | **6,44 GB** |
| Schema `prod` — **espaço morto dentro dos arquivos** | **2,29 GB** |
| Schema `homolog` (`portal-noticias`) | **3,12 GB** + 0,06 morto |
| Schema `mysql` | 0,01 GB + 0,48 morto |
| Redo log (`innodb_redo_log_capacity`) | 2,00 GB |
| Undo (2 tablespaces, até 1 GB cada) | até 2 GB |
| **Binlogs** | **3 arquivos, ~0 GB** (`binlog retention hours` = NULL) |

**Os binlogs não são o problema** — são três arquivos e praticamente nada. O problema é espaço
morto e schema parado.

E o espaço morto tem um dono quase único:

| Tabela | Dados | **Morto dentro do arquivo** |
|---|---|---|
| **`wp_adrotate_tracker`** | **5,0 MB** | **2.056,0 MB** |
| `wp_actionscheduler_actions` | 1.793,7 MB | 81,0 MB |

`wp_adrotate_tracker` é a tabela de rastreio de anúncios do AdRotate. O evento
`adrotate_empty_trackerdata` roda no WP-Cron e a esvazia periodicamente — **mas esvaziar não
devolve o arquivo ao disco.** Sobrou um `.ibd` de 2 GB com 5 MB dentro.

### Como fica depois das limpezas

| Etapa | Ocupado | Livre |
|---|---|---|
| Hoje | **18,0 GiB** | **2,0 GiB** |
| **T0** — reconstruir `wp_adrotate_tracker` | 16,0 | 4,0 |
| **T3** — `DROP DATABASE homolog` | 12,8 | 7,2 |
| **T2** — apagar as 4 tabelas do Action Scheduler | 10,4 | 9,6 |
| **T4** — apagar as 5 tabelas do Rank Math | **9,8 GiB** | **~10,2 GiB** |

**De 10% livre para ~51% livre.** E o dado do banco cai de 9,56 GB para **~3,6 GB**.

### Recomendação à parte: ligar o autoscaling

Não é deste projeto, custa zero até disparar, e a AWS pede há mais de um ano:

```bash
aws rds modify-db-instance --db-instance-identifier rds-bahiaba-2023 \
  --max-allocated-storage 100 --apply-immediately --region us-east-1
```

Fica registrado. **Não executado, e não incluído em nenhuma tarefa** — é sua decisão.

## 0.10 A verificação de pré-atualização — já existe, e diz 0 erros

O item 2 da Fase 0 pedia rodar a verificação e abrir o "Visualizar detalhes". As duas coisas
estão fechadas, e com uma surpresa boa.

### As recomendações do painel não listam incompatibilidade nenhuma

São **9 recomendações ativas**, e nenhuma é sobre incompatibilidade de subida:

| Severidade | Recomendação | Instância |
|---|---|---|
| **high** | Extended Support / fim do suporte padrão do 8.0 | ambas |
| informational | `storage_autoscaling_off` | ambas |
| informational | `multi_az_instance` | ambas |
| informational | `old_minor_version` (8.0.42) | prod |
| informational | `performance_insights_off` | prod |
| informational | `enhanced_monitoring_off` | homolog |

**O "Visualizar detalhes" da recomendação `high` é o aviso de fim de vida**, não uma lista de
problemas. Mas o texto traz um fato que muda a leitura da urgência:

> "Databases without Extended Support enabled will be automatically upgraded to MySQL 8.4 during
> a scheduled maintenance window. **Databases with Extended Support enabled will be upgraded to
> the final MySQL 8.0 version (8.0.46).**"

**Com o Extended Support ligado, a AWS não nos empurra para o 8.4 — ela nos leva ao 8.0.46.**
Ou seja: enquanto se paga, não há subida forçada. **A pressão é de custo, não de prazo.** Isso
não muda a decisão de subir; muda o tom com que se marca a janela.

A recomendação da AWS é literal: *"Upgrade to MySQL 8.4.8 or higher"*.

### A pré-verificação real já rodou neste banco

O `PrePatchCompatibility.log` de produção está lá desde **29/05/2025**, da subida 8.0.35 → 8.0.42:

```
Errors: 0
Warnings: 1
Database Objects Affected: 3
```

Os oito itens, e o que cada um disse:

| # | Item | Resultado |
|---|---|---|
| 1 | Sintaxe de rotinas | limpo |
| 2 | `check table x for upgrade` | **`wp_bwg_theme` — Row size too large (> 8126)**, nos schemas `dev` e `prod` |
| 3 | Métodos de autenticação | **`rootbahiaba@%` com `mysql_native_password`** |
| 4 | Plugins removidos ou depreciados | limpo |
| 5 | **Referência pendente de índice FULLTEXT** | **limpo** |
| 6 | Versões de TLS depreciadas | limpo |
| 7 | **Espaço livre em disco** | limpo **em 2025** — ver §0.9 |
| 8 | Divergência entre dicionário do InnoDB e definição da tabela | limpo |

Homolog, na subida 8.0.42 → 8.0.45: **0 erros, 1 aviso**, e o aviso é o mesmo (autenticação, dois
usuários lá).

**O que esperar do `PrePatchCompatibility.log` de 8.4**, portanto, com base em evidência e não em
palpite:

1. **`wp_bwg_theme` — "Row size too large"**, aviso pré-existente há pelo menos 15 meses. É a
   tabela do plugin Photo Gallery, com **293 colunas utf8mb3** — a mais larga do banco. Não
   impediu a subida anterior.
2. **`rootbahiaba` com `mysql_native_password`**, aviso, com a saída documentada em §0.4.
3. **Datas zero** — 1.885.398 linhas hoje, **2 depois de T2**.
4. **utf8mb3**, aviso consultivo.
5. **Espaço em disco — o único que pode virar ERRO**, e é o que as tarefas da Fase 0.5 resolvem.

Um detalhe de bônus no log de 2025: ele cita um schema **`dev`** que **hoje não existe mais**
naquela instância. Alguém já removeu um schema ali antes; T3 não é inédito. Os schemas de hoje
são `prod`, `homolog`, `test` (vazio) e os quatro do sistema.

## 0.11 Versão de destino: **8.4.9**

| Pergunta | Resposta |
|---|---|
| Destinos válidos a partir de 8.0.42 **e** de 8.0.45 | 8.4.3, 8.4.4, 8.4.5, 8.4.6, 8.4.7, **8.4.8**, **8.4.9**, 8.4.10, 8.4.11 |
| Disponíveis para criação hoje | 8.4.5 a 8.4.11 |
| **Marcada como padrão pela AWS** | **8.4.9** |
| Mínimo recomendado pela recomendação do painel | 8.4.8 |

**Alvo: 8.4.9.** Satisfaz o "8.4.8 ou superior" e é a versão que a AWS escolhe para instâncias
novas — a mais rodada em campo. 8.4.11 é a mais nova; ser a mais nova não é vantagem numa
operação cujo objetivo é não ter surpresa.

## 0.12 Reservas: não há

`describe-reserved-db-instances` devolveu **vazio**. Nenhuma Reserved Instance na conta.

**Consequência para o §3.6:** a `db.m5.xlarge` de produção é sob demanda, e o verde do
Blue/Green também será. Não há complicação de cobertura — a estimativa de custo vale como está.

## 0.13 O lado da aplicação

| Item | Valor |
|---|---|
| PHP | **8.2.29**, mysqli sobre **mysqlnd 8.2.29** |
| WordPress | **6.8.8** (`db_version` 60421) |
| Plugins ativos | **24** — Yoast SEO 27.7, Co-Authors Plus 3.6.6, AdRotate Pro, WP Offload Media |
| mu-plugins | 63 arquivos, 6 com SQL direto |

| Construção nossa | 8.4 |
|---|---|
| `MATCH(…) AGAINST (… IN BOOLEAN MODE)` — 5 ocorrências | inalterado |
| Remoção de `SQL_CALC_FOUND_ROWS` por `preg_replace` — 3 mu-plugins | depreciado desde 8.0.17, **ainda existe em 8.4**; nosso código o **remove**, não depende dele |
| `CREATE TABLE` / `ALTER TABLE … ADD FULLTEXT` | inalterado |

**Nenhuma construção nossa muda de comportamento.** O que muda é o plano do otimizador, e isso
só se vê medindo.

---

# FASE 0.5 — As tarefas independentes

## T-1 — `carga.sh` consertado ✅ FEITO

**Executado em 27/08/2026**, por sua instrução de que viesse antes de tudo. Original preservado
em `carga.sh.orig-20260827`. A lição completa está no `HANDOVER.md` §16.3.

### Os dois defeitos

**Defeito 1 — escrevia num diretório apagado.** A variável `S` apontava para o scratchpad de uma
sessão que já não existe. Com ele ausente, o `rm -f` passava calado, os 30 `>>` falhavam um a um,
e **a carga rodava inteira sem gravar nada**. Pior: se o diretório de outra sessão ainda
existisse, gravaria lá, e a leitura sairia de uma execução anterior.

**Defeito 2, o grave — o pico vinha de 3 amostras.** O monitor fazia 24 `kubectl exec` separados,
cada um com `require_once wp-load.php`. O bootstrap custa ~5 s e a carga termina em ~16 s:
**colhiam-se 3 amostras**, não 24.

### A prova, medida em homolog com minutos de intervalo

| Execução | Amostras | **`Threads_running` pico** | `SQL_CALC` pico |
|---|---|---|---|
| monitor antigo | **3** | **6** | 2 |
| monitor novo | **31** | **11** | 5 |

**O critério de aceitação em uso desde 18/08 é "`Threads_running` abaixo de 10 no pico".** Com 3
amostras dava 6 e **passava**. Com 31 dá 11 e **reprova**. O instrumento não errava por pouco —
**decidia o portão ao contrário**, e teria assinado a virada.

### O que o script faz agora

1. **Testa a escrita ANTES de gastar a medição** (`mkdir -p` + toque no diretório). Falhar aqui
   custa nada; falhar depois custa a carga inteira.
2. **Portão de contagem impresso, com código de saída**:
   ```
   --- PORTAO DE CONTAGEM ---
     URLs disparadas: 30   respostas gravadas: 30   amostras do banco: 31
   ```
   Se `gravadas ≠ disparadas`, ou amostras < 10: imprime `*** FALHOU`, avisa que os números estão
   incompletos e **sai com código 1**.
3. **Monitor num único `kubectl exec`**, com laço PHP e `mysqli` direto, amostrando a cada 0,5 s.
4. **Saída ao lado do script** (`./carga-saida/`), com `CARGA_OUT` para sobrescrever.
5. **Alvo parametrizável** por `CARGA_CTX` e `CARGA_BASE` — necessário para medir a instância de
   teste e o verde sem editar o arquivo.

### A linha de base de homolog, de brinde

Como o conserto exigiu rodar de verdade, ficou registrada a medida de hoje em homolog:

| | valor |
|---|---|
| Respostas | **30 de 30**, todas 200 |
| Tempo | min 2,72 s · **mediana 9,52 s** · p90 12,90 s · máx 13,65 s |
| Acima de 5 s | **24 de 30** |
| `Threads_running` | **pico 11**, média 3,0, 31 amostras |

É o t3.micro fazendo o que o t3.micro faz, e é exatamente por isso que a Fase 1 mudou de desenho.

---

## T1 — `WORDPRESS_DB_HOST` de produção: IP → DNS ✅ CONCLUÍDO

> ### FECHADO EM 27/08/2026 — e o percurso importa, porque deixou um job vermelho
>
> **Commit `6f0d8e5` na `master` do `infra-bahiaba`.** Estado final:
>
> | | |
> |---|---|
> | ConfigMap no cluster | **nome DNS** |
> | Pods | **4 de 4 com o nome DNS**, todos 2/2 prontos |
> | Deployment | despausado, `updatedReplicas = 4` |
> | Site depois do rollout | **5 de 5** no esperado (o `410` do `/feed/` é proposital desde 18/08) |
> | Escrita pelo caminho do WordPress | **CONFIRMADA** — `wp_insert_post` → ID 9001748, `post_meta` gravado, **1 revisão**, tudo lido direto do banco e apagado sem sobra (0 linhas em `wp_posts` e `wp_postmeta`) |
>
> ### ⚠️ O job `Deploy Kubernetes (Prod)` desta execução ficou VERMELHO. Foi de propósito.
>
> **Data: 27/08/2026, ~13h50 WEST. Commit `6f0d8e5`. Não investigue como incidente.**
>
> O push saiu durante um **pico de acesso**, por corrida de mensagens — a instrução de adiar a
> tarefa para o vale de tráfego chegou depois de o commit já estar na `master`. Para não deixar
> os pods reiniciarem em pico, o rollout foi **pausado à mão**:
>
> ```bash
> kubectl -n bahia-wordpress rollout pause deployment/wordpress
> ```
>
> Com o Deployment pausado, o passo `kubectl rollout status … --timeout=300s` do workflow
> **não tem como concluir** e o job falha por tempo esgotado. **A pausa é a causa; o job vermelho
> é o sintoma esperado.** Nada ficou meio aplicado: o ConfigMap foi aplicado normalmente, e a
> troca dos pods aconteceu depois, com `rollout resume`, sob acompanhamento.
>
> **O que se aprendeu, e vale para qualquer pausa futura:** pausar o Deployment é a forma certa
> de separar "aplicar a configuração" de "trocar os pods", mas **o preço é um job vermelho no
> histórico do Actions**. Se for feito de novo, anotar aqui, com data e commit — foi para isso
> que este bloco existe.
>
> ### O que a pausa deixou de brinde: um canário
>
> O pipeline alcançou criar **um** pod novo antes da pausa. Resultado: 1 pod pelo nome DNS e 5
> pelo IP, todos servindo, **todos no mesmo servidor `ip-10-1-4-202`**. Isso permitiu provar o
> mecanismo em produção antes de tocar nos outros cinco — e foi essa medição que fechou a única
> incerteza real da tarefa (*"e se o DNS não resolver de dentro do cluster?"*).
>
> Durante todo o evento a **capacidade nunca caiu**: `maxSurge=1` / `maxUnavailable=0` mantiveram
> os pods antigos 2/2, e as 12 amostras de HTTP feitas no meio deram **200**.
>
> ### O passo que ficou com você
>
> **Publicar uma matéria pelo painel.** O teste acima usa `wp_insert_post()`, que é o mesmo
> caminho de escrita do núcleo, mas **não** cobre o percurso do navegador: REST do editor de
> blocos, nonce e autosave. Não posso fazer esse: exigiria digitar senha.
>
> **Correção de 27/08, e é do mesmo tipo de erro que este documento persegue:** eu havia dito que
> o login ficava em `https://bahia.ba/acesso/`, atrás do `wps-hide-login`. **Está errado.** Li a
> opção `whl_page` (que vale `'acesso'`) sem conferir se o plugin que dá sentido a ela está
> ligado — e **não está**: `wps-hide-login` não consta de `active_plugins`. A opção é resto de
> quando esteve. Consequência medida: `/acesso/` e `/acesso` devolvem **301** e o
> `redirect_guess_404_permalink` do núcleo leva para uma matéria
> (`/politica/acesso-a-alba-so-com-comprovante-de-vacina/`).
>
> **O login de produção é `https://bahia.ba/wp-login.php`, e responde 200 — não está escondido.**
> Fica registrado como observação de segurança: o plugin está no diretório `plugins/` mas
> desativado, e `whl_redirect_admin` está em `'404'`, então religá-lo mudaria o comportamento de
> `/wp-admin/`. **Não mexi.**
>
> Verificação, depois de publicar e apagar:
> ```sql
> SELECT ID, post_title, post_status, post_modified FROM wp_posts
>  WHERE post_title LIKE 'TESTE%' ORDER BY ID DESC LIMIT 5;
> ```

### Por que agora

Falha silenciosa e parcial é o padrão que já mordeu três vezes. Se o IP sobreviver até a virada:
a troca do Blue/Green **renomeia endpoints, não IPs**. Depois dela, o nome aponta para o 8.4 e
**`172.31.70.197` continua apontando para o azul**, renomeado `-old1` e **somente leitura**.

O site continua **lendo normalmente** e **só as escritas falham**, com
`ERROR 1290 (HY000): The MySQL server is running with the --read-only option`. Na redação isso é:
**repórter publica, matéria some, ninguém liga as duas coisas.**

Medido: `rds-bahiaba-2023…rds.amazonaws.com` resolve **hoje** para `172.31.70.197` — a troca é de
forma, não de destino.

### A mudança

`infra-bahiaba/kubernetes/prod/wordpress/configmap.yaml`, linha 7:

```yaml
# antes
  WORDPRESS_DB_HOST: "172.31.70.197"
# depois
  WORDPRESS_DB_HOST: "rds-bahiaba-2023.cr9zu4ke1bev.us-east-1.rds.amazonaws.com"
```

Homolog já faz assim, e o IP está lá **comentado** — o caminho é conhecido e está em produção
há meses do outro lado.

### O reinício é automático — verificado no workflow

O ConfigMap é consumido por `envFrom`/`configMapRef` (`deployment.yaml` linhas 62-64), e variável
de ambiente **só chega ao processo em pod novo**. O `tf-apply.yml` já cuida:

```
linha 352:  kubectl apply -f kubernetes/prod/wordpress/configmap.yaml
linha 378:  kubectl rollout restart deployment/wordpress -n bahia-wordpress
linha 382:  kubectl rollout status  deployment/wordpress -n bahia-wordpress --timeout=300s
```

### Verificação — tem de ESCREVER

O modo de falha que estamos evitando **deixa a leitura funcionando**. Abrir o site não prova nada.

1. **Antes**, o alvo de cada pod, com portão de contagem:
   ```bash
   CTX_PROD="arn:aws:eks:us-east-1:774710032593:cluster/bahia-eks-prod"
   PODS=$(kubectl --context "$CTX_PROD" -n bahia-wordpress get pods -l app=wordpress \
            -o jsonpath='{.items[*].metadata.name}')
   n=0; for p in $PODS; do
     echo -n "$p -> "; kubectl --context "$CTX_PROD" -n bahia-wordpress exec $p -c wordpress \
       -- printenv WORDPRESS_DB_HOST; n=$((n+1))
   done; echo "pods conferidos: $n"
   ```
   Esperado antes: `172.31.70.197` em todos.
2. **Depois do rollout**, o mesmo comando: o nome DNS em **todos**. Um pod antigo sobrevivente
   invalida a verificação.
3. **Publicar uma matéria de teste pelo painel** (rascunho) e confirmar que **grava**:
   ```sql
   SELECT ID, post_title, post_status, post_modified FROM wp_posts
    WHERE post_title LIKE 'TESTE T1 %' ORDER BY ID DESC LIMIT 1;
   ```
   **É este passo que "o site abriu" não faz.**
4. Conferir que não há erro 1290 no log dos pods na janela do rollout.
5. Apagar a matéria de teste.

### Rollback e risco

Reverter o commit e empurrar; o pipeline reaplica e reinicia. **Nenhum estado a desfazer.** Risco
baixo: se o DNS não resolvesse de dentro do pod, o site cairia inteiro e de forma óbvia — não
silenciosa. E não vai cair: a resolução foi testada de dentro do pod de produção.

---

## T0 — `wp_adrotate_tracker`: 2 GB de arquivo com 5 MB dentro ✅ CONCLUÍDO

> ### EXECUTADO EM 29/08/2026, 03:10 UTC — na janela de manutenção
>
> | | |
> |---|---|
> | Dump | `~/BAHIABA-backups/dump-adrotate-tracker-20260829-0410.sql.gz` |
> | Tamanho | **196.035 bytes** · bruto 1.083.092 B · permissão `444` |
> | SHA-256 | `99ca121f61c5cc85270250735c35d7343b1f231d889e7006628bf7b4b4d0a0c5` |
> | Linhas no dump | **22.309**, conferidas tupla a tupla contra a origem |
> | `lock_wait_timeout` da sessão | **5 s**, confirmado por `SELECT @@session.lock_wait_timeout` antes do comando |
> | Duração do `OPTIMIZE` | **0,612 s** |
> | **Bloqueio real da tabela** | **≤ 0,129 s** — sonda paralela, 1.166 leituras em 60 s, **0 falhas** |
> | `CHECK TABLE` depois | `OK` |
> | Site depois | 200 em home, busca e archive |
>
> **Ganho: +2,032 GiB.** Arquivo da tabela de **~2.062 MB → 11,0 MB**; `FreeStorageSpace` de
> **9,432 → 11,464 GiB** no CloudWatch, às 03:11 UTC.
>
> A nota *"Table does not support optimize, doing recreate + analyze instead"* apareceu como
> previsto, seguida de `status OK`.
>
> **Não havia `mysqldump` nem cliente `mysql` na imagem** — só PHP. O dump foi escrito em PHP
> (`SELECT` → `INSERT`), com guarda de ambiente que aborta se `siteurl` não for `https://bahia.ba`,
> e **sem `DROP TABLE`**, de propósito: restaurar exige passo manual.
>
> ### ⚠️ Armadilha de medição — `DATA_FREE` mente depois do `OPTIMIZE`
>
> Logo após o comando, `information_schema.TABLES` **ainda dizia `DATA_FREE = 2.056 MB`** — o
> valor de antes, idêntico. A estatística é cacheada (`innodb_stats_on_metadata=OFF` é o padrão
> no 8.0) e **não se atualiza sozinha na leitura**.
>
> **O dado autoritativo é `information_schema.INNODB_TABLESPACES.FILE_SIZE`**, que já mostrava
> 11,0 MB. Um `ANALYZE TABLE` depois trouxe a `TABLES` para 3,4 MB usados / 4,0 MB mortos.
>
> Quem conferir só pela `TABLES` conclui que o `OPTIMIZE` não fez nada e repete a operação sem
> necessidade. **Confirmar sempre por `INNODB_TABLESPACES`, e cruzar com `FreeStorageSpace`.**

**Nova tarefa, descoberta no levantamento de armazenamento.** É a mais barata do lote e vai
primeiro, porque é o que dá folga para as outras.

| | |
|---|---|
| Dados | **5,0 MB** |
| Espaço morto dentro do arquivo | **2.056,0 MB** |
| Causa | o evento `adrotate_empty_trackerdata` (WP-Cron) esvazia a tabela periodicamente, mas esvaziar **não devolve o arquivo ao disco** |
| Ganho | **~2,0 GB**, imediato |
| Espaço temporário exigido | **~5 MB** — é o tamanho do que se reconstrói |
| Risco | mínimo: `OPTIMIZE TABLE` no InnoDB é `ALTER TABLE … FORCE`, **online**, permite DML concorrente |

```sql
-- antes
SELECT ROUND((DATA_LENGTH+INDEX_LENGTH)/1024/1024,1) AS usado_mb,
       ROUND(DATA_FREE/1024/1024,1) AS morto_mb
  FROM information_schema.TABLES
 WHERE TABLE_SCHEMA='prod' AND TABLE_NAME='wp_adrotate_tracker';   -- 5,0 / 2056,0

OPTIMIZE TABLE wp_adrotate_tracker;
-- devolve a nota "Table does not support optimize, doing recreate + analyze instead".
-- É normal e não é erro.

-- depois: a mesma consulta, e o FreeStorageSpace no CloudWatch deve subir ~2 GB
```

**Sem dump próprio.** São 5 MB de dados de rastreio de anúncio que o próprio plugin apaga
sozinho por agendamento; e o `OPTIMIZE` **não apaga linha nenhuma** — reconstrói o arquivo com as
mesmas linhas. O backup diário do RDS cobre.

**Por que primeiro:** com 2 GiB livres, qualquer operação que precise de espaço temporário é
arriscada. T0 não precisa de espaço e devolve 2 GB. Depois dele, o resto tem folga.

---

## T2 — Action Scheduler ✅ CONCLUÍDO

> ### EXECUTADO EM 27/08/2026, 14h33 WEST (13h33 UTC)
>
> | | |
> |---|---|
> | Backup | `~/BAHIABA-backups/dump-actionscheduler-20260827-1429.sql.gz` |
> | Tamanho | **122.867.889 bytes** (117 MB) · bruto 1.098.248.794 B · permissão `444` |
> | SHA-256 | `618b75b677858956d01434527dc861dfb525057bbbde0b4df97ba316650f9cd1` |
> | Duração do dump | **55 s**, código 0, stderr vazio |
> | Duração do `DROP` | **5 s** |
> | `prod` antes → depois | **90 tabelas / 6,441 GB → 86 tabelas / 4,141 GB** |
> | **Espaço livre antes** | **6,411 GiB** |
> | **Espaço livre depois** | **8,849 GiB** |
> | **Ganho medido** | **+2,44 GiB** |
> | Site depois | **5 de 5 em 200** + matéria seguindo o canônico em 200 |
> | Log dos 5 pods, 5 min após | **0 menções** a `actionscheduler`, `doesn't exist` ou erro 1146 |
>
> **Os quatro portões:**
>
> | Portão | Resultado |
> |---|---|
> | 1 — integridade | `gzip -t` OK · SHA-256 conferido · `444` |
> | 2 — objetos | **4 `CREATE TABLE`**, as quatro esperadas |
> | 3 — terminou | rodapé `-- Dump completed on 2026-08-27 13:30:38`; primeira linha é SQL |
> | 4 — **restaura** | MySQL 8.0.42 descartável, **55 s**, código 0 |
>
> | Verificação | Referência | Restaurado | |
> |---|---|---|---|
> | `wp_actionscheduler_actions` | 1.894.997 | 1.894.997 | ✅ |
> | `wp_actionscheduler_logs` | 3.799.533 | 3.799.533 | ✅ |
> | `wp_actionscheduler_claims` | 2 | 2 | ✅ |
> | `wp_actionscheduler_groups` | 3 | 3 | ✅ |
> | Composição por status | canceled 1.885.457 / complete 9.535 / failed 3 / pending 2 | idêntica | ✅ |
> | As 2 pendentes preservadas | — | `2985509` e `2985507` presentes | ✅ |
>
> ### A regra do T3 foi aplicada: nenhuma contagem no banco vivo
>
> As contagens de referência **não foram remedidas**. Vieram da medição das ~11h30 UTC, feita
> antes de a regra existir, e a validade delas foi provada **só por metadados**:
> `AUTO_INCREMENT` de `wp_actionscheduler_actions` continuava em **2.985.510** e `UPDATE_TIME` em
> **2026-06-02 12:03:37** nas três tabelas ativas. Tabela congelada, contagem antiga vale.
> **Zero páginas de dado lidas fora do próprio dump.**
>
> ### A guarda que acusou falso positivo
>
> A conferência de conexões usou `WHERE INFO LIKE '%actionscheduler%'` e devolveu **1**. Era a
> **própria consulta**, casando com o próprio texto no `PROCESSLIST` — confirmado comparando o
> `ID` com `CONNECTION_ID()`. Excluindo a si mesma: **0**. **Toda guarda que procura pelo texto
> da consulta precisa de `AND ID <> CONNECTION_ID()`**, senão nunca devolve zero e o operador
> aprende a ignorá-la — que é o pior destino de um alarme.
>
> ### O que ficou de propósito
>
> As 2 opções `schema-ActionScheduler_LoggerSchema` e `schema-ActionScheduler_StoreSchema` em
> `wp_options`. São inertes; apagá-las é tarefa de outra natureza.
>
> ### Efeito colateral bom, agora consumado
>
> As **1.885.398 linhas com data zero** do banco estavam todas nestas tabelas. Não há mais
> nenhuma: `wp_posts`, `wp_comments`, `wp_users` e os logs já tinham zero. O aviso de datas zero
> **não vai aparecer** no `PrePatchCompatibility.log` de 8.4.


### T2.1 A causa: Rank Math, e ele já foi embora

**Nenhum dos três suspeitos.** Não é a reconciliação do offload, não é o Yoast, não é o Smush.

| Hook | Status | Linhas |
|---|---|---|
| **`rank_math/analytics/get_inspections_data`** | **canceled** | **1.885.457** |
| `rank_math/analytics/get_inspections_data` | complete | 9.477 |
| `action_scheduler_run_recurring_actions_schedule_hook` | complete | 30 |
| demais hooks `rank_math/*` | complete/failed | 29 |
| `rank_math/analytics/data_fetch` | **pending** | 1 |
| `action_scheduler_run_recurring_actions_schedule_hook` | **pending** | 1 |

**100% das canceladas são um único hook.** O grupo confirma: `rank-math` tem **1.894.965 das
1.894.997** ações do banco.

### T2.2 Não continua acontecendo

| Evidência | Valor |
|---|---|
| Logs por mês de criação | 2026-04: **2.529.689** · 2026-05: **1.269.838** · 2026-06: **3** |
| Última linha de log | **2026-06-01 13:06:25** |
| `AUTO_INCREMENT` de `wp_actionscheduler_actions` | **2.985.510**, parado |

**Congelado há 86 dias.** As datas de agendamento vão de maio a **setembro de 2026** — canceladas
e no futuro, nunca vão rodar.

### T2.3 O que aconteceu, com hora

**O Rank Math foi substituído pelo Yoast em 02/06/2026, por volta das 12h UTC.**

| Marca | Hora |
|---|---|
| Última linha de log do Action Scheduler | 2026-06-01 13:06:25 |
| Última escrita nas tabelas internas do Rank Math | **2026-06-02 12:02:16** |
| Criação da `wp_yoast_indexable` | **2026-06-02 12:06:07** |
| As 2 ações pendentes, agendadas e nunca executadas | 2026-06-02 13:06:25 e 15:41:46 |

`active_plugins` tem 24 entradas, **nenhuma de Rank Math**; `wordpress-seo` está ativo; o
diretório `plugins/` não contém `seo-by-rank-math`.

### T2.4 O Action Scheduler não está parado — ele **não existe**

Você pediu para investigar isto à parte, e a investigação fecha em quatro medições:

| Verificação | Resultado |
|---|---|
| `class_exists('ActionScheduler')` | **AUSENTE** |
| `function_exists('as_schedule_recurring_action')` | **AUSENTE** |
| `has_action('action_scheduler_run_queue')` | **false** |
| Plugin ativo que embarque uma cópia da biblioteca | **nenhum** |
| Referência a Action Scheduler no nosso código | **nenhuma** |

**A biblioteca vinha embarcada no Rank Math e saiu com ele.** Não há classe, não há fila, não há
executor. As 2 ações "pendentes" não estão atrasadas — **não há código capaz de executá-las.**

### T2.5 E o WP-Cron está saudável

| Verificação | Resultado |
|---|---|
| `DISABLE_WP_CRON` / `ALTERNATE_WP_CRON` | **não definidos** |
| Eventos agendados | **38** |
| **Eventos atrasados** | **0** |
| Trava `doing_cron` presa | não |

**Nenhum trabalho represado.** E, respondendo diretamente: **Offload, Yoast e Smush usam WP-Cron,
não Action Scheduler** —

| Plugin | Evento no WP-Cron |
|---|---|
| WP Offload Media (nosso mu-plugin) | `bahia_offload_reconcile` |
| Yoast SEO | `wpseo_indexable_index_batch` |
| WP Smush | `wp_smush_daily_cron` |
| AdRotate | `adrotate_evaluate_ads`, `adrotate_empty_trackerdata`, `adrotate_auto_delete`, `adrotate_empty_trash`, `adrotate_notification` |
| Nossos | `bahia_mais_lidas_24h_refresh` |

**Conclusão: não há incidente aberto.** O que havia era uma biblioteca desinstalada deixando
tabelas para trás — que é a mesma história do Rank Math, contada de outro jeito.

### T2.6 O plano mudou: `DROP TABLE`, não `DELETE` em lotes

Você aprovou "limpar em lotes", e o plano em lotes estava certo **para a hipótese de que o Action
Scheduler estivesse vivo**. Ele não está. Com isso, o `DELETE` em lotes vira o caminho pior:

| | `DELETE` em lotes + `OPTIMIZE` | **`DROP TABLE`** |
|---|---|---|
| Passos | ~1.130 lotes, tabela auxiliar, reconstrução | **4 comandos** |
| **Espaço temporário exigido** | **~1,8 GiB** para reconstruir | **nenhum** |
| Binlog gerado | ~5,66 milhões de eventos de linha | ~nada |
| Devolve espaço ao disco | só depois do `OPTIMIZE` | **na hora** |
| Tempo | 15-25 min, com risco de encher o disco | segundos |
| Rollback | o mesmo dump | o mesmo dump |

**Com 2 GiB livres, o plano em lotes é o arriscado.** E não há nada a preservar: são tabelas de
uma biblioteca que não está instalada, sem chave estrangeira apontando para elas e sem uma linha
de código nosso que as mencione. Se algum dia um plugin trouxer o Action Scheduler de volta, ele
**recria o próprio esquema na ativação** — é assim que a biblioteca funciona.

**Registro a favor do plano em lotes, para constar:** ele preservaria as 9.535 ações completas,
as 3 falhadas e as 2 pendentes, que são histórico de execução. Como o executor não existe, esse
histórico não alimenta nada além de si mesmo — e o dump o guarda.

### T2.7 A tarefa

**1. Dump próprio das 4 tabelas — é o rollback:**

```bash
STAMP=$(date "+%Y%m%d-%H%M")
OUT=~/BAHIABA-backups/dump-actionscheduler-$STAMP.sql.gz
mysqldump --single-transaction --no-tablespaces -h "$DBH" -u "$DBU" -p"$DBP" prod \
  wp_actionscheduler_actions wp_actionscheduler_logs \
  wp_actionscheduler_claims wp_actionscheduler_groups | gzip > "$OUT"
chmod 444 "$OUT"; sha256sum "$OUT" > ~/BAHIABA-backups/sha256-$(basename "$OUT").txt
gzip -t "$OUT" && echo "gzip integro"
zcat "$OUT" | grep -c '^CREATE TABLE'    # portão: esperado 4
```

Estimativa: ~2,3 GB brutos, **~250-350 MB comprimidos**.

**2. Números de "antes":**

```sql
SELECT status, COUNT(*) FROM wp_actionscheduler_actions GROUP BY status;  -- 1.894.997
SELECT COUNT(*) FROM wp_actionscheduler_logs;                             -- 3.799.533
SELECT TABLE_NAME, ROUND((DATA_LENGTH+INDEX_LENGTH)/1024/1024,1) AS mb
  FROM information_schema.TABLES
 WHERE TABLE_SCHEMA='prod' AND TABLE_NAME LIKE 'wp_actionscheduler%';     -- 1793,7 | 562,0 | ~0 | ~0
```

**3. A remoção:**

```sql
DROP TABLE wp_actionscheduler_logs;
DROP TABLE wp_actionscheduler_actions;
DROP TABLE wp_actionscheduler_claims;
DROP TABLE wp_actionscheduler_groups;
```

**4. Portão de saída:**

```sql
SELECT TABLE_NAME FROM information_schema.TABLES
 WHERE TABLE_SCHEMA='prod' AND TABLE_NAME LIKE 'wp_actionscheduler%';   -- esperado: 0 linhas
SELECT COUNT(*) FROM wp_options WHERE option_name LIKE 'schema-Action%'; -- 2 (inertes, deixar)
```

E o `FreeStorageSpace` no CloudWatch deve subir **~2,36 GB**.

**Efeito colateral bom:** as **1.885.398 linhas com data zero** do banco estavam todas aqui.
Depois de T2 sobram **zero**, e o aviso de datas zero some do `PrePatchCompatibility.log`.

---

## T3 — Remoção do schema `portal-noticias.newttech.com.br` ✅ CONCLUÍDO

> ### EXECUTADO EM 27/08/2026, 14h20 WEST (13h20 UTC)
>
> | | |
> |---|---|
> | Backup | `~/BAHIABA-backups/dump-SCHEMA-portalnoticias-20260827-1413.sql.gz` |
> | Tamanho | **348.567.771 bytes** (332 MB) · bruto 2.087.968.020 B · permissão `444` |
> | SHA-256 | `365ca8345b5a159430d178c9d9e724ccc5744658d9a954383f26630f8f921ced` |
> | Duração do dump | **144 s**, código 0, stderr vazio |
> | Duração do `DROP` | **3 s** |
> | **Espaço livre antes** | **2,976 GiB** (oscilando 2,911–3,004) |
> | **Espaço livre depois** | **6,468 GiB** |
> | **Ganho medido** | **+3,49 GiB** |
> | Site depois | **5 de 5 em 200**; matéria individual seguindo o canônico → 200 |
> | `prod` depois | **90 tabelas, 6,441 GB — intacto** |
>
> **Os quatro portões, todos verdes:**
>
> | Portão | Resultado |
> |---|---|
> | 1 — integridade | `gzip -t` OK · SHA-256 conferido · permissão `444` |
> | 2 — contagem de objetos | **80 `CREATE TABLE`** e **1 `CREATE DATABASE`** |
> | 3 — o dump terminou | rodapé `-- Dump completed on 2026-08-27 13:15:31` presente; primeira linha é SQL |
> | 4 — **o backup restaura** | restaurado num MySQL 8.0.42 descartável em **73 s**, código 0, sem erro |
>
> **O portão 4, detalhado** — é o único que prova que o arquivo serve para alguma coisa:
>
> | Verificação | Origem | Restaurado | |
> |---|---|---|---|
> | Tabelas, nome a nome | 80 | 80 | **idênticas** |
> | `wp_postmeta` | 7.584.214 | 7.584.214 | ✅ |
> | `wp_posts` | 402.374 | 402.374 | ✅ |
> | `wp_nxs_log` | 629.322 | 629.322 | ✅ |
> | `wp_as3cf_items` | 141.767 | 141.767 | ✅ |
> | `siteurl` no restaurado | — | `https://portal-noticias.newttech.com.br` | é mesmo o site certo |
>
> ### Duas notas de método, para quem repetir
>
> **1. O dump foi feito por `kubectl exec` num pod dedicado, não por `kubectl run --rm`.** Isso
> elimina por construção o defeito documentado em `RESTAURACAO-PRODUCAO-20260818.md` §4-A: o
> `kubectl run --rm` escreve `pod "..." deleted` **no stdout**, contaminando o arquivo, e o
> filtro `grep -v` que se usaria para limpar descarta 51% do dump em silêncio no macOS. Com
> `exec`, o stdout é só do `mysqldump` — conferido: a primeira linha do arquivo é
> `-- MySQL dump 10.13`.
>
> **2. As guardas rodaram imediatamente antes do `DROP`**, não na véspera: servidor
> `ip-10-1-4-202`, `prod` com 90 tabelas, **0 conexões** no schema alvo, **0 usuários escopados**,
> **0 eventos**, e o `siteurl` do schema alvo conferido como `portal-noticias`. Condição pode
> mudar entre o planejamento e a execução; a guarda vale no instante do comando.
>
> ### Efeito colateral a registrar
>
> Contar linhas no schema morto **puxa aqueles dados para o buffer pool de 11 GB**, que estava
> com 10,90 GB do conjunto quente de produção. As contagens do portão 4 foram feitas na **cópia
> local**, não na origem, justamente para não repetir isso — mas as quatro contagens de
> referência do "antes" saíram da origem e já custaram alguma evicção. **Numa próxima limpeza,
> tirar as contagens de referência do dump, não do banco vivo.**


### T3.1 O que é

| Campo | Valor |
|---|---|
| Nome do schema | **`homolog`** |
| `siteurl` | `https://portal-noticias.newttech.com.br` |
| Tabelas / tamanho | **80** / **3,119 GB** |
| Posts | 402.374 |
| **Última escrita** | **2025-12-01 00:15:23** — 9 meses parado |
| Onde existe | **nas duas instâncias** |

### T3.2 Os três testes que você pediu — todos limpos

| Teste | Consulta | Resultado |
|---|---|---|
| Usuário com grant só nesse schema | `SELECT User, Host, Db FROM mysql.db` | **Nenhum.** As 2 únicas linhas são `mysql.session`→`performance_schema` e `mysql.sys`→`sys`, internas da AWS |
| Serviço conectado | `information_schema.PROCESSLIST` | **Nenhuma conexão com `DB='homolog'`** |
| Cron / evento agendado | `information_schema.EVENTS` | **0 eventos** no schema |

Mais quatro, por precaução:

| Teste | Resultado |
|---|---|
| Views, rotinas e gatilhos no schema | 0, 0 e 0 |
| Chave estrangeira cruzando schemas | **0** |
| Referência ao schema no código | **nenhuma** |
| `WORDPRESS_DB_NAME` nos dois clusters | **`prod`** nos dois |

Ressalva honesta: `rootbahiaba` tem `GRANT … ON *.*`, então **pode** acessá-lo — como pode
qualquer schema. O que se verificou, e é o que você pediu, é que **nenhum usuário está escopado
nele** e ninguém o usa.

**Precedente:** a pré-verificação de 2025 cita um schema `dev` naquela mesma instância, que **hoje
não existe**. Já se removeu schema ali antes.

### T3.3 O dump, com três portões

É dado de outro site e ninguém confere o que tem lá desde 30/11/2025 — o rigor é maior.

```bash
STAMP=$(date "+%Y%m%d-%H%M")
OUT=~/BAHIABA-backups/dump-SCHEMA-portalnoticias-$STAMP.sql.gz
mysqldump --single-transaction --no-tablespaces --routines --events --triggers \
  -h "$DBH" -u "$DBU" -p"$DBP" --databases homolog | gzip > "$OUT"
chmod 444 "$OUT"; sha256sum "$OUT" > ~/BAHIABA-backups/sha256-$(basename "$OUT").txt
```

`--databases` preserva `CREATE DATABASE` + `USE`: o dump restaura sozinho.
Tamanho esperado: o schema `prod`, de 6,44 GB, comprime para 547 MB; proporcionalmente **~265 MB**.

| Portão | Como | Esperado |
|---|---|---|
| 1. Integridade do arquivo | `gzip -t` e `shasum -a 256 -c` | sem erro |
| 2. Contagem de tabelas | `zcat "$OUT" \| grep -c '^CREATE TABLE'` | **80** |
| 3. **Contagem de linhas, lida do arquivo** | restaurar num MySQL local descartável e contar | bate com o banco |

**O portão 3 não é excesso.** Um dump que passa no `gzip -t` e tem 80 `CREATE TABLE` ainda pode
estar truncado no meio de um `INSERT`. É o único teste que prova que o backup **restaura**, e
custa uma hora de máquina local. Comparar as 4 maiores: `wp_postmeta` (6.819.943),
`wp_posts` (341.055), `wp_nxs_log` (592.296), `wp_as3cf_items` (127.926).

### T3.4 A remoção e o ganho

```sql
DROP DATABASE homolog;
```

Uma linha, **nas duas instâncias**, cada uma com o seu dump. Ao contrário do `DELETE`, o `DROP`
**devolve o espaço na hora**: com `innodb_file_per_table = 1` os 80 arquivos `.ibd` somem do
sistema de arquivos.

**Ganho: 3,12 GB por instância.** Esperar um pico curto de E/S ao remover 80 tablespaces de uma
vez — fazer em horário de baixo tráfego.

```sql
-- portão de saída
SELECT SCHEMA_NAME FROM information_schema.SCHEMATA;   -- 'homolog' fora da lista
```
E `FreeStorageSpace` sobe ~3,1 GB.

### T3.5 Rollback

```bash
zcat dump-SCHEMA-portalnoticias-$STAMP.sql.gz | mysql -h "$DBH" -u "$DBU" -p"$DBP"
```

Sem `USE` prévio e sem criar banco à mão — o `--databases` cuida.

---

## T4 — As 5 tabelas do Rank Math ✅ CONCLUÍDO

> ### EXECUTADO EM 27/08/2026, 14h41 WEST (13h41 UTC)
>
> | | |
> |---|---|
> | Backup | `~/BAHIABA-backups/dump-rankmath-20260827-1439.sql.gz` |
> | Tamanho | **48.168.438 bytes** (46 MB) · bruto 212.430.222 B · `444` |
> | SHA-256 | `064ac4cc72ec27560b51ae3019db0684967718bfff8e56aa08174348f8771427` |
> | Duração do dump | **12 s** · do `DROP` | **3 s** |
> | **Espaço livre antes** | **8,848 GiB** |
> | **Espaço livre depois** | **9,430 GiB** |
> | **Ganho medido** | **+0,58 GiB** (bate com os 582,1 MiB de metadado) |
> | `prod` antes → depois | **86 tabelas / 4,141 GB → 81 tabelas / 3,589 GB** |
> | Site depois | **5 de 5 em 200**, mais matéria e `sitemap_index.xml` (7,5 s) |
> | Log dos 4 pods | **0 menções** a `rank_math`, `doesn't exist`, 1146 ou `Fatal` |
>
> **Portões 1 a 3:** `gzip -t` OK · SHA-256 · **5 `CREATE TABLE`**, as cinco esperadas ·
> rodapé `-- Dump completed on 2026-08-27 13:39:21` · primeira linha é SQL.
>
> ### Portão 4 — aqui o gate teve de ser diferente, e vale explicar
>
> No T2 havia **contagem exata** medida antes. Aqui não havia, e a regra nova proíbe contar no
> banco vivo. A solução foi cruzar três coisas, nenhuma delas circular:
>
> | Tabela | Estimativa viva (`TABLE_ROWS`, metadado) | Restaurado (exato) | dif | `AUTO_INCREMENT` vivo | `MAX(pk)+1` restaurado |
> |---|---|---|---|---|---|
> | `wp_rank_math_analytics_gsc` | 949.366 | **979.393** | +3% | 1.106.163 | **1.106.163** ✅ |
> | `wp_rank_math_analytics_objects` | 245.366 | **248.369** | +1% | 248.374 | **248.374** ✅ |
> | `wp_rank_math_analytics_inspections` | 4.492 | **4.585** | +2% | 4.586 | **4.586** ✅ |
> | `wp_rank_math_internal_links` | 1.872 | **1.872** | 0% | 4.090 | **4.090** ✅ |
> | `wp_rank_math_internal_meta` | 4.451 | **4.451** | 0% | — (PK composta) | — |
>
> **A verificação de cauda é o que carrega o portão.** `TABLE_ROWS` é estimativa do InnoDB e
> diverge alguns por cento por natureza — não serve de prova. Mas o `AUTO_INCREMENT` vivo é
> metadado exato e **grátis**, e `MAX(pk)+1` no restaurado bateu com ele **nas quatro tabelas que
> o têm**. Um dump truncado teria a cauda faltando, e é exatamente aí que essa comparação olha.
>
> Amostra do conteúdo conferida: consultas do Search Console (`último horário do ferry boat`)
> ligadas a URLs do site. Os acentos apareceram corrompidos na primeira leitura — **era o charset
> do terminal, não o dado**: `HEX(LEFT(query,3))` devolveu `C3BA`, que é `ú` em UTF-8 correto.
>
> ### O que ficou de propósito
>
> **36 opções `rank_math*` em `wp_options`** (38 pelo padrão mais largo, que pega `rankmath` sem
> sublinhado). São inertes. Apagar opção de plugin é tarefa de outra natureza — pode haver código
> lendo, e o §16.5 do `HANDOVER.md` é sobre exatamente esse tipo de suposição.


Aprovado por você em 27/08: **mesmo tratamento do schema — dump próprio antes, guardado à parte.**

| Tabela | Linhas | Tamanho | Última escrita |
|---|---|---|---|
| `wp_rank_math_analytics_gsc` | 949.366 | **458,4 MB** | 2026-05-09 |
| `wp_rank_math_analytics_objects` | 245.366 | 101,4 MB | 2026-06-02 |
| `wp_rank_math_analytics_inspections` | 4.492 | 4,6 MB | 2026-05-10 |
| `wp_rank_math_internal_links` | 1.872 | 0,5 MB | 2026-06-02 |
| `wp_rank_math_internal_meta` | 4.451 | 0,2 MB | 2026-06-02 |
| **Total** | | **565 MB** | |

Mesmo dono, mesma data de morte, mesma justificativa: **o plugin saiu do site em 02/06/2026** e
`active_plugins` não o contém.

```bash
# dump próprio, à parte
mysqldump --single-transaction --no-tablespaces -h "$DBH" -u "$DBU" -p"$DBP" prod \
  wp_rank_math_analytics_gsc wp_rank_math_analytics_objects \
  wp_rank_math_analytics_inspections wp_rank_math_internal_links \
  wp_rank_math_internal_meta | gzip > ~/BAHIABA-backups/dump-rankmath-$STAMP.sql.gz
# portão: 5 CREATE TABLE
```

```sql
DROP TABLE wp_rank_math_analytics_gsc;
DROP TABLE wp_rank_math_analytics_objects;
DROP TABLE wp_rank_math_analytics_inspections;
DROP TABLE wp_rank_math_internal_links;
DROP TABLE wp_rank_math_internal_meta;
```

**Ficam as 38 opções `rank_math*` em `wp_options`** — são bytes, são inertes, e apagá-las é uma
tarefa de outra natureza (opção pode ser lida por código que não se conhece). **Não incluídas.**

---

## T-ordem — a sequência, agora ditada pelo disco

Você aprovou T1 → T3 → T2. A medição de armazenamento **confirma essa ordem e acrescenta T0 na
frente** — porque com 2 GiB livres, o que devolve espaço tem de vir antes do que consome.

| Ordem | Tarefa | Ganho | Espaço temporário | Livre depois |
|---|---|---|---|---|
| ✅ | **T1** ConfigMap → DNS | — | — | **CONCLUÍDO em 27/08** |
| ✅ | **T3** `DROP DATABASE homolog` | **+3,49 GiB medidos** | nenhum | **CONCLUÍDO em 27/08** |
| ✅ | **T2** 4 tabelas do Action Scheduler | **+2,44 GiB medidos** | nenhum | **CONCLUÍDO em 27/08** |
| ✅ | **T4** 5 tabelas do Rank Math | 0,55 GB de dado | nenhum | **CONCLUÍDO em 27/08** |
| ⏸ | **T0** `OPTIMIZE wp_adrotate_tracker` | +2,0 GB | ~5 MB | **adiado para o vale**: reescreve a tabela e bloqueia escrita nela |
| 3 | **T3** `DROP DATABASE homolog` | **+3,12 GB** | nenhum | **7,2 GiB** |
| 4 | **T2** apagar as 4 tabelas do Action Scheduler | **+2,36 GB** | nenhum | **9,6 GiB** |
| 5 | **T4** apagar as 5 tabelas do Rank Math | **+0,55 GB** | nenhum | **~10,2 GiB** |
| 6 | **Snapshot manual** → instância de teste | — | — | — |

**Nenhuma das quatro limpezas precisa de espaço temporário além dos 5 MB de T0.** Foi por isso
que o plano de T2 mudou.

---

## T-autoscaling — avaliação pedida: vale ligar? **Vale, com teto.**

**Avaliado em 27/08/2026, depois das três limpezas. Não executado.**

### O que a medição mostra

Espaço livre em produção, média semanal dos últimos 90 dias:

| Data | Livre |
|---|---|
| 2026-05-29 | 3,49 GiB |
| 2026-06-26 | 3,17 GiB |
| 2026-07-31 | 3,07 GiB |
| 2026-08-21 | **3,00 GiB** |

**Queda de 0,49 GiB em 84 dias = ~0,175 GiB por mês**, monotônica e sem ninguém olhando. Não é
um pico: é consumo de rotina, e é o que se espera de um portal que publica todo dia.

### Onde isso ia dar

O autoscaling do RDS dispara quando o livre fica **abaixo de 10% do alocado** por 5 minutos
seguidos. Com 20 GiB alocados, o gatilho é **2 GiB**.

| Cenário | Conta | Quando |
|---|---|---|
| **Antes de hoje**, com autoscaling ligado | 3,00 → 2,00 GiB a 0,175/mês | dispararia por volta de **início de 2027** |
| **Antes de hoje**, sem autoscaling | 3,00 → 0 GiB | **storage-full por volta do início de 2028** |
| **Depois de hoje** | ~9,4 → 0 GiB | **~4,5 anos** |

As limpezas compraram quatro anos. **Mas não mudaram a inclinação da curva** — só o ponto de
partida. E o modo de falha continua o mesmo.

### O modo de falha é o de sempre neste projeto

`storage-full` no RDS **não degrada: para**. A instância deixa de aceitar escrita. Para o portal
isso é: repórter publica e a matéria não grava; contador de views falha; qualquer consulta que
precise de tabela temporária falha. E chega sem aviso gradual — o site funciona normalmente até
o minuto em que não funciona.

É exatamente a família de falha que o T1 existe para evitar, e que a pré-verificação da AWS
também vigia: o item 7 do `PrePatchCompatibility.log` é literalmente
*"DB instance must have enough free disk space"*, e **um item reprovado cancela a subida do
MySQL**. Ficar sem disco não atrasa só o site — atrasa este projeto.

### O que custa

**Zero até disparar.** Quando dispara, o RDS acrescenta `max(10 GiB, 10% do atual)`. Em gp2, a
US$ 0,115/GiB-mês, 10 GiB a mais são **US$ 1,15/mês**.

### O que se perde ao ligar

**Armazenamento alocado nunca diminui.** Uma vez em 30 GiB, paga-se 30 GiB para sempre — a menos
que a instância seja reconstruída. Por isso o teto importa: sem `--max-allocated-storage`
sensato, um plugin que registre demais (foi **exatamente** o que o Rank Math fez, 2,9 GB em dois
meses) empurra o alocado para cima e a conta fica.

> **Nota de oportunidade:** o Blue/Green da Fase 3 **é** uma reconstrução de instância. Se algum
> dia o alocado subir demais, aquela é a hora de baixá-lo, via `--target-allocated-storage`.

### Recomendação

**Ligar, com teto de 40 GiB:**

```bash
aws rds modify-db-instance --db-instance-identifier rds-bahiaba-2023 \
  --max-allocated-storage 40 --apply-immediately --region us-east-1
```

Três razões, em ordem de peso:

1. **Troca "o site para de publicar" por "a conta sobe US$ 1,15/mês".** É a troca mais barata
   deste documento inteiro.
2. **Não tem downtime nem reinício** — é ajuste de metadado da instância. Pode ser feito a
   qualquer hora, inclusive em pico.
3. **O teto de 40 GiB** é o dobro do atual e mantém o piso de 20 GiB para o verde do Blue/Green,
   sem abrir espaço para crescimento silencioso indefinido.

**Fazer o mesmo em `rds-bahiaba-hml`**, que tem o mesmo problema e a mesma recomendação ativa da
AWS.

**Não é urgente**, e é por isso que fica como recomendação e não como tarefa: depois das
limpezas há 4,5 anos de folga. Mas é uma linha, não custa nada hoje, e remove uma classe inteira
de incidente.

---

# FASE 1 — Instância de teste — **ROTEIRO PARA APROVAÇÃO (rev. 4)**

**Nada foi criado.** Todos os identificadores abaixo foram lidos da conta em 27/08/2026.
Revisão 4 corrige três pontos da revisão 3 com dado autoritativo da própria AWS.

## 1.0 O que mudou desde a revisão 3

| # | Ponto | Revisão 3 dizia | **Medido agora** |
|---|---|---|---|
| 1 | `mysql_native_password` no 8.4 | "há um parâmetro, é a rede de segurança" | **Já vem `ON` por padrão e é `IsModifiable: false`.** Não há o que configurar, e não há como desligar |
| 2 | Storage initialization | "ligar" | **Não é interruptor.** Não existe opção na API; é processo automático, e só se *observa* por `StorageOperationStatus` |
| 3 | Snapshot de origem | "o automático mais recente" | O mais recente é de **04:11 UTC de hoje — anterior às limpezas**. Restaurá-lo testa um banco que já não existe |

## 1.1 Snapshot de origem — **há uma decisão aqui**

O automático mais recente é `rds:rds-bahiaba-2023-2026-08-27-04-10` (27/08 04:11 UTC, 20 GiB,
8.0.42). **Ele é anterior às limpezas de hoje**, que rodaram entre 13:20 e 13:41 UTC. Restaurá-lo
produz uma instância de teste com **9,56 GB** de dado — o banco de ontem — quando o que vai subir
na janela tem **3,589 GB**.

| Opção | Prós | Contras |
|---|---|---|
| **A. Esperar o automático de amanhã, 04:10 UTC** | pós-limpeza, **custo zero**, e **nenhum evento de E/S novo** — esse snapshot já acontece todo dia | atrasa o teste em ~14 h |
| **B. Snapshot manual agora** | testa hoje | numa instância **Single-AZ**, o snapshot pode causar **breve suspensão de E/S**. É o mesmo evento que já ocorre às 04:10, mas seria um a mais, e você pediu "sem indisponibilidade" |
| C. Usar o de hoje 04:11 | imediato e sem evento novo | **testa o banco errado** — 2,7× maior, com tabelas que já não existem |

**Recomendo A.** O ganho de testar o banco certo é grande, o custo é esperar uma noite, e a
janela ainda não tem data. **C está descartado**: um `PrePatchCompatibility.log` gerado sobre
tabelas que já foram apagadas não responde a pergunta que queremos fazer.

> # 🔴 NÃO use `bahia-mysql84` no homolog. Ele derruba a `t3.micro`.
>
> **Se você chegou aqui pensando "vamos usar o mesmo parameter group nos dois ambientes, por
> consistência" — pare e leia.** É a conclusão certa em quase todo projeto e é **errada neste**.
>
> `bahia-mysql84` fixa **`innodb_buffer_pool_instances = 8`**. O MySQL exige que
> `innodb_buffer_pool_size` seja múltiplo de `chunk_size × instances`, e quando não é,
> **ele aumenta o buffer pool sozinho** para satisfazer a conta.
>
> | | Produção | **Homolog** |
> |---|---|---|
> | Classe | db.m5.xlarge | **db.t3.micro** |
> | RAM | 16 GiB | **1 GiB** |
> | `innodb_buffer_pool_size` | 11,25 GiB | **256 MB** |
> | `innodb_buffer_pool_chunk_size` | 128 MB | 128 MB |
> | **Mínimo forçado por `instances=8`** | 1 GiB — irrelevante | **1 GiB — a RAM inteira** |
>
> **O parâmetro existe para proteger a produção sob concorrência alta, e é exatamente ele que
> põe um buffer pool de 1 GiB numa máquina de 1 GiB.**
>
> **A regra:** `bahia-mysql84` é **só de produção** — a instância de teste e o verde do
> Blue/Green. **Homolog fica em `default.mysql8.4`**, que é a continuação natural do
> `default.mysql8.0` que ele sempre usou. Foi assim que a Fase C subiu, em 29/08/2026.
>
> **A lição geral:** parameter group não é configuração portável entre ambientes — é
> configuração **acoplada ao tamanho da máquina**. Copiar "por consistência" transporta uma
> premissa de hardware junto, e ela pode não valer do outro lado.

## 1.2 Parameter group de destino — quatro linhas, e uma que não é preciso escrever

Família **`mysql8.4`**, nome proposto **`bahia-mysql84`**.

| Parâmetro | Valor | Por quê |
|---|---|---|
| `innodb_strict_mode` | **0** | **o único parâmetro que `mysql80-edit` altera à mão** |
| `innodb_adaptive_hash_index` | **1** | padrão do 8.4 é `OFF`; fixar mantém o comportamento de hoje |
| `innodb_change_buffering` | **all** | padrão do 8.4 é `none` |
| `innodb_io_capacity` | **200** | padrão do 8.4 é `10000` |

**`mysql_native_password` não entra, e isto corrige o que ficou combinado.** Lido da AWS:

```
mysql_native_password
    padrao=ON   tipo=static   modificavel=false   valores=OFF,ON
```

**O RDS entrega o plugin já ligado na família 8.4 e não deixa mexer.** O
`loose_mysql_native_password=ON` que a pré-verificação de 2025 sugeria é a sintaxe de linha de
comando do MySQL, não um parâmetro do RDS. **Nada a fazer — e o assunto autenticação está
fechado por construção.**

`authentication_policy` fica no padrão `*:caching_sha2_password`, que rege **usuários novos**.
Não criamos usuário nenhum, e os existentes mantêm o plugin que já têm.

`restrict_fk_on_non_standard_key` fica no padrão (`ON`): as 5 chaves estrangeiras já foram
conferidas e todas apontam para `PRIMARY`.

## 1.3 Isolamento — e dois achados de segurança que apareceram ao desenhá-lo

### O que a instância de teste terá

**Security group próprio e novo**, nome proposto `bahia-mysql84-teste`, com **uma única regra de
entrada**:

```
porta 3306/tcp  ←  10.2.0.0/16   (apenas a VPC dos pods de produção, de onde eu opero)
```

E, diferente de produção, **`--no-publicly-accessible`**.

**O que fica de fora, de propósito:** `10.1.0.0/16` (homolog), o IP público
`131.196.124.36/32`, e a referência ao `sg-0614f9d2cf0b6c697`. A instância tem dado real de
produção e não pode herdar a superfície de exposição da original.

### ⚠️ Achado 1 — o banco de produção é acessível pela internet

```
rds-bahiaba-2023   PubliclyAccessible = True   StorageEncrypted = True
```

E o security group `AcessoRestrito` (`sg-0e96076df475b4843`), que está anexado à interface de
rede do RDS, permite:

```
porta 3306/tcp  ←  131.196.124.36/32
```

**Somados, isso significa que um endereço de IP público específico consegue abrir conexão com o
banco de produção pela internet** — com o usuário mestre `rootbahiaba`, que autentica por
`mysql_native_password`.

Não é uma invasão e não há indício de uso indevido: é uma regra deliberada, de um IP só. Mas é a
superfície mais sensível que este projeto encontrou.

**De quem é o IP — levantado em 27/08, sem alterar nada:**

| Pergunta | Resposta |
|---|---|
| Reverso | `36.124.196.131.jectix.net.br` — nome **gerado automaticamente** pela operadora, não um servidor nomeado |
| Dono do bloco (`131.196.124.0/22`, LACNIC) | **JECTIX TELECOM**, CNPJ 24.029.566/0001-04 — provedor de internet brasileiro |
| Consta em `wp-content/`, `infra-bahiaba/` ou `documentacao/`? | **Não.** Nenhuma menção em nenhum dos três |
| Está em uso? | **Impossível determinar daqui.** O `general_log` está desligado e o Performance Insights também. `PROCESSLIST` mostra só os pods e o `rdsadmin` — mas isso é uma foto, não histórico |

**É um endereço de assinante de provedor, não documentado em lugar nenhum.** Pode ser o
escritório, pode ser a máquina de alguém, pode ser de quem já saiu.

**O agravante:** endereço de provedor **muda de dono**. Se a linha foi cancelada ou reatribuída,
quem estiver com ele hoje tem **a mesma permissão de rede** ao banco de produção, sem que ninguém
tenha mexido em nada. A senha continuaria protegendo; a primeira das duas barreiras, não.

**O desenho correto**, para quando houver decisão — *não implementado, porque envolve quem usa
aquele acesso*:

1. **Sem endereço público.** O banco responde só de dentro da VPC.
2. **Acesso humano por caminho controlado** — *bastion* ou **AWS Session Manager**. Registra quem
   entrou, quando e de onde; não depende de o IP da pessoa continuar o mesmo; e sai junto com o
   desligamento dela, sem caçar regra antiga de firewall.

### ⚠️ Achado 2 — o mesmo SG tem duas regras abertas para o mundo

```
porta 22/tcp    ←  0.0.0.0/0
porta 9000/tcp  ←  0.0.0.0/0
```

**Hoje são inertes**: o SG está anexado **somente** à interface do RDS, e o RDS não escuta em 22
nem em 9000 — conferido por `describe-network-interfaces`, que devolve apenas
`RDSNetworkInterface 172.31.70.197`.

**O risco é futuro, não presente:** um SG chamado "AcessoRestrito" que na verdade abre SSH e
PHP-FPM para a internet inteira é uma armadilha esperando alguém anexá-lo a uma EC2. **Não
mexi.** Vai para o `PENDENCIAS-gestores.md` junto com o achado 1, se você aprovar o texto.

## 1.4 Os comandos, na ordem

> **Estado em 29/08/2026, 03:0x UTC — passo 1 feito, passos 2 a 5 bloqueados por permissão.**
>
> | Passo | Estado |
> |---|---|
> | 1. security group `bahia-mysql84-teste` | ✅ **`sg-045aed7cf5c92b6c5`**, regra única `3306 ← 10.2.0.0/16`, etiquetado, **anexado a nada**, custo zero — passou porque é EC2, não RDS |
> | 2. parameter group `bahia-mysql84` | ❌ `AccessDenied` |
> | 3. cópia `bahia-mysql80-teste` e restauração | ❌ `AccessDenied` |
> | 4. `siteurl` na cópia | ⏸ depende do 3 |
> | 5. linha de base e subida | ⏸ depende do 3 |
>
> **As três chamadas falharam inteiras, sem estado parcial.** O snapshot de origem é o manual
> `bahia-prod-pre-upgrade-84-20260828` (29/08 02:49:33 → 02:54:36 UTC, `available` 100%, 20 GiB,
> 8.0.42, cifrado com `alias/aws/rds`) — **e não houve suspensão de E/S**: `WriteLatency` ficou
> entre 0,65 e 0,86 ms durante toda a criação, com o site em 200.
>
> Isso **substitui a decisão A × B × C do §1.1**: o Albert tirou o snapshot manual (opção B) e o
> evento temido não ocorreu.

```bash
REG="--region us-east-1"

# 1. security group dedicado, com uma regra só
aws ec2 create-security-group $REG \
  --group-name bahia-mysql84-teste \
  --description "Instancia de teste do upgrade 8.4 — acesso so dos pods de prod" \
  --vpc-id vpc-4c49202b
# (guardar o sg-xxxx devolvido)
aws ec2 authorize-security-group-ingress $REG --group-id <sg-novo> \
  --protocol tcp --port 3306 --cidr 10.2.0.0/16

# 2. parameter group da familia 8.4
aws rds create-db-parameter-group $REG \
  --db-parameter-group-name bahia-mysql84 \
  --db-parameter-group-family mysql8.4 \
  --description "MySQL 8.4 espelhando mysql80-edit, com 3 parametros de desempenho fixados"
aws rds modify-db-parameter-group $REG --db-parameter-group-name bahia-mysql84 --parameters \
  "ParameterName=innodb_strict_mode,ParameterValue=0,ApplyMethod=immediate" \
  "ParameterName=innodb_adaptive_hash_index,ParameterValue=1,ApplyMethod=immediate" \
  "ParameterName=innodb_change_buffering,ParameterValue=all,ApplyMethod=immediate" \
  "ParameterName=innodb_io_capacity,ParameterValue=200,ApplyMethod=immediate"

# 3. restaurar (parameter group 8.0 primeiro — a subida vem depois, em separado)
aws rds copy-db-parameter-group $REG \
  --source-db-parameter-group-identifier mysql80-edit \
  --target-db-parameter-group-identifier bahia-mysql80-teste \
  --target-db-parameter-group-description "Copia de mysql80-edit para a instancia de teste"

aws rds restore-db-instance-from-db-snapshot $REG \
  --db-instance-identifier rds-bahiaba-teste84 \
  --db-snapshot-identifier <o snapshot da opcao A> \
  --db-instance-class db.m5.xlarge \
  --availability-zone us-east-1c \
  --db-subnet-group-name default-vpc-4c49202b \
  --vpc-security-group-ids <sg-novo> \
  --db-parameter-group-name bahia-mysql80-teste \
  --option-group-name default:mysql-8-0 \
  --storage-type gp2 \
  --no-multi-az --no-publicly-accessible \
  --no-auto-minor-version-upgrade --no-deletion-protection \
  --tags Key=projeto,Value=upgrade-mysql84 Key=temporaria,Value=sim \
         Key=apagar-apos,Value=<data+2dias>

aws rds wait db-instance-available $REG --db-instance-identifier rds-bahiaba-teste84
aws rds modify-db-instance $REG --db-instance-identifier rds-bahiaba-teste84 \
  --backup-retention-period 1 --apply-immediately   # sem isto a AWS nao tira o snapshot pre-subida

# 4. corrigir o siteurl NA COPIA, antes de qualquer pod apontar para ela (HANDOVER 0.2.1)
#    UPDATE wp_options SET option_value='https://hml.bahia.ba'
#     WHERE option_name IN ('siteurl','home');

# 5. linha de base em 8.0 (secao 1.6), e SO ENTAO a subida para 8.4.9
```

**Copiar `mysql80-edit`, nunca apontar para ele:** um parameter group serve várias instâncias, e
mexer nele para experimentar valeria em produção.

## 1.5 Storage initialization — o que é possível, honestamente

**Não existe opção para ligar.** Conferido: nem em `restore-db-instance-from-db-snapshot`, nem em
`modify-db-instance`, nem como comando próprio. O que existe é o **estado**, reportado em
`StorageOperationStatus` (`Initializing`) e `StorageOperationPercentProgress` — a AWS documenta
o mecanismo para Blue/Green e "vários fluxos", e pode ou não reportá-lo numa restauração simples.

**O plano fica em dois níveis:**

1. **Se o campo aparecer:** portão é `StorageOperationPercentProgress = 100` antes de medir
   qualquer coisa.
2. **Se não aparecer:** o portão passa a ser empírico, e é o mesmo do §3.3 — rodar a passada de
   aquecimento (`SELECT COUNT(*)` nas tabelas grandes, que força o EBS a puxar os blocos do S3) e
   observar `ReadIOPS` cair e `BurstBalance` parar de descer. **A assinatura de "quente" está
   medida em produção: `ReadIOPS` médio de 0,28/s com `BurstBalance` parado em 99%.**

**Sem um desses dois portões, qualquer número de desempenho é lixo** — a instância fica
`available` muito antes de o volume estar carregado.

## 1.6 A tensão entre isolamento e o teste de carga — e como resolvo

Você pediu duas coisas que se empurram: **isolamento total** ("sem acesso de nada além da sua
sessão") e **`carga.sh` contra o banco 8.4**. O `carga.sh` dispara HTTP contra
`https://hml.bahia.ba` — ele precisa de um front-end web, e o único disponível seria apontar os
pods de homolog para a instância de teste, o que abriria `10.1.0.0/16` no security group.

**Proposta: uma sonda de carga em nível de SQL, e ela é melhor para esta pergunta.**

N conexões concorrentes repetindo as consultas reais — os blocos da home, o archive de editoria,
e o `MATCH ... AGAINST` nos 10 termos do `carga.sh` — medindo mediana, p90, máximo e
`Threads_running`, exatamente como o `carga.sh` faz, mas sem PHP, sem nginx e sem FastCGI cache
no meio.

| | `carga.sh` por HTTP | **sonda SQL** |
|---|---|---|
| Mede | a pilha inteira | **só o banco** |
| Ruído | PHP-FPM, cache, rede, ALB | nenhum |
| Comparar 8.0 × 8.4 | o sinal do motor fica diluído | **isola a variável que mudou** |
| Isolamento | exige abrir homolog no SG | **mantém a regra única** |

**Para comparar duas versões de MySQL, tirar PHP e cache do caminho não é limitação: é método.**
E o "antes" fica gravado no mesmo formato, rodando a sonda contra a instância de teste **ainda em
8.0**, antes da subida — comparação na mesma máquina, mesmo dado, mesmo parameter group.

**Se você quiser também o número de ponta a ponta**, existe a opção B: abrir `10.1.0.0/16` no
security group por uma janela, apontar homolog para a instância de teste, rodar o `carga.sh` de
verdade e reverter as duas coisas. **Precisa da sua palavra** — é exatamente o isolamento que
você pediu para não abrir.

## 1.7 Custo e destruição

| Item | Valor |
|---|---|
| `db.m5.xlarge`, MySQL Single-AZ, us-east-1, **sob demanda** (não há reserva na conta) | **~US$ 0,342/hora** (preço de tabela — a API de preços está negada para esta credencial) |
| Armazenamento 20 GiB gp2 | ~US$ 2,30/mês ≈ US$ 0,077/dia |
| **Total** | **~US$ 8,29 por dia** |

**Destruição:** etiqueta `apagar-apos` na criação, e remoção **assim que o portão de saída da
Fase 2 fechar** — previsto 2 dias, teto de 4. Com `--no-deletion-protection`, apagar é um
comando. **Custo total previsto: US$ 17 a 34.**

O que sobrevive à instância, de propósito: o parameter group `bahia-mysql84` (é o que vai ser
usado no verde do Blue/Green) e os logs baixados. O `bahia-mysql80-teste` e o security group
podem ir junto.

## 1.9 Limites desta fase — definidos pelo Albert em 27/08/2026

**"Fase pré-janela. A janela ainda não tem data. Tudo aqui roda com produção no ar e sem
indisponibilidade."**

### Está nesta fase

| | |
|---|---|
| Correção do `PENDENCIAS-gestores` §3 | ✅ feito |
| Commits na `develop`, **sem push** | ✅ feito — o push reconstrói homolog e troca os pods, e não vale disparar rollout por documento; vai junto com a próxima mudança que precise de deploy |
| **Instância de teste** | ⏸ **aguardando aprovação deste roteiro** |

### NÃO está nesta fase

| Item | Onde fica |
|---|---|
| **T0** (`OPTIMIZE wp_adrotate_tracker`) | **na janela**, com `lock_wait_timeout=5` — ou logo depois das 21:05 UTC, se coincidir |
| Subida do homolog | janela |
| Troca do Blue/Green em produção | janela |
| Autoscaling de disco | **o Albert faz pelo console** |
| Teste do painel | **do Albert**, quando tiver sessão |

### O portão do verde do Blue/Green

> **"Só depois que a instância de teste passar. Não crie antes. Se o teste revelar
> incompatibilidade, o verde não deve existir."**

Registrado como regra, não como preferência. O verde **não é criado** enquanto o portão de saída
da Fase 2 (§2.4) não fechar, e a criação depende de autorização explícita à parte.

Nota técnica que sustenta a regra: o verde **acompanha produção por replicação**, então pode
nascer poucas horas antes da janela sem ficar desatualizado. Não há vantagem nenhuma em criá-lo
cedo — só custo de instância e uma cópia a mais dos dados de produção existindo no mundo.

---

## 1.8 Portões antes de eu criar qualquer coisa

- [ ] Você escolhe entre **A** (esperar o automático de amanhã) e **B** (snapshot manual agora)
- [ ] Você aprova a regra única do security group, e o que fica de fora
- [ ] Você decide sobre a **sonda SQL** (recomendada) ou também o `carga.sh` HTTP com abertura
      temporária
- [ ] Você diz se quer os dois achados de segurança do §1.3 no `PENDENCIAS-gestores.md`

# FASE 2 — RESULTADO REAL (29/08/2026) ✅ APROVADA

> **A instância de teste subiu de 8.0.42 para 8.4.9 e passou em todos os portões que a
> isolamento permite verificar.** `rds-bahiaba-teste84`, `db.m5.xlarge`, dados de produção do
> snapshot `bahia-prod-pre-upgrade-84-20260828`.

## Cronologia, medida

| Hora (UTC) | Evento |
|---|---|
| 03:21:56 | restauração disparada |
| 03:29:45 | **disponível — 7 min 49 s** |
| 03:38:14 | `modify-db-instance --engine-version 8.4.9` |
| 03:38:21 → 03:38:42 | **pré-verificação: 21 s, aprovada** (sem evento de cancelamento) |
| **03:39:00** | **último `SELECT` que respondeu** |
| 03:40:09 | `The engine version upgrade started` |
| **03:41:01** | **primeiro `SELECT` que voltou** |
| 03:42:12 | `The engine version upgrade finished` |
| 03:45:57 | a API diz `available` |

**INDISPONIBILIDADE REAL: 121 s.** Queda única e contígua, 120 amostras de falha seguidas, todas
`c:2002` (conexão recusada) — **nenhum erro de consulta**.

> ### ⚠️ A API mente sobre a indisponibilidade, e mente para mais
>
> O banco voltou a responder às **03:41:01**. A API só disse `available` às **03:45:57** —
> **4 min 56 s depois**. Quem cronometrar pelo console reporta ~7,5 min de queda onde houve 2.
>
> É o §16 do HANDOVER outra vez: o instrumento óbvio responde a coisa errada. **Medir de segundo
> em segundo, de fora, com reconexão a cada tentativa** — foi o que o §2.2 mandou e é o que dá o
> número certo. Vale para a Fase D.

## `PrePatchCompatibility.log` — previsto contra real

```
Errors: 0     Warnings: 2     Database Objects Affected: 32
```

| Item | Previsto no §0.10? | Real |
|---|---|---|
| `prod.wp_bwg_theme` — Row size too large | **sim** (Anexo C item 5) | idêntico, pré-existente desde 2025 |
| `mysql_native_password` em `rootbahiaba` e `rds_superuser_role` | **sim** | idêntico — **e funciona no 8.4.9** |
| **`SET_USER_ID` removido de 3 usuários** | **NÃO** | ver abaixo |

### O único item não previsto — e por que não é portão

O log lista, no item 8, que `rootbahiaba`, `rds_superuser_role` e `rdsadmin` perdem o privilégio
`SET_USER_ID` (substituído por `SET_ANY_DEFINER` no 8.4).

**Alcance prático: nenhum.** `SET_USER_ID` só é exigido para criar objeto com `DEFINER` de outro
usuário, e o §0.1 já contou **0 rotinas, 0 gatilhos, 0 views** nos dois schemas. Não há o que
quebrar. Fica registrado por honestidade de método — **o portão diz "incompatibilidade não
prevista → PARE"**, e a decisão de não parar está justificada aqui, não escondida.

## Validação — o que passou

| Camada | Resultado |
|---|---|
| **2 — a BUSCA (teste principal)** | ✅ **idêntica termo a termo contra produção em 8.0.42** |
| **4 — `CHECK TABLE`** | ✅ 6 de 6 `OK` |
| **`EXPLAIN`** | ✅ planos idênticos |
| **Log de erro do RDS** | ✅ sem erro |
| **Parameter group** | ✅ exatamente 4 parâmetros de usuário |

### Camada 2 — a busca, contra produção

Índice íntegro nas duas: `PRIMARY`, `date_idx`, `ft(post_title,post_excerpt)`. **258.938 linhas
dos dois lados.** Os 10 termos do `carga.sh`, `match_total` em 8.4.9 contra 8.0.42:

| termo | 8.4.9 | 8.0.42 | termo | 8.4.9 | 8.0.42 |
|---|---|---|---|---|---|
| bahia | 14.876 | 14.876 | lula | 9.633 | 9.633 |
| salvador | 14.133 | 14.133 | chuva | 1.579 | 1.579 |
| carnaval | 2.906 | 2.906 | festa | 1.727 | 1.727 |
| eleicao | 966 | 966 | saude | 3.347 | 3.347 |
| praia | 1.066 | 1.066 | escola | 1.826 | 1.826 |

**Dez de dez, exatos.** O índice FULLTEXT atravessou a subida de versão maior sem alteração de
comportamento. Não precisa reconstruir.

### `EXPLAIN` — a única diferença é estatística

Busca, archive, autor e home: mesma ordem de tabelas, mesmos tipos de acesso, mesmas chaves,
mesmo `Extra`. A única linha que difere é a estimativa de linhas no join do `wp_postmeta`
(17 → 16) — estimativa, não plano.

## Desempenho — a sonda SQL, mesma máquina, mesmo dado

10 conexões × 50 rodadas = **11.000 medições de cada lado**, portão de contagem verde nas duas.

| classe | 8.0.42 mediana | **8.4.9 mediana** | 8.0 p90 | **8.4 p90** | 8.0 máx | **8.4 máx** |
|---|---|---|---|---|---|---|
| busca | 43,6 ms | **43,7 ms** | 207,4 ms | **207,8 ms** | 400,1 ms | **430,6 ms** |
| archive | 3,9 ms | **3,9 ms** | 5,6 ms | **5,3 ms** | 42,4 ms | **20,7 ms** |
| home | 6,1 ms | **6,2 ms** | 7,9 ms | **7,4 ms** | 33,0 ms | **14,5 ms** |
| contagem | 130,4 ms | **125,8 ms** | 168,1 ms | **162,5 ms** | 297,8 ms | **315,5 ms** |

`Threads_running`: **pico 12 nos dois**, mediana 11.

**Conclusão: empate.** Nenhuma diferença sai do ruído. O 8.4.9 não é mais rápido nem mais lento
neste conjunto de consultas, nesta classe de máquina, com estes dados.

**Honestidade sobre a comparação:** o buffer pool tinha 0,54 GiB na base e 3,12 GiB depois (o
`CHECK TABLE` carregou tabelas inteiras). O que torna a comparação válida é outra coisa:
`Innodb_buffer_pool_reads` ficou **congelado** nas passadas finais dos dois lados (35.207→35.208
e 126.757 constante), ou seja, **o conjunto que a sonda toca estava residente nas duas medições**.
O excedente é dado que a sonda não visita.

## ⚠️ Quatro parâmetros que mudaram e o grupo de 4 linhas NÃO cobre

Os 4 fixados aguentaram. Mas comparando as variáveis em execução, 8.4.9 contra a produção 8.0.42:

| Variável | Produção 8.0.42 | **Teste 8.4.9** | Coberto? |
|---|---|---|---|
| `innodb_buffer_pool_instances` | **8** | **1** | ❌ |
| `innodb_io_capacity_max` | **2000** | **400** | ❌ (deriva de `io_capacity`) |
| `temptable_use_mmap` | 1 | 0 | ❌ |
| `innodb_numa_interleave` | 0 | 1 | ❌ |
| `binlog_format` | MIXED | ROW | esperado, e a AWS prefere ROW |

**Por que isso não apareceu na medição:** a sonda usou **10 conexões**. Produção roda até
**12 workers PHP-FPM × 5 pods**. `innodb_buffer_pool_instances=1` só dói sob concorrência alta,
na disputa pelo mutex do pool — e a 10 conexões não dói.

**Não dá para testar isso agora:** a instância já é 8.4, não há mais 8.0 contra o que comparar em
concorrência alta.

**Recomendação — fixar os dois primeiros em `bahia-mysql84`**, que é o grupo do verde:

```
innodb_buffer_pool_instances = 8
innodb_io_capacity_max       = 2000
```

Custo zero, elimina duas variáveis da Fase D, e faz o verde nascer com o comportamento de
produção em vez do padrão novo. **Precisa da palavra do Albert** — muda o desenho de 4 linhas
que ele aprovou.

## O que NÃO foi validado, e por quê

| Camada | Situação |
|---|---|
| **1 — o site responde** | ❌ **impossível sob isolamento** — não há front-end apontando para a instância |
| **3 — publicar matéria pelo painel** | ❌ mesma razão |
| **5 — `carga.sh` ponta a ponta** | ❌ mesma razão; a sonda SQL o substituiu por desenho (§1.6) |

**As três dependem de um front-end, e o isolamento que você pediu as exclui por construção.**
Elas são exatamente o conteúdo da **Fase C**, onde o homolog tem painel e URL. Não são dívida
desta fase; são o objeto da próxima.

---

# FASE 2 — Subida da instância de teste e validação (roteiro original)

## 2.1 O parameter group 8.4

```bash
aws rds create-db-parameter-group \
  --db-parameter-group-name bahia-mysql84 \
  --db-parameter-group-family mysql8.4 \
  --description "MySQL 8.4 espelhando mysql80-edit, com os 3 parametros de desempenho fixados" \
  --region us-east-1

aws rds modify-db-parameter-group --db-parameter-group-name bahia-mysql84 --region us-east-1 \
  --parameters \
    "ParameterName=innodb_strict_mode,ParameterValue=0,ApplyMethod=immediate" \
    "ParameterName=innodb_adaptive_hash_index,ParameterValue=1,ApplyMethod=immediate" \
    "ParameterName=innodb_change_buffering,ParameterValue=all,ApplyMethod=immediate" \
    "ParameterName=innodb_io_capacity,ParameterValue=200,ApplyMethod=immediate"
```

Quatro linhas, e cada uma tem razão:

| Parâmetro | Valor | Por quê |
|---|---|---|
| `innodb_strict_mode` | `0` | **o único parâmetro herdado** de `mysql80-edit` |
| `innodb_adaptive_hash_index` | `1` | padrão do 8.4 seria `OFF` |
| `innodb_change_buffering` | `all` | padrão do 8.4 seria `none` |
| `innodb_io_capacity` | `200` | padrão do 8.4 seria `10000` |

**Conferência que fecha o item:** comparar o grupo novo contra os padrões da família e anexar a
diferença ao documento — é o registro de que nada foi esquecido e nada entrou por engano.

```bash
aws rds describe-db-parameters --db-parameter-group-name bahia-mysql84 --region us-east-1 \
  --query "Parameters[?Source=='user'].[ParameterName,ParameterValue]" --output text
# esperado: exatamente as 4 linhas acima
```

## 2.2 A subida

```bash
aws rds modify-db-instance \
  --db-instance-identifier rds-bahiaba-teste84 \
  --engine-version 8.4.9 \
  --db-parameter-group-name bahia-mysql84 \
  --allow-major-version-upgrade \
  --apply-immediately \
  --region us-east-1
```

O que a AWS garante:

- **"MySQL major version upgrades typically complete in about 10 minutes."**
- **A pré-verificação roda antes de a instância ser parada** — *"they don't cause any downtime"*.
  Se achar incompatibilidade, **a AWS cancela a subida sozinha** e gera um evento.
- **É obrigatória:** *"These prechecks are mandatory."*
- Detalhe em **`PrePatchCompatibility.log`**, agora legível pela CLI:
  ```bash
  aws rds download-db-log-file-portion --db-instance-identifier rds-bahiaba-teste84 \
    --log-file-name PrePatchCompatibility.log --region us-east-1 --output text
  ```
- **Até dois snapshots antes** e um depois — só com retenção > 0 (§1.3).
- Se o banco não iniciar, a AWS **reverte sozinha para 8.0** (evento **RDS-EVENT-0188**), com o
  detalhe em `upgradeFailure.log`.
- **Concluída, não há como voltar.**
- A AWS **esvazia `slow_log` e `general_log`**.

**Medir a indisponibilidade de segundo em segundo**, do último `SELECT` que respondeu ao primeiro
que voltou — não pelo relógio do console.

## 2.3 Validação — cinco camadas

**Camada 1 — o site responde.** Uma URL de cada classe: home, os 10 archives, matéria, página de
autor, tag, data, 404, `/sitemap_index.xml`, `/feed/`, `wp-admin`. **Portão de contagem: quantas
entraram, quantas devolveram 200.**

**Camada 2 — a BUSCA, teste principal.**

1. `SHOW INDEX FROM wp_bahia_search_idx` — `PRIMARY`, `date_idx`, `ft`; e `COUNT(*)` contra o
   "antes".
2. **`MATCH` com o mesmo número, termo a termo**, nos 10 termos do `carga.sh`
   (`bahia`, `salvador`, `carnaval`, `eleicao`, `praia`, `lula`, `chuva`, `festa`, `saude`,
   `escola`). **Igual = índice íntegro. Diferente = reconstruir** (§0.5).
3. `/?s=<termo>` pela web, com tempo anotado — é o caminho que já derrubou produção.
4. **`EXPLAIN` antes e depois.** Mudança de plano é o risco mais plausível da operação.

**Camada 3 — publicar matéria de teste** pelo painel, com subtítulo (ACF), imagem de destaque
(campo ACF `imagem`, não `_thumbnail_id`) e coautoria (Co-Authors Plus). Depois: abrir a matéria,
**abrir a página de autor de cada coautor**, e confirmar que entrou na `wp_bahia_search_idx`.

**Camada 4 — `CHECK TABLE`**, uma a uma, com o tempo de cada: `wp_posts`, `wp_postmeta`,
`wp_bahia_search_idx`, `wp_term_relationships`, `wp_yoast_indexable`, `wp_as3cf_items`.
(A `wp_actionscheduler_actions` saiu da lista — T2 a removeu.)

**Camada 5 — carga, antes e depois**, com o `carga.sh` consertado. Comparar mediana, p90, máximo,
códigos HTTP e **pico de `Threads_running` com no mínimo 10 amostras** — o portão que o próprio
script agora impõe.

**É aqui que o desenho aprovado paga:** numa `db.m5.xlarge` com o parameter group de produção e
os dados de produção, esta camada **mede desempenho**.

## 2.4 Portão de saída da Fase 2

- [ ] `PrePatchCompatibility.log` lido e comparado com a previsão do §0.10
- [ ] tempo real de indisponibilidade, de segundo em segundo
- [ ] avisos do log de erro do RDS na janela
- [ ] contagem do `MATCH` idêntica nos 10 termos
- [ ] `EXPLAIN` comparado: busca, archive, página de autor
- [ ] matéria de teste com subtítulo, imagem e coautoria, visível na busca
- [ ] `CHECK TABLE` sem erro nas 6 tabelas
- [ ] `carga.sh` comparado, com "antes" e "depois" **ambos com portão de contagem verde**
- [ ] parameter group 8.4 com exatamente 4 parâmetros de usuário

## 2.5 O que continua sem ser validado

- **A troca do Blue/Green em si** — a instância de teste sobe no lugar. A troca só se ensaia na
  janela; é por isso que o §3.3 existe.
- **Tráfego real.** `carga.sh` são 30 requisições de URLs frias.
- **O tempo de subida em produção.** Mesma classe e mesmo tamanho tornam o número um bom
  indicador — indicador, não garantia.

---

# FASE C — HOMOLOG EM 8.4.9 (29/08/2026) ✅ APROVADA

`rds-bahiaba-hml`, `db.t3.micro`, **8.0.45 → 8.4.9**, no lugar, sem Blue/Green.

## ⚠️ A decisão de parameter group — e por que NÃO foi o grupo do verde

**Homolog foi para `default.mysql8.4`, não para `bahia-mysql84`.** A tentação era usar o mesmo
grupo, para exercitar exatamente o que a produção vai usar. **Seria um erro grave.**

| | Produção | **Homolog** |
|---|---|---|
| `innodb_buffer_pool_size` | 11,25 GiB | **256 MB** |
| `innodb_buffer_pool_chunk_size` | 128 MB | **128 MB** |
| RAM da instância | 16 GiB (m5.xlarge) | **1 GiB (t3.micro)** |

O MySQL exige que `buffer_pool_size` seja múltiplo de `chunk_size × buffer_pool_instances`.
Com o `innodb_buffer_pool_instances=8` que acabamos de fixar em `bahia-mysql84`, o mínimo vira
**128 MB × 8 = 1 GiB** — e o MySQL **aumenta o `buffer_pool_size` sozinho** para chegar lá.

**Isso poria um buffer pool de 1 GiB numa máquina de 1 GiB de RAM.** O parâmetro que protege a
produção derruba o homolog. Homolog fica em `default.mysql8.4`, que é a continuação natural do
`default.mysql8.0` que ele já usava.

## Cronologia

| Hora (UTC) | Evento |
|---|---|
| 04:08:31 → 04:11:23 | snapshot `bahia-hml-pre-upgrade-84-20260829` — `available`, 100%, 8.0.45 |
| 04:12:22 | subida disparada |
| 04:12:31 → 04:12:52 | **pré-verificação: 21 s, aprovada** |
| **04:13:11** | **último `SELECT` que respondeu** |
| **04:15:39** | **primeiro `SELECT` que voltou** |
| 04:20:09 | a API diz `available` |

**INDISPONIBILIDADE REAL: 148 s.** Queda única, 147 falhas contíguas, todas `c:2002`.

**O §16.8 confirmou-se pela segunda vez:** a API demorou **4 min 30 s** a mais que o banco.
Duas instâncias, duas classes, dois tamanhos — e o mesmo desvio nas duas. **Não é acaso da
primeira medição.**

## `PrePatchCompatibility.log`

```
Errors: 0     Warnings: 2     Database Objects Affected: 31
```

**Uma diferença curiosa contra a cópia de produção:** o item 2 (`check table x for upgrade`) diz
**"No issues found"** em homolog, enquanto na cópia de produção acusou
`prod.wp_bwg_theme - Row size too large`. **A tabela existe nos dois.** As duas cópias divergem —
mais uma instância do §16.6 do HANDOVER: *homologação não é evidência sobre produção*.

## Integridade — o portão que importa

Fotografia completa antes e depois (`scratchpad/estado-banco.php`), comparada linha a linha:

| | |
|---|---|
| Tabelas | **92 antes, 92 depois** |
| Tabelas que sumiram | **nenhuma** |
| Tabelas que **diminuíram** | **NENHUMA** |
| Total de linhas | 18.323.235 → 18.323.261 (**+26**) |
| `wp_posts` | **435.766, idêntico** |
| `MAX(wp_posts.ID)` e `MAX(wp_bahia_search_idx.ID)` | **idênticos** |
| Índices FULLTEXT | **idênticos** (os 2 do homolog) |
| Usuários e plugin de autenticação | **idênticos** |

As +26 linhas são `wp_yoast_indexable` (+12), a hierarquia dele (+12), `wp_postmeta` (+2) e
transients — **o Yoast reindexando depois que o site voltou**. Crescimento, não perda.

## As três camadas que a Fase A não alcançava

**Camada 1 — o site responde: 16 de 16.**

Home, as 10 editorias, 2 buscas, `wp-admin` (302) e o 404. **Duas respostas exigiram
investigação, e nenhuma é da subida:**

| URL | Homolog 8.4.9 | Produção 8.0.42 | Veredito |
|---|---|---|---|
| `/feed/` | **410** | **410** | deliberado, `mu-plugins/bahia-feeds.php`, decidido em 18/08 |
| `/sitemap_index.xml` | **504** (60 s) | **200** (7,7 s) | pré-existente, já avaliado e aceito — é a `t3.micro` |

**Camada 2 — a busca funciona**, e devolve resultado, não só código 200: `salvador`, `carnaval`,
`bahia` e `eleicao` retornaram **8 cards cada**.

**Camada 3 — matéria de teste, pela pilha completa do WordPress:**

| Item | Resultado |
|---|---|
| `wp_insert_post` no CPT `politica` | ✅ post 9000288 |
| Subtítulo (ACF) | ✅ gravado, lido de volta, **e renderizado na página** |
| Imagem no campo ACF `imagem` (não `_thumbnail_id`) | ✅ anexo 9000219 |
| Coautoria (Co-Authors Plus) | ✅ `da-redacao` e `agencia-brasil` |
| **Entrou na `wp_bahia_search_idx`** | ✅ **SIM** |
| Matéria, e as 2 páginas de autor | ✅ 200 nas três |
| Aparece na busca do site | ✅ |
| Removida ao final | ✅ zero resíduo na tabela-sombra |

> **O que fica pendente e é seu:** a matéria foi criada pela pilha do WordPress
> (`wp_insert_post` + ACF + CAP), **não pelo navegador com login no painel**. O caminho de escrita,
> os campos, a coautoria e a indexação estão provados; o que falta é o clique no `wp-admin`, que
> exige credencial que eu não tenho. Continua sendo o item 1 do Anexo C.

## Camada 5 — `carga.sh`, o número de ponta a ponta

**E aqui eu errei a medição duas vezes antes de acertar. Fica registrado porque a Fase D vai
repetir isto.**

| Corrida | Contexto | Mediana | p90 | `Threads_running` pico / média |
|---|---|---|---|---|
| 8.0.45 (27/08 #1) | — | 11,85 s | 15,72 s | **só 3 amostras — inválida** |
| 8.0.45 (27/08 #2) | — | 9,52 s | 12,90 s | 11 / 3,0 |
| 8.4.9 #1 | logo após o restart, **pool frio** | 10,63 s | 14,20 s | 13 / 7,1 |
| 8.4.9 #2 | **30 s depois da #1** | 12,43 s | 17,98 s | 15 / 8,3 |
| **8.4.9 #3** | **após 5 min de descanso** | **10,54 s** | **14,49 s** | **9 / 3,5** |

**A corrida #2 foi um erro meu de método:** disparei 30 requisições simultâneas numa `t3.micro`
**30 segundos** depois da rajada anterior. Não mediu aquecimento — mediu a fila da corrida
anterior, e saiu pior. A hipótese que eu queria testar ("o pool está frio") só pôde ser testada
com intervalo de verdade.

**A comparação válida é #2 de 8.0 contra #3 de 8.4:**

| | 8.0.45 | **8.4.9** |
|---|---|---|
| Mediana HTTP | 9,52 s | **10,54 s** |
| p90 | 12,90 s | **14,49 s** |
| Códigos | 30× 200 | **30× 200** |
| `Threads_running` pico | 11 | **9** |
| `SQL_CALC_FOUND_ROWS` pico | 5 | **4** |

**HTTP um pouco mais lento, carga no banco um pouco menor.** Nenhum dos dois sai do ruído de
uma `t3.micro` com 30 URLs frias. **Sem regressão.**

> ### ⚠️ Regra para os portões de carga da Fase D
>
> **Deixar intervalo de recuperação entre corridas.** Duas rajadas encavaladas medem a fila da
> primeira, e o número sai pior sem que nada tenha piorado. Cinco minutos foram suficientes numa
> `t3.micro`; em produção, o portão dos 0, 5 e 15 minutos já embute o intervalo — **desde que as
> medições não sejam repetidas dentro de cada janela.**

## Portão de saída da Fase C

- [x] dump verificado antes (snapshot `available`, 100%, 8.0.45)
- [x] indisponibilidade pelo primeiro `SELECT`: **148 s**
- [x] `PrePatchCompatibility.log`: 0 erros
- [x] integridade: 92 tabelas, nenhuma encolheu
- [x] site respondendo: 16/16
- [x] busca funcionando, com resultado
- [x] matéria de teste: ACF, coautoria, indexação, páginas de autor
- [x] `carga.sh` comparado, com portão de contagem verde
- [ ] publicação **pelo navegador, no painel** — sua, item 1 do Anexo C

---

# FASE 3 — Blue/Green em produção

## 3.1 Pré-requisitos — atendidos

Para MySQL a AWS pede **uma coisa**: *"you must enable automated backups"*. **Retenção de backup
= 7 dias** nas duas instâncias. `log_bin = 1`. `binlog_format` é `MIXED`; a AWS não exige `ROW`
para instância única, e o padrão do 8.4 passa a ser `ROW`. `binlog_expire_logs_seconds` em
**30 dias**, folga para a replicação reversa do §3.5.

## 3.2 A sequência

1. **T1, T0, T3, T2 e T4 feitos**, banco em ~3,6 GB de dados e ~10 GiB livres.
2. **Criar o Blue/Green na mesma versão** (8.0.42). A AWS cria o verde restaurando um snapshot e
   configura a replicação azul → verde.
3. **Esperar a inicialização de armazenamento chegar a 100%** — obrigatório (§3.3).
4. **Subir o verde para 8.4.9**, com o parameter group `bahia-mysql84`. A pré-verificação roda
   ali, **sobre uma cópia, sem tocar em produção**.
5. **Validar o verde** com as cinco camadas do §2.3.
6. **Aquecer o verde** e conferir os quatro portões do §3.3.
7. **`ReplicaLag` perto de zero** e trocar.
8. **Não apagar o azul por 48 horas.**

**Sincronização inicial:** para ~3,6 GB, **20 a 40 minutos** para o verde existir, mais a
replicação alcançar.

**Tabelas sem chave primária**, que atrasam replicação: `wp_nxs_log` e `wp_nxs_query`. **T3
elimina a maior** — as 592.296 linhas estavam no schema `homolog`. No schema `prod` as duas estão
**vazias**.

## 3.3 VERDE FRIO — item obrigatório

### O mesmo modo de falha da virada de 18/08

| Medida | Valor |
|---|---|
| Indisponibilidade da manutenção | 2 min 32 s |
| **Reversão, por saturação do banco** | **39 s** |
| O que derrubou | **desempenho**: 504 intermitente inclusive na home, CPU dos pods em 31% |
| Mediana | **27,69 s** · p90 35,11 s · máx 36,05 s |
| Acima de 5 s | **30 de 30** |
| **`Threads_running` no pico** | **21-22** |

Do relatório: *"onze rodadas não viram o problema: ele só aparece com concorrência e cache frio."*

### Por que o verde é frio, e por que **neste** volume é pior

Produção hoje: **665.072 de 720.896 páginas com dado (92,3%)**, **10,90 GB**, e **uma leitura
física a cada 25,05 milhões de requisições**. `ReadIOPS` médio: **0,28/s**. São 455 dias de
aquecimento.

**E aqui entra o número novo desta revisão: o volume é gp2 de 20 GiB.** Um gp2 desse tamanho tem
**IOPS de base no piso de 100**, com crédito de rajada até 3.000. Produção convive com isso
porque **quase não lê do disco** — 0,28 IOPS de leitura por segundo, com `BurstBalance` parado em
**99%**.

Um verde frio inverte exatamente isso: precisa puxar o volume inteiro do S3 por bloco (*lazy
loading*) **e** encher um buffer pool vazio, num volume cujo regime permanente são 100 IOPS. A
AWS avisa que *"storage upgrades involving General Purpose SSD (gp2) storage can deplete your I/O
credit balance"*.

**Cinco segundos depois de uma troca perfeita, isso recebe 100% do tráfego do portal.**

### As três defesas — obrigatórias

**Defesa 1 — inicialização de armazenamento.** *"Amazon EBS proactively downloads blocks from
Amazon S3, providing maximum volume performance from the first use."* **Disponível para `db.m5`**
— a classe de produção — e **indisponível para t3 e t4**, mais uma razão pela qual homolog nunca
validaria esta parte. **Portão: 100% antes de qualquer coisa.**

**Defesa 2 — aquecer com consultas reais, não só com a replicação.** Duas passadas contra o verde:

*Passada A — derrotar o lazy loading e encher o pool:*

```sql
SELECT COUNT(*) FROM wp_posts;
SELECT COUNT(*) FROM wp_postmeta;
SELECT COUNT(*) FROM wp_bahia_search_idx;
SELECT COUNT(*) FROM wp_term_relationships;
SELECT COUNT(*) FROM wp_yoast_indexable;
SELECT COUNT(*) FROM wp_as3cf_items;
SELECT COUNT(*) FROM wp_terms;
SELECT COUNT(*) FROM wp_term_taxonomy;
```

**Depois das limpezas a base tem ~3,6 GB e o buffer pool tem 11 GB: cabe inteira, três vezes.**
Esta passada não aproxima — **carrega tudo**. É o maior ganho colateral da Fase 0.5.

*Passada B — aquecer os índices que as páginas usam:* as consultas do `carga.sh` — home, os 10
archives e a busca nos 10 termos.

> **Alternativa rejeitada, e o motivo:** apontar um pod de produção para o verde para aquecê-lo
> com tráfego real. O verde é réplica **somente leitura**; qualquer escrita — contador de views,
> transient, sessão — falharia com erro 1290, que é exatamente o modo de falha silenciosa que T1
> existe para evitar.

**Defesa 3 — trocar no horário de menor tráfego.** Recomendação da AWS, e 18/08 mostrou o preço
de errar a hora.

### O critério de "aquecido o suficiente" — em número

**Quatro portões. Todos têm de passar.**

| # | Portão | Valor exigido | Como medir |
|---|---|---|---|
| **1** | Inicialização de armazenamento | **100%** | `describe-db-instances` → `StorageOperationPercentProgress` |
| **2** | Dados residentes no buffer pool | **`Innodb_buffer_pool_bytes_data` ≥ 3,4 GB** (95% da base de ~3,6 GB) | `SHOW GLOBAL STATUS` no verde |
| **3** | Taxa de acerto **incremental**, janela de 5 min | **≥ 99,9%** | fórmula abaixo |
| **4** | `carga.sh` apontado ao verde | **mediana < 5 s**, **0 respostas > 5 s**, **`Threads_running` pico < 10**, **≥ 10 amostras** | `CARGA_BASE=… ./carga.sh verde` |

**Portão 2.** Antes das limpezas seria uma aposta: 9,56 GB de dados a puxar por um gp2 de 100
IOPS. Depois, **~3,6 GB num pool de 11 GB — cabe com folga de 3× e o portão converge.**

**Portão 3, e por que incremental.** A taxa acumulada é inútil numa instância nova: carrega a
história do arranque e melhora sozinha sem dizer nada sobre agora. Duas leituras separadas por
5 minutos:

```sql
SHOW GLOBAL STATUS WHERE Variable_name IN
  ('Innodb_buffer_pool_reads','Innodb_buffer_pool_read_requests');
```
```
taxa = 1 − (reads_2 − reads_1) / (requests_2 − requests_1)
```

Exigido **≥ 0,999**. Para calibrar: produção está em **0,99999996**. O portão é mil vezes mais
frouxo, de propósito — um verde recém-aquecido não tem 455 dias.

**Portão 4.** Os três números são os critérios de aceitação escritos em 18/08 (*"`Threads_running`
abaixo de 10 no pico"*, *"nenhuma resposta acima de 5 s"*), e o estado bom alcançado ali foi
**máximo 4,35 s com `Threads_running` em 2**. A exigência de **≥ 10 amostras** é o que o §T-1
acrescentou: sem ela o pico é ficção.

**Se qualquer portão falhar, não se troca.** O verde continua replicando, o azul continua
servindo, e não se perdeu nada — é a propriedade do Blue/Green que estamos comprando.

## 3.4 O IP — resolvido por T1

Com T1 feito, **a troca não exige nenhuma mudança em Kubernetes**. A AWS renomeia os endpoints do
verde para os nomes do azul, *"so that application changes aren't required"* — o que vale para
quem usa o nome. O DNS do RDS tem **TTL de 5 segundos** e o WordPress abre conexão nova a cada
requisição. **É esse fato que reduz a janela real a segundos.**

O que a troca faz com as conexões: *"Drops connections to the DB instances in both environments
and doesn't allow new connections."* **Toda conexão aberta cai, nos dois lados** — benigno para o
WordPress, que não usa pool persistente. O azul continua existindo como `-old1`, **somente
leitura** até alguém pôr `read_only = 0` e reiniciar.

## 3.5 Rollback

**"Immediate switchback to blue environment with no data loss."** O azul está intacto, com os
dados e **com o cache quente de 455 dias**.

**A ressalva, que é o seu próprio argumento:** o escrito no verde depois da troca **não está** no
azul. Dez minutos até decidir voltar são dez minutos de publicações órfãs.

**A solução: replicação reversa.** O evento da troca publica as coordenadas:

```
Binary log coordinates in green environment after switchover:
file mysql-bin-changelog.000003 and position 40134574
```

Exige `binlog retention hours` ≥ 24. Hoje o valor do RDS é **NULL** (só o necessário) e
`binlog_expire_logs_seconds` são 30 dias — **ajustar antes**:

```sql
CALL mysql.rds_set_configuration('binlog retention hours', 48);
```

**É o que transforma "rollback com perda de minutos" em "rollback sem perda".**

**O gatilho do rollback fica escrito antes da janela, em uma frase**, e os portões do §3.3 são a
régua. Em 18/08 a decisão saiu em 39 segundos porque o critério existia.

## 3.6 Custo

| Item | Valor |
|---|---|
| Segunda `db.m5.xlarge`, **sob demanda — não há reserva na conta** | **~US$ 0,342/h ≈ US$ 8,20/dia** |
| Armazenamento duplicado (20 GiB gp2) | ~US$ 0,08/dia |
| Azul retido 48 h após a troca | ~US$ 16,60 |
| **Total previsto (3 dias de verde + 2 de azul retido)** | **~US$ 42** |

---

# FASE 4 — Janela de produção

**Não escrita, de propósito.** Depende dos números que a Fase 2 produzir. O esqueleto:

1. **Já feito, dias antes:** T1, T0, T3, T2, T4; parameter group `bahia-mysql84` criado e
   conferido; `binlog retention hours` em 48; retenção de backup confirmada em 7 dias.
2. **Backup:** dump de produção pelo comando de `IMPORT-prod-para-homolog.md` §1.5, com SHA-256,
   `444` e tamanho conferido. Referência: 547 MB / 2 min 26 s em 19/08 — **agora menor, porque o
   banco encolheu**.
3. **Snapshot manual** nomeado com data, além dos que a AWS tira sozinha.
4. **Verificação:** os quatro portões do §3.3 **antes** da troca; as cinco camadas do §2.3
   **depois**, com o "antes" anotado **antes**.
5. **Rollback escrito**, com gatilho em uma frase e relógio, e replicação reversa montada.
6. **Depois:** 48 h com o azul de pé; `PrePatchCompatibility.log` e avisos anexados.

**Referência de horário:** a janela de manutenção das instâncias é **sábado 05:00-05:30 UTC**
(02:00-02:30 BRT). Não é obrigatório usá-la — o Blue/Green troca quando se manda — mas é o
horário que a AWS já reservou.

---

# Anexo A — Custo do Extended Support

**US$ 241,20/mês** bate com: **4 vCPU × US$ 0,100/vCPU-hora × 603 horas**. São ~25 dias: é **a
instância de produção sozinha, no acumulado do mês**, desde 01/08/2026. Mês fechado:
**~US$ 292 a 298**.

| | Anos 1 e 2 | **Ano 3 (a partir de 01/08/2028)** |
|---|---|---|
| Single-AZ, 4 vCPU, us-east-1 | **US$ 292/mês** | **US$ 584/mês** |

A `db.t3.micro` de homolog tem 2 vCPU e custaria mais ~US$ 146/mês se estivesse inscrita — o valor
observado sugere que não está, ou aparece noutra linha. **Conferir no Cost Explorer.**

**E o fato novo do §0.10:** com o Extended Support **ligado**, a AWS leva as instâncias ao
**8.0.46**, não ao 8.4. **A pressão é de custo, não de prazo.**

---

# Anexo B — Fontes

Documentação AWS:

- [Major version upgrades for RDS for MySQL](https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/USER_UpgradeDBInstance.MySQL.Major.html)
- [Upgrades of the RDS for MySQL DB engine](https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/USER_UpgradeDBInstance.MySQL.html)
- [Creating a blue/green deployment](https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/blue-green-deployments-creating.html)
- [Switching a blue/green deployment](https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/blue-green-deployments-switching.html)
- [Amazon RDS Extended Support charges](https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/extended-support-charges.html)

Blogs AWS:

- [Amazon RDS for MySQL LTS version 8.4 is now generally available](https://aws.amazon.com/blogs/database/amazon-rds-for-mysql-lts-version-8-4-is-now-generally-available/)
- [Best practices for upgrading Amazon RDS for MySQL 8.0 to 8.4](https://aws.amazon.com/blogs/database/best-practices-for-upgrading-amazon-rds-for-mysql-8-0-to-8-4-with-prechecks-blue-green-and-rollback/)
- [Upgrade strategies for Amazon RDS for MySQL 8.0 to 8.4](https://aws.amazon.com/blogs/database/upgrade-strategies-for-amazon-rds-for-mysql-8-0-to-8-4/)

MySQL:

- [Features removed in MySQL 8.4](https://dev.mysql.com/doc/refman/8.4/en/mysql-nutshell.html#mysql-nutshell-removals)

**Da própria conta** (a fonte mais forte deste documento):

- `PrePatchCompatibility.log` de `rds-bahiaba-2023`, 29/05/2025 — 0 erros, 1 aviso
- `PrePatchCompatibility.log` de `rds-bahiaba-hml` — 0 erros, 1 aviso
- `describe-db-recommendations` — 9 recomendações, nenhuma de incompatibilidade
- `describe-db-parameters --query "Parameters[?Source=='user']"` — 1 parâmetro em `mysql80-edit`

Documentos internos:

- `HANDOVER.md` §0.2 e **§0.2.1** — as guardas do bucket e como se desligam sozinhas
- `HANDOVER.md` §16 e **§16.3** — instrumentos que descartam dado em silêncio
- `INCIDENTE-virada-abortada-20260818.md` — os números do cache frio
- `RESTAURACAO-PRODUCAO-20260818.md` — tamanho e duração reais de dump
- `IMPORT-prod-para-homolog.md` §1.5 — o comando de dump

---

# Anexo D — Item próprio: o Deployment de produção não tem nenhuma probe

**Levantado em 27/08/2026, durante o T1. Não implementado — levantamento apenas, por instrução.**

## O que existe hoje

```
wordpress: readinessProbe=NENHUMA  livenessProbe=NENHUMA  startupProbe=NENHUMA
nginx:     readinessProbe=NENHUMA  livenessProbe=NENHUMA  startupProbe=NENHUMA
```

Nos **dois** contêineres, em produção. A estratégia é `RollingUpdate` com `maxSurge=1` e
`maxUnavailable=0`.

## Por que isso importa, e por que apareceu justamente agora

Com `maxUnavailable=0` a **contagem** de pods nunca cai — e foi o que se viu no T1. Mas sem
probe, **um pod é dado como pronto assim que o contêiner sobe**: antes de o PHP-FPM aceitar
conexão, e antes de o WordPress conseguir falar com o banco. O Deployment então encerra um pod
antigo confiando numa prontidão que não foi verificada.

Quem de fato segura o tráfego externo é o **health check do ALB**, que é mais lento (intervalo e
limiar próprios) e mais grosseiro (checa uma URL, não a saúde do processo).

**Consequência prática, e é a que dói:** um deploy com problema de conexão ao banco só seria
percebido **pelo ALB, depois de o pod já ter recebido tráfego**. É exatamente o modo de falha que
o T1 existe para evitar — leitura funcionando, escrita quebrada, ninguém notando. A diferença é
que aqui o buraco é de segundos a dezenas de segundos, não permanente.

## O que uma `readinessProbe` correta verificaria aqui

Não basta "a porta abriu". As três camadas, da mais fraca para a mais forte:

| Camada | O que prova | O que **não** prova |
|---|---|---|
| `tcpSocket: 9000` no contêiner `wordpress` | o PHP-FPM aceita conexão | que o WordPress carrega, que o banco responde |
| `httpGet: /` pela porta do nginx | a pilha inteira responde | **nada de banco, se a resposta vier do FastCGI cache** — e vem |
| `httpGet` num endpoint próprio de saúde | PHP-FPM + WordPress + **banco**, em ~1 ms | — |

**O desenho certo é o terceiro**, e tem quatro exigências que não são opcionais:

1. **Endpoint próprio e barato** — um mu-plugin que responda em `/bahia-saude`, faça um
   `SELECT 1` pelo `$wpdb` e devolva `200 ok` ou `503 db-fail`. Sem carregar tema, sem `WP_Query`.
2. **Fora do FastCGI cache.** Se o nginx puder servir aquela URL do cache, a probe passa a
   responder por um arquivo em disco e **para de medir o banco** — vira o `carga.sh` de novo:
   instrumento que devolve resultado plausível sem medir nada. Precisa de regra explícita de
   `fastcgi_cache_bypass` / `no_cache` para o caminho.
3. **Fora do buffer de saída** (`bahia-html-saida.php`) e fora de contadores e analytics.
4. **Sem vazar informação** — devolver `ok` / `db-fail`, nunca a mensagem de erro do MySQL.

## O risco de adicionar probes num cluster que hoje não tem nenhuma

**Este é o ponto que faz a tarefa merecer estudo em vez de um `kubectl patch`.** Uma probe mal
configurada é pior que nenhuma, e aqui há três armadilhas concretas:

**1. A probe compete com o tráfego pelo mesmo pool de workers.** O PHP-FPM de produção roda com
`max_children = 12`. Sob saturação — que já aconteceu, com a busca e com o `SQL_CALC_FOUND_ROWS`
dos archives — a probe fica na fila junto com as requisições reais. Se ela expirar, o pod é
marcado `NotReady`, sai do Service e do target group do ALB, e **a capacidade cai exatamente no
momento de pico**. Uma lentidão vira uma queda. É o mesmo mecanismo que derrubou a virada de
18/08, com um gatilho novo.

**2. `livenessProbe` é a perigosa, e a recomendação é NÃO ter.** Ela não tira o pod de serviço:
ela **mata o contêiner**. Uma liveness que toque o banco transforma qualquer soluço do RDS —
failover, pico de latência, os 39 s de reversão de 18/08 — em **reinício de todos os pods ao
mesmo tempo**, ou seja, converte um incidente de banco numa queda total do site. Se um dia
houver liveness, que seja `tcpSocket` e nada mais.

**3. Interação com `maxUnavailable=0`.** Hoje um rollout com pods que nunca ficam prontos
**trava** em vez de derrubar — o que é bom. Mas se a probe for instável, o rollout fica preso no
meio, com metade dos pods numa versão e metade noutra, até alguém intervir. Foi o que se viu no
T1 com a pausa, e ali era intencional.

## Encaminhamento sugerido

1. **`readinessProbe` apenas.** Nada de `livenessProbe`.
2. **Limiares generosos**: `periodSeconds: 10`, `timeoutSeconds: 5`, `failureThreshold: 6` — ou
   seja, um minuto de falha contínua antes de tirar o pod. O objetivo é pegar pod nascendo
   quebrado, não oscilação sob carga.
3. **`startupProbe` separada e frouxa** para cobrir o arranque, deixando a readiness estável.
4. **Em homolog primeiro**, com um rollout completo observado, e só depois em produção.
5. **Medir antes e depois com o `carga.sh` consertado** — inclusive um teste de carga com a probe
   ativa, para ver se ela desestabiliza sob saturação. É a pergunta central, e só se responde
   medindo.

**Não implementar junto com a subida do MySQL.** Se o site ficar instável depois, o número de
suspeitos precisa ser um.

---

# Anexo C — Pendências

> ## 🔴 ITEM DE FECHAMENTO OBRIGATÓRIO — a política de escrita sai
>
> **`RdsEscritaUpgrade84` foi anexada ao usuário `bahia-pipeline` na janela de 29/08/2026,
> exclusivamente para executá-la. Ela sai INTEIRA no fechamento — seja qual for o resultado.**
>
> Vale igual nos três desfechos: virada concluída, janela fechada sem virada por reprovação no
> portão, ou parada no meio. **Não há desfecho em que ela fique.**
>
> ```bash
> aws iam delete-user-policy --user-name bahia-pipeline --policy-name RdsEscritaUpgrade84
> # conferencia: tem que sobrar so a de leitura
> aws iam list-user-policies --user-name bahia-pipeline
> # esperado: bahia-pipeline-cloudformation, bahia-pipeline-eks-iam,
> #           EFSMinimalAccess, RdsSomenteLeituraParaUpgrade84
> ```
>
> **Portão de conferência**, porque remover sem verificar é o mesmo erro do §16:
>
> ```bash
> aws iam simulate-principal-policy \
>   --policy-source-arn arn:aws:iam::774710032593:user/bahia-pipeline \
>   --action-names rds:DeleteDBInstance rds:ModifyDBInstance rds:RestoreDBInstanceFromDBSnapshot \
>   --query 'EvaluationResults[].[EvalActionName,EvalDecision]' --output text
> # esperado: implicitDeny nas tres
> ```
>
> **Também saem** (temporários da Fase 1, §1.7): a instância `rds-bahiaba-teste84`, o parameter
> group `bahia-mysql80-teste` e o security group `bahia-mysql84-teste`
> (`sg-045aed7cf5c92b6c5`). **Fica** o `bahia-mysql84`, que é o do verde.
>
> *O Albert pediu para ser lembrado disto no fechamento. Está aqui para não depender de memória
> de ninguém.*

| # | Item | Depende de |
|---|---|---|
| 0 | 🔴 **Remover `RdsEscritaUpgrade84`** — ver o quadro acima. Vale em qualquer desfecho | **fechamento da janela** |
| 1 | **T1 concluído** ✅. Falta só publicar uma matéria **pelo painel** para fechar a verificação | **você** |
| 1b | **T0 concluído** ✅ em 29/08, 03:10 UTC — **+2,032 GiB**, bloqueio ≤ 0,129 s | — |
| 2 | Ligar autoscaling de armazenamento (`--max-allocated-storage`) — fora do projeto, AWS pede desde 2025 | **você** |
| 3 | Confirmar no Cost Explorer se homolog está inscrita no Extended Support | **você** |
| 3b | **Probes do Deployment de produção** — levantamento no Anexo D, não implementado | **você** |
| 3c | Rodar o `carga.sh` novo sobre o cenário das 4 correções de consulta de 25/08 — agora dá para saber | eu, quando houver janela |
| 4 | Investigar o template `9000140`: mesma data, conteúdo diferente nos dois lados | eu, quando autorizado |
| 5 | `wp_bwg_theme` — "Row size too large", aviso pré-existente desde 2025. Não bloqueia; vale saber que existe | depois |
| 6 | As 38 opções `rank_math*` em `wp_options` — inertes, não incluídas em T4 | depois |
| 7 | utf8mb3 → utf8mb4: projeto próprio | depois |
| 8 | Schema `test`, vazio, na instância de produção | trivial |

**Fechados nesta revisão:** parameter groups · verificação de pré-atualização · armazenamento
alocado · reservas · versão de destino · causa do Action Scheduler · saúde do WP-Cron ·
`carga.sh`.
