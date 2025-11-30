(function ($) {
    'use strict';

    if (typeof bahiaInfiniteScrollData === 'undefined') {
        console.error('❌ bahiaInfiniteScrollData não definido');
        return;
    }

    var InfiniteScroll = {
        config: {
            container: '#posts-container',
            loader: '.imgLoader',
            noMoreMessage: '.no-more-posts',
            threshold: bahiaInfiniteScrollData.isMobile ? 300 : 400,
            debounceDelay: 200
        },

        state: {
            loading: false,
            hasMore: true,
            totalLoaded: 0,
            totalPosts: bahiaInfiniteScrollData.totalPosts || 0,
            postsPerPage: bahiaInfiniteScrollData.postsPerPage || 10,
            loadedIds: new Set()
        },

        init: function () {
            this.registerInitialPosts();

            console.log('🚀 Infinite Scroll iniciado:', {
                postType: bahiaInfiniteScrollData.postType,
                initialPosts: this.state.loadedIds.size,
                totalPosts: this.state.totalPosts,
                postsPerPage: this.state.postsPerPage,
                taxonomy: bahiaInfiniteScrollData.taxonomy || 'none',
                termId: bahiaInfiniteScrollData.termId || 0
            });

            this.checkInitialState();
            this.bindEvents();
        },

        registerInitialPosts: function () {
            var self = this;

            // Método 1: Campo hidden
            var hiddenIds = $('#ids').val();
            if (hiddenIds) {
                hiddenIds.split(',').forEach(function (id) {
                    var numId = parseInt(id.trim());
                    if (numId > 0) {
                        self.state.loadedIds.add(numId);
                    }
                });
            }

            // Método 2: Elementos DOM
            $('.li-home').each(function () {
                var postId = parseInt($(this).attr('id'));
                if (postId > 0) {
                    self.state.loadedIds.add(postId);
                }
            });

            // Método 3: Data inicial
            if (bahiaInfiniteScrollData.excludeIds && Array.isArray(bahiaInfiniteScrollData.excludeIds)) {
                bahiaInfiniteScrollData.excludeIds.forEach(function (id) {
                    if (id > 0) {
                        self.state.loadedIds.add(id);
                    }
                });
            }

            this.state.totalLoaded = this.state.loadedIds.size;

            console.log('📊 Posts iniciais:', {
                count: this.state.loadedIds.size,
                sample: Array.from(this.state.loadedIds).slice(0, 5)
            });
        },

        checkInitialState: function () {
            if (this.state.totalLoaded >= this.state.totalPosts) {
                this.state.hasMore = false;
                if (this.state.totalPosts > 0) {
                    this.showNoMoreMessage();
                }
            }
        },

        bindEvents: function () {
            var self = this;
            var scrollHandler = this.debounce(function () {
                self.onScroll();
            }, this.config.debounceDelay);

            $(window).on('scroll', scrollHandler);

            setTimeout(function () {
                self.checkIfNeedsLoad();
            }, 500);
        },

        onScroll: function () {
            if (!this.state.hasMore || this.state.loading) {
                return;
            }
            this.checkIfNeedsLoad();
        },

        checkIfNeedsLoad: function () {
            var $lastPost = $('.li-home').last();

            if ($lastPost.length === 0) {
                return;
            }

            var scrollTop = $(window).scrollTop();
            var windowHeight = $(window).height();
            var lastPostBottom = $lastPost.offset().top + $lastPost.outerHeight();
            var scrollBottom = scrollTop + windowHeight;
            var distanceFromBottom = lastPostBottom - scrollBottom;

            if (distanceFromBottom <= this.config.threshold) {
                this.loadMore();
            }
        },

        loadMore: function () {
            var self = this;

            if (this.state.loading || !this.state.hasMore) {
                return;
            }

            this.state.loading = true;
            this.showLoader();

            var excludeIds = Array.from(this.state.loadedIds).join(',');

            console.log('📥 Carregando:', {
                exclude_count: this.state.loadedIds.size,
                total_loaded: this.state.totalLoaded,
                total_posts: this.state.totalPosts
            });

            $.ajax({
                url: bahiaThemeData.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'bahia_infinite_scroll',
                    nonce: bahiaThemeData.infiniteScrollNonce,
                    post_type: bahiaInfiniteScrollData.postType,
                    taxonomy: bahiaInfiniteScrollData.taxonomy || '',
                    term_id: bahiaInfiniteScrollData.termId || 0,
                    posts_per_page: self.state.postsPerPage,
                    exclude_ids: excludeIds,
                    is_mobile: bahiaInfiniteScrollData.isMobile,
                    is_multi_post_type: bahiaInfiniteScrollData.isMultiPostType || false
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
                    self.state.loading = false;
                }
            });
        },

        onLoadSuccess: function (response) {
            if (!response.success || !response.data) {
                console.warn('⚠️ Resposta inválida');
                this.state.hasMore = false;
                this.showNoMoreMessage();
                return;
            }

            var data = response.data;

            console.log('📦 Resposta:', {
                count: data.count,
                has_more: data.has_more,
                total_posts: data.total_posts,
                ids: data.ids
            });

            if (!data.html || data.count === 0) {
                console.log('✅ Sem mais posts');
                this.state.hasMore = false;
                this.showNoMoreMessage();
                return;
            }

            // Verifica duplicatas
            var duplicates = [];
            var newIds = [];

            if (data.ids && Array.isArray(data.ids)) {
                data.ids.forEach(function (id) {
                    if (this.state.loadedIds.has(id)) {
                        duplicates.push(id);
                        console.error('🔴 DUPLICADO:', id);
                    } else {
                        this.state.loadedIds.add(id);
                        newIds.push(id);
                    }
                }.bind(this));
            }

            if (duplicates.length > 0) {
                console.error('❌ Total duplicatas:', duplicates.length);
            }

            // Adiciona HTML
            if (newIds.length > 0) {
                $(this.config.container).append(data.html);
                $('#ids').val(Array.from(this.state.loadedIds).join(','));
            }

            // Atualiza estado
            this.state.totalLoaded = this.state.loadedIds.size;
            this.state.hasMore = data.has_more && this.state.totalLoaded < data.total_posts;

            if (data.total_posts) {
                this.state.totalPosts = data.total_posts;
            }

            console.log('✅ Novos:', newIds.length, '| Duplicados:', duplicates.length, '| Total:', this.state.totalLoaded, '/', this.state.totalPosts);

            // Verifica se precisa carregar mais
            if (this.state.hasMore && newIds.length > 0) {
                var self = this;
                setTimeout(function () {
                    self.checkIfNeedsLoad();
                }, 300);
            } else if (!this.state.hasMore) {
                this.showNoMoreMessage();
            }
        },

        onLoadError: function (xhr, status, error) {
            console.error('❌ Erro:', { status: status, error: error });

            if (!this.state.retried) {
                this.state.retried = true;
                var self = this;
                setTimeout(function () {
                    console.log('🔄 Retry...');
                    self.state.loading = false;
                    self.state.retried = false;
                }, 5000);
            }
        },

        showLoader: function () {
            $(this.config.loader).fadeIn(200);
        },

        hideLoader: function () {
            $(this.config.loader).fadeOut(200);
        },

        showNoMoreMessage: function () {
            $(this.config.noMoreMessage).fadeIn(400);
            console.log('🏁 Fim do scroll');
        },

        debounce: function (func, wait) {
            var timeout;
            return function () {
                var context = this;
                var args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(function () {
                    func.apply(context, args);
                }, wait);
            };
        }
    };

    $(document).ready(function () {
        // Não inicializar se o botão "Ver Mais" existir (home usa botão)
        // Apenas em páginas de editoria que não têm o botão
        if ($('#load-more-btn').length === 0) {
            InfiniteScroll.init();
        }
    });

})(jQuery);