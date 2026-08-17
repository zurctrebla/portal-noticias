<?php
/**
 * Plugin Name: Bahia.ba - Datas sem o capitalize do demo
 * Description: As datas saíam como "28 De Julho De 2026". Em português preposição e artigo
 *              não recebem maiúscula — o correto é "28 de julho de 2026".
 *
 *              A origem NÃO está no texto: o HTML servido já vem em minúsculas (o
 *              bahia-traducoes.php entrega "28 de julho de 2026"). É um
 *              `text-transform: capitalize` puramente visual, herdado do demo Magazine
 *              PRO, que o tagDiv imprime como CSS por-bloco a partir dos atributos
 *              f_meta_font_transform / f_meta1_font_transform / f_meta2_font_transform /
 *              f_date_font_transform dos blocos.
 *
 *              POR QUE CSS E NÃO EDITAR OS ATRIBUTOS NO BANCO. As regras nascem em 7
 *              lugares (Home #9000142 e os templates 9000128, 9000130, 9000132, 9000134,
 *              9000136, 9000138) e são emitidas com seletores .tdi_NN, que o tagDiv
 *              RENUMERA a cada edição de template. Corrigir no banco significaria (a)
 *              carregar mais sete alterações para a migração de produção e (b) deixar o
 *              defeito voltar no próximo salvamento de um bloco. Uma regra de CSS viaja
 *              no git e vale para qualquer bloco, inclusive os que ainda não existem.
 *
 *              ESCOPO. Só o elemento da DATA. Os capitalize legítimos ficam intactos:
 *              o menu (.tdb-menu > li > a), o título da página de autor
 *              (.tdb-title-text) e o nome do autor nas linhas .td-editor-date /
 *              .td-author-date — nomes já vêm capitalizados do banco, então nada muda
 *              visualmente neles.
 *
 *              Medido antes da correção (text-transform computado):
 *                home, busca, autor -> capitalize nas datas
 *                single, archives de editoria, data do cabeçalho -> já estavam em none
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', function () {
    if (is_admin()) {
        return;
    }

    // O tagDiv emite as regras já com !important e em seletores longos, do tipo
    //   body .tdi_104 .td_module_flex_6 .td-editor-date .entry-date { ...
    //                                    text-transform:capitalize!important }
    // ou seja, especificidade (0,4,1). Um "!important" sozinho em ".entry-date"
    // (0,1,0) PERDE — foi o que aconteceu na primeira tentativa: .td-post-date virou
    // "none" e .entry-date continuou capitalizado.
    //
    // Para ganhar sem depender dos .tdi_NN (que o tagDiv renumera a cada edição de
    // template) a mesma classe é repetida 5x: seletor idêntico em significado, mas
    // com especificidade (0,5,0), acima de qualquer variante deles.
    $alvos = array(
        '.td-post-date',   // data no post e no card do legacy
        '.entry-date',     // <time> dos cards do tagDiv
        '.td-module-date', // idem, classe irmã
        '.tdb-post-date',  // blocos tdb_ do Cloud Library
    );

    $seletores = array();
    foreach ($alvos as $classe) {
        $seletores[] = str_repeat($classe, 5);
        // O <time> filho, quando a data está aninhada dentro do wrapper.
        $seletores[] = str_repeat($classe, 5) . ' time';
    }

    $css = "/* Data nunca em capitalize: pt-BR não maiúsculiza 'de'. */\n"
         . implode(",\n", $seletores)
         . "{text-transform:none !important;}\n";

    wp_register_style('bahia-datas-minusculas', false, array(), '1.0.0');
    wp_enqueue_style('bahia-datas-minusculas');
    wp_add_inline_style('bahia-datas-minusculas', $css);
}, 46);
