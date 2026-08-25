# Homolog e produção dividem o bucket de mídia

Levantado em 2026-08-25. **Nada alterado.**

---

## O resumo, sem rodeio

**Homolog pode apagar arquivos de produção.** Não é risco teórico: 100% dos caminhos S3
amostrados são idênticos nos dois ambientes, e o mecanismo de remoção já foi observado
funcionando em homolog nesta mesma sessão.

A contaminação por escrita — homolog gravando no bucket de produção — existe, mas é pequena:
**70,1 MB**. O problema grave é o outro sentido.

---

## 1. Desde quando, e quanto de homolog já está lá

Homolog nasceu do retrato de produção de 28/07 e herdou a configuração inteira do WP Offload:
**mesmo bucket, mesma região, mesmo prefixo, mesma distribuição do CloudFront.**

```
                        produção                homolog
bucket .............     static.bahia.ba         static.bahia.ba      <- igual
região .............     sa-east-1               sa-east-1
object-prefix ......     wp-content/uploads/     wp-content/uploads/  <- igual
object-versioning ..     true                    true
delivery-domain ....     d1x4bjge7r9nas.cf.net   d1x4bjge7r9nas.cf.net <- igual
as3cf_items ........     157.302                 155.601
```

O primeiro objeto gravado por homolog é de **2026-07-28 06:15:58** — ou seja, desde o primeiro dia.

### O que é de homolog, e dá para identificar

| | |
|---|---|
| anexos nascidos em homolog | **85** |
| objetos no S3 (anexo + derivadas) | **~886** |
| espaço ocupado | **70,1 MB** |
| período | 28/07 a 24/08 |

**Sim, dá para identificar**, e com precisão: o Offload grava cada anexo sob uma pasta de versão
própria (`wp-content/uploads/2026/08/24073550/`). As 85 pastas criadas por homolog estão listadas
e não se misturam às de produção. A lista saiu de
`as3cf_items JOIN wp_posts WHERE post_date >= '2026-07-28'` no banco de homolog.

Para o bucket inteiro, a regra é: **objeto cujo caminho não aparece em nenhum
`wp_as3cf_items` de produção é de homolog ou é órfão.**

---

## 2. Limpeza de mídia em homolog alcança produção? **Alcança. Provado.**

Amostra determinística de IDs presentes nos dois bancos:

```
ids em AMBOS ............ 142
com path IDENTICO ....... 142  (100,0%)
com path diferente ...... 0
```

Exemplo, o mesmo objeto nos dois bancos:

```
id 547158  ->  wp-content/uploads/2026/07/27205301/INTERNAS-A-Assinatura-...-1536x1024-1.jpg
```

Os ~155.500 itens de homolog anteriores ao retrato apontam para **exatamente os mesmos objetos
que produção serve**. Como o Offload apaga do bucket ao apagar o anexo, qualquer uma destas ações
em homolog remove arquivo de produção:

- apagar uma imagem da biblioteca de mídia (a lixeira não protege: `wp_delete_attachment` com
  `force_delete` remove do bucket);
- ação em massa "Remove from bucket" do próprio Offload (`Remove_Provider_Handler`);
- qualquer script que chame `wp_delete_attachment()`;
- plugin de limpeza de mídia órfã — e este é o pior, porque em homolog **quase toda a mídia
  parece órfã**: o banco é de 28/07 e os posts que referenciam as imagens novas não existem lá.

O mecanismo não é hipótese. Na validação do WebP, nesta sessão, apaguei 24 anexos de teste em
homolog e os objetos correspondentes sumiram do bucket — conferido pasta a pasta. Eram objetos
criados pelo próprio teste; se tivessem sido anexos anteriores a 28/07, teriam sido imagens de
produção.

> **Isto é o achado que importa deste documento.** O resto é arrumação.

---

## 3. O desenho certo

O ponto que decide entre as opções: **`bucket` e `path` são gravados POR LINHA em
`wp_as3cf_items`.** Mudar a configuração do plugin muda apenas o que for gravado dali para
frente — as 155 mil linhas existentes continuam apontando para onde apontam hoje.

Ou seja: **nenhuma mudança de configuração, sozinha, elimina o risco de remoção.**

### Trava de remoção (resolve o risco grave, hoje)

Um mu-plugin só de homolog que devolve lista vazia no filtro que o Offload usa para montar o que
será apagado do bucket:

```php
add_filter('as3cf_remove_source_files_from_provider', '__return_empty_array', 99);
```

É o mesmo filtro que usei no plugin de WebP, então já está verificado que ele governa a remoção.
Efeito: homolog **nunca** apaga objeto do bucket. Apagar um anexo em homolog passa a remover só o
registro no banco de homolog — que é o comportamento desejado num ambiente de teste.

Custo: zero. Risco: um objeto criado por homolog e depois apagado vira órfão de 70 MB no máximo.
Ligar pela chave de ambiente que já existe (`bahia-flags.php`).

### Prefixo próprio (resolve a contaminação por escrita)

`object-prefix` de `wp-content/uploads/` para `hml/wp-content/uploads/`. A partir daí homolog
grava fora do espaço de produção, e limpar o que é de homolog vira um `aws s3 rm --recursive`
num prefixo.

Não mexe no que já existe, e não tem custo. **Conferir antes** se a distribuição do CloudFront
serve `hml/...` — o *origin path* precisa estar vazio, senão as URLs novas de homolog quebram.

### Bucket separado (isolamento completo)

| item | conta | valor |
|---|---|---|
| armazenamento | 50,8 GB × US$ 0,0405 | **US$ 2,06/mês** |
| cópia inicial | 866.855 objetos × US$ 0,005/1000 (COPY) | **US$ 4,33 uma vez** |
| GET da cópia | 866.855 × US$ 0,0004/1000 | US$ 0,35 |

**Cerca de US$ 2/mês e US$ 5 uma vez.** Muito mais barato do que eu esperava antes de medir —
o bucket tem 50,8 GB, não centenas.

O que ele exige, e é aqui que o custo real aparece:

1. copiar 866.855 objetos (`aws s3 sync`, horas de relógio);
2. **`UPDATE wp_as3cf_items SET bucket = '<novo>'` em ~155 mil linhas** de homolog;
3. **uma entrega própria** — hoje homolog e produção usam a mesma distribuição
   `d1x4bjge7r9nas.cloudfront.net`. Um bucket novo precisa de distribuição nova, ou de um segundo
   *origin* com *behavior* por caminho na distribuição atual;
4. manter a cópia atualizada, ou aceitar que homolog envelheça.

### Recomendação

**Trava de remoção agora, prefixo próprio em seguida, bucket separado só se houver motivo além
deste.** A trava elimina o risco que pode causar perda irreversível; o prefixo elimina a sujeira;
o bucket separado resolve elegância e custa trabalho, não dinheiro.

---

## 4. O que muda em `wp_as3cf_items` e nas URLs

| mudança | `wp_as3cf_items` | URLs de homolog |
|---|---|---|
| trava de remoção | nada | nada |
| prefixo novo | só linhas **novas** ganham o prefixo | só as **novas** mudam; as antigas seguem servindo do caminho atual |
| bucket novo, sem `UPDATE` | linhas antigas seguem com o bucket velho | continuam apontando para produção — **não resolve nada** |
| bucket novo, com `UPDATE` | ~155 mil linhas alteradas | **todas** mudam; exige a cópia feita antes, senão o acervo de homolog dá 404 |

Detalhe que evita uma armadilha: a coluna `path` guarda o caminho **com** o segmento de versão
(`2026/08/24073550/arquivo.png`) e `source_path` guarda o caminho local **sem** ele
(`2026/08/arquivo.png`). Uma migração que mexa em caminho tem de tratar os dois campos, e o
`extra_info` traz ainda um `source_file` por tamanho.

---

## 5. Como testar o WebP sem contaminar produção

A validação do WebP que já rodou **escreveu 886 KB no bucket de produção e depois apagou**.
Funcionou e o bucket ficou limpo, conferido pasta a pasta — mas foi mais arriscado do que eu
sabia na hora, e não deve ser repetido assim.

Três formas, da mais simples à mais completa:

**a) Prefixo próprio antes do teste** — mudar `object-prefix` de homolog para
`hml/wp-content/uploads/`. Tudo que o teste gravar cai fora do espaço de produção, e a limpeza é
um `aws s3 rm s3://static.bahia.ba/hml/ --recursive`. É a opção que eu recomendaria: resolve o
teste e já é o passo 2 do desenho definitivo.

**b) Desligar `copy-to-s3` durante o teste** — os arquivos ficam locais e nada vai ao bucket.
Simples e reversível, mas **deixa de exercitar o caminho do Offload**, que é justamente onde o
plugin de WebP faz a parte mais delicada (registrar o original em `bahia_original`). Serve para
medir conversão e tempo, não para validar integração.

**c) Testar como foi feito e limpar** — funciona, e agora com a correção do filtro de remoção o
original também sai. Mas depende de a limpeza não falhar, e o modo de falhar é deixar lixo no
bucket que serve o site.

**Recomendo (a).** E vale notar: com a trava de remoção da seção 3 ligada, a opção (c) deixaria
de funcionar, porque homolog não apagaria mais nada do bucket. Mais uma razão para o prefixo.
