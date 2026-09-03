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

> 🟢 **ATUALIZAÇÃO 02/09/2026 — a tabela acima está velha, e o bloqueio mudou de natureza.**

O lote 3 subiu o WP Offload Media para **3.3.1** (o 3.3.0 declara *"PHP 8.4 compatible"* e
*"PHP 8.5 compatible"* no changelog). Medido com o **mesmo instrumento antes e depois**, no mesmo
pod:

```
amazon-s3-and-cloudfront    248 -> 1        TOTAL   286 -> 39
```

**Esta tarefa não estava travada em "código de terceiro" no geral — estava travada NELE.** Ele
respondia por 87%, e saiu. O que resta não é a mesma lista mais curta; **é uma lista de outra
natureza:**

| O que sobrou | Ocorrências | Quem resolve |
|---|---|---|
| **`adrotate-pro`** (`library/mobile-detect.php`) | **9** | ❌ **pago, sem licença, sem canal** — 🔗 vai para o **item 13** dos gestores |
| `twitter-auto-publish`, `td-social-counter`, `google-site-kit` | 28 | 🟡 vendorizadas em plugins **com canal aberto** — chegam sozinhas |
| **`bahia_refactor` e `bahia_social`** (`Mobile-Detect`) | **2** | ✅ **nosso repositório — é trabalho que podemos fazer** |
| `mu-plugins` (código nosso) | 0 | — |

**A frase de 29/08 — *"o caminho para o 8.4 é esperar os releases, não corrigir código"* — deixou
de ser verdade inteira.** Hoje o caminho é:

1. **2 são nossos.** Duas bibliotecas `Mobile-Detect` vendorizadas no nosso repositório. Não
   dependem de ninguém, e o gesto é trocar `Foo $x = null` por `?Foo $x = null`.
2. **28 chegam sozinhas**, conforme os fabricantes lançarem — todos têm canal aberto.
3. **9 são do AdRotate, e não têm por onde chegar.** Mesmo plugin, mesmo problema de canal e agora
   **o mesmo item de decisão que o do editor isolado**: são dois relógios andando juntos sobre a
   mesma peça. Registrado no **item 13** do `PENDENCIAS-gestores.md`.

> ⚠️ **Nota de instrumento, para não inflar o resultado.** A varredura de 29/08 contou **244** no
> Offload e **280** no total; a minha conta **248** e **286** **no mesmo código 3.2.11**. São
> regras de contagem um pouco diferentes, e a de 29/08 não foi reproduzida. **O que sustenta a
> conclusão é que antes e depois saíram do mesmo instrumento** — a queda de 247 é real.

**A Tarefa A não está pronta; está viável.** Ela saiu de "depende de release de terceiro" e virou
"9 numa decisão comercial e 2 de trabalho nosso". **Não neste ciclo de lotes** — mas merece
reavaliação assim que os sete fecharem.

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
| `wordpress-seo` (Yoast) | **27.7** | 28.3 → **28.4 no dia da execução** | **7.1** | 7.4 |
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
| **3** ✅ | **WP Offload Media Lite** `3.2.11→3.3.1` | minor | **Sozinho, por pedido seu.** É o caminho de toda a mídia do site, e o bucket é **compartilhado com produção**. Bônus: as **244 depreciações de PHP 8.4 da Tarefa A** estão aqui — vale remedir depois |
| **4** ✅ | **Smush** `3.22.1→4.3.2` | **major 3→4** | **Sozinho.** Mesmo pipeline do lote 3. Juntá-los destruiria a atribuição **exatamente onde ela mais importa**: se o upload quebrar, qual dos dois foi? |
| **5** ✅ | **Co-Authors Plus** `3.6.6→4.1.1` | **major 3→4** | **Sozinho, por pedido seu.** Governa a autoria de toda matéria e a página de autor, que já teve incidente de desempenho (`author-archive-cap-lento`) |
| **6** ✅ | **Yoast SEO** `27.7→`**`28.4`** — ⚠️ o plano dizia **28.3**, o canal andou antes da execução | **major** | **Sozinho, por pedido seu.** ~~O lote mais lento e o de maior escrita no banco~~ — **a previsão não se confirmou**: a migração é DDL instantâneo (duas colunas anuláveis), os ~316 mil indexáveis **não** foram reconstruídos, e o `FILE_SIZE` não mudou. Ver a seção do lote 6 |
| **7** ✅ | **PublishPress Capabilities** `2.21.0→2.50.1` | 29 minors | **Sozinho, e o alvo mudou em 02/09.** ~~Principal suspeito da caixa Publicar ausente~~ — a caixa existe, aquilo era artefato do meu `curl` sem JavaScript. O que ele governa é **capacidade e papel**, então o teste é **se a redação continua conseguindo publicar**, não se o botão está desenhado. Por último de propósito: até aqui o editor já terá sido validado seis vezes, então uma mudança nele fica atribuída |

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
- **3 e 4** — **envio de mídia completo**: upload, 12 derivadas, S3, `srcset`, aparecer na matéria.
  **A linha de base mudou em 02/09**: um repórter subiu imagem por navegador nesta 7.1 com o
  Offload **3.2.11** e o Smush **3.22.1**. A pergunta destes dois lotes deixou de ser *"a mídia
  funciona na 7.1?"* — já está respondida — e passou a ser *"continua funcionando com as versões
  novas?"*. Comparar contra o envio humano, não contra a hipótese
- **5** — página de autor e **tempo** dela (o incidente do CAP foi de desempenho, não de erro)
- **6** — contagem de `wp_yoast_indexable` antes e depois, e o tempo da migração
- **7** — **não** "a caixa Publicar existe" (já respondido em 02/09). O teste é de **permissão
  efetiva, por papel**: para *administrator*, *editor* e *author*, conferir `user_can` em
  `publish_posts`, `edit_published_posts`, `upload_files` e `edit_others_posts` **antes e depois**,
  e publicar de fato um rascunho com o papel mais restrito que a redação usa. Um salto de 29
  minors em quem governa capacidade tira acesso sem avisar — e o sintoma disso **não** é um botão
  sumido, é um "você não tem permissão" na hora de publicar

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

# ✅ VALIDAÇÃO HUMANA DA 7.1 — 02/09/2026

**Não fui eu quem validou desta vez.** Um repórter da redação **publicou uma matéria e subiu uma
imagem em homolog**, no fluxo real, pelo navegador, sem instrução minha sobre o que exercitar.
**Funcionou por inteiro.**

| O que o humano exercitou | Resultado |
|---|---|
| Publicar matéria pelo editor | **funcionou normalmente** |
| **Enviar imagem** pelo painel | **funcionou 100%** |

## O que essa validação fecha

Ela cobre o **último buraco declarado** do teste da 7.1: até aqui, o envio de mídia tinha sido
provado por **`media_handle_sideload` chamado por mim em PHP** — que entra pelo mesmo caminho de
gravação, mas **não passa pelo REST nem pelo uploader do navegador**, que é exatamente onde a 7.1
mexeu (validação de dimensões e `encode quality`). Agora passou, com um humano na ponta.

**Estado sob o qual isso aconteceu, e ele importa mais que o resultado:**

```
WordPress   7.1          (db_version 61833)
PHP         8.3.33
plugins     lotes 1 e 2 aplicados
WP Offload Media Lite    3.2.11   <- versao ANTIGA
Smush                    3.22.1   <- versao ANTIGA
```

## 🎯 E isso REESCREVE o que os lotes 3 e 4 precisam provar

A pergunta que os lotes de mídia carregavam era ambígua, e agora não é mais:

| | Pergunta | Situação |
|---|---|---|
| ~~antes~~ | *"a mídia funciona na 7.1?"* | ✅ **RESPONDIDA** — por humano, com as versões antigas dos dois plugins |
| **agora** | *"a mídia continua funcionando **com as versões novas** dos dois plugins?"* | ⬜ é o que os lotes 3 e 4 têm de responder |

**A consequência prática é sobre a linha de base.** Se o upload quebrar no lote 3 ou no lote 4, a
comparação **não é contra uma hipótese** — é contra um envio humano bem-sucedido, datado, nesta
mesma 7.1, neste mesmo PHP, com **uma única variável trocada: a versão do plugin**. A atribuição
fica limpa, e é por isso que os dois seguem separados.

> **Dito ao contrário, para não se perder:** um upload que falhe daqui para a frente **é do
> plugin novo**, não da 7.1. A 7.1 já foi absolvida por quem usa o sistema.

## O que ela NÃO cobre — para não esticar a conclusão

- **Um envio**, não um lote de vários nem arquivo grande — o `offload-s3-imagens-grandes` (40+ MP)
  continua sem teste na 7.1
- Não sei qual **formato nem qual tamanho** o repórter enviou; se foi PNG, o `png-formato-upload`
  segue valendo igual
- A **caixa Publicar** continua sendo questão aberta: ele publicou, então **o botão existe para
  olho humano** — o que confirma que a ausência no meu HTML era do meu `curl` sem JavaScript, e
  **não** um defeito. Isso alivia o lote 7, mas não o dispensa


# ✅ LOTE 3 — concluído em 02/09/2026

| Plugin | De | Para | Salto |
|---|---|---|---|
| WP Offload Media Lite | 3.2.11 | **3.3.1** | minor (com **3.3.0** no meio) |

Continua **ativo**. Atualizado pelo `Plugin_Upgrader` no pod, com `bulk_upgrade` — resposta `OK`, e
o log do upgrader mostra `downloading_package`, `unpack_package`, `installing_package`,
`remove_old`, `process_success`.

## Rede antes de mexer

| | |
|---|---|
| **`tar` do diretório** | `plugins-pre-lote3-offload-3.2.11.tgz` — **3.726.234 bytes, 2.384 entradas** |
| **Dump do banco** | `dump-HOMOLOG-pre-lote3-20260902-1106.sql.gz` — **586.005.843 bytes**, `gzip -t` OK |
| Primeira linha | `-- MySQL dump 10.13` — **sem ruído do `kubectl`** (dump por `exec` em pod dedicado) |
| Rodapé | `Dump completed on 2026-09-02 10:08:36` |
| Estrutura | **92 `CREATE TABLE` × 92 tabelas no banco** · 246 ocorrências do `siteurl` de homolog |
| `grep 'pod ".*" deleted'` | **0** |
| SHA-256 | `b022980e…f4a3`, gravado ao lado · arquivo em `444` |

> O dump saiu **antes** deste lote mesmo o plano original não exigindo — por pedido seu, todos os
> lotes de 3 a 7 têm dump próprio. O lote 3 acabou **migrando dado sim** (abaixo), então foi bom.

## Migração de dados: houve, e é só um marcador

```
as3cf_schema_version   3.2.11 -> 3.3.1      <- migrou
wp_as3cf_items         155.600 -> 155.600   <- intacta
colunas da tabela      14 -> 14, nomes identicos
anexos                 155.675 -> 155.675
settings (29 chaves)   identicos, um a um   <- bucket, regiao, cloudfront, prefixo, remove-local-file
```

## 🎯 O teste que este lote existia para fazer — o envio de imagem, em três caminhos

**A linha de base era o envio humano de 01/09**, com Offload 3.2.11. Refeito agora com 3.3.1:

| Caminho | Como | Resultado |
|---|---|---|
| **A — `media_handle_sideload`** | PHP, o mesmo instrumento de 01/09 | ✅ **13 entradas → 12 arquivos**, idêntico ao de 01/09 |
| **B — controlador REST** | `rest_do_request` em `/wp/v2/media` | ✅ **201**, 13 → 12, offload OK |
| **C — 🔴 NAVEGADOR** | `wp.apiFetch` na sessão real, pelo nginx + PHP-FPM | ✅ **201 em 7,4 s** |

**O caminho C é o que reproduz o repórter**, e é o que fecha o lote: cookie de sessão de verdade,
nonce do REST, passando por nginx e PHP-FPM, no mesmo endpoint que o uploader do editor usa.

```
imagem     : 1600x1067 JPEG, 28.624 bytes, gerada no canvas do navegador
resposta   : 201 em 7.437 ms, ID 9000312, image/jpeg
derivadas  : 14 entradas -> 13 arquivos distintos (9 delas td_* do Newspaper)
offload    : bucket static.bahia.ba, regiao sa-east-1, is_verified=1
URL        : https://d1x4bjge7r9nas.cloudfront.net/.../teste-navegador-lote3-...jpg
CloudFront : 200 (28.624 bytes) e 200 na td_485x360 (5.795 bytes)
s3 ls      : 14 objetos no prefixo — 1 original + 13 derivadas, nada faltando
srcset     : PRESENTE
local      : 0 de 14 arquivos no disco — remove-local-file=1 honrado
```

## 🟠 O achado do lote, e por que ele NÃO é defeito — mas muda o instrumento

Rodando o teste REST **pelo meu `kubectl exec`**, o log encheu:

```
AS3CF: Could not initialize WP_Filesystem.
PHP Warning: Undefined array key "remove_result" ... remove-local-handler.php on line 217
```

…e o arquivo local **não era removido**. Levaria a "regressão do 3.3.1" — e estaria errado.

**A causa é o `uid`, não o plugin.** O `kubectl exec` roda como **root**; o PHP-FPM roda como
**www-data**. Medido, lado a lado, no mesmo pod:

| Sob qual usuário | `get_filesystem_method()` | `WP_Filesystem()` | remoção local | tempo |
|---|---|---|---|---|
| **root** (meu `exec`) | cai para FTP | **false** | ❌ 13 de 13 ficam no disco | **40,2 s** |
| **www-data** (o do FPM) | `direct` | **true** (`WP_Filesystem_Direct`) | ✅ 0 de 13 no disco | **7,3 s** |

E o `3.3.1` **documenta a mudança** no próprio changelog:

> *"Offload and remove from local triggered from outside the admin context no longer sometimes
> results in a fatal error"*

O `3.2.11` chamava `@unlink()` direto; o `3.3.1` exige `WP_Filesystem` e **desiste com `return
false`** se não conseguir inicializar. Em contexto de web isso é uma proteção; fora dele, vira
aviso. **Não há caminho de produção que caia nisso:** não existe `CronJob`, não existe `crontab`
no contêiner, e `DISABLE_WP_CRON` não está definida — o WP-Cron roda **na requisição web**, como
`www-data`.

> 📌 **Lição de método, e vale para os lotes 4 a 7:** meu arranjo de teste rodava como **root**, que
> **não é o usuário do site**. Para qualquer coisa que toque `WP_Filesystem`, permissão ou remoção
> de arquivo, o teste tem de rodar como **www-data** — senão eu meço o meu harness, não o site.
> Os 33 segundos a mais eram o *fallback* de FTP tentando e falhando.

## 🟢 E o bônus da Tarefa A entregou — o PHP 8.4 destravou

O 3.3.0 declara no changelog **"PHP 8.4 compatible"** e **"PHP 8.5 compatible"**. Medido, com o
**mesmo instrumento antes e depois**, no mesmo pod:

```
amazon-s3-and-cloudfront    248  ->  1      (-247)
```

| Escopo | Antes do lote 3 | Depois | |
|---|---|---|---|
| `amazon-s3-and-cloudfront` | **248** | **1** | `vendor/Gcp/google/auth/…/ApplicationDefaultCredentials.php` |
| Demais plugins | 36 | 36 | mobile-detect, twitteroauth, ca-bundle, php-jwt |
| Temas | 2 | 2 | as duas `Mobile-Detect` vendorizadas |
| `mu-plugins` (nosso) | 0 | 0 | |
| **TOTAL** | **286** | **39** | |

> **Nota de instrumento, para não inflar o resultado:** a varredura de 29/08 contou **244** no
> Offload e **280** no total; a minha conta **248** e **286** **no mesmo código 3.2.11**. São
> regras de contagem um pouco diferentes. O que vale é que **antes e depois saíram do mesmo
> instrumento**: a queda de **247 ocorrências** é real, e o Offload deixa de ser o bloqueador.

**O que isso muda na Tarefa A:** o bloqueio do PHP 8.4 era *"244 no Offload, código de terceiro,
depende de release deles"*. **O release chegou.** Sobram 39 ocorrências, e o desenho delas é outro:

- **2 estão no nosso repositório** (`bahia_refactor` e `bahia_social`, `Mobile-Detect` vendorizada)
- 9 no `adrotate-pro` — **pago e sem licença**, continua sem canal
- as outras 28 em `twitteroauth`, `ca-bundle` e `php-jwt` vendorizados

**A Tarefa A não está pronta — está viável.** Ela deixou de depender de um release de terceiro e
passou a depender de decisão nossa. Merece reavaliação, mas **não neste ciclo de lotes.**

## Validação

| Camada | Resultado |
|---|---|
| Site (home, 3 archives, 2 buscas, Quem Somos, autor) | **7 de 7** em 200 |
| Busca | índice `wp_bahia_search_idx` **242.865** linhas · **10 de 10** termos · `s=bahia` 501 · 98–456 ms |
| **Envio de mídia** | **os três caminhos acima**, incluindo o do navegador |
| Rascunho com ACF + coautoria | subtítulo, imagem e **2 coautores** lidos de volta; removido sem resíduo |
| **Editor no navegador** | canvas em iframe, **126 blocos registrados, 0 inválidos**, botão **Publicar** presente e habilitado, 8 campos ACF, 11 metaboxes, 161 elementos tagDiv, 8 do CAP, **0 avisos do editor** |
| Logs — janela de 8 min, **240 requisições com bypass de cache** | **0 fatais · 0 depreciações · 0 notices · 0 linhas `AS3CF`** |

### Os 26 avisos da janela, e nenhum é deste lote

```
24x  Attempt to read property "user_nicename" on false
     co-authors-plus/php/class-coauthors-plus.php        <- 1 por acesso a /colunistas/
 2x  Cannot modify header information - headers already sent
     puredevs-gdpr-compliance/public/class-pd-gdpr-public.php
```

> **O 24 não é coincidência:** a janela bateu **24 vezes** em `/colunistas/da-redacao/`, e o aviso
> saiu **uma vez por acesso**. É a página de autor, pré-existente, e é **alvo do lote 5** — que já
> tem o incidente de desempenho do CAP na conta. Fica medido aqui como "antes".

### 📌 Console — a linha de base de 8 se manteve, item a item

**Zero erros. Oito advertências**, exatamente as mesmas do fim do lote 2:

```
2x  Block API version 1: adrotate/advert, adrotate/group
1x  wp.compose.pure deprecated since 7.1
1x  wp.compose.withState deprecated since 5.8
1x  wp.editPost.PluginDocumentSettingPanel deprecated since 6.6
3x  ... added to the iframe incorrectly (global-styles, td-guten-blocks, td-gut)
```

E a causa conferida no registro, não inferida da ausência: dos **126 blocos registrados**, os
**únicos** com `apiVersion < 3` seguem sendo `adrotate/advert` e `adrotate/group`.

**Era o esperado, e vale dizer por quê:** o Offload Media **não registra bloco nem enfileira JS de
editor**, então um console alterado aqui seria sinal de que ele saiu do lugar dele.

## ✅ E a caixa Publicar — a questão aberta de 01/09 está respondida

O botão **Publicar** está na tela, habilitado, com olho humano e com `querySelector`. **Não havia
defeito**: o site usa o editor de blocos, onde o controle é montado por JavaScript e **nunca**
apareceria no HTML servido — o `submitdiv` que eu procurava é do editor clássico. O repórter já
tinha provado isso publicando.

> **Consequência para o lote 7:** ele perde o "principal suspeito da caixa Publicar ausente", que
> era metade da sua justificativa. Continua sozinho e por último — 29 minors em quem governa
> capacidade no admin é motivo bastante — mas o alvo mudou.

## ✅ Sobreviveu ao rollout — e a prova de que veio da IMAGEM

`git push origin develop` → **`dbb2bca0`**. O `Build e Deploy (homolog)` passou, e o *deployment*
foi de **generation 130 → 131**, com a *tag* da imagem trocando para o SHA do commit:

```
antes : bahia-wordpress:647b8790...   (lote 2)     pod wordpress-b7b875686-5tf2f
depois: bahia-wordpress:dbb2bca0...   (lote 3)     pod wordpress-79f56f5cb6-g27nl
```

**No pod novo, que nunca viu o `Plugin_Upgrader`:**

| | |
|---|---|
| WordPress · PHP | **7.1 · 8.3.33** |
| `siteurl` | `https://hml.bahia.ba` |
| **Offload Media** | **3.3.1** ativo |
| Smush · FooGallery · Site Kit | 3.22.1 · **3.2.6** · **1.186.0** — lotes 1 e 2 intactos |
| `as3cf_schema_version` | `3.3.1` |
| Guarda de remoção | `has_filter` **true** |

Revalidado depois do rollout: **site 7 de 7 em 200**, e **mais um envio de mídia pelo REST**
(201 em 6,7 s, 13 → 12 arquivos, offload verificado, `srcset` presente, CloudFront 200 nos dois
tamanhos). Anexo removido: **155.600 / 155.675**, os mesmos números de antes do lote.

> **Esta seção fica sem `push` de propósito.** Homolog roda em **um nó só**, com `maxSurge=0` — todo
> rollout tem janela de indisponibilidade. Um `push` só para publicar parágrafo derruba homolog
> com um repórter dentro. **Vai junto com o lote 4.**

## ⚠️ O resíduo no bucket compartilhado, e ele cresceu

Anexos e rascunhos de teste **removidos, com resíduo zero**: `wp_as3cf_items` e a contagem de
anexos voltaram a **155.600 / 155.675**, os mesmos números de antes do lote.

**Os arquivos no S3 permanecem**, porque a guarda `as3cf_remove_source_files_from_provider`
(conferida por `has_filter` **antes de cada envio**, e o teste **aborta** se ela faltar) impede
remoção do provedor — o bucket é o **de produção**.

```
6 prefixos datados de 02/09  ->  80 objetos, 2.169.564 bytes (~2,1 MB)
   (em 01/09 o lote anterior deixou 13, no prefixo 01053739)
```

> 🟡 **Isto acumula, e o lote 4 vai somar mais.** Cada envio de teste deixa 13–14 objetos. São
> nomes datados e prefixos exclusivos de teste, então **dá para limpar com `aws s3 rm` nos
> prefixos**, fora do WordPress e sem passar pela guarda. **Não fiz por conta própria:** apagar do
> bucket de produção é exatamente o risco que a guarda existe para evitar, e a prática fixada em
> 01/09 foi deixar. **Fica para sua decisão — junto, ao fim do lote 4.**
>
> ✅ **Autorizado e executado em 02/09** — ver a seção da limpeza do bucket, e o erro de contagem
> que o portão pegou no caminho.


---

# ✅ LOTE 4 — concluído em 02/09/2026

| Plugin | De | Para | Salto |
|---|---|---|---|
| Smush | 3.22.1 | **4.3.2** | **major 3 → 4** |

Continua **ativo**. O diretório encolheu de **822 para 569 arquivos** — o 4.0 reescreveu a
interface e removeu código.

## Rede antes de mexer

| | |
|---|---|
| **`tar` do diretório** | `plugins-pre-lote4-smush-3.22.1.tgz` — **5.073.004 bytes, 982 entradas** |
| **Dump do banco** | `dump-HOMOLOG-pre-lote4-20260902-1316.sql.gz` — **586.088.906 bytes**, `gzip -t` OK |
| Primeira linha / rodapé | `-- MySQL dump 10.13` · `Dump completed on 2026-09-02 12:20:40` |
| Estrutura | **92 `CREATE TABLE` × 92 tabelas** · 246 ocorrências do `siteurl` de homolog |
| `grep 'pod ".*" deleted'` | **0** |
| SHA-256 | `523af4c4…a723a`, gravado ao lado · arquivo em `444` |
| **Guarda de remoção** | `has_filter` **true**, conferida **antes** de mexer e antes de cada envio |

## 🟠 A atualização falhou na primeira tentativa — e a falha foi minha, não do plugin

Rodei o `Plugin_Upgrader` **como `www-data`**, aplicando a lição do lote 3. Deu erro:

```
PHP Warning: chmod(): Operation not permitted
   wp-admin/includes/class-wp-filesystem-direct.php:195
=> ERRO: The update cannot be installed because some files could not be copied.
```

**A lição do lote 3 estava certa e eu a apliquei larga demais.** Os arquivos dos plugins são
**do `root`** — vêm do `COPY` da imagem — e o `www-data` não consegue sobrescrevê-los. O diretório
`plugins/` é dele, mas os arquivos dentro não.

| Quem faz o quê | Usuário certo | Por quê |
|---|---|---|
| **Medir comportamento** (upload, remoção, permissão) | **`www-data`** | é quem o site usa; foi o achado do lote 3 |
| **Rodar o `Plugin_Upgrader`** | **`root`** | precisa sobrescrever arquivo de `root` vindo da imagem |

**O `WP_Upgrader` desfez sozinho, e conferi:** o diretório voltou com **822 arquivos, o mesmo
número do `tar`**, versão ainda 3.22.1 e plugin ativo. Restou só o pacote extraído em
`wp-content/upgrade/wp-smushit.4.3.2`, que apaguei antes de repetir.

Refeito como `root`: `process_success`, **3.22.1 → 4.3.2**, ativo. Arquivos de `root`, legíveis
pelo `www-data` — conferido.

## Migração de dados: houve, e ela DUPLICA em vez de converter

```
wp-smush-version         3.22.1 -> 4.3.2
settings (24 chaves)     identicas, uma a uma
wp-smpro-smush-data      5.844 -> 5.844 linhas em postmeta
wp-smush-lossy           5.827 -> 5.827
wp_smush_dir_images      0 -> 0
```

**O 4.x criou uma segunda cópia das listas, em JSON, e deixou a antiga:**

| Opção nova (4.x) | Opção antiga (mantida) | Tamanho |
|---|---|---|
| `wp-smush-error-items-list-json` | `wp-smush-error-items-list` | **401.661 e 401.659 bytes** |
| `wp_smush_global_stats_json` | `wp_smush_global_stats` | 100 e 126 |
| `wp-smush-optimize-list-json` | `wp-smush-optimize-list` | 22 e 20 |
| `wp-smush-reoptimize-list-json` · `wp-smush-ignored-items-list-json` · `wp-smush-animated-items-list-json` | as três antigas | ≤ 2 |

Confirmado que são novas: **o código 3.22.1 não menciona `-json` em lugar nenhum**; quem as
escreve é o `core/class-installer.php` do 4.x, e o `uninstall.php` conhece as duas famílias.

> **Custa quase nada, mas vale escrito:** são ~**800 KB** em `wp_options` onde antes havia 400 KB.
> **Todas com `autoload=off`**, então **não entram em nenhuma requisição** — é peso morto em disco,
> não em runtime. Não removi: a cópia antiga é o caminho de volta se o 4.x precisar ser revertido.

Uma única diferença de configuração, e é inócua: em `wp-smush-lazy_load`, o `spinner.selected` foi
de **1 para 2**. O `animation.selected` é `"fadein"`, então **o spinner não é usado** — mudou um
valor que não é lido.

## 🎯 O teste do lote — o envio de imagem, outra vez nos três caminhos

Mesma bateria do lote 3, agora com a variável trocada sendo o **Smush**:

| Caminho | Resultado |
|---|---|
| **A** — `media_handle_sideload` | ✅ ID 9000314 em **6,4 s** · **13 → 12 arquivos** (9 `td_*`) |
| **B** — controlador REST | ✅ **201** em 6,8 s · 13 → 12 · offload `is_verified=1` |
| **C** — 🔴 **NAVEGADOR**, sessão real por nginx + PHP-FPM | ✅ **201 em 8,0 s** · ID 9000318 |

```
caminho C  : 1600x1067 JPEG, 35.827 bytes, gerado no canvas do navegador
derivadas  : 14 entradas -> 13 arquivos distintos (9 td_* do Newspaper)
offload    : static.bahia.ba, sa-east-1, is_verified=1
s3 ls      : 14 objetos no prefixo — 1 original + 13 derivadas
srcset     : PRESENTE
local      : 0 de 14 no disco, nos TRES caminhos — remove-local-file honrado
```

**Os dois plugins de mídia agora estão nas versões novas, e o envio continua funcionando pelo
mesmo caminho que o repórter usou.** Era essa a pergunta dos lotes 3 e 4.

## 🔴 O que o Smush toca e o Offload não — e por isso foi medido à parte

O Smush **reescreve o HTML do front**: com `lazy_load` ligado, cada `<img>` sai com `data-src` em
vez de `src`. **Um major que quebrasse isso quebraria a imagem de todo o site**, e nenhum teste de
upload perceberia. Amostrado antes e depois, com *bypass* de cache:

| Página | `<img>` | `data-src` | `srcset` | `loading="lazy"` | bytes |
|---|---|---|---|---|---|
| `/` | 17 → **17** | 17 → **17** | 0 → **0** | 0 → **0** | 566.166 → 566.109 |
| `/economia/` | 18 → **18** | 17 → **17** | 0 → **0** | 0 → **0** | 307.120 → 306.307 |
| matéria | 10 → **10** | 10 → **10** | 1 → **1** | 0 → **0** | 318.792 → 318.658 |

**Idêntico em tudo que é comportamento.** O `smush-lazy-load.min.js` segue enfileirado, com as
variáveis CSS de *placeholder* (`--smush-placeholder-aspect-ratio`) montadas por imagem.

> 🔗 **E confirma de novo o que `imagens-td-sizes-regressao` já dizia:** `loading="lazy"` **nativo
> continua em zero** nas três páginas. O *lazy* do site é inteiramente do Smush — se ele sair, sai
> junto, e não há rede nativa embaixo. **Isto virou seção própria** (*Dependência não declarada*),
> porque é maior que este lote: o plugin está instalado por causa da compressão, que nem sequer
> está ligada, e hoje é indispensável por outro motivo.

## Segundo plano: o 4.0 trouxe processamento em fundo, e ele não saiu andando sozinho

O changelog do 4.0 anuncia *"Free Background Processing"*. Conferido depois da atualização:

```
eventos agendados no total : 33
eventos do Smush           : 1   ->  wp_smush_daily_cron, para 03/09 10:04
lotes em segundo plano     : nenhum criado
```

As únicas opções de lote no banco continuam sendo as **três do Offload**, que já existiam.
**Nenhuma varredura de biblioteca foi disparada** — o que importa, porque são **155.675 anexos**.

## Validação

| Camada | Resultado |
|---|---|
| Site (home, 3 archives, 2 buscas, Quem Somos, autor) | **7 de 7** em 200 |
| Busca | índice **242.865** linhas · **10 de 10** termos · 367–1.583 ms |
| **Envio de mídia** | **os três caminhos**, incluindo o do navegador |
| **Front-end com lazy load** | **idêntico**, tabela acima |
| Rascunho com ACF + coautoria | subtítulo, imagem e **2 coautores**; removido sem resíduo |
| **Editor no navegador** | 126 blocos registrados, **0 inválidos**, **Publicar** presente, 8 campos ACF, 11 metaboxes, 161 elementos tagDiv, 8 do CAP, **0 avisos do editor** |
| Logs — **260 requisições** com bypass de cache | **0 fatais · 0 depreciações · 0 notices · 0 linhas do Smush** |
| Limpeza | anexos e rascunhos removidos; `wp_as3cf_items` e anexos de volta a **155.600 / 155.675** |

Os **28 avisos** da janela são os dois de sempre: **26** do Co-Authors Plus (um por acesso a
`/colunistas/`, e a janela bateu 26 vezes lá) e **2** do PureDevs GDPR.

### 📌 Console — 8 advertências, as mesmas de novo

Zero erros. As mesmas oito do fim do lote 2 e do lote 3, item a item. Dos **126 blocos**
registrados, os únicos com `apiVersion < 3` seguem sendo `adrotate/advert` e `adrotate/group`.

> 🟡 **Um aviso a mais apareceu, e não é do lote — é de uma tela que a linha de base nunca cobriu.**
> Na **lista de posts** (`edit.php`), e **não** no editor, o OneSignal registra
> `could not load wp.data.select("core/editor")`. Faz sentido: naquela tela o `core/editor` não
> existe mesmo. **A linha de base das 8 vale para `post-new.php`**, que é onde a redação escreve.
> Fica anotado que ela **não cobre as outras telas do painel**, e que ninguém mediu essas.

---

## ✅ Sobreviveu ao rollout

`git push origin develop` → **`2adc285b`**, `Build e Deploy (homolog)` OK, deployment de
**generation 131 → 132**, pod **`wordpress-67cb496b8b-5hclj`**.

No pod novo, que nunca viu o `Plugin_Upgrader`: **WP 7.1 · PHP 8.3.33 · Smush 4.3.2 ·
Offload 3.3.1 · FooGallery 3.2.6 · Site Kit 1.186.0**, `wp-smush-version` em `4.3.2`,
`lazy_load` e `auto` ligados, guarda de remoção com `has_filter` **true**.

Revalidado depois: **site 7 de 7**, **envio pelo REST em 201** (13 → 12 arquivos, offload
verificado, `srcset` presente) e o **front-end com `data-src` idêntico** — 17/17, 17/18 e 10/10,
os mesmos números de antes da atualização.

---

# ✅ LOTE 5 — concluído em 02/09/2026

| Plugin | De | Para | Salto |
|---|---|---|---|
| Co-Authors Plus | 3.6.6 | **4.1.1** | **major 3 → 4** |

Continua **ativo**. O pacote encolheu de **219 para 86 entradas**.

## Rede antes de mexer

| | |
|---|---|
| **`tar` do diretório** | `plugins-pre-lote5-cap-3.6.6.tgz` — **424.183 bytes, 219 entradas** |
| **Dump do banco** | `dump-HOMOLOG-pre-lote5-20260902-1447.sql.gz` — **586.296.024 bytes**, `gzip -t` OK |
| Primeira linha / rodapé | `-- MySQL dump 10.13` · `Dump completed on 2026-09-02 13:49:42` |
| Estrutura | **92 `CREATE TABLE` × 92 tabelas** · 247 ocorrências do `siteurl` de homolog · ruído do `kubectl`: **0** |
| SHA-256 | `5810847c…8cbb`, gravado ao lado · arquivo em `444` |

Atualizado **como `root`** desde a primeira tentativa — a lição do lote 4 aplicada na forma certa.

## 🔴 A pergunta que este lote tinha de responder primeiro: o nosso mu-plugin ainda se aplica?

O `bahia-autor-archive.php` **desliga três filtros do CAP pelo nome do método** e injeta um UNION
indexado no lugar. **Se o 4.1.1 tivesse renomeado ou movido esses métodos, `method_exists`
devolveria `false`, os filtros não seriam removidos, e o SQL lento voltaria — com o nosso UNION
por cima.** Falharia em silêncio, e o sintoma seria a página de autor lenta de novo.

Medido, antes e depois:

| | 3.6.6 | 4.1.1 |
|---|---|---|
| `CoAuthors_Plus::posts_where_filter` | existe | **existe** |
| `CoAuthors_Plus::posts_join_filter` | existe | **existe** |
| `CoAuthors_Plus::posts_groupby_filter` | existe | **existe** |
| registrados em `posts_where` / `posts_join` / `posts_groupby` | prio 10 | **prio 10** |
| `get_coauthors()` devolve | `WP_User` com `display_name`, `ID`, `user_nicename` | **idêntico** |
| `get_coauthors`, `coauthors`, `is_coauthor_for_post` | existem | **existem** |
| termos `author` / relações | 179 / 253.757 | **179 / 253.757** |

**O mu-plugin continua se aplicando, e continua sendo necessário.**

## 🎯 O 4.1.1 NÃO consertou o desempenho — e a medição prova que nossa correção ainda é o que segura

Comparei, **já sobre o 4.1.1**, o conjunto do nosso UNION contra o conjunto que o **próprio CAP**
produz, desligando o `pre_get_posts` do mu-plugin:

| Autor | nosso UNION | CAP | bate? | nosso | **CAP** |
|---|---|---|---|---|---|
| `mateus-soares` | 1.763 | 1.763 | ✅ | 2.055 ms | **38.865 ms** |
| `breno-cunha` | 991 | 991 | ✅ | 1.457 ms | **37.788 ms** |

> **Duas conclusões, e as duas importam.** A **semântica** do nosso UNION continua idêntica à do
> plugin — contagem igual, autor a autor. E o **problema de desempenho continua lá**: o SQL do CAP
> ainda leva **38 segundos** por autor. O incidente `author-archive-cap-lento` **não foi resolvido
> a montante**; se o mu-plugin sair, a página volta a beirar o timeout.

**A página de autor, medida ponta a ponta** (segunda medida, com cache quente):

| Autor | antes | depois |
|---|---|---|
| `da-redacao` | 3,89 s | **3,05 s** |
| `mateus-soares` | 2,12 s | **1,88 s** |
| `rodrigo-daniel` | 2,15 s | **1,84 s** |
| `luis-filipe` | 2,18 s | **1,85 s** |
| `breno-cunha` | 2,10 s | **1,84 s** |

70 matérias listadas em todas, antes e depois. **Nenhuma regressão** — e uma melhora pequena e
consistente, que não vou creditar ao plugin sem mais medida.

## 🟢 O ganho mensurável: os avisos de `user_nicename` foram a ZERO

Era o "antes" que o lote já tinha na mão, medido nas janelas dos lotes 3 e 4:

```
lote 3 — 240 requisicoes : 24 avisos  Attempt to read property "user_nicename" on false
                                       co-authors-plus/php/class-coauthors-plus.php
lote 4 — 260 requisicoes : 26 avisos  (1 por acesso a /colunistas/)
lote 5 — 210 requisicoes :  0 avisos  <- ZERO
```

**Um por acesso à página de autor, e agora nenhum.** O total de avisos da janela caiu de **28 para
1** — sobrou só o `headers already sent` do PureDevs GDPR.

## 🟢 E o console caiu de 8 para 7 — a advertência que sumiu é dele

```
antes : 8 advertencias
depois: 7   —  "wp.editPost.PluginDocumentSettingPanel is deprecated since version 6.6" SUMIU
```

E a causa foi **conferida no código, não inferida do sumiço**:

```
3.6.6  build/index.js :  a.PluginDocumentSettingPanel          (pacote @wordpress/edit-post)
4.1.1  build/index.js :  wp.editor?.PluginDocumentSettingPanel
                         || wp.editPost?.PluginDocumentSettingPanel
```

O 4.1.1 **prefere o `wp.editor`** e só cai no depreciado se ele não existir. Na 7.1 existe.

> **Segunda advertência legada eliminada em três lotes** — a do FooGallery no lote 2, esta agora.
> A linha de base para o lote 6 é **7**, e as que restam são: 2 do AdRotate (`apiVersion 1`),
> `wp.compose.pure`, `wp.compose.withState`, e 3 de `added to the iframe incorrectly`.

## Validação

| Camada | Resultado |
|---|---|
| Site (home, 3 archives, 2 buscas, Quem Somos, autor) | **7 de 7** em 200 |
| Busca | índice **242.865** · **10 de 10** termos · 107–682 ms |
| **Página de autor** | **5 autores**, tabela acima — sem regressão |
| **Byline com 2 coautores** | **2 links separados**, em 3 matérias — `Por <a>A</a> e <a>B</a>`, mesmas URLs |
| **Escrita de coautoria** | rascunho com ACF + `add_coauthors()` de 2 pessoas, lido de volta, removido sem resíduo |
| Envio de mídia (REST) | **201** · 13 → 12 arquivos · offload `is_verified=1` · `srcset` presente |
| **Editor no navegador** | 126 blocos, **0 inválidos**, **Publicar** presente, 8 campos ACF, 11 metaboxes, **0 avisos do editor** |
| Logs — **210 requisições** com bypass de cache | **0 fatais · 0 depreciações · 0 notices · 1 aviso** (o do GDPR) |
| Migração de dados | **nenhuma** — sem opção `coauthor*` no banco, sem evento de cron |

### Duas observações que não são defeito

- **O painel do CAP no editor engordou.** O 4.x carrega **8 arquivos de JavaScript** próprios na
  tela de edição (7 `index.js` de dependências + `co-authors-plus.js`), onde o 3.6.6 carregava um
  pacote só. Os elementos de coautoria no DOM foram de 8 para 7. Nada quebrou, e a tela abre sem
  aviso — mas é peso novo numa tela que já carrega 161 elementos do tagDiv.
- **Os 259 `guest-author` do banco são órfãos.** Nenhum deles tem termo `cap-*` na taxonomia, e
  nenhum tem post associado. É resíduo da importação, **anterior a este lote**, e por isso o
  formato de guest author não é caminho exercitado aqui — toda a autoria do site passa por
  `WP_User`.

### 🟡 Uma divergência de número, dita como divergência

O registro anterior falava em **28.379 posts (11,7%)** sem o termo do autor primário — o caso de
borda que o ramo A do UNION cobre. **A minha medição de hoje devolveu 30.892 de 272.149 (11,4%)**,
e o valor **não mudou com a atualização** (idêntico antes e depois). A diferença é de
**instrumento**: eu contei todos os tipos publicáveis, e o número antigo tinha outro recorte. **O
que o lote precisava provar — que o 4.1.1 não mexeu nessa fatia — está provado.** O número
absoluto exato fica para quem precisar dele, com o recorte declarado.

## ✅ Sobreviveu ao rollout

`git push origin develop` → **`051cb366`**, generation **132 → 133**, pod
**`wordpress-7fbd67f758-mrnpr`**.

No pod novo: **WP 7.1 · PHP 8.3.33 · CAP 4.1.1 · Offload 3.3.1 · Smush 4.3.2 · FooGallery 3.2.6 ·
Site Kit 1.186.0**. E, o que mais importa aqui, **os três métodos do CAP continuam existindo e
registrados em prioridade 10 no pod que veio da imagem** — o `bahia-autor-archive` segue válido.

Revalidado depois: **site 7 de 7**, **página de autor em 1,49–2,68 s** nos cinco autores (70
matérias em cada), e o **byline com 2 links separados** nas três matérias de teste.

## 🟡 Resíduo deste lote no bucket, e ele NÃO foi apagado

```
2026/09/02110414   teste-rest-lote5-20260902-140412*   13 objetos, 481.100 bytes
```

**Não apaguei por conta própria.** As duas autorizações anteriores foram específicas — os
prefixos do dia ao fim do lote 4, e o `01053739` de 01/09. Remoção do bucket de **produção** não
herda autorização de uma vez para a seguinte. Fica com o portão já pronto para uma linha sua.

---

# ✅ LOTE 6 — concluído em 02/09/2026

| Plugin | De | Para | Salto |
|---|---|---|---|
| Yoast SEO | 27.7 | **28.4** | **major** |

> ⚠️ **Foi para a 28.4, não para a 28.3.** O plano dizia 28.3; entre o levantamento de 01/09 e a
> execução de hoje a Yoast lançou a **28.4**, e o *updater* do WordPress instala **a versão atual
> do canal**, não a que estava anotada. Não é desvio de procedimento — é o procedimento
> funcionando —, mas **o número muda em todo registro que dizia 28.3**.
>
> **Corrigido em todo o documento em 02/09.** Se alguém procurar por *"Yoast 28.3"* mais tarde e
> não achar, **não é que o lote não foi feito** — é que a versão instalada tem outro número. Os
> lugares que diziam 28.3 são: esta seção, a linha 6 da tabela **Os lotes**, e a tabela do
> levantamento de versões (onde `28.3 → 28.4 no dia da execução` ficou registrado com as duas).

## Rede antes de mexer

| | |
|---|---|
| **`tar` do diretório** | `plugins-pre-lote6-yoast-27.7.tgz` — **4.167.735 bytes, 2.315 entradas** |
| **Dump do banco** | `dump-HOMOLOG-pre-lote6-20260902-1520.sql.gz` — **586.305.262 bytes**, `gzip -t` OK |
| Primeira linha / rodapé | `-- MySQL dump 10.13` · `Dump completed on 2026-09-02 14:22:41` |
| Estrutura | **92 `CREATE TABLE` × 92 tabelas** · 248 ocorrências do `siteurl` · ruído do `kubectl`: **0** |
| SHA-256 | `bdb0af49…c7a8`, gravado ao lado · arquivo em `444` |

## 🎯 A migração: PRIMEIRO PLANO, e ela NÃO reconstrói os 323 mil indexáveis

Era a pergunta central do lote — *"roda em primeiro plano ou em background, e pressiona o banco
por horas?"*. **Medido, e a resposta é melhor do que a pergunta supunha.**

```
upgrader (copia de arquivo)           : 2 s
primeira carga do WP depois da troca  : ~3 s de relogio  (linha de base antes: 2.846 ms)
cargas seguintes                      : 5.787 ms e 1.128 ms — variacao normal, sem bloqueio
```

**A migração roda em primeiro plano, na primeira carga, e termina em segundos.** Nada ficou
pendente, e nenhuma requisição do site esperou por ela.

### Por que foi tão rápido: é DDL, não movimentação de dado

A migração nova é a `20260709144332`, e ela faz exatamente duas coisas:

```php
$this->add_column($table, 'seo_title_score',        'integer', ['null' => true, 'limit' => 3]);
$this->add_column($table, 'meta_description_score', 'integer', ['null' => true, 'limit' => 3]);
```

**Duas colunas anuláveis, sem `DEFAULT`, no fim da tabela** — o caso em que o MySQL 8 usa
`ALGORITHM=INSTANT` e **não reescreve uma única linha**. Conferido depois:

| | |
|---|---|
| `seo_title_score` / `meta_description_score` | `int`, `null=YES`, `default=NULL` — as duas presentes |
| colunas na tabela | **55** |
| linhas com valor nas colunas novas | **50** de 316.641 — preenchidas **sob demanda**, ao visitar a página |
| tamanho | 244,8 MB dados + 87,8 MB índice · `FILE_SIZE` **388 MB** — **igual ao de antes** |
| `wp_yoast_migrations` | 26 → **27** |

**E nenhuma reindexação foi disparada** — 🔴 **e esta frase estava errada, corrigida em 02/09**.
Ver o §16.12 do `HANDOVER.md` e a seção do incidente do lote 7: as três opções abaixo marcam a
reindexação **iniciada pela interface**, e não dizem nada sobre o **cron de fundo**, que estava
rodando o tempo todo — e continua rodando em **produção**, na 27.7, de 15 em 15 minutos. O que a
28.4 não fez foi **disparar** trabalho novo: a versão do construtor de indexável é a **mesma** nas
duas (`'post' => 2`), então nenhum indexável existente virou desatualizado.

As três opções, que eu li e que respondem outra pergunta:

```
wpseo_indexation_started            : false
wpseo_indexables_indexation_reason  : false
wpseo_unindexed_post_count          : false
```

> **A leitura de risco que o plano trazia — "o lote mais lento e o de maior escrita no banco" —
> não se confirmou.** A escrita foi de duas colunas de metadado. **Os 316 mil indexáveis não foram
> tocados**, e é por isso que o `FILE_SIZE` não mudou.

## 🟡 O que SIM continua depois do rollout: um cron novo, de hora em hora

```
cron NOVO : wpseo_cleanup_cron          (não existia na 27.7)
cadencia  : de hora em hora
lote      : limite de 1.000 linhas por consulta
            apply_filters('wpseo_cron_query_limit_size', 1000)
            src/integrations/cleanup-integration.php:241
```

E ele **já rodou uma vez**, com assinatura reconhecível:

```
wp_yoast_indexable_hierarchy : 325.725 -> 324.728    (-997, na primeira carga)
```

**997 de um teto de 1.000** — é uma passada do lote de limpeza removendo hierarquia órfã.
Acompanhado por **2,5 minutos** depois disso, a cada 25 s: as contagens ficaram **estáveis**, e o
único movimento foi **+1 indexável por página nova visitada** (criação preguiçosa, comportamento
normal).

> **É pressão limitada, não uma varredura livre.** Mil linhas por consulta, de hora em hora, numa
> tabela de 316 mil. Vale saber que existe **antes de virar produção**, e vale conferir depois do
> rollout — mas não é o cenário de "horas de pressão" que o plano temia. O `wpseo_indexable_index_batch`
> que aparece nos eventos **já existia na 27.7**; não é novidade deste lote.

## 🔴 A `wp_yoast_indexable` é de onde saem `title` e `description` — conferido, e continua saindo

É o mecanismo que já mordeu três vezes: o Yoast serve **da tabela**, não da opção. Amostrado
antes e depois, com *bypass* de cache:

| Página | `title` antes | `title` depois |
|---|---|---|
| Home | `bahia.ba - A notícia no ponto certo` | **idêntico** |
| `/economia/` | `Economia - bahia.ba` | **idêntico** |
| `/esporte/` | `Esporte - bahia.ba` | **idêntico** |
| `/colunistas/da-redacao/` | `Redação: matérias publicadas - bahia.ba` | **idêntico** |
| matéria | `Vitória vence Botafogo… - bahia.ba` | **idêntico** |

**E o subtítulo na `meta description` continua saindo** — que é o mais frágil dos dois, porque
depende de um filtro nosso com assinatura do Yoast:

```
description     : Rubro-Negro venceu o Botafogo por 1 a 0 pela 23ª rodada do Campeonato Brasileiro
og:description  : (o mesmo)
```

O `bahia-subtitulo.php` engancha em `wpseo_metadesc`, `wpseo_opengraph_desc` e
`wpseo_twitter_description` **lendo `$presentation->model->object_type` e `object_id`**. Se a 28.4
tivesse mudado a forma da apresentação, o subtítulo sumiria das metatags **sem erro nenhum** — o
Yoast voltaria a servir o que servia antes. Os quatro filtros seguem registrados
(`wpseo_metadesc`, `wpseo_opengraph_desc`, `wpseo_twitter_description`, `wpseo_title`), e a saída
está igual.

## O sitemap: igual antes e depois, e o 504 é o de sempre

```
                          ANTES                  DEPOIS
/sitemap_index.xml    504  60,45 s            504  60,46 s     <- 🟡 PRE-EXISTENTE, so em homolog
/post-sitemap.xml     200   1,62 s            200   1,61 s
/page-sitemap.xml     200   0,59 s            200   1,17 s
```

> **O 504 não é regressão e não é deste lote.** É o `sitemap-504-homolog-rds`: o RDS de homolog tem
> *buffer pool* de 256 MB contra uma `wp_posts` de 1,1 GB, e produção responde a mesma URL em ~2 s.
> **Medido antes de mexer justamente para não confundir as duas coisas.** Os sub-sitemaps, que são
> o que o Google efetivamente busca, respondem 200 nas duas pontas.

## 🟡 Os posts sem linha em `yoast_indexable`: a 28.4 NÃO os trata

```
antes  : 23.405 de 272.149  (8,6%)
depois : 23.654 de 272.149  (8,7%)
```

**Continuam fora, e a proporção não se moveu.** O crescimento de 249 é a janela de tráfego criando
indexáveis para páginas visitadas — ou seja, **o mecanismo é o mesmo de antes: preguiçoso, sob
demanda, à medida que alguém acessa.** A 28.4 não trouxe nada que os alcance em lote.

> ⚠️ **E o número que estava registrado era outro: 27.961.** A minha consulta exclui `attachment`,
> `revision` e `nav_menu_item` e olha só `post_status='publish'`; a de antes tinha outro recorte.
> **A conclusão que o lote precisava — "a 28.4 não muda essa fatia" — está medida com o MESMO
> instrumento nas duas pontas.** O absoluto fica para quem precisar dele, com o recorte declarado.

## Validação

| Camada | Resultado |
|---|---|
| Site (home, 3 archives, 2 buscas, Quem Somos, autor) | **7 de 7** em 200 |
| Busca | índice **242.865** · **10 de 10** termos · 136–824 ms |
| **`title` e `description`** | **5 telas, idênticas**, tabela acima |
| **Sitemap** | igual antes e depois; 504 do índice é pré-existente de homolog |
| Rascunho com ACF + coautoria | subtítulo, imagem e **2 coautores**; removido sem resíduo |
| **Editor no navegador** | 126 blocos, **0 inválidos**, **Publicar** presente, 8 campos ACF, 11 metaboxes, **0 avisos do editor** |
| Logs — **220 requisições** com bypass de cache | **0 fatais · 0 depreciações · 0 notices · 0 linhas do Yoast** · 3 avisos, todos o do PureDevs GDPR |
| **Console** | **7 advertências** — a linha de base do lote 5 **segurou**, item a item |

### 🟡 O editor continua engordando, e agora dá para nomear o peso

O lote 5 registrou o CAP indo de 1 para 8 arquivos de JS. Medido agora na mesma tela:

```
scripts do Yoast          : 32
scripts do Co-Authors Plus:  8
                            --
                            40 arquivos de JS, de DOIS plugins
```

Mais 161 elementos do tagDiv, 8 campos ACF e 11 metaboxes. **Nada quebrou e a tela abre sem
aviso** — mas nenhum lote mediu o **tempo de abertura do editor**, e esse é o número que a redação
sentiria primeiro. Fica anotado como lacuna de medição, não como defeito.

## ✅ Sobreviveu ao rollout

`git push origin develop` → **`3da334b7`**, generation **133 → 134**, pod
**`wordpress-7c455796f4-75ctq`**.

No pod novo: **WP 7.1 · PHP 8.3.33 · Yoast 28.4 · CAP 4.1.1 · Offload 3.3.1 · Smush 4.3.2 ·
FooGallery 3.2.6 · Site Kit 1.186.0**. `wp_yoast_migrations` em **27**, última `20260709144332`, e
**nenhuma reindexação pendente** — os três sinalizadores seguem em `false` no pod que veio da
imagem, que era o que faltava confirmar.

Revalidado depois: **site 7 de 7**, **sitemap idêntico** (504 pré-existente no índice, 200 em
0,64 s no `post-sitemap` e 0,59 s no `page-sitemap`), e as **5 telas com `title` e `description`
iguais** — inclusive o subtítulo na `meta description` da matéria.

**Nenhum resíduo no bucket:** este lote não exercitou envio de mídia, então não criou objeto de
teste. O `2026/09/02110414` do lote 5 foi removido no início desta etapa, com o portão fechado em
**13 esperados / 13 apagados / 0 restantes**, e os **99 objetos de produção intactos**.

---

# ✅ LOTE 7 — concluído em 02/09/2026

| Plugin | De | Para | Salto |
|---|---|---|---|
| PublishPress Capabilities | 2.21.0 | **2.50.1** | 29 minors |

**Rede antes:** `tar` (901.930 bytes, 292 entradas) e dump verificado —
`dump-HOMOLOG-pre-lote7-20260902-1816.sql.gz`, **586.537.347 bytes**, `gzip -t` OK, rodapé
`Dump completed on 2026-09-02 17:19:51`, **92 `CREATE TABLE` × 92 tabelas**, 249 ocorrências do
`siteurl`, ruído do `kubectl`: 0, SHA-256 `c787f6fe…8713`, arquivo em `444`.

## Qual papel a redação usa de fato — a pergunta que reordenou o teste

| papel | pessoas | posts no acervo | **últimos 120 dias** |
|---|---|---|---|
| **editor** | **17** | **194.838** | **15 pessoas · 6.478 posts** |
| author | 2 | **7** | 2 pessoas · 7 posts |
| contributor | 1 | 0 | — |
| administrator | 6 | 239 | 3 pessoas · 84 posts |

> **A redação publica como `editor`, e não há ambiguidade.** O `author` tem **7 posts em todo o
> acervo** — testá-lo era academia, como você suspeitou. Testei os três mesmo assim, mas o peso
> da conclusão está no `editor`.

Há ainda **128 usuários com papel `none`**, responsáveis por 73.694 posts históricos: são os
autores da importação, sem capacidade nenhuma. Não entram, não publicam.

## 🎯 As capacidades, antes × depois — diferidas uma a uma, não por contagem

O `md5` das definições de papel mudou (`3d265e53…` → `034252d1…`), e as contagens também
(administrator 126 → 128, editor 60 → **58**). **Contagem não diz o quê**, então extraí o "antes"
do dump e diferi capacidade por capacidade:

```
administrator  +2  GANHOU  manage_capabilities_admin_notices
                           manage_capabilities_admin_styles
editor         -2  PERDEU  manage_capabilities_frontend_features
                           manage_capabilities_redirects
author, contributor, subscriber, wpseo_manager, wpseo_editor   IDENTICOS
```

**As quatro capacidades editoriais estão intactas em todos os papéis:**

| capacidade | administrator | editor | author | contributor | subscriber |
|---|---|---|---|---|---|
| `publish_posts` | sim | **sim** | sim | – | – |
| `edit_published_posts` | sim | **sim** | sim | – | – |
| `upload_files` | sim | **sim** | sim | sim | – |
| `edit_others_posts` | sim | **sim** | – | – | – |

Idênticas antes e depois, na definição do papel e em `user_can()` sobre usuário real.

> **O que o editor perdeu são duas telas do próprio plugin de capacidades** — "frontend features"
> e "redirects". É permissão de administrar o plugin, **não de publicar**. Um editor que abrisse
> essas telas deixa de abrir; ninguém da redação abre.

## ✅ E publicou de verdade — com a matéria aparecendo no site

| papel | REST `POST /wp/v2/posts` | post | **no site** |
|---|---|---|---|
| **editor** (#137) | **201** | 9000330 `publish` | **200** · título na página · corpo presente |
| author (#170) | 201 | 9000331 `publish` | **200** · corpo presente |
| contributor (#165) | — | `publish_posts=NAO`, **igual a antes** | — |

**Não bastava o botão funcionar.** Busquei as duas URLs públicas com *bypass* de cache: as duas
respondem **200**, com o título e o corpo do teste no HTML servido. Removidos depois, sem resíduo
— `wp_as3cf_items` e anexos de volta a **155.600 / 155.675**.

## 📏 A lacuna de medição virou linha de base

Nenhum lote tinha medido o tempo de abertura do editor. Agora existe, medida por `iframe` com
`performance.getEntriesByType('navigation')`, quatro amostras antes e dez depois:

| | antes do lote 7 | depois |
|---|---|---|
| **recursos carregados** | **250** | **250** |
| **arquivos de JS** | **178** | **178** |
| editor pronto (melhor amostra) | 3.988 ms | 3.994 ms |

**As duas medidas estruturais são idênticas — o lote 7 não acrescentou peso.** O tempo, nas
amostras posteriores, oscilou de 4,0 a 32,9 s: a medição pegou o ambiente sob a contenção descrita
abaixo. **Recurso e JS não dependem de carga; tempo depende.** Por isso a conclusão se apoia nos
dois primeiros e não no terceiro.

> ⚠️ **Esta linha de base é depois dos lotes 1 a 6.** Não existe o "antes do lote 1", e não dá para
> reconstruir. O que fica estabelecido é o ponto de partida daqui para a frente.

## Validação

| Camada | Resultado |
|---|---|
| Site | **7 de 7** em 200 (depois da recuperação, abaixo) |
| Busca | **10 de 10** termos, contagens idênticas (501, 483) |
| **Capacidades** | tabela acima, diferidas uma a uma |
| **Publicação real** | editor e author publicaram, e a matéria apareceu |
| **Editor no navegador** | 126 blocos, 0 inválidos, **Publicar** presente, 8 campos ACF, 11 metaboxes, 0 avisos |
| **Console** | **7 advertências** — a linha de base do lote 5 segurou pelo terceiro lote seguido |

---

# 🔴 INCIDENTE: homolog fora do ar durante o lote 7 — e não foi o lote 7

**02/09/2026.** Durante a validação do lote 7 homolog parou de responder: 504 na home e na busca,
10 s num archive. **A causa não era o plugin que estava sendo atualizado.**

## O sintoma que separou as hipóteses

A busca **devolvia os resultados certos** — 10 de 10 termos, contagens idênticas às de sempre
(501, 483) — e levava **3 a 163 segundos** onde levava 107 a 824 ms.

> **Resultado certo com tempo absurdo é contenção, não defeito de consulta.** Foi o que impediu de
> procurar bug no lote 7.

## A causa, no `PROCESSLIST`

```sql
SELECT P.ID FROM wp_posts AS P
WHERE P.post_type IN (30 tipos) AND P.post_status NOT IN ('auto-draft')
  AND NOT EXISTS (SELECT 1 FROM wp_yoast_indexable I
                  WHERE I.object_id = P.ID AND I.object_type = 'post' AND I.version = 2)
LIMIT 15
```

**Cinco cópias empilhadas, de 13 a 28 minutos cada**, disparadas pelo `wpseo_indexable_index_batch`
— o cron de indexação do Yoast, que roda **de 15 em 15 minutos**. Cada disparo encavalava no
anterior, que ainda não tinha terminado.

**Homolog tem ~138 mil candidatos** (23.654 posts sem indexável + 114.873 indexáveis em
`version='0'`) e um `innodb_buffer_pool` de **128 MB** contra uma `wp_posts` de **1,1 GB**. O
`LIMIT 15` não ajuda: para achar 15, varre a tabela.

### E eu contribuí, de duas formas

1. **Duas varreduras minhas** (`post_title LIKE 'Teste%'` sobre 435 mil linhas) que eu interrompi
   no terminal — **e que continuaram rodando no servidor**, 20 e 23 minutos. Ver §16.13.
2. **O ClaudeBot crawlando homolog** em rajada de ~46 req/min, com `?p=NNNN` que **fura o cache**
   — cada uma renderizando uma página de ~320 KB. Homolog tem `blog_public=1`, serve
   `<meta name="robots" content="index, follow">` e **não tem `robots.txt`** (404 no nginx).

## A correção aplicada: `mu-plugins/bahia-yoast-indexacao-fundo.php`

**Ataca a causa, não o efeito.** Matar consulta presa trata o sintoma e o cron reagenda em 15
minutos.

| | |
|---|---|
| **O que faz** | `wp_clear_scheduled_hook('wpseo_indexable_index_batch')` no `init` e no `admin_init`, mais `remove_all_actions()` no mesmo gancho |
| **Por que os dois** | o Yoast **reagenda sozinho** em `admin_init` prioridade 11 (`background-indexing-integration.php:211`); tirar da fila uma vez não basta |
| **Onde age** | **só em homolog** — guarda `bahia_ambiente()` na primeira linha do corpo |
| **O que custa** | nada de funcionalidade: o indexável é construído **sob demanda**. Troca-se trabalho de fundo caro por trabalho sob demanda barato |
| **🔴 Como reverter** | **apagar o arquivo.** Sem estado guardado, sem opção escrita, sem migração. No carregamento seguinte o Yoast reagenda sozinho |
| **Como conferir que voltou** | `wp_next_scheduled('wpseo_indexable_index_batch')` deixa de ser `false` |
| **Condição de saída** | escrita no cabeçalho do arquivo: ou os ~138 mil pendentes são preenchidos em janela controlada, ou fica decidido **por escrito** que a indexação antecipada não interessa |

Conferido depois de aplicar:

```
wp_next_scheduled('wpseo_indexable_index_batch') : false
has_action('wpseo_indexable_index_batch')        : false
```

## A recuperação, medida

```
                        durante          depois
site                    4 de 7 em 200    7 de 7 em 200
busca                   504 / 3-163 s    10 de 10, 310 ms a 7,8 s
Threads_running         16               2
consultas ativas > 60s  8                0
```

**A busca voltou funcionalmente inteira.** Os tempos seguem acima da linha de base (107–824 ms)
porque o `buffer_pool` de 128 MB ficou frio depois das varreduras — reaquece com uso.

## 🟡 O que fica como tarefa, e não é do lote 7

**Homolog é rastreável publicamente.** `blog_public=1`, `robots: index, follow`, sem
`robots.txt`. Foram vistos **ClaudeBot e Googlebot**. Além da carga, é conteúdo duplicado do site
de produção num domínio diferente. **Um `robots.txt` com `Disallow: /` em homolog resolve os
dois**, e é uma linha no nginx ou um filtro — mas é decisão sua, porque muda o que os buscadores
enxergam.

---

# 🔗 DEPENDÊNCIA NÃO DECLARADA: a página de autor é do `bahia-autor-archive`, não do plugin

**Registrado em 02/09/2026, a partir do lote 5.** Irmão direto da dependência do Smush, e com o
mesmo formato: **algo que parece otimização é, na verdade, o que segura o funcionamento.**

## O 4.1.1 NÃO consertou o desempenho — medido, já sobre ele

Com o `pre_get_posts` do mu-plugin desligado, comparando o **mesmo conjunto** pelos dois caminhos:

| Autor | nosso UNION | SQL do CAP | contagem bate? | nosso | **CAP** |
|---|---|---|---|---|---|
| `mateus-soares` | 1.763 | 1.763 | ✅ | 2.055 ms | **38.865 ms** |
| `breno-cunha` | 991 | 991 | ✅ | 1.457 ms | **37.788 ms** |

**Semântica idêntica. Desempenho 19 a 26 vezes pior.** O salto de major não tocou nisso.

> ### O `bahia-autor-archive.php` não é otimização. É dependência.
>
> **Se alguém removê-lo achando que "o plugin novo já resolve", o incidente
> `author-archive-cap-lento` volta no mesmo instante** — a página de autor sai de ~1,8 s para ~38 s
> de consulta, à beira do timeout. Não há aviso, não há erro: **só a página lenta de novo.**

## ⚠️ E a dependência tem um ponto frágil que precisa ser conferido a cada atualização

O mu-plugin desliga os filtros do CAP **pelo nome do método**:

```php
'posts_where'   => 'posts_where_filter',
'posts_join'    => 'posts_join_filter',
'posts_groupby' => 'posts_groupby_filter',
```

`method_exists()` devolvendo `false` **não é erro** — é `continue`. O filtro não é removido, o SQL
lento volta, **e o nosso UNION entra por cima dele**. O resultado continua correto e a página fica
lenta, que é o pior modo de falhar: **sem sintoma que aponte a causa.**

> **Primeira coisa a conferir em toda atualização do Co-Authors Plus:** os três nomes ainda
> existem e ainda estão registrados. No 4.1.1 estão — em prioridade 10, conferido também no pod
> pós-rollout.

## 🟡 Nota de peso: o painel do CAP no editor foi de 1 para 8 arquivos de JavaScript

O 3.6.6 carregava um pacote na tela de edição. O 4.1.1 carrega **8** — 7 `index.js` de
dependências mais o `co-authors-plus.js`. Nada quebrou e a tela abre sem aviso.

**Mas é peso novo numa tela que já carrega 161 elementos do tagDiv**, 8 campos ACF, 11 metaboxes e
os pacotes do Yoast. Nenhum lote mediu o tempo de abertura do editor até aqui — **e é exatamente
esse tipo de custo que se acumula sem ninguém medir**, atualização após atualização, até alguém da
redação dizer que "o editor está lento" sem ninguém saber desde quando.

---

# 🔗 DEPENDÊNCIA NÃO DECLARADA: o *lazy loading* do site inteiro é do Smush

**Registrado em 02/09/2026**, saiu da validação do lote 4 e **não era o que o lote procurava**.

Para provar que o major 3 → 4 do Smush não quebrava a renderização, amostrei o HTML servido antes
e depois. O que a amostra mostrou, além do que eu queria:

| Página | `<img>` | com `data-src` (Smush) | com `loading="lazy"` (nativo) |
|---|---|---|---|
| Home | 17 | **17** | **0** |
| `/economia/` | 18 | **17** | **0** |
| Matéria | 10 | **10** | **0** |

**Não há uma única imagem com `loading="lazy"` nativo do WordPress.** Todo o carregamento
preguiçoso do bahia.ba passa pelo `smush-lazy-load.min.js`, que troca o `src` por `data-src` e
monta as variáveis CSS de *placeholder* (`--smush-placeholder-aspect-ratio`) imagem por imagem.

## Por que isso é dependência e não configuração

O WordPress adiciona `loading="lazy"` sozinho desde a 5.5. Aqui ele **não aparece** — porque o
Smush, ao assumir o *lazy*, desliga o nativo para não haver dois mecanismos concorrendo. É o
comportamento correto do plugin. **O efeito colateral é que não existe rede embaixo:**

> **Se o Smush for removido ou trocado, o site perde o *lazy loading* no mesmo gesto** — todas as
> imagens de todas as páginas passam a baixar de uma vez. Não é degradação sutil: é a home
> carregando 17 imagens de largura cheia antes da primeira rolagem, num site cuja maior fatia de
> acesso é celular.

**Ninguém escolheu isso, e ninguém sabia.** O Smush está instalado por causa da compressão — que,
aliás, **não está ligada** (`lossy = '0'`, `webp_mod = false`, e só **5.844 dos 155.675** anexos
têm dado de otimização). Ou seja: **o plugin foi mantido por um motivo e hoje é indispensável por
outro.**

## O que fazer com isso — hoje, nada; na hora certa, uma linha

Não há ação agora, e o lote 4 provou que o *lazy* sobreviveu ao major. O que fica é um **aviso
amarrado ao plugin**:

- **Antes de remover ou substituir o Smush**, ligar o *lazy* nativo (ou o do substituto) **no
  mesmo deploy** — não depois
- Ao avaliar troca por outro plugin de imagem, *lazy loading* **entra na lista de requisitos**, e
  não como item opcional
- 🔗 Relacionado a `imagens-td-sizes-regressao`: as `td_*` do Newspaper não existem no acervo e o
  *fallback* devolve o original cheio. **O Smush é o que segura o custo dessas imagens grandes na
  primeira dobra.** As duas coisas juntas explicam por que a página aguenta hoje

---

# 🧹 LIMPEZA DO BUCKET COMPARTILHADO — 02/09/2026

**A única operação desta sessão que apaga do bucket de PRODUÇÃO.** Autorizada com portão de
contagem: dizer antes o que se espera apagar, e conferir depois o que se apagou.

## 🔴 O portão pegou o que precisava pegar

A regra pedida era *"confirme que TODOS são de hoje e de homolog"*. **"De hoje" sozinho teria
destruído mídia de produção.** O bucket é compartilhado, e a redação subiu imagem o dia inteiro:

```
prefixos sob 2026/09/ com data de HOJE : 19
   MEU-TESTE (100% dos objetos com nome teste-*)  : 11   <- apagados
   NAO-TOCAR (upload editorial real)              :  8   <- preservados
```

Os 8 preservados não são teóricos — são matéria publicada de hoje:

```
02065409  WhatsApp-Image-2026-09-02-at-06.23.51...    13 objetos
02074413  desembargador-2...                            8
02083542  desembargador-3...                            8
02084645  WhatsApp-Image-2026-09-02-at-08.18.23...     14
02085441  MERETRIZES-morgananarjara@colibri...         17
02091309  image-1068x1335.png                          14
02091426  desembargador-4...                            8
02093034  Maglore_2_Foto_Elisa-Imperial...             17
```

> **O critério que separou não foi a data — foi o conteúdo.** Um prefixo só entrou na lista de
> remoção quando **100% dos objetos dentro dele** começavam com `teste-`. Data serviu para
> limitar o escopo; **nome de arquivo foi o que decidiu**.

## Verificações antes de apagar

| Verificação | Resultado |
|---|---|
| Referências em `wp_as3cf_items` de homolog, prefixo a prefixo | **0** nos 11 |
| Posts citando os arquivos no corpo | **0** |
| Anexos correspondentes | já removidos — `155.600 / 155.675`, os números de antes dos lotes |
| Objetos soltos na raiz de `2026/09/` | **nenhum** |

## O portão, fechado

```
esperado apagar : 146 objetos, 11 prefixos, 4.208.529 bytes (~4,1 MB)
apagado         : 146 objetos
bate            : SIM
restante nos 11 : 0
producao (8 prefixos): 99 -> 99 objetos, INTACTOS
```

Depois da remoção: **site 7 de 7 em 200**, e as três imagens de produção de hoje conferidas uma a
uma no CloudFront — **200 nas três**.

## 🟡 Sobrou um, e é de ontem — fora do escopo aprovado

A varredura recursiva por `teste-` em todo o `2026/` agora devolve **13 objetos, num prefixo só**:

```
2026/09/01053739   teste-midia-71-20260901-083736*.jpg   13 objetos, ~95 KB
```

É o resíduo do teste de mídia de **01/09**, o que validou a 7.1. **Não apaguei**: a autorização
foi para os prefixos **de hoje**, e ele não é de hoje. Fica para uma linha sua — o gesto é o
mesmo, e o portão também.

> ⚠️ **E fica registrado um erro meu que o portão corrigiu.** No fechamento do lote 3 eu contei
> `01213929` como "o resíduo de 01/09, 13 objetos". **Não era.** É upload editorial real
> (`Congresso-Nacional-MP-das-blusinhas.png`), e eu tinha contado objetos sem olhar os nomes. O
> resíduo verdadeiro de ontem é o `01053739`. **Contar não é conferir** — e se a limpeza tivesse
> saído por aquela contagem, teria apagado matéria publicada.

---

# 🧪 O `uid` do teste — `kubectl exec` é root, o site é `www-data`

**Registrado em 02/09/2026, a partir do lote 3. Vale para todos os lotes e para além deles.**

`kubectl exec` no pod do WordPress entra como **root**. O **PHP-FPM roda como `www-data`**. Para a
maior parte do que se mede isso não importa — mas para **qualquer coisa que dependa de permissão
de arquivo**, os dois usuários dão respostas **opostas**, e o teste passa a medir o instrumento.

## O caso que revelou

O WP Offload Media 3.3.1 trocou `@unlink()` por `WP_Filesystem` na remoção do arquivo local.
Medido lado a lado, no mesmo pod, no mesmo segundo:

| Sob qual usuário | `get_filesystem_method()` | `WP_Filesystem()` | remoção local | tempo do upload |
|---|---|---|---|---|
| **root** — o meu `kubectl exec` | cai para **FTP** | **`false`** | ❌ 13 de 13 ficam no disco | **40,2 s** |
| **www-data** — o do PHP-FPM | **`direct`** | **`true`** (`WP_Filesystem_Direct`) | ✅ 0 de 13 | **7,3 s** |

Como root, o log enchia de `AS3CF: Could not initialize WP_Filesystem` e
`Undefined array key "remove_result"`. **Eu estava a um passo de reportar uma regressão do
plugin que não existe** — e os 33 segundos a mais eram o *fallback* de FTP tentando e falhando.

## O gesto

```bash
kubectl -n bahia-wordpress exec $POD -c wordpress -- \
  su -s /bin/sh www-data -c 'php /tmp/teste.php'
```

## 🔴 E o inverso engana igual

O erro simétrico é mais perigoso, porque **não deixa rastro no log**: como root, `@unlink()`,
`chmod`, `mkdir` e escrita em qualquer diretório **sempre funcionam**. Um teste de permissão feito
por root **passa quando deveria falhar** — e o defeito só aparece em produção, servido a leitor.

## 🔴 A outra metade, descoberta no lote 4: "sempre `www-data`" está errado também

Apliquei a regra acima larga demais e rodei o **`Plugin_Upgrader`** como `www-data`. Falhou:

```
PHP Warning: chmod(): Operation not permitted
=> ERRO: The update cannot be installed because some files could not be copied.
```

**Os arquivos dos plugins são do `root`** — vêm do `COPY` da imagem. O diretório `plugins/` é do
`www-data`, mas os arquivos dentro dele não são, e ele não consegue sobrescrevê-los.

| Tarefa | Usuário certo | Por quê |
|---|---|---|
| **Medir comportamento** — upload, remoção, permissão, `WP_Filesystem` | **`www-data`** | é quem o site usa |
| **Escrever arquivo de plugin** — `Plugin_Upgrader`, `Core_Upgrader` | **`root`** | precisa sobrescrever arquivo de `root` vindo da imagem |

> **A regra, corrigida:** **medir** como `www-data`; **instalar** como `root`. Não é "um dos dois
> sempre" — é qual dos dois o gesto imita. E vale saber que o `WP_Upgrader` **desfaz sozinho**
> quando a cópia falha: conferi que o diretório voltou com o mesmo número de arquivos do `tar`.

**Onde isso já valia sem eu saber:** os lotes 1 e 2 foram validados por `exec` como root. Nenhum
dos dois dependia de `WP_Filesystem`, então a conclusão deles continua de pé — mas a validação
**não teria detectado** um defeito de permissão se houvesse um.

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

---

# 🛡️ DESENHO DA DEFESA CONTRA A RASPAGEM DE BUSCA — proposta, NÃO aplicada

## As quatro perguntas do Albert, respondidas com medição

### 1. Quem chega legitimamente de fora?

```
1.158 buscas em 3 h
   10 com Referer   (6 de https://bahia.ba, 4 de paginas de materia)
1.148 SEM Referer nenhum
```

**Busca legítima na caixa do site manda Referer** — é o que os 10 mostram. Link compartilhado,
favorito e URL digitada **não mandam**, e cairiam na regra.

**Quantos são?** Não dá para separá-los dos ~1.000 do pool no dado atual. **Mas dá para limitar o
dano:** se a resposta ao caso suspeito for a **página de busca vazia**, e não um bloqueio, o
usuário legítimo vê "nenhum resultado" em vez de um erro — degradação, não porta fechada.

**E a ordem de grandeza da busca legítima é baixa:** 10 buscas com Referer em 3 h = **3,3 por
hora**. Mesmo que as legítimas sem Referer sejam dez vezes isso, são **0,55 por minuto** — contra
os **106 por minuto** do pico da rajada.

### 2. E os buscadores?

**A página de busca já é `noindex, follow`** — verificado na resposta:

```html
<meta name='robots' content='noindex, follow' />
```

**Bloquear a busca para rastreador não custa nada em SEO: o Google e o Bing não indexam essas
páginas de qualquer forma.** O Bingbot fez 61 buscas em 3 h para nada.

> 🔴 **E há um achado no caminho: `https://bahia.ba/robots.txt` responde 404. O site não tem
> robots.txt.** Sem ele, nenhum rastreador recebe orientação — nem sobre a busca, nem sobre
> qualquer outra coisa. **Um `Disallow: /?s=` num robots.txt resolveria os 61 do Bingbot sem uma
> linha de nginx**, e é o controle mais padrão que existe.

### 3. Qual resposta dar?

| Resposta | O que diz a quem raspa | Custo de servir |
|---|---|---|
| `403` | "fui detectado" — convida a adaptar | baixo |
| `429` | "há um limite" — convida a esperar e voltar | baixo |
| **página de busca vazia, `200`** | **nada** | **~0 se vier do cache** |

**A mais silenciosa é a melhor**, e neste caso também é a mais barata: uma página de "nenhum
resultado" cacheada é um `HIT`, custa microssegundos, e não dá nenhum sinal de que houve detecção.

### 4. 🔴 E quando perceberem e mandarem Referer?

**`Referer` é cabeçalho do cliente. É forjável numa linha.** Quem já gira IP e user-agent vai
girar o Referer assim que o volume cair. **A regra vale enquanto não repararem — e é preciso
desenhar sabendo disso.**

> **Por isso o Referer não pode ser a defesa principal. Ele é ganho de tempo.**

**A defesa que NÃO se contorna é outra: limitar a taxa GLOBAL do endereço caro.**

```nginx
limit_req_zone $server_name zone=busca:10m rate=60r/m;   # global, nao por IP
location / {
    if ($arg_s != "") { limit_req zone=busca burst=10 nodelay; }
}
```

**Por que é à prova de evasão:** ela **não tenta identificar o cliente**. Girar IP não ajuda,
forjar user-agent não ajuda, forjar Referer não ajuda — o teto é do **recurso**, não do
solicitante. Com 60/min o legítimo (0,55/min medido) tem **100× de folga**, e a rajada de 106/min
bate no teto.

**O preço, dito com todas as letras:** durante uma rajada, uma busca legítima pode ser recusada.
**É a troca explícita — a busca degrada para que o site não caia.** E busca é recurso secundário
num portal de notícias.

## A proposta, em camadas

| # | Controle | Custo | Contornável? |
|---|---|---|---|
| **1** | **`robots.txt` com `Disallow: /?s=`** | zero, e resolve o Bingbot | sim, por quem ignora robots |
| **2** | **`limit_req` GLOBAL na busca, 60/min** | uma linha de nginx | **NÃO — não depende de identificar** |
| 3 | Referer ausente → página vazia cacheada | uma linha | **sim**, e é por isso que é a camada 3 |
| **4** | **Ligar a compressão** | item 9, já documentado | — reduz o custo do que passar |

**A ordem importa:** 1 e 2 primeiro, porque 2 é a única que não se contorna. A 3 é bônus enquanto
durar.

---

# 💸 O CUSTO EM DÓLAR — e ele não está onde eu esperava

**Correção de premissa: o HTML NÃO passa pelo CloudFront.** `bahia.ba` resolve direto para os IPs
do ALB (`54.147.10.11`, `52.204.147.62`); o CloudFront serve **só a mídia**
(`d1x4bjge7r9nas.cloudfront.net`). O custo é transferência de saída do ALB/EC2.

```
ProcessedBytes do ALB, media de 7 dias : 92,34 GB/dia  ->  2.770 GB/mes
saida estimada (assumindo 85% do processado): 2.355 GB/mes
custo a 0,09 USD/GB (primeiros 10 TB)  : ~USD 212/mes
```

### A raspagem de busca

```
386 buscas/hora x 156 KB = 58 MB/h  ->  41 GB/mes
custo: USD 3,65/mes   =   1,7% da banda de saida
```

**A raspagem custa quase nada em dólar.** O dano dela é worker, não fatura.

### 🔴 O que custa de verdade: a compressão desligada

Medido nas páginas reais:

| Página | Hoje | Com gzip | Economia |
|---|---|---|---|
| Home | **575.135 B** | 93.622 B | **84%** |
| Busca | 310.510 B | 61.645 B | **80%** |
| Archive | 312.601 B | 59.051 B | **81%** |

```
custo hoje       : ~USD 212/mes
custo com gzip   : ~USD  42/mes
ECONOMIA         : ~USD 170/mes  =  USD 2.034/ano
```

**É o item 9 do `PENDENCIAS-gestores.md`, documentado em 18/08 e ainda aberto — agora com o número
em dólar.** E o ganho não é só de fatura: quem paga em segundos de espera é o leitor no celular.

> **Fui procurar o custo da raspagem e encontrei um custo 46× maior parado ao lado.**

---

# Os parâmetros que realmente exigem bypass de cache

Em vez de `if ($query_string != "") { set $skip_cache 1; }`, que nega tudo:

```nginx
# so estes precisam de bypass — o resto pode cachear
if ($arg_s != "")              { set $skip_cache 1; }   # busca
if ($arg_doing_wp_cron != "")  { set $skip_cache 1; }   # cron
if ($arg_redirect_to != "")    { set $skip_cache 1; }   # login
if ($arg_preview != "")        { set $skip_cache 1; }   # previa de rascunho
if ($arg_p != "")              { set $skip_cache 1; }   # permalink cru
if ($arg_page_id != "")        { set $skip_cache 1; }
if ($arg_replytocom != "")     { set $skip_cache 1; }   # resposta a comentario
```

**Recupera 1.817 requisições em 3 h — 8,0% das dinâmicas**, quase todas `oembed` com `url=` e
`format=`. Os parâmetros de rastreio (`fbclid`, `gclid`) passariam a cachear também, mas são
**18 ocorrências em 3 h**: o ganho ali é estrutural, não de volume.

---

# 🔍 O RASTRO DE SPAM — o que foi procurado, o que não foi achado, e o que resta saber

**Investigado em 01/09/2026**, depois que o log instrumentado mostrou o Googlebot como o maior
gerador de 404 do site: **540 de 563 requisições em `/listing-sell/…`**, com `/craigslist/` e
`/near-me/` junto, e caminhos em base64 referenciando o IMDb.

## O que foi procurado — e não achado

| Onde | Resultado |
|---|---|
| `wp_posts` — slug, título e conteúdo, 5 padrões | **0** de 435 mil |
| `wp_options` — nome e valor, incl. `eval(` e `base64_deco` | **0** |
| Regras de reescrita | **0** de 737 |
| Arquivos em `wp-content` com o padrão | **0** |
| Assinaturas clássicas de webshell (8) | **0** em todas |
| Administradores | **6**, todos `@bahia.ba` + o do Albert |

E o base64 das URLs **não decodifica** — nem direto nem invertido. Não é base64 padrão.

## O argumento que pesa mais que a varredura

**O site roda de imagem imutável.** O `wp-content` vem do git pela imagem, o core vem da imagem
oficial do WordPress, e `/var/www/html` é `emptyDir`. **Arquivo injetado morre no próximo
rollout — e os pods reiniciam o tempo todo.** Persistência em arquivo é **arquiteturalmente
impossível** nesta infraestrutura. E o banco está limpo.

**Os dois juntos fecham o que dá para fechar daqui.**

## 🟡 A incerteza que resta, e ela é real

**Não sei como aquelas URLs chegaram ao índice do Google.** Duas explicações, e não consigo
separá-las com o que tenho:

| | Hipótese | O que a confirmaria |
|---|---|---|
| **a** | Houve injeção **na era da VPS** (OpenLiteSpeed, desligada), o conteúdo morreu na migração, e o Google ficou com o índice | alguma dessas URLs ter retornado **200** algum dia |
| **b** | As URLs **nunca estiveram aqui** — sites de spam criam links para URLs fabricadas num domínio real | nenhuma jamais ter retornado 200; só link externo |

> **O Search Console decide, e é do Albert.** Ver quantas URLs desse padrão o Google conhece e se
> alguma já retornou 200. Sem isso, *"provavelmente resíduo de índice"* é o máximo que a
> evidência sustenta — e **"provavelmente" não basta para comprometimento**.

## ✅ Por que o item B (410) é a resposta certa **nos dois cenários**

**Independe de como aquilo chegou lá.** O `410 Gone` diz ao Google *"isto não existe mais"*, e ele
**remove do índice** — enquanto o `404` diz *"não achei agora"* e ele volta a tentar. Se houve
injeção, o 410 encerra o rastro; se nunca houve, o 410 encerra igual. **Uma linha resolve o custo
de hoje e a permanência no índice, sem depender da resposta.**

## 🔴 E um achado no meio: URL de spam **resolve para matéria real**

```
/listing-sell/qualquer/coisa -> 301 -> /entretenimento/coisa-atipica-diz-equipe-de-roberto-carlos.../
/craigslist/x                -> 301 -> /economia/x-afirma-que-pagou-todas-as-multas.../
/near-me/y                   -> 301 -> /entretenimento/yacoce-simoes-celebra-35-anos.../
```

É o **`redirect_guess_404_permalink()`** do núcleo: quando uma URL não existe, o WordPress
**adivinha** a matéria de slug mais parecido e redireciona. **É o mesmo mecanismo do `/acesso/`**
já documentado nos gestores.

**Isto é pior que o 404**, e provavelmente é parte de por que essas URLs sobrevivem: elas
*funcionam*. O Google segue um 301 para conteúdo real e mantém a URL de spam viva no índice, com
autoridade emprestada do domínio.

**O item B fecha isso para os três caminhos.** Mas a função continua ligada para todo o resto do
site — **desligá-la é um `remove_filter` de uma linha num mu-plugin**, e fica como tarefa própria,
porque exige deploy de imagem.

---

# Lotes 8, 9 e 10 — os plugins e temas que faltavam (03/09/2026)

Levantamento em homolog (WP 7.1, PHP 8.3.33, tema Newspaper 12.7.6). Fora do escopo por decisão
do Albert: **AdRotate Professional 5.13.1** (licença em verificação com os responsáveis).

## 🔴 O ACF PRO não é escolha — é impossibilidade

O painel oferece **6.2.1.1 → 6.8.9**. O canal responde, mas:

```
[package]           =>            <- VAZIO
acf_pro_license     => presente (176 chars)
acf_pro_get_license_key() => vazia
```

**Sem licença o WordPress não tem de onde baixar.** O botão no painel é decorativo: o canal
anuncia a versão para induzir a compra, e omite a URL. Nenhuma quantidade de tentativa resolve
isto por código. É o **item 15 de PENDENCIAS-gestores.md** se manifestando na prática.

## ✅ Lote 8 — os 7 inativos, num rollout só

| Item | De | Para |
|---|---|---|
| Akismet | 5.3.1 | 5.7.2 |
| All-in-One WP Migration | 6.77 | 7.110 |
| NextScripts SNAP | 4.4.6 | 4.4.8 |
| WPS Hide Login | 1.9.17.2 | 1.9.19 |
| Twenty Twenty-Two | 1.6 | 2.2 |
| Twenty Twenty-Three | 1.3 | 1.7 |
| Twenty Twenty-Four | 1.0 | 1.6 |

**O que junta o lote é que os sete estão INATIVOS.** Nenhum executa: não há como mudarem
comportamento, enfileirarem JS ou escreverem no banco. O console **não** foi medido, e a razão
está escrita para que ninguém leia como economia: *não há caminho físico* para o console mudar.

Conferido: `active_plugins` idêntico (24), `td_011` idêntico (500 chaves), sem fatal, site em 200,
e **839/839 checksums** batendo entre o pod e a árvore local. Commit `cdd34063`.

## ✅ Lote 9 — os 2 ativos

Disable Comments 2.8.0 → 2.9.0, OneSignal 3.9.2 → 3.9.3. Separados do lote 8 de propósito: se
algo quebrasse, dois suspeitos e não nove.

A verificação que importava aqui **não era a versão** — era a contagem. O Disable Comments tem
ferramenta de apagar comentário em massa:

```
comentários no banco        : 318     -> 318      OK
posts com comment_status=open: 92.755 -> 92.755   OK
```

Console medido **antes e depois**: idêntico, uma única exceção nas duas leituras — e ela **não é
do lote**:

> `OneSignalSDK: The "My site is not fully HTTPS" option is no longer supported starting with
> version 16 (User Model) of the OneSignal SDK.`

O OneSignal está configurado como *site não-HTTPS* num site inteiramente HTTPS, e o SDK v16 recusa
inicializar por isso. **É configuração no painel do OneSignal, não versão de plugin** — a 3.9.3
não muda nada. Medir antes E depois foi o que impediu de creditar isto à atualização.
Commit `cd53c252`.

## 🔴 Lote 10 — o incidente: o tema do tagDiv APAGA os plugins do tagDiv

**Newspaper 12.7.6 → 12.7.7 foi aplicado, quebrou homolog, e foi revertido.**

### O que se sabia antes de começar

- `clear_destination => true` no pacote: o atualizador **apaga o diretório do tema** antes de
  extrair. O único delta local era o commit `213dd7a7` — 7 linhas em `archive.php` que fazem o
  título de archive de CPT sair como "Política"/"Municípios" em vez do genérico "Arquivos".
- **Esse arquivo é código vivo.** Confirmado servindo desktop *e* celular; o
  `td-composer/mobile/archive.php` não tem o ramo de CPT, então não é ele que responde.
- Salvaguarda dirigida: 11 opções (incluindo `td_011` com 24 KB e `td_011_settings` com 121 KB),
  13 `tdb_templates` com conteúdo e 98 `postmeta`. Mais tar do tema. Mais PITR do RDS confirmado
  ativo (retenção 7 dias, último ponto restaurável de 1 minuto antes).

### O desvio do "dump antes de cada lote", declarado

Não foi feito dump completo (525 MiB, ~4min35). **Para um salto de patch de tema o raio de dano
real são 24 KB de opções**, e a salvaguarda dirigida restaura em segundos, enquanto o dump
completo reverteria tudo o mais junto. O PITR cobre o caso catastrófico. **Foi a decisão certa:
o estrago real foi exatamente onde se previu, e a restauração levou segundos.**

### O que aconteceu

O upgrader terminou com fatal — mas **depois** de instalar os arquivos, no gancho
`upgrader_process_complete`:

```
PHP Fatal error: Uncaught Error: Class "tagdiv_theme_plugins_setup" not found
  in themes/Newspaper/functions.php:484
```

Artefato de CLI: a classe existe no disco, mas só é carregada em contexto de admin. **Não é tema
quebrado.** O dano estava noutro lugar:

| | Antes | Depois |
|---|---|---|
| `td_011` | 24.228 bytes, 500 chaves | **282 bytes, 8 chaves** |
| `td_011_settings` | 121.088 bytes | 97.253 bytes |
| `active_plugins` | 1.181 bytes | **1.039 bytes** |
| `tdb_templates` | 13 | 13 — **intactos, byte a byte** |

E o essencial, que a listagem de opções não mostrava:

```
plugins/td-composer/        APAGADO DO DISCO
plugins/td-cloud-library/   APAGADO DO DISCO
plugins/td-social-counter/  APAGADO DO DISCO
```

`class-tagdiv-current-plugins-deactivation.php` **não desativa: remove.** O `td_011` guardou os
marcadores `td_theme_deactivated_current_plugins` e `theme_update_to_version`, que é o tema
avisando que fez isso de propósito e espera que o admin reinstale pelo painel.

### 🔴 A lição: o `200 OK` mentiu, e o `h1` correto mentiu junto

Depois de restaurar o tema e as opções, medi e **declarei o site restaurado**. Estava errado.
Naquele momento os plugins ainda estavam apagados e o site respondia assim:

```
http 200 ✅   |   h1 "Política" ✅   |   tempos normais ✅   |   home 131.475 bytes, 24 td_block
```

Tudo o que eu costumava checar dizia "de pé". Só depois de repor os arquivos apareceu o real:

```
home 572.257 bytes, 241 td_block
```

**A home estava servindo 23% do seu tamanho, com código HTTP 200 e título correto.** Três sinais
positivos e todos compatíveis com um site quebrado — o mesmo padrão de §16.12, agora com o agravo
de que **eu já tinha a regra escrita e ainda assim parei no primeiro sinal verde**.

O que teria pego na hora, e passa a ser o critério: **para página montada por blocos, o tamanho
do HTML é a medida; o código HTTP não é.** Um número absoluto comparado contra a linha de base,
não um "respondeu".

### Por que o site sobreviveu à remoção

`opcache.validate_timestamps=1` com `revalidate_freq=60`. O PHP seguiu servindo código compilado
de arquivos que já não existiam, e o WordPress apenas ignora `include` de plugin ausente. **Um
site que funciona por inércia de cache é um site já quebrado** — o rollout seguinte o teria
revelado inteiro.

### A restauração

1. Tema de volta à 12.7.6 pelo tar — 137 arquivos.
2. `td_011`, `td_011_settings`, `active_plugins` reescritos com os **bytes exatos** do snapshot
   (via `$wpdb`, não `update_option`, para não haver re-serialização). **10 de 11 idênticas.**
3. Os 3 plugins do tagDiv repostos **a partir do git** (`plugins/` está versionado): 4.028
   arquivos, **4028/4028 checksums batendo**.

A 11ª opção é `theme_switched`: antes existia vazia, agora está ausente. **Inerte** — o WordPress
só a lê para saber qual era o tema anterior e apagá-la em seguida; vazia e ausente são o mesmo
`false`. Ausente é o estado normal.

> ⚠️ **Armadilha do `tar` do macOS:** a primeira reposição levou **4.425 arquivos `._*`**
> (AppleDouble) para dentro do pod — 8.453 arquivos onde deviam ser 4.028. Só apareceu porque a
> conferência era **contagem + checksum**, não "extraiu sem erro". Usar `COPYFILE_DISABLE=1`.

### ✅ O achado que compensa o incidente: os "3 tagDiv sem canal" TÊM canal

A pendência dizia que td-composer, td-cloud-library e td-social-counter não têm canal de
atualização. **Têm — e ele mora dentro do tema**, em `includes/tagdiv-config.php`, como URLs
diretas com token:

```
https://cloud.tagdiv.com/td_plugins/td-composer/<hash>/td-composer.zip
https://cloud.tagdiv.com/td_plugins/td-cloud-library/<hash>/td-cloud-library.zip
https://cloud.tagdiv.com/td_plugins/td-social-counter/<hash>/td-social-counter.zip
```

Comparando os hashes da 12.7.6 com os da 12.7.7, **só dois mudaram**. E o que eles servem é
modesto:

| Plugin | Instalado | Exigido pela 12.7.7 |
|---|---|---|
| td-composer | 5.4.5 | **5.4.6** |
| td-cloud-library | 3.9.5 | **3.9.6** |
| td-social-counter | 5.7 | 5.7 — **mesmo hash, sem mudança** |

**São dois patches.** O tema não empacota os plugins (o zip de 1,8 MB só traz um ícone), ele
aponta para eles.

### O caminho para fazer o lote 10 dar certo — ORDEM É TUDO

O código da desativação dá a chave:

```php
if ( false === $this->theme_plugin_has_update( $plugin['slug'] ) && tagdiv_util::is_active( $plugin ) ) {
    unset( $theme_deactivated_plugin_array[$plugin['slug']] );
```

**Plugin que já está na versão esperada e ativo sai da lista de remoção.** Logo:

1. **Primeiro** os plugins: td-composer → 5.4.6 e td-cloud-library → 3.9.6, pelas URLs acima.
2. **Depois** o tema → 12.7.7. Com os plugins já corretos, não há o que ele queira remover.
3. Re-aplicar as 7 linhas do `archive.php` (`$SP/archive-cpt.patch`) — `clear_destination` vai
   apagá-las de novo, e elas **não** estão na 12.7.7 de origem.
4. Conferir `td_011` (500 chaves) e `active_plugins` (24) — e restaurar se mexer.
5. Validar por **tamanho de HTML**, não por código HTTP.

**Não executado: depende de decisão.** Homolog ficou na 12.7.6, íntegra e verificada — 3 leituras
estáveis em 572.257 bytes, 241 blocos, 200, e zero fatal no log.
