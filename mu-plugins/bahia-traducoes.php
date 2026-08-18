<?php
/**
 * Plugin Name: Bahia Traduções (tema/plugins)
 * Description: Traduz para PT-BR as strings fixas de interface do tema Newspaper e dos plugins
 *   tagDiv que ficavam em inglês (Trending Now, Stay Connected, comentários, busca, 404, widget
 *   social, etc.). Feito via filtro gettext (não edita arquivos do tema; sobrevive a updates).
 *
 * Escopo: só atua quando o domínio NÃO é 'default' (WordPress core), para não interferir nas
 * traduções pt_BR nativas do core. As strings do tema/tagDiv usam o domínio 'newspaper'.
 *
 * "Load more" passou a entrar aqui: o bahia-scroll-infinito só reescreve o texto VISÍVEL via
 * JS, mas o mesmo __td() alimenta também o aria-label do botão (td_block.php:3060), que ficava
 * em inglês para leitores de tela. Traduzindo na fonte, os dois saem certos já no HTML servido.
 *
 * NÃO inclui "Tags" (mantido, termo já usual em PT no contexto de notícias) — decisão do editor.
 *
 * @author bahia.ba / Claude Code
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mapa de tradução: string-fonte (inglês, como aparece no __()) => PT-BR.
 * Observação de caixa: onde a fonte é MAIÚSCULA, o tema já usa text-transform; mantemos a
 * caixa da fonte para casar exatamente.
 */
function bahia_traducoes_map() {
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $map = array(
        // Home / blocos de listagem
        'Trending Now'                => 'Em alta',
        'Stay Connected'              => 'Siga-nos',
        'EVEN MORE NEWS'              => 'MAIS NOTÍCIAS',
        'POPULAR CATEGORY'            => 'CATEGORIA POPULAR',
        'POPULAR POSTS'               => 'MAIS LIDAS',
        'OUR LATEST POSTS'            => 'NOSSAS ÚLTIMAS',
        'LATEST ARTICLES'             => 'ÚLTIMOS ARTIGOS',
        'Most popular'                => 'Mais populares',
        'Most Popular'                => 'Mais populares',
        'All time popular'            => 'Populares de sempre',
        'RELATED ARTICLES'            => 'ARTIGOS RELACIONADOS',
        'MORE FROM AUTHOR'            => 'MAIS DO AUTOR',
        'FOLLOW US'                   => 'SIGA-NOS',
        'Follow us on Instagram'      => 'Siga-nos no Instagram',
        'Read more'                   => 'Leia mais',

        // Yoast: %%page%% no title dos archives paginados
        // (class-wpseo-replace-vars.php:1029). Saía "Página 3 - Page 3 of 7763".
        'Page %1$d of %2$d'           => 'Página %1$d de %2$d',
        'Page %s'                     => 'Página %s',

        // Widget social / contadores
        'Fans'                        => 'Fãs',
        'Followers'                   => 'Seguidores',
        'Subscribers'                 => 'Inscritos',
        'Like'                        => 'Curtir',
        'Likes'                       => 'Curtidas',
        'Follow'                      => 'Seguir',
        'Subscribe'                   => 'Inscrever-se',

        // Post / single
        'Previous article'            => 'Artigo anterior',
        'Next article'                => 'Próximo artigo',
        'By'                          => 'Por',

        // Comentários
        'LEAVE A REPLY'               => 'DEIXE UMA RESPOSTA',
        'Post Comment'                => 'Publicar comentário',
        'Log in to leave a comment'   => 'Entre para comentar',
        'Logged in as'                => 'Conectado como',
        'Cancel reply'                => 'Cancelar resposta',
        'Reply'                       => 'Responder',
        'COMMENTS'                    => 'COMENTÁRIOS',
        'NO COMMENTS'                 => 'SEM COMENTÁRIOS',
        'Please enter your name here' => 'Digite seu nome aqui',

        // Busca
        'You searched for'            => 'Você buscou por',
        'search results'              => 'resultados da busca',
        'No results'                  => 'Nenhum resultado',
        'No results for your search'  => 'Nenhum resultado para sua busca',
        'No search results.'          => 'Nenhum resultado encontrado.',
        'No posts to display'         => 'Nenhum post para exibir',
        "If you're not happy with the results, please do another search"  => 'Não encontrou o que queria? Faça uma nova busca',

        // 404 / navegação / arquivos
        'Page not found'              => 'Página não encontrada',
        'HOMEPAGE'                    => 'PÁGINA INICIAL',
        'You can go to the'           => 'Você pode ir para',
        'View all posts in'           => 'Ver todos os posts em',
        'Posts by'                    => 'Posts de',
        'Recent comments'             => 'Comentários recentes',
        'Recent Comments'             => 'Comentários recentes',
        'Sign in'                     => 'Entrar',
        'Join'                        => 'Cadastrar',
        'Search'                      => 'Buscar',
        'Latest'                      => 'Recentes',
        'Archives'                    => 'Arquivos',
        'Authors'                     => 'Autores',
        'Home'                        => 'Início',
    );
    return $map;
}

/**
 * Whitelist de strings do WordPress core (domínio 'default') que aparecem em inglês no
 * front-end deste site (o core pt_BR não está cobrindo — ver item das traduções do core).
 * Só traduzimos ESTAS, para não sobrescrever o restante do core/admin.
 */
function bahia_traducoes_core_map() {
    static $core = null;
    if ($core !== null) {
        return $core;
    }
    $core = array(
        'Recent Comments' => 'Comentários recentes',
        'Recent Posts'    => 'Posts recentes',
        'No results found.' => 'Nenhum resultado encontrado.',
        'Search'          => 'Buscar',
    );
    return $core;
}

/**
 * Aplica a tradução. Domínio 'default' só via whitelist do core; demais via mapa do tema/tagDiv.
 */
function bahia_traducoes_gettext($translation, $text, $domain) {
    if ($domain === 'default') {
        $core = bahia_traducoes_core_map();
        return isset($core[$text]) ? $core[$text] : $translation;
    }
    $map = bahia_traducoes_map();
    return isset($map[$text]) ? $map[$text] : $translation;
}
add_filter('gettext', 'bahia_traducoes_gettext', 20, 3);

/**
 * Strings que passam pelo __td() do tagDiv, e NÃO pelo gettext.
 *
 * O td-composer tem um mecanismo de tradução próprio: __td() consulta os globais
 * $td_translation_map_user / $td_translation_map (td_translate.php:51) e nunca chama
 * __(). Por isso o filtro gettext acima não alcança essas strings — foi o que deixou
 * o title/aria-label "Search" nos botões de busca mesmo com 'Search' => 'Buscar' no
 * mapa. O mapa de usuário é lido de td_options::get_array('td_translation_map_user'),
 * ou seja, de dentro do option de tema — então dá para injetar pelo mesmo filtro.
 *
 * Vale para texto visível e para atributos (title, aria-label), que é onde isso
 * ainda aparecia.
 */
function bahia_traducoes_td_map() {
    return array(
        'Search' => 'Buscar',
        // Alimenta o texto E o aria-label do botão de load more (td_block.php:3060).
        // O bahia-scroll-infinito só reescreve o texto visível por JS; sem isto o
        // aria-label continuava "Load more" para leitores de tela.
        'Load more' => 'Ver mais notícias',
    );
}

add_filter('option_' . (defined('BAHIA_TD_OPTION_NAME') ? BAHIA_TD_OPTION_NAME : 'td_011'), function ($options) {
    if (!is_array($options)) {
        return $options;
    }
    $atual = isset($options['td_translation_map_user']) && is_array($options['td_translation_map_user'])
        ? $options['td_translation_map_user']
        : array();
    // o que já estiver configurado no painel tem precedência
    $options['td_translation_map_user'] = $atual + bahia_traducoes_td_map();
    return $options;
});

/**
 * Mesmo para strings com contexto (_x / _ex).
 */
function bahia_traducoes_gettext_ctx($translation, $text, $context, $domain) {
    if ($domain === 'default') {
        $core = bahia_traducoes_core_map();
        return isset($core[$text]) ? $core[$text] : $translation;
    }
    $map = bahia_traducoes_map();
    return isset($map[$text]) ? $map[$text] : $translation;
}
add_filter('gettext_with_context', 'bahia_traducoes_gettext_ctx', 20, 4);
