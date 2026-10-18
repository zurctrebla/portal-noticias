<?php
/**
 * Plugin Name: Bahia.ba - Desliga a indexação em segundo plano do Yoast
 * Description: TEMPORÁRIO, E SÓ EM HOMOLOG. Impede que o `wpseo_indexable_index_batch` do Yoast
 *              rode em segundo plano.
 *
 *              ================================================================
 *              POR QUE EXISTE — medido em 02/09/2026, no lote 7
 *              ================================================================
 *
 *              Depois do Yoast subir para 28.4 (lote 6), homolog ficou com a busca em 3 a 163
 *              segundos, onde levava 107 a 824 ms, e com 504 na home. As CONTAGENS da busca
 *              continuaram certas (501, 483 — as mesmas de antes), então não era defeito de
 *              consulta: era contenção.
 *
 *              A causa, achada no `PROCESSLIST`: cinco cópias empilhadas desta consulta, de 13 a
 *              28 minutos cada, disparadas pelo `wpseo_indexable_index_batch`:
 *
 *                  SELECT P.ID FROM wp_posts AS P
 *                  WHERE P.post_type IN (30 tipos) AND P.post_status NOT IN ('auto-draft')
 *                    AND NOT EXISTS (SELECT 1 FROM wp_yoast_indexable I
 *                                    WHERE I.object_id = P.ID AND I.object_type = 'post'
 *                                      AND I.version = 2)
 *                  LIMIT 15
 *
 *              É um anti-join sobre `wp_posts` (435 mil linhas, 1,1 GB) num `innodb_buffer_pool`
 *              de 128 MB. Ele procura os posts SEM linha de indexável — que em homolog são
 *              23.654 — e o `LIMIT 15` não ajuda: para achar 15, varre a tabela.
 *
 *              O cron é de 15 em 15 minutos (`fifteen_minutes`), e cada disparo pode encavalar
 *              com o anterior, que ainda não terminou. Foi assim que chegou a cinco.
 *
 *              ================================================================
 *              O QUE ESTE ARQUIVO FAZ — duas linhas de defesa
 *              ================================================================
 *
 *              1. `wp_clear_scheduled_hook()` tira o evento da fila do WP-Cron;
 *              2. `remove_all_actions()` esvazia o gancho, para o caso de o evento ser
 *                 reagendado por outro caminho antes do próximo carregamento.
 *
 *              A segunda existe porque o Yoast REAGENDA sozinho, em `admin_init` prioridade 11
 *              (`background-indexing-integration.php:211`). Tirar da fila uma vez não basta.
 *
 *              ================================================================
 *              O QUE ISSO CUSTA — e não é nada, e é preciso saber por quê
 *              ================================================================
 *
 *              O Yoast cria o indexável de uma página SOB DEMANDA, quando alguém a acessa. O
 *              trabalho em segundo plano só ADIANTA esse trabalho para páginas que ninguém
 *              visitou ainda.
 *
 *              Com isto ligado:
 *                - `title`, `meta description`, canonical e Open Graph continuam saindo iguais
 *                  (conferido em 5 telas, antes e depois);
 *                - os sub-sitemaps continuam respondendo;
 *                - o que NÃO acontece é o preenchimento antecipado dos 23.654 sem indexável.
 *
 *              Ou seja: troca-se um trabalho de fundo caro por trabalho sob demanda barato.
 *              **Não é perda de funcionalidade; é mudança de quando o custo é pago.**
 *
 *              ================================================================
 *              🔴 COMO REVERTER — uma linha, e é para isto que este bloco existe
 *              ================================================================
 *
 *              APAGUE ESTE ARQUIVO. Não há estado guardado, não há opção escrita, não há
 *              migração a desfazer. No carregamento seguinte o Yoast reagenda o cron sozinho, em
 *              `admin_init`, e volta ao comportamento de fábrica.
 *
 *              Para conferir que voltou:
 *                  wp_next_scheduled('wpseo_indexable_index_batch')   // deixa de ser false
 *
 *              ================================================================
 *              ⚠️ ESTE ARQUIVO NÃO AGE EM PRODUÇÃO, DE PROPÓSITO
 *              ================================================================
 *
 *              A guarda de ambiente é a primeira coisa no corpo. Produção tem 435 mil posts e um
 *              `buffer_pool` de 11 GB — o custo pode ser absorvido lá, e ninguém mediu. Se a
 *              decisão for aplicar em produção também, o gesto é trocar a condição do `return`
 *              abaixo, **com a medição na mão**, e não por herança deste arquivo.
 *
 *              Portão de produção escrito em `scratchpad/MIGRACAO-homolog-para-prod.md`, seção 10.
 *
 *              ================================================================
 *              ⏳ CONDIÇÃO DE SAÍDA — para não virar permanente sem ninguém decidir
 *              ================================================================
 *
 *              Este arquivo sai quando UMA das duas acontecer:
 *
 *              a) os 23.654 posts sem linha de indexável forem preenchidos de uma vez, em janela
 *                 controlada (fora do horário da redação), e o anti-join passar a devolver vazio
 *                 rápido; ou
 *              b) ficar decidido que a indexação antecipada não interessa, e aí este arquivo
 *                 deixa de ser temporário — mas por DECISÃO ESCRITA, não por esquecimento.
 *
 *              Enquanto nenhuma das duas acontecer, ele fica, e esta seção é o lembrete.
 *
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Só homolog. Em produção, e em ambiente não identificado, este arquivo é inerte.
 *
 * `bahia_ambiente()` vem do `bahia-flags.php` e resolve por `siteurl`. Se ele não existir
 * (ordem de carga dos mu-plugins), o arquivo se cala em vez de adivinhar — o modo seguro aqui
 * é NÃO desligar nada.
 */
if (!function_exists('bahia_ambiente') || 'homolog' !== bahia_ambiente()) {
    return;
}

const BAHIA_YOAST_CRON_INDEXACAO = 'wpseo_indexable_index_batch';

/**
 * Tira o evento da fila e esvazia o gancho.
 *
 * Roda em `init` prioridade 20: depois de os plugins registrarem seus ganchos (o Yoast pendura
 * o `index()` no `wpseo_indexable_index_batch` em `background-indexing-integration.php:118`) e
 * antes de `admin_init`, onde ele reagenda.
 *
 * `wp_clear_scheduled_hook()` só escreve quando encontra algo agendado; nas cargas em que já
 * está limpo, é leitura de uma opção que o WordPress já carregou.
 */
function bahia_yoast_desliga_indexacao_fundo() {
    if (wp_next_scheduled(BAHIA_YOAST_CRON_INDEXACAO)) {
        wp_clear_scheduled_hook(BAHIA_YOAST_CRON_INDEXACAO);
    }

    // Rede de segurança: se o evento for disparado por outro caminho, não há o que executar.
    remove_all_actions(BAHIA_YOAST_CRON_INDEXACAO);
}
add_action('init', 'bahia_yoast_desliga_indexacao_fundo', 20);

/**
 * O mesmo, tarde, para cobrir o reagendamento do Yoast em `admin_init` prioridade 11.
 *
 * Sem isto, uma visita ao painel reagenda o evento e ele volta a rodar até o próximo `init`.
 */
add_action('admin_init', 'bahia_yoast_desliga_indexacao_fundo', 99);
