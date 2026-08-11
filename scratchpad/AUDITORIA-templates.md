# Auditoria dos templates — o que realmente renderiza cada página

**Levantado em:** 11/08/2026 (rodada 5), em hml.bahia.ba
**Método:** filtro em `template_include` com prioridade 9999, devolvendo o caminho final em
um cabeçalho HTTP, requisição por requisição. Não é dedução a partir do nome do template:
é o arquivo que o WordPress de fato incluiu.

## Por que esta auditoria existe

Três briefings seguidos apontaram "template que não renderiza", e uma dessas vezes custou
trabalho perdido: a remoção do Pinterest foi feita no template errado e "voltou" na rodada
seguinte. A causa raiz é conhecida e não vai mudar:

> `td-composer` registra um `template_include` com **prioridade 99**. Ele roda depois de
> praticamente todo mundo e **desvia** vários contextos para arquivos PHP dentro de
> `plugins/td-composer/legacy/Newspaper/`. Quando isso acontece, o `tdb_templates`
> correspondente — mesmo publicado, mesmo apontado nas opções do tema — **nunca é lido**.

Consequência prática: **um template publicado e configurado pode ser código morto.** Antes de
editar qualquer coisa, consulte a tabela abaixo.

---

## 1. O que renderiza cada contexto

| Contexto | URL testada | Arquivo que renderizou | Fonte |
|----------|-------------|------------------------|-------|
| **Home** | `/` | `plugins/td-composer/legacy/Newspaper/page.php` | PHP do plugin — o layout vem do `post_content` da página **547432** |
| **Archive de editoria** | `/politica/` | `themes/Newspaper/archive.php` | **PHP do TEMA** (usa `loop-archive.php`) |
| **Post individual** | `/politica/<slug>/` | `plugins/td-composer/legacy/Newspaper/single.php` | PHP do plugin |
| **Página comum** | `/quem-somos/`, `/ultimas-noticias/` | `plugins/td-composer/legacy/Newspaper/page.php` | PHP do plugin |
| **Autor** | `/author/neison-cerqueira/` | `plugins/td-cloud-library/wp_templates/tdb_view_author.php` | **tdb_template 547422** |
| **Busca** | `/?s=bahia` | `plugins/td-cloud-library/wp_templates/tdb_view_search.php` | **tdb_template 547428** |
| **404** | `/nao-existe-abc/` | `plugins/td-cloud-library/wp_templates/tdb_view_404.php` | **tdb_template 547430** |
| **Cabeçalho** | (todas) | bloco do Cloud Library | **tdb_template 547414** |
| **Rodapé** | (todas) | bloco do Cloud Library | **tdb_template 547416** |
| **Taxonomia (categoria)** | `/categoria/dia-a-dia/` | `tdb_view_404.php` — **é 404** | ver 3.1 |
| **Taxonomia (tag)** | `/tag/pmdb/` | `tdb_view_404.php` — **é 404** | ver 3.1 |
| **Arquivo por data** | `/2026/07/` | `tdb_view_404.php` — **é 404** | ver 3.2 |

> Repare na assimetria que mais confunde: **archive de editoria vem do TEMA**
> (`themes/Newspaper/archive.php`), enquanto **post individual vem do PLUGIN**
> (`td-composer/legacy/.../single.php`). São pastas diferentes. Editar `single.php` do tema
> não tem efeito nenhum.

---

## 2. Inventário completo dos `tdb_templates`

Treze templates publicados. **Cinco renderizam. Oito são código morto.**

| ID | Título | Apontado por | Renderiza? | Veredito |
|----|--------|--------------|-----------|----------|
| 547414 | Header Template - Magazine PRO | `td_011[tdb_header_template]` | **Sim** | **VIVO** |
| 547416 | Footer - Magazine PRO | `td_011[tdb_footer_template]` | **Sim** | **VIVO** |
| 547422 | Author Template - Magazine PRO | `td_011[tdb_author_template]` | **Sim** | **VIVO** |
| 547428 | Search Template - Magazine PRO | `td_011[tdb_search_template]` | **Sim** | **VIVO** |
| 547430 | 404 Template - Magazine PRO | `td_011[tdb_404_template]` | **Sim** | **VIVO** |
| 547418 | Category Template - Magazine PRO | `td_011[tdb_category_template]` | Não | **MORTO** — archive vem do tema |
| 547420 | Single Post Template - Magazine PRO | `td_011[td_default_site_post_template]` | Não | **MORTO** — single vem do plugin |
| 547424 | Tag Template - Magazine PRO | `td_011[tdb_tag_template]` | Não | **MORTO** — URL de tag é 404 |
| 547426 | Date Template - Magazine PRO | `td_011[tdb_date_template]` | Não | **MORTO** — URL de data é 404 |
| 547291 | Search Template - Default PRO | ninguém | Não | **MORTO** — sobra do demo Default PRO |
| 547297 | Single Post Template - Default PRO | ninguém | Não | **MORTO** — idem |
| 547299 | Footer Template - Default PRO | ninguém | Não | **MORTO** — confirmado em rodada anterior |
| 547301 | Header Template - Default PRO | ninguém | Não | **MORTO** — confirmado em rodada anterior |

Os quatro "Default PRO" (547291/547297/547299/547301) são resto da troca de demo feita na
rodada 3, quando o site passou para o Magazine PRO. Não são referenciados por nenhuma opção.

Os quatro "Magazine PRO" mortos (547418/547420/547424/547426) **estão apontados nas opções** —
é isso que os faz parecer vivos no painel. Eles perdem para o `template_include` de
prioridade 99 (547418, 547420) ou nunca são alcançados porque a URL não resolve
(547424, 547426).

---

## 3. Dois achados que explicam parte dos "mortos"

### 3.1 As URLs de categoria e tag das editorias respondem 404 — em produção também

`/categoria/<termo>/` e `/tag/<termo>/` devolvem **404 em homolog e em produção** (conferido
em `bahia.ba` em 11/08/2026). **É um defeito pré-existente, não introduzido por este
trabalho.**

**Causa:** `mu-plugins/bahia-editorias-cpt.php` registra, para cada uma das 18 editorias, uma
taxonomia `{slug}_cat` com `rewrite => array('slug' => 'categoria')` e uma `{slug}_tag` com
`'slug' => 'tag'`. Ou seja, **18 taxonomias disputam a mesma regra de reescrita**. Só uma
vence — a última registrada, `dende_poder`.

Comprovação: pedindo `/categoria/dia-a-dia/` (termo que existe em `politica_cat`, com 32
posts), o WordPress monta a query como:

```
matched_rule: categoria/([^/]+)/?$
query_vars:   {"dende_poder_cat":"dia-a-dia"}
is_tax=1  found=0   ->  404
```

Ele procura o termo na taxonomia errada, não acha e cai no 404.

Os args foram portados fielmente do tema antigo `bahia_refactor`, então o comportamento
provavelmente é antigo — o que a checagem em produção confirma.

**Não corrigido nesta rodada**, por estar fora do escopo (rodada de fechamento) e por ser
mudança de URL, com impacto de SEO que precisa de decisão. Registrado como pendência.

**Se for corrigir**, o caminho é dar a cada taxonomia um slug de reescrita próprio —
`politica/categoria`, `esporte/categoria` etc. — e um `flush_rewrite_rules`. Isso **cria URLs
novas**; as antigas já são 404 hoje, então não há link a preservar, mas vale conferir o
Search Console antes.

### 3.2 Arquivo por data não existe

`/2026/07/` é 404. Os CPTs de editoria são registrados sem suporte a arquivo por data, e o
`post` nativo praticamente não tem conteúdo. O template 547426 nunca é alcançado.

---

## 4. Proposta de limpeza — **não executada**

Sugestão para uma janela futura, com backup antes. Nada aqui foi aplicado.

**Fase 1 — remover o que não é referenciado por ninguém (risco baixo):**

- 547291 — Search Template - Default PRO
- 547297 — Single Post Template - Default PRO
- 547299 — Footer Template - Default PRO
- 547301 — Header Template - Default PRO

Antes de excluir, confirmar que continuam sem referência:

```sql
SELECT option_name FROM wp_options
 WHERE option_value LIKE '%547291%' OR option_value LIKE '%547297%'
    OR option_value LIKE '%547299%' OR option_value LIKE '%547301%';
```

Sugestão: mover para a lixeira (`post_status = 'trash'`) em vez de excluir, e só apagar de
vez depois de o site rodar alguns dias.

**Fase 2 — decidir sobre os quatro apontados mas mortos** (547418, 547420, 547424, 547426):

Estes **não devem ser simplesmente excluídos**, porque as opções de `td_011` apontam para
eles. Duas escolhas coerentes:

- **Manter**, aceitando que são inertes, e documentar (é o estado de hoje); ou
- **Excluir e limpar as chaves** `tdb_category_template`, `td_default_site_post_template`,
  `tdb_tag_template` e `tdb_date_template`, deixando-as vazias.

A segunda é mais limpa, mas só faz sentido depois de decidido o item 3.1 — se as URLs de
taxonomia forem consertadas, **547418 e 547424 podem voltar a ser necessários**.

**Não migrar os mortos para produção** economiza trabalho na janela. Ver
`MIGRACAO-homolog-para-prod.md`, seção 1.1.

---

## 5. Como refazer esta auditoria

O `template_include` é a única fonte confiável. Para repetir em qualquer ambiente, criar um
mu-plugin temporário:

```php
<?php /* Plugin Name: ZZ Probe temporario */
add_filter('template_include', function ($t) {
    if (!isset($_GET['bahia_probe'])) return $t;
    header('X-Bahia-Template-Include: ' . $t);
    return $t;
}, 9999);
```

E consultar:

```bash
curl -s -D- -o /dev/null "https://SEU-HOST/politica/?bahia_probe=1" | grep -i x-bahia
```

**Apagar o mu-plugin ao terminar.** A prioridade 9999 garante rodar depois do 99 do
td-composer; qualquer valor menor pode mostrar o template errado.

---

## 6. Regra para os próximos briefings

Antes de pedir (ou fazer) alteração em qualquer template:

1. Consultar a tabela da seção 1 para saber **o que renderiza aquele contexto**.
2. Se for **PHP do plugin ou do tema**, a alteração vai por **hook em mu-plugin** — nunca
   editando `plugins/`, que não é versionado e some no próximo deploy.
3. Se for **tdb_template**, a alteração é no banco e **precisa entrar no inventário de
   migração**.
4. Na dúvida, rodar o probe da seção 5. Leva um minuto e evita perder uma rodada inteira.
