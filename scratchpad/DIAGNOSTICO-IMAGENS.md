# Diagnóstico — a saída de dados triplicada

Medido em 2026-08-25, contra o runtime de produção e o CDN em produção.
**Nenhuma correção aplicada.** Este documento é só diagnóstico, como combinado.

---

## Veredito em uma linha

**Sua hipótese está correta e eu a confirmei com medição direta — mas ela explica cerca de um
terço do aumento, não o todo.** O fator dominante é outro: o site está servindo 67% mais
requisições. A decomposição está na seção 4.

---

## 1. A hipótese, confirmada

### 1.1 Os 6 tamanhos do `bahia_refactor` morreram; o Newspaper pede outros 10

Registrados hoje em produção (`wp_get_registered_image_subsizes()` no runtime):

```
thumbnail 150x150      td_0x420      0x420      td_324x400   324x400
medium    300x300      td_80x60     80x60      td_485x360   485x360
medium_large 768x0     td_150x0    150x0       td_696x0     696x0
large     1024x1024    td_218x150  218x150     td_1068x0   1068x0
1536x1536 / 2048x2048  td_300x0    300x0       td_1920x0   1920x0
```

E os seis do tema antigo:

```
destaque_gigante   NÃO (morto)      destaque_mini   NÃO (morto)
destaque_grande    NÃO (morto)      news_home       NÃO (morto)
destaque_pequeno   NÃO (morto)      user_avatar     NÃO (morto)
```

### 1.2 Zero anexos do acervo têm os tamanhos que o Newspaper pede

Amostragem por ano de upload, 200 anexos por ano em 5 pontos distintos de cada ano
(início, 25%, 50%, 75%, 95%), ponderada pela contagem real de cada ano:

| ano | anexos | amostra | sem nenhuma derivada | só legado | com `td_*` |
|---|---|---|---|---|---|
| 2016 | 19.267 | 199 | 40% | 60% | 0% |
| 2017 | 13.684 | 200 | **100%** | 0% | 0% |
| 2018 | 11.939 | 200 | **100%** | 0% | 0% |
| 2019 | 10.380 | 200 | **100%** | 0% | 0% |
| 2020 | 10.148 | 200 | **100%** | 0% | 0% |
| 2021 | 11.169 | 200 | **100%** | 0% | 0% |
| 2022 | 11.550 | 200 | 80% | 20% | 0% |
| 2023 | 15.265 | 200 | 0% | 100% | 0% |
| 2024 | 17.592 | 200 | 0% | 100% | 0% |
| 2025 | 18.870 | 199 | 0% | 100% | 0% |
| 2026 | 13.978 | 198 | 1% | 99% | 0% |

**Acervo estimado: 153.842 anexos de imagem**

- **48,3% (~74.300) não têm derivada nenhuma** — o `_wp_attachment_metadata` tem só
  `width, height, file, image_meta`, sem a chave `sizes`
- **51,7% (~79.500) têm apenas os tamanhos legados** (`destaque_*`, `news_home`, `user_avatar`)
- **0% têm qualquer `td_*`**

> **Correção a um número seu:** são **153.842** anexos de imagem, não 271 mil.
> `SELECT COUNT(*) ... post_type='attachment'` devolve 157.337 no total, dos quais os não-imagem
> saem da conta.

O corte é limpo e informativo: **2017 a 2022 não têm derivada alguma** — nem legada. Essas
imagens **já eram servidas em tamanho cheio pelo `bahia_refactor`**, então não são regressão,
são um problema mais antigo. A regressão de agora é a faixa **2023–2026**, que tinha derivadas
legadas e deixou de usá-las.

### 1.3 O que acontece quando o tema pede um tamanho que não existe

Testado no anexo real `421217` (600×420, de 2024, com tamanhos legados e sem `td_*`):

```
PEDIDO         DEVOLVIDO   arquivo
td_80x60       80x56       animais-mortos-...-cobasi-rs.png
td_218x150     214x150     animais-mortos-...-cobasi-rs.png
td_324x400     324x227     animais-mortos-...-cobasi-rs.png
td_485x360     485x340     animais-mortos-...-cobasi-rs.png
td_696x0       600x420     animais-mortos-...-cobasi-rs.png   <<< ORIGINAL CHEIO
td_1068x0      600x420     animais-mortos-...-cobasi-rs.png   <<< ORIGINAL CHEIO

destaque_pequeno  269x187  animais-mortos-...-cobasi-rs-269x187.png
news_home         345x240  animais-mortos-...-cobasi-rs-345x240.png
destaque_grande   538x374  animais-mortos-...-cobasi-rs-538x374.png
```

**Todos os `td_*` devolvem o mesmo arquivo: o original.** O que muda é só o `width`/`height`
do atributo HTML. Repare no pior caso: **`td_80x60` — uma miniatura de 80 pixels — faz o
navegador baixar o PNG original inteiro** e reduzir por CSS. Os tamanhos legados, ao contrário,
devolvem arquivos derivados de verdade, com sufixo `-269x187`.

Isto é exatamente o comportamento que você descreveu: pedir um tamanho inexistente **não devolve
nada — devolve a imagem original**.

---

## 2. Medição na página real

### 2.1 Home de `bahia.ba`

44 imagens do CDN (contando `background-image`, que é como o TagDiv monta a maioria dos cards):

| | imagens | bytes | média |
|---|---|---|---|
| derivadas (`-WxH`) | 13 | 366 KB | 28 KB |
| **originais em tamanho cheio** | **30** | **4.353 KB** | **145 KB** |
| **total** | 44 | **4.719 KB (4,6 MB)** | |

**70% das imagens são o original, e elas são 92,2% dos bytes.** Uma imagem original pesa em
média **5,2× mais** que uma derivada.

Os maiores arquivos servidos na home, todos originais:

```
665 KB  55399459158_9b842a3f48_k.jpg
460 KB  outdoor-de-jeronimo.png
397 KB  SSP.png
329 KB  tarzia-e-lucas-arcanjo-vitoria.png
271 KB  footer-bg-azul.png
```

### 2.2 O contrafactual — o que o `bahia_refactor` serviria nas mesmas imagens

Testei, para cada original da home, se existe no CDN a derivada legada correspondente
(`-345x240`, `-269x187`, `-538x374`, `-1076x560`) e quanto ela pesa.

- **11 das 30** originais têm equivalente legado real
- as outras 19 não têm — são a faixa 2017–2022, que já era servida cheia antes

| | bytes |
|---|---|
| servido **hoje** (original cheio) | **2.250 KB** |
| equivalente que o `bahia_refactor` servia | **865 KB** |
| **fator** | **2,6× mais pesado hoje** |

Este 2,6× é **conservador**: para cada imagem usei a *maior* derivada legada disponível
(`destaque_gigante` quando existia). Com a que o card realmente usava (`news_home`, 345×240),
o fator seria bem maior.

### 2.3 Página de artigo

`/municipios/vice-prefeito-de-porto-seguro-vira-reu.../` — 12 imagens do CDN, 672 KB,
sendo **80% dos bytes em originais**. Mesmo padrão, escala menor.

---

## 3. O que mais achei, e não estava na hipótese

**`loading="lazy"` não existe em nenhuma das páginas.** Zero ocorrências, nem na home nem no
artigo. O WordPress injeta isso por padrão desde a 5.5.

A causa é estrutural: **21 das 44 imagens da home são `background-image` em atributo `style`
inline**, que é como os módulos do TagDiv montam a miniatura do card. Imagem de fundo em CSS
**não aceita lazy nativo** — ela é buscada assim que o elemento entra no render. Existe um
mecanismo próprio (`td-lazy` / `data-src`, 25 ocorrências), mas o nativo está fora.

Isso ajuda a explicar por que subiu o número de imagens por página, e não só o peso.

---

## 4. A decomposição honesta do aumento

Aqui está a parte que corrige o enquadramento. Comparei **requisições** e **bytes** no CloudFront,
que serve exclusivamente `wp-content/uploads` — ou seja, **só imagem**, nada de CSS/JS do tema.

```
                    requisições/dia    GB/dia   KB por requisição
ANTES (até 17/08)          938.875      66,2         73,9
DEPOIS (20/08+)          2.505.369     233,8         97,8

fator requisições : 2,67x
fator bytes       : 3,53x
fator PESO/req    : 1,32x
```

**Se fosse só peso de imagem, as requisições teriam ficado estáveis. Elas quase triplicaram.**

Cruzando com o ALB de produção (que conta requisições de página) no mesmo período, usando a
virada como experimento natural:

```
                         páginas/dia    imagens/dia    imagens por página
ANTES da virada (11-17/08)   459.709      1.110.745            2,4
DEPOIS (21/08+)              766.279      2.288.272            3,0

fator páginas servidas   : 1,67x
fator imagens por página : 1,24x
fator peso por imagem    : 1,32x
```

**1,67 × 1,24 × 1,32 ≈ 2,7×** — consistente com o salto observado.

| causa | fator | é defeito? |
|---|---|---|
| **mais páginas servidas** | **1,67×** | Não. É tráfego real a mais. |
| mais imagens por página | 1,24× | Parcialmente — layout do Newspaper + lazy nativo ausente |
| **peso por imagem** | **1,32×** | **Sim — é a sua hipótese, confirmada** |

**Conclusão:** a regressão de imagem é real, está provada e vale corrigir. Mas ela responde por
~1,32× de um total de ~3,5×. **O maior componente isolado é o site estar entregando 67% mais
requisições do que entregava antes da virada** — o que é notícia boa, não bug, e precisa ser
levado em conta antes de tratar o custo como desperdício.

Uma ressalva de método: o `RequestCount` do ALB conta toda requisição HTTP, não só pageview,
e a série começa em 09/08 (quando o EKS passou a servir). A janela pré-virada disponível é curta,
de 11 a 17/08. O sinal é forte e consistente, mas a confirmação de audiência real deve vir do
GA4, não daqui.

---

## 5. Caminhos possíveis — sem recomendação fechada, como pedido

### A. Mapear os `td_*` para as derivadas que já existem

Um filtro em `image_downsize` que, ao receber um tamanho inexistente, escolhe a **menor derivada
existente com dimensão ≥ a pedida**, em vez de cair no original. Genérico, não uma tabela fixa.

- **Alcance:** os 51,7% (~79.500) que têm tamanhos legados — que é exatamente a faixa 2023–2026,
  a mais visitada
- **Custo:** um mu-plugin. Sem migração de dados, sem tocar no S3, reversível removendo o arquivo
- **Não resolve:** os 48,3% sem derivada nenhuma — não há para onde mapear
- **Efeito estimado:** derruba quase todo o fator 1,32×, algo em torno de **50 a 76 USD/mês** no
  CloudFront, mais ganho de velocidade
- **Ressalva:** `td_324x400` é retrato e as legadas são paisagem; vai haver diferença de
  enquadramento em alguns blocos

### B. Regenerar derivadas para o acervo

- **Alcance:** os 100%, inclusive a faixa 2017–2022 que **nunca** teve derivada. Levaria o
  tráfego **abaixo** do patamar pré-virada
- **Custo:** alto e concreto — 153.842 anexos × ~10 tamanhos ≈ **1,5 milhão de objetos novos**
  no S3, cada imagem precisa ser baixada do S3, redimensionada e reenviada. Dias de
  processamento, custo de PUT e de armazenamento, e pressão nos pods se rodar no cluster
- **Observação:** é o único caminho que resolve os 48,3%, que hoje são a maior fatia dos bytes

### C. Redimensionar na borda

CloudFront + Lambda@Edge (ou S3 Object Lambda) gerando o tamanho sob demanda e cacheando.

- **Alcance:** 100%, sem migração
- **Custo:** infraestrutura nova para manter, e invocação por miss
- É o caminho mais robusto e o mais caro em complexidade

### D. Devolver o lazy nativo

Independente dos outros três, e barato. Não reduz o peso por imagem, mas reduz **quantas** são
buscadas — ataca o fator 1,24×.

---

## 6. Para decidir, na ordem

1. **A regressão está provada.** O que falta decidir é o alcance da correção, não se existe.
2. **A pergunta que mais muda a conta:** os 48,3% sem derivada nenhuma são um problema
   pré-existente que ninguém tinha medido. Corrigi-los é o maior ganho disponível — e o mais caro.
3. **Confirme o crescimento de audiência no GA4** antes de tratar 1,67× como desperdício. Se for
   público real, parte desse custo é o preço do sucesso, e a discussão vira capacidade, não limpeza.
4. O caminho A é o de melhor relação esforço/retorno para começar, e não impede B nem C depois.
