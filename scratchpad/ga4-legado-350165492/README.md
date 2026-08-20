# Arquivo da propriedade GA4 legada (`G-JBPJTKCCXY`)

Cópia local da série histórica do bahia.ba que vive na propriedade GA4 **legada**, exportada
em **20/08/2026** por `arc.albert.cruz@gmail.com`.

| | |
|---|---|
| Conta | `67237036` — "Bahia.ba" |
| Propriedade | `350165492` — "Bahia.ba – GA4" |
| Fluxo de dados | `4437501814` — `https://bahia.ba` |
| ID da métrica | `G-JBPJTKCCXY` |
| Fuso dos relatórios | (GMT-03:00) Horário Bahia |
| Período com dado | **2023-01-19 → 2026-08-19** (1.309 dias, 44 meses) |
| Total no período | 26.173.608 visualizações · 11.859.442 usuários ativos |

## Por que existe este arquivo

Essa propriedade guarda a série longa do site — a linhagem do `UA-67237036-1`, anterior à
propriedade que a redação usa hoje (`G-96ZB07C336`, que só começa em 10/04/2026).

O acesso a ela **não é nosso**: `arc.albert.cruz@gmail.com` entra como convidado, sem função
de administrador, numa conta cujos administradores são de fora da equipe (era de uma agência
anterior) e que não podemos nem listar. Qualquer um deles pode revogar esse acesso a qualquer
momento, sem aviso — e aí a série some. Por isso a cópia foi tirada enquanto dava.

A tag `G-JBPJTKCCXY` parou de ser emitida em **20/08/2026** (commit `d04906e9`). Antes disso
ela morreu sozinha na virada de 19/08, quando o tema legado saiu do ar levando junto o
`header.php` que a escrevia à mão — daí o degrau no último dia da série.

## Arquivos

| arquivo | o que é |
|---|---|
| `serie-diaria.csv` | **A série.** `data, usuários ativos, novos usuários`, um dia por linha. Derivado do export de visão geral, com o índice `Nº dia` convertido em data real. |
| `serie-mensal.csv` | O mesmo agregado por mês, para leitura rápida. |
| `visao-geral-aquisicao-2020-01-01_2026-08-19.csv` | Export cru da "Visão geral da aquisição". Além das séries diárias, traz a quebra por canal de aquisição (primeiro usuário e sessão) do período inteiro. |
| `paginas-todas-2023-01-19_2026-08-19.csv.gz` | Export cru de "Páginas e telas": **as 100.000 páginas mais vistas** de todo o período, com visualizações, usuários ativos, tempo de engajamento e contagem de eventos. |
| `paginas-top5000-2023.csv`<br>`paginas-top5000-2024.csv`<br>`paginas-top5000-2025.csv`<br>`paginas-top5000-2026-ate-08-19.csv` | **As 5.000 páginas mais vistas de cada ano.** A tabela de todo o período responde "a mais lida de sempre"; estes respondem "a mais lida de 2024". Recortados dos exports anuais (74.636 / 65.251 / 75.055 / 100.000 linhas), que ficariam em 8 MB cada — a cauda descartada é de páginas com pouquíssimas visualizações. |

## Ressalvas ao ler estes números

- **O export de páginas está truncado.** O relatório tem 239.274 linhas; a exportação do GA
  para em 100.000. O que ficou de fora é a cauda de páginas com pouquíssimas visualizações.
- **`usuarios_ativos_soma_diaria` em `serie-mensal.csv` é soma de únicos diários**, não único
  do mês: quem leu em dois dias conta duas vezes. Serve para tendência, não para "quantas
  pessoas distintas vieram no mês". O único de verdade do período inteiro é o 11.859.442 da
  tabela acima.
- **Esta propriedade contava usuário logado no WordPress; a atual não.** A `G-96ZB07C336` roda
  pelo Site Kit com `trackingDisabled = ["loggedinUsers"]`. Comparar as duas séries de frente
  vai mostrar um degrau que não é audiência.
- **Tem tráfego de datacenter no meio.** Em agosto/2026, Singapura respondia por algo entre 18%
  e um terço dos usuários em tempo real. Quase certamente não é leitor.
- Datas no fuso da Bahia (GMT-03:00), como configurado na propriedade.

## Como refazer, se um dia houver acesso de novo

Relatório → período personalizado `01/01/2020` a hoje → ícone de compartilhar → **Baixar o
arquivo** → **Baixar o CSV**. A visão geral da aquisição é a que traz as séries diárias; o
"Páginas e telas" traz a tabela de páginas.
