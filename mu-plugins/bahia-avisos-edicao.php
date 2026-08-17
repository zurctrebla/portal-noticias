<?php
/**
 * Plugin Name: Bahia.ba - Silêncio de avisos de plugin na tela de edição
 * Description: Remove os avisos de plugin do topo das telas de criar e editar matéria, e
 *              SOMENTE para quem não administra o site. Administrador e comercial continuam
 *              vendo tudo, em todas as telas.
 *
 * ---------------------------------------------------------------------------
 * ISTO É SUPRESSÃO DELIBERADA. NÃO É BUG.
 *
 * Se você chegou aqui daqui a seis meses procurando por que um aviso não aparece:
 * é este arquivo. Para reverter, apague-o — não há estado a desfazer, nada é gravado
 * no banco. Para ver os avisos sem apagar nada, entre com um usuário que tenha
 * `manage_options`.
 *
 * ---------------------------------------------------------------------------
 * POR QUE
 *
 * A tela de edição carregava três avisos acima do campo de título, empurrando-o para
 * baixo da dobra, em TODA matéria:
 *
 *   - FooGallery                — oferta de teste grátis de recursos premium
 *   - WP Twitter Auto Publish   — pedido de avaliação com 5 estrelas e link de doação
 *   - AdRotate                  — "105 adverts expired" e "banner folder not writable"
 *
 * Os dois primeiros são propaganda. O terceiro é informação legítima, mas endereçada ao
 * comercial, não a quem escreve matéria.
 *
 * É atrito diário: some do caminho de quem publica dezenas de vezes por dia, e é o tipo
 * de coisa que faz a redação achar o site novo pior sem conseguir dizer por quê. Não vem
 * do tema — viria com qualquer tema, porque a origem são os plugins.
 *
 * ---------------------------------------------------------------------------
 * POR QUE remove_all_actions() E NÃO AS ALTERNATIVAS
 *
 * `remove_action` por nome de callback exigiria saber o nome que cada plugin registrou.
 * Quebra em silêncio na primeira atualização que renomear o método, e o ruído volta sem
 * ninguém perceber.
 *
 * Filtrar o HTML no buffer de saída dependeria do TEXTO que o plugin imprime, que muda com
 * a versão e com a tradução — acoplamento a copy de terceiros.
 *
 * `remove_all_actions()` não depende de nome de callback nem de texto: esvazia o hook.
 *
 * ---------------------------------------------------------------------------
 * O PONTO DE ENGANCHE, E POR QUE ESTE
 *
 * Medido no core desta instalação:
 *
 *   wp-admin/admin.php:176         do_action( 'admin_init' )
 *   wp-admin/admin.php:213         set_current_screen()
 *   wp-admin/admin-header.php:277  do_action( 'in_admin_header' )   <-- aqui
 *   wp-admin/admin-header.php:299  do_action( 'network_admin_notices' )
 *   wp-admin/admin-header.php:306  do_action( 'user_admin_notices' )
 *   wp-admin/admin-header.php:313  do_action( 'admin_notices' )
 *   wp-admin/admin-header.php:321  do_action( 'all_admin_notices' )
 *
 * `in_admin_header` roda depois de todo plugin ter registrado e depois de a tela ser
 * conhecida, e antes dos quatro disparos de aviso. `network_admin_notices` fica de fora
 * porque só dispara no admin de rede, que este site não usa.
 *
 * ---------------------------------------------------------------------------
 * O QUE NÃO É AFETADO
 *
 * A mensagem de "Post publicado" / "Rascunho do post atualizado" NÃO passa por estes
 * hooks: sai de `wp_admin_notice()` chamado direto em `wp-admin/edit-form-advanced.php`
 * (linhas 438, 448 e 465), junto com os avisos de post bloqueado por outro editor.
 * Continua aparecendo.
 *
 * ---------------------------------------------------------------------------
 * O CUSTO ACEITO, REGISTRADO
 *
 * Um plugin que use `admin_notices` para relatar erro operacional legítimo — um campo ACF
 * mal configurado, por exemplo — também fica calado para o repórter nessas duas telas. Foi
 * pesado contra o atrito diário e aceito: quem precisa agir sobre esse tipo de erro tem
 * `manage_options` e continua vendo.
 *
 * RESSALVA DO AdRotate: o aviso de "banner folder not writable" é hoje inofensivo porque
 * os 5 anúncios ativos referenciam a Biblioteca de Mídia por URL, e a pasta própria do
 * plugin nunca foi usada. **Se um dia o comercial passar a usar o upload nativo do
 * AdRotate, esse aviso volta a ser útil e esta supressão precisa ser revista.**
 *
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Telas cobertas: criar e editar post, de qualquer tipo.
 *
 * `base === 'post'` cobre `post.php` e `post-new.php`. Qualquer outra tela do painel —
 * listagens, mídia, plugins, ajustes, AdRotate — fica de fora de propósito.
 */
function bahia_avisos_edicao_tela_coberta() {
    if (!function_exists('get_current_screen')) {
        return false;
    }

    $tela = get_current_screen();

    return $tela && $tela->base === 'post';
}

add_action('in_admin_header', function () {
    if (wp_doing_ajax() || current_user_can('manage_options')) {
        return;
    }
    if (!bahia_avisos_edicao_tela_coberta()) {
        return;
    }

    remove_all_actions('admin_notices');
    remove_all_actions('all_admin_notices');
    remove_all_actions('user_admin_notices');
}, PHP_INT_MAX);
