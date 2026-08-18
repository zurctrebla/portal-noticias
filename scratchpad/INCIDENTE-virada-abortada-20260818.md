# Virada do tema abortada — 18/08/2026

**Estado: produção estável no `bahia_refactor`. A Fase 4 foi aplicada e revertida.**
Nada da Fase 3 se perdeu. A próxima janela começa daqui.

> **A reversão não devolveu produção ao estado anterior — e ninguém percebeu por 3 horas.**
> A foto sumiu de **todas** as matérias. Ver a seção 7: é a lição mais cara desta janela, e não
> tem nada a ver com desempenho.

---

## 1. O que aconteceu, em ordem

| Marco | UTC |
|---|---|
| `.maintenance` em todos os pods | 07:56:28 |
| Transação da virada — `COMMIT` em **30 ms** | |
| `flush_rewrite_rules` (631 → 678 regras) e purge dos 3 pods | |
| Saída da manutenção — **2m32s de indisponibilidade real** | 07:59:00 |
| Reversão, por saturação do banco | 08:27:52 → 08:28:31 (**39 s**) |

A virada em si funcionou. **Zero 404**: as 23 editorias resolveram para o post certo, com a URL
inalterada, verificado pelas rewrite rules dentro do pod. O acervo nunca esteve em risco.

O que derrubou foi **desempenho**: 504 intermitente, inclusive na home, com CPU dos pods em 31%
do alvo — ou seja, esperando o banco, não processando.

---

## 2. A causa — e por que o diagnóstico inicial estava errado

O `SHOW FULL PROCESSLIST` durante o incidente mostrou 32 consultas simultâneas de
`SELECT SQL_CALC_FOUND_ROWS`, de 7 a 9 s cada, com `Threads_running` em 33.

A primeira leitura foi que os blocos paginados do tagDiv (`ajax_pagination`, o "Ver mais" e o
scroll infinito) escapavam do `bahia-td-query-perf.php`, que só desliga o count quando o bloco
**não** pagina. **Essa hipótese foi testada e REPROVADA** — ver seção 3.

Capturando o SQL inteiro, a consulta é:

```sql
SELECT SQL_CALC_FOUND_ROWS wp_posts.ID FROM wp_posts WHERE 1=1
 AND ((post_type='artigo'   AND (post_status='publish' OR post_status='acf-disabled'))
   OR (post_type='attachment' AND ...)
   ... 29 post types ...)
 ORDER BY wp_posts.post_date DESC LIMIT 0, 10
```

O `post_status='acf-disabled'` denuncia: **não é consulta de bloco tagDiv.** O backtrace deu as
duas origens reais:

### Origem A — a consulta principal da própria página

```
index.php -> wp() -> WP->main -> WP->query_posts -> WP_Query->get_posts
```

É a *main query* do archive, que paga `SQL_CALC_FOUND_ROWS` para contar as linhas publicadas da
editoria (78.170 em `politica`) só para alimentar a paginação — um total que não é exibido em
lugar nenhum.

> **CORRIGIDO em 18/08/2026.** A primeira versão desta seção afirmava que a main query estava
> varrendo **os 29 post types** e mandava procurar um `pre_get_posts` que expandisse o
> `post_type`. **Isso está errado, e a correção importa.**
>
> Medido depois, com sondas em cada prioridade de `pre_get_posts`: o `post_type` da main query
> entra `'politica'` e **sai `'politica'`** — nenhum hook o expande. E o rastreio do SQL confirma
> `tipos=1`.
>
> A varredura dos 29 post types era **a consulta do rodapé** (origem B). Eu havia conflado dois
> backtraces distintos do mesmo log e atribuído à main query um sintoma que era do rodapé.
> Quem seguisse esta seção como escrita passaria a janela seguinte caçando um `pre_get_posts`
> que não existe.

### Origem B — o rodapé, em toda página

```
tdm_block_inline_text->render -> td_util::parse_footer_texts
  -> td_util::get_the_privacy_policy_link -> new WP_Query (td_util.php:4894)
```

O bloco de texto do rodapé monta uma `WP_Query` **sem restrição de post_type** só para descobrir
o link da política de privacidade — e paga `SQL_CALC_FOUND_ROWS` sobre 271 mil linhas **em cada
render de cada página**. É a origem mais barata de matar: o link pode vir de
`get_option('wp_page_for_privacy_policy')` ou de um filtro, sem consulta nenhuma.

---

## 3. O teste de carga — o que faltou em todas as validações anteriores

Homolog vinha sendo validado com **uma requisição por vez, um pod, cache quente**. Foi por isso
que onze rodadas não viram o problema: ele só aparece com concorrência e cache frio.

O teste que passa a valer (`scratchpad/carga.sh`): 30 requisições **simultâneas** em URLs
**frias** (cache-buster próprio), misturando home, 10 archives de editoria e 10 buscas, medindo
`Threads_running` e contando `SQL_CALC_FOUND_ROWS` no processlist **durante**.

### Os números

| | ANTES | DEPOIS da hipótese tagDiv |
|---|---|---|
| Respostas | 30× 200 | 30× 200 |
| Mediana | **27,69 s** | **26,71 s** |
| p90 | 35,11 s | 34,02 s |
| Máximo | 36,05 s | 34,02 s |
| Acima de 5 s | **30 de 30** | **30 de 30** |
| `Threads_running` pico | **21** | **22** |
| `SQL_CALC_FOUND_ROWS` pico | **19** | **19** |

**A correção não mudou nada** — é a prova de que a hipótese estava errada, e a razão de ela ter
sido revertida em vez de commitada. Reproduzir o problema em homolog, porém, funcionou: os
números de "antes" são o mesmo fenômeno de produção, em escala menor.

### Critério de aprovação para a próxima tentativa

- **Zero** ocorrências de `SQL_CALC_FOUND_ROWS` no processlist durante o teste
- `Threads_running` **abaixo de 10** no pico
- **Nenhuma** resposta acima de 5 s
- "Ver mais" e scroll infinito entregando a página seguinte, sem repetir item e sem item a mais

---

## 4. O que foi feito — TUDO RESOLVIDO em 18/08/2026, commit `49ee6cf6`

Quatro consultas, não duas. As duas do diagnóstico original mais duas que só apareceram depois
que a maior saiu da frente:

| # | Onde | mu-plugin |
|---|---|---|
| 1 | rodapé (`get_the_privacy_policy_link`) | `bahia-privacy-link-perf.php` (novo) |
| 2 | main query do archive de editoria | `bahia-archive-count-perf.php` (novo) |
| 3 | blocos paginados do tagDiv | `bahia-td-query-perf.php` (estendido) |
| 4 | endpoint AJAX do "Ver mais" | `bahia-scroll-infinito.php` |

Medido com `carga.sh`, 30 requisições simultâneas em URLs frias:

| | mediana | >5s | `Threads_running` | `SQL_CALC` |
|---|---|---|---|---|
| base | 28,56 s | 30/30 | 22 | 19 |
| + rodapé | 17,21 s | 30/30 | 12 | 10 |
| + archive | 12,57 s | 29/30 | 8 | 6 |
| **+ blocos e AJAX** | 17,84 s | 30/30 | **3** | **0** |

Com 8 simultâneas — a fatia que **um** pod de produção veria de 30 com 4 pods —: **0 de 8 acima
de 5 s**, máximo 4,35 s, `Threads_running` 2.

> **A ordem em que foram encontradas é a lição.** A correção nº 3 foi tentada primeiro, medida
> como inútil e revertida. Ela estava certa: com a consulta do rodapé ainda no lugar, era grande
> demais e escondia as dos blocos. Uma medição que não melhora não invalida a hipótese quando
> existe uma causa maior no mesmo caminho — invalida a *ordem* em que se está atacando.

> **Se `no_found_rows` for aplicado a blocos paginados numa próxima tentativa**, lembrar que o
> `td_block.php:3149-3181` lê `found_posts` **e** `max_num_pages`, injeta os dois em JS e esconde
> o botão quando `1 >= ceil((found_posts - offset)/limit)`; o `bahia-scroll-infinito.php` usa os
> mesmos dois no `has_more`. Zerá-los mata o botão e para o scroll — é preciso **preencher os
> dois** a partir de um post extra (`posts_per_page + 1`), e descartar o extra da saída.

---

## 5. O que a Fase 4 provou que funciona, e não precisa ser refeito

- A transação inteira, em **30 ms**, com `td_011` escrita **antes** da troca de tema — o tema não
  cria a opção sozinho, e `tagdiv_options::get()` cai no padrão para chave ausente.
- `wpseo_titles` por união: 991 → 1.115 chaves, **zero perdidas**.
- A reversão: `td_011` e `theme_mods_Newspaper` **apagadas** (não existiam antes — é `delete`, não
  restauração), 3 plugins desativados, tema de volta. 39 segundos.
- O `.maintenance` com `WP_INSTALLING` no script, e o purge **antes** de liberar.

## 6. Resíduos limpos

- Categoria **"Featured"** criada pelo tagDiv em `term_id 9100005` durante a janela — apagada
  (`count=0`, sem relações, não era a padrão). A faixa voltou aos 3 termos.
- Nenhum pod ficou com `.maintenance`. HPA de volta a `min=2 / max=5`.

> **Erro de execução a não repetir:** o HPA foi congelado em 3 quando havia **5** réplicas, o que
> forçou terminação, e depois em 5, o que criou dois pods **sem** `.maintenance`. A varredura os
> pegou, mas por sorte. **Ler o número de réplicas ANTES e congelar nele.**

---

## 7. O resíduo que a checagem de reversão não pegou: a foto de todas as matérias

Relatado pelos repórteres em 18/08, por volta das 10:00 UTC, e confirmado: **nenhuma matéria
exibia foto**, em desktop e em celular, publicação nova e acervo antigo.

### O que aconteceu

O mu-plugin `bahia-remove-dup-featured.php` remove o primeiro `[caption]` do corpo do post
quando ele aponta para o mesmo anexo da imagem destacada. Ele existe porque o **Newspaper**
redesenha a destacada no topo, por fora do conteúdo — sem ele a foto sairia duas vezes.

Só que `bahia_refactor/single_web.php` e `single_mobile.php` **não imprimem a destacada em lugar
nenhum**. No tema antigo, a foto dentro do conteúdo é a única que existe. O filtro tirava, e nada
repunha.

Pegou 100% do acervo porque a premissa se confirma sempre: as matérias começam com
`[caption]<img class="wp-image-{ID}">` e esse `{ID}` é justamente o `_thumbnail_id`, que a ponte
`acf-imagem-featured.php` grava a partir do campo ACF `imagem`. O crédito do fotógrafo, que vive
na legenda, ia junto.

### A janela

Os dois mu-plugins chegaram à `main` **nesta janela**, no merge `071af82c` — os commits são de
29/07 e 04/08, mas viveram em `staging` até aqui. Logo:

| | |
|---|---|
| Defeito começa | **07:19 UTC**, rollout da revisão 35 (fase 2) |
| Suspenso | 07:56 (`.maintenance`) → 08:28, com o **Newspaper** ativo, quando o filtro estava certo |
| Volta | **08:28 UTC**, na reversão para o `bahia_refactor` |
| Termina | **11:13 UTC**, revisão 39 |

Ou seja: **a fase 2 já tinha quebrado a foto, antes de a virada começar.** A reversão da fase 4
devolveu o tema, mas não podia devolver o código — e recolocou o site exatamente no estado
quebrado. Foi por isso que passou por toda a checagem de reversão: ela comparou o depois com o
antes *da fase 4*, e o defeito era anterior a ele.

### A lição, que é generalizável

**Reverter o tema não reverte o código.** A reversão desativou os 3 plugins tagDiv e apagou
`td_011` e `theme_mods_Newspaper` — mas **mu-plugin não tem chave de liga/desliga**: está no
disco, carrega sempre, e não pergunta qual tema está ativo. Todo mu-plugin escrito assumindo o
Newspaper é uma bomba armada enquanto o `bahia_refactor` estiver no ar.

Varredura feita depois do incidente, nos 51 arquivos de `mu-plugins/`: **nenhum** consultava o
tema ativo — o corrigido é hoje o único que consulta. Os
que mexem em saída de conteúdo são `bahia-remove-dup-featured.php` (corrigido),
`bahia-home-destaques.php` e `bahia-scroll-infinito.php` (procuram markup do tagDiv, não acham,
saem sem efeito) e `bahia-limites-texto.php` — este **age** no tema antigo, cortando títulos em 70
nas listagens, e **fica assim por decisão editorial de 18/08**, registrada no próprio arquivo.

### O que passa a valer

1. **Depois de qualquer reversão de tema, abrir uma matéria e um archive do tema antigo** e
   comparar com uma captura anterior à janela. A checagem de 18/08 verificou tema, opções,
   plugins, rewrite rules, 404 e desempenho — e nada disso olha para o que o leitor vê.
2. **Mu-plugin escrito para o Newspaper nasce com guarda de tema**, como a
   `bahia_rdf_tema_mostra_destacada()`. Quem depende do tema tem de dizer isso no código, não na
   descrição do arquivo — lá estava escrito, e não impediu nada.
3. **A fase 2 precisa da mesma prova que a fase 4.** Ela põe código novo em produção com o tema
   antigo servindo: é uma mudança de comportamento, não uma preparação inerte.

### Correção

Guarda de tema no `bahia-remove-dup-featured.php` (commit `8187d2f5`, merge `cce23200`,
revisão 39). Conferido em produção: 10 matérias de `/politica/` mais 1 de cada uma de outras 8
editorias — 18 matérias, cada uma em desktop e em celular, **36 de 36 com a foto de volta**.
Inclui um post da faixa nova de IDs (9000207), então o conserto não depende da origem do post.
O filtro volta a agir sozinho quando o Newspaper for ativado, sem ninguém precisar lembrar.
