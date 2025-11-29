// ============================================
// BOOTSTRAP PRINCIPAL DO TEMA BAHIA
// ============================================

// 1. jQuery - DEVE ser o primeiro import
import $ from 'jquery';
import '@fontsource/lato';
import '../assets/js/infinite-scroll-editoria';

// Expõe jQuery globalmente IMEDIATAMENTE e SINCRONAMENTE
window.$ = $;
window.jQuery = $;

// Log para debug
console.log('✅ jQuery carregado:', $.fn.jquery);


// ============================================
// 2. SEMANTIC UI
// ============================================

// Semantic UI JS (depende do jQuery)
import 'semantic-ui-css/semantic.js';
console.log('✅ Semantic UI JS carregado');


// ============================================
// 3. INICIALIZAÇÃO
// ============================================

// Aguarda o DOM estar pronto
$(function () {
    console.log('🚀 DOM pronto! Inicializando componentes...');

    // Inicializa componentes do Semantic UI
    try {
        // Dropdowns
        if ($.fn.dropdown) {
            $('.ui.dropdown').dropdown();
            console.log('✅ Dropdowns inicializados');
        }

        // Modals
        if ($.fn.modal) {
            $('.ui.modal').modal();
            console.log('✅ Modals inicializados');
        }

        // Sidebar (menu mobile)
        if ($.fn.sidebar) {
            $('.ui.sidebar').sidebar('attach events', '.mobile-button');
            console.log('✅ Sidebar inicializado');
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
        console.error('❌ Erro ao inicializar Semantic UI:', error);
    }

    // Inicializa funcionalidades personalizadas do tema
    initTheme();
});


// ============================================
// 4. FUNÇÕES DO TEMA
// ============================================

function initTheme() {
    console.log('🎨 Inicializando tema Bahia...');

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

        console.log('✅ Botão "voltar ao topo" inicializado');
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

    console.log('✅ Smooth scroll inicializado');
}


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

// Busca
$(document).on('click', '#btnSearch, #btnSearch2', function (e) {
    e.preventDefault();
    const searchInput = $(this).siblings('input[type="text"]');
    const searchTerm = searchInput.val();
    const baseUrl = searchInput.data('url');

    if (searchTerm) {
        window.location.href = baseUrl + '/?s=' + encodeURIComponent(searchTerm);
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

console.log('🎉 Tema Bahia carregado com sucesso!');