# Caminho B — plano de regeneração de derivadas

**Não executar.** Plano para aprovação. Levantado em 2026-08-25.

Alvo: 153.842 anexos de imagem, dos quais **48,3% (~74.300) não têm derivada nenhuma** e hoje
são servidos em tamanho cheio — inclusive antes da virada.

---

## 0. Três descobertas que mudam o desenho do job

### 0.1 O bucket de mídia está em **sa-east-1**, não em us-east-1

```
aws s3api get-bucket-location --bucket static.bahia.ba  ->  sa-east-1
```

O RDS de produção está em **us-east-1**. Ou seja, o job não tem uma região "natural": ele lê
metadados de um lado e move arquivos do outro.

### 0.2 Uma EC2 avulsa exigiria mexer no SG do RDS de produção

O `sg-0234245542eb43738` ("MySQL") libera 3306 para `10.1.0.0/16`, `10.2.0.0/16` e o SG `EC2`.
Uma spot fora desses ranges **não conecta no banco** — seria preciso acrescentar uma regra de
entrada no SG que está anexado ao ENI do RDS de produção. É exatamente o grupo marcado como
intocável no `LIMPEZA-AWS.md`.

**Consequência:** a spot avulsa custa mais barato em dólar e mais caro em risco. A alternativa
sem tocar em SG é rodar **dentro do cluster de produção, em nó dedicado** — banco e S3 já
funcionam ali, por rede e por IRSA.

### 0.3 O registro por tamanho vive em `wp_as3cf_items.extra_info`

```
wp_as3cf_items: 157.275 linhas (uma por ANEXO, não por tamanho)
  path                 wp-content/uploads/2026/08/25135312/dra-Karine-Andrade.png   <- COM segmento
  source_path          2026/08/dra-Karine-Andrade.png                               <- SEM segmento
  extra_info           a:2:{s:7:"objects";a:9:{s:15:"__as3cf_primary";...;s:6:"medium";...}}
```

O segmento de versão (`25135312`) é **por anexo** e já existe na coluna `path`. Cada derivada
nova entra em `extra_info['objects'][<nome_do_tamanho>]` como
`['source_file' => 'arquivo-269x187.jpg', 'is_private' => false]`, e o arquivo vai para o mesmo
diretório do `path`.

**É isto que impede o "regenerar e pronto".** Escrever o arquivo no S3 sem atualizar
`extra_info` deixa o objeto pago e invisível; atualizar `extra_info` sem subir o arquivo
produz 404 no CDN.

---

## 1. Custo, revisado com a região no lugar

| item | conta | valor |
|---|---|---|
| PUT (sa-east-1) | 1.046.000 ÷ 1000 × US$ 0,005 | **US$ 5,23** |
| GET dos originais | 153.842 ÷ 1000 × US$ 0,0004 | US$ 0,06 |
| armazenamento | 38,4 GB × US$ 0,0405 (sa-east-1) | **US$ 1,56/mês** |
| transferência entre regiões, se rodar em us-east-1 | ~28 GB × US$ 0,138 (saída de SP) | **US$ 3,86** |
| compute — nó dedicado t3.large ~20 h | 20 × US$ 0,0832 | **US$ 1,66** |
| **TOTAL** | | **~US$ 11 uma vez + US$ 1,56/mês** |

Ainda abaixo de US$ 15. **A economia de CloudFront paga isso em menos de uma semana.**

---

## 2. Onde rodar — duas opções

| | **Nó dedicado no EKS de produção** (recomendado) | **Spot avulsa** |
|---|---|---|
| acesso ao banco | já funciona | **exige nova regra no SG do RDS de produção** |
| acesso ao S3 | já funciona (IRSA) | precisa de credencial nova |
| impacto nos nós que servem | nenhum, com taint + nodeSelector | nenhum |
| custo compute | ~US$ 1,66 (t3.large 20 h) | ~US$ 2–3 |
| risco | baixo | mexe em recurso intocável |

**Recomendação: nó dedicado.** Subir o nodegroup de 4 para 5 com um nó *tainted*, rodar o Job
com `tolerations` e `nodeSelector`, e devolver a 4 no fim. A diferença de custo é de um dólar;
a diferença de risco não é.

> Isso **não** contradiz a decisão de não baixar o mínimo do nodegroup. Aqui é subir
> temporariamente, não descer.

---

## 3. Como o job fica retomável, não recomeçável

O ponto que você levantou. A retomabilidade não vem de um arquivo de progresso — vem do
**próprio banco**, com marcador por anexo:

```
meta_key  = _bahia_regen_v1
meta_value = <timestamp da conclusão>
```

A seleção do lote é sempre "quem ainda não tem o marcador":

```sql
SELECT p.ID
FROM wp_posts p
LEFT JOIN wp_postmeta m ON m.post_id = p.ID AND m.meta_key = '_bahia_regen_v1'
WHERE p.post_type = 'attachment'
  AND p.post_mime_type LIKE 'image/%'
  AND m.post_id IS NULL
ORDER BY p.ID
LIMIT 200;
```

Se a spot sumir, se o pod for morto, se alguém der Ctrl-C — **basta rodar de novo**. Ele
recomeça de onde parou, sem lista externa e sem estado em disco.

Antes de começar, criar o índice que torna essa consulta barata (o `wp_postmeta` é a tabela
grande):

```sql
-- meta_key já é indexado no schema padrão do WP; confirmar antes de criar qualquer coisa
SHOW INDEX FROM wp_postmeta WHERE Key_name IN ('meta_key','post_id');
```

### Ordem das operações dentro de um anexo — importa

```
1. baixar o original do S3
2. gerar as derivadas que faltam, em disco temporário
3. SUBIR todas para o S3            <- primeiro o arquivo
4. atualizar _wp_attachment_metadata
5. atualizar wp_as3cf_items.extra_info
6. gravar o marcador _bahia_regen_v1  <- por último, sempre
7. apagar o temporário
```

Interromper em qualquer ponto antes do 6 deixa, no pior caso, **objetos órfãos no S3** — que
custam centavos e são sobrescritos na próxima passada. **Nunca deixa metadado apontando para
arquivo inexistente**, porque o arquivo sempre vai primeiro.

O passo 5 tem de ser **leitura-modificação-escrita da linha inteira** de `extra_info`, com
`SELECT ... FOR UPDATE` ou releitura imediatamente antes, para não perder alterações
concorrentes entre os 8 processos. Como cada processo trata anexos distintos, o conflito real
é improvável — mas a releitura é barata.

---

## 4. Pressão no RDS

Por anexo: 1 SELECT de metadata, 1 SELECT em `as3cf_items`, 2 UPDATEs, 1 INSERT do marcador.
Em 20 horas com 8 processos:

```
153.842 anexos ÷ 72.000 s  ≈  2,1 anexos/s  ≈  6,4 escritas/s
```

O RDS de produção roda hoje a **62 write IOPS de média** (pico 305). Somar ~6/s é ruído.

**Mesmo assim, três travas:**

1. `sleep` configurável entre anexos, começando em 100 ms por processo
2. Parar sozinho se `Threads_running` passar de um limite — o `carga.sh` já mostra como ler
3. **Não rodar entre 08h e 23h.** A curva horária diz que o vale é 02h–07h (Salvador), e ali
   o ganho é modesto (2,1× de razão pico/vale), mas o job leva 20 h — então ele vai atravessar
   horário de movimento. Preferir madrugada de sábado para domingo, quando o volume é menor.

Vale lembrar: o **espaço livre do RDS de produção é de 3,0 GB**, caindo 3,1 MB/dia. O marcador
são 153.842 linhas novas em `wp_postmeta` (~15 MB com índice). Cabe, mas confira o espaço antes
de disparar.

---

## 5. Lote piloto — 1.000 anexos

Obrigatório antes dos 153 mil. Escolher **estratificado, não os 1.000 primeiros**: 150 de cada
ano de 2017 a 2022 (a faixa sem derivada nenhuma) e 100 de 2016.

### Verificação do piloto, item por item

```bash
# 1. contagem: o marcador bate com o pedido?
#    esperado: 1000

# 2. para uma amostra de 30, cada tamanho novo responde 200 no CDN?
#    (é o teste que pega o erro de extra_info — arquivo no S3 mas invisível)
for id in $AMOSTRA; do
  # obter as URLs por wp_get_attachment_image_src($id, $tamanho) e testar
  curl -sSI -o /dev/null -w '%{http_code} %{size_download}\n' "$URL"
done

# 3. nenhum 404 e nenhum tamanho devolvendo o ORIGINAL
#    (comparar o basename retornado com o basename do original: têm de diferir)

# 4. o objeto está no caminho COM segmento de versão?
aws s3 ls s3://static.bahia.ba/wp-content/uploads/2019/03/ --recursive | grep -c '269x187'

# 5. extra_info tem a chave do tamanho novo?
#    desserializar e conferir objects['td_218x150']['source_file']

# 6. a página renderiza? abrir 5 posts antigos do piloto e medir o peso antes/depois
```

**Critério de aprovação:** os 6 itens limpos, e o peso das imagens dessas páginas caindo.
Qualquer 404 aborta o projeto até entender a causa.

---

## 6. Como abortar sem deixar estado inconsistente

- **Durante:** matar o processo. Pela ordem da seção 3, o pior resíduo é objeto órfão no S3.
  Nenhum anexo fica meio-registrado, porque o marcador é o último passo.
- **Depois, se der errado:** o rollback é possível porque nada é destrutivo — nenhuma
  operação apaga ou sobrescreve o original, nem remove derivada existente. Reverter é:
  1. remover as chaves novas de `_wp_attachment_metadata` e de `extra_info`
  2. remover o marcador `_bahia_regen_v1`
  3. opcionalmente apagar os objetos novos do S3 (ou deixar: custam US$ 1,56/mês)

  Para isso funcionar, **o job precisa registrar quais chaves acrescentou por anexo** —
  gravar a lista no próprio marcador em vez de só o timestamp:
  `_bahia_regen_v1 = {"ts":..., "sizes":["td_218x150","td_300x0",...]}`.
  Sem isso, não há como distinguir o que era antigo do que foi criado.

- **Ponto de não-retorno:** não existe. Nenhuma etapa é destrutiva.

---

## 6.5 O acervo antigo em PNG entra NESTA passada — não é projeto separado

Decidido em 25/08. O `DIAGNOSTICO-PNG.md` mostrou que 91% dos PNGs são fotografias sem
transparência alguma, e que convertê-los para WebP q85 rende ~9× de redução. Para o material
**novo**, isso é resolvido no upload (`bahia-webp-upload.php`). Para o **acervo**, a conversão
entra aqui.

**Por que na mesma passada:** o job já vai, para cada anexo, (1) baixar o original do S3,
(2) decodificar, (3) redimensionar e (4) subir. Converter o formato usa exatamente o mesmo
download, o mesmo decode e o mesmo upload. **O custo marginal é a codificação — e o WebP
codifica mais rápido que o PNG que ele substitui.**

Tratar isso como projeto à parte significaria baixar e decodificar 153.842 imagens duas vezes,
pelo mesmo resultado.

### O que muda no desenho do job

- ao gerar cada derivada, gravar em **WebP** em vez do formato de origem, aplicando a mesma
  regra do mu-plugin: codificar com perdas (q85) e sem perdas, ficar com o menor, e manter o
  original se a economia for menor que 15%;
- a checagem de **alfa real** (varrer pixels, não confiar no tipo RGBA) vale igual aqui;
- o **original permanece intocado** no S3. A regeneração só acrescenta derivadas; em nenhum
  momento apaga ou reescreve o arquivo que veio da redação;
- `_wp_attachment_metadata['sizes'][*]['mime-type']` passa a ser `image/webp` nessas entradas —
  é o campo que o WordPress usa para montar a URL, então precisa estar correto;
- a verificação nº 2 do piloto (cada tamanho novo responde 200 no CDN) já cobre isso sem
  alteração.

### Efeito somado

Os 48,3% do acervo que hoje não têm derivada nenhuma passariam de originais de ~184 KB para
derivadas WebP de **10 a 30 KB**, em vez dos 10–60 KB estimados na seção 7. É o mesmo trabalho,
com resultado consideravelmente melhor.

---

## 7. Sequência proposta

| # | passo | duração |
|---|---|---|
| 1 | Confirmar espaço no RDS e índices em `wp_postmeta` | minutos |
| 2 | Escrever o job com marcador e ordem da seção 3 | — |
| 3 | **Piloto de 1.000 em homolog**, com as 6 verificações | 1 h |
| 4 | **Piloto de 1.000 em produção**, mesmas verificações | 1 h |
| 5 | Parada para avaliação — medir peso de página e CloudFront | 1 dia |
| 6 | Subir o nó dedicado (nodegroup 4 → 5, com taint) | 10 min |
| 7 | Rodar o restante, madrugada de sábado, 8 processos | ~20 h |
| 8 | Devolver o nodegroup a 4 | 5 min |
| 9 | Medir CloudFront por 7 dias | 1 semana |

**Ganho esperado:** os 48,3% sem derivada saem de originais de ~184 KB para derivadas de
10 a 60 KB. Combinado com o caminho A, o tráfego deve ficar **abaixo do patamar pré-virada**.

---

# Adendo — as três perguntas em aberto

## A. O lote piloto de mil anexos, e o que exatamente se confere

### Como escolher os mil

Estratificado, nunca "os mil primeiros" — os primeiros são de 2016 e não representam o problema.

| faixa | quantos | por quê |
|---|---|---|
| 2017 a 2022 | 150 de cada ano = **900** | é a faixa **sem derivada nenhuma**, o alvo real do projeto |
| 2016 | 60 | tem mistura: 40% sem derivada, 60% com legado |
| 2023 a 2026 | 40 | controle — já têm legado; servem para provar que o job **não estraga** o que existe |

Dentro de cada ano, pegar em cinco pontos (início, 25%, 50%, 75%, 95%), como foi feito na
amostragem do diagnóstico, para não cair todo num mesmo lote de importação.

### As sete verificações, com critério de aprovação

| # | o que se confere | como | aprova se |
|---|---|---|---|
| 1 | **cobertura** | `COUNT` de `_bahia_regen_v1` | = 1.000 |
| 2 | **arquivo existe no CDN** | para 100 anexos sorteados, `HEAD` em cada tamanho novo | **zero** ≠ 200 |
| 3 | **não voltou o original** | comparar o `basename` devolvido por `wp_get_attachment_image_src` com o do original | todos **diferem** |
| 4 | **caminho com segmento de versão** | o `path` do objeto bate com o prefixo já gravado em `wp_as3cf_items.path` | 100% |
| 5 | **registro no offload** | desserializar `extra_info` e achar `objects[<tamanho>]['source_file']` | presente para todo tamanho novo |
| 6 | **metadata coerente** | `_wp_attachment_metadata['sizes'][<tamanho>]` com `file`, `width`, `height` | presente e com dimensões plausíveis |
| 7 | **página real** | abrir 5 posts antigos do piloto e medir o peso das imagens antes/depois | peso cai, layout igual |

**Qualquer 404 na verificação 2 aborta o projeto** até entender a causa. É o teste que pega o
erro mais provável — arquivo no S3 mas invisível para o CDN por `extra_info` desatualizado, ou o
inverso.

O piloto roda **primeiro em homolog** e só depois em produção, com as mesmas sete verificações.

---

## B. Como provar, no fim, que arquivo e `wp_as3cf_items` estão coerentes na faixa inteira

Conferir 153.842 anexos um a um pelo CDN é inviável — seriam ~1 milhão de `HEAD`. A prova se
monta em três camadas, da mais barata para a mais cara:

### Camada 1 — reconciliação por listagem (cobre 100%, sem tocar no CDN)

Uma listagem completa do bucket já existe como técnica no `COMANDOS-VPS.md`. Aqui ela serve
para outra coisa:

```bash
aws s3 ls s3://static.bahia.ba/wp-content/uploads/ --recursive \
  | awk '{ $1="";$2="";$3="";sub(/^ +/,""); print }' | sort > /tmp/s3-real.txt
```

E do lado do banco, gerar a lista do que **deveria** existir, a partir de
`wp_as3cf_items.path` + cada `extra_info['objects'][*]['source_file']`:

```
esperado = dirname(path) + '/' + source_file    (para cada objeto de cada anexo)
```

```bash
comm -23 /tmp/esperado.txt /tmp/s3-real.txt   # registrado no banco e AUSENTE no S3  -> 404 no site
comm -13 /tmp/esperado.txt /tmp/s3-real.txt   # existe no S3 e NÃO registrado        -> objeto órfão, paga e não serve
```

**Critério: a primeira lista tem de estar vazia.** A segunda pode ter entradas — são órfãos de
execuções interrompidas, inofensivos, e o comando de limpeza sai daí.

Isto é uma comparação de dois arquivos de texto: cobre os 153.842 anexos e o milhão de objetos
sem uma única requisição HTTP.

### Camada 2 — coerência metadata × offload (cobre 100%, só banco)

Para todo anexo com o marcador, o conjunto de chaves de `_wp_attachment_metadata['sizes']` tem
de ser **subconjunto** das chaves de `extra_info['objects']`. Uma consulta que percorre os dois
campos e conta divergências; roda em minutos e não sai do RDS.

### Camada 3 — amostra viva (300 anexos, ~2.000 `HEAD`)

Sorteio estratificado por ano, batendo no CDN de verdade, como na verificação 2 do piloto.
É o que confirma que as camadas 1 e 2 não estão se enganando juntas.

**As três camadas juntas dão prova de cobertura total com custo de uma listagem e uma consulta.**

---

## C. O que acontece se o nó for removido no meio

É o mesmo caso de a spot sumir — e o desenho já responde, mas vale explicitar por etapa:

| onde o job estava | o que fica | como se recupera |
|---|---|---|
| baixando o original | nada | nada a fazer |
| gerando derivadas em disco | arquivos no disco efêmero do pod, que some junto | nada a fazer |
| **subindo para o S3** (passo 3) | alguns objetos novos no S3, **sem** registro | ficam órfãos; a Camada 1 os lista; a re-execução sobrescreve |
| entre o passo 3 e o 6 | objetos no S3, metadata talvez atualizado, **sem marcador** | a re-execução refaz o anexo inteiro, sobrescrevendo |
| depois do marcador | anexo completo | é pulado na próxima passada |

**Em nenhum ponto o site fica servindo metadado que aponta para arquivo inexistente**, porque o
arquivo sempre vai antes. O pior caso é gastar de novo o processamento de um anexo.

### O detalhe operacional que evita a pior versão disso

Se o nó for removido pelo **Cluster Autoscaler** durante o job, o pod é despejado sem aviso útil.
Duas travas:

1. **`cluster-autoscaler.kubernetes.io/safe-to-evict: "false"`** na anotação do pod do Job —
   impede o autoscaler de escolher aquele nó para reduzir.
2. Job com **`backoffLimit`** e `restartPolicy: OnFailure`, para que o Kubernetes recrie o pod
   sozinho. Como a seleção do lote é sempre "quem não tem marcador", o pod novo continua de onde
   o antigo parou, sem intervenção.

E, no fim, **devolver o nodegroup a 4 só depois de confirmar que o Job terminou** — senão a
redução de 5 para 4 pode escolher justamente o nó do job.

> Observação: o nó dedicado sobe com **taint**, então o autoscaler não moveria carga do site para
> ele nem o contrário. O risco acima é só o de o autoscaler **remover** o nó, não o de misturar
> cargas.
