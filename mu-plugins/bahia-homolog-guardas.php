<?php
/**
 * Plugin Name: Bahia.ba - Guardas de homologação
 * Description: Impede que uma exclusão feita em HOMOLOG apague arquivo do site no ar.
 *
 *              Homolog e produção compartilham o bucket `static.bahia.ba`, o mesmo prefixo e a
 *              mesma distribuição do CloudFront. O banco de homolog é o retrato de produção de
 *              28/07/2026 e trouxe junto a `wp_as3cf_items` inteira: ~155.500 linhas apontando
 *              para os MESMOS objetos que o site serve. Conferido por amostra determinística —
 *              142 de 142 IDs presentes nos dois bancos com caminho idêntico.
 *
 *              Em 25/08/2026 isso deixou de ser risco e virou incidente: uma limpeza de anexos
 *              de teste em homolog, filtrada por título com LIKE, casou com dois anexos reais e
 *              apagou NOVE objetos de produção. O bucket não tem versionamento; não houve como
 *              restaurar pelo S3. Relato em scratchpad/INCIDENTE-APAGUEI-2-IMAGENS.md.
 *
 *              Duas guardas, ambas só em homolog:
 *
 *              1. o Offload nunca remove objeto do bucket;
 *              2. anexo com ID abaixo de 9.000.001 não pode ser apagado — essa é a faixa dos
 *                 registros nascidos em PRODUÇÃO. Os dois anexos do incidente eram 313723 e
 *                 542264, e a guarda os teria barrado.
 *
 *              CONSEQUÊNCIA, de propósito: "testar e limpar" deixa de limpar o bucket. Objetos
 *              criados por teste em homolog PERMANECEM no bucket de produção e viram tarefa
 *              própria, feita por lista de prefixos, de fora do WordPress. Eram ~886 KB em
 *              25/08. É o preço, e é barato perto de nove arquivos perdidos.
 *
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Só em homolog. `bahia_ambiente()` vem de `bahia-flags.php`, que carrega antes por ordem
 * alfabética; a checagem de existência evita depender disso.
 */
if (!function_exists('bahia_ambiente') || 'homolog' !== bahia_ambiente()) {
    return;
}

/**
 * Guarda 1 — o Offload não apaga nada do bucket.
 *
 * `as3cf_remove_source_files_from_provider` é o filtro que monta a lista de objetos a remover.
 * Devolver array vazio faz a remoção não ter o que fazer. Verificado que é este o filtro que
 * governa a remoção: instrumentado em 25/08, ele recebe todas as chaves de
 * `extra_info['objects']` antes de o cliente do S3 ser chamado.
 *
 * Prioridade 99 para vir depois de qualquer coisa que acrescente caminhos.
 */
add_filter('as3cf_remove_source_files_from_provider', '__return_empty_array', 99);

/**
 * Guarda 2 — não apagar anexo nascido em produção.
 *
 * A renumeração de 16/08/2026 moveu tudo que nasceu em homolog para a faixa 9.000.001+
 * (ver a nota no topo do HANDOVER). Logo, ID abaixo disso veio do retrato de produção e não é
 * material de teste, por mais que o título pareça.
 *
 * `pre_delete_attachment` permite curto-circuito: qualquer retorno diferente de null impede a
 * exclusão. Devolvemos false, que o WordPress trata como "não apagou".
 */
add_filter('pre_delete_attachment', function ($check, $post, $force_delete) {
    if (null !== $check) {
        return $check; // alguém já decidiu antes
    }
    $id = is_object($post) ? (int) $post->ID : (int) $post;
    if ($id > 0 && $id < 9000001) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log(sprintf(
                '[bahia-homolog-guardas] exclusao do anexo %d bloqueada: ID abaixo de 9.000.001, nasceu em producao.',
                $id
            ));
        }
        return false;
    }
    return $check;
}, 1, 3);
