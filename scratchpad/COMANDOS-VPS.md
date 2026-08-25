# Comandos prontos para você rodar — VPS antiga e ALB antigo

Nada aqui foi executado. São três blocos independentes.

VPS: `i-067a9df3e888a90f6` · `54.243.117.103` (público) · `172.31.0.178` (privado) · c6a.xlarge

---

## Bloco 1 — Inventário de uploads: VPS × S3

### ⚠️ A armadilha que invalida a comparação ingênua

O WP Offload Media insere **um segmento de versão** no caminho do objeto. Confirmei no bucket:

```
na VPS :  wp-content/uploads/2016/04/salario-minimo-2016.jpg
no S3  :  wp-content/uploads/2016/04/23215048/salario-minimo-2016.jpg
                                     ^^^^^^^^ segmento que só existe no S3
```

**Comparar caminho com caminho dá 100% de divergência falsa.** Os comandos abaixo comparam por
`ano/mês/arquivo`, removendo o segmento de versão do lado do S3.

Segunda armadilha: o S3 contém também as **derivadas** (`-269x187`, `-150x150` etc.), que na VPS
existem igualmente. O que interessa é o **original**. Os comandos separam as duas coisas.

### 1.1 Na VPS (por SSH)

```bash
# descobrir a raiz real do WordPress antes de tudo
ls -d /var/www/*/wp-content/uploads 2>/dev/null || \
  find / -maxdepth 6 -type d -name uploads -path '*wp-content*' 2>/dev/null | head

# a partir daqui, ajuste UP para o caminho que apareceu acima
UP=/var/www/html/wp-content/uploads

# contagem total
find "$UP" -type f | wc -l

# lista normalizada: ano/mes/arquivo  (só o que é imagem)
find "$UP" -type f \( -iname '*.jpg' -o -iname '*.jpeg' -o -iname '*.png' -o -iname '*.gif' -o -iname '*.webp' \) \
  -printf '%P\n' \
  | grep -E '^[0-9]{4}/[0-9]{2}/' \
  | sort -u > /tmp/vps-todos.txt

# só os ORIGINAIS (remove as derivadas -WxH)
grep -vE -- '-[0-9]{2,4}x[0-9]{2,4}\.[A-Za-z]+$' /tmp/vps-todos.txt | sort -u > /tmp/vps-originais.txt

wc -l /tmp/vps-todos.txt /tmp/vps-originais.txt
```

Traga os dois arquivos para a sua máquina:

```bash
scp SEU_USUARIO@54.243.117.103:/tmp/vps-originais.txt .
scp SEU_USUARIO@54.243.117.103:/tmp/vps-todos.txt .
```

### 1.2 Do lado do S3 (na sua máquina, com a credencial da conta)

O bucket é **`static.bahia.ba`**. Esta listagem é longa — deixe rodando.

```bash
aws s3 ls s3://static.bahia.ba/wp-content/uploads/ --recursive --summarize \
  | tee /tmp/s3-raw.txt | tail -3      # imprime Total Objects e Total Size
```

Normalizar removendo o segmento de versão, para ficar no mesmo formato `ano/mes/arquivo`:

```bash
awk '{ $1=""; $2=""; $3=""; sub(/^ +/,""); print }' /tmp/s3-raw.txt \
  | sed 's#^wp-content/uploads/##' \
  | sed -E 's#^([0-9]{4})/([0-9]{2})/[0-9]+/#\1/\2/#' \
  | grep -E '^[0-9]{4}/[0-9]{2}/.+\.(jpg|jpeg|png|gif|webp)$' \
  | sort -u > /tmp/s3-todos.txt

grep -vE -- '-[0-9]{2,4}x[0-9]{2,4}\.[A-Za-z]+$' /tmp/s3-todos.txt | sort -u > /tmp/s3-originais.txt

wc -l /tmp/s3-todos.txt /tmp/s3-originais.txt
```

### 1.3 A comparação que decide

```bash
echo "=== ORIGINAIS que existem na VPS e NÃO existem no S3 ==="
comm -23 /tmp/vps-originais.txt /tmp/s3-originais.txt | tee /tmp/FALTANDO-NO-S3.txt | head -40
echo "TOTAL FALTANDO: $(wc -l < /tmp/FALTANDO-NO-S3.txt)"

echo; echo "=== contagens dos dois lados ==="
printf "  VPS originais: %s\n" "$(wc -l < /tmp/vps-originais.txt)"
printf "  S3  originais: %s\n" "$(wc -l < /tmp/s3-originais.txt)"
printf "  VPS todos    : %s\n" "$(wc -l < /tmp/vps-todos.txt)"
printf "  S3  todos    : %s\n" "$(wc -l < /tmp/s3-todos.txt)"
```

**Critério de liberação: `/tmp/FALTANDO-NO-S3.txt` tem que estar vazio.**
Enquanto tiver linha, a VPS pode ser parada, **mas não terminada**.

Se aparecerem poucos arquivos, confira um a um pela URL do CDN antes de concluir que sumiram —
pode ser diferença de acentuação ou de maiúscula no nome:

```bash
while read -r f; do
  printf '%s -> ' "$f"
  curl -sSI -m 10 -o /dev/null -w '%{http_code}\n' "https://d1x4bjge7r9nas.cloudfront.net/wp-content/uploads/$f"
done < /tmp/FALTANDO-NO-S3.txt | head -30
```

> Referência: o total de anexos de imagem no banco de produção é **153.842**. Se as contagens dos
> dois lados divergirem muito desse número, o problema é de normalização, não de arquivo perdido.

---

## Bloco 2 — O que ainda roda dentro da VPS

Tudo somente leitura.

```bash
# --- serviços e o que escuta ---
systemctl list-units --type=service --state=running --no-pager
ss -tulpn | grep LISTEN

# --- é Docker Swarm: o que está de pé ---
docker ps --format 'table {{.Names}}\t{{.Image}}\t{{.Status}}\t{{.Ports}}'
docker service ls 2>/dev/null
docker stack ls 2>/dev/null
docker node ls 2>/dev/null

# --- agendamentos: o que dispara sozinho ---
crontab -l 2>/dev/null
sudo ls -la /etc/cron.d/ /etc/cron.daily/ /etc/cron.hourly/ 2>/dev/null
sudo grep -rs '' /etc/cron.d/ 2>/dev/null
systemctl list-timers --all --no-pager
for u in $(ls /home 2>/dev/null); do echo "--- crontab de $u ---"; sudo crontab -u "$u" -l 2>/dev/null; done

# --- banco local: existe? tem dado vivo? ---
sudo systemctl status mysql mariadb 2>/dev/null | head -20
mysql -e "SHOW DATABASES;" 2>/dev/null || echo "sem mysql local acessível"
docker exec -it $(docker ps -qf name=mysql 2>/dev/null | head -1) mysql -e "SHOW DATABASES;" 2>/dev/null

# --- os suspeitos citados no escopo ---
for s in varnish fail2ban netdata nginx openlitespeed redis memcached postfix; do
  printf '%-16s ' "$s"; systemctl is-active "$s" 2>/dev/null || echo inativo
done

# --- quem está falando com ela agora (2 min de amostra) ---
sudo timeout 120 tcpdump -nn -q -i any 'tcp port 80 or tcp port 443' 2>/dev/null | awk '{print $3}' | cut -d. -f1-4 | sort | uniq -c | sort -rn | head -20

# --- quem bateu recentemente, pelo log do nginx ---
sudo find /var/log -name 'access*log*' -mtime -7 2>/dev/null
sudo awk '{print $1}' /var/log/nginx/access.log 2>/dev/null | sort | uniq -c | sort -rn | head -20
sudo awk '{print $7}' /var/log/nginx/access.log 2>/dev/null | sort | uniq -c | sort -rn | head -20

# --- o que mais consome (a instância roda a 12% de CPU, então há algo ativo) ---
ps aux --sort=-%cpu | head -15
df -h; du -sh /var/www/* 2>/dev/null
```

**O que você está procurando:** qualquer serviço que alguém ainda use sem saber, qualquer cron
que escreva em lugar que importe, e a origem real das ~1.000 requisições/dia.

---

## Bloco 3 — Descobrir quem ainda chama `aws.bahia.ba`

### 3.1 Estado atual: os logs estão DESLIGADOS

Verificado no ALB antigo:

```
access_logs.s3.enabled      = false
access_logs.s3.bucket       = (vazio)
connection_logs.s3.enabled  = false
```

Os listeners são `:80 → redirect` e `:443 → forward`.

### 3.2 O bucket que existe NÃO serve

Há um `bahiaba.lblogs` de 2016, mas ele está em **sa-east-1**. O ALB é de **us-east-1**, e a AWS
exige que o bucket de access logs esteja **na mesma região do load balancer**. Precisa de bucket
novo em us-east-1.

### 3.3 O que precisa para ligar

**Passo 1 — criar o bucket em us-east-1**
Console: **S3 → Create bucket** → nome `bahiaba-alb-logs-us-east-1`, região **US East (N. Virginia)**.
Deixe *Block all public access* ligado.

**Passo 2 — política do bucket** (S3 → o bucket → Permissions → Bucket policy → Edit).
`127311923021` é a conta de ELB da us-east-1; `logdelivery...` cobre o formato novo. As duas
declarações juntas funcionam nos dois casos:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": { "AWS": "arn:aws:iam::127311923021:root" },
      "Action": "s3:PutObject",
      "Resource": "arn:aws:s3:::bahiaba-alb-logs-us-east-1/alb-antigo/AWSLogs/774710032593/*"
    },
    {
      "Effect": "Allow",
      "Principal": { "Service": "logdelivery.elasticloadbalancing.amazonaws.com" },
      "Action": "s3:PutObject",
      "Resource": "arn:aws:s3:::bahiaba-alb-logs-us-east-1/alb-antigo/AWSLogs/774710032593/*",
      "Condition": { "StringEquals": { "s3:x-amz-acl": "bucket-owner-full-control" } }
    }
  ]
}
```

**Passo 3 — ligar no ALB**
Console: **EC2 → Load balancers → `load-balancer-bahiaba-2023`** → aba **Attributes** → **Edit** →
**Monitoring → Access logs → Enable** → bucket `bahiaba-alb-logs-us-east-1`, prefixo `alb-antigo`
→ **Save**.

Se o Save falhar, é a política do bucket — a AWS valida escrevendo um arquivo de teste na hora.

**Passo 4 — esperar e ler.** A entrega sai a cada ~5 minutos. Deixe rodando **3 a 7 dias**, já
que 1.000 req/dia é volume baixo e um cron semanal pode não aparecer em 24h.

```bash
aws s3 sync s3://bahiaba-alb-logs-us-east-1/alb-antigo/ ./alblogs/
gunzip -c ./alblogs/**/*.gz > /tmp/alb.log

echo "=== quem chama (IP de origem) ==="
awk '{print $4}' /tmp/alb.log | cut -d: -f1 | sort | uniq -c | sort -rn | head -25

echo "=== que Host e que caminho ==="
awk '{print $13}' /tmp/alb.log | tr -d '"' | sort | uniq -c | sort -rn | head -25

echo "=== User-Agent: separa robô de integração ==="
awk -F'"' '{print $4}' /tmp/alb.log | sort | uniq -c | sort -rn | head -25
```

### 3.4 O caminho mais rápido, se não quiser esperar

O nginx **dentro da VPS** já registra tudo isso hoje, sem precisar de bucket nem de política —
é o último comando do Bloco 2. Ligar o access log do ALB só vale a pena se você quiser continuar
enxergando depois que a VPS estiver parada.

**Custo de ligar:** desprezível. Alguns MB por mês de S3.

---

## Lembrete de calendário

**01/10/2026** — vence a reserva `c6a.xlarge` que cobre a VPS. Até lá, parar a instância não
economiza nada. Depois, ligada, ela passa a custar **111,69 USD/mês** sob demanda.
Sua decisão registrada: não parar antes de outubro; não renovar a reserva e terminar até a data.
