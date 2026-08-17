# Inventário publicitário — onde cada grupo do AdRotate aparece

**Levantado em:** 11/08/2026 (rodada 8)
**Ambiente de trabalho:** hml.bahia.ba — mas **as posições foram conferidas em produção**
(`bahia.ba`), lendo o HTML servido, não só o código.

> **Renumeração de IDs — 16/08/2026.** Os registros nascidos em homolog (templates, páginas,
> anexos e itens de menu) foram movidos para a faixa **9.000.001+** pela fórmula
> `novo = 9.000.000 + (antigo − 547.290)`, para não colidirem com os IDs que produção passou a
> usar desde o retrato de 28/07. **Os IDs neste documento já estão atualizados.** O mapa
> completo antigo→novo está na tabela `wp_bahia_renum_map` (117 linhas), que **não deve ser
> apagada**. Plano e registro da operação em `RENUMERACAO-homolog.md`.

---

## 0. Resumo em uma tela

O tema novo (Newspaper) renderizava **um único espaço publicitário** de um total de sete com
inventário. Pior: renderizava o grupo **errado** — servia inventário de HOME em páginas
internas e de municípios.

Esta rodada transportou as posições do tema antigo. Resultado:

| Grupo | Nome | Medida | Onde aparece agora | Antes desta rodada |
|-------|------|--------|--------------------|--------------------|
| 1 | Home - Super leader board | 970x90 | Topo da **home** | não renderizava |
| 3 | Home - Leader Board 2 | 728x90 | **nenhum lugar** — ver §4 | topo de **todas** as páginas |
| 5 | Home - Retângulo | 300x250 | **nenhum lugar** — ver §4 | não renderizava |
| 8 | Square-TopoINTERNA | 300x250 | **Topo da coluna lateral** das internas | não renderizava |
| 9 | INTERNA-Interna | 300x250 | **Fim da coluna lateral** das internas | não renderizava |
| 12 | Internas - Leaderboard | 970x90 | Topo das **internas** | não renderizava |
| 14 | Leaderboard Municipios | 728x90 | Topo de **municípios** | não renderizava |

**De 1 espaço passou a 6.** Dois grupos (3 e 5) continuam sem posição, e isso é uma decisão
comercial pendente — não um esquecimento. Ver §4.

Os outros 11 grupos do AdRotate (2, 4, 6, 7, 10, 11, 13, 15, 16, 17, 18) estão **zerados**:
nenhum anúncio cadastrado, ou nenhum ativo. Renderizariam espaço em branco. Ficaram de fora
de propósito. **Todos os formatos de mobile estão nesse grupo** — ver §6.

---

## 1. Qual tema é o de produção

**Produção roda `themes/bahia_refactor/`.** Confirmado lendo o HTML de `bahia.ba`, que
carrega os assets de `wp-content/themes/bahia_refactor/`.

O diagnóstico anterior citava `themes/bahia_social/`. **Os dois existem no repositório e os
dois têm chamadas de anúncio**, quase idênticas — o `bahia_social` é a geração anterior do
mesmo tema. As diferenças que importam:

| | `bahia_refactor` (produção) | `bahia_social` (inativo) |
|---|---|---|
| Bloco de anúncio no topo | `header.php:200` e `header.php:400` (duplicado: versão fixa e versão normal) | `header.php:378` |
| Retângulos da coluna lateral | `sidebar.php:18` e `sidebar.php:89` | `sidebar.php:18` e `sidebar.php:86` |
| Grupo 3 | **não aparece em lugar nenhum** | `index.php:242`, **comentado** (`<//?php`) |
| Grupo 17 (rodapé) | `footer.php:90` — grupo zerado | não existe |
| Grupo 18 (pop-up) | `functions.php:1715` — grupo zerado | não existe |

**Para efeito de transporte, o que vale é o `bahia_refactor`.** O `bahia_social` foi
consultado apenas para explicar a origem do grupo 3.

---

## 2. O achado principal: o inventário estava indo para o contexto errado

Os nomes dos grupos separam por contexto — 1 e 3 são de HOME, 12 é de INTERNAS, 14 é de
MUNICÍPIOS. **O tema antigo respeita essa separação.** `themes/bahia_refactor/header.php`,
linhas 200-207:

```php
<?php if(is_home()): ?>
    <div class="publicidade-leaderboardtop"><?php echo adrotate_group(1); ?></div>
    <div class="publicidade-minibannertop"><?php echo adrotate_group(2); ?></div>
<?php elseif(get_query_var('post_type') == 'municipios') : ?>
    <div class="publicidade-leaderboardtop"><?php echo adrotate_group(14); ?></div>
<?php else : ?>
    <div class="publicidade-leaderboardtop"><?php echo adrotate_group(12); ?></div>
    <div class="publicidade-minibannertop"><?php echo adrotate_group(13); ?></div>
<?php endif; ?>
```

Conferido no HTML servido por **produção**, e as três entregas são de fato diferentes:

| URL de produção | Criativo servido no topo |
|-----------------|--------------------------|
| `bahia.ba/` | `1607_BR_OFDR_PARCEIRIA_MAIN_728X90_DATA.jpg` (grupo 1) |
| `bahia.ba/politica/` | `IAB-0017-26-A-VINHETAS-ANIMADAS-BANNER-728x90px-1507.gif` (grupo 12) |
| `bahia.ba/municipios/` | `download.gif` (grupo 14) |

**Sim: o grupo 3 estava mesmo servindo inventário de home nas internas.** Medido em homolog
antes da correção — `/politica/`, `/esporte/`, `/quem-somos/` e os posts individuais todos
traziam `<div class="g g-3">` no cabeçalho. Um anunciante que comprou "Internas -
Leaderboard" não estava sendo entregue nas internas; quem comprou home estava sendo
entregue em toda parte.

### Como ficou

`mu-plugins/bahia-publicidade.php` reproduz a mesma decisão. Conferido no HTML servido de
homolog depois da mudança (o atributo `data-grupo` foi acrescentado justamente para tornar
isso auditável sem abrir o código):

| URL de homolog | Grupo entregue no topo | Retângulos laterais |
|----------------|------------------------|---------------------|
| `/` | **1** | — (a home não tem coluna lateral) |
| `/politica/` | **12** | 8 (topo) + 9 (fim) |
| `/esporte/` | **12** | 8 + 9 |
| `/politica/<post>/` | **12** | 8 + 9 |
| `/municipios/` | **14** | 8 + 9 |
| `/municipios/<post>/` | **14** | 8 + 9 |
| `/quem-somos/` | **12** | 8 + 9 |

Mesma lógica para os retângulos: o 8 é o topo da coluna lateral e o 9 é o fim dela, como em
`sidebar.php:18` e `sidebar.php:89`.

---

## 3. Problemas de exibição — com medição

Conforme combinado, aqui vão medidos, não adaptados por conta própria.

### 3.1 Os grupos 1 e 12 são de 970x90 e no topo só cabem 728

Medido na home de homolog, em desktop:

```
faixa útil da linha da marca ....... 1028 px
logotipo ...........................  256 px
espaço entre os dois ...............   24 px
--------------------------------------------
sobra para o banner ................  748 px
um criativo de 970 precisa de ......  970 px
                                     -------
                                      -222 px  -> NÃO CABE
```

**Mas isso é um problema quase teórico, e o número explica por quê.** Contando os criativos
cadastrados em cada grupo pela medida que trazem no nome do arquivo:

| Grupo | 728 de largura | 970 de largura | outros |
|-------|----------------|----------------|--------|
| 1 — Home - Super leader board | **94** | 1 | 7 |
| 12 — Internas - Leaderboard | **96** | 2 | 6 |

Ou seja: os grupos estão **declarados** como 970x90, mas o que o comercial de fato entrega
são peças de **728x90**. Todos os criativos ativos hoje, nos dois grupos, medem 728x90 —
verificado baixando os arquivos e lendo o cabeçalho da imagem.

**Consequência prática:** o slot funciona. Um criativo de 970, se aparecer, é reduzido a 728
(perde ~25% de área) em vez de estourar o layout — a redução é proposital, e é o que evita
quebrar a página.

**Onde um 970 caberia de verdade:** em linha própria, ocupando a largura toda. As faixas da
home medem 1116 px úteis e a caixa de conteúdo das internas mede 1068 px — 970 cabe nas
duas, desde que **sozinho na linha**, sem o logotipo ao lado. Isso é mudança de desenho do
cabeçalho, não transporte, e por isso não foi feita.

**A decidir:** ou o comercial passa a tratar os grupos 1 e 12 como 728x90 (que é o que já
são na prática), ou se abre uma frente para dar linha própria ao leaderboard.

### 3.2 Os retângulos 300x250 cabem, com folga

Medido em `/politica/` e num post individual, em desktop:

```
coluna lateral (.td-main-sidebar) .... 372 px
recuo interno (24 de cada lado) ......  48 px
-----------------------------------------------
largura útil ......................... 324 px
criativo ............................. 300 px
                                       -------
folga .................................  24 px  -> CABE
```

Renderizam no tamanho nativo (300x250), sem redução e sem transbordar a coluna. A coluna
lateral estava **vazia** no tema novo, então os dois retângulos não empurraram nem cobriram
nada. Sem barra de rolagem horizontal em nenhuma das páginas validadas.

**Ressalva honesta:** como a coluna lateral não tem mais nada nela, o retângulo "do fim"
(grupo 9) acaba ficando logo abaixo do "do topo" (grupo 8), separados por 24 px. No tema
antigo havia conteúdo entre os dois. Não é defeito — é consequência de a coluna estar vazia
—, mas quem olhar vai ver dois retângulos empilhados, e vale saber por quê.

### 3.3 Busca, autor e 404 ficaram sem os retângulos

No tema antigo, a página de resultados de busca (`search-web.php`) e a de autor
(`sidebar-levi.php:118` e `:184`) usavam a mesma coluna lateral, com os grupos 8 e 9.

No tema novo essas três páginas são renderizadas por modelos do Cloud Library (9000138,
9000132 e 9000140) que **não têm coluna lateral nenhuma** — não é que o anúncio não foi
colocado, é que não existe onde colocar.

**Não foi improvisada uma posição.** Criar uma coluna lateral nesses três modelos é mudança
de layout, com decisão de desenho, e está fora do escopo de "transporte fiel". Fica
registrado como perda de inventário conhecida, a decidir.

### 3.4 O grupo 14 no contexto de municípios

728x90, o mesmo formato já validado no cabeçalho nas rodadas anteriores. Conferido em
`/municipios/` e num post de município: renderiza a 728x90 nativos, sem redução. Sem
problema.

---

## 4. Os dois grupos que continuam sem posição

### Grupo 3 — "Home - Leader Board 2" (728x90, 106 anúncios, 4 ativos)

**No tema de produção não existe uma única chamada a este grupo.** No tema anterior
(`bahia_social/index.php:242`) existe uma, mas está **comentada** — `<//?php echo
adrotate_group(3); ?>` — ou seja, foi desligada de propósito em algum momento e nunca
voltou.

Transporte fiel, portanto, é **não renderizar**. Foi o que se fez. Como era o grupo que o
cabeçalho servia até aqui, ele **sai do ar** com esta rodada.

> **Isto precisa de decisão comercial.** Se há contrato vigente apontando para o grupo 3, ele
> não está mais sendo entregue — e antes desta rodada estava sendo entregue *em toda página do
> site*, o que também não era o contratado. Os 4 anúncios ativos nele são os mesmos que já
> estão nos grupos 1, 12 e 14, então **nenhum anunciante ficou sem exibição** hoje. O que
> falta decidir é se o grupo 3 deve ganhar uma posição própria (era um "segundo leaderboard"
> da home) ou ser aposentado.

### Grupo 5 — "Home - Retângulo" (300x250, 34 anúncios, 1 ativo)

**Não há chamada a este grupo em nenhum dos dois temas.** Os arquivos que seriam o lugar
natural dele — `ad-long.php`, `ad-small.php` e `ad-mobile.php`, incluídos nas colunas
laterais da home — estão **vazios, com 0 byte**, e assim estão desde o primeiro commit do
repositório. Não é algo que se perdeu na migração: nunca teve conteúdo aqui.

Some-se a isso que **a home do tema novo não tem coluna lateral**, que é onde um 300x250 da
home moraria.

**Não foi inventada uma posição.** Colocá-lo em algum lugar da home é decisão de desenho e de
comercial, não transporte.

---

## 5. Como isso foi implementado

Tudo em `mu-plugins/`, como manda a regra do projeto — `plugins/` e o tema não foram tocados,
porque um commit que mexa em `plugins/` quebra o deploy de produção por permissão.

| Arquivo | O que faz |
|---------|-----------|
| `bahia-publicidade.php` | **novo.** Escolhe o grupo do topo pelo contexto e ancora os dois retângulos na coluna lateral. |
| `bahia-header-ad.php` | passa a pedir o grupo a `bahia_pub_grupo_topo()` em vez do 3 cravado. Acrescenta `data-grupo` ao HTML. |

Dois detalhes que valem para quem for mexer depois:

- **A coluna lateral do Newspaper tem dois caminhos diferentes**, e os dois precisaram de
  âncora. O post individual e as páginas passam por `get_sidebar()` → `tdc_sidebar`; o
  archive de editoria chama `dynamic_sidebar('td-default')` direto, no PHP do tema. Há uma
  trava que impede render duplo caso um dia os dois se cruzem.
- **`is_front_page()`, não `is_home()`.** Aqui a home é uma página estática (9000142), então
  `is_home()` é falso nela — o teste do tema antigo não funcionaria copiado ao pé da letra.

---

## 6. O que fica para a rodada de mobile

**Todos os formatos de mobile do AdRotate estão zerados hoje:**

| Grupo | Nome | Medida | Anúncios | Ativos |
|-------|------|--------|----------|--------|
| 2 | Home - Formato Proprietário 1 | 320x100 | 2 | **0** |
| 4 | Home - Formato Proprietário 2 | 320x100 | 4 | **0** |
| 10 | HomeMobile-1 | 125x125 | 1 | **0** |
| 11 | InternaMobile-1 | 125x125 | 1 | **0** |
| 13 | Internas-Botao_Proprietario | 320x100 | 4 | **0** |

Transportá-los agora renderizaria espaço em branco no celular. É a pendência nº 2 de
`PENDENCIAS-gestores.md` (criar peça 320x100 ou não vender), e a decisão comercial vem antes
do trabalho técnico.

Vale lembrar que **o celular é a maior parte do público de um portal de notícias** — os cinco
grupos acima zerados são, provavelmente, o maior buraco de receita deste inventário, bem
maior que os grupos 3 e 5 do §4.

---

## 7. O que levar ao comercial

1. **O inventário passou de 1 para 6 espaços renderizados.** Cinco grupos que estavam
   cadastrados e vendáveis não apareciam em lugar nenhum do site novo.
2. **A entrega por contexto foi restabelecida.** Home, internas e municípios voltam a servir
   cada um o seu grupo. Antes, todo o site servia o grupo de home.
3. **Grupos 1 e 12 são 970x90 no papel e 728x90 na prática** (94 de 102 e 96 de 104
   criativos). Vale acertar o cadastro com a realidade, ou dar linha própria ao leaderboard.
4. **Grupos 3 e 5 seguem sem posição**, por não terem posição no tema de origem. Precisam de
   decisão: aposentar ou desenhar um lugar.
5. **Busca, autor e 404 perderam os dois retângulos**, por não terem coluna lateral no tema
   novo. Precisa de decisão de layout.
6. **Todo o inventário de mobile está zerado.** É a maior lacuna, e depende de peça criativa.
7. **A contagem de exibições continua desligada** em todos os anúncios, e o último registro de
   estatística é de 28/06/2026 — pendência nº 3 de `PENDENCIAS-gestores.md`, e sem ela não há
   como comprovar entrega a anunciante.
8. **Anúncio novo demora 3 horas para aparecer**, por um defeito de fuso horário do plugin.
   Contorno: deixar a data de início em branco. Pendência nº 6 de `PENDENCIAS-gestores.md`.

---

## 8. Rodada 9 — os slots de CELULAR

**Levantado e aplicado em:** 11/08/2026. Posições conferidas em **produção** (`bahia.ba`) com
UA móvel **e cache-buster** — sem o buster o CDN devolve a variante de desktop e a leitura sai
errada. Foi assim que a primeira medição desta rodada se enganou.

### 8.1 O achado: o leaderboard não era entregue no celular

O `#bar` do legado (`themes/bahia_refactor/header.php:196-210`) está **dentro do ramo
`is_mobile()`**, com o comentário `<!-- Banners mobile - fora do header fixo -->`. O legado
entrega leaderboard no celular; o Newspaper não entregava. Medido em 390px, antes da rodada:

```
data-grupo=12  caixa=0x0  ANCESTRAL display:none -> .td-header-desktop-wrap
data-grupo=8   caixa=350x252  VISIVEL
data-grupo=9   caixa=350x252  VISIVEL
```

Os retângulos da coluna lateral apareciam; o leaderboard, não. **Grupos 1, 12 e 14 são os
únicos com criativo ativo hoje** — era inventário vendido e vivo perdido justamente onde está
a maior parte do público de um portal.

Depois da rodada, a troca por largura é limpa e sem duplicata:

| largura | `.bahia-header-ad` | `.bahia-pub-mob` |
|---------|--------------------|------------------|
| 390px | 0x0 (oculto) | **350x43 visível** |
| 1280px | **728x90 visível** | 0x0 (oculto) |

### 8.2 Onde cada grupo de celular ficou

| Grupo | Medida | Posição no celular | Renderiza hoje? |
|-------|--------|--------------------|-----------------|
| 1 / 12 / 14 | 728x90 | topo, acima do conteúdo (onde o `#bar` do legado fica) | **sim** |
| 2 | 320x100 | home, **depois** do primeiro bloco | não — grupo zerado |
| 13 | 320x100 | internas, logo abaixo do leaderboard | não — grupo zerado |
| 10 | 125x125 | fim da listagem (home e archives) | não — grupo zerado |
| 11 | 125x125 | fim do post individual | não — grupo zerado |
| 4 | 320x100 | **nenhuma** — única chamada do legado está comentada | — |
| 7 | 125x125 | **nenhuma** — sem chamada em nenhum tema, 0 anúncios | — |

### 8.3 Grupos 2 e 13 ficam SÓ no celular — e isso é infidelidade consciente

No legado o `#bar` é **compartilhado**: `header.php:401/406` é cópia idêntica do ramo mobile,
ou seja, o legado põe o 320x100 também no desktop. **Não foi transportado para o desktop**, e
a razão é aritmética: a linha da marca fechada na rodada 8 tem 256px de logo + 728x90 numa row
de 1068px, com ~20px de folga. **Não cabe um 320x100 ali.**

Enquanto os grupos estiverem zerados nada apareceria e a cópia pareceria inofensiva — mas no
dia em que o comercial cadastrasse um criativo, o desktop quebraria sozinho e ninguém
associaria à origem. O legado tinha outra estrutura de cabeçalho.

> **Se o desktop um dia precisar dos grupos 2 e 13, é REDESENHO DE CABEÇALHO, não transporte.**

### 8.4 Slot vazio não deixa buraco — provado nos dois caminhos

Sem criativo elegível o AdRotate devolve **só um comentário HTML** (92 bytes, sem nenhum
`<div class="g">`). A guarda devolve `''` **antes de montar o contêiner**, então não há div,
não há margem e não há espaço reservado:

```
grupo 2   -> '' (contêiner NÃO emitido — colapsa)
grupo 13  -> '' (contêiner NÃO emitido — colapsa)
grupo 10  -> '' (contêiner NÃO emitido — colapsa)
grupo 11  -> '' (contêiner NÃO emitido — colapsa)
grupo 8   -> <div class="bahia-pub-mob ..." data-grupo="8"><div class="g g-8">…
```

### 8.5 Encaixe e reserva de altura

Largura útil da coluna medida em **20px de recuo constante** nas três larguras testadas:

| viewport | coluna | 728x90 vira | 320x100 | 125x125 |
|----------|--------|-------------|---------|---------|
| 360px | 320px | 320x40 | cabe exato | cabe |
| 390px | 350px | **350x43** | cabe, folga 30px | cabe |
| 414px | 374px | 374x46 | cabe, folga 54px | cabe |

**Sem deslocamento de layout.** A caixa recebe `aspect-ratio` por grupo, então tem a altura
final antes de a imagem carregar — provado removendo o `src` em runtime: a caixa continua com
43px. CLS medido: **0,0053 na home e 0,0001 no archive** (limite bom é 0,1), e o resíduo da
home é de `.td-module-meta-info`, não do slot.

A reserva usa a medida **real** (728x90), não a cadastrada (970x90 nos grupos 1 e 12): o
cadastro está errado desde sempre — 94 de 102 e 96 de 104 criativos são 728. Reservar por 970
deixaria faixa vazia permanente.

### 8.6 Por que CSS e não `Mobile_Detect` como o legado

O site serve **fastcgi_cache**. Uma variante por user-agent envenenaria o cache: a primeira
visita decidiria o que todo mundo veria depois. A separação é por media query em 767px, que é
como o próprio tema já esconde o header de desktop.
