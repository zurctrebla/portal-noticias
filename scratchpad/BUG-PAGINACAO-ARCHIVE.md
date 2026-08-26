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

---

# CAUSA CONFIRMADA (corrige a hipótese acima)

A hipótese do `td_flex_block_1` estava **errada** — aquela injeção só roda em `/ultimas-noticias`,
não nos archives de editoria. Medi a query real de `/esporte/page/2/` e o culpado é outro:

**`mu-plugins/bahia-archive-count-perf.php`.**

## posts_per_page: o real é 10, mas o plugin o infla para 11

Esse plugin existe para tirar o `SQL_CALC_FOUND_ROWS` dos archives (era a 2ª causa do 504 da
virada — contar 78 mil linhas de /politica/ só para o rodapé de paginação). A técnica: em vez de
contar, **pede um post a mais** para saber se há próxima página:

```php
$q->set('no_found_rows', true);
$q->set('posts_per_page', $por_pagina + 1);   // 10 vira 11
```

O problema é que o WordPress calcula o **offset** do `LIMIT` a partir do `posts_per_page`:

```
offset = posts_per_page × (paged − 1)
```

Com `posts_per_page = 11` e `paged = 2`, o offset vira **11**, não 10. Medido na query real:

```
is_post_type_archive: sim   posts_per_page(pos-loop): 10   found_posts: 21
página 2 devolve: 9001373 (posição 12), 9001361 (13)...  — começa na 12, não na 11
```

Passo a passo do que some:
- **Página 1**: offset 0, limit 11 → posições 1–11. O `array_pop` joga fora a 11ª (o "extra") →
  exibe 1–10. **A posição 11 foi buscada e descartada.**
- **Página 2**: offset 11 → posições 12–22, descarta a 22ª → exibe 12–21. **A posição 11 nunca é
  buscada de novo.**
- Cai na fresta. Mesma coisa em 22, 33, 44…

O `+1` inflou o `posts_per_page`, e o WordPress usa esse mesmo número para o offset — então cada
página "anda" 11, mas exibe 10, e a diferença de 1 é a matéria perdida na emenda.

## O fix — 2 linhas, a decidir e validar em homolog

O `+1` tem de esticar só o **limite**, não o **offset**. Fixar o offset explicitamente com o
per_page REAL resolve — quando `offset` é setado, o WordPress o usa literal e não recalcula de
`paged × posts_per_page`:

```php
$q->set('no_found_rows', true);
$q->set('posts_per_page', $por_pagina + 1);
$pagina = max(1, (int) $q->get('paged'));
$q->set('offset', ($pagina - 1) * $por_pagina);   // <<< a linha que falta: offset com o per_page real
$q->set(BAHIA_ACP_FLAG, $por_pagina);
```

Efeito:
- Página 1: offset 0, limit 11 → 1–11, descarta 11 → exibe 1–10.
- Página 2: offset **10**, limit 11 → **11**–21, descarta 21 → exibe 11–20 (a posição 11 volta).
- Página 3: offset 20 → 21–31 → exibe 21–30. Sem buracos.

A lógica de `array_pop`/`found_posts`/`max_num_pages` do `the_posts` continua valendo (limit 11:
11 posts quando há próxima, ≤10 na última). E `found_posts`/`max_num_pages` já são preenchidos à
mão pelo plugin, então os links de paginação não dependem do offset nativo.

**Validação (a mesma deste diagnóstico):** render das páginas 1–4, mapa slug → posição do banco,
conferir que nenhuma posição some. Fazer em homolog antes de produção.
