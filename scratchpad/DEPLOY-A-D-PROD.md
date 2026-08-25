# Deploy de A e D em produção — relatório

2026-08-25. Commits `32cda92c`, `298e0a79`, `512ce363`.

## Protocolo de medição

| | ANTES | DEPOIS |
|---|---|---|
| imagem | `bahia-wordpress:prod-dd2da0b7...` | `bahia-wordpress:prod-512ce363...` |
| revisão do deploy | 50 | **51** |
| ReplicaSet | `wordpress-fc88c7846` | `wordpress-cfc4679d8` |
| pods | 5 Running | 5 Running |

`fastcgi_cache` purgado pelo próprio pipeline (passo "Purgar fastcgi_cache", ✓).
Homolog reconstruído antes, pelo push em `develop` (✓ 1m09s).

## Portões de banco, depois do rollout

Cinco amostras seguidas, sob tráfego normal (sem gerar carga em produção):

```
Threads_running = 2, 2, 2, 3, 3
SQL_CALC_FOUND_ROWS = 0 em todas
consultas > 5s = 1 (constante, pré-existente)
conexões = 6 a 9
```

Limpo.

## Prova de que os dois filtros estão ativos

```
filtro carregado: SIM
anexo 421217:  td_80x60 -> -110x76 | td_218x150 -> -269x187 | td_485x360 -> -538x374
```

E no network do Chrome, na página de artigo:
- **`footer-bg-azul.png` NÃO é requisitado** ✓
- a imagem de destaque vem como `jeronimo-lula-rui-wagner-768x512.jpg`, e antes vinha inteira

**Par medido no CDN, mesma imagem:** `175 KB -> 54 KB` (−69%).

## Peso medido pelo CDN

| página | antes | depois |
|---|---|---|
| home (média de 3) | 5.617 KB | **5.356 KB** (−4,6%) |
| single | 1.069 KB | 1.209 KB* |
| archive /politica/ | 2.253 KB | 2.155 KB |

\* conteúdo e anúncios diferentes entre as duas medições; não é comparável.

**A home mudou pouco, e o motivo é legítimo.** Dos 30 originais que continuam sendo servidos,
**23 são de 2026** — imagens recentes cujo original é *menor* que o tamanho pedido
(`620x400` contra `td_696x0`). Nesse caso o filtro deliberadamente não age, porque a alternativa
seria upscale. A home de produção é conteúdo novo; o ganho do filtro está no acervo antigo, que
aparece nos archives, nas relacionadas e nas mais lidas.

Nos archives dá para ver o filtro trabalhando: as derivadas servidas são `768x512`, `768x576`,
`768x543` — `medium_large` escolhido no lugar do original.

## Conferência visual

Home, archive `/politica/`, single e rodapé, desktop. Layout intacto, enquadramento natural,
nenhuma quebra. Rodapé com o degradê azul→violeta idêntico ao do PNG.
Móvel conferido pelo servidor (User-Agent de iPhone): 45 imagens, 15 derivadas, incluindo
`218x150` e `696x*`; a regra do gradiente presente.

> O `resize_window` do navegador não muda o viewport da captura de forma confiável no macOS —
> mesma armadilha já registrada. Por isso o móvel foi conferido pelo servidor.

## Como reverter

Não há alteração de banco. Reverter é **remover os dois arquivos e reconstruir**:

```
mu-plugins/bahia-image-size-fallback.php     -> apagar
mu-plugins/bahia-cores-ui.php                -> git revert 298e0a79
```

e push em `main`, que reconstrói a imagem e troca os pods. O `git revert` dos três commits
também serve. Nenhum passo é destrutivo e nada precisa ser desfeito no S3 nem no RDS.

## O que observar nas próximas 48h

No CloudFront, distribuição `E1WMK6FHJH285F`:
- **esperado:** queda nos `BytesDownloaded` por dia **sem** queda em `Requests`
- se as requisições caírem junto, não foi o filtro — foi audiência, e a leitura muda
