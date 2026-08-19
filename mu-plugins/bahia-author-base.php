<?php
/**
 * Plugin Name: Bahia.ba - Base da URL de autor (/colunistas/)
 * Description: Devolve `author_base = colunistas`, que o tema bahia_refactor definia e que a
 *              virada para o Newspaper levou embora. Porte de functions.php:1236-1240.
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * POR QUE ISTO EXISTE (19/08/2026).
 *
 * O `bahia_refactor` fazia isto num `add_action('init', ...)` e a virada, ao desligar o tema,
 * devolveu o padrão do WordPress. Medido em produção minutos depois da virada:
 *
 *     /colunistas/neison-cerqueira/   200 antes  ->  404 depois
 *     /author/neison-cerqueira/       404 antes  ->  200 depois
 *
 * Ou seja: todo link de colunista que existe no mundo — compartilhado em WhatsApp, citado em
 * matéria antiga, indexado no Google — passou a 404 de uma vez só. A decisão da redação foi
 * restaurar `colunistas`, e não migrar para `author` com 301: `/colunistas/` é o endereço que
 * o público já tem, e trocar estrutura de URL de portal merece janela própria.
 *
 * CONSEQUÊNCIA CONHECIDA E ACEITA: com `author_base` valendo `colunistas`, o `/author/<slug>/`
 * deixa de casar com a regra de autor. Ele não era endereço nosso — era o padrão do WordPress,
 * que ficou exposto durante as horas entre a virada e este porte.
 *
 * O QUE ESTE ARQUIVO FAZ DIFERENTE DO TEMA, e é o ponto: o tema chamava
 * `$wp_rewrite->flush_rules()` **a cada requisição**. Com 25 CPTs e as taxonomias de cada um,
 * isso é regeneração completa das regras de reescrita em todo request — custo pago em toda
 * página, para gravar um valor que quase nunca muda. Aqui o flush acontece UMA VEZ por versão,
 * pelo mesmo padrão do bahia-editorias-cpt.php.
 */

/** Bump para forçar novo flush quando mexer na base abaixo. */
define('BAHIA_AUTHOR_BASE_VER', '1.0.0');

/** A base da URL de autor. Trocar aqui exige bump da versão acima. */
define('BAHIA_AUTHOR_BASE', 'colunistas');

/**
 * Prioridade 0: o valor precisa estar de pé antes de qualquer código gerar link de autor ou
 * regenerar as regras. `get_author_posts_url()` lê `author_base` em tempo de execução, então
 * os links passam a sair com /colunistas/ já a partir daqui, mesmo antes do flush.
 */
add_action('init', function () {
    global $wp_rewrite;
    if ($wp_rewrite instanceof WP_Rewrite) {
        $wp_rewrite->author_base = BAHIA_AUTHOR_BASE;
    }
}, 0);

/**
 * mu-plugins não têm hook de ativação. O flush é o que materializa a regra no option
 * `rewrite_rules`; sem ele, o link sai certo e a URL responde 404.
 */
add_action('init', function () {
    if (get_option('bahia_author_base_flushed') !== BAHIA_AUTHOR_BASE_VER) {
        flush_rewrite_rules(false);
        update_option('bahia_author_base_flushed', BAHIA_AUTHOR_BASE_VER);
    }
}, 999);
