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

## 3. Contagem de visualizações dos anúncios — **CORRIGIDO em 27/08/2026**

> ### ⚠️ A versão anterior deste item estava errada, e a correção muda a decisão
>
> Até 27/08/2026 este item afirmava que a contagem de anúncios estava **desligada** e que o
> último registro era de **28 de junho de 2026**. **Não é verdade em produção.**
>
> Aquele levantamento foi feito no **ambiente de homologação**, em 11/08. Homologação é uma cópia
> de teste do site, e o estado dela **não vale como retrato do site no ar**. A conferência foi
> feita no lugar errado e a conclusão viajou para este documento como se valesse para produção.
>
> **Se alguém do comercial deixou de cobrar relatório, ou negociou renovação sem número, com base
> na versão anterior deste item, a informação que tinha estava errada.**

### O que está acontecendo de verdade, medido no site no ar

A contagem **está ligada e funcionando**. Medido em 27/08/2026, no meio da tarde:

| | |
|---|---|
| Configuração geral do sistema | **ligada** |
| Exibições registradas **só no dia 27/08** | **20.825** |
| Ritmo medido ao vivo | **cerca de 70 registros por minuto**, ou ~4.200 por hora |
| Cliques registrados nas últimas 24 horas | 8 |

### Quais anúncios estão sendo contados

Dos **3 anúncios ativos**, **2 têm a contagem ligada** e 1 não tem:

| Anúncio | Exibições em 27/08 | Cliques |
|---|---|---|
| AGOSTO — FILME — MUITO PRAZER (peça 1) | **13.583** | 4 |
| AGOSTO — FILME — MUITO PRAZER (peça 2) | **7.951** | 4 |
| O terceiro anúncio ativo | **não é contado** | — |

No cadastro completo há 160 anúncios: 3 ativos, 4 programados, 2 expirados, 150 arquivados.

### ⚠️ O limite que importa para contrato: **os dados duram cerca de um dia**

Este é o ponto que não estava em nenhuma versão anterior deste documento.

O próprio sistema de anúncios roda uma **limpeza automática diária** que apaga os registros
antigos. Conferido: neste momento a base guarda de **26/08 às 10h48** até **27/08 às 11h30** — ou
seja, **pouco mais de 24 horas**. A próxima limpeza está agendada para hoje às 18h05 (horário de
Brasília).

**Consequência prática, em uma frase: dá para emitir relatório do dia; não dá para emitir
relatório do mês nem da campanha inteira.** O número de ontem já não existe mais no sistema.

### O que precisa ser decidido

1. **O terceiro anúncio ativo deve passar a ser contado?** É uma caixa de seleção na tela de
   edição daquele anúncio. Enquanto estiver desmarcada, aquela campanha não tem número nenhum.
2. **O histórico precisa ser guardado?** Se algum contrato prevê relatório mensal ou de campanha,
   **alguém precisa anotar o número todo dia antes da limpeza automática** — ou o site precisa
   passar a guardar esse total em outro lugar. Hoje, o dado nasce e some em 24 horas.
3. **A partir de quando vale.** A contagem que existe hoje começou a valer quando foi ligada em
   cada anúncio; não é retroativa. Não sabemos dizer a data exata de cada peça sem abrir uma a
   uma — se isso for necessário para uma renovação, avisem e levantamos.

### O que foi feito, e o que não foi

**Nada foi alterado.** Nenhuma configuração de anúncio foi ligada, desligada ou mexida. Este item
apenas corrige a informação.

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
| 3 | Contagem de exibição de anúncios — **item corrigido em 27/08** | **Operacional** — a contagem **já está ligada** em 2 dos 3 ativos; o que falta é o 3º anúncio e **guardar o histórico, que hoje some em 24h** | **Alta** (comprovação ao anunciante) |
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

---

## 8. Cor própria das tags de editoria — 15 das 25 estão no preto padrão

**Levantado em 18/08/2026, na fase 2 da virada. Não é defeito, é escolha em aberto.**

### O que é

Cada matéria exibe, no card e no topo, um selo com o nome da editoria. Dez editorias têm cor
própria, definida ao longo das rodadas de ajuste visual, e as demais caem num **padrão preto
com texto branco** — legítimo, com contraste máximo (21:1), e já é o que acontece hoje.

| Com cor própria (10) | No preto padrão (15) |
|---|---|
| Política, Salvador, Municípios, Justiça, Esporte, Brasil, Entretenimento, Mundo, Artigos, Dendê e Poder | Bahia, Especial, Exclusivo, Economia, Entrevistas, Mais Notícias, Carnaval, Mais Gente, **Covid-19**, **Eleições 2024**, **Saúde e Bem Estar**, **Coluna do Ginno**, **Gente**, **Investimentos**, **Bombou** |

As sete em **negrito** são as editorias que a fase 2 trouxe de volta ao mapa. As outras oito já
estavam no preto antes deste trabalho.

### Por que importa

O selo colorido é o que dá reconhecimento imediato da editoria numa home com muitos cards.
Onde ele é preto, a editoria some visualmente no meio das outras — o leitor lê o texto do selo
em vez de reconhecê-lo pela cor.

Não afeta acessibilidade: o preto com texto branco é o melhor contraste da paleta inteira.

### O que é preciso decidir

**Quais dessas 15, se alguma, merecem cor própria** — provavelmente só as de tráfego real, não
as 15. Pelo acervo de produção, as candidatas naturais são **Economia** (18.972 matérias
publicadas), **Bahia** (17.161) e **Covid-19** (10.871); as demais somam menos de 3.000 cada.

Cada cor nova precisa de um tom que (a) não se confunda com os dez já em uso e (b) passe em
contraste AA contra o texto branco — o componente já escurece o fundo automaticamente até
4,5:1 quando necessário, então a escolha é de matiz, não de luminosidade.

**Se a resposta for "está bom assim", não há trabalho a fazer** — o padrão preto continua e o
site fica como está hoje.

---

## 9. Produção serve o site inteiro SEM compressão

**Medido em 18/08/2026, na fase 2 da virada. Pré-existente, não causado por este trabalho.**

### O que é

Servidores web comprimem o que enviam (gzip ou brotli). Texto — HTML, CSS, JavaScript —
encolhe tipicamente para 15–20% do tamanho original. É padrão da indústria há duas décadas e
o navegador faz a descompressão sozinho, sem custo perceptível.

**O nginx de produção não comprime nada.** Verificado pedindo explicitamente:

```
curl -sI https://bahia.ba/politica/ -H "Accept-Encoding: gzip, deflate, br"
   -> nenhum cabeçalho content-encoding na resposta
   -> 197.309 bytes transferidos
```

Esses 197 KB caberiam em cerca de 20 KB.

### Por que importa

Vale para **todo** o site, não só para o HTML: a folha de estilo do tema sozinha tem 650 KB,
e também viaja inteira.

Quem paga a conta é principalmente o leitor no celular, em rede móvel — é lá que cada
100 KB vira segundo de espera. E é peso de saída da AWS, ou seja, custo de banda medido em
fatura.

### O que é preciso decidir

**Ligar a compressão no ConfigMap do nginx.** É mudança de uma dezena de linhas
(`gzip on`, `gzip_types`, `gzip_min_length`), **aditiva e reversível** — se algo sair errado,
volta-se o ConfigMap e reinicia-se o pod.

Não é decisão de conteúdo, é de infraestrutura, e o único motivo de não estar feita hoje é
não ser assunto da janela de migração do tema.

**Fica para depois da virada**, com o site novo já estabilizado, para que qualquer efeito
seja atribuível a uma coisa só.

---

## 10. Segurança: a tela de entrada do painel está aberta e visível

**Levantado em 27/08/2026, durante a limpeza do banco. Observação, sem ação tomada.**

### O que é

A tela onde os jornalistas entram para publicar — o painel do WordPress — fica no endereço
padrão, `https://bahia.ba/wp-login.php`, e responde normalmente a qualquer pessoa da internet.
Não há nada escondendo esse endereço.

Isso não é uma invasão nem um problema em curso. É a configuração padrão do WordPress, e é assim
que a maioria dos sites funciona. Registramos porque **alguém já tentou mudar isso e a mudança
foi desfeita pela metade.**

### O que encontramos

Existe instalado no site um programa chamado **WPS Hide Login**, cuja função é exatamente
esconder esse endereço — trocá-lo por outro, conhecido só pela equipe. Ele está **desligado**.
Mas a configuração que ele deixou continua gravada no banco: o endereço secreto seria
`/acesso/`.

O efeito visível hoje: quem digita `bahia.ba/acesso/` **não** chega a lugar nenhum útil. O
WordPress não encontra a página, e o próprio WordPress "adivinha" o endereço mais parecido,
levando o visitante para uma **matéria de política** cujo endereço começa com a mesma palavra.

Ou seja: alguém instalou a proteção um dia, removeu depois, e não limpou a configuração. Ficou
uma instrução órfã no banco e um endereço que leva a lugar errado.

### Por que contamos isso

Não é decisão nossa e não é urgente. Mas quem cuidar de segurança do portal precisa saber de
duas coisas:

1. **A porta de entrada do painel está no endereço que todo robô da internet testa primeiro.**
   Quem quiser proteger melhor tem caminhos: reativar o WPS Hide Login, exigir segundo fator de
   autenticação, ou restringir por endereço de rede.
2. **Se alguém religar o WPS Hide Login sem revisar a configuração**, o painel passa a atender em
   `/acesso/` e o endereço antigo `/wp-admin/` passa a devolver "página não encontrada" — está
   assim gravado. Uma reativação distraída deixaria a redação sem saber por onde entrar.

### O que precisa ser decidido

Se vale investir em proteger a entrada do painel, e por qual caminho. **Nada foi alterado.**

