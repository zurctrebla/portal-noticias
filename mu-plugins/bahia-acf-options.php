<?php
/**
 * Plugin Name: Bahia.ba - Página de Opções do ACF (Destaques da Home)
 * Description: Devolve ao painel a tela onde o editor escolhe os destaques manuais da
 *              home. Ela existia no tema bahia_refactor e sumiu na migração, porque quem
 *              a registrava era o próprio tema.
 *
 * ---------------------------------------------------------------------------
 * O QUE ACONTECEU
 *
 * `themes/bahia_refactor/functions.php:33-35` registrava as páginas de opções:
 *
 *     if (function_exists('acf_add_options_sub_page')) {
 *         acf_add_options_sub_page('Home');
 *         acf_add_options_sub_page('Geral');
 *     }
 *
 * Trocado o tema, essas linhas saíram de cena. O grupo de campos "Destaques Home"
 * continuou no banco, os valores continuaram em `wp_options` (`options_slider_m1`,
 * `options_semi_destaques_m1`…), e o `bahia-home-destaques.php` continuou lendo dali —
 * mas **não havia mais tela nenhuma para o editor mudar a escolha**. O conteúdo do
 * destaque ficou congelado no que estava gravado no dia do retrato.
 *
 * É a mesma família de falha do subtítulo e do `page_on_front`: o dado sobrevive à
 * migração, o caminho de edição não, e nada acusa erro.
 *
 * ---------------------------------------------------------------------------
 * POR QUE `acf/init` E NÃO NO CORPO DO ARQUIVO
 *
 * mu-plugins carregam ANTES dos plugins normais, então `acf_add_options_sub_page()` ainda
 * não existe quando este arquivo é lido. No tema aquilo funcionava porque tema carrega
 * depois de plugin. Aqui o registro tem de esperar o ACF subir.
 *
 * ---------------------------------------------------------------------------
 * POR QUE A CHAMADA É IDÊNTICA À DO TEMA ANTIGO
 *
 * O grupo de campos tem `location = options_page == 'acf-options-home'`. O slug precisa
 * bater exatamente, ou a página aparece vazia. Repetir a mesma chamada que funcionava em
 * produção é o caminho mais seguro para chegar ao mesmo slug, em vez de montar o array de
 * argumentos à mão e torcer para o slug coincidir.
 *
 * A página "Geral" NÃO é registrada aqui, de propósito: seus campos (`options_facebook`,
 * `options_whatsapp`, `options_logo_login`) não são lidos por nada no tema novo —
 * conferido por varredura em `mu-plugins/` e `themes/Newspaper/`. Registrá-la exporia uma
 * tela cujas edições não teriam efeito nenhum, que é pior que não ter a tela.
 *
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Slug exigido pela `location` do grupo de campos "Destaques Home". Não pode mudar. */
define('BAHIA_ACF_OPTIONS_HOME_SLUG', 'acf-options-home');

/** Mesma capacidade do tema antigo: todo mundo que edita matéria alcança a tela. */
define('BAHIA_ACF_OPTIONS_HOME_CAP', 'edit_posts');

/**
 * O NOME: "Destaques da Home", e não o "Opções → Home" de produção.
 *
 * Decidido em 17/08/2026, por evidência e não por gosto. A sequência foi esta:
 *
 *   1. Registrei como "Destaques da Home" (topo, ícone de estrela), achando que o nome
 *      descritivo ajudaria mais que o genérico "Opções" de produção.
 *   2. Um repórter reclamou que não encontrava a tela. A hipótese óbvia era a minha
 *      divergência: ele procuraria "Opções", como está acostumado.
 *   3. Reproduzi a paridade — "Opções" com submenu "Home" e "Geral", posição 38 de 48
 *      contra 37 de 47 em produção, mesmos vizinhos (ACF acima, Yoast SEO abaixo).
 *   4. **Ele já tinha encontrado, pelo nome novo.** O que faltava não era paridade de
 *      rótulo; era a tela existir, o que só passou a acontecer horas antes.
 *
 * Fica o nome descritivo, então, porque foi o que funcionou na prática. Se a virada
 * mostrar que a memória de "Opções" pesa mais, é trocar as duas linhas abaixo pelas de
 * `bahia_refactor/functions.php:33-35` (`acf_add_options_sub_page('Home')` e `('Geral')`),
 * que reproduzem produção exatamente.
 *
 * O SLUG não muda em nenhuma hipótese: `acf-options-home` é o que a `location` do grupo
 * "Destaques Home" exige, e é a mesma URL de produção — quem comparar as duas telas pela
 * barra de endereço vê o mesmo caminho.
 *
 * "Geral" continua fora: seus campos (`options_facebook`, `options_whatsapp`,
 * `options_logo_login`) não são lidos por nada no tema novo, conferido por varredura.
 * Expor uma tela cujas edições não têm efeito é pior que não tê-la. Se algum dia esses
 * campos voltarem a ser lidos, registrar junto.
 */
add_action('acf/init', function () {
    if (!function_exists('acf_add_options_page')) {
        return;
    }

    acf_add_options_page(array(
        'page_title' => 'Destaques da Home',
        'menu_title' => 'Destaques da Home',
        'menu_slug'  => BAHIA_ACF_OPTIONS_HOME_SLUG,
        'capability' => BAHIA_ACF_OPTIONS_HOME_CAP,
        'icon_url'   => 'dashicons-star-filled',
        'position'   => 26,
        'redirect'   => false,
        'autoload'   => true,
    ));
});

/**
 * Atalho para os destaques dentro da caixa "Publicar".
 *
 * A tela de destaques é uma entrada solta na barra lateral, entre 48 itens, e nada na tela
 * de escrever matéria levava até ela: o repórter publicava e não tinha como saber que a
 * escolha do destaque existe e onde fica. Em produção é igual — lá o item chama-se
 * "Opções", que diz menos ainda.
 *
 * `post_submitbox_misc_actions` desenha na mesma seção de "Status", "Visibilidade" e
 * "Publicar em", então o atalho herda o estilo nativo em vez de parecer enxerto.
 *
 * ABRE EM ABA NOVA, de propósito: sair da tela com matéria não salva custaria o texto, ou
 * o diálogo de "Sair do site?". O repórter volta para o que estava escrevendo.
 */
function bahia_acf_options_atalho_destaques() {
    if (!current_user_can(BAHIA_ACF_OPTIONS_HOME_CAP)) {
        return;
    }

    $tela = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$tela || $tela->base !== 'post') {
        return;
    }

    // Página não entra em destaque da home; o atalho ali seria só ruído.
    $objeto = get_post_type_object($tela->post_type);
    if ($tela->post_type === 'page' || !$objeto || empty($objeto->public)) {
        return;
    }

    printf(
        '<div class="misc-pub-section bahia-atalho-destaques">' .
        '<span class="dashicons dashicons-star-filled" style="color:#996800;vertical-align:middle;"></span> ' .
        '<a href="%s" target="_blank" rel="noopener">%s</a>' .
        '<p class="description" style="margin:4px 0 0;">%s</p>' .
        '</div>',
        esc_url(admin_url('admin.php?page=' . BAHIA_ACF_OPTIONS_HOME_SLUG)),
        esc_html__('Destaques da Home', 'bahia'),
        // Diz ONDE fica no menu, não só o que é: quem clicar aqui uma vez aprende o
        // caminho e não depende mais do atalho.
        esc_html__('No menu lateral: Destaques da Home. Abre em outra aba.', 'bahia')
    );
}
add_action('post_submitbox_misc_actions', 'bahia_acf_options_atalho_destaques');
