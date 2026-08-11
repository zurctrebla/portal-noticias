# Pendências para decisão — portal bahia.ba

**Data:** 11 de agosto de 2026
**Para:** gestão do bahia.ba
**Assunto:** cinco pontos que dependem de decisão de vocês, não de execução técnica

O trabalho de redesenho do portal está concluído no ambiente de homologação
(`hml.bahia.ba`). Este documento lista o que **não** foi decidido por nós, porque envolve
custo, contrato com terceiros ou escolha editorial.

Cada item segue a mesma estrutura: o que é, por que importa, e o que precisa ser decidido.

---

## 1. Conteúdo mais largo que a tela em celulares estreitos

### O que é

Em aparelhos de tela estreita (a referência usada nos testes é 390 pixels de largura, o que
corresponde a um iPhone comum), parte do conteúdo ultrapassa a borda direita da tela. O leitor
precisa arrastar a página para o lado para ver o texto inteiro, em vez de só rolar para baixo.

### **Este problema é anterior ao nosso trabalho**

Registramos isso com clareza porque é o tipo de coisa que pode ser confundida com efeito
colateral do redesenho. **Não é.** Verificamos o comportamento de forma isolada e ele já
existia antes de qualquer alteração desta série de ajustes. O redesenho não criou o problema,
não o agravou, e também não o resolveu — ele estava fora do escopo contratado.

### Por que importa

A maior parte do público de um portal de notícias lê pelo celular. O incômodo é pequeno em
cada visita, mas é constante e transmite descuido.

### O que é preciso decidir

Se vale abrir uma frente específica para isso. É um trabalho de revisão de conteúdo e de
folha de estilo, item por item, para descobrir quais elementos estouram a largura — não é um
ajuste único. Precisa de estimativa própria.

---

## 2. Publicidade no cabeçalho em celulares

### O que é

No computador, existe um espaço de publicidade no topo do site, ao lado do logotipo, no
formato 728x90 pixels (o "leaderboard", padrão de mercado). **No celular esse espaço não
aparece** — simplesmente não cabe: 728 pixels não entram numa tela de 390.

Hoje o cabeçalho no celular mostra só o menu e o logotipo. Nenhum anúncio.

### Por que importa

É receita não realizada. O celular é onde está a maior parte da audiência, e é justamente
onde o inventário do topo está vazio.

### O que é preciso decidir

Três caminhos possíveis:

1. **Criar um formato próprio para celular** — o padrão de mercado é 320x100 ou 320x50. Exige
   que os anunciantes entreguem uma peça nova nesse tamanho, ou que a equipe de criação
   adapte as existentes. É o caminho que gera receita.
2. **Não vender esse espaço no celular** — assumir que o topo é só marca e navegação, e
   concentrar a publicidade móvel nos espaços dentro do conteúdo.
3. **Manter como está por ora** e reavaliar quando houver demanda comercial.

A parte técnica está pronta para qualquer um dos três: incluir o espaço no celular é rápido,
assim que existir a peça no tamanho certo. **A decisão é comercial.**

---

## 3. Contagem de visualizações dos anúncios está desligada

### O que é

O sistema de anúncios do site (AdRotate) tem um contador de exibições e cliques. Esse contador
está **desligado nos anúncios que estão no ar**, nos dois ambientes.

Números levantados em 11/08/2026, no ambiente de homologação:

- **151 anúncios** cadastrados no total
- **3 anúncios ativos** no momento — e **nenhum dos três** tem a contagem ligada
- **104 anúncios expirados** (campanhas encerradas)
- **O último registro de estatística é de 28 de junho de 2026** — de lá para cá, nada foi
  contabilizado

### Por que importa

Sem esse dado, não há como comprovar entrega ao anunciante nem embasar preço de renovação. Se
algum contrato prevê relatório de exibições, esse relatório hoje não pode ser emitido a partir
do sistema.

### O que é preciso decidir e saber

Três pontos, e o terceiro é o mais importante:

1. **Como se liga:** é uma caixa de seleção na tela de edição de cada anúncio. Não é uma
   configuração geral do site — é **um a um**.
2. **Não é retroativo.** Ligar hoje começa a contar de hoje. O período de 28/06 até a data em
   que for ligado **não é recuperável** por essa via.
3. **O volume de trabalho:** para passar a medir tudo, seriam os anúncios ativos (hoje 3, e os
   que entrarem daqui em diante). Se a intenção for também deixar os 104 expirados prontos para
   futuras reativações, é ajuste individual em cada um.

**Recomendação prática:** ligar a contagem nos 3 ativos agora (leva poucos minutos) e adotar
como regra que todo anúncio novo já entre com a opção marcada. Os expirados só quando forem
reaproveitados.

> Nota: existem 3 anúncios ativos no ambiente de homologação que foram reativados **apenas
> como material de teste**, para validar o layout. Eles não devem ir para o site de produção,
> e a equipe técnica já registrou como desfazer isso.

---

## 4. Logotipo branco do rodapé

### O que é

O rodapé do site tem fundo azul-escuro, e por isso precisa de uma versão do logotipo em branco.
O arquivo em uso hoje **foi derivado por nós a partir do logotipo colorido** — ou seja, é uma
adaptação, não o arquivo oficial da marca.

### Por que o arquivo anterior não servia

O logotipo disponível é uma imagem colorida com fundo transparente, feita para fundo claro.
Sobre o azul do rodapé, as letras escuras praticamente desapareciam. As alternativas eram
deixar o logo ilegível ou gerar uma versão branca — optamos pela segunda, para não travar a
entrega.

### A limitação da versão atual

Por ser derivada de uma imagem (e não do desenho vetorial original), ela **não tem a mesma
nitidez em todas as ampliações**. Em telas de alta densidade ou se for ampliada, pode
apresentar bordas menos definidas do que o logotipo oficial.

### O que é preciso decidir

Solicitar ao designer responsável pela marca o **arquivo vetorial** (formato `.svg`, `.ai` ou
`.eps`) da versão **monocromática branca** do logotipo. Com esse arquivo, a substituição é
imediata e o resultado fica perfeito em qualquer tamanho.

Enquanto isso não chega, o site funciona normalmente com a versão atual — é uma melhoria de
acabamento, não uma correção urgente.

---

## 5. Limite de 70 e 160 caracteres em títulos e resumos

### O que é

Foi pedido que os títulos das listagens fossem cortados em 70 caracteres e os resumos em 160,
para que os cartões de notícia fiquem alinhados e o layout não quebre com títulos muito longos.
**Está implementado e funcionando.**

### Qual é a ressalva

Para conseguir isso, foi necessário usar um ponto de acesso interno do tema — uma peça de
código que o fabricante do tema (tagDiv) não documenta como área pública de extensão. Foi o
único caminho disponível: o tema não passa esses textos pelos mecanismos padrão do WordPress,
que seriam o lugar natural para esse ajuste.

### Por que isso é registrado aqui

Porque tem uma consequência concreta: **numa atualização futura do tema, se o fabricante mudar
essa peça interna, o limite pode deixar de funcionar sem aviso.** Não quebraria o site nem
geraria erro visível — os títulos simplesmente voltariam a aparecer inteiros, e o
desalinhamento nos cartões seria a única pista.

### O que é preciso saber

Nenhuma ação agora. O registro serve para que, **após qualquer atualização do tema Newspaper**,
alguém confira se os títulos das listagens continuam sendo cortados. É uma verificação de trinta
segundos, desde que se saiba que ela precisa ser feita — e é exatamente por isso que está
escrita aqui.

Uma solução definitiva existiria se o fabricante oferecesse um ponto de extensão oficial. Não
oferece hoje.

---

## Resumo

| # | Pendência | Tipo de decisão | Urgência |
|---|-----------|-----------------|----------|
| 1 | Conteúdo largo em celular estreito | Abrir frente de trabalho (problema **pré-existente**) | Baixa |
| 2 | Publicidade no topo em celular | **Comercial** — criar peça 320x100 ou não vender | Média (receita) |
| 3 | Contagem de exibição de anúncios | **Operacional** — ligar nos ativos, virar rotina | **Alta** (comprovação ao anunciante) |
| 4 | Logotipo branco vetorial | Solicitar arquivo ao designer | Baixa (acabamento) |
| 5 | Limite de 70/160 caracteres | Nenhuma — só conferir após atualizar o tema | Informativa |
