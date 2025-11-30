<?php
/**
 * Template Part: Barra de Busca com Filtros
 * Inclui busca por palavra-chave, data e editoria (post_type)
 */

// Obter post types disponíveis
global $POST_TYPES;

// Valores atuais (se houver)
$current_search = isset($_GET['s']) ? esc_attr($_GET['s']) : '';
$current_date_from = isset($_GET['date_from']) ? esc_attr($_GET['date_from']) : '';
$current_date_to = isset($_GET['date_to']) ? esc_attr($_GET['date_to']) : '';
$current_post_type = isset($_GET['post_type_filter']) ? esc_attr($_GET['post_type_filter']) : '';

// Classes adicionais
$wrapper_class = isset($args['class']) ? esc_attr($args['class']) : '';
?>

<div class="bahia-searchbar <?php echo $wrapper_class; ?>">
    <form role="search" method="get" class="search-form" action="<?php echo esc_url(home_url('/')); ?>">
        <div class="search-fields">
            <!-- Campo de busca -->
            <div class="search-field-group">
                <label for="search-input" class="screen-reader-text">Buscar</label>
                <div class="ui fluid action input search-input-wrapper">
                    <input
                        type="search"
                        id="search-input"
                        class="search-field"
                        placeholder="Digite sua busca..."
                        value="<?php echo $current_search; ?>"
                        name="s"
                        required
                    />
                    <button type="submit" class="ui blue button search-submit">
                        <i class="search icon"></i> <span class="button-text">Buscar</span>
                    </button>
                </div>
            </div>

            <!-- Filtros adicionais -->
            <div class="search-filters">
                <!-- Filtro por data -->
                <div class="filter-group date-filter">
                    <label for="date-from">De:</label>
                    <input
                        type="date"
                        id="date-from"
                        name="date_from"
                        value="<?php echo $current_date_from; ?>"
                        class="date-input"
                    />

                    <label for="date-to">Até:</label>
                    <input
                        type="date"
                        id="date-to"
                        name="date_to"
                        value="<?php echo $current_date_to; ?>"
                        class="date-input"
                    />
                </div>

                <!-- Filtro por editoria (post_type) -->
                <div class="filter-group editoria-filter">
                    <label for="editoria-select">Editoria:</label>
                    <select id="editoria-select" name="post_type_filter" class="ui dropdown">
                        <option value="">Todas as editorias</option>
                        <?php foreach ($POST_TYPES as $post_type): ?>
                            <option
                                value="<?php echo esc_attr($post_type); ?>"
                                <?php selected($current_post_type, $post_type); ?>
                            >
                                <?php echo esc_html(getNamePostType($post_type)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Botão limpar filtros -->
                <?php if ($current_search || $current_date_from || $current_date_to || $current_post_type): ?>
                    <div class="filter-group">
                        <a href="<?php echo esc_url(home_url('/')); ?>" class="ui basic button clear-filters">
                            <i class="remove icon"></i> Limpar
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>
