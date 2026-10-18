# PNG: de onde vem, o que custa e o que fazer

Medido em 2026-08-25, contra produção. **Nada aplicado.**

Amostra principal: os 45 PNGs mais recentes de agosto, baixados e convertidos localmente
(`sips` e `cwebp`), para não pesar nos nós de produção.

---

## Resumo

**91% dos PNGs do site são fotografias sem transparência alguma, salvas em PNG sem motivo.**
Convertidos para WebP q85 ficam **9,4× mais leves**, com diferença de pixel abaixo do limiar
perceptível. E **80,5% dos bytes servidos vêm de uploads de 2026** — corrigir só daqui para
frente já pega quase todo o ganho.

---

## 1. De onde vêm os PNGs

### Não é aleatório — mudou em abril/maio

Anexos de imagem por mês, em produção:

| mês | total | jpeg | png | webp | **% PNG** |
|---|---|---|---|---|---|
| 2026-01 | 1.646 | 1.187 | 339 | 65 | 20,6% |
| 2026-02 | 1.632 | 1.152 | 347 | 95 | 21,3% |
| 2026-03 | 1.735 | 1.219 | 363 | 105 | 20,9% |
| 2026-04 | 1.827 | 1.082 | 542 | 151 | **29,7%** |
| 2026-05 | 1.896 | 861 | 916 | 64 | **48,3%** |
| 2026-06 | 1.779 | 901 | 757 | 85 | 42,6% |
| 2026-07 | 1.986 | 997 | 908 | 41 | 45,7% |
| 2026-08 | 1.423 | 718 | 665 | 26 | 46,7% |

Referência: 2024 fechou em 31,1% e 2025 em 32,9%.

**A proporção dobrou entre março e maio de 2026 e ficou lá.** Isso é mudança de ferramenta ou de
rotina na redação, não variação natural. Vale perguntar à equipe o que mudou em abril.

### Dois grupos, por padrão de nome (400 PNGs de jul/ago)

| padrão | quantos | |
|---|---|---|
| nome descritivo — `Dolly Parton Morre`, `jean lucas do bahia`, `Evaldo Macarrão` | 273 (68%) | fotos nomeadas por quem publica |
| começa com dimensões — `620x400 - 2026-08-25T145402.482` | 118 (30%) | export de ferramenta de design no corte padrão do site |
| Canva/sem nome, WhatsApp, captura de tela | 9 (2%) | |

O segundo grupo tem assinatura clara: **largura×altura seguidos de timestamp ISO com
milissegundos**, sempre em 620×400. É uma ferramenta exportando no formato do site — e exportando
em PNG por padrão.

### O que esses arquivos são, tecnicamente (45 medidos)

```
tipo de cor      RGB  40    RGBA  5
alfa REALMENTE usado ................ 0 de 45
dimensão dominante .................. 600x420 (42 de 45)
peso médio .......................... 296 KB
fotográficos (3.000+ cores únicas) .. 41 de 45
arte/texto (menos de 3.000 cores) ... 4 de 45
```

**Nenhum dos cinco RGBA usa transparência de verdade** — todos os pixels amostrados têm alfa
opaco. O canal alfa está lá sem função, ocupando 33% a mais de dados brutos.

Um PNG de 600×420 pesando 296 KB é aproximadamente o dobro do que o arquivo *bruto* de uma
imagem desse tamanho deveria comprimir em qualquer formato com perdas.

---

## 2. O que o Smush está fazendo hoje: lazy load, e só

Versão 3.22.1, **gratuita**. Configuração relevante:

```
png_to_jpg .... OFF      <- a conversão que resolveria existe e está desligada
webp_mod ...... OFF      <- WebP local desligado (pasta smush-webp não existe)
cdn ........... off
auto_resize ... off
lazy_load ..... ON       <- é o terceiro mecanismo de lazy que encontrei
background_images ON
```

E a cobertura de compressão é praticamente nula no material novo:

| período | anexos | com dados do Smush |
|---|---|---|
| ago/2026 | 1.424 | **2 (0,1%)** |
| jul/2026 | 1.986 | 3 (0,2%) |
| jan–jun/2026 | 10.515 | 14 (0,1%) |
| 2025 | 18.826 | 1 (0,0%) |
| **os 45 PNGs da amostra** | 45 | **0** |

> Correção a uma leitura minha durante o levantamento: eu havia consultado a meta_key errada e
> concluído que o Smush nunca comprimira nada. **Ele processou 5.847 anexos** — mas são quase
> todos antigos. Sobre o material de 2026 ele é, na prática, inerte.

**E mesmo ligado não resolveria.** O Smush gratuito faz compressão sem perdas; num PNG
fotográfico isso rende algo entre 5% e 20%, não os 77% que a troca de formato rende. **A alavanca
aqui é formato, não compressão.**

---

## 3. WebP: já funciona, e não precisa de fallback

Três coisas que mudam a conversa:

1. **O site já serve WebP hoje.** Há **773 anexos** com mime `image/webp` em produção, e vi um
   deles ser buscado normalmente pelo navegador (`Lula-e1775663567617.webp`). Não é hipótese.
2. **Fallback não é mais necessário.** WebP tem suporte universal em navegador desde 2021 —
   Safari 14+, Chrome, Firefox, Edge. Montar máquina de fallback em 2026 é resolver um problema
   que não existe mais.
3. **Variar por `Accept` no CloudFront é possível, mas é o caminho ruim.** Exigiria encaminhar o
   header `Accept` na cache policy — o que **multiplica as entradas de cache** — e ainda assim o
   S3 sozinho não escolhe formato: precisaria de Lambda@Edge ou S3 Object Lambda. É a opção C do
   documento de imagens, com o mesmo veredito: infraestrutura nova para resolver o que a conversão
   no upload resolve sem ela.

Do lado dos plugins: o WebP local do Smush está desligado, e na versão gratuita ele grava
arquivos paralelos que dependem de regra de reescrita no servidor — o que não combina com o
Offload, que serve direto do S3/CloudFront.

**O caminho limpo é converter no upload e guardar um arquivo só.**

---

## 4. Converter é viável — e mede-se o que se perde

Convertendo os 45 PNGs (13.316 KB no total):

| formato | total | vs PNG | média por imagem |
|---|---|---|---|
| **PNG (hoje)** | 13.316 KB | — | 296 KB |
| WebP q75 | 1.028 KB | **13,1×** | 23 KB |
| WebP q80 | 1.196 KB | 11,2× | 27 KB |
| **WebP q85** | **1.432 KB** | **9,4×** | **32 KB** |
| WebP q90 | 1.828 KB | 7,3× | 41 KB |
| JPEG q80 | 3.104 KB | 4,3× | 69 KB |
| JPEG q85 | 3.616 KB | 3,7× | 80 KB |
| JPEG q90 | 3.788 KB | 3,5× | 84 KB |

**WebP q90 ainda pesa metade de um JPEG q80.** Não há cenário em que JPEG ganhe de WebP aqui.

### Quanto se degrada, em número

Diferença por pixel contra o PNG original, medida decodificando os dois lados:

| caso | formato | diferença média | máxima | pixels com desvio > 8 |
|---|---|---|---|---|
| arte com texto | JPEG q82 | 0,49/255 | 13 | **0,0%** |
| arte com texto | WebP q80 | 1,26/255 | 19 | 1,5% |
| foto | JPEG q82 | 1,59/255 | 22 | 0,7% |
| foto | WebP q80 | 2,74/255 | 29 | 4,7% |

O caso difícil que testei é uma **captura de post de rede social, texto claro sobre fundo escuro**
— exatamente onde JPEG costuma criar halo. Comparei as duas versões lado a lado no tamanho em que
o site renderiza: indistinguíveis, e o número confirma (0,0% dos pixels com desvio acima de 8).

Detalhe contraintuitivo: **para texto, o JPEG q82 saiu mais fiel que o WebP q80.** É argumento
para usar WebP em qualidade mais alta (q85–q90), onde ele fica mais fiel que o JPEG **e** ainda
metade do tamanho.

### Como distinguir automaticamente o que não pode ser convertido

Duas checagens, nesta ordem, ambas baratas:

1. **O alfa é realmente usado?** Não basta ser RGBA — é preciso varrer e achar pixel com
   alfa < 250. Na amostra, **5 arquivos eram RGBA e nenhum usava transparência**. Se usar de
   verdade: WebP também suporta alfa, então converte igual; só o JPEG estaria descartado.
2. **Quantas cores únicas?** Abaixo de ~3.000 é arte/texto/captura; acima é fotografia. Na amostra
   o corte separou limpo: os quatro de baixo tinham 999 a 2.005 cores, os fotográficos 10.000 a
   26.000. Para arte com pouca cor, WebP **sem perdas** costuma bater o PNG e evita qualquer
   discussão de qualidade.

Ou seja: **WebP resolve os dois ramos** — com perdas para foto, sem perdas para arte — e nenhum
caso do acervo exige manter PNG.

---

## 5. Acervo ou daqui para frente: 80% do ganho está no que ainda vai ser publicado

Distribuição, por ano do arquivo, das imagens efetivamente servidas em produção (home + três
archives, 101 imagens medidas):

| ano | arquivos | bytes | % dos bytes |
|---|---|---|---|
| **2026** | 82 | 9.439 KB | **80,5%** |
| 2025 | 6 | 882 KB | 7,5% |
| 2024 | 4 | 498 KB | 4,3% |
| 2023 | 1 | 529 KB | 4,5% |
| 2022 e antes | 8 | 369 KB | 3,2% |

**80,5% dos bytes servidos vêm de uploads de 2026.** Um portal consome o próprio acervo de forma
muito concentrada no recente — a home e os archives de primeira página são quase inteiramente
material do mês.

Consequência prática: **corrigir só daqui para frente captura a maior parte do ganho quase
imediatamente**, sem tocar em 153 mil arquivos. Em um mês de publicação (~1.400 imagens), a
fatia nova já domina a home e os archives rasos.

O acervo antigo é território do **caminho B** (`PLANO-REGENERACAO.md`), e há uma sinergia a
registrar: aquele job já vai reprocessar cada anexo. **Converter formato na mesma passada custa
quase nada a mais** — é o mesmo download, o mesmo decode, o mesmo upload.

---

## 6. Recomendação

**Converter no upload para WebP q85**, com as duas checagens da seção 4 decidindo entre WebP com
perdas (foto) e WebP sem perdas (arte, texto, captura, transparência real).

Por quê, em vez das alternativas:

- **em vez de ligar o `png_to_jpg` do Smush:** entrega 4,3× onde o WebP entrega 9,4×, perde o
  canal alfa nos casos que um dia precisem dele, e depende de um plugin que hoje não processa
  0,1% dos uploads;
- **em vez de ligar a compressão do Smush:** compressão sem perdas em PNG fotográfico rende 5–20%,
  não 77%. Não é o mesmo problema;
- **em vez de WebP com fallback ou negociação por `Accept`:** o site já serve WebP sem incidente
  há 773 arquivos, e o suporte de navegador é universal. Fallback aqui é complexidade sem
  contrapartida.

### Efeito estimado

Nas páginas medidas, PNG é **59,9% dos bytes de imagem**. Se essa fatia ficar 9,4× menor, o total
de imagens cai para ~46% do atual — **uma redução de ~53% nos bytes servidos**.

Ao volume de hoje (~220 GB/dia no CloudFront), seriam **~117 GB/dia a menos**, ou cerca de
**160–170 USD/mês** à taxa efetiva de 0,0477 USD/GB — mais rápido para o leitor de celular, que
é o motivo que importa.

**Premissas, ditas:** supõe que a proporção de PNG das páginas medidas vale para o tráfego geral,
e que a conversão alcança as imagens novas. Não considera converter também os JPEG existentes,
que renderiam mais. O número honesto sai da curva do CloudFront depois de aplicado, como no
caminho A.

### Duas perguntas para a redação, antes de qualquer código

1. **O que mudou em abril/maio de 2026?** A proporção de PNG dobrou. Se for uma ferramenta com
   opção de formato, mudar a configuração dela resolve na origem — e é mais barato que qualquer
   conversão no servidor.
2. **O export 620×400 é de que ferramenta?** São 30% dos PNGs, sempre no mesmo corte. Se ela
   permitir exportar em WebP ou JPEG, esse terço some do problema sem tocar no WordPress.
