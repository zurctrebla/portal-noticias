# Reversão dos anúncios de teste no AdRotate (homolog)

**Ambiente:** apenas **hml.bahia.ba**. Nada aqui se aplica a produção.
**Estado levantado em:** 11/08/2026

## Contexto

Três anúncios foram deixados ativos em homologação para servir de inventário de teste — sem
eles, os espaços publicitários renderizam vazios e não dá para validar o layout do cabeçalho
e das listagens.

**Eles não devem ser transportados para produção.** Produção tem o próprio calendário
comercial. Ver `MIGRACAO-homolog-para-prod.md`, seção 1.6.

## Estado atual (o que existe hoje em homolog)

Tabela `wp_adrotate`:

| id | título | type | tracker | agendamento (`wp_adrotate_schedule`) |
|----|--------|------|---------|--------------------------------------|
| 1724 | JULHO - CMS - PI 2760 banner 1 | `active` | `N` | 06/07/2026 → 30/09/2026 |
| 1725 | JULHO - CMS - PI 2760 banner 2 | `active` | `N` | 06/07/2026 → 30/09/2026 |
| 1726 | JULHO - CANDEIAS - PI 9257 | `active` | `N` | 07/07/2026 → 30/09/2026 |

São os **únicos três** com `type = 'active'` no ambiente (total de 151 anúncios cadastrados).

## Ressalva honesta sobre o estado anterior

**Os valores que essas três linhas tinham antes de serem colocadas em `active` não foram
registrados em lugar nenhum** — nem em backup de opção, nem em anotação de rodada. O que se
sabe:

- O agendamento atual vai até **30/09/2026**, uma data futura. Ou seja, hoje elas estão dentro
  da janela e o AdRotate as considera legitimamente vigentes.
- Os títulos começam com "JULHO", e o restante do inventário do mesmo período está `expired`.

Portanto **não é possível afirmar com certeza** se o que mudou foi o `type` (de `expired` para
`active`) ou o `stoptime` do agendamento (estendido para 30/09). A reversão abaixo assume o
caso mais provável — voltar o `type` para `expired` — e é reversível.

## Como reverter em homolog

Só é necessário se alguém quiser devolver a homologação ao estado "sem anúncios no ar". Para o
objetivo de não contaminar produção, **basta não migrar** — nenhuma ação em homolog é exigida.

### 1. Conferir antes (e guardar o retrato)

```sql
SELECT id, title, type, tracker FROM wp_adrotate WHERE id IN (1724,1725,1726);

SELECT lm.ad, s.id AS schedule_id,
       FROM_UNIXTIME(s.starttime) AS inicio,
       FROM_UNIXTIME(s.stoptime)  AS fim
  FROM wp_adrotate_linkmeta lm
  JOIN wp_adrotate_schedule s ON s.id = lm.schedule
 WHERE lm.ad IN (1724,1725,1726);
```

Copiar a saída para algum lugar antes de escrever qualquer coisa.

### 2. Desativar

```sql
UPDATE wp_adrotate SET type = 'expired' WHERE id IN (1724,1725,1726);
```

### 3. Desfazer a desativação, se preciso

```sql
UPDATE wp_adrotate SET type = 'active' WHERE id IN (1724,1725,1726);
```

### 4. Limpar o cache depois

O AdRotate guarda cache próprio, e o nginx tem o `fastcgi_cache` no sidecar:

```bash
POD=$(kubectl get pod -n bahia-wordpress -l app=wordpress -o jsonpath='{.items[0].metadata.name}')
kubectl exec -n bahia-wordpress $POD -c nginx -- sh -lc 'rm -rf /tmp/nginx-cache/*'
```

## Observação que vale para produção

Os três estão com **`tracker = 'N'`** — a contagem de exibições está desligada. Isso não é
específico do teste: **nenhum** anúncio ativo tem a contagem ligada, e o último registro de
estatística no banco é de **28/06/2026**. É a pendência nº 3 de `PENDENCIAS-gestores.md`, e
vale igualmente para produção.
