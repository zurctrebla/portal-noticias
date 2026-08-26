# Archive engole 1 matéria a cada troca de página

26/08/2026. Diagnóstico. **Nada modificado — aguarda aprovação.**

## O sintoma que a redação viu

A matéria `vasco-x-vitoria-tem-ingressos-esgotados...` (id 9001382, publicada 25/08 12:12,
editoria esporte) **não aparece na listagem**, embora esteja publicada, com imagem, e responda
200 na URL direta. É a 11ª mais nova de esporte.

## Não é recência, nem esta matéria, nem esporte — é sistêmico

Cruzei todas as 15 matérias de esporte do dia 25 (banco, em ordem) contra o que o archive
`/esporte/` renderiza. **14 aparecem; só a nossa some** — e ela cai exatamente na fronteira
entre a página 1 e a 2.

Mapeando slug → posição do banco, página a página:

```
PÁGINA 1: posições  1– 10
PÁGINA 2: posições 12– 21      <- pula a 11
PÁGINA 3: posições 23– 32      <- pula a 22
PÁGINA 4: posições 34– …       <- pula a 33
```

**Cada troca de página engole exatamente uma matéria: as posições 11, 22, 33, 44…** somem da
listagem. Confirmado também em **política, salvador e municípios** — a 11ª mais nova some em
todas. Não é específico da matéria nem da editoria: é a paginação do archive.

## A causa

- **Página 1** é renderizada pela query nativa do archive (`posts_per_page` = 10, Settings).
  Mostra as posições 1–10.
- **Páginas 2+** são produzidas por `mu-plugins/bahia-scroll-infinito.php`, que injeta
  `[td_flex_block_1 page="N"]` no conteúdo (linha 188).
- O **offset do bloco** anda de **11 em 11**: page 2 começa na posição 12 (offset 11), page 3 na
  23 (offset 22), page 4 na 34 (offset 33). Mas cada página **exibe 10** posts.
- A diferença entre o passo (11) e o que se exibe (10) é a matéria perdida em cada emenda.

Ou seja: **o passo da paginação (11) não bate com a quantidade exibida (10).** O `td_flex_block_1`
conta 11 por página para calcular o offset, enquanto a página 1 nativa e o visual mostram 10.

O comentário do próprio plugin registra que a paginação foi trocada de `exclude_ids` para `paged`
("Pagina por `paged`, não mais por exclude_ids"). A emenda entre a página 1 nativa e a página 2 do
bloco é onde o off-by-one mora.

## Gravidade

Não é uma matéria só. É **uma matéria perdida a cada 10 exibidas**, em toda editoria, de forma
silenciosa — o leitor que navega o archive nunca vê as posições 11, 22, 33… A matéria não está
perdida (está no banco e abre pela URL direta); ela só nunca é listada por cair na emenda.

## Correção — a decidir, não aplicada

O conserto é **alinhar o passo com a exibição**: o `td_flex_block_1` precisa paginar de 10 em 10,
não de 11. Três caminhos possíveis, todos a validar em homolog com o mesmo cruzamento
banco × render:

1. **Igualar o `posts_per_page`/limit do `td_flex_block_1`** ao do archive (10). Se o bloco estiver
   configurado com 11 (o "1 grande + 10" do flex), é a origem do 11.
2. **Fazer a página 1 também sair pelo `[td_flex_block_1]`**, para as duas usarem a mesma contagem
   — hoje a página 1 é nativa e as demais são do bloco, e é justamente aí que elas divergem.
3. **Compensar o offset** na injeção do `bahia-scroll-infinito.php`, descontando a diferença de 1
   por página — o mais cirúrgico, mas o que mais depende do comportamento interno do bloco.

Recomendo diagnosticar primeiro o `posts_per_page` real do `td_flex_block_1` no template do
archive (caminho 1) — se ele for 11, a correção é trocar por 10 e o passo passa a bater.

Método de verificação do fix (o mesmo deste diagnóstico): render das páginas 1–4, mapa slug →
posição do banco, e conferir que **nenhuma posição some** entre as emendas.
