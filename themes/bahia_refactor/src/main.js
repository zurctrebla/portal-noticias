// ============================================
// BOOTSTRAP PRINCIPAL DO TEMA BAHIA
// ============================================

// 1. jQuery - DEVE ser o primeiro import
import $ from 'jquery';
import '@fontsource/lato';
import '../assets/js/infinite-scroll-editoria';
import '../assets/js/load-more-home';

// Expõe jQuery globalmente IMEDIATAMENTE e SINCRONAMENTE
window.$ = $;
window.jQuery = $;

// ============================================
// 2. SEMANTIC UI
// ============================================

// Semantic UI JS (depende do jQuery)
import 'semantic-ui-css/semantic.js';


// ============================================
// 3. INICIALIZAÇÃO
// ============================================

// Aguarda o DOM estar pronto
$(function () {
    // Inicializa componentes do Semantic UI
    try {
        // Dropdowns
        if ($.fn.dropdown) {
            $('.ui.dropdown').dropdown();
        }

        // Modals
        if ($.fn.modal) {
            $('.ui.modal').modal();
        }

        // Sidebar (menu mobile)
        if ($.fn.sidebar) {
            $('.ui.sidebar').sidebar('attach events', '.mobile-button');
        }

        // Accordion
        if ($.fn.accordion) {
            $('.ui.accordion').accordion();
        }

        // Tabs
        if ($.fn.tab) {
            $('.ui.menu .item').tab();
        }

    } catch (error) {
        // Mantém apenas erros críticos
        console.error('Erro ao inicializar Semantic UI:', error);
    }

    // Inicializa funcionalidades personalizadas do tema
    initTheme();

    // Inicializa menu mobile responsivo
    initMobileMenu();
});


// ============================================
// 4. FUNÇÕES DO TEMA
// ============================================

function initTheme() {
    // Botão voltar ao topo
    initBackToTop();

    // Smooth scroll para âncoras
    initSmoothScroll();
}

function initBackToTop() {
    const $btnTop = $('#toTop, #toTopMobile');

    if ($btnTop.length) {
        $(window).on('scroll', function () {
            if ($(this).scrollTop() > 300) {
                $btnTop.fadeIn();
            } else {
                $btnTop.fadeOut();
            }
        });

        $btnTop.on('click', function (e) {
            e.preventDefault();
            $('html, body').animate({ scrollTop: 0 }, 600);
        });
    }
}

function initSmoothScroll() {
    // Scroll suave para âncoras
    $('a[href^="#"]').not('[href="#"]').not('[href="#/"]').on('click', function (e) {
        const target = $(this.getAttribute('href'));

        if (target.length) {
            e.preventDefault();
            $('html, body').stop().animate({
                scrollTop: target.offset().top - 80
            }, 600);
        }
    });
}

function initMobileMenu() {
    const $menuToggle = $('.menu-toggle');
    const $mainNav = $('.main-navigation');

    if ($menuToggle.length && $mainNav.length) {
        // Toggle menu ao clicar no botão
        $menuToggle.on('click', function (e) {
            e.preventDefault();
            $mainNav.toggleClass('active');
            $(this).toggleClass('active');

            // Adiciona aria-expanded para acessibilidade
            const isExpanded = $mainNav.hasClass('active');
            $(this).attr('aria-expanded', isExpanded);
        });

        // Fecha menu ao clicar em um link (em mobile)
        $mainNav.find('a').on('click', function () {
            if (window.innerWidth <= 768) {
                $mainNav.removeClass('active');
                $menuToggle.removeClass('active').attr('aria-expanded', 'false');
            }
        });

        // Fecha menu ao redimensionar para desktop
        let resizeTimer;
        $(window).on('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                if (window.innerWidth > 768) {
                    $mainNav.removeClass('active');
                    $menuToggle.removeClass('active').attr('aria-expanded', 'false');
                }
            }, 250);
        });

        // Fecha menu ao clicar fora (em mobile)
        $(document).on('click', function (e) {
            if (window.innerWidth <= 768) {
                if (!$(e.target).closest('.site-header').length) {
                    $mainNav.removeClass('active');
                    $menuToggle.removeClass('active').attr('aria-expanded', 'false');
                }
            }
        });
    }
}

// Detectar orientação e ajustar viewport em mobile
function handleOrientationChange() {
    if (window.matchMedia('(max-width: 768px)').matches) {
        // Adiciona classe ao body para CSS específico
        if (window.orientation === 0 || window.orientation === 180) {
            $('body').removeClass('landscape').addClass('portrait');
        } else {
            $('body').removeClass('portrait').addClass('landscape');
        }
    }
}

// Listener para mudança de orientação
$(window).on('orientationchange resize', handleOrientationChange);

// Executa na carga
handleOrientationChange();


// ============================================
// 5. HANDLERS PARA MENU E BUSCA
// ============================================

// Links com data-url (navegação customizada)
$(document).on('click', '.url-link', function (e) {
    const url = $(this).data('url');
    if (url && url !== '#/') {
        e.preventDefault();
        window.location.href = url;
    }
});

// Busca - agora usa submit nativo do formulário (GET)
// Código removido - os formulários agora usam method="get" e action correto

// ============================================
// 5.5 LOAD MORE HOME
// ============================================
window.BahiaLoadMore = {
    state: {
        loading: false,
        hasMore: true,
        loadedIds: new Set(),
        postsPerLoad: 15
    },

    init: function() {
        var self = this;

        // Registrar posts iniciais
        var hiddenIds = $('#ids').val();
        if (hiddenIds) {
            hiddenIds.split(',').forEach(function (id) {
                var numId = parseInt(id.trim());
                if (numId > 0) self.state.loadedIds.add(numId);
            });
        }

        // Click no botão
        $(document).on('click', '#load-more-btn', function (e) {
            e.preventDefault();
            self.loadMore();
        });
    },

    loadMore: function() {
        var self = this;

        if (this.state.loading || !this.state.hasMore) {
            return;
        }

        this.state.loading = true;
        $('#load-more-btn').prop('disabled', true).addClass('loading');
        $('.imgLoader').fadeIn(200);

        var excludeIds = Array.from(this.state.loadedIds).join(',');

        $.ajax({
            url: bahiaThemeData.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'bahia_infinite_scroll',
                nonce: bahiaThemeData.infiniteScrollNonce,
                post_type: bahiaThemeData.postTypesList || '',
                posts_per_page: self.state.postsPerLoad,
                exclude_ids: excludeIds,
                is_mobile: false,
                is_multi_post_type: true
            },
            timeout: 15000,
            success: function (response) {
                if (!response.success || !response.data || !response.data.html || response.data.count === 0) {
                    self.state.hasMore = false;
                    $('#load-more-btn').fadeOut();
                    $('.no-more-posts-message').fadeIn();
                    return;
                }

                // Adicionar IDs
                if (response.data.ids && Array.isArray(response.data.ids)) {
                    response.data.ids.forEach(function (id) {
                        self.state.loadedIds.add(id);
                    });
                }

                // Adicionar HTML
                $('#posts-container').append(response.data.html);
                $('#ids').val(Array.from(self.state.loadedIds).join(','));

                self.state.hasMore = response.data.has_more;

                if (!self.state.hasMore) {
                    $('#load-more-btn').fadeOut();
                    $('.no-more-posts-message').fadeIn();
                }
            },
            error: function () {
                alert('Erro ao carregar notícias. Tente novamente.');
            },
            complete: function () {
                $('.imgLoader').fadeOut(200);
                $('#load-more-btn').prop('disabled', false).removeClass('loading');
                self.state.loading = false;
            }
        });
    }
};

$(document).ready(function () {
    // Inicializa se o botão "Ver Mais" existir na página
    if ($('#load-more-btn').length > 0) {
        window.BahiaLoadMore.init();
    }
});

// ============================================
// 6. EXPORTS GLOBAIS
// ============================================

// Expõe funções para uso em inline scripts do WordPress
window.BahiaTheme = {
    init: initTheme,
    backToTop: initBackToTop,
    version: '1.0.0'
};