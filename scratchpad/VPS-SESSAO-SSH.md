# Sessão SSH na VPS — recuperação, inventário e serviços

26/08/2026. Acesso por chave, usuário `admin`, `54.243.117.103`. **Só leitura; nada alterado ou
apagado na VPS.** A única escrita foi a listagem do S3 (leitura do bucket).

---

## 1. Recuperação das 2 imagens: MORTA por este caminho

**Os arquivos não estão na VPS.** Busca exaustiva no sistema inteiro (`find / -xdev`) pelos dois
nomes — vazia. O diretório `2026/06` existe mas não contém `rd-congo`; `2022/04` não existe no
acervo local. O backup `wp-content_backup_2025-12-04` só tem `2025/12`. Não há `.wpress` no
sistema (só um dump de **banco**, `bahia.sql.gz` de 27/09/2025, que não recupera imagem).

**Por quê:** a VPS mantém localmente apenas **1.154 originais** — o offload removeu o resto após
enviar ao S3. `rd-congo` (jun/2026) e `lote-saeb` (2022) foram offloaded e removidos daqui; a
única cópia estava no S3, que eu apaguei, e o bucket **não tem versionamento**.

**Conclusão: não há de onde recuperar as duas imagens.** As opções que restam são as do
`INCIDENTE-APAGUEI-2-IMAGENS.md` que não dependem da VPS — republicar à mão nas duas matérias. A
da RD Congo é a que aparece no conteúdo; a do leilão não aparece em lugar nenhum.

---

## 2. ⚠️ A VPS lê e escreve no RDS de PRODUÇÃO

O contêiner WordPress da VPS conecta em:

```
WORDPRESS_DB_HOST = rds-bahiaba-2023.cr9zu4ke1bev.us-east-1.rds.amazonaws.com
WORDPRESS_DB_NAME = prod
```

**É o mesmo banco do site no ar.** A VPS não é um sistema congelado e isolado — é um segundo
WordPress vivo sobre os dados de produção. Some-se a isto que ela também **serve o bucket de
produção** (mesma `static.bahia.ba`), e a conclusão é:

> **A VPS não é uma cópia de segurança de nada.** Ela compartilha banco E mídia com produção.
> Foi por isso que apagar mídia "de teste" apagou arquivo do site — e é por isso que qualquer
> operação nela é operação em produção.

O MariaDB **local** da VPS (`mariadb.service`) existe mas **não tem banco WordPress** — só as
tabelas de sistema. O WP nunca usou o banco local; sempre foi o RDS.

---

## 3. O que roda na VPS além do WordPress (item 5d)

### Docker Swarm — as stacks

| serviço | imagem | papel |
|---|---|---|
| `bahia-wordpress_wordpress` | wordpress:6.4-php8.2-fpm | o WP (conecta no RDS de prod) |
| `bahia-wordpress_nginx` | nginx:alpine | front |
| `bahia-wordpress_varnish` | varnish:7.4 | cache (o `X-Cache: HIT` visto de fora) |
| `bahia-wordpress_redis` | redis:7-alpine | object cache |
| `bahia-wordpress_wp-cron` | wordpress:6.4 | cron do WP |
| `netdata_netdata` | netdata/netdata | monitoração |
| `netdata_goaccess` + `-web` | allinurl/goaccess | relatório de acesso |
| `portainer_portainer` + `_agent` | portainer-ce | gestão de containers |

### systemd — fora do Docker

- **`actions.runner.zurctrebla-portal-noticias` — ATIVO.** É um **GitHub Actions runner
  self-hosted** para **o mesmo repositório** que faz os deploys. Terminar a VPS **desliga um
  runner de CI**. Precisa ser verificado se algum workflow depende dele (label `self-hosted`)
  antes de qualquer desligamento.
- `mariadb.service` — sem dados de WordPress (ver seção 2).
- `fail2ban`, `exim4` (mail), `unattended-upgrades`, `polkit`.

### cron

- crontab de `admin`: a cada 15 min, gera o relatório do goaccess. Só isso.
- `/etc/cron.d`: só `e2scrub_all` (padrão do sistema).
- Sem cron de root.

### tráfego

Snapshot no momento: **0 conexões estabelecidas** em 80/443. Condiz com as ~1.000 req/dia do
`aws.bahia.ba` (o 301 servido pelo Varnish) — volume baixo, sem fluxo contínuo.

---

## 4. Inventário de uploads VPS × S3 (o que decide a terminação)

**Portão de contagem aplicado — e ele pegou um erro.** A primeira listagem do S3 parou em
`2018/06` com 237k linhas; o bucket tem **866.683 objetos**. Comparar ali teria reportado 1.148
"perdas" falsas. Refeita a listagem completa (866.683 linhas conferidas) antes de comparar.

```
S3  originais (normalizados) : 159.001
VPS originais locais         :   1.154
originais SÓ na VPS          :     118  (marcados)
  - falsos positivos (basename existe no S3, difere só acento/caixa): 16
  - AUSÊNCIA REAL no S3       :     102
```

**102 originais existem só na VPS e em nenhum lugar do S3.** Distribuição: 2024 (6), **2025 (93)**,
2026 (3). Lista em `scratchpad/perda-real.txt`.

Os três de 2026 são material recente:
```
2026/05 FAMILIA-ESPORTE-CLUBE-JACUIPENSE...JPG
2026/06 anatomia-do-caos_ALTA-1.png
2026/07 WhatsApp-Image-2026-07-01-at-09.13.44-e1782908112470.jpeg
```

**Consequência para a decisão de terminar a VPS:** terminar hoje **perde esses 102 arquivos**.
São falhas de offload (uploads que ficaram locais e nunca subiram) — exatamente o tipo de coisa
que a VPS guardava como rede. **Antes de terminar, subir os 102 para o S3.** É barato: são 102
arquivos, e já estão listados. Isso pode ser feito nesta mesma chave, com um laço de
`aws s3 cp`, e aí a terminação deixa de perder mídia.

> Nota: os 102 são *originais* sem derivada no S3. Como o registro de cada um em `wp_as3cf_items`
> aponta para um caminho **com segmento de versão**, subir o arquivo cru não basta para ele
> aparecer no site — teria de ir no caminho versionado que o banco espera, ou passar pelo
> "Offload" do próprio anexo. Para o propósito de **não perder o bit**, subir cru a um prefixo de
> resguardo (`s3://.../_resguardo-vps/`) já preserva. Fazer o religamento correto é decisão
> à parte.

---

## 5. Para a decisão de 01/10

Terminar a VPS antes de 01/10 continua sem ganho (a RI cobre de qualquer jeito). Mas quando for:

1. **Confirmar que nenhum workflow usa o runner self-hosted** (`runs-on: self-hosted` no repo).
2. **Repontar `aws.bahia.ba`** — ainda serve o 301 pela VPS.
3. **Subir os 102 originais** para o S3 antes de terminar.
4. Só então a terminação não perde nada — e mesmo assim, AMI antes, como no plano.

O ponto que muda o tom do plano de limpeza: **a VPS compartilha banco e bucket com produção.**
Ela não é infraestrutura morta a ser varrida; é um segundo nó vivo sobre os mesmos dados. Tratar
como descartável é o que causou a perda de hoje.
