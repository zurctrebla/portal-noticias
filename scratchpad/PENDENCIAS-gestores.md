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

## 6. Anúncio novo só aparece 3 horas depois de cadastrado

### O que é

Ao cadastrar um anúncio no AdRotate com a data de início marcada para **agora**, ele fica
**3 horas sem aparecer no site** — mesmo constando como "ativo" no painel, mesmo com o
agendamento aparentemente correto. Não há mensagem de erro. Passadas as 3 horas, ele começa
a ser exibido normalmente, sozinho.

A causa é um defeito no plugin AdRotate: ele **anota** a hora de início no fuso de Londres e
**confere** no fuso da Bahia, que está 3 horas atrás. A diferença sempre atrasa a estreia,
nunca adianta.

### O que fazer enquanto isso não é corrigido

**Ao cadastrar um anúncio, deixe a data de início em branco, ou coloque uma data anterior à
de hoje.** Nos dois casos o anúncio entra no ar imediatamente.

A data de **término** pode ser preenchida normalmente — o mesmo deslocamento de 3 horas
existe nela, mas 3 horas a mais no fim de uma campanha de semanas não tem efeito prático.

Se um anúncio precisa estrear numa hora exata (uma estreia de filme, um evento), cadastre-o
com início em branco algumas horas antes e **ative-o na hora certa**, em vez de confiar no
agendamento.

### Por que importa

É perda de exibição paga que ninguém percebe: o anúncio consta como ativo no painel, e a
única forma de notar é abrir o site e reparar que ele não está lá. Numa campanha curta, de
um ou dois dias, 3 horas são uma fatia relevante do que foi contratado.

### O que é preciso decidir

**Nada agora, se a orientação acima for seguida.** A correção definitiva é possível, mas
exige alterar um arquivo que hoje **quebra o processo de publicação em produção** — é uma
frente de trabalho própria, com teste, não um ajuste de minutos. Se a equipe preferir a
correção definitiva a conviver com a orientação, é uma decisão a tomar, e o caminho técnico
já está registrado no documento de handover.

---

## 7. Treze matérias com a descrição do Google cortada no meio — corrigir NA PRODUÇÃO

### O que é

A "descrição" é a frase que aparece embaixo do título nos resultados do Google e no cartão
que o WhatsApp monta quando alguém compartilha o link. Em quase todo o site ela passa a ser
o **subtítulo** escrito pelo repórter, automaticamente, sem ninguém precisar fazer nada.

A exceção são as matérias em que alguém preencheu à mão o campo de descrição do Yoast:
nelas o texto escrito à mão continua mandando, como deve ser. São 968 matérias, e 955
delas têm texto bom.

**Treze estão cortadas no meio de uma frase.** Terminam em "da", "sua", "que", "sido" —
palavras que não encerram frase. É resíduo de um preenchimento automático antigo, não
texto de jornalista. Todas as treze têm subtítulo preenchido, pronto para assumir.

Exemplo, a matéria 545982:

> **Hoje:** "Eduardo Bolsonaro recebeu um green card concedido pelo governo dos Estados
> Unidos, documento que permite **sua**"
>
> **Subtítulo disponível:** "A concessão do cartão de residência ocorre em meio ao aumento
> das tensões diplomáticas entre Brasil e Estados Unidos"

### A ação, e por que é na produção

**Estas treze matérias vivem no banco de produção e não vêm de homolog.** Corrigir em
homolog não resolve nada: o conteúdo caminha no sentido contrário, de produção para
homolog. A correção tem de ser feita no painel de **bahia.ba**.

O que fazer, em cada uma das treze: abrir a matéria no painel de produção, ir ao bloco do
Yoast SEO, **apagar o conteúdo do campo de descrição** e salvar. Nada além disso. Deixando
o campo vazio, o subtítulo assume sozinho assim que a nova versão do site entrar no ar.

Não é preciso escrever descrição nova. Não é preciso mexer no texto da matéria.

### As treze

| # | ID | Editoria | Data | Título |
|---|---|---|---|---|
| 1 | 545948 | Política | 20/07/2026 | Otto Alencar prevê crescimento do PSD e descarta apoio a Caiado |
| 2 | 545973 | Política | 20/07/2026 | Após saída do PV, Ludmilla Fiscina oficializa disputa pelo PSD |
| 3 | 545982 | Política | 20/07/2026 | Eduardo Bolsonaro recebe green card e garante residência nos EUA |
| 4 | 546158 | Política | 21/07/2026 | Lula diz que Brasil 'não se entrega' antes de tarifa dos EUA |
| 5 | 546171 | Mundo | 21/07/2026 | França se torna primeiro país da UE a proibir redes sociais para crianças |
| 6 | 546560 | Política | 23/07/2026 | Leandro de Jesus critica Jerônimo após anuário da violência na Bahia |
| 7 | 546570 | Mundo | 23/07/2026 | EUA preparam anúncio sobre novas tarifas nesta quinta (23) |
| 8 | 546638 | Política | 23/07/2026 | Michelle perdoa Flávio Bolsonaro e propõe diálogo após crise |
| 9 | 546767 | Política | 24/07/2026 | Delliana Ricelli quer renovar perfil do Senado |
| 10 | 546815 | Política | 24/07/2026 | Bolsonaro recorre contra proibição de visitas durante prisão domiciliar |
| 11 | 546845 | Bahia | 25/07/2026 | Homem flagrado se masturbando em academia de condomínio é preso |
| 12 | 546860 | Mundo | 25/07/2026 | Governo Trump planeja enviar missão ao Brasil para questionar eleições |
| 13 | 547274 | Política | 28/07/2026 | Condenado, Binho Galinha tenta reeleição pelo Avante |

Para abrir direto: `https://bahia.ba/wp-admin/post.php?post=ID&action=edit`, trocando `ID`
pelo número da tabela.

### Urgência

Baixa. São treze matérias de julho de 2026, já fora do fluxo de leitura. A correção pode
ser feita a qualquer momento, inclusive depois da virada — o efeito aparece assim que o
campo ficar vazio. Está aqui para não se perder.

---

## Resumo

| # | Pendência | Tipo de decisão | Urgência |
|---|-----------|-----------------|----------|
| 1 | Conteúdo largo em celular estreito | Abrir frente de trabalho (problema **pré-existente**) | Baixa |
| 2 | Publicidade no topo em celular | **Comercial** — criar peça 320x100 ou não vender | Média (receita) |
| 3 | Contagem de exibição de anúncios | **Operacional** — ligar nos ativos, virar rotina | **Alta** (comprovação ao anunciante) |
| 4 | Logotipo branco vetorial | Solicitar arquivo ao designer | Baixa (acabamento) |
| 5 | Limite de 70/160 caracteres | Nenhuma — só conferir após atualizar o tema | Informativa |
| 6 | Anúncio novo demora 3h para aparecer | **Operacional** — deixar a data de início em branco | Média (perda de exibição paga) |
| 7 | 13 descrições cortadas no meio | **Operacional, na PRODUÇÃO** — apagar o campo de descrição do Yoast nas 13 | Baixa |

---

## Rodada 9 — o que o celular acrescenta a esta lista

### A. Legibilidade da publicidade no celular depende de PEÇA, não de layout

Os slots de celular foram abertos e estão prontos. O que falta é criativo.

Um **728x90** na coluna do celular (350px em aparelho de 390px) é reduzido a **350x43** — 48%
da largura original. Peça com texto miúdo fica ilegível nesse tamanho. **Isto não é defeito do
site:** é o que produção já entrega hoje, e as duas alternativas foram medidas e descartadas —
sangrar até a borda quebra o alinhamento do conteúdo, e rolagem lateral exige um gesto que
ninguém faz para ver publicidade.

**O caminho é cadastrar peça em formato de celular.** Os grupos existem, têm posição e
renderizam sozinhos assim que houver criativo ativo:

| Grupo | Nome | Medida | Anúncios | **Ativos** | Onde aparece |
|-------|------|--------|----------|-----------|--------------|
| 2 | Home - Formato Proprietário 1 | 320x100 | 2 | **0** | home, após o 1º bloco |
| 13 | Internas-Botao_Proprietario | 320x100 | 4 | **0** | internas, sob o leaderboard |
| 10 | HomeMobile-1 | 125x125 | 1 | **0** | fim da listagem |
| 11 | InternaMobile-1 | 125x125 | 1 | **0** | fim do post |

Um 320x100 **cabe inteiro** na coluna de 350px, com 30px de folga, sem redução nenhuma.

> Enquanto estiverem zerados, os slots **não ocupam espaço**: o contêiner nem chega a ser
> emitido. Não há buraco no layout e não há nada a corrigir do lado técnico. É decisão
> comercial pura.

### B. Grupos 4 e 7 entram na mesma fila dos grupos 3 e 5

Levantamento desta rodada, sobre os dois temas legados:

- **Grupo 4** ("Home - Formato Proprietário 2", 320x100, 4 anúncios, 0 ativos): a única
  chamada existente está **comentada** (`bahia_social/index.php:243`) e no tema de produção
  não há nenhuma.
- **Grupo 7** ("Home-Proprietário 3 SubDestaques", 125x125): **nenhuma chamada em nenhum dos
  dois temas**, e **zero anúncios cadastrados**.

São quatro grupos sem posição de origem — 3, 4, 5 e 7. **Aposentar ou desenhar lugar** é
decisão comercial; não se inventou posição para nenhum.

### C. A editoria "Dendê e Poder" está no ar e vazia

`/dende-e-poder/` responde 200, com título e menu corretos, e **zero matérias**. O leitor vê
uma página de seção sem conteúdo. Precisa de pauta ou de saída do menu antes da homologação.
