# Caminhos A, B e D — investigação prévia

Medido em 2026-08-25. **Nada executado, nada alterado.**
Complementa `DIAGNOSTICO-IMAGENS.md`.

---

## D — lazy loading: **já funciona. Eu estava errado.**

### O que eu disse antes, e por que estava errado

Na rodada anterior reportei "`loading="lazy"` não existe em nenhuma página" e tratei isso como
defeito. A ausência é real, mas a conclusão não: **o TagDiv não usa o lazy nativo do navegador,
usa um mecanismo próprio em JS.** Olhei o atributo errado.

### O que a página realmente faz

Separando os blocos `<style>` do corpo e classificando cada referência de imagem da home:

```
IMAGENS BUSCADAS DE IMEDIATO (eager)
  background-image:url() dentro de <style> ....  6   (2 únicas, do CDN)
  background-image:url() em style= inline .....  0
  <img src=...> direto ........................  1

IMAGENS DIFERIDAS (lazy)
  <span td-thumb-css data-img-url=...> ........ 35
  <span .lazyload data-bg-image=...> .......... 12
  style inline com background-image:inherit ... 12
  <img data-src=...> .......................... 22

TOTAL de URLs únicas do CDN na página ......... 51
destas, EAGER ................................. 2
```

O card do TagDiv é assim — a URL fica em `data-img-url`, não em `style`:

```html
<span class="entry-thumb td-thumb-css" data-type="css_image"
      data-img-url="https://d1x4bjge7r9nas.cloudfront.net/.../jeronimo-acm-neto-e-mansur.jpg">
```

Há **dois** mecanismos convivendo: o `td-thumb-css`/`data-img-url` do TagDiv (35 ocorrências) e
um `.lazyload`/`data-bg-image` com `background-image:inherit` no style (12). Os dois diferem a
busca. Nenhum está quebrado.

**A medição independente confirma:** o CloudWatch mostra **3,0 imagens por requisição de página**.
Se o lazy estivesse quebrado, a home sozinha pediria 44. Os 21 `background-image` que eu contei
antes eram quase todos regra de CSS ou `background-image:inherit` — não URL a buscar.

**Sobre os 21 `background-image` inline:** a premissa da sua pergunta cai junto. Não há 21 imagens
de fundo inline sendo buscadas de imediato; há **duas**. Não é preciso saída para eles.

### O que sobra de acionável — e vale a pena

Duas imagens são buscadas de imediato **em todas as páginas** (confirmei na home e no artigo):

| arquivo | peso | cache-control |
|---|---|---|
| **`footer-bg-azul.png`** | **271 KB** | `max-age=31536000` |
| `bghd.jpg` | 75 KB | `max-age=31536000` |

**346 KB no primeiro carregamento de todo visitante novo**, e `footer-bg-azul.png` é o fundo do
rodapé — algo que só aparece depois de rolar a página inteira. É um PNG de 271 KB para um fundo
azul.

O cache de um ano segura o visitante recorrente, e o CloudFront segura a origem — então isto
**não** é um dos grandes componentes do salto de tráfego. Mas é o item de melhor relação
esforço/retorno da lista: converter para gradiente CSS, ou reexportar como PNG-8/WebP, deve
levar os 271 KB para a casa de 1 a 15 KB.

**Proposta para D:** não acrescentar mecanismo de lazy nenhum — já há dois funcionando. Tratar só
os dois assets eager, sendo o `footer-bg-azul.png` o que importa.

---

## A — mapeamento `td_*` → derivada existente

### A regra, genérica

No filtro de `image_downsize`, quando o tamanho pedido não existir no
`_wp_attachment_metadata`, escolher **a menor derivada existente que satisfaça as duas dimensões
pedidas**, e só cair no original se nenhuma satisfizer:

- tamanho com corte (`80x60`, `218x150`, `324x400`, `485x360`): exige `largura ≥ W` **e** `altura ≥ H`
- tamanho só de largura (`150x0`, `300x0`, `696x0`, `1068x0`, `1920x0`): exige apenas `largura ≥ W`
- tamanho só de altura (`0x420`): exige apenas `altura ≥ H`

Nunca escolher derivada menor que o pedido — isso viraria upscale e borrão. Sem tabela fixa:
a regra lê o que existe no anexo e continua valendo se algum dia surgirem outros tamanhos.

### O `td_324x400` — e por que o risco é menor do que parece

Proporções em jogo:

| tamanho | proporção | |
|---|---|---|
| `td_324x400` | **0,81** | **retrato** |
| `destaque_gigante` 1076×560 | 1,92 | paisagem |
| `destaque_grande` 538×374 | 1,44 | paisagem |
| `news_home` 345×240 | 1,44 | paisagem |
| `destaque_pequeno` 269×187 | 1,44 | paisagem |

Mas o que o bloco recebe **hoje** já é paisagem: o original. E a dimensão típica do acervo é
**600×420 (1,43)** — 69 de 119 anexos recentes medidos. Ou seja, o bloco já monta o retrato
cortando uma paisagem por CSS. **Trocar o original 600×420 por uma derivada 538×374 não muda a
proporção da fonte** (1,43 → 1,44); muda só o número de pixels.

O risco real não é enquadramento, é **resolução**: `td_324x400` precisa de 400px de altura, e
`destaque_grande` tem 374. Pela regra acima ele **não** seria escolhido — falha na altura — e o
anexo cairia no original, como hoje. **Para os originais de 600×420, o `td_324x400` não muda
nada.** Só ganharia se existisse `destaque_gigante` (1076×560), o que acontece apenas em anexos
com original grande.

**Onde `td_324x400` é usado:** dois blocos, ambos configurados na própria página
(`"image_size":"td_324x400"` no JSON do bloco) — um de 5 colunas
(`modules_on_row_regular: 20%`) e um com miniatura flutuando à esquerda a 30% de largura
(`image_floated: float_left`). No código, `td_module_slide.php` / `td_block_slide.php`.
São os dois blocos a olhar na validação visual.

### O que A realmente ganha, por tamanho

Para o caso mais comum do acervo (original 600×420, com tamanhos legados):

| pedido | derivada escolhida | ganho |
|---|---|---|
| `td_80x60` | `destaque_mini` 110×76 | **~184 KB → 3,6 KB** |
| `td_150x0` | `destaque_pequeno` 269×187 | grande |
| `td_218x150` | `destaque_pequeno` 269×187 | grande |
| `td_300x0` | `news_home` 345×240 | grande |
| `td_485x360` | `destaque_grande` 538×374 | grande |
| `td_324x400` | — (nenhuma tem altura ≥400) | **nenhum**, fica no original |
| `td_696x0` | — (original só tem 600 de largura) | **nenhum** |
| `td_1068x0`, `td_1920x0`, `td_0x420` | — | **nenhum** |

**5 dos 10 tamanhos melhoram** — e são justamente os pequenos, usados em card, lista e menu, que
são a maioria esmagadora das requisições. Os grandes não melhoram porque **o original já é menor
do que o tamanho pedido** — não há o que otimizar ali sem regenerar (caminho B).

### Validação proposta, antes de considerar pronto

1. Aplicar em **homolog** primeiro
2. `carga.sh` antes e depois — o script já existe em `scratchpad/carga.sh`, bate 30 URLs frias
   (home, 10 archives de editoria, 10 buscas) medindo `Threads_running` durante. Serve para
   garantir que o filtro não introduz custo de banco
3. Medir o peso da home antes e depois com a mesma metodologia desta rodada: extrair as URLs de
   `data-img-url`/`data-bg-image`/`data-src`/`url()` e somar `Content-Length`
4. **Inspeção visual dos dois blocos `td_324x400`** e dos blocos de 5 colunas, comparando
   homolog contra produção lado a lado
5. Só então produção

---

## B — o custo em dólares: **você está certo, é trivial**

Medi as derivadas `td_*` reais de 119 anexos pós-virada, que já as possuem.

```
derivadas td_* geradas por imagem : média 6,8  (min 5, max 10)
PESO das td_* por imagem          : média 261,8 KB   mediana 265,8 KB
                                    p90 365,2 KB     max 1.111,5 KB
original médio                    : 183,9 KB
```

São 6,8 e não 10 porque o WordPress só gera o tamanho quando o original é maior que ele — e o
acervo é dominado por originais de 600×420.

### Conta para os 153.842 anexos

| item | conta | valor |
|---|---|---|
| objetos novos no S3 | 153.842 × 6,8 | **~1.046.000** |
| **PUT** | 1.046.000 ÷ 1000 × US$ 0,005 | **US$ 5,23 uma vez** |
| **GET dos originais** | 153.842 ÷ 1000 × US$ 0,0004 | US$ 0,06 uma vez |
| armazenamento novo | 153.842 × 261,8 KB | **38,4 GB** |
| **custo de armazenamento** | 38,4 GB × US$ 0,023 | **US$ 0,88/mês** |
| transferência S3 → EC2 (mesma região) | — | US$ 0,00 |

**Total: ~US$ 5,30 uma vez e ~US$ 0,90/mês.** Sua estimativa de ~7 USD estava certa; a minha
descrição de "custo alto" estava errada em dólares. **O obstáculo é inteiramente operacional.**

E vale lembrar o outro lado: os 48,3% do acervo que hoje são servidos em tamanho cheio passariam
a servir derivadas de ~10 a 60 KB no lugar de originais de ~184 KB. O caminho B **se paga em
poucos dias** de CloudFront.

### O tempo, e o que impede rodar no cluster

Cada imagem exige baixar o original do S3, decodificar, redimensionar ~6,8 vezes e subir 6,8
objetos. Entre 2 e 6 segundos, adotando 3,5 s:

**153.842 × 3,5 s ≈ 150 horas de trabalho de uma linha só.**

Levantei a curva horária de 7 dias, em horário de Salvador:

```
  hora      req/h   CPU nó3
  05:00     20.273     30%   <= vale
  04:00     20.910     32%
  06:00     22.214     32%
  03:00     23.604     31%
  16:00     42.090     36%   <= pico
```

**O vale é 02h–07h, mas a razão pico/vale é só 2,1× — o site não dorme.** E o dado que decide:
**mesmo às 05h a CPU do nó fica em ~30%, que é exatamente o baseline do t3.large.** Não há folga
de CPU nem na madrugada. Rodar isso nos nós de produção queimaria crédito de burst e estrangularia
o site — é o mesmo problema já documentado no levantamento anterior.

### Como rodar sem tocar em produção

Não usar os nós do cluster. Uma máquina temporária e separada, com acesso ao S3 e ao RDS:

- **1 EC2 spot** (ex. `c6i.2xlarge`, 8 vCPU, ~US$ 0,10–0,15/h spot)
- 6 a 8 processos paralelos → **153.842 ÷ 8 × 3,5 s ≈ 19 horas de relógio**
- custo de compute: **US$ 2 a 3**
- zero impacto no cluster: só lê o banco e escreve no S3

**Custo total do caminho B: menos de US$ 10 uma vez, mais US$ 0,90/mês.**

Se preferir ainda mais conservador, um job throttled a 2 processos ao longo de duas semanas
também serve — mas aí é preciso um nó dedicado no nodegroup, com taint, senão a carga cai nos
nós que servem o site.

### Duas ressalvas antes de agendar

1. **O offload precisa entrar na conta.** Cada derivada nova tem que ir para o S3 no caminho com
   segmento de versão que o WP Offload usa (`uploads/2026/08/25092113/arquivo-269x187.jpg`) e ser
   registrada em `wp_as3cf_items`. Regenerar localmente e não sincronizar deixa o acervo pior do
   que está — arquivo em disco que o CDN não serve.
2. **`_wp_attachment_metadata` tem que ser reescrito** por anexo. São 153.842 UPDATEs em
   `wp_postmeta`. Espaçar; o RDS de produção tem 3,0 GB livres e já foi medido em 62 write IOPS
   de média.

---

## Resumo das três frentes

| | veredito |
|---|---|
| **D** | Lazy **já funciona** — dois mecanismos, nenhum quebrado. Minha leitura anterior estava errada. Sobra `footer-bg-azul.png`, 271 KB eager em toda página. |
| **A** | Regra genérica definida. Melhora **5 dos 10** tamanhos — os pequenos, que são a maioria das requisições. `td_324x400` **não muda nada** para o original típico de 600×420, então o risco de enquadramento é bem menor do que se supunha. |
| **B** | **US$ 5,30 uma vez + US$ 0,90/mês.** Você estava certo. O obstáculo é CPU, não dinheiro — e a solução é não rodar no cluster: uma spot separada resolve em ~19 h por US$ 2–3. |
