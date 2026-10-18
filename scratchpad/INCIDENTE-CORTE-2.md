# "Imagens saindo cortadas" — segundo relato

25/08/2026, fim do dia. **Produção restaurada.** Diagnóstico não fecha sozinho — falta um dado
da redação, no fim.

## O que foi feito, primeiro

Revertido o filtro `bahia-image-size-fallback.php` **antes de investigar**, como combinado.
Commit `cc8d4409`, imagem `prod-cc8d4409`, arquivo ausente do pod, cache purgado nos pods.

## O filtro NÃO era a causa — e isto está provado

Peguei uma matéria publicada **hoje** em produção e comparei o que era servido com o filtro ativo
contra o que seria servido sem ele, no mesmo pod:

```
post 9001483 — "Confira os compromissos de ACM Neto, Jerônimo e Mansur..."
imagem 9000783 — original 600x420 (1,4286)
sizes gerados: medium, thumbnail, td_80x60, td_150x0, td_218x150, td_300x0, td_324x400, td_485x360

PEDIDO         COM filtro   SEM filtro   mudou?
td_218x150     218x150      218x150      -
td_324x400     324x400      324x400      -
td_485x360     485x360      485x360      -
td_696x0       600x420      600x420      -
td_1068x0      600x420      600x420      -
medium_large   600x420      600x420      -
large          600x420      600x420      -
```

**Nada muda.** O motivo é estrutural: num upload 600x420 de hoje, **todos os `td_*` pequenos são
gerados no upload**, então o filtro — que só age quando o tamanho pedido NÃO existe — nunca
dispara. E os tamanhos maiores que o original devolvem o original de qualquer jeito.

Sua hipótese sobre o crop era boa e eu confirmei a premissa dela — os tamanhos legados **foram
mesmo registrados com corte**:

```php
$pos_center = array('center', 'center');
add_image_size('destaque_grande',  538, 374, $pos_center);
add_image_size('destaque_pequeno', 269, 187, $pos_center);
add_image_size('news_home',        345, 240, $pos_center);
```

Mas ela não se aplica ao caso de hoje, porque **um anexo de hoje não tem tamanho legado nenhum**
para o filtro escolher. Os `destaque_*` só existem no acervo anterior à virada.

## O que realmente corta um 600x420

Calculado sobre o formato de export que a redação usa:

| tamanho | alvo | proporção | corta |
|---|---|---|---|
| `td_324x400` | 324x400 | 0,8100 | **43,3% da largura** |
| `thumbnail` | 150x150 | 1,0000 | **30,0% da largura** |
| `td_80x60` | 80x60 | 1,3333 | 6,7% da largura |
| `td_485x360` | 485x360 | 1,3472 | 5,7% da largura |
| `td_218x150` | 218x150 | 1,4533 | 1,7% da altura |
| **`news_home` (tema ANTIGO)** | 345x240 | 1,4375 | **0,6% da altura** |
| **`destaque_pequeno` (ANTIGO)** | 269x187 | 1,4385 | **0,7% da altura** |
| **`destaque_grande` (ANTIGO)** | 538x374 | 1,4385 | **0,7% da altura** |

**Aqui está a diferença que a redação sentiu.** O tema antigo tinha todos os cortes em ~1,44,
praticamente a proporção do export 600x420 (1,4286) — a imagem saía inteira, perdendo meio por
cento. O Newspaper trouxe um tamanho **retrato** (`td_324x400`) e um **quadrado** (`thumbnail`),
que comem 43% e 30% da largura.

**Isso é consequência da virada de 19/08, não de nada que subiu ontem.**

## O que o site serve hoje

```
home     : 150x150 (8x), 218x150 (6x), 696x464/492/423, 300x300
archive  : 300x300, 768x512/542/432, 150x150
```

As imagens grandes — card principal, artigo — saem em `696x*` e `768x*`, que preservam a
proporção e **não cortam**. Quem corta de verdade é o `150x150`, e são oito na home: é a lista
**MAIS LIDAS** da barra lateral.

## O que foi descartado

- **plugin de WebP em produção**: não está — nem na `main`, nem no pod
- **outro deploy**: os 8 deploys das últimas 48h são meus, e nenhum toca tema ou plugin
- **alteração de tema ou plugin desde a virada**: nenhuma além do filtro já revertido

## O que falta, e é da redação

O diagnóstico explica um corte estrutural que existe **desde 19/08**, mas o relato é de **hoje**.
Ou a redação só agora notou, ou há um caso que eu não reproduzi. Para fechar, preciso de:

1. **A URL de uma matéria** em que ele vê o corte
2. **Onde** aparece cortada — card da home, listagem de editoria, a própria matéria, MAIS LIDAS,
   ou a miniatura no painel ao publicar
3. Se a imagem original dele é **600x420 mesmo**, ou se nesse caso era outra

Com isso eu fecho em minutos. Sem isso, o mais provável é que seja o corte estrutural acima — e
a correção seria ajustar os tamanhos que o tema registra para casar com o export 600x420, não
mexer em filtro.
