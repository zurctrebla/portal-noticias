# Limpeza e custo do ambiente AWS — levantamento

Conta `774710032593`, região principal `us-east-1`. Levantado em 2026-08-25.
Credencial usada: `arn:aws:iam::774710032593:user/bahia-pipeline`.

**Nada foi executado, parado, desalocado ou removido.** Este documento é só levantamento e proposta.

---

## 0. O que eu NÃO consegui medir — leia antes de confiar em qualquer número

A credencial de pipeline não tem permissão para os serviços abaixo. Isso não é um detalhe de
rodapé: **duas das maiores linhas da fatura estão nesse ponto cego.**

| API | Estado | O que fica sem resposta |
|---|---|---|
| `ce:GetCostAndUsage` | **NEGADO** | Custo de agosto por serviço e por recurso |
| `ce:GetCostForecast` | **NEGADO** | Conferir a previsão de 2.884 |
| `cur:DescribeReportDefinitions` | **NEGADO** | Relatório detalhado em S3 |
| `budgets:*` | **NEGADO** | Orçamentos configurados |
| `rds:DescribeDBInstances` | **NEGADO** | Classe das instâncias, Multi-AZ, sob demanda ou reservada |
| `rds:DescribeDBSnapshots` | **NEGADO** | Lista de snapshots do RDS (item 3 do escopo original) |
| `savingsplans:DescribeSavingsPlans` | **NEGADO** | Se existe Savings Plan, e se algum foi comprado em agosto |
| `wafv2:ListWebACLs` | **NEGADO** | Detalhe do WAF |
| `logs:DescribeLogGroups` | **NEGADO** | Log groups sem retenção |
| `elasticache`, `redshift`, `docdb`, `ecs`, `backup` | **NEGADO** | Não consegui provar que não existem |

Também tentei o caminho indireto: a métrica `AWS/Billing / EstimatedCharges` do CloudWatch está
**vazia** (alertas de faturamento nunca foram ligados na conta), então não há como reconstruir a
fatura por fora do Cost Explorer.

Não me autoconcedi IAM, como combinado.

**Consequência prática:** consegui responder com números as perguntas 2, 3, 4 e 6, responder a 5
parcialmente, e **não consegui fechar a 1**. O que fiz no lugar está na seção 1.

---

## 1. O salto de agosto — o que eu provei e o que continua em aberto

### 1.1 O que eu provei, com data

Duas coisas mudaram de patamar em agosto, e as duas têm data exata.

**a) A produção só começou a servir pelo EKS em 9–10 de agosto.**

O ALB de produção (`k8s-bahiawor-bahiaing-4f2c71ba0e`) processou **0 bytes** de 28/07 até 08/08.
O tráfego começa em 09/08 (1,8 GB) e no dia seguinte já está em 40 GB/dia:

```
2026-08-08     0.0 GB
2026-08-09     1.8 GB   <- primeiro tráfego real
2026-08-10    40.7 GB
```

Ou seja: em julho o EKS existia e era cobrado, mas não servia nada. **Agosto é o primeiro mês em
que os dois ambientes rodam de verdade ao mesmo tempo** — VPS + ALB antigo + RDS antigo de um
lado, 2 clusters + 5 nós + 2 NAT + 2 ALB do outro.

**b) Em 19–20 de agosto — a data da virada do tema — a saída de dados triplicou.**

CloudFront, bytes por dia:

```
2026-08-17     76.9 GB
2026-08-18    106.1 GB
2026-08-19    134.2 GB   <- virada do Newspaper
2026-08-20    224.5 GB
2026-08-21    272.2 GB
2026-08-22    258.0 GB
2026-08-23    213.5 GB
2026-08-24    200.6 GB
```

Por mês: junho 198 GB → julho 1.891 GB → agosto 2.510 GB só até o dia 24.
O ALB de produção acompanha: de ~40 GB/dia (10–17/08) para ~100–153 GB/dia depois de 18/08.

Pela taxa efetiva que a própria fatura de julho revela (90,11 USD ÷ 1.891 GB = **0,0477 USD/GB**),
o CloudFront de agosto fecha perto de **190–200 USD**, contra 90 em julho. E, mantida a taxa atual
de ~220 GB/dia, **setembro vai a ~6.600 GB, ou ~315 USD**.

> Isto não é só custo. Um site que passa a entregar 3× mais bytes para o mesmo público, no dia
> exato da troca de tema, é forte indício de **regressão de otimização de imagem** — tamanho
> servido maior que o necessário. Vale investigar como item técnico, independentemente da conta.
> A distribuição está em `PriceClass_All`, servindo inclusive de edges caros.

### 1.2 O que continua em aberto — e é a maior parte

Inventariei **todos** os recursos que a credencial enxerga, em 9 regiões, e montei o custo
de baixo para cima com preço de tabela us-east-1:

| Item | Quantidade medida | USD/mês |
|---|---|---|
| Nós EKS prod | 4 × t3.large | 242,96 |
| Nó EKS homolog | 1 × t3.medium | 30,37 |
| Control planes EKS | 2 × 0,10 USD/h | 146,00 |
| NAT Gateway | 2 × 0,045 USD/h + 161 GB | 72,95 |
| ALB | 3 × 0,0225 USD/h (LCU desprezível) | ~50,00 |
| IPv4 público | 14 × 3,65 | 51,10 |
| EBS | 164 GB gp3 + 40 GB gp2 (sa-east-1) | 17,12 |
| Snapshots EBS | 96 GB | 4,80 |
| ECR | 28,7 GB | 2,87 |
| EFS | 0,02 GB | ~0,01 |
| CloudFront | ~4.050 GB @ 0,0477 | ~193,00 |
| Transferência de saída (ALB) | ~1.400 GB @ 0,09 | ~126,00 |
| RDS | valor de julho, estável | 264,00 |
| WAF | valor de julho | 24,00 |
| S3 + Route 53 | valor de julho | 10,00 |
| **Subtotal** | | **~1.235** |
| Imposto (~12%) | | ~148 |
| **TOTAL MODELADO** | | **~1.383** |

**Seu console mostra 2.263 fechados e previsão de 2.884. Sobram ~1.500 USD/mês que não
correspondem a nenhum recurso que esta credencial consegue enxergar.**

Isso é um achado, não uma desistência: significa que o dinheiro está concentrado exatamente
onde eu estou cego. Em ordem de probabilidade:

1. **Compra de Savings Plan ou RI em agosto, com pagamento adiantado.** É a hipótese que melhor
   explica um degrau grande e único. `savingsplans:*` é justamente uma das APIs negadas, então
   não consigo nem confirmar nem descartar.
2. **A linha do RDS** — snapshots e armazenamento de backup acima do alocado (0,095 USD/GB-mês),
   IOPS provisionado, ou Multi-AZ. `rds:*` negado.
3. **WAF por volume de requisição.** Com o tráfego triplicando, um WAF cobrado por milhão de
   requisições sobe junto. `wafv2:*` negado.
4. **Plano de Suporte** (Developer/Business), que é percentual da fatura e não aparece como
   recurso nenhum.

### 1.3 Como fechar isso em 3 minutos no console

Isto é o primeiro clique de tudo, antes de qualquer limpeza:

1. **Billing and Cost Management → Cost Explorer → New cost and usage report**
2. Date range: `Aug 1, 2026` a `Aug 25, 2026`; ao lado, `Jul 1` a `Jul 31` para comparar
3. Granularity **Monthly**, Group by **Service** — isso já mostra qual linha explodiu
4. Filtre pelo serviço que subiu e troque Group by para **Usage Type**, e depois para
   **Resource** (precisa de *Resource level data* ligado em Preferences; se estiver desligado,
   ligue agora — passa a valer para os próximos dias)
5. **Billing → Savings Plans → Inventory** e **EC2 → Reserved Instances**: confirme se houve
   compra em agosto
6. **Billing → Bills → agosto**, expanda a linha do RDS e a do WAF

Se preferir que eu feche isso sozinho numa próxima rodada, o mínimo necessário na credencial é:
`ce:GetCostAndUsage`, `ce:GetDimensionValues`, `rds:Describe*`, `savingsplans:DescribeSavingsPlans`,
`wafv2:List*`, `logs:DescribeLogGroups` — tudo somente leitura.

---

## 2. Respostas diretas às suas perguntas

### 2.1 Load Balancing (era 131,61 em julho)

**Três ALBs. Nenhum Classic.**

| Nome | Criado | VPC | Requisições 7d | Situação |
|---|---|---|---|---|
| `load-balancer-bahiaba-2023` | 2023-01-06 | default (172.31) | **7.177** | o ALB antigo da VPS |
| `k8s-bahiawor-bahiaing-7add34a578` | 2026-07-27 | homolog | 227.296 | homolog |
| `k8s-bahiawor-bahiaing-4f2c71ba0e` | 2026-07-28 | prod | 5.776.411 | produção |

O ALB antigo é o `load-balancer-bahiaba-2023`. **Ele NÃO está parado** — recebe ~1.000
requisições por dia. O motivo está na seção 3.2.

Target groups: 4, e um deles é órfão — **`target-group-https-bahiaba` não está ligado a load
balancer nenhum**. Custo zero, mas é lixo.

Sobre o valor: 3 ALBs a 0,0225 USD/h dão ~49 USD/mês de base, e o LCU medido é desprezível
(0,083 no de produção). Os 131,61 de julho não batem com isso — em fevereiro, com **um** ALB
só, a linha já era 91,74. Isso significa que o grosso da linha histórica era **LCU do tráfego
que passava pela VPS**, e essa parte agora caiu para zero. **A linha de ELB deve encolher em
agosto/setembro, não crescer.**

### 2.2 EC2-Outros (34 → 62 de fevereiro a julho): é IP, não disco

Item a item, tudo que existe:

- **IPv4 público — 14 endereços, 51,10 USD/mês.** Este é o item que cresceu. Desde fev/2024 a
  AWS cobra 0,005 USD/h por **todo** IPv4 público, inclusive os associados. Distribuição:
  - **6** no ALB antigo `load-balancer-bahiaba-2023` (um por AZ) → **21,90 USD/mês**
  - 4 nos dois ALBs novos
  - 2 nos NAT Gateways
  - 1 na VPS
  - **1 ocioso: `13.219.54.229` / `eipalloc-0dc37849453fa5107`** — não está associado a nada
- **EBS: 164 GB, ~13 USD/mês. Zero volumes desanexados.** Nada a limpar aqui.
- **Snapshots EBS: 6, 96 GB nominais, ~4,80 USD/mês.** Todos de 2023–2025, todos gerados por
  `CreateImage` das AMIs da VPS.
- **AMIs: 5**, de 2023 a 2025.

### 2.3 VPC a 38,21: sim, há NAT Gateway — dois

| ID | VPC | Criado | Dados 7d |
|---|---|---|---|
| `nat-039402825a0f8a317` | homolog | 2026-07-27 | 2,85 GB |
| `nat-0018a32007b68e4f0` | prod | 2026-07-28 | 34,75 GB |

**65,70 USD/mês** só de hora, mais ~7 USD de processamento. Em julho eles existiram por 4 dias
apenas — por isso a linha de VPC era 38. **Em agosto ela vai para ~117 USD** (NAT cheio + IPv4).
É um dos aumentos reais e previsíveis.

### 2.4 RDS: duas instâncias, uso real medido, classe pendente de console

`rds:Describe*` está negado, então descobri as instâncias pelas métricas do CloudWatch e inferi
o porte pela memória livre.

| | `rds-bahiaba-2023` (PROD) | `rds-bahiaba-hml` (HOMOLOG) |
|---|---|---|
| IP / AZ | 172.31.70.197 / us-east-1c | 172.31.50.61 / us-east-1d |
| CPU média 7d | **9,4%** (máx 64,6%) | 6,0% (máx 51,7%) |
| Memória livre | **2,05 GB** estável | **0,04 GB** — 40 MB |
| Conexões média | 4,45 (máx 60) | 0,57 (máx 12) |
| Espaço livre | 3,00 GB | 2,50 GB |
| Write IOPS | 62 (máx 305) | 10 |
| Read IOPS | 0,28 | 28 (máx **2.089**) |

**Produção está claramente superdimensionada em CPU** — 9,4% de média com pico de 64%. Se a
classe for `db.m5.large` ou maior, há espaço real para descer um degrau. Mas **não proponha
mudar antes de ver a classe e se há Multi-AZ**, porque metade dos 264 USD pode ser justamente
Multi-AZ, e aí a conversa é outra.

**Homolog tem um problema técnico, não de custo:** 40 MB de memória livre, com picos de 2.089
Read IOPS. Isso é uma instância em inanição de memória, lendo do disco o que deveria estar em
buffer pool. É a explicação do sitemap que dá 504 em homolog e não em produção.

Espaço em disco: cheguei a suspeitar de risco, mas a tendência de 30 dias descarta.
Produção perde **3,1 MB/dia** — cerca de **976 dias** até encher. É baixo em valor absoluto,
mas estável. Homolog cai mais rápido (−25 MB/dia, ~100 dias) e merece um olhar, sem urgência.

### 2.5 CloudFront (5 → 114 oscilando): já respondido na seção 1.1

A oscilação histórica é tráfego real do site. O que importa é o degrau novo: **triplicou em
19–20/08, na virada do tema, e continua no patamar alto.** Taxa efetiva de 0,0477 USD/GB.

---

## 3. Perguntas da rodada anterior

### 3.1 Nós por cluster, tipo e custo separado

| | **prod** | **homolog** |
|---|---|---|
| Cluster | `bahia-eks-prod` (1.32) | `bahia-eks-homolog` (1.32) |
| Nodegroup | `bahia-nodes-prod` | `bahia-nodes-homolog` |
| Nós | **4 × t3.large** | **1 × t3.medium** |
| Escala | **min 4 / desejado 4 / máx 6** | min 1 / desejado 1 / máx 2 |
| Capacidade | ON_DEMAND | ON_DEMAND |
| Control plane | 73,00 USD/mês | 73,00 USD/mês |
| Nós | 242,96 USD/mês | 30,37 USD/mês |
| **Subtotal** | **315,96** | **103,37** |

> **Correção a um dado da sua pergunta:** o nodegroup de produção está em **min = 4**, não
> min = 2. Confirmado tanto no nodegroup quanto no ASG
> (`eks-bahia-nodes-prod-eccfd4c7-...`: Min 4, Desired 4, Max 6). Com min igual a desired, o
> Cluster Autoscaler **não consegue reduzir nada hoje** — ele só pode subir até 6.

### 3.2 Baixar o mínimo para 2 é seguro? **Não. Os números dizem o contrário.**

Uso real dos 4 nós de produção, agora:

```
NODE                          CPU(cores)  CPU(%)   MEM        MEM(%)
ip-10-2-10-61.ec2.internal    1770m       91%      4070Mi     57%
ip-10-2-10-98.ec2.internal    1119m       57%      3812Mi     53%
ip-10-2-11-61.ec2.internal    1315m       68%      4031Mi     56%
ip-10-2-11-90.ec2.internal    1768m       91%      4802Mi     67%
```

- **CPU somada em uso: ~5,97 vCPU.** Capacidade de 2 nós t3.large = 4 vCPU. **Não cabe.**
- **Memória somada em uso: ~16,3 GB.** Capacidade de 2 nós = 16 GB, antes do sistema. **Não cabe.**
- **CPU somada em *requests*: 4,75 vCPU.** O scheduler recusaria os pods em 2 nós, mesmo que o
  uso real coubesse.
- O **HPA está no teto**: `wordpress-hpa` com min 2 / max 5 está em **5 réplicas de 5**.

E há um problema que a média esconde: **t3 é burstable**. O baseline do t3.large é 30% por vCPU.
Dois nós rodam acima disso, e um deles já **zerou os créditos**:

```
INSTÂNCIA             CPU méd 7d   Crédito méd   Crédito MÍN   Surplus máx
i-08ab025c06095f807      34.8%        227.9         0.0          608.2
i-006b4f7ee050eabef      24.3%        856.4       779.0            0.0
i-0c93d0e54e269854c      21.7%        862.7       833.9            0.0
i-05ddf59910d207289      18.7%        863.5       835.1            0.0
```

`i-08ab025c06095f807` esgotou o saldo, entrou em **surplus** — que é cobrado à parte, a
0,05 USD por vCPU-hora — e está sendo estrangulado no baseline.

**Conclusão: produção não está sobrando, está apertada.** Reduzir para 2 nós seria queda, não
economia. A economia aqui não vem de cortar nós; vem de trocar a família e comprometer capacidade
(seção 3.4).

### 3.2.1 A curva horária encerra a ideia de reduzir capacidade por horário

Medida em 7 dias, horário de Salvador, no ALB de produção e na CPU do nó `i-08ab025c06095f807`:

```
  hora      req/h   CPU
  05:00    20.273    30%   <- vale
  04:00    20.910    32%
  03:00    23.604    31%
  02:00    25.190    31%
  ...
  16:00    42.090    36%   <- pico
```

**Razão pico/vale: apenas 2,1×. E mesmo às 05h a CPU fica em ~30% — exatamente o baseline do
t3.large.** Não há folga nem na madrugada.

Isso descarta, com número, a família inteira de soluções por agendamento: desligar nós à noite,
escalar por horário, parar homolog fora do expediente do lado de produção. **O site não dorme.**

A resposta continua sendo a da seção 3.4: **família não-burstable com Savings Plan** — capacidade
constante, porque a demanda é constante. Agendamento resolveria um problema que esta conta não
tem.

O que dá para fazer com segurança **hoje**: baixar o **min de 4 para 3**, mantendo desired 4.
Isso não muda nada agora, mas devolve ao Cluster Autoscaler o direito de recuar um nó nas
madrugadas — cerca de **60 USD/mês** no melhor caso, com risco baixo e reversível num clique.

### 3.3 Homolog inteiro: ~166 USD/mês (fora RDS)

| Item | USD/mês |
|---|---|
| Control plane | 73,00 |
| 1 × t3.medium | 30,37 |
| NAT Gateway | 32,85 + ~0,55 |
| ALB | 16,43 |
| IPv4 (3) | 10,95 |
| EBS 20 GB | 1,60 |
| **Total** | **~165,75** |

Mais a fatia de `rds-bahiaba-hml` dentro da linha de RDS, que o console vai mostrar.

Uso real do nó: **62% de CPU, 39% de memória** (1199m / 1310Mi). **Não dá para diminuir o nó** —
um t3.small tem 2 GB de RAM e o uso já é 1,3 GB antes do sistema. O t3.medium está certo.

Opções, com o número de cada uma:

| Opção | Economia | Custo |
|---|---|---|
| **Manter como está** | — | 166 USD/mês |
| **Remover o NAT Gateway** (nós em subnet pública, ou VPC endpoints para ECR/S3) | ~33 USD/mês | mexer na rede de homolog |
| **Desligar os nós fora do horário** (scale 0 à noite/fim de semana, ~60% do tempo) | ~18 USD/mês | o control plane continua cobrando; ganho pequeno |
| **Destruir o cluster e recriar por Terraform quando precisar** | **~166 USD/mês** | o estado está em `bahia-terraform-state`; recriar leva ~20 min e o banco de homolog continua de pé |

O ponto que decide: **73 dos 166 são o control plane, e ele não tem como baratear.** Se homolog
não é usado todo dia, a última opção é desproporcionalmente melhor que as outras duas. Você pediu
para não propor remoção de homolog, então deixo isto como informação, não como proposta.

### 3.4 Savings Plan / Reserva: existe **uma**, e ela é da VPS

```
1 × c6a.xlarge   standard   termina em 2026-10-01
```

Nenhum Savings Plan que eu consiga ver (`savingsplans:*` negado — confirme no console).

**Isto muda completamente a ordem da limpeza da VPS.** A reserva cobre exatamente o tipo da VPS
(`c6a.xlarge`). Numa RI standard você paga **exista ou não** a instância rodando. Portanto:

> **Parar a VPS antes de 01/10/2026 economiza exatamente 0 USD.**
> O que economiza é **não renovar a reserva** e ter a instância terminada até lá.

E o inverso é o risco de esquecer: se a VPS continuar ligada depois de 01/10, ela deixa de ser
coberta e passa a custar **111,69 USD/mês sob demanda**. **01/10/2026 é o prazo real desta
decisão.**

Sobre comprometer a capacidade nova: os 5 nós EKS somam 273,33 USD/mês sob demanda e são carga
estável há um mês. Um **Compute Savings Plan de 1 ano, sem adiantamento**, dá tipicamente ~28% e
vale para qualquer família e região — o que importa aqui, porque a família provavelmente vai
mudar (abaixo).

| Cenário | USD/mês |
|---|---|
| Hoje: 4 t3.large + 1 t3.medium sob demanda | 273,33 |
| Mesma capacidade com Compute SP 1 ano | ~197 |
| **2 × m6i.xlarge** (8 vCPU / 32 GB, **não burstable**) + t3.medium sob demanda | ~311 |
| **2 × m6i.xlarge + t3.medium com Compute SP 1 ano** | **~224** |

A terceira linha é a que resolve o problema técnico e o custo ao mesmo tempo: mesma CPU e mesma
memória de hoje, **sem crédito de burst para esgotar**, por menos do que se paga agora — desde
que se aceite o compromisso de 1 ano. Não compre nada antes de confirmar no console se já existe
Savings Plan ativo (hipótese 1 da seção 1.2).

---

## 4. MAPA DE REDE DO RDS — RECURSOS INTOCÁVEIS

Esta é a linha entre limpeza e queda. **Nada nesta seção pode ser removido, e o VPC default não
pode ser limpo por varredura.**

O RDS de produção **está** no VPC default, junto com a VPS antiga:

```
rds-bahiaba-2023 (PRODUÇÃO)
  ENI ........ eni-07b2a97c5bd5c34bf   (owner: amazon-rds)
  IP ......... 172.31.70.197
  Sub-rede ... subnet-08fd776d         us-east-1c
  VPC ........ vpc-4c49202b            172.31.0.0/16  (DEFAULT)
  SGs ........ sg-0234245542eb43738  "MySQL"
               sg-0e96076df475b4843  "AcessoRestrito"

rds-bahiaba-hml (HOMOLOG — também no VPC default)
  ENI ........ eni-02423520eb09003b7
  IP ......... 172.31.50.61
  Sub-rede ... subnet-a3dbd889         us-east-1d
  SG ......... sg-0234245542eb43738  "MySQL"
```

### 4.1 Lista de intocáveis

| Recurso | ID | Por quê |
|---|---|---|
| VPC default | `vpc-4c49202b` | contém os dois RDS |
| Sub-rede | `subnet-08fd776d` | ENI do RDS de produção |
| Sub-rede | `subnet-a3dbd889` | ENI do RDS de homolog |
| SG `MySQL` | `sg-0234245542eb43738` | **único** SG que libera 3306 para os clusters |
| SG `AcessoRestrito` | `sg-0e96076df475b4843` | **anexado ao ENI do RDS de produção** |
| SG `EC2` | `sg-0614f9d2cf0b6c697` | é a *origem* autorizada no 3306 do SG MySQL |
| Peering | `pcx-0e1bf34bd88aee31c` | homolog 10.1/16 ↔ default — **ativo** |
| Peering | `pcx-010554a6402559d7a` | prod 10.2/16 ↔ default — **ativo** |
| ENIs | `eni-07b2a97c5bd5c34bf`, `eni-02423520eb09003b7` | são o RDS |

### 4.2 As duas armadilhas concretas

**Armadilha 1 — o SG `AcessoRestrito` é compartilhado.**
Ele está na VPS **e** no ENI do RDS de produção. Ao desmontar a VPS, é natural olhar para os SGs
dela e apagar os dois. **Apagar `AcessoRestrito` derruba a produção.** Mesma coisa para o SG
`EC2`: ele não está no RDS, mas é a origem nomeada na regra de 3306 do SG `MySQL` — apagá-lo
revoga o acesso.

**Armadilha 2 — a instância da VPS se chama "PRODUÇÃO".**
Na tela do EC2, o que você vê é:

```
i-067a9df3e888a90f6   PRODUÇÃO   c6a.xlarge   running    <- é a VPS ANTIGA, a que sai
i-006b4f7ee050eabef   (sem nome) t3.large     running    <- produção de verdade
i-0c93d0e54e269854c   (sem nome) t3.large     running    <- produção de verdade
i-08ab025c06095f807   (sem nome) t3.large     running    <- produção de verdade
i-05ddf59910d207289   (sem nome) t3.large     running    <- produção de verdade
i-0421c2cf337fc35a9   (sem nome) t3.medium    running    <- homolog
```

**A única instância chamada "PRODUÇÃO" é justamente a que deve sair, e os nós que realmente
servem o site não têm nome.** Confira sempre pelo ID `i-067a9df3e888a90f6`, nunca pelo nome.

### 4.3 Observação de segurança, fora do escopo de custo

O SG `AcessoRestrito`, que está no ENI do RDS de produção, tem regras de entrada abertas ao mundo:

- `22/tcp` (SSH) para `0.0.0.0/0`
- `9000/tcp` (Portainer) para `0.0.0.0/0`

Para o RDS essas portas não respondem, então não é uma porta aberta no banco — mas a VPS carrega
o mesmo SG e nela essas portas existem. Não é item de limpeza; é item de segurança e deveria ser
tratado à parte.

---

## 5. A VPS antiga — as quatro provas que você pediu

`i-067a9df3e888a90f6` · `54.243.117.103` / `172.31.0.178` · c6a.xlarge · ligada desde 08/05/2026

### (a) "Nada aponta para ela" — **FALSO. Achei um apontamento.**

Varri as duas zonas do Route 53 (`bahia.ba` com 23 registros e `bahiaba.com.br` com 3),
procurando o IP público, o IP privado e o DNS do ALB antigo:

```
*** bahia.ba.   aws.bahia.ba.   A   ->   ['54.243.117.103']
```

**`aws.bahia.ba` resolve para a VPS.** E ela responde:

```
HTTP/1.1 301 Moved Permanently
Server: nginx/1.31.1
X-Redirect-By: WordPress
Location: https://bahia.ba/
X-Cache: HIT
```

É um WordPress vivo, atrás de cache, servindo redirecionamento para o site principal. Isso
explica as ~1.000 requisições/dia no ALB antigo e o tráfego de rede da instância (**573 MB/dia
de entrada, 94 MB/dia de saída** — ela não está ociosa).

**Consequência:** desligar a VPS quebra `aws.bahia.ba`. O impacto é pequeno (é só um 301), mas
é real e precisa de ação: substituir o registro A por um redirecionamento que não dependa da
VPS, ou removê-lo se ninguém mais usa. **Antes disso, descubra quem ainda chama esse host** —
o log do ALB antigo diz.

Webhooks, cron externo e integrações de terceiros **não têm como ser verificados de dentro da
AWS**. A janela de 7 dias parada (seção 6) é exatamente o mecanismo para isso aparecer.

### (b) "Os arquivos estão todos no S3" — **NÃO VERIFICADO. Fica pendente.**

Você pediu inventário, não amostragem, e com razão — foi dessa máquina que saíram 32 fotos
recuperadas. Mas não tenho acesso SSH a ela por aqui, e as métricas de tamanho de bucket do
CloudWatch não estão sendo publicadas para `static.bahia.ba`.

**Este é o item que mais me impede de dizer "pode desligar".** É preciso rodar, na VPS:

```bash
# na VPS
find /var/www/.../wp-content/uploads -type f | wc -l
find /var/www/.../wp-content/uploads -type f -printf '%P\n' | sort > /tmp/vps-uploads.txt
```

e comparar com o bucket:

```bash
aws s3 ls s3://static.bahia.ba/wp-content/uploads/ --recursive --summarize | tail -3
aws s3 ls s3://static.bahia.ba/wp-content/uploads/ --recursive \
  | awk '{print substr($0, index($0,$4))}' | sed 's#^wp-content/uploads/##' | sort > /tmp/s3-uploads.txt
comm -23 /tmp/vps-uploads.txt /tmp/s3-uploads.txt   # o que só existe na VPS
```

**A saída do `comm` tem que ser vazia.** Enquanto não for, a VPS não deve ser terminada — parada
sim, terminada não.

### Fato do inventário: a VPS ainda é o WordPress de produção, com o DNS mudado

Levantado por SSH em 26/08. A VPS roda um WordPress (Docker Swarm) que conecta no **RDS de
produção** (`WORDPRESS_DB_HOST=rds-bahiaba-2023`, `DB_NAME=prod`) e serve o **mesmo bucket**
`static.bahia.ba`. Isso não é anomalia: **ela era o ambiente de produção**, e a virada trocou só
o DNS. Continuar conectada é o estado natural de uma máquina que ainda não foi desligada.

Consequências para o desligamento, sem as quais a terminação perde coisa:
- **102 originais existem só na VPS** (falhas de offload; 8,33 MB) — copiados para
  `s3://static.bahia.ba/_resguardo-vps/` em 26/08, então a terminação já não perde mídia. Nenhum
  deles é referenciado em matéria: 101 são arquivos de trabalho órfãos, 1 é variante de edição
  cujo arquivo servido já está no S3.
- **Runner de CI ocioso**: há um GitHub Actions self-hosted registrado para `zurctrebla/portal-noticias`,
  mas os dois workflows do repo usam `ubuntu-latest`. Terminar a VPS remove um runner sem uso;
  nenhum workflow quebra. Resta desregistrá-lo (Settings → Actions → Runners), cosmético.
- **`aws.bahia.ba`** ainda serve o 301 pela VPS (Varnish) — repontar antes de desligar.
- O MariaDB **local** da VPS não tem dados de WordPress; o WP sempre usou o RDS.

Detalhe da sessão em `VPS-SESSAO-SSH.md`.

### (c) "O fallback de offload não a referencia mais" — **VERDADEIRO, verificado em runtime**

Como pedido, no runtime de produção e não no código:

```
$ kubectl exec -n bahia-wordpress wordpress-fc88c7846-5fg22 -c wordpress -- \
    php -r 'echo defined("BAHIA_OFFLOAD_FALLBACK_ORIGIN") ? "SIM" : "NAO";'
NAO
```

E o caminho de código não tem valor embutido — quando a constante não existe, devolve string
vazia:

```php
public static function fallback_origin() {
    $origin = defined( 'BAHIA_OFFLOAD_FALLBACK_ORIGIN' )
        ? BAHIA_OFFLOAD_FALLBACK_ORIGIN
        : '';
    return apply_filters( 'bahia_offload_fallback_origin', $origin );
}
```

O `172.31.0.178` que ainda aparece em `bahia-offload-reconciliation.php` está só em comentário
de documentação. **Prova aceita.**

### (d) "O que mais roda nela" — **PARCIAL**

Do lado de fora dá para afirmar: nginx 1.31.1, um WordPress vivo, e uma camada de cache
(`X-Cache: HIT`, `X-Cache-TTL`). O volume raiz tem 64 GB. O consumo médio de **12,3% de CPU**
não é de máquina ociosa — há processo rodando.

O que **não** consigo ver de fora: MySQL local, cron, Varnish, Fail2ban, Netdata. Isso exige
entrar na máquina:

```bash
systemctl list-units --type=service --state=running
crontab -l; ls -la /etc/cron.d/
docker ps        # é Docker Swarm
ss -tulpn | grep LISTEN
```

**Enquanto (b) e (d) não estiverem respondidos, a VPS não deve passar de "parada".**

---

## 6. Sequência de execução — na ordem de clicar

### GRUPO 1 — Seguro agora

Custo somado destes itens: **~26 USD/mês.** É pouco, e está aqui de propósito: são os itens em
que não há como errar. **O dinheiro de verdade está na seção 1.3 e em 3.4** — faça aquilo antes.

---

#### 1.1 — Liberar o Elastic IP ocioso · 3,65 USD/mês

- **O que é:** IPv4 público alocado e associado a nada.
- **Evidência:** `describe-addresses` retorna sem `AssociationId`, sem instância, sem ENI.
- **Tela:** EC2 → Network & Security → **Elastic IPs**
- **Identificar por:** IP **`13.219.54.229`**, alocação **`eipalloc-0dc37849453fa5107`**.
  Confirme que a coluna *Associated instance ID* está **vazia**. Os outros 13 têm ENI — não toque.
- **Botão:** selecionar → **Actions → Release Elastic IP addresses** → **Release**
- **Risco:** o IP volta para o pool da AWS e **não pode ser recuperado**. Como não está ligado a
  nada, ninguém depende dele.
- **Depois:** nada a observar.

---

#### 1.2 — Apagar o target group órfão · 0 USD

- **O que é:** target group sem load balancer.
- **Evidência:** `LoadBalancerArns` vazio.
- **Tela:** EC2 → Load Balancing → **Target Groups**
- **Identificar por:** nome **`target-group-https-bahiaba`**, coluna *Load balancer* **vazia**.
  O `target-group-http-bahiaba` **está** ligado ao ALB antigo — esse fica por enquanto.
- **Botão:** **Actions → Delete** → confirmar
- **Risco:** nulo. Recriar é um formulário.

---

#### 1.3 — Apagar o volume e a instância parada de sa-east-1 · ~4 USD/mês

- **O que é:** `InstanceApuracao`, m3.xlarge **parada desde 2017**, com um volume gp2 de 40 GB
  de 2016. Parada não paga CPU, mas o disco paga.
- **Evidência:** `state: stopped`, lançada em 2017-04-12; nenhuma outra coisa existe em sa-east-1.
- **Tela:** **trocar a região para São Paulo (sa-east-1)** — canto superior direito. Este é o
  único item fora de us-east-1.
- **Identificar por:** instância **`i-0664952b001fc10a5`**, volume **`vol-0e86de6ca382d9022`**.
- **Botão:** EC2 → Instances → Actions → **Terminate instance**. O volume some junto.
- **Risco:** **é de 2017 e ninguém sabe o que tem dentro.** Se houver qualquer chance de valor
  histórico, tire um snapshot antes (40 GB ≈ 2 USD/mês) — Actions → **Create snapshot**.
- **Depois:** volte a região para **N. Virginia (us-east-1)** antes de seguir. Trabalhar na
  região errada é como se apaga a coisa certa no lugar errado.

---

#### 1.4 — Lifecycle policy no ECR · ~3 USD/mês hoje, crescendo ~3/mês

- **O que é:** `bahia-wordpress` com **75 imagens, 28,70 GB, sem nenhuma lifecycle policy**, todas
  de 27/07 a 24/08. Um mês de deploy gerou 75 tags.
  (O repositório `bahia-nginx` que você mencionou **não existe** — só há um repositório.)
- **Tela:** ECR → Repositories → **bahia-wordpress** → **Lifecycle Policy** → *Create rule*
- **Regra sugerida:** *Rule priority* 1, *Image status* **Any**, **Since image pushed / 30 days**,
  ou "manter as 20 mais recentes".
- **Botão:** **Save**. Use antes o **Dry Run** — ele lista exatamente o que seria apagado.
- **Risco:** apagar a imagem de um rollback. Antes de aplicar, veja qual tag está em produção:
  `kubectl -n bahia-wordpress get deploy wordpress -o jsonpath='{..image}'` e garanta que a regra
  a preserva.
- **Depois:** confirme que o deploy atual continua com imagem resolvível.

---

### PONTO DE PARADA 1 — antes de qualquer coisa da VPS

Não siga para o Grupo 2 sem ter, na mão:

1. O **Cost Explorer de agosto agrupado por serviço e por recurso** (seção 1.3). Sem isso a
   limpeza continua sendo palpite sobre ~1.500 USD que ninguém localizou.
2. A confirmação de **Savings Plan** no console.
3. A **classe e o Multi-AZ** dos dois RDS.
4. O **inventário de uploads** VPS × S3 da seção 5(b) com saída vazia.
5. O **levantamento de serviços** de dentro da VPS, seção 5(d).

---

### GRUPO 2 — Seguro depois de observação

#### 2.1 — Repontar `aws.bahia.ba` · pré-requisito da VPS

Antes de mexer na instância. Descubra quem chama o host, depois troque o registro A por um
redirecionamento que não passe pela VPS (CloudFront, S3 redirect ou regra no ingress novo).
Tela: Route 53 → Hosted zones → **bahia.ba** → registro **`aws.bahia.ba`**.
**Espere o TTL vencer** antes de considerar o host migrado.

#### 2.2 — AMI da VPS · **passo que torna tudo reversível**

- **Tela:** EC2 → Instances → **`i-067a9df3e888a90f6`** (a chamada "PRODUÇÃO") →
  **Actions → Image and templates → Create image**
- Nome sugerido: `vps-swarm-final-20260825`. Deixe **"No reboot" desmarcado** se puder aceitar
  a parada; com reboot a imagem fica consistente.
- **Custo:** ~64 GB de snapshot ≈ **3,20 USD/mês**. É o seguro mais barato deste documento.
- **Só siga adiante quando a AMI estiver `available`.**

#### 2.3 — PARAR a VPS (não terminar) e observar 7 dias

- **Tela:** EC2 → Instances → `i-067a9df3e888a90f6` → **Instance state → Stop instance**
- **Confirmação:** a AWS avisa que dados em *instance store* se perdem. O volume raiz é EBS
  (`vol-06c7441a163797b82`, 64 GB) e **sobrevive**.
- **Mantenha o Elastic IP `54.243.117.103` alocado** durante a janela.
- **Custo enquanto parada:** o EBS de 64 GB continua (**~5,12 USD/mês**) e o Elastic IP passa a
  ser cobrado como ocioso (**3,65 USD/mês**). A instância em si não paga.
  **Mas veja 3.4: a reserva c6a.xlarge é cobrada de todo jeito até 01/10.** Parar não economiza
  compute — economiza risco, que é o objetivo aqui.
- **O que observar nos 7 dias:**
  - `bahia.ba` respondendo normalmente (a produção não depende da VPS, mas confirme)
  - imagens antigas carregando — é o teste real do offload
  - reclamação sobre `aws.bahia.ba`
  - qualquer integração ou job externo que falhe

#### 2.4 — ALB antigo e seus 6 IPs · ~38 USD/mês

Só **depois** que a VPS estiver parada 7 dias sem incidente, porque ele é a única porta para ela.

- **Tela:** EC2 → Load Balancing → **Load balancers**
- **Identificar por:** **`load-balancer-bahiaba-2023`**, criado em **2023-01-06**, VPC
  **`vpc-4c49202b`**. Os outros dois começam com **`k8s-`** e são os que servem o site — **não
  toque neles.**
- **Antes de apagar:** ative os access logs por alguns dias, ou confira no CloudWatch de onde vêm
  as ~1.000 req/dia. Elas caem sozinhas quando `aws.bahia.ba` sair.
- **Botão:** **Actions → Delete load balancer** → digitar **`confirm`**
- **Efeito colateral bom:** os **6 IPv4** associados são liberados junto (**21,90 USD/mês**).
- **Risco:** recriar um ALB com listener, certificado e regras é meia hora de trabalho. O DNS
  dele muda.
- **Depois:** apagar também o `target-group-http-bahiaba`, que fica órfão.

---

### PONTO DE PARADA 2 — antes de terminar a VPS

**Não termine a instância enquanto a comparação de uploads da seção 5(b) não tiver dado saída
vazia.** Terminar apaga o volume raiz por padrão. Você já recuperou 32 fotos dessa máquina uma
vez; a AMI de 2.2 é a rede, mas a rede é melhor não precisar.

Prazo que manda na decisão: **a reserva termina em 01/10/2026.** Depois disso a VPS ligada passa
a custar 111,69 USD/mês sob demanda.

---

### GRUPO 3 — Não apagar

| Recurso | Motivo |
|---|---|
| `vpc-4c49202b` e tudo em 172.31 ligado ao RDS | seção 4 |
| SGs `MySQL`, `AcessoRestrito`, `EC2` | seção 4.2 — compartilhados com a produção |
| Peerings `pcx-0e1bf34bd88aee31c`, `pcx-010554a6402559d7a` | é por onde o EKS fala com o banco |
| Cluster `bahia-eks-homolog` | você pediu para não propor; custo em 3.3 |
| Snapshot `bahia-prod-pre-virada-newspaper-20260819` | preservar |
| Snapshot `bahia-prod-pre-virada-newspaper-20260817` | preservar |
| Snapshot `bahiaba-para-hml` | preservar |
| Certificados ACM `bahia.ba` (×2), `prod.bahia.ba`, `hml.bahia.ba` | todos ISSUED e em uso |
| Bucket `bahia-terraform-state` | é o estado do Terraform dos dois clusters |
| Volume `vol-06c7441a163797b82` (raiz da VPS) | enquanto a VPS não for terminada |

**Sobre os snapshots de RDS candidatos** (`snap20230614`, `img20250211`, `img20250703`):
`rds:DescribeDBSnapshots` está **negado**, então não consegui listar, datar nem dimensionar
nenhum deles. Vá em **RDS → Snapshots → aba Manual**, ordene por *Created*, e confira o tamanho
antes de decidir. Os três nomes que você citou coincidem com AMIs de EC2 de mesma data
(`img20250211` → `ami-0a2449bf8c673001f`, `img_20250703` → `ami-0c382a5692331f2c2`), então
confirme se são snapshots de RDS mesmo ou se estava se lembrando das AMIs da VPS. **Antes de
remover qualquer um, verifique obrigação de retenção — não tenho como ver contrato ou política
de auditoria daqui.**

---

## 7. Resumo

**A limpeza clássica — VPS, snapshots, IPs órfãos — vale entre 26 e 90 USD/mês.** É real, vale
fazer, e está detalhada acima. Mas não é onde está o problema.

O que os números mostram:

1. **~1.500 USD/mês da fatura de agosto não correspondem a nenhum recurso visível por esta
   credencial.** Achar isso é o primeiro clique, e o caminho está em 1.3.
2. **A saída de dados triplicou em 19–20/08, no dia da virada do tema**, e continua subindo.
   Isso é custo e provavelmente é regressão técnica de imagem.
3. **Produção não tem folga: dois nós em 91% de CPU, um já sem créditos de burst, HPA no teto.**
   Baixar para 2 nós causaria queda. A resposta certa é trocar t3 por família não-burstable com
   Savings Plan — que sai **mais barato** que a conta de hoje.
4. **A única reserva da conta cobre a VPS e vence em 01/10/2026.** Parar a VPS antes disso não
   economiza nada; deixar passar essa data custa 111,69 USD/mês.
5. **`aws.bahia.ba` ainda aponta para a VPS**, então a premissa de que nada aponta para ela
   não se sustenta — e o inventário de uploads continua por fazer.

---

# CHECKLIST DE DESLIGAMENTO DA VPS — para executar pelo console

Consolidado em 26/08/2026. IDs conferidos na AWS nesta data. **Executar na ordem.** Cada passo
diz onde no console, como identificar por **ID** (nunca por nome — ver o aviso abaixo), e o que
conferir depois.

> ⚠️ **A instância a desligar chama-se "PRODUÇÃO".** É a VPS antiga. Confira SEMPRE pelo ID
> **`i-067a9df3e888a90f6`**. As instâncias que realmente servem o site (nós do EKS) não têm nome.

## O que NÃO é tocado por esta lista — ler antes

- **O RDS `rds-bahiaba-2023` FICA.** É ele que serve o EKS de produção. Nenhum passo aqui o toca.
  Ele vive no ENI `eni-07b2a97c5bd5c34bf` (IP 172.31.70.197), no VPC default. O passo 7 mexe num
  SG que ele compartilha — por isso o SG é **desanexado da instância, não apagado**.
- **Quando a economia começa:** só em **01/10/2026**, quando vence a RI `c6a.xlarge`. A RI é
  cobrada exista ou não a instância, então **parar antes de outubro não rende nada.** O que importa
  é **não renovar a RI** e ter a instância **terminada até 01/10**. Depois dessa data, instância
  ligada volta a custar 111,69 USD/mês sob demanda.

---

## Passo 1 — Desregistrar o runner de CI

- **O que é:** um GitHub Actions self-hosted registrado para `zurctrebla/portal-noticias`. Está
  **ocioso** (os dois workflows do repo usam `ubuntu-latest`); desligar a VPS não quebra CI. Isto
  é só para ele não ficar listado como *offline* para sempre.
- **Onde:** GitHub → repositório `zurctrebla/portal-noticias` → **Settings → Actions → Runners**.
- **Identificar:** o runner `ip-172-31-0-178` (ou o único self-hosted da lista).
- **Ação:** botão **Remove** (ou **⋯ → Remove**), confirmar.
- **Conferir depois:** a lista de Runners não mostra mais o self-hosted.
- **Reversível?** Sim — reinstala-se o runner na máquina a qualquer momento. Cosmético.

## Passo 2 — Remover `aws.bahia.ba` no Route 53 (ANTES do ALB)

- **Por que antes do ALB:** hoje `aws.bahia.ba` → `54.243.117.103` (a VPS). Se o ALB for apagado
  antes e o subdomínio for repontado para ele, apontaria para um balanceador inexistente. Removendo
  o registro primeiro, nada fica pendurado.
- **Onde:** Route 53 → **Hosted zones → `bahia.ba`** (zona `Z1893FF3C31FZC`).
- **Identificar com certeza:** registro **`aws.bahia.ba`**, tipo **A**, valor **`54.243.117.103`**,
  TTL 300. É o único A com esse valor na zona.
- **Ação:** selecionar o registro → **Delete record** → confirmar. (Se quiser preservar o
  redirecionamento que ele fazia, criar antes um registro novo apontando para outra origem; se não,
  apagar resolve — é só um 301 de baixo tráfego.)
- **Conferir depois:** `dig aws.bahia.ba +short` deixa de retornar `54.243.117.103` (após o TTL de
  5 min); `curl -I http://aws.bahia.ba/` passa a falhar em DNS.

## Passo 3 — Criar a AMI da instância (ANTES de parar)

- **Por que agora:** terminar a instância **apaga o volume raiz** — confirmado
  `vol-06c7441a163797b82` (64 GB, /dev/xvda) com **DeleteOnTermination = true**. A AMI é a única
  volta.
- **Onde:** EC2 → **Instances** → selecionar **`i-067a9df3e888a90f6`** → **Actions → Image and
  templates → Create image**.
- **Identificar:** confira o ID `i-067a9df3e888a90f6` no topo do painel de detalhes antes de
  clicar. (Nome "PRODUÇÃO" — é a VPS, é esta mesmo.)
- **Ação:** nome sugerido `vps-swarm-final-20260826`. Deixar *No reboot* **desmarcado** para a
  imagem sair consistente (a instância reinicia; aceitável, ela não serve nada crítico).
- **Conferir depois:** EC2 → **AMIs** → a imagem aparece e fica **`available`** (leva alguns
  minutos). Só prosseguir quando estiver `available`.
- **Custo:** ~64 GB de snapshot ≈ 3,20 USD/mês enquanto a AMI existir. É o seguro mais barato da
  operação.

## Passo 4 — Parar a instância e observar

- **Onde:** EC2 → Instances → **`i-067a9df3e888a90f6`** → **Instance state → Stop instance**.
- **Confirmação da AWS:** avisa que dados em *instance store* se perdem — o volume raiz é **EBS**
  (`vol-06c7441a163797b82`) e **sobrevive**.
- **Manter o Elastic IP alocado** durante a observação (passo 6 só depois).
- **Observar por alguns dias:** `bahia.ba` normal (não depende da VPS), imagens antigas carregando,
  e qualquer reclamação sobre `aws.bahia.ba` ou integração externa que só apareça com a máquina fora.
- **Custo parada:** a RI cobre o compute de qualquer jeito até 01/10; o EBS de 64 GB segue (~5 USD/mês)
  e o EIP passa a ser cobrado como ocioso (3,65 USD/mês) até o passo 6. **Parar não economiza — o
  objetivo aqui é observar antes de terminar.**

## Passo 5 — Terminar a instância

- **Só após:** a AMI estar `available` (passo 3) e a observação (passo 4) sem incidente. E, sobre
  a data: idealmente **até 01/10**, para não renovar a RI com a máquina ainda de pé.
- **Onde:** EC2 → Instances → **`i-067a9df3e888a90f6`** → **Instance state → Terminate instance**.
- **Confirmar:** o ID de novo, e que o volume `vol-06c7441a163797b82` some junto (esperado).
- **Conferir depois:** a instância vai a `terminated`; o volume desaparece de **Volumes**.

## Passo 6 — Liberar o Elastic IP

- **O que é:** o IP público da VPS, que passa a ser cobrado como ocioso assim que a instância sai.
- **Onde:** EC2 → **Network & Security → Elastic IPs**.
- **Identificar com certeza:** IP **`54.243.117.103`**, alocação **`eipalloc-0e6f8adf7907e7a62`**.
  (Após a terminação, a coluna *Associated instance* fica vazia.)
- **Ação:** selecionar → **Actions → Release Elastic IP addresses** → **Release**.
- **Conferir depois:** o IP some da lista. **Irreversível** — o IP volta ao pool da AWS.

## Passo 7 — Desanexar o SG `AcessoRestrito` da instância — NÃO APAGAR

- **O ponto mais delicado da lista.** O SG **`AcessoRestrito` (`sg-0e96076df475b4843`)** está na
  VPS **e** no ENI do RDS de produção (`eni-07b2a97c5bd5c34bf`, confirmado). **Apagá-lo derruba o
  RDS.** Com a instância terminada, o vínculo do SG com ela já deixou de existir — não há o que
  desanexar manualmente. O que **não** se faz é ir aos SGs da VPS e apagar `AcessoRestrito`.
- **Onde (se quiser confirmar):** EC2 → **Security Groups** → `sg-0e96076df475b4843`.
- **Ação:** **nenhuma exclusão.** Deixá-lo existir. Idem para o SG `EC2` (`sg-0614f9d2cf0b6c697`),
  que é a origem nomeada na regra 3306 do SG `MySQL`.
- **Conferir depois:** `sg-0e96076df475b4843` continua listado e anexado a `eni-07b2a97c5bd5c34bf`
  (o RDS). Se em algum momento a intenção for limpar a regra `0.0.0.0/0` de SSH/9000 desse SG, é o
  **item de segurança à parte** — não faz parte deste desligamento.

## Passo 8 — Apagar o ALB antigo e o target group

- **Só depois** de o Route 53 (passo 2) já não apontar para lá, e da VPS terminada.
- **Onde:** EC2 → **Load Balancing → Load balancers**.
- **Identificar com certeza:** **`load-balancer-bahiaba-2023`**, criado em 2023-01-06, VPC
  `vpc-4c49202b` (o default). **Não confundir** com os dois `k8s-bahiawor-bahiaing-…`, que servem
  o site — esses ficam.
- **Ação:** selecionar `load-balancer-bahiaba-2023` → **Actions → Delete load balancer** → digitar
  `confirm`.
- **Depois, o target group:** EC2 → **Target Groups** → apagar **`target-group-http-bahiaba`**
  (ficava ligado a este ALB) e, se ainda não foi no Grupo 1, **`target-group-https-bahiaba`** (órfão).
- **Efeito colateral bom:** os IPv4 do ALB antigo são liberados junto (~22 USD/mês).
- **Conferir depois:** só os dois ALBs `k8s-…` permanecem; `bahia.ba` e as editorias continuam 200.

---

## Depois de tudo — encerramento

- **Prefixo `s3://static.bahia.ba/_resguardo-vps/`** (8,33 MB, 102 objetos): guarda os originais
  que só existiam na VPS. **Pode ser apagado quando você confirmar que o assunto está encerrado** —
  `aws s3 rm s3://static.bahia.ba/_resguardo-vps/ --recursive`. Não antes.
- **AMI `vps-swarm-final-20260826`**: manter enquanto houver qualquer dúvida; é o único retrato da
  máquina. Quando decidir que não volta, apagar a AMI e o snapshot dela encerra o custo de ~3 USD/mês.
- **As duas imagens apagadas** (RD Congo, leilão): tratadas à parte, pela redação. O
  `REDACAO-2-imagens.md` fica arquivado, sem encaminhamento agora.

---

# Security groups — inventário de 27/08/2026, para o levantamento de EC2 adiado

Levantado ao desenhar o isolamento da instância de teste do upgrade, e depois de o Albert remover
as quatro regras de entrada do `AcessoRestrito`. **Nada foi apagado.**

## O que existe hoje, e o que está anexado a quê

| Security group | ID | Entrada | Anexado a |
|---|---|---|---|
| `MySQL` | `sg-0234245542eb43738` | 1 regra: `3306 ← 10.1.0.0/16, 10.2.0.0/16, sg-0614f9d2cf0b6c697` | **ENI dos dois RDS** (`172.31.70.197` e `172.31.50.61`) |
| `AcessoRestrito` | `sg-0e96076df475b4843` | **0 regras** | **ENI do RDS de prod** (`172.31.70.197`) |
| `rds-ec2-1` | `sg-06a8e5bcd98765b27` | 1 regra | **nenhum ENI — órfão** |
| `ec2-rds-1` | `sg-05713cea6b755e67b` | 0 regras | **nenhum ENI — órfão** |
| `mysql-bahiaba-2023` | `sg-0bb13629c7fd663f8` | 1 regra | **nenhum ENI — órfão** |

Mais três grupos `default`, um por VPC (`sg-5bdf2021`, `sg-0ed6a2885344114d4`,
`sg-090d94591e6f93e20`) — são criados pela AWS junto com a VPC e não são resíduo.

## Os três órfãos, e por que dois deles são certeza

As descrições dizem o que são:

```
rds-ec2-1   "Security group attached to rds-bahiaba-restore-temp to allow EC2 instances
             with specific security groups attached to connect to the database."
ec2-rds-1   "Security group attached to instances to securely connect to
             rds-bahiaba-restore-temp."
```

São o par que o console da AWS cria sozinho quando alguém usa o assistente "conectar EC2 ao RDS".
Foram feitos para uma instância chamada **`rds-bahiaba-restore-temp`**.

**Essa instância não existe.** Conferido: as únicas instâncias RDS da conta são `rds-bahiaba-2023`
e `rds-bahiaba-hml`. Os dois grupos referenciam um recurso apagado, não estão em nenhum ENI, e não
protegem nada.

O terceiro, `mysql-bahiaba-2023`, tem nome de quando o banco de produção foi criado (2023) e
também não está anexado a nada — **candidato, mas conferir antes**: nome parecido com o do banco
não é prova de que seja resto.

## O `AcessoRestrito` sai quando o banco tiver grupo próprio

Com **zero regras de entrada**, ele não faz mais nada. **Só continua existindo porque está
anexado ao ENI do RDS de produção** — e um security group anexado não pode ser apagado.

Quando o banco de produção ganhar um grupo próprio e enxuto (o que faz sentido fazer junto com a
subida para o MySQL 8.4, que já vai mexer na instância), o `AcessoRestrito` fica desanexado e pode
ser removido.

## Um a conferir que não estava na lista

`sg-0614f9d2cf0b6c697` — **é referenciado pela regra do grupo `MySQL`** e não foi inspecionado.
Vale saber o que é antes de mexer no grupo `MySQL`: se for um grupo de EC2 que já não existe, a
referência é resíduo; se for algo em uso, não é.

## O padrão que estes cinco casos repetem

Todos os órfãos vieram de recurso **desligado sem limpar a volta**: as três regras removidas do
`AcessoRestrito` apontavam para serviços da VPS terminada na véspera (SSH, Portainer,
OpenLiteSpeed), e os dois `*-rds-1` apontam para uma instância que não existe mais.

**Configuração órfã não avisa que ficou órfã** — ela continua parecendo intencional, e é por isso
que uma regra de firewall para o IP de assinante de alguém sobreviveu cerca de um ano sem que
ninguém soubesse de quem era.

