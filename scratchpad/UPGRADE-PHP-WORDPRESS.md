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

### ✅ NÃO É HIPÓTESE — foi observado em 29/08/2026, num rollout medido

Ao fixar o SHA no manifesto de homolog (commit `89c6d6b`), o pod foi recriado às 06:48:50 UTC.
Leitura no pod novo, imediatamente depois:

```
antes do rollout:  $wp_version = '6.8.8'
depois do rollout: $wp_version = '6.8.3'
```

**O core regrediu cinco versões de correção num rollout de rotina**, disparado por uma mudança
que não tinha nada a ver com o WordPress — era só a troca de uma tag de imagem por um SHA
equivalente.

**Nada no cluster registrou isso.** Não há evento, não há aviso, e o painel do WordPress vai
mostrar "atualização disponível" até o WP-Cron rodar. Se ninguém tivesse lido o `version.php` nos
dois momentos, a regressão teria passado.

**Isto eleva o peso da Tarefa B:** ela deixa de ser "arrumar um desenho estranho" e passa a ser
"fechar uma regressão de segurança que já acontece, medida, a cada rollout".

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

## 🔴 Tarefa B — PRIORIDADE IMEDIATA APÓS ESTA JANELA

> **Elevada em 29/08/2026, à frente do levantamento de EC2 e do resto da fila.**

### O fato que a eleva, medido em produção às 07:33 UTC

> **Produção estava servindo WordPress 6.8.3 e 6.8.8 SIMULTANEAMENTE desde 28/08, e o leitor
> recebia uma ou outra conforme o pod para o qual o ALB o mandava.**

| pod | início | `wp_version` | `mtime` do `version.php` |
|---|---|---|---|
| `wordpress-5745977bf4-58zb4` | 29/08 07:19 | **6.8.3** | 2025-09-30 (da imagem) |
| `wordpress-5745977bf4-7f5dk` | 27/08 13:03 | **6.8.3** | 2025-09-30 (da imagem) |
| `wordpress-5745977bf4-gsf6q` | 27/08 13:02 | **6.8.8** | **2026-08-28 14:15** |

**Os dois pods de 27/08 nasceram com 41 segundos de diferença. Um auto-atualizou, o outro não.**
Cada um tem o seu próprio `emptyDir`, e o WP-Cron de cada pod decide sozinho quando rodar.

**Isto não é "uma janela de versão antiga após restart", que era como estava descrito antes.**
É **divergência de versão entre pods servindo tráfego ao mesmo tempo**, por pelo menos um dia,
sem nada no cluster registrando.

### E a normalização de hoje foi acidente, não conserto

O rollout do pino de SHA (07:36–07:39) recriou os cinco pods, e todos voltaram ao **6.8.3** da
imagem. **Ficou uniforme por acidente** — o efeito colateral de uma mudança que não tinha nada a
ver com WordPress.

**O WP-Cron vai recriar a divergência sozinho nos próximos dias**, um pod de cada vez, na ordem
em que cada cron disparar. Sem intervenção, o estado misto volta.

### Os três mecanismos que se somam

| Mecanismo | Estado | Consequência |
|---|---|---|
| **Auto-update do core** | **LIGADO** (padrão; sem `DISALLOW_FILE_MODS` nem `WP_AUTO_UPDATE_CORE`) | cada pod atualiza por conta própria, para um `emptyDir` |
| **`imagePullPolicy: Always`** | **LIGADO** nos dois contêineres | com tag flutuante, restart puxa build novo sem deploy (HANDOVER §21) |
| **`/var/www/html` em `emptyDir`** | por desenho | cada pod tem o seu core, e ele morre com o pod |

**Somados: o que roda em produção não é determinado por nenhum deploy aprovado** — é determinado
por *quando cada pod nasceu* e *o que estava em `latest` naquele instante*, pod a pod.

### O que a tarefa precisa decidir

1. Desligar o auto-update do core (`WP_AUTO_UPDATE_CORE=false`) **e** passar a atualizar pela
   imagem. **Desligar sem trocar a imagem congela em 6.8.3, que é pior que a divergência.**
2. `DISALLOW_FILE_MODS` cobriria plugins e temas também, e impediria instalação pelo painel —
   decisão de processo, não técnica.
3. O `wp-content/upgrade` existe nos pods: conferir se há plugin com auto-update próprio.

---

## Tarefa B — levantamento original (o que atualiza sozinho hoje)

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

---

# FASES 2 e 3 — PHP 8.3 EM HOMOLOG ✅ APROVADO (29/08/2026)

`FROM wordpress:6.8-php8.2-fpm` → `wordpress:6.8-php8.3-fpm`, commit `fd15e6f3` na `develop`.

## Ordem executada, e por quê

| # | Passo | Resultado |
|---|---|---|
| 1 | SHA fixo no manifesto de **homolog** (`89c6d6b` no infra) | rollout, **26 s** em 2 blocos |
| 2 | Paridade do `deploy-homolog.yml` — os dois contêineres (`01506f89`) | rollout, **26 s** |
| 3 | **PHP 8.3** (`fd15e6f3`) | rollout, **34 s** em 1 bloco |

Produção **não foi tocada em nenhum dos três**: 5 pods, mesmos nomes, 200.

## A prova de que só uma variável mudou

Era o risco que o Albert levantou: o rollout devolve o core do WordPress para o 6.8.3 da imagem, e
quem validar sem saber pode atribuir ao PHP um comportamento que é do core regredindo.

| | Antes (07:04:27) | **Depois (07:15:50)** |
|---|---|---|
| PHP | 8.2.29 | **8.3.28** |
| `wp_version` | 6.8.3 | **6.8.3** |
| `core mtime` | 2025-09-30 17:30:38 | **2025-09-30 17:30:38** |
| Extensões | 41 | **41** |

**O core não se moveu** — porque o rollout do passo 2 já o tinha devolvido a 6.8.3, e a imagem
nova entrega o mesmo 6.8.3. **Uma variável, provada, não assumida.**

E a **terceira variável — o WP-Cron reatualizando no meio do teste — nunca disparou.** O `mtime`
foi conferido no início, depois do deploy, depois da matéria de teste e no fechamento: idêntico
nos quatro momentos. O `mtime` é o detector: o auto-update reescreve o arquivo e a data vira
recente (foi assim que se viu o 6.8.8 de 26/08).

## Indisponibilidade: 34 s, em bloco único

```
queda: 07:07:09 -> 07:07:42   34s
codigos: 29x 503, 2x 502
tempo de resposta quando OK: mediana 2,20s  p90 2,48s  max 4,46s
```

É o `maxSurge: 0` com uma réplica: o pod cai antes de o novo subir. **Esperado e aceito em
homolog** — em produção o `maxSurge: 1` evita isso.

## Validação

| Camada | Resultado |
|---|---|
| **Site** | **12 de 12** — home, 3 archives, single, 2 buscas, autor, Quem Somos, 404, `wp-admin` (302), `wp-login` |
| **Busca** (teste principal) | índice íntegro (`PRIMARY`, `date_idx`, `ft`), **242.864 linhas**, os 10 termos com `MATCH`, **8 cards** por busca na web |
| **Matéria de teste** | post no CPT `politica`, subtítulo ACF **renderizado na página**, imagem no campo `imagem`, **2 coautores** do CAP, **entrou na tabela-sombra**, matéria e as 2 páginas de autor em 200, apareceu na busca — removida sem resíduo |
| **Painel** | `/wp-admin/` 302 e `/wp-login.php` servindo o formulário |
| **Logs dos 62 mu-plugins** | **0 fatais, 0 depreciações** |

### Os avisos são pré-existentes — verificado contra produção

Apareceram 6 `PHP Warning` em 25 min. **Nenhum é novo.** Produção, ainda em **PHP 8.2.29**, tem
os mesmos, em volume muito maior:

| Origem | Homolog (PHP 8.3, 25 min) | **Produção (PHP 8.2, 60 min)** |
|---|---|---|
| `co-authors-plus/php/class-coauthors-plus.php:1193` | 2 | **90** |
| `puredevs-gdpr-compliance/.../class-pd-gdpr-public.php:356` | 2 | **5** |
| `wp-smushit/core/class-url-utils.php:171` | 0 | 3 |
| **Fatais / depreciações** | **0 / 0** | **0 / 0** |

**O PHP 8.3 não introduziu um único aviso novo.** A comparação contra produção é o que permite
afirmar isso — sem ela, os 6 avisos seriam indistinguíveis de regressão.

## Carga — mesma receita, com 5 min de descanso

| | n | mediana | p90 | máximo | `Threads_running` pico | média |
|---|---|---|---|---|---|---|
| **PHP 8.2.29** | 30× 200 | 10,54 s | 14,49 s | 14,98 s | **9** | 3,5 |
| **PHP 8.3.28** | 30× 200 | **10,55 s** | **13,65 s** | **14,32 s** | **6** | 3,4 |

**Empate na mediana, cauda melhor.** p90 caiu 0,84 s, o máximo 0,66 s e o pico de
`Threads_running` de 9 para 6. Nada indica regressão; a melhora está dentro do ruído de duas
corridas de 30 URLs.

## Portão de saída da Fase 3

- [x] site respondendo: 12/12
- [x] busca funcionando, índice íntegro, resultados voltando
- [x] matéria com subtítulo, imagem e coautoria — publicada, visível, removida
- [x] 62 mu-plugins: **0 fatais, 0 avisos novos** (comparado contra produção)
- [x] `carga.sh` antes e depois, com descanso, portão de contagem verde nas duas
- [x] painel abrindo
- [x] **PHP foi a única variável** — core e `mtime` idênticos antes e depois
- [x] indisponibilidade cronometrada: 34 s, bloco único

---

# ⚠️ A oferta da 7.1 no painel — auditoria de 29/08/2026

**O painel oferece a 7.1 nos dois ambientes.** Levantado a pedido do Albert. **Nada foi
atualizado.**

**Correção de escopo do meu levantamento:** a existência da 7.1 *estava* registrada (§"WordPress:
ficar na linha 6.8"). **O que faltou foi a pergunta que importa — se o auto-update pegaria uma
versão principal sozinho.** Essa lacuna era minha, e é a séria: se pegasse, "ficar no 6.8" não
seria uma decisão nossa, seria uma esperança.

## 1. O que o painel oferece, exatamente

`get_site_transient('update_core')`, conferido em produção **e** homolog, verificado às 07:14 UTC:

```
oferta 0: response=upgrade     current=7.1     php_min=7.4     locale=pt_BR
oferta 1: response=upgrade     current=7.1     php_min=7.4     locale=en_US
oferta 2: response=autoupdate  current=7.1     php_min=7.4     locale=en_US
oferta 3: response=autoupdate  current=7.0.4   php_min=7.4     locale=en_US
oferta 4: response=autoupdate  current=6.9.7   php_min=7.2.24  locale=en_US
oferta 5: response=autoupdate  current=6.8.8   php_min=7.2.24  locale=en_US
```

Instalado: **6.8.3** nos dois. **A 7.1 aparece inclusive como `response=autoupdate`** — o que
assusta, e é justamente por isso que a pergunta 2 precisava de resposta medida.

## 2. ✅ O auto-update NÃO pega versão principal — verificado na função que decide

**A 7.1 é versão principal:** ramo `6.8` → ramo `7.1`. Também são principais a 7.0.4 e a 6.9.7.

Em vez de deduzir do padrão do WordPress, chamei a própria função de decisão do core:

```
Core_Upgrader::should_update_to_version()
  7.1     ramo 6.8 -> 7.1   MAJOR   false
  7.0.4   ramo 6.8 -> 7.0   MAJOR   false
  6.9.7   ramo 6.8 -> 6.9   MAJOR   false
  6.8.8   ramo 6.8 -> 6.8   MINOR   *** true ***

find_core_auto_update()  ->  aplicaria sozinho: 6.8.8
```

**Idêntico nos dois ambientes.** Os valores que governam:

| | valor | efeito |
|---|---|---|
| `auto_update_core_major` | **`'unset'`** | **principal DESLIGADO** |
| `auto_update_core_minor` | `'enabled'` | minor ligado |
| `auto_update_core_dev` | `'enabled'` | dev ligado |
| `WP_AUTO_UPDATE_CORE` | **não definida** | vale o padrão acima |
| filtro `allow_major_auto_core_updates` | **`false`** | nenhum plugin o liga |

> ### ⚠️ A armadilha que quase me pegou aqui
>
> O filtro **`auto_update_core` devolve `true` para a 7.1**. Olhar só para ele — que é o nome
> mais óbvio — daria a resposta **errada**. Ele governa *se o core atualiza*, não *para qual
> versão*. A escolha da versão é do `should_update_to_version()`, e lá a 7.1 é `false`.
>
> **Dois filtros com nomes parecidos e respostas opostas.** A pergunta certa não é "o
> auto-update está ligado?", é "o que `find_core_auto_update()` devolve?".

**Conclusão: produção sobe sozinha para a 6.8.8, e não passa disso.** A Tarefa B continua sendo
"divergência entre pods", não "mudança de versão principal sem aprovação".

## 3. Compatibilidade da 7.1 — existe lá em cima, e nenhuma está instalada aqui

Consultado o `api.wordpress.org` para os plugins que vivem lá:

| plugin | **nosso** | upstream | tested up to | req. PHP |
|---|---|---|---|---|
| `advanced-custom-fields` | **6.2.1.1** | 6.8.9 | **7.1** | 7.4 |
| `wordpress-seo` (Yoast) | **27.7** | 28.3 | **7.1** | 7.4 |
| `wp-smushit` | **3.22.1** | 4.3.2 | **7.1** | 7.4 |
| `capability-manager-enhanced` | **2.21.0** | 2.50.1 | **7.1** | 7.2.5 |
| `foogallery` | **2.4.32** | 3.2.6 | **7.1** | 7.0 |
| `google-site-kit` | **1.180.0** | 1.186.0 | **7.1** | 7.4 |
| `taxonomy-terms-order` | **1.9.1** | 2.0 | **7.1** | — |
| `amazon-s3-and-cloudfront` | **3.2.11** | 3.3.1 | 7.0.4 | **8.1** |
| `co-authors-plus` | **3.6.6** | 4.1.1 | 7.0.4 | 7.4 |
| `disable-comments` | **2.5.3** | 2.8.0 | 7.0.4 | 7.0 |
| `twitter-auto-publish` | **1.7.6** | 1.7.7 | 7.0.4 | 7.4 |
| `post-type-switcher` | **4.0.0** | 4.0.1 | 6.9.7 | 8.0 |
| `regenerate-thumbnails` | **3.1.6** | 3.1.6 | 6.8.8 | 5.2.4 |

**Nenhum plugin nosso está na versão de cima. Todos, sem exceção.** Alguns por muito: ACF
6.2.1.1 → 6.8.9; Smush 3.22.1 → 4.3.2; CAP 2.21.0 → 2.50.1; FooGallery 2.4.32 → 3.2.6;
Co-Authors 3.6.6 → 4.1.1.

**Sete dos treze já suportam a 7.1 — em versões que não temos.**

**Fora do wp.org, sem informação nenhuma:**

| | situação |
|---|---|
| Tema **Newspaper 12.7.6** | pago, `Requires at least` e `Tested` **vazios** |
| `td-composer`, `td-cloud-library`, `td-social-counter` | sem `readme.txt`, acompanham o tema |
| **AdRotate Pro 5.13.1** | pago |
| **ACF Pro 6.2.1.1** | pago (o 7.1 acima é do ACF gratuito) |
| `role-quick-changer` | **não está mais no wp.org** — abandonado |
| Nossos 6 plugins internos + **62 mu-plugins** | só testáveis rodando contra a 7.1 |

**Os 62 mu-plugins passaram no `php -l` sob 8.3 e 8.4 — isso é sintaxe, não API do WordPress.**
Compatibilidade com a 7.1 só se prova rodando, e não há ambiente 7.1 para rodar. **Construí-lo é
projeto próprio.**

## 4. ✅ A 7.1 NÃO exige PHP acima do 8.3

```
PHP  : exige >= 7.4      temos 8.2.29 (prod) / 8.3.28 (homolog)  -> OK
MySQL: exige >= 5.5.5    temos 8.4.9                             -> OK
```

**O PHP não é o obstáculo — nem antes nem depois do 8.3.** O obstáculo são os plugins e o tema.

## 5. O que muda de comportamento

WordPress **7.1 "Mary Lou", lançada em 19/08/2026** — dez dias atrás. `db_version` **61833**
contra os **60421** instalados: **1.412 revisões de esquema** de distância.

Do Field Guide, filtrado para o que atinge tema clássico:

| Área | Mudança | Risco aqui |
|---|---|---|
| **Editor em iframe, agora obrigatório** | "plugins que dependem de atravessar a fronteira do documento do editor devem revisar seu JavaScript e CSS" | **o maior** — o tagDiv injeta JS e CSS no editor |
| **Barra de admin persistente** | permanece durante navegação nas telas do editor | quem estende a toolbar precisa revisar |
| **REST/mídia** | validação de dimensões no sideload, `encode quality` em anexos, registro em múltiplos tamanhos | toca o Offload Media e o Smush |
| `@wordpress/reusable-blocks` | depreciado, a caminho de no-op | baixo |
| Prefixo `__experimental*` | passa a registrar depreciação no console | baixo |

**O Field Guide não lista remoção de função PHP nem mudança de `WP_Query` ou de esquema** — o
foco dele é o editor. **Isso não é o mesmo que dizer que não há**; é dizer que a fonte consultada
não cobre. Duas versões principais (6.9 e 7.0) ficam no meio do caminho e não foram auditadas.

## Veredito

**A recomendação de ficar no 6.8 não muda — e agora está sustentada por medição, não por
prudência.** O que mudou é a razão: não é só que os plugins declaram até 7.0, é que
**nenhum deles está na versão que declara isso.**

**O caminho para a 7.1, se um dia for desejado, é um projeto com esta ordem:** atualizar os 13
plugins do wp.org → resolver os 4 pagos/sem-fonte (ACF Pro, AdRotate Pro, tagDiv, Newspaper) →
decidir o que fazer com o `role-quick-changer` abandonado → só então o core, passando por 6.9 e
7.0. **Não é uma atualização; é uma migração.**

**E o alívio é real e verificado: nada disso acontece sozinho.**

---

# TESTE: WordPress 7.1 em HOMOLOG (29/08/2026) — **nada quebrou**

**Só homolog. Produção não foi tocada e está verificada.** O objetivo era descobrir hoje o que a
7.1 quebra, para dimensionar a migração. **A resposta é surpreendente e está medida.**

## Método

**Pelo caminho de código do painel, no pod em execução** — `Core_Upgrader::upgrade()` com a oferta
`7.1 pt_BR`, `FS_METHOD=direct`, seguido de `wp_upgrade()`.

**Por que não pela imagem:** o `Dockerfile` é **compartilhado com produção**. Trocar o `FROM` para
uma tag 7.1 deixaria uma mudança de core esperando o próximo merge para a `main` — armadilha.
Aplicando no pod, a mudança vive no `emptyDir` e some num `rollout restart`.

**Não foi preciso destravar auto-update nenhum:** `auto_update_core_major` continua `'unset'`.
A trava governa o updater **automático**; a atualização manual não passa por ela. **Nada foi
alterado e nada precisa ser desfeito.**

| Etapa | Resultado |
|---|---|
| Troca de arquivos | 08:29:40, ~10 s |
| Migração de banco (`wp_upgrade()`) | **0,2 s** — `db_version` 60421 → **61833** |
| **Indisponibilidade real** | **5 s**, em 2 blocos (4 s + 1 s), códigos 503 |

> ⚠️ **O `kubectl exec` caiu no meio** (`connection reset by peer`, 08:36:27), depois da troca de
> arquivos e **antes** do `wp_upgrade()`. Homolog ficou com core 7.1 e banco em 60421 por ~1
> minuto. Refeito **destacado** (`setsid nohup`), que é como operação longa em pod deve rodar.
> **Lição: `kubectl exec` não é um lugar seguro para processo longo — o túnel cai e não há como
> saber o que ficou pela metade.**

## O achado que reenquadra tudo: **o site usa o editor CLÁSSICO**

O maior risco previsto era o **editor em iframe obrigatório da 7.1** contra o tagDiv, que injeta
JS e CSS no editor. Medido no navegador, na tela de edição de uma matéria real:

```
editor_blocos    : false
editor_classico  : true      (TinyMCE, #content_ifr)
iframe_canvas    : 0         (o iframe da 7.1 e do editor de BLOCOS)
```

**A mudança que mais assustava não se aplica a este site.** O tagDiv monta a experiência de edição
sobre o editor clássico, e a 7.1 não mexeu nele.

## O editor, item a item

| Item | Resultado |
|---|---|
| Tela de edição carrega | ✅ **HTTP 200**, 2,99 MB |
| Campos ACF | ✅ **12 campos**, incluindo **`subtitulo`** e **`imagem`** |
| Metabox do tagDiv | ✅ **11 elementos** `td_post*` / `tdc*` |
| Co-Authors Plus | ✅ 14 elementos |
| Yoast | ✅ 93 elementos |
| Metaboxes visíveis | ✅ **16** |
| Botão de publicar | ✅ presente |
| **Erro fatal na página** | ✅ **nenhum** |
| **Erro de JS no console** | ✅ **NENHUM** |
| Aviso no console | **1**: `wp.compose.pure is deprecated since version 7.1` |

O único aviso de console **não vem do nosso código** — `grep` em `plugins/`, `themes/` e
`mu-plugins/` não acha `compose.pure`. Vem de bundle minificado ou do próprio core.

**Os avisos na tela do painel são todos pré-existentes:** AdRotate ("107 anúncios expirados"),
o pedido de doação do Twitter Auto Publish, e dois avisos padrão do WordPress **ocultos**.

## Publicação e busca

| Camada | Resultado |
|---|---|
| **Matéria de teste** | ✅ post no CPT `politica`, **subtítulo ACF**, **imagem** no campo ACF, **2 coautores** do CAP, **entrou na tabela-sombra**, permalink e páginas de autor gerados — removida sem resíduo |
| **Índice de busca** | ✅ `PRIMARY`, `date_idx`, `ft` — estrutura idêntica |
| **`MATCH` nos 10 termos** | ✅ todos respondendo; contagens iguais às de antes (+1 da matéria de teste) |
| **Site** | ✅ **14 de 14** — home, 4 archives, single, 2 buscas, autor, Quem Somos, 404, `wp-admin`, `wp-login`, `/feed/` 410 |

## Logs — comparados com a linha de base do PHP 8.3

| | PHP 8.3 + WP **6.8.3** | PHP 8.3 + WP **7.1** |
|---|---|---|
| `PHP Fatal error` | **0** | **0** |
| `PHP Deprecated` | **0** | **0** |
| `PHP Warning` | 6 em 25 min | **7 em 25 min** |
| Origens | `co-authors-plus:1193`, `puredevs-gdpr:356` | **as mesmas duas** |

**Nenhum tipo de aviso novo.** O §23 aplicado: sem a linha de base, os 7 avisos seriam
indistinguíveis de regressão.

## Carga, com 5 min de descanso

| | mediana | p90 | máximo | `Threads_running` pico / média |
|---|---|---|---|---|
| WP 6.8.3 | 10,55 s | 13,65 s | 14,32 s | 6 / 3,4 |
| **WP 7.1** | **10,49 s** | **13,66 s** | **14,06 s** | 11 / **3,4** |

30× 200 nas duas, portão verde. **Mediana e p90 idênticos.** O pico de 6 para 11 é ruído de
amostragem de 30 pontos — a média é a mesma (§22: um pico de poucas amostras não mede pico).

---

## Dimensionamento da migração

**Com o que foi medido, a migração para a 7.1 é MUITO menor do que o levantamento previa.**

### O que mudou na avaliação

| No levantamento (antes de testar) | **Medido agora** |
|---|---|
| "Editor em iframe é o maior risco, o tagDiv injeta JS e CSS nele" | **Não se aplica — o site usa o editor clássico** |
| "Teto declarado é 7.0, maioria em 6.8 — apostar sem informação" | **Os plugins declaram 6.8/7.0 e funcionam na 7.1 do mesmo jeito** |
| "Três versões maiores à frente, migração de projeto próprio" | **Zero fatais, zero erros de JS, zero regressão de desempenho** |

### Estimativa

| Trabalho | Dias |
|---|---|
| Repetir este teste com a redação usando o painel de verdade por um dia | **1** |
| Fluxos de painel não cobertos: envio de mídia, agendamento, edição em massa, lixeira, revisões | **1** |
| Atualizar os 13 plugins do wp.org e revalidar (é o maior bloco, e é independente da 7.1) | **2–3** |
| Tema Newspaper e os 3 tagDiv: confirmar com o fornecedor ou testar o pacote novo | **1–2** |
| Decidir o `role-quick-changer` (abandonado desde o WP 4.4) — substituir ou remover | **0,5** |
| Subida em produção com Blue/Green de banco não é necessária; é rollout de imagem | **0,5** |
| **Total** | **6 a 8 dias** |

### Os bloqueadores reais — e nenhum é a 7.1

1. **`role-quick-changer`, abandonado.** Fora do wp.org, `Tested up to 4.4.2`, sem manutenção
   desde 2015. **É o único item da lista sem dono.**
2. **Tema Newspaper 12.7.6 e os 3 plugins tagDiv.** Não declaram compatibilidade com nada, não
   estão no wp.org, e são o código mais acoplado do site. **Funcionaram na 7.1 no teste de hoje**
   — mas o teste cobriu abrir e publicar, não a superfície inteira do tema.
3. **Os 13 plugins desatualizados.** **Este é o trabalho de verdade**, e ele existe
   independentemente da 7.1: ACF 6.2.1.1 → 6.8.9, Smush 3.22.1 → 4.3.2, CAP 2.21.0 → 2.50.1.
4. **ACF Pro e AdRotate Pro** exigem licença válida para atualizar. **Verificar antes de planejar.**

### O que este teste NÃO cobriu, e é honesto dizer

- **Uso real da redação.** Foram ~30 minutos, sem ninguém editando de verdade.
- **Envio de mídia** — e a 7.1 mexeu em validação de dimensões e em `encode quality` no REST,
  que toca o Offload Media e o Smush.
- **6.9 e 7.0**, que ficam no caminho e não foram auditadas isoladamente.
- **A superfície do tema além de abrir e publicar uma matéria.**

> **Veredito: a 7.1 deixou de ser o obstáculo. O obstáculo é a dívida de plugins, que já existia
> e que este teste tornou visível.**

---

# ✅ PRODUÇÃO EM PHP 8.3 — 01/09/2026

```
07:24:22  push na main (fast-forward 804c68f0 -> e090c731)
07:26:24  primeiro pod na imagem nova
07:29:52  workflow verde, 5/5 pods na imagem nova   (3 min 28 s de rollout)
```

**Uma variável só.** A `main` está **47 commits atrás** da `develop` de propósito; um merge traria
tudo. Foi para lá **um commit, um arquivo, uma linha funcional**:

```diff
-FROM wordpress:6.8-php8.2-fpm
+FROM wordpress:6.8-php8.3-fpm
```

Do commit `fd15e6f3` da `develop` veio **só o `Dockerfile`** — a parte que tocava
`deploy-homolog.yml` ficou de fora (é comentário, e é de homolog). Verificado antes: o
`Dockerfile` das duas pontas difere **exatamente** nessa linha mais o bloco de comentário, e o
`php/php.ini` é idêntico.

| | Antes | Depois |
|---|---|---|
| Imagem | `prod-804c68f0…` | **`prod-e090c731de4c158c106f72e542dc9ea8d27d452e`** |
| ReplicaSet | `75f4fdcf7f` | **`7f9b96ffcc`** |
| PHP | 8.2.29 × 5 | **8.3.28 × 5** |
| Extensões | 41, hash `321dd9e4` | **41, hash `321dd9e4` — idêntico** |

## 🔴 DESTAQUE: o core caiu de 6.8.8 para 6.8.3, e isso é do ROLLOUT, não do PHP

**Antes, produção NÃO estava uniforme:**

| Pod | WP | core mtime |
|---|---|---|
| `75f4fdcf7f-44bzk` | **6.8.8** | **2026-08-30 14:15:59** |
| os outros quatro | 6.8.3 | 2025-09-30 17:30:38 |

**Um dos cinco pods servia 6.8.8** — cerca de 20% do tráfego. E os dois pods de 29/08 tinham a
**mesma idade e versões diferentes**: o WP-Cron disparou num e não no outro.

**Depois: cinco pods em 6.8.3, `mtime` 2025-09-30, uniformes.** O `emptyDir` morreu com os pods
antigos e levou junto a auto-atualização. **Isto não tem relação com o PHP** — aconteceria em
qualquer deploy. A `db_version` do banco não se moveu: **60421** antes e depois.

> **O rollout consertou a divergência por acidente e por algumas horas.** O WP-Cron vai
> reatualizar pod a pod, e a divergência volta. É a Tarefa B, e continua de pé.

## 🟡 ACHADO: todo rollout de produção derruba ~2,4% das requisições

**O portão do Albert era explícito: "com `maxSurge 1` / `maxUnavailable 0` não deve haver
indisponibilidade; se houver, é achado."** Houve.

Sonda externa a 1 Hz, com a janela ultrapassando a operação nos dois lados (HANDOVER §28):

| Janela | Amostras | Erros |
|---|---|---|
| **Antes** do rollout | 78 | **0** — 0,00% |
| **Durante** | 85 | **3** — 3,53% |
| **Depois** | 432 | **0** — 0,00% |

E o número real, do CloudWatch do ALB, minuto a minuto:

| | Requisições | Falhas | Taxa |
|---|---|---|---|
| **Durante o rollout (07:26–07:29)** | 1.459 | **35** | **2,40%** |
| Fora dele (07:20–07:45) | 3.991 | 4 | **0,10%** |

**24× a taxa de fundo. Trinta e cinco requisições de leitor real falharam.**

### O mecanismo: falha de CONEXÃO, não da aplicação

```
HTTPCode_ELB_502_Count     29 durante o rollout   <- o ALB nao obteve resposta do alvo
HTTPCode_Target_5XX_Count   6 durante o rollout   <- um pod respondeu 5xx
HTTPCode_ELB_503_Count      0
5xx no log de nginx dos pods NOVOS: 0
```

**29 das 35 falhas foram geradas pelo balanceador**, não pela aplicação — o ALB mandou requisição
para um alvo que não aceitou a conexão. Nginx não registra conexão recusada, e por isso o log dos
pods novos está limpo: **a ausência de registro é consistente com a falha, não a contradiz.**

**A causa está no manifesto, e já era conhecida:**

```
readinessProbe : NAO      em ambos os conteineres
livenessProbe  : NAO
preStop        : NAO
strategy       : maxSurge 1, maxUnavailable 0
```

**Sem `readinessProbe`, `maxUnavailable: 0` não promete o que parece prometer.** O Kubernetes
considera o pod disponível assim que o contêiner sobe — não quando o PHP-FPM aceita conexão. E
sem `preStop`, o pod que está sendo derrubado continua recebendo tráfego durante a
desregistração. **As duas pontas do ciclo de vida estão abertas**, e os dados não permitem separar
qual delas contribuiu mais: dos três 502 da sonda, dois caem 3–4 s depois de um pod novo subir, e
o pico do CloudWatch cobre também as terminações.

> **Isto não é regressão do PHP 8.3. É propriedade de todo deploy de produção**, e sempre foi.
> Este deploy só foi o primeiro medido com sonda contínua dos dois lados. Casa com o item
> "Probes do Deployment de produção — levantamento no Anexo D, **não implementado**", que estava
> na lista de pendências sem número.

**A correção é conhecida e barata:** `readinessProbe` HTTP em ambos os contêineres e
`preStop: sleep 10`. Não foi feita nesta janela — o Albert autorizou **uma** variável.

## Validação

| Camada | Resultado |
|---|---|
| Site (home, 3 archives, 3 singles, autor, 3 buscas, Quem Somos, 404, painel) | **14 de 14** |
| **Busca — MATCH direto**, 10 termos | **10 de 10**, 1–10 ms, índice com **259.094** linhas, `FULLTEXT ft (post_title, post_excerpt)` |
| **Busca — `WP_Query`**, os mesmos 10 | **10 de 10**, 23–98 ms |
| Rascunho com ACF + coautoria | **subtítulo, imagem (CloudFront) e 2 coautores** lidos de volta após `wp_cache_flush()`; não publicado; **removido sem resíduo** |
| mu-plugins | **58** carregados |
| **Fatais / depreciações / notices** | **0 / 0 / 0** |

### Avisos, normalizados por tráfego

| | PHP 8.2 | PHP 8.3 |
|---|---|---|
| Avisos em 15 min | 52 | **11** |
| Requisições na janela | 6.494 | 2.973 |
| **Avisos por mil** | **8,01** | **3,70** |
| Razão | — | **0,46×** |

Mesmas **duas** origens nos dois lados, e uma mudou de texto com a versão:

```
PHP 8.2:  Attempt to read property "user_nicename" on bool
PHP 8.3:  Attempt to read property "user_nicename" on false
```

**Mesmo defeito, mensagem diferente.** Quem procurar pela string antiga num alerta não vai achar.

> ⚠️ **Não afirmo que o 8.3 reduziu os avisos.** O tráfego caiu pela metade entre as duas janelas
> e a linha de base tinha 38 respostas 503 que sumiram depois. O que a medição sustenta é
> **ausência de aumento** — e isso, com 0 fatais e 0 depreciações, é o que o portão pedia.

### Portões de carga

| | T+7 min (cache frio) | T+13 min (após descanso) | Referência: virada do 8.4 |
|---|---|---|---|
| Códigos | **30× 200** | **30× 200** | 30× 200 |
| Mediana | **4,72 s** | **4,90 s** | 5,05–6,07 s |
| p90 | **5,84 s** | 7,83 s | 5,96–8,12 s |
| **`Threads_running` pico** | **8** | **9** | 7–9 |

**Nenhum passou de 10.** Ambos na mesma faixa das medições sob PHP 8.2 — sem regressão.

## Erros meus nesta janela, para o registro

1. **Sonda de busca com a coluna errada.** Usei `MATCH(post_title, post_content)`; a tabela-sombra
   de produção é `(post_title, post_excerpt)`. Deu "0 resultados em 10 termos" — que parecia
   falha de produção e era do meu script. O `WP_Query` no mesmo bloco já mostrava 501 resultados
   em 92 ms, e foi o que denunciou.
2. **Fatal no ACF causado por colisão de variável global.** Script de topo tem variáveis em escopo
   **global**: meu `$acf = function_exists(...)` sobrescreveu o objeto `$acf` do plugin com `true`,
   e `acf()->init()` estourou. **Não foi PHP 8.3.** Deixou um rascunho órfão (`9002416`),
   removido na corrida seguinte; zero rascunhos de teste no banco ao final.

---

## Manifesto de produção fixado no SHA novo — e um rollout que eu não previ

```
07:53:25  push em infra-bahiaba/kubernetes/prod/wordpress/deployment.yaml
          prod-804c68f0… -> prod-e090c731de4c158c106f72e542dc9ea8d27d452e
          nas DUAS linhas image: (initContainer copy-wp-files + conteiner wordpress)
```

O SHA anterior era o **pré-PHP**, fixado de propósito para que um `apply` acidental durante a
validação revertesse em vez de avançar. A validação passou, então a proteção sai do caminho —
mantida, o próximo `apply` desfaria o deploy de hoje.

### 🔴 Eu verifiquei a unidade errada

**Antes do push rodei `kubectl diff` contra o cluster: saída vazia, código 0.** Reportei que
aplicar o manifesto não mudaria nada. **Era verdade sobre o `apply` e falso sobre o pipeline.**

Produção reiniciou: ReplicaSet novo `747784485b`. O diff dos dois templates mostra **um único
campo diferente**:

```
.metadata.annotations.kubectl.kubernetes.io/restartedAt
    ANTES : 2026-08-29T07:36:19Z
    DEPOIS: 2026-09-01T07:54:31Z
```

Essa anotação é assinatura de `kubectl rollout restart`. E ela está no `tf-apply.yml`, como passo
**incondicional** do job de prod:

```yaml
# Mudanca em Secret/ConfigMap nao reinicia pod sozinho: o pod atual segue com
# as variaveis de ambiente que recebeu no startup (envFrom e resolvido uma vez so).
- name: Reiniciar pods para aplicar mudancas de ConfigMap/Secret
  run: kubectl rollout restart deployment/wordpress -n bahia-wordpress
```

**A intenção é correta e o gatilho é largo demais:** o passo existe porque mudança de
ConfigMap/Secret não reinicia pod sozinho, mas ele roda em **qualquer** push que toque
`kubernetes/**` — inclusive um que só corrige a linha `image:` que o pipeline de aplicação já
tinha reconciliado.

### O custo, medido

| | Requisições | Falhas | Taxa |
|---|---|---|---|
| **Rollout 2 (07:54–07:58)** | 628 | **5** | **0,80%** (8,62% no minuto 07:54) |
| Fora dele (07:48–07:58) | 1.365 | 0 | 0,00% |

`HTTPCode_ELB_502_Count = 5`, `HTTPCode_Target_5XX_Count = 0` — **todas falha de conexão, nenhuma
da aplicação**, exatamente o padrão do §29. Menor que o rollout do deploy (35) porque o tráfego
estava mais baixo e havia 3 pods, não 5.

**Estado final conferido:** ReplicaSet `747784485b`, 3 pods `2/2`, os dois contêineres em
`prod-e090c731…`, site em 200. O HPA está em 3 (min 2 / max 5), não é efeito do manifesto.

---

# 🔺 PRIORIDADE ELEVADA — Anexo D sai de "melhoria de desenho"

**O achado agora tem número: 2,40% contra 0,10% de fundo, 24×, com 29 de 35 falhas vindas do ALB
e não da aplicação.** Deixa de ser desenho e vira **"cada deploy de produção derruba requisição
de leitor real, medido"**. Sobe junto com a Tarefa B.

### São DUAS correções, não uma

| # | Falta | O que acontece | Correção |
|---|---|---|---|
| **1** | **`readinessProbe`** | Sem prova de prontidão, o Kubernetes conta o pod como disponível assim que o contêiner sobe. **`maxUnavailable: 0` conta pods, não capacidade de servir** — o alvo entra no balanceador antes de o PHP-FPM aceitar conexão | `readinessProbe` HTTP nos dois contêineres |
| **2** | **`preStop`** | O pod derrubado **segue recebendo tráfego durante a desregistração** no balanceador, enquanto o processo já está parando | `preStop: sleep 10` + `terminationGracePeriodSeconds` compatível |

**As duas pontas do ciclo de vida estão abertas.** Corrigir só a prontidão deixa a terminação
sangrando, e vice-versa. Os dados desta janela não separam qual contribuiu mais — dos três 502 da
sonda, dois caem 3–4 s depois de um pod novo subir, e o pico do CloudWatch cobre também as
terminações.

**Terceiro item, do mesmo achado:** o `rollout restart` incondicional do `tf-apply.yml` deveria
ser **condicional à mudança de ConfigMap/Secret**. Hoje qualquer edição em `kubernetes/**` custa
um rollout — e, enquanto 1 e 2 não existirem, cada rollout custa requisições.

---

# 🔀 Dockerfile separado por ambiente — 01/09/2026, **TEMPORÁRIO**

## O desenho: um arquivo com `ARG`, não dois arquivos

```dockerfile
ARG WP_VERSION=6.8.3                        # default = PRODUCAO
FROM wordpress:${WP_VERSION}-php8.3-fpm
```

```yaml
# deploy-homolog.yml — a UNICA diferenca entre as duas imagens
WP_VERSION: 7.1.0
docker build --build-arg WP_VERSION="$WP_VERSION" ...
```

`deploy-prod.yml` **não muda**: usa o default. **Produção é o padrão, homolog é o desvio, e o
desvio é o que se apaga na saída.**

### Por que não dois arquivos

O levantamento mostrou que **só a linha `FROM` precisa diferir** — `php.ini`,
`zzz-bahia-pool.conf` e `.dockerignore` são **idênticos** entre `main` e `develop`, e as variáveis
de ambiente nem estão na imagem (vivem no ConfigMap/Secret do `infra-bahiaba`).

Com dois arquivos, toda correção futura no build teria de ser feita **duas vezes**, e o
esquecimento seria **silencioso**. Numa separação temporária o risco não é o desenho estar errado
— é apodrecer sem ninguém notar. **Com um arquivo só, a pergunta "como impedir que uma correção
se perca no outro" deixa de existir por construção.**

## Condição de saída — escrita no topo do `Dockerfile`

> A separação acaba quando homolog e produção estiverem na **mesma versão de WordPress E de
> plugins**. O gesto: (1) apagar `--build-arg WP_VERSION=` do `deploy-homolog.yml`; (2) alinhar o
> default do `ARG`. **Nenhum outro arquivo participa.**
>
> **E os patches de PHP se realinham sozinhos nesse mesmo gesto**: os dois ambientes voltam a
> construir a partir da mesma tag, que traz o mesmo PHP. O desalinhamento de patch **não é uma
> pendência própria** — ele nasce e morre com a separação.

## Onde fica o aviso para quem fizer o merge `develop → main`

**No próprio `Dockerfile`, no bloco de comentário do `ARG`** — que é o arquivo que aparece no diff
daquele merge, então o aviso é lido no momento em que importa:

> O `FROM` de produção muda de `6.8-php8.3-fpm` para `6.8.3-php8.3-fpm`. Medido em 01/09/2026: as
> duas tags têm o **mesmo digest** (`sha256:906c2572…`), então é no-op no dia. **Confira o digest
> de novo antes de mergear** — `6.8` é flutuante e pode ter se movido.

## A guarda de build, testada antes de entrar

| Teste | Pedido | Entregue | Resultado |
|---|---|---|---|
| 1 — default (produção) | `6.8.3` | `6.8.3` (db_version **60421**) | ✅ build passa |
| 2 — homolog | `7.1.0` | `7.1` (db_version **61833**) | ✅ build passa (compara major.minor) |
| 3 — **divergência forçada** | `7.1.0` | `6.8.3` | ✅ **build FALHA, código 1** |

**O teste 2 é a prova que faltava para esta etapa:** a imagem `7.1.0` traz `db_version 61833` —
**exatamente o que o banco de homolog já tem**. O rollout torna durável o que hoje só existe no
`emptyDir`, sem migração nova.

`docker build --check` no arquivo real: **"Check complete, no warnings found"**, e o metadata
carregado foi `wordpress:6.8.3-php8.3-fpm` — o default resolve certo.

## Rollout de homolog — a 7.1 SOBREVIVEU, que era o teste desta etapa

```
08:32:46  push na develop (fd15e6f3..b1d0d15b, 13 commits)
08:33:19  guarda de build no CI: "core na imagem: 7.1 (db_version 61833) — pedido: 7.1.0"
08:34:56  workflow verde, pod novo de pe
```

**Indisponibilidade: 38 s**, um bloco contínuo (último 200 às 08:34:10, primeiro 200 às 08:34:48;
1× 504 e 5× 503 entre eles). Homolog tem **1 réplica com `maxSurge: 0`** — a queda é por desenho.

> ⚠️ **Minha primeira contagem deu 36 s em "dois blocos" e estava errada** — o script separou por
> intervalo de 3 s e o buraco entre as amostras era só a resposta de 10,4 s consumindo o intervalo
> da sonda. É o §22 de novo. **Entre 08:34:22 e 08:34:44 não há nenhum 200: é um bloco só.**

### A prova de que a 7.1 agora vem da IMAGEM

| | Antes (aplicada no pod) | Depois (pela imagem) |
|---|---|---|
| `wp_version` | 7.1 | **7.1** |
| **`core mtime`** | **2026-08-29 08:29:40** ← `Core_Upgrader` | **2026-08-19 20:04:50** ← build da imagem |
| `db_version` (banco) | 61833 | **61833** |
| PHP | 8.3.28 | **8.3.33** |

**O `mtime` é a prova.** Se a 7.1 ainda viesse do `emptyDir`, o pod novo teria 6.8.3 com `mtime`
de 2025-09-30. Ele tem os arquivos da 7.1 com a data de build da imagem oficial.
**A armadilha do `db_version` acabou: core e banco são coerentes e sobrevivem a qualquer rollout.**

**E foi provado duas vezes**: o segundo rollout, do `apply` do manifesto (08:48), trouxe a 7.1 de
volta igual.

### 🟡 Consequência do desenho (não é defeito): o PHP também mudou

`8.3.28` → **`8.3.33`**.

**Cada tag do WordPress empacota o próprio patch de PHP.** O `ARG WP_VERSION` isola a versão do
WordPress, mas o **veículo é a imagem base**, e ela carrega o PHP junto. Escolher a versão do
WordPress escolhe, sem dizer, o patch do PHP.

**Homolog está em PHP 8.3.33 e produção em 8.3.28** — mesma minor, patches diferentes. **Isto é
consequência do desenho, não defeito**: qualquer separação por imagem base teria o mesmo efeito, e
a alternativa (fixar o patch do PHP por conta própria) trocaria um desalinhamento por manutenção
de uma segunda dimensão.

> ### ⚠️ O que isso significa para a validação dos plugins que vem agora
>
> **A validação dos plugins vai acontecer sobre um PHP diferente do de produção.** Não é
> bloqueante — é diferença de patch, não de minor —, mas **precisa estar no relatório daquela
> etapa**, para que ninguém atribua a um plugin um comportamento que é do patch do PHP.
>
> Na prática: se um plugin se comportar de forma inesperada em homolog, a pergunta *"isso também
> acontece em 8.3.28?"* faz parte do diagnóstico, e não pode ser respondida em homolog. É o mesmo
> tipo de armadilha do §23 — **um sintoma num ambiente que difere em duas dimensões não diz qual
> delas o causou.**

## Validação em homolog

| Camada | Resultado |
|---|---|
| Site (home, 2 archives, 2 buscas, Quem Somos, autor) | **7 de 7** em 200 |
| Índice de busca | **242.864** linhas, `FULLTEXT (post_title, post_excerpt)` |
| Busca — MATCH / `WP_Query` | **10 de 10** · `s=bahia` 501 encontrados |
| Rascunho com ACF + coautoria | subtítulo, imagem e **2 coautores** lidos de volta; removido sem resíduo |
| **Editor** | **200, 996 KB** — 75 refs TinyMCE, `wp-editor-container`, 56 campos ACF, 25 metaboxes, 92 elementos tagDiv, 50 CAP, 277 Yoast, **0 fatais** |

### ✅ ENVIO DE MÍDIA — o buraco do teste anterior, agora coberto

```
imagem gerada  : 1200x800 JPEG, 30.667 bytes
upload         : ID 9000292 em 6,7 s        (media_handle_sideload)
derivadas      : 13 entradas -> 12 arquivos distintos
                 (medium e td_300x0 dao 300x200 e o WP reusa o mesmo arquivo)
offload S3     : wp_as3cf_items OK — bucket static.bahia.ba, regiao sa-east-1
URL            : https://d1x4bjge7r9nas.cloudfront.net/.../teste-midia-71-...jpg
```

**Verificado dos dois lados:** as URLs respondem **200** no CloudFront (30.667 / 2.170 / 6.955
bytes), o `head-object` da AWS confirma o objeto, e `s3 ls` mostra **13 objetos** no prefixo —
1 original + 12 derivadas, **exatamente o esperado, nada faltando**.

**Na matéria:** `td_485x360` renderizou `<img width="485" height="360" src="…cloudfront…">` com
**srcset**, e os `td_*` do Newspaper foram todos gerados. Smush 3.22.1 ativo, sem interferência.

**A 7.1 mexeu em validação de dimensões e `encode quality` no REST — e nada disso quebrou o
Offload Media nem o Smush.**

Limpeza: rascunho e anexo removidos, 0 resíduo em `postmeta` e em `as3cf_items`. **Os arquivos no
S3 permanecem de propósito** — a guarda `bahia-homolog-guardas.php` registra
`as3cf_remove_source_files_from_provider => __return_empty_array`, porque o bucket é compartilhado
com produção. São 13 objetos de teste, ~95 KB, com nome datado.

## 🟠 Questão aberta: a caixa Publicar não está no HTML do editor

`submitdiv` / `id="publish"` **não aparecem** na tela de edição — nem em post publicado, nem em
post novo, nem em página. O que foi possível estabelecer:

| Verificação | Resultado |
|---|---|
| É mudança da 7.1? | **NÃO** — `post_submit_meta_box` e as 6 ocorrências de `id="publish"` em `meta-boxes.php` são **idênticas** entre o core 6.8.3 e o 7.1 |
| É específico do tipo de post? | **NÃO** — falta em `post` e em `page` |
| Oculto por preferência do usuário? | **NÃO** — `metaboxhidden_post` vazio |
| Restrito pelo PublishPress Capabilities? | **NÃO** — `cme_restrict_editor_features` vazio |
| Algum plugin remove? | os dois `remove_meta_box('submitdiv')` (ACF Pro e CAP) são **escopados a outros post types** |

**Não determinei a causa, e não comparei com produção de propósito:** buscar a tela de admin de
produção exigiria uma sessão, que grava token no banco — fora do que esta etapa autorizou.

> **Precisa de 30 segundos de olho humano:** abrir `hml.bahia.ba/wp-admin` no navegador e ver se o
> botão *Atualizar* está na tela. **Meu `curl` não executa JavaScript**, e o tagDiv Composer tem
> 49–68 referências nessas páginas e manipula a interface do editor em tempo de execução. Se o
> botão estiver lá, não há nada errado. Se não estiver, é defeito **pré-existente** — o core é
> idêntico — e vale conferir em produção também.

## Manifesto de homolog — estava defasado desde antes de hoje

`a9c7d1ab` → **`b1d0d15b`**. O SHA anterior é o commit do *offset do archive*, **anterior até ao
PHP 8.3**: um `apply` teria revertido homolog para antes da 7.1 **e** do 8.3. A disciplina do
mesmo dia não foi seguida no deploy anterior de homolog, e isso estava valendo agora.

Sabendo do §32, **contei com o rollout**: o `tf-apply.yml` reinicia os pods de forma incondicional.
Custou mais um restart de homolog — e serviu como segunda prova de que a 7.1 sobrevive.

---

# 📦 PLANO DE LOTES — plugins em homolog sobre a 7.1

**Levantamento em 01/09/2026, no pod de homolog rodando WordPress 7.1.**

```
31 plugins instalados · 24 ativos · 17 com atualizacao · 13 deles ATIVOS
```

## O que NÃO entra em lote nenhum

| Grupo | Plugins | Por quê |
|---|---|---|
| **tagDiv** | Composer 5.4.5, Cloud Library 3.9.5, Social Counter 5.7 | **sem canal de atualização** — nenhum oferece update |
| **Internos** | Coberturas, Posts do Dia, Relatórios, Vídeo de destaque, Push Notifications | são nossos |
| **Premium sem licença** | AdRotate Professional 5.13.1 | sem licença no `adrotate_config`, sem canal |
| **Já atuais** | Regenerate Thumbnails 3.1.6, PureDevs GDPR 1.0.3, Role Quick Changer 0.2.1 | não há versão nova |
| **Inativos** | Akismet, All-in-One WP Migration, NextScripts, WPS Hide Login | **não rodam**; atualizar não muda comportamento. Decisão separada: atualizar ou **remover** |

## 🔴 Portão prévio: ACF PRO está BLOQUEADO por licença

```
advanced-custom-fields-pro   6.2.1.1 -> 6.8.9
   pacote: (VAZIO — exige licenca)
   acf_pro_license          definida (176 chars)
   acf_pro_license_status   (VAZIA)
   acf_pro_get_license_key(): (VAZIA)
```

**O servidor da ACF responde** — ele sabe que existe a 6.8.9 e que ela foi testada na 7.1 — **mas
não entrega o pacote.** A opção `acf_pro_license` tem conteúdo, e mesmo assim
`acf_pro_get_license_key()` volta vazia e o status está em branco: **a chave não está válida.**

**Consequência para o plano:** o plugin de maior salto (6 minors) e de dependência mais profunda
(todo o modelo editorial: `subtitulo`, `imagem`, 5 grupos de campos) **não pode ser atualizado até
a licença ser resolvida.** Isso é do Albert, não meu.

> **E reordena tudo.** O certo seria ACF cedo, para que os outros 12 fossem validados já sobre a
> versão final. Como não dá, **todos os lotes serão validados sobre o ACF 6.2.1.1**, e quando o
> ACF finalmente subir ele muda debaixo de todos eles. **Isso terá de ser revalidado.**

## Os lotes

| # | Plugins | Salto | Por que juntos / sozinho |
|---|---|---|---|
| **1** | Post Type Switcher `4.0.0→4.0.1` · WP Twitter Auto Publish `1.7.6→1.7.7` · Site Kit `1.180.0→1.186.0` | patch | **Prova o procedimento com o menor custo.** Nenhum toca conteúdo, mídia ou renderização: dois são só admin e um é analytics. Se o processo estiver errado, descobre-se aqui |
| **2** | Disable Comments `2.5.3→2.8.0` · Category Order `1.9.1→2.0` · OneSignal `3.5.0→3.9.2` · FooGallery `2.4.32→3.2.6` | minor a **major** | Periféricos com **sintomas distinguíveis entre si** — comentário, ordem de termo, push e galeria não se confundem. Category Order toca ordenação de taxonomia, e **editorias são CPTs**: merece olhar na navegação |
| **3** | **WP Offload Media Lite** `3.2.11→3.3.1` | minor | **Sozinho, por pedido seu.** É o caminho de toda a mídia do site, e o bucket é **compartilhado com produção**. Bônus: as **244 depreciações de PHP 8.4 da Tarefa A** estão aqui — vale remedir depois |
| **4** | **Smush** `3.22.1→4.3.2` | **major 3→4** | **Sozinho.** Mesmo pipeline do lote 3. Juntá-los destruiria a atribuição **exatamente onde ela mais importa**: se o upload quebrar, qual dos dois foi? |
| **5** | **Co-Authors Plus** `3.6.6→4.1.1` | **major 3→4** | **Sozinho, por pedido seu.** Governa a autoria de toda matéria e a página de autor, que já teve incidente de desempenho (`author-archive-cap-lento`) |
| **6** | **Yoast SEO** `27.7→28.3` | **major** | **Sozinho, por pedido seu.** Salto de major **com migração de indexáveis**: `wp_yoast_indexable` tem ~323 mil linhas. É o lote mais lento e o de maior escrita no banco |
| **7** | **PublishPress Capabilities** `2.21.0→2.50.1` | 29 minors | **Sozinho.** Governa capacidades no admin e **é o principal suspeito da caixa Publicar ausente**. Por último de propósito: até aqui o editor já terá sido validado seis vezes, então uma mudança nele fica atribuída |

**Sete lotes, 12 plugins.** O 13º — ACF PRO — fica fora até a licença.

## Procedimento por lote (igual em todos)

1. **`tar` dos diretórios do lote**, para fora do pod → rollback de arquivo em segundos
2. **Dump do banco** antes dos lotes **2, 4, 5, 6 e 7** — os que fazem migração de dados
3. Atualizar **pelo updater do WordPress**, no pod
4. **Validar** (abaixo)
5. **Extrair os arquivos para o repositório** e commitar — 1 commit por lote
6. **Push** ao fim do lote: prova que sobrevive ao rollout, e é a diferença entre "funcionou no pod" e "está versionado"

### Validação, em todos os lotes
site · busca · **editor abrindo** · rascunho com ACF e coautoria · logs (fatais, depreciações, avisos normalizados por tráfego)

### Validação extra, por lote
- **2** — ordem das editorias na navegação; uma galeria FooGallery renderizando
- **3 e 4** — **envio de mídia completo**: upload, 12 derivadas, S3, `srcset`, aparecer na matéria
- **5** — página de autor e **tempo** dela (o incidente do CAP foi de desempenho, não de erro)
- **6** — contagem de `wp_yoast_indexable` antes e depois, e o tempo da migração
- **7** — a caixa Publicar, com **olho humano no navegador**

## ⚠️ O que o rollback NÃO cobre

**Arquivo volta fácil; migração de banco, não.** Os lotes 2, 4, 5, 6 e 7 têm salto de major ou
salto grande e podem migrar dados. **Desfazer isso exige restaurar o dump inteiro de homolog** —
não há rollback por plugin. Por isso o dump vem antes de cada um deles, e por isso eles são
solitários: um dump restaurado apaga o trabalho de todo lote que estiver junto.

## ⚠️ E o PHP é 8.3.33, não 8.3.28

**Toda esta validação acontece sobre um patch de PHP diferente do de produção.** Não é bloqueante,
mas se um plugin se comportar de forma inesperada, *"isso também acontece em 8.3.28?"* faz parte
do diagnóstico — e **não pode ser respondido em homolog**. Ver a seção da separação do Dockerfile.

## Registros que acompanham o plano de lotes

### Os 4 inativos — decisão adiada para depois dos lotes

Akismet · All-in-One WP Migration · NextScripts · **WPS Hide Login**. Não rodam, então atualizar
não muda comportamento. A escolha é **atualizar ou remover**, e fica para depois.

> 🔗 **O WPS Hide Login tem um resíduo que precisa sair junto se ele for removido.** Foi ele que
> deixou a opção órfã **`whl_page = 'acesso'`**, que faz **`/acesso/` cair numa matéria por
> adivinhação do núcleo** — o WordPress não encontra a página, chuta o post mais parecido e serve
> outra coisa. **Remover o plugin sem apagar a opção mantém o defeito sem o culpado à vista.**
> Se a decisão for remover, a opção sai no mesmo gesto.

### Os que ficam fora por não ter canal de atualização

| Plugin | Versão | Situação |
|---|---|---|
| tagDiv Composer | 5.4.5 | sem canal — atualização só pelo fornecedor |
| tagDiv Cloud Library | 3.9.5 | idem |
| tagDiv Social Counter | 5.7 | idem |
| Coberturas · Posts do Dia · Relatórios · Vídeo de destaque · Push Notifications | 1.0 | **nossos** |
| **AdRotate Professional** | **5.13.1** | 🟡 **pago, e sem licença no `adrotate_config`** |

### 🟡 Pendência nova: AdRotate Professional é pago e está sem licença

Medido em 01/09/2026: `adrotate_config` **não tem licença**, e o plugin **não oferece nenhuma
atualização**. Ele governa toda a publicidade do site — os grupos, os agendamentos e a contagem
de entrega que sustenta a PI.

> **Plugin pago sem licença não recebe correção de segurança.** Não é questão de perder recurso
> novo: é ficar de fora do canal por onde a correção chegaria, num plugin que fica **em toda
> página** e que **grava** (a tabela `wp_adrotate_tracker`, que já precisou de `OPTIMIZE`).
> Não há como saber daqui se existe correção pendente — **é exatamente esse o problema.**

Fica como pendência de decisão comercial, ao lado das licenças do ACF PRO.

---

# 🔴 CORREÇÃO GRAVE — o site NÃO usa o editor clássico

**Verificado no navegador em 01/09/2026, com sessão real em `hml.bahia.ba`.**

## O que eu afirmei em 29/08, e estava errado

> *"O site usa o editor CLÁSSICO (`editor_classico: true`, `iframe_canvas: 0`), então o iframe
> obrigatório da 7.1 — o maior risco previsto contra o tagDiv — **não se aplica**."*

**Era o ponto central daquele relatório, e é falso.** Medido no navegador:

```
blocos_wp_data     : true                  <- editor de BLOCOS ativo
canvas_em_IFRAME   : true
iframe_src         : blob:https://hml.bahia.ba/fc66f79f-...
content_ifr        : 0                     <- nao ha editor classico
botao              : editor-post-publish-button "Salvar", habilitado
classe do body     : ... is-fullscreen-mode post-php post-type-post
```

**O editor de blocos está ativo E o canvas da 7.1 está em iframe.** O conteúdo é **um único bloco
`core/freeform`** — o bloco Clássico —, e é por isso que o TinyMCE aparece carregado: ele serve
esse bloco, não a tela.

### Por que errei

A determinação de 29/08 saiu de **PHP por `kubectl exec`**, fora de uma requisição de admin.
`use_block_editor_for_post()` e o contexto de tela dependem de `is_admin()`, da tela atual e de
filtros que só rodam numa requisição real do painel. **Em CLI a resposta é outra — e é confiante.**
Mesma família do `is_admin()` valendo `true` em `admin-ajax`. Ver HANDOVER §34.

### A conclusão "nada quebrou" continua de pé — e agora vale mais

Antes eu dizia que nada quebrou **porque o risco não se aplicava**. A verdade é melhor: **o risco
se aplicava, e o site passou por ele.** Zero erros de console, zero blocos inválidos, 8 campos ACF,
11 metaboxes (5 do ACF, 3 do tagDiv, Yoast, OneSignal, Twitter), 152 elementos tagDiv, botão de
salvar habilitado.

## 🟠 O que o console revelou — e que eu não tinha visto

**Zero erros. Nove advertências por carga** (mais 2 logs informativos), e três delas nomeiam o risco futuro com precisão:

```
Block with API version 2 or lower is deprecated since version 6.9.
  "adrotate/advert"        registered with API version 1
  "adrotate/group"         registered with API version 1
  "fooplugins/foogallery"  registered with API version 1
  -> "This means that the post editor MAY WORK AS A NON-IFRAME EDITOR. Since all
      editors are planned to work as iframes in the future, set `apiVersion` to 3
      and test the block inside the iframe editor."
```

**É o mecanismo que explica tudo.** O WordPress mantém um **caminho de compatibilidade sem
iframe** para quem registra bloco em API antiga — e avisa que ele **vai acabar**.

| Bloco legado | Plugin | Consegue ser corrigido? |
|---|---|---|
| `adrotate/advert` | AdRotate **Professional** | 🔴 **NÃO** — pago e **sem licença**, sem canal de atualização |
| `adrotate/group` | AdRotate **Professional** | 🔴 **NÃO** — idem |
| `fooplugins/foogallery` | FooGallery 2.4.32 | 🟢 **talvez** — o lote 2 sobe para **3.2.6** |

> **A licença ausente do AdRotate deixou de ser risco abstrato de segurança.** Ela tem uma
> consequência datada e nomeada: quando o WordPress remover o caminho sem iframe, **os dois blocos
> do AdRotate quebram dentro do editor**, e não há por onde receber a correção.

E o tagDiv tem blocos Gutenberg próprios, que enfileiram estilo de forma incompatível com o iframe:

```
td-guten-blocks-editor-css-css was added to the iframe incorrectly.
td-gut-editor-css              was added to the iframe incorrectly.
```

Hoje é aviso, não erro. **Mas é exatamente a superfície de compatibilidade do iframe** — e o tagDiv
também não tem canal de atualização.

Demais avisos: `wp.compose.pure` (depreciado na 7.1), `wp.compose.withState` (5.8),
`wp.editPost.PluginDocumentSettingPanel` (6.6) — uso de API velha por plugins.

## 🔴 E o "zero erros, um aviso" de 29/08 era subcontagem

Naquele dia relatei **1 aviso**. Hoje são **9 advertências por carga**. A diferença não é o ambiente: é que o
rastreamento de console **começa quando a ferramenta é chamada**, e eu havia capturado **depois**
da carga. **Os avisos de carregamento não estavam lá para serem lidos.**

Corrigido no método: **chamar o leitor de console ANTES, recarregar, e só então ler.**

## O `submitdiv` ausente: explicado, e não era defeito

`#submitdiv` e `#publish` = **0**, e está certo: **o editor de blocos não tem caixa Publicar.**
O controle é o botão React `editor-post-publish-button`, presente e habilitado.
**A pergunta estava mal formulada desde o início** — eu procurava, num editor de blocos, um
elemento que só existe no clássico.

**Consequência para o plano de lotes:** o lote 7 (PublishPress Capabilities) **não sobe** para
primeiro. Não há caixa Publicar ausente para diagnosticar.

---

## ✅ LOTE 1 — concluído em 01/09/2026

| Plugin | De | Para |
|---|---|---|
| Post Type Switcher | 4.0.0 | **4.0.1** |
| WP Twitter Auto Publish | 1.7.6 | **1.7.7** |
| Site Kit by Google | 1.180.0 | **1.186.0** |

Os três continuam **ativos**. Atualizados pelo `Plugin_Upgrader` no pod, com
`bulk_upgrade` — as três respostas `OK`, e o log do upgrader mostra download do wordpress.org,
descompactação, remoção da versão antiga e "Plugin updated successfully" para cada um.

**Rede antes de mexer:** `tar` dos três diretórios (4.272.082 bytes, 2.259 entradas) guardado —
rollback de arquivo em segundos. **Sem dump de banco**: nenhum dos três migra dados.

### Validação

| Camada | Resultado |
|---|---|
| Site (home, 2 archives, busca, Quem Somos, autor) | **6 de 6** em 200 |
| Busca | índice **242.864** linhas · **10 de 10** termos · `s=bahia` 501 encontrados |
| Rascunho com ACF + coautoria | subtítulo ok, imagem `9000219`, **2 coautores**, removido sem resíduo |
| Post Type Switcher carregado | sim |
| Logs (8 min) | **0 fatais · 0 depreciações · 0 notices** · 5 avisos, mesmas duas origens conhecidas |
| **Editor no navegador** | canvas em iframe, **0 blocos inválidos**, botão Salvar habilitado, 8 campos ACF, 11 metaboxes, 152 elementos tagDiv, **0 avisos do editor** |

### 📌 Linha de base do console, para comparar nos próximos lotes

**Zero erros. Nove advertências por carga**, idênticas antes e depois deste lote:

```
2x  Block API version 1: adrotate/advert, adrotate/group
1x  Block API version 1: fooplugins/foogallery        <- ALVO DO LOTE 2
1x  wp.compose.pure deprecated since 7.1
1x  wp.compose.withState deprecated since 5.8
1x  wp.editPost.PluginDocumentSettingPanel deprecated since 6.6
3x  ... added to the iframe incorrectly (global-styles, td-guten-blocks, td-gut)
```

> **O teste objetivo do lote 2 é este:** se depois de subir o FooGallery para 3.2.6 a advertência
> de `fooplugins/foogallery` **sumir**, é um bloco legado a menos — e sobram só os dois do
> AdRotate, que não têm conserto pelo nosso lado.

**Nota de contagem:** o registro anterior dizia "11 avisos"; são **9 advertências mais 2 logs
informativos** (`JQMIGRATE` e `api-fetch preload`). Corrigido acima, porque este número é a
linha de base de comparação dos próximos lotes e precisa estar exato.

---

## ✅ LOTE 2 — concluído em 01/09/2026

| Plugin | De | Para | Salto |
|---|---|---|---|
| Disable Comments | 2.5.3 | **2.8.0** | minor |
| Category Order and Taxonomy Terms Order | 1.9.1 | **2.0** | **major** |
| OneSignal Push Notifications | 3.5.0 | **3.9.2** | minor |
| FooGallery | 2.4.32 | **3.2.6** | **major** |

Os quatro continuam **ativos**.

**Rede antes:** dump do banco **verificado** — 578.906.249 bytes (552 MiB), `gzip -t` OK, rodape
`Dump completed on 2026-09-01 9:31:27`, **92 `CREATE TABLE` × 92 tabelas no banco**, 233
ocorrências do `siteurl` de homolog, SHA-256 gravado ao lado, arquivo em `444`. Mais o `tar` dos
quatro diretórios (9.008.164 bytes, 716 entradas).

> **A lição de 29/08 aplicada:** o dump saiu **sem `--rm`** no `kubectl run`, e por isso **não há
> a linha `pod "..." deleted` dentro do gzip**. Conferido explicitamente: *"ruído do kubectl no
> fim: nenhum (limpo)"*.

### Migração de dados: houve, e era esperada

```
Disable Comments  db_version  7 -> 8     <- migrou, e preservou remove_everywhere=true
comentarios       318 -> 318             <- intactos
Terms Order       term_order<>0: 0 de 76.562 -> 0 de 76.562   <- sem ordenacao propria, nada a migrar
OneSignal         86 chaves -> 86 chaves
FooGallery        24 galerias -> 24 galerias
```

### Validação

| Camada | Resultado |
|---|---|
| Site | **6 de 6** em 200 |
| Busca | índice 242.864 · **10 de 10** termos |
| **Galeria renderizando** | `[foogallery id="547226"]` devolveu **4.656 bytes com `<img>`**, sem erro |
| Rascunho com ACF + coautoria | subtítulo, imagem, **2 coautores**, removido sem resíduo |
| Logs | **0 fatais · 0 depreciações · 0 notices** · 2 avisos, mesmas origens conhecidas |
| Editor no navegador | canvas em iframe, **0 blocos inválidos**, Salvar habilitado, 8 campos ACF, 11 metaboxes, 0 avisos do editor |

### 🎯 O teste objetivo — passou, e confirmado na causa

```
console ANTES : 9 advertencias, incluindo "fooplugins/foogallery ... API version 1"
console DEPOIS: 8 advertencias — a do foogallery SUMIU
```

E a causa, medida direto no registro de blocos em vez de inferida da ausência do aviso:

```
wp.blocks.getBlockType('fooplugins/foogallery').apiVersion  ->  3
blocos legados restantes, de 126 registrados:
   adrotate/advert   apiVersion 1
   adrotate/group    apiVersion 1
```

> **Um bloco legado a menos, e agora o problema tem tamanho exato: dois blocos, um plugin, sem
> canal de correção.** O item 13 do `PENDENCIAS-gestores.md` foi atualizado com esse contraste —
> o FooGallery custou uma atualização de rotina; o AdRotate virou decisão porque não há por onde
> a correção chegar.

---

# 🔺 ANEXO D ELEVADO OUTRA VEZ — o incidente é a TERCEIRA manifestação

**Registrado em 01/09/2026.** O mesmo achado apareceu três vezes no mesmo dia, por três caminhos
diferentes:

| # | Onde | Custo medido |
|---|---|---|
| **1** | Rollout do PHP 8.3 em produção | **35 falhas em 1.459 req = 2,40%**, contra 0,10% fora da janela |
| **2** | Rollout disparado pelo `apply` do manifesto | 5 falhas em 628 req = 0,80%, contra 0,00% fora |
| **3** | **Incidente da redação** | quem está logado **não tem cache para absorver** — é quem paga o rollout, e é quem pagou a saturação |

**A terceira manifestação é a que muda a natureza do item.** As duas primeiras eram custo de
operação nossa. A terceira mostra que **a mesma população — a redação — é a que sofre nos dois
casos**, porque em nenhum dos dois há cache entre ela e o PHP.

E os **dois rollouts da correção de capacidade somam mais uma dose**: cada um ~2,40%, ambos
pagos principalmente por quem está logado.

> **O Anexo D deixou de ser melhoria de desenho e virou item que se paga a cada operação.**
> Enquanto não existir `readinessProbe` e `preStop`, toda mudança em produção — inclusive as que
> existem para ajudar a redação — cobra um pedágio da redação.

**São três correções, e continuam sendo três:**

1. `readinessProbe` — sem ela, `maxUnavailable: 0` conta pods, não capacidade de servir
2. `preStop` — sem ele, o pod derrubado segue recebendo tráfego durante a desregistração
3. **`rollout restart` incondicional no `tf-apply.yml`** — deveria ser condicional a mudança de
   ConfigMap/Secret; hoje qualquer edição em `kubernetes/**` custa um rollout inteiro

## Caminho 3 da capacidade — tarefa própria, ao lado do Anexo D

**O HPA por CPU é cego para esgotamento de pool por construção.** Medido: durante a saturação
completa dos 60 workers, `cpu=38%` e `memory=38%` — nenhuma das duas métricas chega perto do
gatilho, porque worker bloqueado esperando I/O **não gasta CPU**.

**O sinal certo é ocupação do pool ou tempo de fila**, não utilização de CPU. Implementação
possível: expor o `php-fpm status` (`listen.status_path`), coletar `active processes` /
`listen queue`, e escalar por métrica externa. **Não resolve amanhã, e por isso não entra na
janela de capacidade** — mas é o que faz a correção de hoje parar de ser um número escolhido a
mão.
