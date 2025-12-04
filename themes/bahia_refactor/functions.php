<?php
require_once 'Mobile-Detect/Mobile_Detect.php';
require_once 'inc/search-filters.php';

// Disable use XML-RPC
add_filter('xmlrpc_enabled', '__return_false');
// Disable X-Pingback to header
add_filter('wp_headers', 'disable_x_pingback');
// remove barra de admin do topo do site
show_admin_bar(false);

function disable_x_pingback($headers)
{
    unset($headers['X-Pingback']);
    return $headers;
}

define('TEMPLATE_URL', get_bloginfo('template_url'));
define('URL', get_bloginfo('url'));

// adiciona suporte a html5
current_theme_supports('html5');
add_theme_support('html5', array(
    'comment-list',
    'search-form',
    'comment-form',
    'gallery',
    'caption',
    'figure',
    'figcaption',
));

if (function_exists('acf_add_options_sub_page')) {
    acf_add_options_sub_page('Home');
    acf_add_options_sub_page('Geral');
}

$pos_center = array('center', 'center');
add_image_size('destaque_gigante', 1076, 560, $pos_center);
add_image_size('destaque_grande', 538, 374, $pos_center);
add_image_size('destaque_pequeno', 269, 187, $pos_center);
add_image_size('destaque_mini', 110, 76, $pos_center);
add_image_size('news_home', 345, 240, $pos_center);
add_image_size('user_avatar', 200, 200, $pos_center);

// REMOVE POST
add_action('admin_menu', 'remove_default_post_type');

function remove_default_post_type()
{
    remove_menu_page('edit.php');
}

// DEFINE CUSTOM POST TYPES
global $POST_TYPES;
$POST_TYPES = array(
    'artigo',
    'bombou',
    'entrevista',
    'bahia',
    'brasil',
    'covid19',
    'carnaval',
    'carnaval',
    'eleicoes2024',
    'economia',
    'entretenimento',
    'especial',
    'esporte',
    'exclusivo',
    'gente',
    'justica',
    'mais_noticias',
    'municipios',
    'mundo',
    'politica',
    'saude',
    'salvador',
    'social',
    'investimentos',
);

global $POST_TYPES_LIST;
$POST_TYPES_LIST = implode(",", $POST_TYPES);

// adiciona post_types
foreach ($POST_TYPES as $post_type) {
    if ($post_type == 'post') $post_type = 'levi';
    include("post-types/{$post_type}.php");
}

// REMOVE META BOX QUE DESATIVA COMENTÁRIOS
function remove_meta_boxes()
{
    global $POST_TYPES;
    foreach ($POST_TYPES as $post_type)
        remove_meta_box('commentstatusdiv', $post_type, 'normal');
}

add_action('admin_menu', 'remove_meta_boxes');

/* REMOVE RASCUNHOS */
function relationship_options_filter($options, $field, $the_post)
{
    $options['post_status'] = array('publish');
    return $options;
}

$relationship_acfs = array('slider_m1', 'semi_destaques_m1', 'destaques', 'slider_m3', 'destaque_m3', 'sub_destaques');

foreach ($relationship_acfs as $field) {
    add_filter('acf/fields/relationship/query/name=' . $field, 'relationship_options_filter', 10, 3);
}

// redefine logo na página /wp-admin
function my_login_logo()
{
    $logo_login = get_bloginfo('template_url') . '/assets/imgs/logo_ba.png';
?>
    <style type="text/css">
        #login h1 a {
            background-image: url("<?= $logo_login; ?>") !important;
            height: 150px;
            width: 150px;
            background-size: 150px;
        }
    </style>
    <?php
}

add_action('login_head', 'my_login_logo');

// redefine url da logo na página /wp-admin
function my_login_logo_url()
{
    return get_bloginfo('url');
}

add_filter('login_headerurl', 'my_login_logo_url');

// redefine título da logo na página /wp-admin
function my_login_logo_url_title()
{
    return get_bloginfo('name');
}

add_filter('login_headertitle', 'my_login_logo_url_title');

###############################################
# PAINEL - /wp-admin
###############################################

// customiza o footer do painel de admin
function remove_footer_admin()
{
    echo '2016 © Bahia.Ba';
}

add_filter('admin_footer_text', 'remove_footer_admin');

function wps_admin_bar()
{
    global $wp_admin_bar;
    $wp_admin_bar->remove_menu('wp-logo');
    $wp_admin_bar->remove_menu('about');
    $wp_admin_bar->remove_menu('wporg');
    $wp_admin_bar->remove_menu('documentation');
    $wp_admin_bar->remove_menu('support-forums');
    $wp_admin_bar->remove_menu('feedback');
    $wp_admin_bar->remove_menu('view-site');
}

add_action('wp_before_admin_bar_render', 'wps_admin_bar');

function strip_tags_caption($text)
{
    $cont = preg_replace("/\[caption.*\[\/caption\]/", '', $text);
    $cont = preg_replace("/\[video.*\[\/video\]/", '', $cont);
    return $cont;
}

add_filter('onesignal_send_notification', 'onesignal_send_notification_filter', 10, 4);
function onesignal_send_notification_filter($fields, $new_status, $old_status, $post)
{
    /* Goal: We don't want to modify the original $fields array, because we want the original web push notification to go out unmodified. However, we want to send an additional notification to Android and iOS devices with an additionalData property.
	 *     */
    $fields_dup = $fields;
    $fields_dup['isAndroid'] = true;
    $fields_dup['isIos'] = true;
    $fields_dup['isAnyWeb'] = true;
    // $fields_dup['android_channel_id'] = "<CHANNEL ID UUID HERE>";
    $fields_dup['data'] = array("customkey" => $fields['url']);
    /* Important to set web_url to support opening through both mobile and browser*/
    $fields_dup['web_url'] = $fields_dup['url'];
    /* Important to unset the URL to prevent opening the browser when the notification is clicked for mobile app users */
    unset($fields_dup['url']);
    $onesignal_post_url = "https://onesignal.com/api/v1/notifications";
    /* Hopefully OneSignal::get_onesignal_settings(); can be called outside of the plugin */
    $onesignal_wp_settings = OneSignal::get_onesignal_settings();
    $onesignal_auth_key = $onesignal_wp_settings['app_rest_api_key'];
    $request = array("headers" => array("content-type" => "application/json;charset=utf-8", "Authorization" => "Basic " . $onesignal_auth_key), "body" => json_encode($fields_dup), "timeout" => 60);
    $response = wp_remote_post($onesignal_post_url, $request);
    if (is_wp_error($response) || !is_array($response) || !isset($response['body'])) {
        $status = $response->get_error_code();
        $error_message = $response->get_error_message();
        error_log("There was a " . $status . " error returned from OneSignal when sending to mobile users: " . $error_message);
        return;
    }
    return $fields;
}

/* EXCERPT */
function resumo($num, $str)
{
    $str = wp_strip_all_tags($str);
    $str = strip_tags_caption($str);
    $str = trim($str);
    $str = preg_replace('/&nbsp;/', '', $str, 1);

    if (strlen($str) > $num) { // Se a string for maior que o número a ser cropado
        // Corta string na quantidade dada
        $tmpstr = substr($str, 0, $num);
        $excerpt = explode(' ', $tmpstr); // Gera array com as palavras

        array_pop($excerpt); // Tiro o último item do array
        $excerpt = implode(" ", $excerpt) . " [...]";
        echo $excerpt;
    } else {
        echo $str;
    }
}

function resumo2($num, $str)
{
    $str = wp_strip_all_tags($str);
    $str = strip_tags_caption($str);
    $str = trim($str);
    $str = preg_replace('/&nbsp;/', '', $str, 1);

    if (strlen($str) > $num) { // Se a string for maior que o número a ser cropado
        // Corta string na quantidade dada
        $tmpstr = substr($str, 0, $num);
        $excerpt = explode(' ', $tmpstr); // Gera array com as palavras

        array_pop($excerpt); // Tiro o último item do array
        $excerpt = implode(" ", $excerpt) . " [...]";
        return $excerpt;
    } else {
        return $str;
    }
}

/* SLIDERS HOME */
function sliders($n, $options = false)
{
    global $news_ids;
    $slides = get_option("options_slider_m{$n}");

    if ($slides) {
        $id = $slides[0];
        $fields = get_post_acf_fields($id);
        $img = isset($fields['imagem_url']) ? $fields['imagem_url'] : '';
        $ftId = $fields['imagem']['id'];

        $caption = wp_get_attachment_caption($ftId);
        $news_ids[] = $id;

        $post_type = get_post_type($id);

        $spanCategoria = "";
        if ($post_type == "especial") {
            $spanCategoria = "spanEspecial";
        }

        if ($post_type == "exclusivo") {
            $spanCategoria = "spanExclusivo";
        }
    ?>
        <figure class="chamada">
            <a href="<?= get_the_permalink($id); ?>" title="<?= $caption; ?>">
                <?php if ($img) : ?>
                    <img src="<?= $img; ?>" <?= getMedidasImagem('destaque_grande'); ?> alt="<?= $caption; ?>">
                <?php else : ?>
                    <img src="<?= bloginfo('template_url'); ?>/assets/imgs/destaque_grande/<?= $post_type; ?>.png" />
                <?php endif; ?>

                <figcaption>
                    <span class="categoria <?= $spanCategoria ?>">
                        <?php
                        echo post_label($post_type, $id);
                        ?>
                    </span>
                    <span><?php resumo(100, get_the_title($id)); ?></span>

                </figcaption>
            </a>
        </figure>

    <?php
    }
}

function getContent($texto)
{
    return apply_filters('the_content', $texto);
}

/* SEMI DESTAQUES */
function semi_destaque($n = 0, $destaques = false)
{
    global $news_ids;

    if (!$destaques) {
        $destaques = get_option('options_semi_destaques_m1');
    }

    if (isset($destaques[$n])) {

        $id = $destaques[$n];
        $fields = get_post_acf_fields($id);
        $img = isset($fields['imagem_url']) ? $fields['imagem_url'] : '';
        $ftId = $fields['imagem']['id'];

        $caption = wp_get_attachment_caption($ftId);

        $news_ids[] = $id;

        $post_type = get_post_type($id);
        $spanCategoria = "";
        if ($post_type == "especial") {
            $spanCategoria = "spanEspecial";
        }

        if ($post_type == "exclusivo") {
            $spanCategoria = "spanExclusivo";
        }
    ?>
        <figure class="chamada">
            <a href="<?= get_the_permalink($id); ?>" title="<?= $caption; ?>">

                <?php if (!empty($img)) : ?>
                    <img src="<?= $img; ?>" <?= getMedidasImagem('destaque_pequeno'); ?> alt="<?= $caption; ?>">
                <?php else : ?>
                    <img src="<?= bloginfo('template_url'); ?>/assets/imgs/destaque_pequeno/<?= $post_type; ?>.png" />
                <?php endif; ?>

                <figcaption>
                    <span class="categoria <?= $spanCategoria ?>">
                        <?php
                        echo post_label($post_type, $id);
                        ?>
                    </span>
                    <span><?php resumo(100, get_the_title($id)); ?></span>
                </figcaption>
            </a>
        </figure>
    <?php
    }

    return $destaques;
}

function semi_destaque_video($video)
{
    ?>
    <figure class="chamada">
        <iframe width="269" height="186" src="<?= $video->url; ?>" frameborder="0" allowfullscreen></iframe>
    </figure>
    <?php
}

function video($link, $iframe = false, $width = '690', $height = '390', $autoplay = 0, $controls = 0, $showinfo = 0, $modestbranding = 0)
{
    $video = explode('/', $link);
    $video = array_reverse($video);
    $matchv = array();
    $matchy = array();
    foreach ($video as $v) {
        $matchv[] = preg_match("/vimeo/i", $v);
        $matchy[] = preg_match("/youtube/i", $v);
        $matchy[] = preg_match("/youtu.be/i", $v);
    }
    //echo '$matchv = ';print_r($matchv); echo '<br />';
    //echo '$matchy = ';print_r($matchy); echo '<br />';
    $src = null;
    if (in_array("1", $matchv)) {
        $src = "https://player.vimeo.com/video/" . $video[0];
    } elseif (in_array("1", $matchy)) {
        if (strlen($video[0]) > 11) {
            $video[0] = substr($video[0], -11);
        }
        $src = "https://www.youtube.com/embed/" . $video[0];
    }

    if ($iframe) {
        return '<iframe width="' . $width . '" height="' . $height . '"

		src="' . $src . '?autoplay=' . $autoplay . '&controls=' . $controls . '&showinfo=' . $showinfo . '&modestbranding=' . $modestbranding . '"

		frameborder="0"
		modestbranding="' . $modestbranding . '"
		showinfo="' . $showinfo . '"
		controls="' . $controls . '"
		autoplay="' . $autoplay . '"
		allowfullscreen></iframe>';
    } else {
        return $src;
    }
}

function destaquesArchive($n = 0, $destaques = false, $pag = false, $showCaption = false)
{
    global $news_ids;

    if ($n == 0 or $pag != false)
        $size = 'destaque_grande';
    else
        $size = 'destaque_pequeno';

    if ($destaques) {

        if (isset($destaques[$n])) {
            $d = $destaques[$n];

            $fields = get_post_acf_fields($d->ID);
            $img = isset($fields['imagem_url']) ? $fields['imagem_url'] : '';
            $ftId = isset($fields['imagem']['id']) ? $fields['imagem']['id'] : '';

            $caption = wp_get_attachment_caption($ftId);

            $post_type = get_post_type($d->ID);

            $news_ids[] = $d->ID;
    ?>
            <figure class="chamada">
                <a href="<?= get_the_permalink($d->ID); ?>" title="<?= $caption; ?>">

                    <?php if (!empty($img)) : ?>
                        <img src="<?= $img; ?>" <?= getMedidasImagem($size); ?> alt="<?= $caption; ?>">

                    <?php else : ?>
                        <img src="<?= bloginfo('template_url'); ?>/assets/imgs/<?= $size ?>/<?= $post_type; ?>.png" />
                    <?php endif; ?>

                    <figcaption>
                        <?php if ($showCaption) : ?>
                            <span class="categoria">
                                <?= post_label($post_type, $d->ID); ?>
                            </span>
                        <?php endif; ?>
                        <span><?php resumo(100, $d->post_title); ?></span>
                    </figcaption>
                </a>
            </figure>
        <?php
        }
    }
}

function destaquesEntrenimento($n = 0, $destaques = false, $pag = false)
{
    global $news_ids;

    if ($n == 0 or $pag != false)
        $size = 'destaque_grande';
    else
        $size = 'destaque_pequeno';

    if ($destaques) {

        if (isset($destaques[$n])) {
            $id = $destaques[$n];

            $fields = get_post_acf_fields($id);
            $img = isset($fields['imagem_url']) ? $fields['imagem_url'] : '';
            $ftId = isset($fields['imagem']['id']) ? $fields['imagem']['id'] : '';
            $caption = wp_get_attachment_caption($ftId);
            $post_type = get_post_type($id);

            $news_ids[] = $id;

        ?>
            <figure class="chamada">
                <a href="<?= get_the_permalink($id); ?>" title="<?= $caption; ?>">

                    <?php if (!empty($img)) : ?>
                        <img src="<?= $img; ?>" <?= getMedidasImagem($size); ?> alt="<?= $caption; ?>">

                    <?php else : ?>
                        <img src="<?= bloginfo('template_url'); ?>/assets/imgs/<?= $size ?>/<?= $post_type; ?>.png" />
                    <?php endif; ?>

                    <figcaption>
                        <span class="categoria">
                            <?= obterTituloEntretenimento($id); ?>
                        </span>
                        <span><?php resumo(100, get_the_title($id)); ?></span>
                    </figcaption>

                </a>
            </figure>
        <?php
        }
    }
}

function destaquesEsporte($n = 0, $destaques = false, $pag = false)
{
    global $news_ids;

    if ($n == 0 or $pag != false)
        $size = 'destaque_grande';
    else
        $size = 'destaque_pequeno';

    if ($destaques) {

        if (isset($destaques[$n])) {
            $id = $destaques[$n];

            $fields = get_post_acf_fields($id);
            $img = isset($fields['imagem_url']) ? $fields['imagem_url'] : '';
            $ftId = isset($fields['imagem']['id']) ? $fields['imagem']['id'] : '';
            $caption = wp_get_attachment_caption($ftId);
            $post_type = get_post_type($id);
            $news_ids[] = $id;

        ?>
            <figure class="chamada">
                <a href="<?= get_the_permalink($id); ?>" title="<?= $caption; ?>">

                    <?php if (!empty($img)) : ?>
                        <img src="<?= $img; ?>" <?= getMedidasImagem($size); ?> alt="<?= $caption; ?>">

                    <?php else : ?>
                        <img src="<?= bloginfo('template_url'); ?>/assets/imgs/<?= $size ?>/<?= $post_type; ?>.png" />
                    <?php endif; ?>

                    <figcaption>
                        <span class="categoria">
                            <?= obterTituloEsporte($id); ?>
                        </span>
                        <span><?php resumo(100, get_the_title($id)); ?></span>
                    </figcaption>

                </a>
            </figure>
    <?php
        }
    }
}

function obterQueryPostsPorPostType($postType, $limit = 5, $taxonomy = null, $category = null)
{
    $args = array(
        'showposts' => $limit,
        $taxonomy => $category,
        'post_status' => 'publish',
        'orderby' => 'post_date',
        'order' => 'DESC',
        'no_found_rows' => true,
        'ignore_sticky_posts' => true
    );

    if ($postType == 'exclusivo') {
        $args['post_type'] = 'any';
        $args['meta_key'] = 'exclusivo';
        $args['meta_value'] = 1;
    } else {
        $args['post_type'] = $postType;
    }

    $query = new WP_Query($args);

    return $query->posts;
}

add_filter('posts_groupby', function ($groupby) { return ''; });

add_filter('asl_result_image_after_prostproc', 'asl_image_from_cf_array', 1, 2);
function asl_image_from_cf_array($img_url, $post_id)
{
    $img = get_field('imagem', $post_id);

    if (is_array($img) && count($img) > 0) {
        $img_url = $img['sizes']['thumbnail'];
    }

    return $img_url;
}

function change_post_type_labels()
{
    global $wp_post_types;

    // Get the post labels
    $economia = $wp_post_types['economia'];
    $economia->label = 'Economia';
    $economia->labels->name = 'Economia';
    $economia->labels->singular_name = 'Economia';
}

add_action('init', 'change_post_type_labels');

function obterCorPostType($post_type)
{
    if ($post_type == "exclusivo") {
        return "txtPostTypeExclusivo";
    } elseif ($post_type == "especial") {
        return "txtPostTypeEspecial";
    } elseif ($post_type == "entrevista") {
        return "txtPostTypeEntrevistas";
    }

    return "txtPostType";
}

function obterTextoPostType($post_type)
{
    $post_type = get_post_type_object($post_type);
    return $post_type->label;
}

function obterTituloEntretenimento($postId)
{
    $objCategoria = get_the_terms($postId, "entretenimento_cat");
    $nomeCategoria = "Entretenimento";

    if ($objCategoria) {
        foreach ($objCategoria as $categoria) {
            $nomeCategoria = $categoria->name;
        }
    }

    return $nomeCategoria;
}

function obterTituloMunicipios($postId)
{
    $municipio = get_post_meta($postId, 'municipio', true);
    return $municipio;
}

function obterTituloMotor($postId)
{
    $objCategoria = get_the_terms($postId, "motor_cat");
    $nomeCategoria = "Motor";
    if ($objCategoria) {
        foreach ($objCategoria as $categoria) {
            $nomeCategoria = $categoria->name;
        }
    }

    return $nomeCategoria;
}

function obterTituloEsporte($postId)
{
    $objCategoria = get_the_terms($postId, "esporte_cat");
    $nomeCategoria = "Esporte";
    if ($objCategoria) {
        foreach ($objCategoria as $categoria) {
            $nomeCategoria = $categoria->name;
        }
    }

    return $nomeCategoria;
}

function embedVideo($url)
{
    return "https://www.youtube.com/embed/" . $url;
}

function getMedidasImagem($size)
{
    // Garante que $size seja uma string
    if (!is_string($size)) {
        $size = 'news_home';
    }

    switch ($size) {
        case 'news_home':
            $measures = 'width="345" height="240"';
            break;
        case 'destaque_grande':
            $measures = 'width="538" height="374"';
            break;
        case 'destaque_pequeno':
            $measures = 'width="269" height="187"';
            break;

        default:
            $measures = 'width="345" height="240"';
            break;
    }

    return $measures;
}

function getNamePostType($postType)
{
    switch ($postType) {
        case "artigo":
            $name = "Artigo";
            break;
        case "bahia":
            $name = "Bahia";
            break;
        case "municipios":
            $name = "Municípios";
            break;
        case "brasil":
            $name = "Brasil";
            break;
        case "carnaval2016":
            $name = "Carnaval 2016";
            break;
        case "carnaval2017":
            $name = "Carnaval 2017";
            break;
        case "carnaval2018":
            $name = "Carnaval 2018";
            break;
        case "carnaval2019":
            $name = "Carnaval 2019";
            break;
        case "carnaval":
            $name = "Carnaval";
            break;
        case "eleicoes2018":
            $name = "Eleições 2018";
            break;
        case "economia":
            $name = "Economia";
            break;
        case "empregos_concursos":
            $name = "Empregos e Concursos";
            break;
        case "entretenimento":
            $name = "Entretenimento";
            break;
        case "esporte":
            $name = "Esporte";
            break;
        case "gente":
            $name = "Gente";
            break;
        case "justica":
            $name = "Justiça";
            break;
        case "meioambiente":
            $name = "Meio Ambiente";
            break;
        case "mundo":
            $name = "Mundo";
            break;
        case "eleicoes2016":
            $name = "Eleições 2016";
            break;
        case "motor":
            $name = "Motor";
            break;
        case "mais_noticias":
            $name = "Mais Notícias";
            break;
        case "politica":
            $name = "Política";
            break;
        case "saude":
            $name = "Saúde";
            break;
        case "servicos_cidadania":
            $name = "Serviços e Cidadania";
            break;
        case "salvador":
            $name = "Salvador";
            break;
        case "ssa":
            $name = "Salvador";
            break;
        case "vida_digital":
            $name = "Vida Digital";
            break;
        case "pipocou":
            $name = "Pipocou";
            break;
        case "entrevista":
            $name = "Entrevista";
            break;
        case "exclusivo":
            $name = "Exclusivo";
            break;
        case "especial":
            $name = "Especial";
            break;
        default:
            $name = "Mundo";
            break;
    }

    return $name;
}

function obterAssinaturaColunista($idUser, $nameUser)
{
    $objAvatar = get_user_meta($idUser, 'foto', true);
    $img = wp_get_attachment_image_src($objAvatar);
    ?>
    <div class="ui divider" style="margin-top: 30px;"></div>
    <div id="author-info" class="ui items">
        <div class="item">
            <a class="ui small image">
                <img src="<?= $img[0] ?>" style="border-radius: 50%; width: 150px; height: 150px;">
            </a>
            <div class="content">
                <a class="header"><?= $nameUser ?></a>
                <div class="author-info-description">
                    <?php
                        if (get_the_author_meta('description')) {
                            echo get_the_author_meta('description');
                        }
                    ?>
                </div>
            </div>
        </div>
    </div>
<?php
}

function obterSubtitulo($fields)
{
    $subtitulo = isset($fields['subtitulo']) ? $fields['subtitulo'] : '';
    return resumo2(200, $subtitulo);
}

function showLinePostWeb($postId, $fields, $divChamada = null, $exclusivo = false, $editoria = false)
{
    $img = isset($fields['imagem_url']) ? $fields['imagem_url'] : '';

    // Se exclusivo não foi passado, obtém do campo ACF
    if ($exclusivo === false) {
        $exclusivo = isset($fields['exclusivo']) ? $fields['exclusivo'] : false;
    }

    // Garante que $img seja uma string
    if (!is_string($img)) {
        $img = '';
    }

    // Garante que $divChamada seja uma string
    if (!is_string($divChamada)) {
        $divChamada = '';
    }

    $post_author = get_post_field('post_author', $postId);
    $post_title = get_the_title($postId);
    $permalink = get_permalink($postId);
    $post_type = get_post_type($postId);
    $post_date = get_the_date('H\hi \d\e d/m/Y', $postId);
?>
    <li class="li-home <?php echo $divChamada; ?>" id="<?= $postId ?>">
        <a aria-label="post" href="<?= $permalink; ?>">
            <?php if (!empty($img)) : ?>
                <figure><img alt="<?= $post_title; ?>" src="<?= $img; ?>" <?= getMedidasImagem('news_home'); ?>></figure>
            <?php endif; ?>
            <div class="item <?= obterCorPostType($post_type, $post_author); ?>" style="padding-top: 10px; <?php if (!$editoria || $exclusivo) {
                                                                                                                echo 'padding-bottom: 10px;';
                                                                                                            } ?>">
                <?php
                if (!$editoria) {
                    if ($post_type == "entretenimento") {
                        echo obterTituloEntretenimento($postId);
                    } elseif ($post_type == "municipios") {
                        echo $fields['municipio'];
                    } elseif ($post_type == "motor") {
                        echo obterTituloMotor($postId);
                    } elseif ($post_type == "esporte") {
                        echo obterTituloEsporte($postId);
                    } else {
                        echo obterTextoPostType($post_type);
                    }
                }
                ?>
                <?php if ($exclusivo) { ?>
                    <div class="ui red label" style="<?php if (!$editoria) {
                                                            echo 'margin-left: 5px;';
                                                        } ?>">EXCLUSIVO</div>
                <?php } ?>
            </div>
            <p class="dataPostHome"><?= $post_date; ?></p>
            <p class="txtTitleHome"><?php resumo(100, $post_title); ?></p>
            <p class="txtSubtitleHome"><?= obterSubtitulo($fields); ?></p>
            <div id="div-share" style="padding-left: 0px; padding-top: 5px; padding-bottom: 30px;">
                <a href="#/" class="twitter" title="Twitter" onclick="window.open('https://twitter.com/intent/tweet?url='+encodeURIComponent('<?= $permalink ?>')+'&text=<?php resumo(80, $post_title); ?>', '', 'toolbar=no, location=no, status=no, menubar=no, scrollbars=no, resizable=no, top=200, left='+Math.round((screen.width / 2) - (550 / 2))+', width=550, height=450');"><img id="img_twitter_<?= $postId ?>" style="width: 25px; height: 25px;" src="<?php bloginfo('template_url'); ?>/assets/imgs/img_twitter_big.png" alt="Twitter" /></a>
            </div>
        </a>
    </li>
<?php
}

function showLinePostMobile($postId, $fields, $divChamada = null, $spanCategoria, $exclusivo = false, $editoria = false, $destaque = false)
{
    $img = isset($fields['imagem_url']) ? $fields['imagem_url'] : '';

    // Se exclusivo não foi passado, obtém do campo ACF
    if ($exclusivo === false) {
        $exclusivo = isset($fields['exclusivo']) ? $fields['exclusivo'] : false;
    }

    $subtitulo = isset($fields['subtitulo']) ? $fields['subtitulo'] : '';

    // Garante que $spanCategoria seja uma string
    if (!is_string($spanCategoria)) {
        $spanCategoria = '';
    }

    $post_title = get_the_title($postId);
    $permalink = get_permalink($postId);
    $post_type = get_post_type($postId);
    $post_date = get_the_date('H\hi \d\e d/m/Y', $postId);
?>
    <li class="li-home <?php echo $divChamada; ?>" id="<?= $postId ?>">
        <?php if (!$editoria) { ?>
            <span class="categoriaMobile <?= $spanCategoria ?>">
                <?php echo post_label($post_type, $postId); ?>
            </span>
        <?php } ?>

        <?php if (!$editoria) { ?>
            <span class="infoPost2" style="margin-top: 8px;"><?php if ($post_type != "especial" && $post_type != "entrevista" && !$destaque) {
                                                                    echo $post_date;
                                                                } ?></span>
        <?php } ?>

        <a href="<?= $permalink; ?>">
            <?php if (!empty($img)) : ?>
                <figure style="width: 100%"><img src="<?= $img; ?>" alt="<?= $post_title; ?>" <?= getMedidasImagem('news_home'); ?> style="width: 100% !important;"></figure>
            <?php endif; ?>

            <?php if ($exclusivo) { ?>
                <div class="ui red label" style="margin: 10px 0px 0px 10px;">EXCLUSIVO</div>
            <?php } ?>

            <div class="clearfix" style="<?php if (!empty($divChamada)) {
                                                echo "padding: 0 20px;";
                                            } ?>">
                <?php if ($editoria) { ?>
                    <p class="dataPost" style="margin-top: 10px;"><?= $post_date; ?></p>
                <?php } ?>
                <p class="data-publicacao"></p>
                <h2 class="call-chamada"><?php resumo(100, $post_title); ?></h2>
                <?php if ($subtitulo == "") : ?>
                    <p><?php resumo(170, get_the_excerpt()); ?></p>
                <?php else : ?>
                    <p><?php resumo(170, $subtitulo); ?></p>
                <?php endif; ?>
            </div>
        </a>
    </li>
<?php
}


function printDateSearch($date)
{
?>
    <div>
        <div class="ui label dateSearch">
            <?= $date ?>
        </div>
    </div>
<?php
}

function obterDestaqueMaiorMobile()
{
    $destaque = get_option("options_slider_m1", 'options');
?>

    <div class="filtros-resultado-busca">
        <a href="#" class="ativo">DESTAQUE</a>
    </div>

    <ul class="resultado-busca">
        <?php
        if ($destaque):
            global $news_ids;
            $news_ids[] = $id = $destaque[0];
            $post_type = get_post_type($id);
            $fields = get_post_acf_fields($id);
            $exclusivo = isset($fields['exclusivo']) ? $fields['exclusivo'] : false;

            $divChamada = "";
            $spanCategoria = "";
            if ($post_type == "especial" || $post_type == "exclusivo" || $exclusivo) {
                $divChamada = "divChamadaPostTypeMobile";
            }

            if ($post_type == "especial") {
                $spanCategoria = "spanEspecial";
            }

            if ($post_type == "exclusivo") {
                $spanCategoria = "spanExclusivo";
            }

            if ($post_type == "motor") {
                $spanCategoria = "categoriaMotor";
            }

            showLinePostMobile($id, $fields, $divChamada, $spanCategoria, $exclusivo, false, true);

        else:
        ?>
            <li>
                <p class="call-chamada">Sua busca não trouxe resultados.</p>
            </li>
        <?php endif; ?>
    </ul>

    <?php
}


function post_label($post_type, $id)
{
    if ($post_type == "entretenimento") {
        return obterTituloEntretenimento($id);
    } elseif ($post_type == "municipios") {
        return obterTituloMunicipios($id);
    } elseif ($post_type == "motor") {
        return obterTituloMotor($id);
    } elseif ($post_type == "esporte") {
        return obterTituloEsporte($id);
    } else {
        return obterTextoPostType($post_type);
    }
}


function mais_lidas($limit = 7, $type = false)
{
    global $wpdb;

    $dataInicial = date('Y-m-d 00:00:00', strtotime("-2 days"));
    $sql = "SELECT
                            DISTINCT p.*, (meta_value+0) AS views FROM wp_posts p
                    INNER JOIN wp_postmeta pm ON pm.post_id = p.ID
                    WHERE
                            post_status = 'publish'
                            AND meta_key = 'views'
                            AND post_password = ''
                            AND post_date BETWEEN '" . $dataInicial . "' AND now() ";

    if (!$type) $sql .= "AND p.post_type NOT IN ('page','attachment','acf') ";
    else $sql .= "AND p.post_type = '{$type}' ";

    $sql .= " ORDER BY views DESC
                      LIMIT {$limit} ";

    $query = $wpdb->get_results($sql);

    $n_post = 0;
    foreach ($query as $q) : $n_post++;
        //setup_postdata( $q );
        $author = get_userdata($q->post_author);
    ?>
        <div class="item-popular" data-comments="<?= get_comments_number($q->ID); ?>">
            <div class="chamada-popular">
                <p><a href="<?= get_the_permalink($q->ID); ?>"><?php resumo(70, get_the_title($q->ID)); ?></a></p>
                <p class="author"><?= $author->display_name; ?> | <?php echo get_the_date('d/m/Y \à\s H\hi', $q->ID); ?></p>
            </div>
        </div>
    <?php
    endforeach;
    wp_reset_query();
}

function mais_lidas2($limit = 7, $type = false)
{
    global $wpdb;

    $dataInicial = date('Y-m-d 00:00:00', strtotime("-2 days"));
    $sql = "SELECT
                            DISTINCT p.*, (meta_value+0) AS views FROM wp_posts p
                    INNER JOIN wp_postmeta pm ON pm.post_id = p.ID
                    WHERE
                            post_status = 'publish'
                            AND meta_key = 'views'
                            AND post_password = ''
                            AND post_date BETWEEN '" . $dataInicial . "' AND now() ";

    if (!$type) $sql .= "AND p.post_type NOT IN ('page','attachment','acf') ";
    else $sql .= "AND p.post_type = '{$type}' ";

    $sql .= " ORDER BY views DESC
                      LIMIT {$limit} ";

    $query = $wpdb->get_results($sql);

    $n_post = 0;
    foreach ($query as $q) : $n_post++;
    ?>
        <div class="item-popular">
            <div class="chamada-popular">
                <p><a href="<?= get_the_permalink($q->ID); ?>"><?php resumo(100, get_the_title($q->ID)); ?></a></p>
            </div>
        </div>
        <?php
    endforeach;
    //wp_reset_query();
}

function mais_noticias_editoria($limit = 7, $type = false)
{
    global $news_ids;

    $args = array(
        'post_type' => array('mais_noticias'),
        'showposts' => $limit,
        'no_found_rows' => true,
        'ignore_sticky_posts' => true
    );

    $query = new WP_Query($args);

    $n_post = 0;
    for ($i = 0; $i < count($query->posts) && $i < $limit; $i++) {
        if (! in_array($query->posts[$i]->ID, $news_ids)) {
            $n_post++;
        ?>
            <div class="item-popular">
                <div class="chamada-popular">
                    <p><a href="<?= get_the_permalink($query->posts[$i]->ID); ?>"><?php resumo(100, get_the_title($query->posts[$i]->ID)); ?></a></p>
                </div>
            </div>

<?php
        }
    }
    //wp_reset_postdata();
}


// get taxonomies terms links
function tags()
{
    global $post;
    $post_type = $post->post_type;

    $taxonomies = get_object_taxonomies($post_type, 'objects');
    //print_r($taxonomies);

    $out = array();
    foreach ($taxonomies as $taxonomy_slug => $taxonomy) {

        if (substr($taxonomy_slug, -4) == '_tag') {
            // get the terms related to post
            $terms = get_the_terms($post->ID, $taxonomy_slug);
            //print $taxonomy_slug."\n";
            //print_r($terms);

            if (!empty($terms)) {

                foreach ($terms as $term) {
                    /*
	        $out[] =
	          '  <a href="'
	        .    get_term_link( $term->slug, $taxonomy_slug ) .'">'
	        .    $term->name
	        . "</a>\n";
	        */
                    $out[] = "<a href='/?s={$term->slug}'>{$term->name}</a>\n";
                }
            }
        } // substr

    } // foreach

    return implode(', ', $out);
}




function data_post($post_id, $sep = false, $choose_date = false, $format = 'd/m/Y \à\s H\hi')
{
    // pega datas do post
    $date = get_the_date($format, $post_id);
    $modified_date = get_the_modified_date($format, $post_id);

    // compara se atualização é anterior à publicação
    if (strtotime(get_the_modified_date('Y-m-d H:i:s', $post_id)) < strtotime(get_the_date('Y-m-d H:i:s', $post_id)))
        $modified_date = $date;

    // imprime publicação
    echo "Publicado em {$date}. ";

    if (get_post_meta($post_id, 'exibir_atualizacao', true) == true) {
        // imprime atualização
        if ($date != $modified_date)
            echo $sep . "Atualizado em {$modified_date}.";
    }
}


// renomeia URL author ara COLUNISTAS
add_action('init', function () {
    global $wp_rewrite;
    $wp_rewrite->author_base = "colunistas";
    $wp_rewrite->flush_rules();
});

function is_mobile()
{
    $detect = new Mobile_Detect;

    return $detect->isMobile() || $detect->isAndroidOS() || $detect->isiOS();
}

function getUserAgent()
{
    return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "";
}

function turn_off_feed()
{
    wp_die(__('No feed available,please visit our <a href="' . get_bloginfo('url') . '">homepage</a>!'));
}

add_action('do_feed', 'turn_off_feed', 1);
add_action('do_feed_rdf', 'turn_off_feed', 1);
add_action('do_feed_rss', 'turn_off_feed', 1);
add_action('do_feed_rss2', 'turn_off_feed', 1);
add_action('do_feed_atom', 'turn_off_feed', 1);
add_action('do_feed_rss2_comments', 'turn_off_feed', 1);
add_action('do_feed_atom_comments', 'turn_off_feed', 1);

remove_action('wp_head', 'feed_links', 2); // remove rss feed links (make sure you add them in yourself if youre using feedblitz or an rss service)
remove_action('wp_head', 'feed_links_extra', 3); // removes all extra rss feed links

add_action('init', 'customRSS');
function customRSS()
{
    add_feed('feedbahiaba', 'customRSSFunc');
}

function customRSSFunc()
{
    get_template_part('rss', 'feedbahiaba');
}

function rssLanguage() {
    update_option('rss_language', 'pt-BR');
}
add_action('admin_init', 'rssLanguage');

function bahia_theme_enqueue_assets()
{
    $theme_uri = get_template_directory_uri();
    $theme_version = '1.0.2';

    wp_enqueue_script(
        'bahia-theme-script',
        $theme_uri . '/dist/js/theme.min.js',
        [], // Sem dependências - jQuery está bundled
        $theme_version,
        true // Carrega no footer
    );

    // jQuery Timing (plugin legado)
    wp_enqueue_script(
        'jquery-timing',
        $theme_uri . '/dist/js/jquery-timing.min.js',
        ['bahia-theme-script'], // Depende do theme.min.js (que tem jQuery)
        $theme_version,
        true
    );

    // Base.js (scripts personalizados legados)
    wp_enqueue_script(
        'bahia-base',
        $theme_uri . '/dist/js/base.js',
        ['bahia-theme-script', 'jquery-timing'], // Depende de ambos
        $theme_version,
        true
    );

    // Passa dados para o JavaScript
    global $POST_TYPES_LIST;
    wp_localize_script('bahia-theme-script', 'bahiaThemeData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('bahia_theme_nonce'),
        'infiniteScrollNonce' => wp_create_nonce('bahia_infinite_scroll'),
        'homeUrl' => home_url('/'),
        'themeUrl' => $theme_uri,
        'postTypesList' => $POST_TYPES_LIST, // Lista de post types para a home
    ]);
}
add_action('wp_enqueue_scripts', 'bahia_theme_enqueue_assets');

function add_essential_meta_tags()
{
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
    echo '<meta name="format-detection" content="telephone=no">' . "\n";
}

add_action('wp_head', 'add_essential_meta_tags', 1);

/**
 * Função para verificar se estamos em modo de desenvolvimento Vite
 */
function is_vite_dev_mode()
{
    return defined('WP_DEBUG') && WP_DEBUG && isset($_GET['vite_dev']);
}

/**
 * Adiciona comentário no HTML indicando se é build de produção ou desenvolvimento
 */
function add_build_info()
{
    if (is_vite_dev_mode()) {
        echo "\n<!-- Modo desenvolvimento Vite ativo -->\n";
    } else {
        echo "\n<!-- Build de produção Vite -->\n";
    }
}
add_action('wp_head', 'add_build_info');

function get_post_acf_fields($post_id) {
    static $cache = array();

    // Valida post_id
    $post_id = absint($post_id);
    if ($post_id === 0) {
        return array();
    }

    // Retorna do cache se já foi carregado
    if (isset($cache[$post_id])) {
        return $cache[$post_id];
    }

    // Inicializa array de campos
    $fields = array();

    // Tenta carregar com ACF primeiro
    if (function_exists('get_fields')) {
        $acf_fields = get_fields($post_id);
        if (is_array($acf_fields) && !empty($acf_fields)) {
            $fields = $acf_fields;
        }
    }

    // Fallback: carrega campos específicos necessários
    if (empty($fields)) {
        $meta_keys = array('subtitulo', 'imagem', 'exclusivo', 'video', 'tipo', 'views', 'municipio', 'exibir_atualizacao');

        foreach ($meta_keys as $key) {
            $value = get_post_meta($post_id, $key, true);
            $fields[$key] = $value !== '' ? $value : null;
        }
    }

    // Processa campo de imagem
    $fields['imagem_url'] = '';

    if (!empty($fields['imagem'])) {
        $image_id = null;

        // Determina o ID da imagem
        if (is_array($fields['imagem']) && isset($fields['imagem']['id'])) {
            $image_id = absint($fields['imagem']['id']);
        } elseif (is_array($fields['imagem']) && isset($fields['imagem']['ID'])) {
            $image_id = absint($fields['imagem']['ID']);
        } elseif (is_numeric($fields['imagem'])) {
            $image_id = absint($fields['imagem']);
        } elseif (is_string($fields['imagem']) && filter_var($fields['imagem'], FILTER_VALIDATE_URL)) {
            // Já é uma URL válida
            $fields['imagem_url'] = $fields['imagem'];
        }

        // Obtém URL da imagem se temos um ID
        if ($image_id && empty($fields['imagem_url'])) {
            // Tenta usar a função do S3 primeiro
            if (function_exists('as3cf_get_attachment_url')) {
                $url = as3cf_get_attachment_url($image_id);
                if (is_string($url) && !empty($url)) {
                    $fields['imagem_url'] = $url;
                }
            }

            // Fallback para URL padrão do WordPress
            if (empty($fields['imagem_url'])) {
                $url = wp_get_attachment_url($image_id);
                if (is_string($url) && !empty($url)) {
                    $fields['imagem_url'] = $url;
                }
            }
        }
    }

    // Normaliza tipos de dados
    $fields['subtitulo'] = isset($fields['subtitulo']) && is_string($fields['subtitulo']) ? $fields['subtitulo'] : '';
    $fields['exclusivo'] = !empty($fields['exclusivo']) && $fields['exclusivo'] !== '0';
    $fields['views'] = isset($fields['views']) ? absint($fields['views']) : 0;
    $fields['municipio'] = isset($fields['municipio']) && is_string($fields['municipio']) ? $fields['municipio'] : '';
    $fields['exibir_atualizacao'] = !empty($fields['exibir_atualizacao']);

    // Armazena em cache
    $cache[$post_id] = $fields;

    return $fields;
}

function preload_post_acf_fields($post_ids) {
    if (empty($post_ids) || !is_array($post_ids)) {
        return;
    }

    // Remove posts já em cache
    static $cache = array();
    $post_ids = array_diff($post_ids, array_keys($cache));

    if (empty($post_ids)) {
        return;
    }

    // Pré-carrega meta em uma única query
    update_meta_cache('post', $post_ids);

    // Carrega campos ACF para cada post
    foreach ($post_ids as $post_id) {
        get_post_acf_fields($post_id);
    }
}

/**
 * Handler AJAX - USA APENAS exclude_ids (SEM offset)
 */
function bahia_infinite_scroll_handler() {
    if (!check_ajax_referer('bahia_infinite_scroll', 'nonce', false)) {
        wp_send_json_error(array('message' => 'Nonce inválido'));
        return;
    }

    // Parâmetros
    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
    $posts_per_page = isset($_POST['posts_per_page']) ? absint($_POST['posts_per_page']) : 10;
    $exclude_ids_str = isset($_POST['exclude_ids']) ? sanitize_text_field($_POST['exclude_ids']) : '';
    $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';
    $term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
    $is_mobile = isset($_POST['is_mobile']) && $_POST['is_mobile'] === 'true';
    $is_multi_post_type = isset($_POST['is_multi_post_type']) && $_POST['is_multi_post_type'] === 'true';

    // Processa IDs para excluir
    $exclude_ids = array();
    if (!empty($exclude_ids_str)) {
        $exclude_ids = array_map('absint', explode(',', $exclude_ids_str));
        $exclude_ids = array_filter($exclude_ids);
    }

    error_log('=== INFINITE SCROLL ===');
    error_log('Posts per page: ' . $posts_per_page);
    error_log('Exclude IDs count: ' . count($exclude_ids));

    // Valida post_type
    global $POST_TYPES;

    if (strpos($post_type, ',') !== false) {
        $post_types = array_map('trim', explode(',', $post_type));
        $post_types = array_intersect($post_types, $POST_TYPES);
        if (empty($post_types)) {
            wp_send_json_error(array('message' => 'Post types inválidos'));
            return;
        }
        $post_type = $post_types;
    } else {
        if (!in_array($post_type, $POST_TYPES)) {
            wp_send_json_error(array('message' => 'Post type inválido'));
            return;
        }
    }

    $posts_per_page = min($posts_per_page, 20);

    // Query args - SEM OFFSET, APENAS post__not_in
    $args = array(
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => $posts_per_page,
        'orderby' => 'post_date',
        'order' => 'DESC',
        'no_found_rows' => false,
        'ignore_sticky_posts' => true,
        'update_post_meta_cache' => true,
        'update_post_term_cache' => true,
    );

    // Adiciona exclusão de IDs
    if (!empty($exclude_ids)) {
        $args['post__not_in'] = $exclude_ids;
    }

    // Taxonomia
    $allowed_taxonomies = array('entretenimento_cat', 'esporte_cat', 'motor_cat');
    if (!empty($taxonomy) && in_array($taxonomy, $allowed_taxonomies) && $term_id > 0) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => $taxonomy,
                'field' => 'term_id',
                'terms' => $term_id,
            )
        );
    }

    // Executa query
    $query = new WP_Query($args);

    error_log('Posts encontrados: ' . $query->post_count);

    // Total (sem exclusões)
    $count_args = array_diff_key($args, array('posts_per_page' => '', 'post__not_in' => ''));
    $count_args['posts_per_page'] = -1;
    $count_args['fields'] = 'ids';
    $count_query = new WP_Query($count_args);
    $total_posts = $count_query->found_posts;

    error_log('Total disponível: ' . $total_posts);

    $response = array(
        'html' => '',
        'ids' => array(),
        'count' => 0,
        'has_more' => false,
        'total_posts' => $total_posts
    );

    if ($query->have_posts()) {
        $post_ids = wp_list_pluck($query->posts, 'ID');
        preload_post_acf_fields($post_ids);

        ob_start();

        $processed = 0;
        while ($query->have_posts() && $processed < $posts_per_page) {
            $query->the_post();
            $post_id = get_the_ID();

            // Segurança: pula se já foi excluído
            if (in_array($post_id, $exclude_ids)) {
                error_log('AVISO: Post ' . $post_id . ' está na lista de exclusão!');
                continue;
            }

            $response['ids'][] = $post_id;
            $response['count']++;
            $processed++;

            $fields = get_post_acf_fields($post_id);
            $current_post_type = get_post_type($post_id);
            $exclusivo = isset($fields['exclusivo']) ? (bool)$fields['exclusivo'] : false;

            if ($is_mobile) {
                $divChamada = "";
                $spanCategoria = "";

                if (in_array($current_post_type, array('especial', 'exclusivo')) || $exclusivo) {
                    $divChamada = "divChamadaPostTypeMobile";
                }

                if ($current_post_type == "especial") {
                    $spanCategoria = "spanEspecial";
                } elseif ($current_post_type == "exclusivo") {
                    $spanCategoria = "spanExclusivo";
                } elseif ($current_post_type == "motor") {
                    $spanCategoria = "categoriaMotor";
                }

                showLinePostMobile($post_id, $fields, $divChamada, $spanCategoria, $exclusivo, !$is_multi_post_type);
            } else {
                $divChamada = "";
                if (in_array($current_post_type, array('especial', 'exclusivo', 'entrevista')) || $exclusivo) {
                    $divChamada = "divChamadaPostType";
                }

                showLinePostWeb($post_id, $fields, $divChamada, $exclusivo, !$is_multi_post_type);
            }
        }

        $response['html'] = ob_get_clean();

        // Has more = ainda há posts não excluídos
        $total_loaded = count($exclude_ids) + $response['count'];
        $response['has_more'] = $total_loaded < $total_posts;

        error_log('Retornados: ' . implode(', ', $response['ids']));
        error_log('Total carregado até agora: ' . $total_loaded . ' / ' . $total_posts);
        error_log('Has more: ' . ($response['has_more'] ? 'yes' : 'no'));
    }

    wp_reset_postdata();
    error_log('=== FIM ===');

    wp_send_json_success($response);
}

remove_all_actions('wp_ajax_bahia_infinite_scroll');
remove_all_actions('wp_ajax_nopriv_bahia_infinite_scroll');
add_action('wp_ajax_bahia_infinite_scroll', 'bahia_infinite_scroll_handler');
add_action('wp_ajax_nopriv_bahia_infinite_scroll', 'bahia_infinite_scroll_handler');

/**
 * Adiciona target="_blank" a todos os links dentro do conteúdo dos posts
 * para que abram em uma nova guia, mantendo o leitor na matéria original
 */
function bahia_add_target_blank_to_content_links($content) {
    // Verifica se estamos em um single post
    if (!is_single()) {
        return $content;
    }

    // Cria um DOMDocument para parsear o HTML
    $dom = new DOMDocument();

    // Suprime warnings de HTML mal formado
    libxml_use_internal_errors(true);

    // Carrega o HTML (usando UTF-8)
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    // Limpa os erros
    libxml_clear_errors();

    // Encontra todos os links
    $links = $dom->getElementsByTagName('a');

    // Percorre todos os links encontrados
    foreach ($links as $link) {
        $href = $link->getAttribute('href');

        // Ignora links vazios, âncoras e javascript
        if (empty($href) || $href === '#' || strpos($href, 'javascript:') === 0) {
            continue;
        }

        // Adiciona target="_blank"
        $link->setAttribute('target', '_blank');

        // Adiciona rel="noopener noreferrer" para segurança
        // (previne que a nova página tenha acesso ao window.opener)
        $existing_rel = $link->getAttribute('rel');
        if ($existing_rel) {
            // Se já tem rel, adiciona noopener noreferrer se não existir
            $rel_values = explode(' ', $existing_rel);
            if (!in_array('noopener', $rel_values)) {
                $rel_values[] = 'noopener';
            }
            if (!in_array('noreferrer', $rel_values)) {
                $rel_values[] = 'noreferrer';
            }
            $link->setAttribute('rel', implode(' ', $rel_values));
        } else {
            $link->setAttribute('rel', 'noopener noreferrer');
        }
    }

    // Retorna o HTML modificado
    $content = $dom->saveHTML();

    // Remove a declaração XML que foi adicionada
    $content = str_replace('<?xml encoding="utf-8" ?>', '', $content);

    return $content;
}

// Adiciona o filtro ao conteúdo dos posts
add_filter('the_content', 'bahia_add_target_blank_to_content_links', 99);
