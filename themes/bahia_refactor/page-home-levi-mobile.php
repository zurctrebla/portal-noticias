<?php
$category = null;
if($_GET) {
    $category = $_GET['category'];
}

$ipad = strpos(getUserAgent(), "iPad");
$news_ids = array();
?>
<style>
    .ui.dropdown .menu {
        left: -87px;
    }
</style>
<main style="padding-top: 65px;">
    <div class="container">
        <div class="colunistas">
            <div class="filtros-resultado-busca" style="text-align: center; line-height: 30px; border-bottom: none;">
                <a href="/colunistas/levi-vasconcelos/" class="ativo" style="border-right: none;">
                    <img style="width: 100%; height: auto;" src="<?php bloginfo('template_url');?>/assets/imgs/blogDoLevi.jpg"/>
                </a>
            </div>

            <div style="float: left; margin-top: 3.6%; position: absolute; border-bottom: 2px solid rgba(34,36,38,.15); width: 35%"></div>

             <div class="circular ui icon top pointing dropdown button btnDropdownCategorias" style="left: 43.7%; margin-top: -3px !important; position: absolute; float: left;">
                 <i class="sidebar icon"></i>
                 <div class="menu" >
                     <div class="<?php if($category == "analises"){echo "active";}?> item"><a style="color: #545454;" href="?category=analises">ANÁLISES</a></div>
                     <div class="<?php if($category == "politica_vatapa"){echo "active";}?> item"><a style="color: #545454;" href="?category=politica_vatapa">POLÍTICA COM VATAPÁ</a></div>
                     <div class="<?php if($category == "videos"){echo "active";}?> item"><a style="color: #545454;" href="?category=videos">VÍDEOS</a></div>
                 </div>
             </div>

             <div style="float: right; margin-top: 4.2%; margin-bottom: 5%; border-bottom: 2px solid rgba(34,36,38,.15); width: 40%"></div>

        </div>

        <div class="col2t">
            <div class="mais-colunistas">

                <ul class="resultado-busca">

                <?php
                    if(!$category) {
                        $args = array(
                            'post_type' => array('politica'),
                            'showposts' => 1,
                            'author__in' => array(17, 58),
                            'politica_cat' => 'analises',
                            'orderby' => 'post_date',
                            'order' => 'DESC',
                            'no_found_rows' => true,
                            'ignore_sticky_posts' => true
                        );

                        $query = new WP_Query($args);

                        for ($i = 0; $i < count($query->posts) && $i < $posts_per_page; $i++) {
                            if (! in_array($query->posts[ $i ]->ID, $news_ids)) {
                                $id = $query->posts[$i]->ID;
                                $news_ids[] = $id;
                                $img = as3cf_get_attachment_url(get_post_meta($id, 'imagem', true));
                                $exclusivo = get_post_meta($id, 'exclusivo', true);

                                $divChamada = "";
                                $spanCategoria = "";
                                if ($exclusivo) {
                                    $divChamada = "divChamadaPostTypeMobile";
                                }

                                showLinePostMobile($id, $img, $divChamada, $spanCategoria, $exclusivo, true);
                            }
                        }
                    }


                    $args2 = array(
                        'post_type' => array('politica'),
                        'showposts' => 9,
                        'author__in' => array(17, 58),
                        'politica_cat' => $category,
                        'orderby' => 'post_date',
                        'order' => 'DESC',
                        'no_found_rows' => true,
                        'ignore_sticky_posts' => true
                    );

                    $query2 = new WP_Query($args2);

                    for ($i = 0; $i < count($query2->posts) && $i < $posts_per_page; $i++) {
                        if (! in_array($query2->posts[ $i ]->ID, $news_ids)) {
                            $id = $query2->posts[$i]->ID;
                            $news_ids[] = $id;
                            $img = as3cf_get_attachment_url(get_post_meta($id, 'imagem', true));
                            $exclusivo = get_post_meta($id, 'exclusivo', true);

                            $divChamada = "";
                            $spanCategoria = "";
                            if ($exclusivo) {
                                $divChamada = "divChamadaPostTypeMobile";
                            }

                            showLinePostMobile($id, $img, $divChamada, $spanCategoria, $exclusivo, true);
                        }
                    }

                    ?>
                </ul>

                <div class="pix-wrapper pix-loader imgLoader" style="display: none;">
                    <img src="<?php bloginfo('template_url');?>/assets/imgs/loader.gif">
                </div>
            </div>

            <input type="hidden" id="ids" value="<?=implode(',', $news_ids)?>">
            <input type="hidden" id="loadMore" value="true">

        </div>

    </div>
</main>
