
<?php if ($mod == 1): ?>
    <div class="grid-base destg">
        <div class="slider-baba">
            <?php sliders($mod, 'options'); ?>
        </div>
    </div>
    <?php
    $sql = "SELECT ";
    $sql .= "   id, ";
    $sql .= "   url, ";
    $sql .= "   posicao ";
    $sql .= "FROM wp_video_destaque ";
    $sql .= "WHERE ";
    $sql .= "   status = 1 ";

    $listVideo = $wpdb->get_results($sql);
    
    $video1 = array();
    $video2 = array();
    $video3 = array();
    $video4 = array();
    if($listVideo){
        foreach ($listVideo as $video) {
            switch ($video->posicao) {
                case 1:
                    $video1 = $video;
                    break;
                case 2:
                    $video2 = $video;
                    break;
                case 3:
                    $video3 = $video;
                    break;
                case 4:
                    $video4 = $video;
                    break;
            }
        }
    }
    $destaques = null;
    ?>

    <div class="grid-base destp">
        <?php $video1 != null ? semi_destaque_video(0, $video1) : semi_destaque(0, $destaques); ?>
        <?php $video2 != null ? semi_destaque_video(1, $video2) : semi_destaque(1, $destaques); ?>
    </div>
    <div class="grid-base destp">
        <!-- <figure class="chamada">
            <a href="/covid19">
                <img src="https://d1x4bjge7r9nas.cloudfront.net/wp-content/uploads/2020/04/01082608/painel_corona1.jpg" width="269" height="187">
                <figcaption>
                    <span class="categoria">
                        Covid-19
                    </span>
                    <span>Acompanhe os números atualizados da Covid-19 na Bahia em nosso painel interativo</span>
                </figcaption>
            </a>
        </figure> -->
        <?php $video3 != null ? semi_destaque_video(2, $video3) : semi_destaque(2, $destaques); ?>
        <?php $video4 != null ? semi_destaque_video(3, $video4) : semi_destaque(3, $destaques); ?>
    </div>

<?php endif; ?>
