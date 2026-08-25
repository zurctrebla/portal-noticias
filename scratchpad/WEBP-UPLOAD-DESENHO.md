# Conversão para WebP no upload — desenho e testes

`mu-plugins/bahia-webp-upload.php`. **Escrito e testado, NÃO instalado em lugar nenhum.**
Aguarda a conversa com a redação, que pode mudar o desenho.

---

## Uma correção antes de tudo: o corte em 3.000 cores estava errado

A recomendação aprovada era decidir entre com e sem perdas pela contagem de cores, com corte em
3.000. **Medi, e o corte não funciona.**

| caso | cores | PNG | WebP q85 | WebP sem perdas | quem ganha |
|---|---|---|---|---|---|
| foto | 29.259 | 408 KB | **55 KB** | 260 KB | q85 |
| captura com texto | 1.298 | 131 KB | **22 KB** | 68 KB | q85 |
| arte poucas cores | 2.381 | 89 KB | **9 KB** | 52 KB | q85 |
| PNG grande | 24.411 | 834 KB | **52 KB** | 615 KB | q85 |
| logo com alfa real | 108 | 24 KB | 19 KB | **12 KB** | sem perdas |

Os dois casos abaixo de 3.000 cores — a captura de tela e a arte — ficaram **3× e 6× melhores
COM perdas**, exatamente o oposto do que a regra previa. Sem perdas só ganhou no logo de 108
cores.

**Contagem de cores separa "arte" de "foto", mas não responde qual codificação comprime melhor.**
A pergunta que importa não é o que a imagem *é*, e sim qual arquivo sai menor.

### O que o plugin faz no lugar

Codifica **as duas** e fica com a menor. Sem heurística, sem adivinhação, sempre ótimo.
Custo: uma codificação extra por upload, na casa de dezenas a poucas centenas de milissegundos.

A varredura de **alfa real** continua — mas com outro papel. Ela não decide com/sem perdas;
decide se o canal alfa precisa ser preservado na saída. E é indispensável do jeito que você
pediu, varrendo pixels: na amostra de agosto, **cinco arquivos eram RGBA e nenhum tinha um único
pixel translúcido**. Confiar no tipo declarado teria preservado um canal morto em todos.

---

## 1. Onde entra no fluxo, e por quê ali

```
upload do arquivo
      ↓
validação do WordPress, arquivo movido para uploads/AAAA/MM/
      ↓
>>> filtro wp_handle_upload  <-- AQUI
      ↓
post do anexo é criado          (add_attachment)
      ↓
metadados e derivadas geradas   (wp_generate_attachment_metadata)
      ↓
WP Offload envia ao S3          (as3cf_attachment_file_paths)
```

**A conversão acontece antes de o anexo existir.** É o que elimina de uma vez o problema de
renomear arquivo: não há nada a renomear, porque o anexo já nasce `.webp`. O `wp_as3cf_items`
recebe o caminho certo no primeiro registro, as derivadas são geradas a partir do WebP, e nenhum
metadado precisa ser corrigido depois.

Verificado que nada mais engancha em `wp_handle_upload` — nem o Offload, nem o Smush.

---

## 2. Se a conversão falhar, o upload não se perde

A regra é explícita no código: **qualquer falha devolve o array de upload como veio.**

O método inteiro está em `try { } catch (\Throwable $e)` — `Throwable`, não `Exception`, para
pegar também `Error` de extensão ausente ou estouro de memória. Nesse caminho o arquivo original
já está em disco, validado, e o WordPress segue o fluxo normal como se o plugin não existisse.

Saídas antecipadas, todas devolvendo o upload intacto:

| condição | motivo |
|---|---|
| mime diferente de `image/png` | GIF, JPEG e SVG ficam de fora de propósito |
| arquivo ilegível ou `getimagesize` falha | pode ser PNG corrompido |
| mais de 25 megapixels | um RGBA de 25 MP ocupa ~100 MB cru; o `memory_limit` é 512M |
| nenhuma das duas codificações produziu arquivo | sem candidato |
| economia menor que **15%** | trocar formato por pouco não paga |
| `rename` para o nome final falhou | disco ou permissão |

O caso da economia insuficiente é o que protege contra o absurdo silencioso: se algum PNG já
estiver bem otimizado, ele fica como está.

---

## 3. O original é guardado — e custa quase nada

**Guardado**, como era sua inclinação. O PNG fica ao lado do WebP e sobe para o S3 junto com o
anexo, pelo filtro `as3cf_attachment_file_paths` (o ponto de extensão documentado do Offload,
em `as3cf-utils.php:283`).

Isso significa que o original:

- vai para o **mesmo prefixo com segmento de versão** do anexo;
- é **registrado** em `wp_as3cf_items.extra_info['objects']['bahia_original']`;
- é recuperável por caminho determinístico;
- **não** vira um segundo item na biblioteca de mídia.

O vínculo é a meta `_bahia_webp_original`, que guarda o **nome** do arquivo, não o caminho — o
caminho muda quando o Offload move, o nome não.

### Custo

665 PNGs por mês × 296 KB = **0,19 GB/mês** de material novo.

| depois de | acumulado | USD/mês |
|---|---|---|
| 1 mês | 0,19 GB | 0,01 |
| 6 meses | 1,13 GB | 0,05 |
| 12 meses | 2,25 GB | 0,09 |
| 36 meses | 6,76 GB | 0,27 |

**Custo total do primeiro ano: US$ 0,59.** Terceiro ano: US$ 2,78.

Contra os ~US$ 167/mês que a conversão economiza em tráfego. Guardar o original custa
aproximadamente **0,4% de um único mês de economia** — sua inclinação está certa com folga.

> Ressalva honesta: como o original vai para o mesmo prefixo dos arquivos servidos, uma regra de
> lifecycle no S3 não conseguiria expirar só os originais sem prefixo ou tag próprios. A esse
> custo não vale complicar, mas fica registrado caso um dia o volume mude de ordem de grandeza.

---

## 4. GIF animado: intocado

O plugin só age quando o mime é **`image/png`**. GIF — animado ou não — não passa nem pela
primeira checagem.

Isso é deliberado e vale registrar por quê: há **271 anexos marcados como animados** pelo Smush
no acervo. Se um dia alguém estender este plugin para GIF, é obrigatório detectar animação antes
(procurar múltiplos blocos `NETSCAPE2.0` / mais de um frame) — converter um GIF animado quadro a
quadro com GD ou Imagick sem tratar a animação **produz uma imagem estática**, e a perda é
silenciosa.

O WebP suporta animação, mas exige API diferente da usada aqui. Fora do escopo.

---

## 5. Nome do arquivo, `wp_as3cf_items` e o segmento de versão

**O nome muda** — de `foto.png` para `foto.webp`. Mas, como a troca acontece antes de o anexo
existir, isso não gera nenhuma migração:

| o que | efeito |
|---|---|
| `wp_posts.guid` e `post_mime_type` | nascem já com `.webp` / `image/webp` |
| `_wp_attached_file` | nasce apontando para o `.webp` |
| derivadas (`sizes`) | geradas a partir do WebP, já em WebP |
| `wp_as3cf_items.path` / `source_path` | primeiro registro já é o `.webp` |
| segmento de versão (`.../25160418/`) | é criado pelo Offload no primeiro envio, uma vez, para os dois arquivos |
| conteúdo de posts antigos | **não é tocado** — nenhum post existente referencia o arquivo novo |

Duas armadilhas cobertas no código:

1. **Colisão de nome.** O `wp_unique_filename()` do WordPress já rodou para o `.png`; trocar a
   extensão pode colidir com um `.webp` que já exista na mesma pasta. O plugin chama
   `wp_unique_filename()` de novo, com a extensão nova.
2. **A URL do array de retorno.** Não basta trocar `file`; `url` e `type` precisam acompanhar,
   senão o anexo nasce com URL apontando para um arquivo que não existe mais.

---

## 6. Os cinco casos difíceis

Decisão simulada do plugin sobre arquivos reais do acervo:

| caso | origem | PNG | escolhido | economia | resultado |
|---|---|---|---|---|---|
| foto comum | `9001435` | 408 KB | q85 | **87%** | 55 KB |
| captura de rede social com texto | `9001247` | 131 KB | q85 | **84%** | 22 KB |
| arte com transparência REAL | `9000075` | 24 KB | **sem perdas** | 49% | 12 KB |
| imagem de poucas cores | `550126` | 89 KB | q85 | **90%** | 9 KB |
| PNG grande (1200×800) | `531089` | 834 KB | q85 | **94%** | 52 KB |

Todos convertem; nenhum cai na cláusula dos 15%.

**Transparência conferida no caso 3**, que é o que poderia quebrar:

```
webpinfo 3-alfa-real-ll.webp
  Width: 1151   Height: 229
  Alpha: 1                 <- canal preservado
  Format: Lossless (2)
```

E decodificando o WebP de volta para PNG, a varredura continua encontrando pixels translúcidos —
ou seja, o alfa sobreviveu à ida e à volta, não só ao cabeçalho.

Fidelidade dos dois casos fotográficos, medida pixel a pixel na rodada anterior: diferença média
de 0,49 a 1,59 em 255, com no máximo 0,7% dos pixels desviando mais de 8. Abaixo do perceptível.

---

## 7. O que falta antes de instalar

1. **A resposta da redação.** Se a ferramenta do export 620×400 permitir escolher formato, um
   terço dos PNGs some na origem — e talvez o corte de qualidade deva ser outro.
2. Instalar em homolog e subir imagens de teste pelo `wp-admin` de verdade, exercitando o fluxo
   completo: upload → anexo → derivadas → Offload → CDN.
3. Conferir no `wp_as3cf_items` que o `bahia_original` aparece em `extra_info['objects']`.
4. Confirmar que o editor vê a imagem normalmente na biblioteca e no post.

**Não decidido de propósito:** se vale converter também os **JPEG** no upload. O ganho existe
(WebP costuma render 25–35% sobre JPEG), mas é bem menor que os 87% do PNG, e cada formato a mais
aumenta a superfície de risco. Melhor medir o efeito do PNG primeiro.

---

## 8. Erro de percurso: `mu-plugins/` não tem "commitar sem instalar"

Registrado porque a lição é geral e vale para qualquer rodada futura.

Eu commitei o plugin em `mu-plugins/` e empurrei para `develop` achando que estava apenas
versionando. **Não estava:** `develop` reconstrói a imagem de homolog, e tudo em `mu-plugins/`
entra em vigor no instante em que o pod sobe. Na prática o push instalou o plugin em homolog —
exatamente o que estava combinado para não acontecer antes da conversa com a redação.

Tentei cancelar o build (`gh run cancel`) e não tenho permissão de admin no repositório, então a
correção foi empurrar a trava e deixar o build seguinte anular o anterior. O plugin ficou ativo
em homolog por cerca de dois minutos. Como ele só age em upload de PNG e ninguém publicou nesse
intervalo, não houve efeito — mas isso é sorte, não desenho.

### A correção

O plugin sai pela porta logo depois da checagem de `ABSPATH`:

```php
if (!defined('BAHIA_WEBP_UPLOAD_ATIVO') || !BAHIA_WEBP_UPLOAD_ATIVO) {
    return;
}
```

Fica versionado e inerte. Ligar é definir a constante; desligar é removê-la; e não há estado a
desfazer, porque nada roda antes disso.

### Verificado em homolog

```
arquivo presente ............................. SIM
constante definida ........................... nao
wp_handle_upload  registrado por nos ......... nao
add_attachment    registrado por nos ......... nao
as3cf_attachment_file_paths registrado ....... nao
```

> Uma sutileza que quase me fez ler errado a própria verificação: `class_exists('Bahia_WebP_Upload')`
> devolve **true** mesmo com o `return` antes da classe. O PHP declara classes de nível superior
> ao compilar o arquivo, independentemente do fluxo de execução. O que não roda é o
> `Bahia_WebP_Upload::init()` do final — e é ele que registra os ganchos. **O teste certo é
> `has_filter()`, não `class_exists()`.** A classe existir é um símbolo sem efeito.

### A regra para a próxima vez

Código que deve ficar pronto **sem** entrar em vigor não pode ser só "commitado": em
`mu-plugins/`, commit e instalação são o mesmo ato. Ou nasce atrás de uma trava como esta, ou
fica fora da pasta até a hora de ligar.
