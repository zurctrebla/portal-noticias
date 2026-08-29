# Atualização de PHP e WordPress — FASE 1, levantamento

**29/08/2026, na janela de manutenção**, logo após a virada do MySQL para 8.4.9.
**Nada foi alterado.** Tudo aqui é leitura, `php -l` em contêiner descartável, e uma construção de
imagem de teste que não foi publicada em lugar nenhum.

---

## 🔴 O achado que reenquadra o objetivo: **o WordPress se atualiza sozinho, para um disco que morre com o pod**

| | |
|---|---|
| WP na **imagem** (`wordpress:6.8-php8.2-fpm`) | **6.8.3** |
| WP **rodando** nos dois ambientes | **6.8.8** |
| Data do `wp-includes/version.php` no pod | **26/08/2026** |
| `/var/www/html` | **`emptyDir`** — efêmero, por pod |
| Core do WP | **gravável** (`touch` funciona) |
| `DISALLOW_FILE_MODS` / `WP_AUTO_UPDATE_CORE` no `wp-config.php` | **ausentes** → auto-update de segurança **ligado por padrão** |

**O que acontece hoje, a cada pod que nasce:**

1. O `initContainer copy-wp-files` copia `wp-content` da imagem para o `emptyDir`.
2. O contêiner `wordpress` sobe e o entrypoint oficial copia o **core** de `/usr/src/wordpress`
   — que é **6.8.3**.
3. O site serve **6.8.3** até o WP-Cron rodar o auto-update e subir para **6.8.8**.
4. O pod morre. **Tudo isso se perde.** O próximo começa de novo em 6.8.3.

**Consequência que importa:** existe uma **janela de versão antiga a cada restart, deploy ou
escalonamento do HPA**, e ela some sozinha sem deixar rastro. São 5 versões de correção de
distância — 6.8.3 → 6.8.8 — e releases de patch do WordPress são majoritariamente de segurança.

**Isto não é um bug a consertar antes de atualizar: é o motivo pelo qual "atualizar o WordPress"
tem de ser feito pela imagem.** Enquanto o core vier de `6.8-php8.2-fpm`, o número que a gente
"atualiza" pelo painel dura até o próximo rollout.

---

## Estado atual — os dois ambientes são idênticos

| | Homolog | Produção |
|---|---|---|
| Imagem base | `wordpress:6.8-php8.2-fpm` | idem |
| PHP | **8.2.29** | **8.2.29** |
| WordPress (rodando) | **6.8.8** | **6.8.8** |
| Sistema | Debian 13 (trixie) | idem |
| Extensões carregadas | **41** | **41** |
| Tema ativo | **Newspaper 12.7.6** | idem |
| Plugins ativos | **24** | idem |
| `mu-plugins` | **62 arquivos** ¹ | idem |

¹ São **62 arquivos `.php`**, não 31. A contagem anterior está desatualizada.

Extensões presentes: `bcmath curl dom exif fileinfo filter gd hash iconv imagick intl json
libxml mbstring mysqli mysqlnd openssl pcre PDO pdo_sqlite Phar posix random readline Reflection
session SimpleXML sodium SPL sqlite3 standard tokenizer xml xmlreader xmlwriter zip zlib
Zend OPcache Core date`.

---

## Destino proposto

### PHP: **8.2.29 → 8.3.28**, pela tag `wordpress:6.8-php8.3-fpm`

| Verificação | Resultado |
|---|---|
| `php -l` nos **173** arquivos nossos (mu-plugins + tema) sob 8.3 **e** 8.4 | **0 erros** |
| `php -l` nos **~9.800** arquivos dos 30 plugins sob 8.4 | **0 erros** |
| Paridade de extensões 8.2 × 8.3 | **41 × 41 — nenhuma perdida, nenhuma nova** |
| Construção da imagem com o `FROM` trocado | **funcionou, 39 s** |
| `php.ini` nosso aplicado na imagem nova | `memory_limit=512M`, `upload_max_filesize=128M`, `max_execution_time=0`, `opcache.enable=On` |
| Artefatos do tema (`main.min.css`, `theme.min.js`) | gerados |

### PHP 8.4: **não agora**

Sintaticamente passa. Mas o 8.4 depreciou **tipo implicitamente nullable**
(`function f(Foo $x = null)` — agora exige `?Foo`), e uma varredura restrita a listas de
parâmetros encontrou **280 ocorrências reais**:

| componente | ocorrências |
|---|---|
| `amazon-s3-and-cloudfront` (Offload Media) | **244** |
| `twitter-auto-publish` (vendor Composer) | 13 |
| `adrotate-pro` | 9 |
| `google-site-kit` | 7 |
| tema, `td-composer`, `td-social-counter` | 7 |
| **nossos `mu-plugins`** | **0** |

São `E_DEPRECATED`, não fatais — mas em código de terceiro que **não podemos corrigir**, e em
plugin pago no caso do AdRotate. **O 8.3 não tem essa depreciação.** Pular direto para o 8.4
trocaria um problema resolvido por 280 linhas de log por requisição.

### WordPress: **ficar na linha 6.8**

O WordPress atual é o **7.1** — três versões maiores à frente. O teto declarado pelos plugins é
**7.0**, e a maioria para em **6.8**, exatamente onde estamos:

| Plugin | Tested up to | Observação |
|---|---|---|
| `role-quick-changer` | **4.4.2** | **abandonado** — WP 4.4 é de 2015 |
| `advanced-custom-fields-pro` | 6.3 | **pago**, versão 6.2.1.1 |
| `adrotate-pro` | 6.3.1 | **pago** |
| `regenerate-thumbnails` | 6.3 | |
| `co-authors-plus` | 6.6 | acoplado à página de autor |
| `amazon-s3-and-cloudfront` | 6.7 | |
| `capability-manager-enhanced`, `disable-comments`, `foogallery`, `onesignal`, `puredevs-gdpr`, `taxonomy-terms-order`, `wp-smushit` | 6.8 | |
| `google-site-kit`, `wordpress-seo` | **7.0** | o teto |
| `td-composer`, `td-cloud-library`, `td-social-counter` | **não declaram** | sem `readme.txt` |
| Tema `Newspaper` 12.7.6 | **não declara** | tema pago |

**O tema e os três plugins tagDiv — que são o código mais acoplado do site e que não
atualizamos — não declaram compatibilidade com nada.** Subir três versões maiores do WordPress
com eles é apostar sem informação.

---

## Se a atualização do WordPress exigiria atualizar plugins

**Ficando em 6.8: não.** Tudo já roda aí hoje.

**Indo para 7.x: sim, e vira outro projeto.** `role-quick-changer` está abandonado desde o WP 4.4
e teria de sair ou ser substituído; `ACF Pro` e `AdRotate Pro` são pagos e precisam de licença
válida para atualizar; e os três plugins tagDiv acompanham o tema, que é uma compra à parte.

---

## Ordem recomendada: **PHP primeiro, WordPress depois — e o WordPress quase não se move**

**PHP primeiro**, por três razões:

1. **É o que está verificado.** Zero erros de sintaxe em ~10.000 arquivos, paridade de extensões
   e a imagem já construída. O risco do WordPress é o oposto: código de terceiro sem declaração.
2. **É uma variável só.** Trocar o `FROM` para `6.8-php8.3-fpm` muda o PHP e mantém o WP em 6.8.3
   — o mesmo core que a imagem já entrega hoje.
3. **O ganho do WordPress é diferente do que parece.** "Atualizar o WordPress" hoje significa
   **fazer a imagem entregar o que o auto-update já entrega** — fechar a janela do 6.8.3. E a tag
   `6.8` do WordPress oficial hoje carrega **6.8.3**; não existem tags `6.8.8-php8.3-fpm`. Ou
   seja: **a correção da janela não é uma versão nova, é uma decisão sobre auto-update**, e
   merece tarefa própria.

---

## ⚠️ Onde vive o manifesto — e a correção de duas coisas que escrevi errado

**Os `Deployment` não estão neste repositório.** Vivem em
`infra-bahiaba/kubernetes/{homolog,prod}/wordpress/deployment.yaml`, e **push em `kubernetes/**`
é deploy**. Ou seja: há **dois caminhos** que alteram o que roda, e eles se sobrescrevem.

### Correção 1 — produção JÁ atualiza os dois contêineres

O `deploy-prod.yml` faz:

```bash
kubectl set image deployment/wordpress \
  copy-wp-files="$IMG" \
  wordpress="$IMG" \
  -n "$NAMESPACE"
```

**Os dois, por SHA imutável**, e o workflow ainda imprime o comando de rollback com os dois no
resumo da execução. **Produção está certa.** O que eu descrevi como problema do "pipeline" é
problema **só do `deploy-homolog.yml`**, que faz `set image` apenas no contêiner `wordpress`.

### Correção 2 — e o problema real é outro: **produção está rodando na tag flutuante**

Estado lido dos `Deployment` agora:

| | `initContainer` | contêiner de aplicação |
|---|---|---|
| **Homolog** | `homolog-latest` | **`a9c7d1ab…` (SHA)** |
| **Produção** | **`prod-latest`** | **`prod-latest`** |

O `deploy-prod.yml` fixa `prod-<sha>` nos dois. **Produção não está em nenhum SHA** — logo, o
`set image` foi **sobrescrito por um `apply` do manifesto** depois do último deploy. Push em
`kubernetes/**` reaplica o YAML, e o YAML declara `prod-latest`.

**E o `initContainer` tem `imagePullPolicy: Always`.**

**Consequência:** com os dois contêineres numa tag flutuante e `pull` sempre, **qualquer restart
de pod — escalonamento do HPA, troca de nó, crash — puxa o que `prod-latest` estiver apontando
naquele instante**, sem deploy nenhum. E o pino por SHA que o workflow coloca dura até o próximo
push que toque `kubernetes/**`.

O comentário do próprio manifesto conta que isto já aconteceu antes, em outra forma:

> *"Até 2026-08-10 estas duas imagens eram `homolog-latest`, construída por push na `staging` —
> era por isso que produção rodava o código da staging e absorvia qualquer push nela no próximo
> restart de pod."*

**A separação de ambientes foi corrigida; o mecanismo que a causou continua lá.** Hoje ele só é
inofensivo porque `prod-latest` e o último build da `main` coincidem.

**Por que isso importa para esta atualização:** o plano de rollback do PHP é "voltar a tag por
SHA anterior nos dois contêineres". Isso funciona — **e é desfeito silenciosamente pelo próximo
push em `kubernetes/**`**, que devolve os dois para `prod-latest`, ou seja, para o PHP novo.

**Em homolog é pior**, e vale saber antes de começar: o `initContainer` está em
`homolog-latest`, então **o `wp-content` servido é sempre o último build da `develop`**,
independentemente do SHA fixado no contêiner de aplicação. Assim que eu commitar e o build rodar,
qualquer restart de pod já traz o código novo.

## ⚠️ Risco de rollback: o `initContainer` não é atualizado pelo pipeline DE HOMOLOG

```yaml
initContainers:
  - name: copy-wp-files
    image: .../bahia-wordpress:homolog-latest      # <- tag FLUTUANTE
    command: ["sh","-c","cp -r /var/www/html/. /shared/"]
containers:
  - name: wordpress
    image: .../bahia-wordpress:a9c7d1ab...         # <- SHA fixo
```

O `deploy-homolog.yml` faz **apenas**:

```bash
kubectl set image deployment/wordpress wordpress=$ECR/$REPO:$IMAGE_TAG -n $NS
```

**Só o contêiner de aplicação** — ao contrário do `deploy-prod.yml`, que faz os dois. O
`initContainer` fica em `homolog-latest`, que o mesmo build também empurra.

**Por que isso é grave para o rollback:** o `wp-content` servido — todos os 62 `mu-plugins` e o
tema — vem do **`initContainer`**, não do contêiner de aplicação, porque o `emptyDir` monta por
cima de `/var/www/html`. **Voltar só o contêiner de aplicação para o SHA anterior não volta o
código.** Volta o binário do PHP e o core do WordPress, e deixa o `wp-content` novo por cima —
um estado misto que nunca foi testado.

**Em produção os dois estão em `prod-latest`** — consistentes entre si, mas **fora do SHA que o
workflow fixa**, porque um `apply` do manifesto passou por cima. Ver a seção acima.

---

## Tempo estimado

| Etapa | Estimativa | Base |
|---|---|---|
| Trocar o `FROM` e commitar | 2 min | — |
| Dump do banco de homolog + verificações | **~3 min** | o de produção levou 55 s para 117 MB; homolog tem 6,4 GiB, mas o dump é do RDS por snapshot |
| Build + push no GitHub Actions | **~5-8 min** | imagem de 402 MB, build local levou 39 s com cache quente |
| Rollout em homolog (1 nó, `maxSurge=0`) | **~2-3 min** | pod único, termina antes de criar |
| Validação da Fase 3 (site, busca, matéria, logs, `carga.sh` ×2 com descanso) | **~25-30 min** | o descanso entre corridas sozinho são 5 min |
| **Total homolog** | **~40-45 min** | |
| Produção (build + rollout + portões) | **~30 min** | HPA min2/max5, rollout mais longo |

**Cabe na janela.** E como não há prazo, cada etapa pode parar sem custo.

---

## Portões antes de eu alterar qualquer coisa

- [ ] Você aprova **PHP 8.3 agora, PHP 8.4 não**
- [ ] Você aprova **ficar na linha 6.8 do WordPress**, e tratar a janela do auto-update como
      tarefa própria
- [ ] Você decide se o **`deploy-homolog.yml` passa a fazer `set image` nos dois contêineres**,
      como o de produção já faz — é correção de rollback, não desta atualização, mas sairia
      barato junto
- [ ] Você decide o que fazer com **produção rodando em `prod-latest`** nos dois contêineres, com
      `imagePullPolicy: Always` — hoje qualquer restart de pod puxa o último build da `main` sem
      deploy. **É tarefa própria e não bloqueia esta atualização**, mas afeta o rollback dela
- [ ] Confirmar que **62 mu-plugins** é o número certo (o roteiro dizia 31)

---

# Tarefas próprias, decididas em 29/08/2026

## Tarefa A — PHP 8.4, quando for a hora

**Não é hoje.** O bloqueio é a depreciação de **tipo implicitamente nullable**
(`function f(Foo $x = null)` passa a exigir `?Foo $x = null`). São `E_DEPRECATED`, não fatais,
mas cada chamada escreve no log.

**Quem depreca o quê — medido em 29/08/2026**, varredura restrita a listas de parâmetros:

| Componente | Ocorrências | Podemos corrigir? |
|---|---|---|
| `amazon-s3-and-cloudfront` (WP Offload Media) | **244** | ❌ terceiro, gratuito — depende de release deles |
| `twitter-auto-publish` (`vendor/composer/ca-bundle`) | 13 | ❌ dependência Composer vendorizada |
| `adrotate-pro` (`library/mobile-detect.php`) | 9 | ❌ **pago** — depende de release |
| `google-site-kit` (`third-party/firebase/php-jwt`) | 7 | ❌ dependência vendorizada |
| `td-social-counter` (`vendor/abraham/twitteroauth`) | 5 | ❌ dependência vendorizada |
| `themes/bahia_refactor/Mobile-Detect` | 1 | ✅ **nosso repositório**, biblioteca vendorizada |
| `td-composer/includes/Mobile_Detect.php` | 1 | ❌ plugin do tema |
| **`mu-plugins` (código nosso)** | **0** | — |
| **TOTAL** | **280** | |

**O padrão é claro: 100% está em biblioteca de terceiro vendorizada.** Nenhuma linha do nosso
código precisa mudar. O caminho para o 8.4 é **esperar os releases**, não corrigir código.

**O que destrava:** WP Offload Media sozinho responde por **87%**. Quando ele sair com o
`?` nos tipos, o número cai para 36 e o assunto muda de figura.

**Como reavaliar:** repetir a varredura (está em `git log` desta sessão) depois de cada rodada
de atualização de plugins. **Não subir para 8.4 enquanto o total não estiver perto de zero em
código que não controlamos.**

## Tarefa B — o que atualiza sozinho hoje, que é deploy que ninguém aprova

**Levantado em 29/08/2026, a pedido do Albert.**

| Mecanismo | Estado | Consequência |
|---|---|---|
| **Auto-update do core do WordPress** | **LIGADO** (padrão; sem `DISALLOW_FILE_MODS` nem `WP_AUTO_UPDATE_CORE` no `wp-config.php`) | o core sobe sozinho de 6.8.3 para 6.8.8 dentro do pod, **num `emptyDir`** — e volta a 6.8.3 no próximo pod |
| **`imagePullPolicy: Always`** nos dois contêineres | **LIGADO** | com tag flutuante, qualquer restart de pod puxa o build mais recente **sem deploy** |
| **Tag flutuante no manifesto** (`prod-latest` / `homolog-latest`) | **em uso** | um `kubectl apply` desfaz o pino por SHA — ver a seção do `tf-apply` |

**As três se somam:** o código e a versão do WordPress que rodam em produção num instante
qualquer **não são determinados por nenhum deploy aprovado**. São determinados por *quando o
último pod nasceu* e *o que estava em `latest` naquele momento*.

**Perguntas que a tarefa precisa responder, e não respondi aqui:**

1. Desligar o auto-update do core (`WP_AUTO_UPDATE_CORE=false`) e passar a atualizar pela imagem,
   ou mantê-lo e aceitar a janela? **Desligar sem trocar a imagem congela em 6.8.3, que é pior.**
2. O mesmo vale para plugins e temas — `DISALLOW_FILE_MODS` cobriria tudo de uma vez, e também
   impediria instalação pelo painel, o que é decisão de processo, não técnica.
3. O `wp-content/upgrade` existe no pod: o WordPress **está** escrevendo lá. Vale conferir se há
   plugin com auto-update ligado individualmente.
