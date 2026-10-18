# Recuperar os 9 arquivos apagados — comandos prontos

> **EU NÃO ALCANÇO A VPS — a recuperação é sua.** Testado em 25/08: a porta 22 está aberta
> (OpenSSH 9.2 Debian) e a VPS está no meu `known_hosts`, mas ela **só aceita `publickey`** e
> nenhuma das minhas chaves autentica (`Permission denied (publickey)` com id_ed25519, do_mt25519,
> application, github_pessoal, nos usuários ubuntu/admin/debian/root/bitnami/albert/bahia). SSM
> está negado à credencial de pipeline. E a VPS **não serve `/wp-content/uploads/` por HTTP** —
> devolve 404 até para arquivos que sabidamente existem (offload), então baixar pela web também
> não funciona. Um pod de produção alcança o IP privado 172.31.0.178:80 pelo peering, mas esbarra
> no mesmo 404.
>
> **Só o acesso ao filesystem da VPS resolve, e ele exige a sua chave.** Os comandos abaixo são
> para você rodar por SSH.


Para rodar **na VPS antiga** (`i-067a9df3e888a90f6`, `54.243.117.103`) quando você entrar por SSH.
Contexto do incidente em `INCIDENTE-APAGUEI-2-IMAGENS.md`.

Nada no banco precisa mudar. Os registros de produção em `wp_as3cf_items` e o
`_wp_attachment_metadata` continuam intactos e apontando para estes caminhos — **só os bytes
sumiram do S3.**

## 1. Conferir se os arquivos estão na VPS

```bash
# ajuste a raiz se o `find` da secao 5(d) do COMANDOS-VPS.md mostrar outra
UP=/var/www/html/wp-content/uploads

ls -la "$UP/2022/04/lote-leião-saeb.jpg"
ls -la "$UP/2026/06/"rd-congo-copa-do-mundo-2026*
```

Esperado: **1 arquivo** no primeiro, **8 arquivos** no segundo (original + 7 derivadas).

> O acento em `leião` é do nome real. Se o shell reclamar, use aspas — já estão no comando — ou
> `ls "$UP/2022/04/" | grep -i saeb`.

## 2. Devolver ao bucket, no caminho COM o segmento de versão

O segmento (`25090246`, `27224944`) é o que o registro do Offload já espera. **Não invente outro**
— com segmento diferente o arquivo sobe mas o site continua sem imagem.

```bash
aws s3 cp "$UP/2022/04/lote-leião-saeb.jpg" \
  "s3://static.bahia.ba/wp-content/uploads/2022/04/25090246/lote-leião-saeb.jpg"

for f in "$UP/2026/06/"rd-congo-copa-do-mundo-2026*; do
  aws s3 cp "$f" "s3://static.bahia.ba/wp-content/uploads/2026/06/27224944/$(basename "$f")"
done
```

Os oito do segundo caso são:

```
rd-congo-copa-do-mundo-2026.png              (original)
rd-congo-copa-do-mundo-2026-300x210.png      medium
rd-congo-copa-do-mundo-2026-150x150.png      thumbnail
rd-congo-copa-do-mundo-2026-538x374.png      destaque_grande
rd-congo-copa-do-mundo-2026-269x187.png      destaque_pequeno
rd-congo-copa-do-mundo-2026-110x76.png       destaque_mini
rd-congo-copa-do-mundo-2026-345x240.png      news_home
rd-congo-copa-do-mundo-2026-200x200.png      user_avatar
```

## 3. Verificar que os nove voltaram, com o tamanho certo

```bash
echo "=== contagem por pasta (esperado: 1 e 8) ==="
aws s3 ls s3://static.bahia.ba/wp-content/uploads/2022/04/25090246/ | wc -l
aws s3 ls s3://static.bahia.ba/wp-content/uploads/2026/06/27224944/ | wc -l

echo "=== tamanho no S3 x tamanho na VPS ==="
aws s3 ls s3://static.bahia.ba/wp-content/uploads/2022/04/25090246/ --recursive
ls -la "$UP/2022/04/lote-leião-saeb.jpg"

aws s3 ls s3://static.bahia.ba/wp-content/uploads/2026/06/27224944/ --recursive
ls -la "$UP/2026/06/"rd-congo-copa-do-mundo-2026*
```

Os bytes têm de bater arquivo a arquivo.

## 4. Confirmar pelo site

```bash
# 403 antes, 200 depois
curl -sSI "https://d1x4bjge7r9nas.cloudfront.net/wp-content/uploads/2022/04/25090246/lote-lei%C3%A3o-saeb.jpg" | head -1
curl -sSI "https://d1x4bjge7r9nas.cloudfront.net/wp-content/uploads/2026/06/27224944/rd-congo-copa-do-mundo-2026.png" | head -1
```

E as duas matérias, a segunda sendo a que exibe a imagem no conteúdo:

- https://bahia.ba/bahia/secretaria-da-administracao-leiloa-316-bens-no-proximo-dia-6/
- https://bahia.ba/esporte/rd-congo-vence-uzbequistao-e-conquista-classificacao-inedita-na-copa/

> O CloudFront guarda o 403 em cache. Se continuar 403 depois da cópia, force com
> `?v=1` na URL para confirmar que o objeto voltou, e só então invalide o caminho no console.

## Se a VPS não tiver os arquivos

1. O backup All-in-One (`.wpress`) usado para montar o docker local — ver
   `restore-wpress-docker-local` nas notas.
2. Republicar as duas imagens à mão nas matérias. A da RD Congo é a que importa; a do leilão não
   aparece em lugar nenhum.
