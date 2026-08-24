<?php
/**
 * Página "Quem Somos": troca o texto institucional de abertura (o bloco .qs-intro).
 *
 * O QUE MUDA
 *
 *   Os quatro parágrafos de abertura passam a ser três, com a redação enviada pela
 *   redação em 24/08/2026. Some a atribuição nominal ("idealizado pelo empresário
 *   João Lourenço Botti" -> "idealizado para ser") e entra a frase sobre a ampliação
 *   de cobertura (esportes, entretenimento, área jurídica e cidades).
 *
 *   Nada fora do .qs-intro é tocado: a grade da equipe (.qs-equipe, 23 pessoas) e o
 *   contato (.qs-contact) seguem intactos. A troca é feita por regex ancorada na
 *   abertura e no fechamento do próprio bloco — o .qs-intro não tem div aninhada.
 *
 * POR QUE O TEXTO NOVO SAI EM <p> E NÃO EM <span>
 *
 *   O conteúdo antigo eram <span style="font-weight:400"> separados por linha em
 *   branco — resíduo de colagem do editor. Como esta página desliga o wpautop
 *   (bahia-quem-somos.php: os blocos já vêm em HTML estruturado), essas linhas em
 *   branco NÃO viravam parágrafo: o navegador colapsava tudo e os quatro trechos
 *   saíam como um único bloco corrido, sem respiro entre eles.
 *
 *   O stylesheet já previa o certo e nunca era usado:  .qs-intro p{margin:0 0 18px;}
 *   Com <p> de verdade essa regra passa a valer e os parágrafos ganham o espaçamento
 *   que o layout sempre esperou. É correção de marcação, não de estilo — o CSS do
 *   mu-plugin não muda uma linha.
 *
 * ESCRITA DIRETA NA TABELA, DE PROPÓSITO
 *
 *   wp_update_post() passaria o HTML pelo KSES, que em execução por CLI (sem usuário
 *   logado) derruba atributos e pode desmontar o bloco. $wpdb->update() grava o que
 *   foi conferido aqui, e o clean_post_cache() logo abaixo desfaz o cache do objeto.
 *
 *   O conteúdo anterior é guardado inteiro na postmeta _bahia_qs_intro_backup antes
 *   de gravar: para reverter, basta reescrever o valor dessa meta no post_content.
 *
 * USO (dentro do pod):
 *     php texto-quem-somos-apply.php            # seco, não escreve nada
 *     php texto-quem-somos-apply.php --aplicar  # grava
 *
 * Idempotente: rodar de novo depois de aplicado não faz nada. Localiza a página pelo
 * caminho, nunca por ID fixo — é 9000079 em homologação, da faixa renumerada, e não
 * há garantia de que seja o mesmo número na produção.
 */

define('WP_INSTALLING', true);
require '/var/www/html/wp-load.php';

const CAMINHO  = 'quem-somos';
const META_BKP = '_bahia_qs_intro_backup';

/** O bloco novo, na íntegra — abre e fecha a div. */
const INTRO_NOVA = <<<'HTML'
<div class="qs-intro">
<p>O <strong>bahia.ba</strong> foi fundado em novembro de 2015, idealizado para ser um portal de notícias sobre a Bahia, com foco sobretudo na cobertura política do estado.</p>
<p>Com o tempo, o site ampliou sua atuação para outros temas, como esportes, entretenimento, área jurídica e cidades, se consolidando como uma das principais fontes de notícias voltadas para o povo baiano.</p>
<p>Após mais de uma década de existência, o bahia.ba segue mantendo uma linha editorial serena e confiável, o que nos garante como um espaço de confirmação dos fatos para os leitores mais críticos.</p>
</div>
HTML;

$aplicar = in_array('--aplicar', $argv, true);
$siteurl = get_option('siteurl');

$ambiente = ($siteurl === 'https://hml.bahia.ba') ? 'HOMOLOGACAO'
          : (($siteurl === 'https://bahia.ba')    ? 'PRODUCAO' : null);

if ($ambiente === null) {
    echo "ABORTA: siteurl inesperado ($siteurl)\n";
    exit(1);
}

echo "ambiente: $ambiente ($siteurl)\n";
echo "modo: " . ($aplicar ? 'APLICAR' : 'seco (nada sera escrito)') . "\n\n";

$p = get_page_by_path(CAMINHO, OBJECT, 'page');
if (!$p) {
    echo "ABORTA: pagina '" . CAMINHO . "' nao encontrada neste ambiente\n";
    exit(1);
}

echo "pagina: #{$p->ID} \"{$p->post_title}\" ({$p->post_status}) modificada em {$p->post_modified}\n";

$conteudo = $p->post_content;

if (strpos($conteudo, INTRO_NOVA) !== false) {
    echo "\ntexto novo ja esta no ar. nada a fazer.\n";
    exit(0);
}

$padrao = '#<div class="qs-intro">.*?</div>#s';
if (!preg_match($padrao, $conteudo, $m)) {
    echo "ABORTA: bloco .qs-intro nao encontrado no post_content\n";
    exit(1);
}

$intro_antiga = $m[0];
$novo = preg_replace($padrao, str_replace('$', '\\$', INTRO_NOVA), $conteudo, 1);

/* ---------- conferencia antes de gravar ---------- */

$resto_antes  = str_replace($intro_antiga, '', $conteudo);
$resto_depois = str_replace(INTRO_NOVA, '', $novo);

echo "\n--- o que sai ---\n" . $intro_antiga . "\n";
echo "\n--- o que entra ---\n" . INTRO_NOVA . "\n";
echo "\n--- verificacoes ---\n";
echo "  resto do conteudo intacto: " . ($resto_antes === $resto_depois ? 'sim' : 'NAO — ABORTA') . "\n";
echo "  membros da equipe antes/depois: "
   . substr_count($conteudo, 'qs-member') . '/' . substr_count($novo, 'qs-member') . "\n";
echo "  bloco de contato preservado: " . (strpos($novo, 'qs-contact') !== false ? 'sim' : 'NAO') . "\n";

if ($resto_antes !== $resto_depois || substr_count($conteudo, 'qs-member') !== substr_count($novo, 'qs-member')) {
    echo "\nABORTA: a troca mexeu em algo fora do .qs-intro\n";
    exit(1);
}

if (!$aplicar) {
    echo "\nseco: nada foi escrito. rode com --aplicar para gravar.\n";
    exit(0);
}

/* ---------- grava ---------- */

global $wpdb;

update_post_meta($p->ID, META_BKP, wp_slash($intro_antiga));

$n = $wpdb->update(
    $wpdb->posts,
    array('post_content' => $novo),
    array('ID' => $p->ID),
    array('%s'),
    array('%d')
);

clean_post_cache($p->ID);

echo "\nlinhas gravadas: " . var_export($n, true) . "\n";

/* ---------- conferencia depois ---------- */

$dep = get_post($p->ID);
$ok  = (strpos($dep->post_content, INTRO_NOVA) !== false)
    && (strpos($dep->post_content, 'Botti') === false)
    && (substr_count($dep->post_content, 'qs-member') === substr_count($conteudo, 'qs-member'));

echo "\n--- conferencia ---\n";
echo "  texto novo no banco: " . (strpos($dep->post_content, INTRO_NOVA) !== false ? 'sim' : 'NAO') . "\n";
echo "  texto antigo sumiu:  " . (strpos($dep->post_content, 'Botti') === false ? 'sim' : 'NAO') . "\n";
echo "  equipe preservada:   " . substr_count($dep->post_content, 'qs-member') . " membros\n";
echo "  backup em " . META_BKP . ": " . (get_post_meta($p->ID, META_BKP, true) !== '' ? 'gravado' : 'NAO') . "\n";
echo $ok ? "  OK\n" : "  FALHOU\n";
exit($ok ? 0 : 1);
