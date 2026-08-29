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
