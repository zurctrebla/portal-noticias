<?php
/*add_action('init', 'unregister_wp_heartbeat', 1);

function unregister_wp_heartbeat()
{
    global $pagenow;

    if ($pagenow != 'post.php' && $pagenow != 'post-new.php' && $pagenow != 'edit.php') {
        wp_deregister_script('heartbeat');
    }
}

function adjust_heartbeat_interval($response)
{
    $response['heartbeat_interval'] = 120;
    return $response;
}

add_filter('heartbeat_send', 'adjust_heartbeat_interval');*/

// Disable use XML-RPC
add_filter('xmlrpc_enabled', '__return_false');

// Disable X-Pingback to header
add_filter('wp_headers', 'disable_x_pingback');
function disable_x_pingback($headers)
{
    unset($headers['X-Pingback']);

    return $headers;
}

define('TEMPLATE_URL', get_bloginfo('template_url'));
define('URL', get_bloginfo('url'));

@ini_set('upload_max_size', '128M');
@ini_set('post_max_size', '128M');
@ini_set('max_execution_time', '600');

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

// define timezone
//date_default_timezone_set('Brazil/East');

if( function_exists('acf_add_options_sub_page') ) {
    acf_add_options_sub_page('Home');
	acf_add_options_sub_page('Geral');
}

// remove barra de admin do topo do site
show_admin_bar(false);

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
    // 'carnaval2016',
    // 'carnaval2017',
    // 'carnaval2018',
    // 'carnaval2019',
    'carnaval',
    // 'eleicoes2016',
    // 'eleicoes2018',
    //'eleicoes2020',
    //'eleicoes2022',
    'eleicoes2024',
    'economia',
    // 'empregos_concursos',
    'entretenimento',
    'especial',
    'esporte',
    'exclusivo',
    'gente',
    'justica',
    'mais_noticias',
    // 'meioambiente',
    // 'motor',
    'municipios',
    'mundo',
    // 'pipocou',
    'politica',
    'saude',
    // 'servicos_cidadania',
    'salvador',
    // 'vida_digital',
    //'tipoassim',
    'mais_gente',
    'social',
    'investimentos',
    //'rms',
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

foreach ($relationship_acfs as $field)
    add_filter('acf/fields/relationship/query/name=' . $field, 'relationship_options_filter', 10, 3);


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
function onesignal_send_notification_filter($fields, $new_status, $old_status, $post) {
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
        $ftId = get_post_meta($id, 'imagem', true);
        $img = as3cf_get_attachment_url($ftId);
        $caption = wp_get_attachment_caption($ftId);
        $news_ids[] = $id;

        $spanCategoria = "";
        if (get_post_type($id) == "especial") {
            $spanCategoria = "spanEspecial";
        }

        if (get_post_type($id) == "exclusivo") {
            $spanCategoria = "spanExclusivo";
        }

        if ((int) get_post_field('post_author', $id) == 17 || (int) get_post_field('post_author', $id) == 58) {
            $spanCategoria = "spanLevi";
        }
    ?>

        <figure class="chamada" data-comments="<?= get_comments_number($id); ?>">
            <a href="<?= get_the_permalink($id); ?>" title="<?= $caption; ?>">
                <?php if ($img) : ?>
                    <img src="<?= $img; ?>" <?= getMedidasImagem('destaque_grande'); ?> alt="<?= $caption; ?>">
                <?php else : ?>
                    <img src="<?= bloginfo('template_url'); ?>/assets/imgs/destaque_grande/<?= get_post_type($id); ?>.png" />
                <?php endif; ?>

                <figcaption>
                    <span class="categoria <?= $spanCategoria ?>">
                        <?php
                        if ((int) get_post_field('post_author', $id) == 17 || (int) get_post_field('post_author', $id) == 58) {
                            echo "Blog do Levi";
                        } else {
                            echo post_label($id);
                        }
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
        
        $ftId = get_post_meta($id, 'imagem', true);
        $img = as3cf_get_attachment_url($ftId);
        $caption = wp_get_attachment_caption($ftId);

        $news_ids[] = $id;


        $spanCategoria = "";
        if (get_post_type($id) == "especial") {
            $spanCategoria = "spanEspecial";
        }

        if (get_post_type($id) == "exclusivo") {
            $spanCategoria = "spanExclusivo";
        }

        if ((int) get_post_field('post_author', $id) == 17 || (int) get_post_field('post_author', $id) == 58) {
            $spanCategoria = "spanLevi";
        }
    ?>
        <figure class="chamada" data-comments="<?= get_comments_number($id); ?>">
            <a href="<?= get_the_permalink($id); ?>" title="<?= $caption; ?>">

                <?php if (!empty($img)) : ?>
                    <img src="<?= $img; ?>" <?= getMedidasImagem('destaque_pequeno'); ?> alt="<?= $caption; ?>">
                <?php else : ?>
                    <img src="<?= bloginfo('template_url'); ?>/assets/imgs/destaque_pequeno/<?= get_post_type($id); ?>.png" />
                <?php endif; ?>

                <figcaption>
                    <span class="categoria <?= $spanCategoria ?>">
                        <?php
                        if ((int) get_post_field('post_author', $id) == 17 || (int) get_post_field('post_author', $id) == 58) {
                            echo "Blog do Levi";
                        } else {
                            echo post_label($id);
                        }
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

function video($link,$iframe=false,$width='690',$height='390',$autoplay=0,$controls=0,$showinfo=0,$modestbranding=0){
	$video = explode('/',$link);
	$video = array_reverse($video);
	$matchv = array();
	$matchy = array();
	foreach($video as $v){
		$matchv[] = preg_match("/vimeo/i", $v);
		$matchy[] = preg_match("/youtube/i", $v);
		$matchy[] = preg_match("/youtu.be/i", $v);
	}
	//echo '$matchv = ';print_r($matchv); echo '<br />';
	//echo '$matchy = ';print_r($matchy); echo '<br />';
	$src = null;
	if(in_array("1", $matchv)){
		$src="https://player.vimeo.com/video/".$video[0];
	}elseif(in_array("1", $matchy)){
		if(strlen($video[0]) > 11){ $video[0] = substr($video[0], -11); }
		$src="https://www.youtube.com/embed/".$video[0];
	}

	if($iframe){
		return '<iframe width="'.$width.'" height="'.$height.'" 

		src="'.$src.'?autoplay='.$autoplay.'&controls='.$controls.'&showinfo='.$showinfo.'&modestbranding='.$modestbranding.'" 
		
		frameborder="0" 
		modestbranding="'.$modestbranding.'" 
		showinfo="'.$showinfo.'" 
		controls="'.$controls.'" 
		autoplay="'.$autoplay.'" 
		allowfullscreen></iframe>';
	
	}else{
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
			
			$ftId = get_post_meta($d->ID, 'imagem', true);
			$img = as3cf_get_attachment_url($ftId);
			$caption = wp_get_attachment_caption($ftId);
			
            $news_ids[] = $d->ID;
        ?>
            <figure class="chamada" data-comments="<?= get_comments_number($d->ID); ?>">
                <a href="<?= get_the_permalink($d->ID); ?>" title="<?= $caption; ?>">

                    <?php if (!empty($img)) : ?>
                        <img src="<?= $img; ?>" <?= getMedidasImagem($size); ?> alt="<?= $caption; ?>">

                    <?php else : ?>
                        <img src="<?= bloginfo('template_url'); ?>/assets/imgs/<?= $size ?>/<?= get_post_type($d->ID); ?>.png" />
                    <?php endif; ?>

                    <figcaption>
                        <?php if ($showCaption) : ?>
                            <span class="categoria">
                                <?= post_label($d->ID); ?>
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
			
			$ftId = get_post_meta($id, 'imagem', true);
			$img = as3cf_get_attachment_url($ftId);
			$caption = wp_get_attachment_caption($ftId);
			
            $news_ids[] = $id;

        ?>
            <figure class="chamada" data-comments="<?= get_comments_number($id); ?>">
                <a href="<?= get_the_permalink($id); ?>" title="<?= $caption; ?>">

                    <?php if (!empty($img)) : ?>
                        <img src="<?= $img; ?>" <?= getMedidasImagem($size); ?> alt="<?= $caption; ?>">

                    <?php else : ?>
                        <img src="<?= bloginfo('template_url'); ?>/assets/imgs/<?= $size ?>/<?= get_post_type($id); ?>.png" />
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
			
			$ftId = get_post_meta($id, 'imagem', true);
			$img = as3cf_get_attachment_url($ftId);
			$caption = wp_get_attachment_caption($ftId);
            $news_ids[] = $id;

        ?>
            <figure class="chamada" data-comments="<?= get_comments_number($id); ?>">
                <a href="<?= get_the_permalink($id); ?>" title="<?= $caption; ?>">

                    <?php if (!empty($img)) : ?>
                        <img src="<?= $img; ?>" <?= getMedidasImagem($size); ?> alt="<?= $caption; ?>">

                    <?php else : ?>
                        <img src="<?= bloginfo('template_url'); ?>/assets/imgs/<?= $size ?>/<?= get_post_type($id); ?>.png" />
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


add_filter('posts_groupby', function ($groupby) {
    return '';
});

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

function obterCorPostType($id, $author = null)
{
    if ($author == "17" || $author == "58") {
        return "txtPostTypeLevi";
    }

    if (get_post_type($id) == "exclusivo") {
        return "txtPostTypeExclusivo";
    } elseif (get_post_type($id) == "especial") {
        return "txtPostTypeEspecial";
    } elseif (get_post_type($id) == "entrevista") {
        return "txtPostTypeEntrevistas";
    } else {
        return "txtPostType";
    }
}

function obterTextoPostType($id, $author = null)
{
    if ($author == "17" || $author == "58") {
        return "BLOG DO LEVI";
    } else {
        $post_type = get_post_type_object(get_post_type($id));
        return $post_type->label;
    }
}

function obterTituloEntretenimento($idPost)
{
    $objCategoria = get_the_terms($idPost, "entretenimento_cat");
    $nomeCategoria = "Entretenimento";

    if ($objCategoria) {
        foreach ($objCategoria as $categoria) {
            $nomeCategoria = $categoria->name;
        }
    }

    return $nomeCategoria;
}

function obterTituloMunicipios($idPost)
{
    $municipio = get_post_meta($idPost, 'municipio', true);
    return $municipio;
}

function obterTituloMotor($idPost)
{
    $objCategoria = get_the_terms($idPost, "motor_cat");
    $nomeCategoria = "Motor";
    if ($objCategoria) {
        foreach ($objCategoria as $categoria) {
            $nomeCategoria = $categoria->name;
        }
    }

    return $nomeCategoria;
}

function obterTituloEsporte($idPost)
{
    $objCategoria = get_the_terms($idPost, "esporte_cat");
    $nomeCategoria = "Esporte";
    if ($objCategoria) {
        foreach ($objCategoria as $categoria) {
            $nomeCategoria = $categoria->name;
        }
    }

    return $nomeCategoria;
}

function dataPostHome($id, $sep = false, $choose_date = false, $format = 'd/m/Y \à\s H\hi')
{
    $format = 'd/m/Y \à\s H\hi';
    $date = get_the_date('H\hi \d\e d \d\e F \d\e Y', $id);
    return $date;
}

function embedVideo($url)
{
    return "https://www.youtube.com/embed/" . $url;
}



function getMedidasImagem($size)
{
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
    <div class="ui items" style="padding-bottom: 20px;">
        <div class="item">
            <a class="ui small image">
                <img src="<?= $img[0] ?>" style="border-radius: 50%; width: 150px; height: 150px;">
            </a>
            <div class="content">
                <a class="header"><?= $nameUser ?></a>
                <div class="description">
                    <p>
                        <?php
                        if (get_the_author_meta('description')) {
                            echo get_the_author_meta('description');
                        }
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
<?php
}

function obterSubtitulo($postId)
{
    $subtitulo = get_post_meta($postId, 'subtitulo', true);

    if ($subtitulo == "") {
        $content_post = get_post($postId);
        $content = $content_post->post_content;
        $content = apply_filters('the_content', $content);
        $content = preg_replace('/<figure.*<\/figure>/', '', $content);
    } else {
        $content = $subtitulo;
    }

    return resumo2(200, $content);
}

function showLinePostWeb($idPost, $img, $divChamada, $exclusivo = false, $editoria = false)
{
?>
    <li class="li-home" id="<?= $idPost ?>">
        <a aria-label="post" href="<?= get_permalink($idPost); ?>">
            <?php if (!empty($img)) : ?>
                <figure><img alt="<?= $img; ?>" src="<?= $img; ?>" <?= getMedidasImagem('news_home'); ?>></figure>
            <?php endif; ?>

            <div class="<?= $divChamada ?>">

                <div class="item <?= obterCorPostType($idPost, get_post_field('post_author', $idPost)); ?>" style="padding-top: 10px; <?php if (!$editoria || $exclusivo) {
                                                                                                                                            echo 'padding-bottom: 10px;';
                                                                                                                                        } ?>">
                    <?php
                    if (!$editoria) {
                        if (get_post_type($idPost) == "entretenimento") {
                            echo obterTituloEntretenimento($idPost);
                        } elseif (get_post_type($idPost) == "municipios") {
                            echo obterTituloMunicipios($idPost);
                        } elseif (get_post_type($idPost) == "motor") {
                            echo obterTituloMotor($idPost);
                        } elseif (get_post_type($idPost) == "esporte") {
                            echo obterTituloEsporte($idPost);
                        } else {
                            echo obterTextoPostType($idPost, get_post_field('post_author', $idPost));
                        }
                    }
                    ?>
                    <?php if ($exclusivo) { ?>
                        <div class="ui red label" style="<?php if (!$editoria) {
                                                                echo 'margin-left: 5px;';
                                                            } ?>">EXCLUSIVO</div>
                    <?php } ?>
                </div>

                <p class="dataPostHome"><?= dataPostHome($idPost); ?></p>
                <p class="txtTitleHome"><?php resumo(100, get_the_title($idPost)); ?></p>
                <p class="txtSubtitleHome"><?= obterSubtitulo($idPost); ?></p>

                <div id="div-share" style="padding-left: 0px; padding-top: 5px; padding-bottom: 30px;">
                    <a href="#/" style="float: left;" class="facebook" title="Facebook" onclick="window.open('https://www.facebook.com/sharer/sharer.php?u='+encodeURIComponent('<?= get_permalink($idPost) ?>')+'&app_id=1013999078657900', '', 'toolbar=no, location=no, status=no, menubar=no, scrollbars=no, resizable=no, top=200, left='+Math.round((screen.width / 2) - (550 / 2))+', width=550, height=450');"><img id="img_facebook_<?= $idPost ?>" style="width: 25px; height: 25px;" src="<?php bloginfo('template_url'); ?>/assets/imgs/img_facebook_big.png" alt="Facebook" /></a>
                    <a href="#/" style="float: left; padding-left: 10px;" class="twitter" title="Twitter" onclick="window.open('https://twitter.com/intent/tweet?url='+encodeURIComponent('<?= get_permalink($idPost) ?>')+'&text=<?php resumo(80, get_the_title($idPost)); ?>', '', 'toolbar=no, location=no, status=no, menubar=no, scrollbars=no, resizable=no, top=200, left='+Math.round((screen.width / 2) - (550 / 2))+', width=550, height=450');"><img id="img_twitter_<?= $idPost ?>" style="width: 25px; height: 25px;" src="<?php bloginfo('template_url'); ?>/assets/imgs/img_twitter_big.png" alt="Twitter" /></a>
                </div>
            </div>

        </a>
    </li>
<?php
}

function showLinePostMobile($idPost, $img, $divChamada, $spanCategoria, $exclusivo = false, $editoria = false, $destaque = false)
{
    $subtitulo = get_post_meta($idPost, 'subtitulo', true);
?>
    <li class="li-home" id="<?= $idPost ?>">
        <?php if (!$editoria) { ?>
            <span class="categoriaMobile <?= $spanCategoria ?>">
                <?php
                if ((int) get_post_field('post_author', $idPost) == 17 || (int) get_post_field('post_author', $idPost) == 58) {
                    echo "BLOG DO LEVI";
                } else {
                    echo post_label($idPost);
                }
                ?>
            </span>
        <?php } ?>

        <?php if (!$editoria) { ?>
            <span class="infoPost2" style="margin-top: 8px;"><?php if (get_post_type($idPost) != "especial" && get_post_type($idPost) != "entrevista" && !$destaque) {
                                                                    echo get_the_date('H\hi \d\e d/m/Y', $idPost);
                                                                } ?></span>
        <?php } ?>

        <div class="<?= $divChamada ?>" style="float: left">
            <a href="<?= get_permalink($idPost); ?>">
                <?php if (!empty($img)) : ?>
                    <figure style="width: 100%"><img src="<?= $img; ?>" alt="<?= $img; ?>" <?= getMedidasImagem('news_home'); ?> style="width: 100% !important;"></figure>
                <?php endif; ?>

                <?php if ($exclusivo) { ?>
                    <div class="ui red label" style="margin: 10px 0px 0px 10px;">EXCLUSIVO</div>
                <?php } ?>

                <div>
                    <?php if ($editoria) { ?>
                        <p class="dataPost" style="margin-top: 10px;"><?= get_the_date('H\hi \d\e d/m/Y', $idPost); ?></p>
                    <?php } ?>
                    <p class="data-publicacao"></p>
                    <p class="call-chamada"><?php resumo(100, get_the_title($idPost)); ?></p>
                    <?php if ($subtitulo == "") : ?>
                        <p><?php resumo(170, get_the_excerpt()); ?></p>
                    <?php else : ?>
                        <p><?php resumo(170, $subtitulo); ?></p>
                    <?php endif; ?>
                </div>
            </a>
        </div>
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
            $img = as3cf_get_attachment_url(get_post_meta($id, 'imagem', true));
            $exclusivo = get_post_meta($id, 'exclusivo', true);

            $divChamada = "";
            $spanCategoria = "";
            if (get_post_type($id) == "especial" || get_post_type($id) == "exclusivo" || $exclusivo) {
                $divChamada = "divChamadaPostTypeMobile";
            }

            if (get_post_type($id) == "especial") {
                $spanCategoria = "spanEspecial";
            }

            if (get_post_type($id) == "exclusivo") {
                $spanCategoria = "spanExclusivo";
            }

            if (get_post_type($id) == "motor") {
                $spanCategoria = "categoriaMotor";
            }

            if ((int) get_post_field('post_author', $id) == 17 || (int) get_post_field('post_author', $id) == 58) {
                $spanCategoria = "spanLevi";
            }

            showLinePostMobile($id, $img, $divChamada, $spanCategoria, $exclusivo, false, true);

        else:
        ?>
            <li>
                <p class="call-chamada">Sua busca não trouxe resultados.</p>
            </li>
        <?php endif; ?>
    </ul>

<?php
}


function post_label($id)
{
    $post_type = get_post_type_object(get_post_type($id));
    if (get_post_type($id) == "entretenimento") {
        return obterTituloEntretenimento($id);
    } elseif (get_post_type($id) == "municipios") {
        return obterTituloMunicipios($id);
    } elseif (get_post_type($id) == "motor") {
        return obterTituloMotor($id);
    } elseif (get_post_type($id) == "esporte") {
        return obterTituloEsporte($id);
    } else {
        return $post_type->label;
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
        <div class="item-popular" data-comments="<?= get_comments_number($q->ID); ?>">
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
        if (! in_array($query->posts[ $i ]->ID, $news_ids)) {
            $n_post++;
        ?>
            <div class="item-popular" data-comments="<?= get_comments_number($query->posts[$i]->ID); ?>">
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
add_action('init', function(){
    global $wp_rewrite;
    $wp_rewrite->author_base = "colunistas";
    $wp_rewrite->flush_rules();
});


function is_mobile()
{
    require_once "Mobile-Detect/Mobile_Detect.php";

    $detect = new Mobile_Detect;

    if ($detect->isMobile() || $detect->isAndroidOS() || $detect->isiOS()) {
        return true;
    }
    return false;
}

function is_android()
{
    if (is_mobile() && strpos(getUserAgent(), "Android")) {
        return true;
    }

    return false;
}

function is_iPhone()
{
    if (is_mobile() && strpos(getUserAgent(), "iPhone")) {
        return true;
    }

    return false;
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
function customRSS(){
	add_feed('feedbahiaba', 'customRSSFunc');
}

function customRSSFunc(){
	get_template_part('rss', 'feedbahiaba');
}

function rssLanguage(){
	update_option('rss_language', 'pt-BR');
}
add_action('admin_init', 'rssLanguage');