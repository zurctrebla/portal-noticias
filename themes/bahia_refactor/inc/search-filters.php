<?php
/**
 * Filtros de Busca
 * Adiciona filtros por data e categoria à busca do WordPress
 */

// Modifica a query de busca para incluir filtros
function bahia_search_filter($query) {
    // Só modifica a busca principal no front-end
    if (!is_admin() && $query->is_main_query() && $query->is_search()) {

        // Filtro por data
        if (!empty($_GET['date_from']) || !empty($_GET['date_to'])) {
            $date_query = array();

            if (!empty($_GET['date_from'])) {
                $date_from = sanitize_text_field($_GET['date_from']);
                $date_query['after'] = $date_from;
            }

            if (!empty($_GET['date_to'])) {
                $date_to = sanitize_text_field($_GET['date_to']);
                $date_query['before'] = $date_to;
            }

            $date_query['inclusive'] = true;
            $query->set('date_query', array($date_query));
        }

        // Filtro por post_type (categoria/editoria)
        if (!empty($_GET['post_type_filter'])) {
            $post_type = sanitize_text_field($_GET['post_type_filter']);

            global $POST_TYPES;
            if (in_array($post_type, $POST_TYPES)) {
                $query->set('post_type', $post_type);
            }
        }

        // Ordenar por data (mais recentes primeiro)
        $query->set('orderby', 'date');
        $query->set('order', 'DESC');
    }
}
add_action('pre_get_posts', 'bahia_search_filter');

// Adiciona informações sobre os filtros ativos na página de resultados
function bahia_get_active_filters() {
    $filters = array();

    if (!empty($_GET['s'])) {
        $filters['search'] = array(
            'label' => 'Busca',
            'value' => get_search_query()
        );
    }

    if (!empty($_GET['date_from'])) {
        $filters['date_from'] = array(
            'label' => 'De',
            'value' => date_i18n('d/m/Y', strtotime(sanitize_text_field($_GET['date_from'])))
        );
    }

    if (!empty($_GET['date_to'])) {
        $filters['date_to'] = array(
            'label' => 'Até',
            'value' => date_i18n('d/m/Y', strtotime(sanitize_text_field($_GET['date_to'])))
        );
    }

    if (!empty($_GET['post_type_filter'])) {
        $post_type = sanitize_text_field($_GET['post_type_filter']);
        $filters['post_type'] = array(
            'label' => 'Editoria',
            'value' => getNamePostType($post_type)
        );
    }

    return $filters;
}

// Helper function para exibir os filtros ativos
function bahia_display_active_filters() {
    $filters = bahia_get_active_filters();

    if (empty($filters)) {
        return;
    }

    echo '<div class="search-results-info">';

    if (isset($filters['search'])) {
        global $wp_query;
        echo '<p class="results-count">';
        printf(
            'Encontrados <strong>%d resultados</strong> para: <span class="search-term">"%s"</span>',
            $wp_query->found_posts,
            esc_html($filters['search']['value'])
        );
        echo '</p>';
        unset($filters['search']);
    }

    if (!empty($filters)) {
        echo '<div class="active-filters">';
        echo '<strong>Filtros ativos:</strong> ';

        foreach ($filters as $key => $filter) {
            echo sprintf(
                '<span class="filter-tag">%s: <strong>%s</strong></span>',
                esc_html($filter['label']),
                esc_html($filter['value'])
            );
        }

        echo '</div>';
    }

    echo '</div>';
}
