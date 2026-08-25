# O corte das imagens — as duas saídas, dimensionadas

25/08/2026. **Nada aplicado.**

---

## A descoberta: o pior corte é código NOSSO, não do tema

Varri seis tipos de página em produção — home, dois archives, busca, autor e matéria — e contei
quais tamanhos são efetivamente servidos. **Dos dez `td_*` que o Newspaper registra, só dois
aparecem:**

| tamanho | ocorrências | onde | corta um 600x420 |
|---|---|---|---|
| **`thumbnail` 150x150** | **7** | **MAIS LIDAS** | **30,0% da largura** |
| `td_218x150` | 6 | cards do TagDiv | 1,7% da altura |
| `td_696x0` | 9 | card principal, destaque | 0% (proporção natural) |
| `medium_large` 768x0 | 5 | matéria e archive | 0% |
| `medium` / `large` | 5 | matéria | 0% |

Os outros oito — `td_324x400`, `td_485x360`, `td_80x60`, `td_0x420`, `td_150x0`, `td_300x0`,
`td_1068x0`, `td_1920x0` — **não são servidos em lugar nenhum**. O `td_324x400`, que come 43% da
largura, está configurado só no mega menu, que não renderiza miniatura.

E os `300x300` que apareciam em toda página são **o favicon**, não imagem de matéria.

### O corte é DUPLO, e está em `bahia-mais-lidas.php`

```php
$thumb = get_the_post_thumbnail_url(get_the_ID(), 'thumbnail');   // 150x150 QUADRADO
```
```css
.bahia-ml-thumb{ width:56px; height:42px; }        /* a caixa e 1,333 */
.bahia-ml-thumb img{ object-fit:cover; }           /* e corta de novo */
```

Pedimos um **quadrado** (1,000) para encaixar numa **caixa 4:3** (1,333). Então:

1. o WordPress corta 600x420 → 150x150 e perde **30% da largura**;
2. o CSS cobre esse quadrado na caixa 56x42 e perde mais **25% da altura**.

Medido nos sete itens que estavam na home:

| item | original | servido | perda no WP | + perda no CSS | **área visível** |
|---|---|---|---|---|---|
| 1, 2, 5, 6, 7 | 600x420 | 150x150 | 30,0% | 25,0% | **52,5%** |
| 3 | 900x600 | 150x150 | 33,3% | 25,0% | **50,0%** |
| 4 | 1082x765 | 150x150 | 29,3% | 25,0% | **53,0%** |

**Sobra pouco mais da metade da foto.** No item 4, uma montagem com duas pessoas lado a lado,
o servido corta as bordas das duas.

---

## (a) Ajustar os tamanhos — e por que quase não é preciso

### O que realmente resolve: uma linha no nosso mu-plugin

Trocar `'thumbnail'` por **`'td_80x60'`** em `bahia-mais-lidas.php`. O `td_80x60` é 80x60 —
proporção **1,3333, exatamente a da caixa**. Efeito:

- o WordPress corta só **6,7% da largura** em vez de 30%;
- o CSS **não corta mais nada**, porque a proporção já bate;
- **vale para o acervo inteiro na hora**, porque só muda qual derivada existente é pedida.

| | corte médio ponderado |
|---|---|
| hoje | **6,9%** |
| com a MAIS LIDAS pedindo `td_80x60` | **1,8%** |

Alternativas, se quiser mais nitidez: `td_150x0` (150x105, natural) ou `td_218x150` (218x150) —
ambas cortam ~7% no total e são mais densas para tela retina, já que a caixa tem 56x42 CSS.

### Mexer nos tamanhos do tema: não vale a pena

- **`td_218x150`** corta 1,7% de um 600x420. Não é problema.
- **`td_324x400`** é retrato **por desenho do bloco** — mudá-lo para paisagem desmontaria o
  layout do mega menu. E ele não é servido hoje.
- Os outros oito não aparecem.
- **O impedimento decisivo:** mudar um `add_image_size` só afeta **uploads futuros**. As
  derivadas já geradas mantêm as dimensões que têm. Ou seja, mexer no tema **não corrige nenhuma
  imagem já publicada** — e a queixa é sobre matérias que já estão no ar.

Se um dia for preciso, a forma sem editar o tema é `add_image_size()` num mu-plugin carregado
depois do tema (sobrescreve o registro) ou o filtro `intermediate_image_sizes_advanced` (decide
o que gerar no upload). Mas para este caso não é o caminho.

---

## (b) Pedir outro export à redação: **não resolve**

Calculei o corte médio ponderado para cada proporção de export, sobre os tamanhos realmente
servidos:

| proporção | exemplo | corte médio |
|---|---|---|
| 0,81 (retrato) | 340x420 | 12,5% |
| **1,00 (quadrado)** | **420x420** | **5,8%** ← o melhor possível |
| 1,3333 | 560x420 | 7,0% |
| **1,4286 (export atual)** | **600x420** | **6,9%** |
| 1,4533 | 610x420 | 6,8% |
| 1,50 | 630x420 | 7,9% |
| 1,7778 | 747x420 | 13,0% |

**Nenhuma proporção zera o corte, e a melhor delas só ganha 1,1 ponto sobre o export atual** —
às custas de entregar foto quadrada, que serviria mal a todo o resto do site.

O motivo é simples: o único corte grande vem de um tamanho **quadrado**. Não existe proporção
retangular que satisfaça um quadrado. **Pedir outro export à redação não resolveria o problema —
e o export de 600x420 já está praticamente no ótimo.**

> Isto é o número que você queria antes de levar a conversa. A resposta é: não leve. O ajuste é
> do nosso lado.

---

## Recomendação

**Uma linha em `bahia-mais-lidas.php`.** Corte médio de 6,9% para 1,8%, valendo para todo o
acervo imediatamente, sem tocar no tema, sem pedir nada à redação e sem afetar upload.

Não aplicado — aguarda a URL e a descrição do repórter para confirmar que é disto que ele fala.
