# Virada do tema abortada — 18/08/2026

**Estado: produção estável no `bahia_refactor`. A Fase 4 foi aplicada e revertida.**
Nada da Fase 3 se perdeu. A próxima janela começa daqui.

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
