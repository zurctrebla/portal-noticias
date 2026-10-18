# Apaguei duas imagens de produção — 25/08/2026

**Erro meu, causa conhecida, sem desculpa.** Registro completo para recuperação.

## O que aconteceu

Ao limpar anexos de teste em homolog, usei filtro por título com `LIKE`:

```sql
post_title LIKE 'AB %' OR post_title LIKE 'LOTE %' OR post_title LIKE 'RD %'
```

O `LIKE` do MySQL é **insensível a maiúsculas** com a collation padrão. Dois anexos reais casaram:

| id | título | por quê casou |
|---|---|---|
| 313723 | **lote** leião saeb | `LIKE 'LOTE %'` |
| 542264 | **rd** congo copa do mundo 2026 | `LIKE 'RD %'` |

Como **homolog e produção compartilham o bucket** — o risco que eu mesmo documentei hoje na
seção 0.2 do HANDOVER — o `wp_delete_attachment` removeu os objetos do S3 que **produção serve**.

## O dano, medido

| | |
|---|---|
| `wp-content/uploads/2022/04/25090246/` | **0 objetos** (era 1) |
| `wp-content/uploads/2026/06/27224944/` | **0 objetos** (eram 8) |
| CDN nas duas URLs | **403** |
| versionamento do bucket | **desabilitado** — não há versão anterior |

**Nove objetos apagados, sem possibilidade de restaurar pelo S3.**

## Matérias afetadas — as duas seguem no ar (HTTP 200), sem a imagem

1. **313722** — "Secretaria da Administração leiloa 316 bens no próximo dia 6"
   https://bahia.ba/bahia/secretaria-da-administracao-leiloa-316-bens-no-proximo-dia-6/
   **CORREÇÃO (26/08):** eu havia dito que não aparecia no conteúdo. Aparece — há um
   `<img class="size-full wp-image-313723">` no corpo do artigo, quebrado (403). Não é imagem
   destacada, mas está visível no texto. Precisa de reposição, não só remoção do registro.

2. **542263** — "RD Congo vence Uzbequistão e conquista classificação inédita na Copa"
   https://bahia.ba/esporte/rd-congo-vence-uzbequistao-e-conquista-classificacao-inedita-na-copa/
   **A imagem é citada no conteúdo do post** — esta matéria deve estar com imagem quebrada.

## Recuperação: a VPS antiga

Os arquivos devem existir no disco da VPS `i-067a9df3e888a90f6`, que foi origem até a migração e
**já serviu de recuperação neste projeto** (32 fotos). Caminhos locais a procurar:

```
wp-content/uploads/2022/04/lote-leião-saeb.jpg

wp-content/uploads/2026/06/rd-congo-copa-do-mundo-2026.png
wp-content/uploads/2026/06/rd-congo-copa-do-mundo-2026-300x210.png
wp-content/uploads/2026/06/rd-congo-copa-do-mundo-2026-150x150.png
wp-content/uploads/2026/06/rd-congo-copa-do-mundo-2026-538x374.png
wp-content/uploads/2026/06/rd-congo-copa-do-mundo-2026-269x187.png
wp-content/uploads/2026/06/rd-congo-copa-do-mundo-2026-110x76.png
wp-content/uploads/2026/06/rd-congo-copa-do-mundo-2026-345x240.png
wp-content/uploads/2026/06/rd-congo-copa-do-mundo-2026-200x200.png
```

Para conferir se estão lá (por SSH, sem alterar nada):

```bash
UP=/var/www/html/wp-content/uploads      # ajustar a raiz real
ls -la "$UP/2022/04/lote-leião-saeb.jpg"
ls -la "$UP/2026/06/"rd-congo-copa-do-mundo-2026*
```

E para devolver ao bucket, no caminho **com o segmento de versão** que o registro já espera:

```bash
aws s3 cp "$UP/2022/04/lote-leião-saeb.jpg" \
  "s3://static.bahia.ba/wp-content/uploads/2022/04/25090246/lote-leião-saeb.jpg"

for f in "$UP/2026/06/"rd-congo-copa-do-mundo-2026*; do
  aws s3 cp "$f" "s3://static.bahia.ba/wp-content/uploads/2026/06/27224944/$(basename "$f")"
done
```

**Nada no banco precisa mudar**: os registros em `wp_as3cf_items` e o `_wp_attachment_metadata`
de produção continuam intactos e apontando para esses caminhos. Só os bytes sumiram.

Se a VPS não tiver os arquivos, a alternativa é o backup All-in-One (.wpress) usado para montar
o docker local, ou republicar as imagens à mão nessas duas matérias.

## Como isto não se repete

O erro não foi o `LIKE` — foi **eu ter escrito um filtro por texto para escolher o que apagar**.
A lista de IDs criados já existia em arquivo (`/tmp/*-ids.txt`); bastava usá-la.

Regra: **exclusão em massa se faz por lista explícita de IDs coletada no momento da criação,
nunca por padrão de título.** E, neste ambiente, com uma segunda trava: conferir que o ID está na
faixa nascida em homolog (**≥ 9.000.001**) antes de apagar qualquer coisa. Os dois anexos que
apaguei têm ID 313723 e 542264 — a trava os teria barrado.
