/**
 * Load More Button para Home
 * Carrega mais notícias ao clicar no botão "Ver mais"
 */
(function ($) {
    'use strict';

    var LoadMoreHome = {
        config: {
            button: '#load-more-btn',
            container: '#posts-container',
            loader: '.imgLoader',
            noMoreMessage: '.no-more-posts-message',
            postsPerLoad: 15
        },

        state: {
            loading: false,
            hasMore: true,
            loadedIds: new Set()
        },

        init: function () {
            // Ajusta posts por página para mobile
            if (typeof bahiaInfiniteScrollData !== 'undefined' && bahiaInfiniteScrollData.isMobile) {
                this.config.postsPerLoad = bahiaInfiniteScrollData.postsPerPage || 10;
            }

            console.log('🏠 Load More Home inicializado', {
                postsPerLoad: this.config.postsPerLoad,
                isMobile: typeof bahiaInfiniteScrollData !== 'undefined' ? bahiaInfiniteScrollData.isMobile : false
            });

            this.registerInitialPosts();
            this.bindEvents();
            this.checkIfHasMore();
        },

        registerInitialPosts: function () {
            var self = this;

            // Pega IDs do campo hidden
            var hiddenIds = $('#ids').val();
            if (hiddenIds) {
                hiddenIds.split(',').forEach(function (id) {
                    var numId = parseInt(id.trim());
                    if (numId > 0) {
                        self.state.loadedIds.add(numId);
                    }
                });
            }

            // Pega IDs dos elementos DOM
            $('.li-home').each(function () {
                var postId = parseInt($(this).attr('id'));
                if (postId > 0) {
                    self.state.loadedIds.add(postId);
                }
            });

            console.log('📊 Posts iniciais carregados:', this.state.loadedIds.size);
        },

        bindEvents: function () {
            var self = this;

            $(document).on('click', this.config.button, function (e) {
                e.preventDefault();
                self.loadMore();
            });
        },

        checkIfHasMore: function () {
            var loadMoreFlag = $('#loadMore').val();
            if (loadMoreFlag === 'false') {
                this.state.hasMore = false;
                this.hideButton();
                this.showNoMoreMessage();
            }
        },

        loadMore: function () {
            var self = this;

            if (this.state.loading || !this.state.hasMore) {
                return;
            }

            this.state.loading = true;
            this.disableButton();
            this.showLoader();

            var excludeIds = Array.from(this.state.loadedIds).join(',');

            console.log('📥 Carregando mais posts...', {
                exclude_count: this.state.loadedIds.size,
                posts_per_page: this.config.postsPerLoad
            });

            $.ajax({
                url: bahiaThemeData.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'bahia_infinite_scroll',
                    nonce: bahiaThemeData.infiniteScrollNonce,
                    post_type: bahiaThemeData.postTypesList || '', // Todos os post types
                    posts_per_page: self.config.postsPerLoad,
                    exclude_ids: excludeIds,
                    is_mobile: false, // Home é sempre web
                    is_multi_post_type: true
                },
                timeout: 15000,
                success: function (response) {
                    self.onLoadSuccess(response);
                },
                error: function (xhr, status, error) {
                    self.onLoadError(xhr, status, error);
                },
                complete: function () {
                    self.hideLoader();
                    self.enableButton();
                    self.state.loading = false;
                }
            });
        },

        onLoadSuccess: function (response) {
            if (!response.success || !response.data) {
                console.warn('⚠️ Resposta inválida');
                this.state.hasMore = false;
                this.hideButton();
                this.showNoMoreMessage();
                return;
            }

            var data = response.data;

            console.log('📦 Resposta:', {
                count: data.count,
                has_more: data.has_more,
                ids: data.ids
            });

            if (!data.html || data.count === 0) {
                console.log('✅ Sem mais posts');
                this.state.hasMore = false;
                this.hideButton();
                this.showNoMoreMessage();
                return;
            }

            // Adiciona novos IDs
            if (data.ids && Array.isArray(data.ids)) {
                data.ids.forEach(function (id) {
                    this.state.loadedIds.add(id);
                }.bind(this));
            }

            // Adiciona HTML ao container
            $(this.config.container).append(data.html);

            // Atualiza campo hidden
            $('#ids').val(Array.from(this.state.loadedIds).join(','));

            // Verifica se tem mais
            this.state.hasMore = data.has_more;

            if (!this.state.hasMore) {
                this.hideButton();
                this.showNoMoreMessage();
            }

            console.log('✅ Carregados:', data.count, '| Total:', this.state.loadedIds.size);
        },

        onLoadError: function (xhr, status, error) {
            console.error('❌ Erro ao carregar:', { status: status, error: error });

            var errorMessage = 'Erro ao carregar notícias. Tente novamente.';
            if (status === 'timeout') {
                errorMessage = 'Tempo esgotado. Tente novamente.';
            }

            this.showError(errorMessage);
        },

        showLoader: function () {
            $(this.config.loader).fadeIn(200);
        },

        hideLoader: function () {
            $(this.config.loader).fadeOut(200);
        },

        disableButton: function () {
            $(this.config.button)
                .prop('disabled', true)
                .addClass('loading');
        },

        enableButton: function () {
            $(this.config.button)
                .prop('disabled', false)
                .removeClass('loading');
        },

        hideButton: function () {
            $(this.config.button).fadeOut(400);
        },

        showNoMoreMessage: function () {
            $(this.config.noMoreMessage).fadeIn(400);
        },

        showError: function (message) {
            // Você pode personalizar a exibição de erro aqui
            alert(message);
        }
    };

    // Inicializar apenas se estivermos na home
    $(document).ready(function () {
        console.log('🔍 Load More Home - Verificando inicialização...', {
            isHome: $('body').hasClass('home'),
            isBlog: $('body').hasClass('blog'),
            bodyClasses: $('body').attr('class'),
            hasButton: $('#load-more-btn').length > 0
        });

        if ($('body').hasClass('home') || $('body').hasClass('blog')) {
            console.log('✅ Inicializando Load More Home...');
            LoadMoreHome.init();
        } else {
            console.log('⚠️ Load More Home NÃO inicializado - não está na home');
        }
    });

})(jQuery);
