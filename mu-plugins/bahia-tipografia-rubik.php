<?php
/**
 * Plugin Name: Bahia.ba - Reverte a tipografia Rubik do demo Magazine PRO
 * Description: A troca de demo (Newspaper PRO -> Magazine PRO) trouxe a fonte Rubik como
 *              83 declarações `font-family:Rubik!important` espalhadas pelos blocos. Não
 *              há licença de webfont para a Ageo, então a decisão foi voltar ao estado
 *              anterior — o padrão do próprio tagDiv.
 *
 * ---------------------------------------------------------------------------
 * POR QUE REMOVER A DECLARAÇÃO EM VEZ DE SUBSTITUIR A FONTE
 *
 * Cada nível tem um padrão diferente no tema, e não existe registro de qual era a fonte
 * de cada um antes da troca (ver abaixo). Trocar `Rubik` por um nome escolhido a dedo
 * seria inventar um estado que nunca existiu. Removendo só a declaração, cada elemento
 * cai na regra que o TEMA já define para ele — que é, por definição, o padrão anterior.
 *
 * Medido em hml.bahia.ba depois da remoção:
 *
 *     títulos de card, títulos de bloco ....... Roboto      (--td_default_google_font_2)
 *     menu, autor, data, textos de apoio ...... Open Sans   (--td_default_google_font_1)
 *     campo de busca do cabeçalho ............. Verdana     (o tema não estiliza este
 *                                                            input; herda de `body`)
 *
 * Confere com a expectativa registrada no briefing: o Rubik entrou como override e o
 * padrão por baixo dele é o par Open Sans / Roboto do tagDiv.
 *
 * ---------------------------------------------------------------------------
 * POR QUE ISTO NÃO É UMA ALTERAÇÃO DE BANCO
 *
 * As 83 declarações NÃO estão no git: vieram no `post_content` dos templates e da home,
 * gravados pela troca de demo. Editá-las no banco somaria 83 pontos ao inventário de
 * migração para produção e amarraria a correção aos ids `.tdi_NN`, que o tagDiv renumera
 * a cada edição de template.
 *
 * A remoção acontece no HTML já montado, pelo buffer de saída único do site, e é ancorada
 * na string da declaração — não em id de bloco nenhum. Mesma técnica das datas da rodada 5.
 *
 * O escopo é o interior das tags <style>. O conteúdo editorial não é tocado: um artigo só
 * seria afetado se citasse literalmente `font-family:Rubik!important` dentro de um <style>.
 *
 * @see bahia-html-saida.php  buffer de saída único do site (filtro bahia_hs_html)
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Remove as declarações de font-family:Rubik de dentro das tags <style>.
 *
 * Só a declaração de fonte sai; `font-size`, `font-weight`, `line-height` e
 * `text-transform`, que vêm no mesmo bloco, permanecem — o objetivo é reverter a
 * TIPOGRAFIA, não o dimensionamento que o demo definiu junto.
 */
function bahia_rubik_remover($html) {
    if (stripos($html, 'Rubik') === false) {
        return $html;
    }

    return preg_replace_callback(
        '#(<style\b[^>]*>)(.*?)(</style>)#is',
        function ($m) {
            // Aceita com ou sem aspas, com ou sem !important, com ou sem o ; final.
            // O ; opcional cobre a última declaração do bloco (`...;font-family:Rubik!important}`).
            $css = preg_replace(
                '/font-family\s*:\s*(?:\'|")?Rubik(?:\'|")?\s*(?:!\s*important)?\s*;?/i',
                '',
                $m[2]
            );
            return $m[1] . $css . $m[3];
        },
        $html
    );
}
add_filter('bahia_hs_html', 'bahia_rubik_remover');

/**
 * Tira o Rubik da requisição ao Google Fonts.
 *
 * O tema pede as três famílias numa URL só, separadas por `|`:
 *   ...css?family=Open+Sans:400,600,700|Roboto:400,600,700|Rubik:500,400&display=swap
 * Sem uso na página, baixar o arquivo é peso morto. Open Sans e Roboto continuam — são
 * exatamente as famílias em que a tipografia passa a cair.
 */
function bahia_rubik_fora_do_google_fonts($src, $handle) {
    if (strpos($src, 'fonts.googleapis.com') === false || stripos($src, 'Rubik') === false) {
        return $src;
    }

    // A URL chega com as entidades já aplicadas em alguns contextos; trata os dois casos.
    foreach (array('|', '%7C', '%7c') as $sep) {
        // Remove "SEP Rubik:pesos" e também "Rubik:pesos SEP" (se vier em primeiro).
        $src = preg_replace('/' . preg_quote($sep, '/') . 'Rubik(?:%3A|:)[0-9,%A-Za-z]*/', '', $src);
        $src = preg_replace('/Rubik(?:%3A|:)[0-9,%A-Za-z]*' . preg_quote($sep, '/') . '/', '', $src);
    }

    return $src;
}
add_filter('style_loader_src', 'bahia_rubik_fora_do_google_fonts', 10, 2);
