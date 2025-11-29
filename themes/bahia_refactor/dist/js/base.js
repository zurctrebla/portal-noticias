$(document).ready(function () {
    $(".linkMenu").on('click', function () {
        var url = $(this).attr('data-url');
        window.location = url;
    });

    transitionend = 'otransitionend oTransitionEnd msTransitionEnd transitionend webkitTransitionEnd';
    animationend = 'oanimationend oAnimationEnd msAnimationEnd animationendend webkitAnimationEnd';

    var header = $('header');

    header.on('mouseover', '.bt-menu', function () {
        $('.nav-drop, .bt-menu', header).addClass('ativo');
    }).on('mouseleave', '.container', function () {
        $('.nav-drop, .bt-menu, .bt-menu-mobile', header).removeClass('ativo');
    });

    $('.bt-menu-mobile').on('click', function () {
        if ($('.nav-drop').hasClass('ativo')) {
            $('.nav-drop, .bt-menu-mobile').removeClass('ativo');
        } else {
            $('.nav-drop, .bt-menu-mobile').addClass('ativo');
        }
    });

    header.on('click', 'ul.menu-principal li.drop>a', function () {
        $('ul.menu-principal li a', header).removeClass('ativo');
        $(this).parent().addClass('ativo');
        return false;
    });
    header.on('click', '.buscaNav button', function () {
        $(this).parents('form').submit();
    });

    // Eventos de click globais
    function handleComment(event, foo) {
        var pai = event.data.elem.parent();
        // COMMENT
        if (!$(event.target).closest(pai).length) {
            pai.find('[class^="box-comment-"]').removeClass('ativo');
            if (!$('[class^="box-comment-"]').hasClass('ativo')) {
                $(document).off('click.handleComment');
            }

        }
    };

    var interacoes = $('.barra-interacao');
    interacoes.on('click', '.flag-retado-horizontal, .flag-porreta-horizontal', function () {
        elem = $(this);
        if (elem.hasClass('flag-retado-horizontal')) {
            var clicklike = 'naocurti';
        } else {
            var clicklike = 'curti';
        }
        if (!elem.hasClass('clicado')) {
            var url = elem.attr('data-url');
            $.post(url, { like: clicklike }).done(function (data) {

                var count = parseInt(elem.find('.qtd strong').text());
                count = count + 1;
                elem.find('.qtd strong').html(count);
                elem.addClass('clicado').parent().find('[class^="box-comment-"]').toggleClass('ativo');
                elem.parent().find('[class^="box-comment-"] form').append('<input type="hidden" name="opinion_id" value="' + data + '" />');

            });
            $(document).on('click.handleComment', { elem: elem }, handleComment);
        } else {
            $.post(url).done(function () {
                elem.removeClass('clicado').parent().find('[class^="box-comment-"]').toggleClass('ativo');
            });
        }
    });

    // VALIDAÇÃO FORMULÁRIO
    $(document).on('submit', '.validar', function () {
        campos = [];
        form = false;
        var Pai = this;
        var msgForm = "";
        jQuery(".obg", this).each(function () {
            if (!$(this).hasClass('select')) {
                jQuery(this).removeClass("naopreenchido");
                if (jQuery.trim(jQuery(this).val()) == "") {
                    jQuery(this).addClass("naopreenchido");
                    if ($(this).attr('type') == 'file') {
                        $(this).parent('.labelFile').addClass("naopreenchido");
                    }
                    form = true;
                }
            }
        });
        if (form == true) {
            msgForm += "erro não preenchido";
        }
        if (jQuery('.cp_email', Pai).length) {
            jQuery(".cp_email", this).each(function () {
                var email = jQuery(this).val();
                if (email.indexOf("@") == -1 || email.indexOf(".") == -1) {
                    msgForm += "erro EMAIL";
                    jQuery(this).addClass("naopreenchido");
                }
            });
        }
        if (msgForm != "") {
            $(".feedback", Pai).html('Preencha os campos corretamente.').stop(true, true).fadeIn(500, function () {
                $(this).delay(4000).fadeOut(400);
            });
        } else {
            $(Pai).find('input[type=submit]').addClass('idle');
            $.ajax({
                type: 'POST',
                url: $(Pai).attr('action'),
                data: $(Pai).serialize(),
                complete: function (data) {
                    if ($(Pai).parent('.box-comment-retado')) {
                        $(Pai).prev('.titulo-main').html('Não curti');
                    } else {
                        $(Pai).prev('.titulo-main').html('Curti');
                    }
                    $(Pai).parent().find('.comentario-sucesso').html(
                        '<p class="titulo">Obrigado por colaborar com o Bahia.ba</p>' +
                        '<p class="titulo">Seu comentário será analisado, e publicado caso não infrinja os <a href="#">Princípios Editoriais</a> do Bahia.ba</p>'
                    );
                    $(Pai).fadeOut(300, function () {
                        $(Pai).parent().find('.comentario-sucesso').fadeIn('fast');
                    });
                }
            });
        }
        return false;
    });

    $('a.box-categoria').on('click', function (event) {
        var $this = $(this);
        var target = $(this).attr("data-target");
        event.preventDefault();

        $this.siblings().addClass('disable');
        $this.removeClass('disable');

        $this.parent().find('.cont-tab').not('#' + target).hide();
        $('#' + target).fadeIn();
    });


    $('.mashsb-buttons a').on('click', function (b) {
        winWidth = 520, winHeight = 550;
        var c = screen.height / 2 - winHeight / 2,
            d = screen.width / 2 - winWidth / 2,
            e = $(this).attr("href");
        return window.open(e, "sharer", "top=" + c + ",left=" + d + ",toolbar=0,status=0,width=" + winWidth + ",height=" + winHeight),
            b.preventDefault(b), !1
    });

    // LIGHTBOX
    $(document).on('click', '.bt_play', function () {
        var urlvideo = $(this).attr('href');
        $('body').append('<div class="lightbox"><div class="cont_lb"><iframe src="' + urlvideo + '?title=0&amp;byline=0&amp;portrait=0&amp;autoplay=1" width="810" height="455" frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe></div><a href="#" class="bt_fechar_lb"><span>Fechar</span></a></div>');
        $('.lightbox').fadeIn('fast');
        $(document).on('click.handleLightbox', handleLightbox);
        return false;
    });

    // Eventos de click globais
    function handleLightbox(event) {
        // COMMENT
        if (!$(event.target).closest('.cont_lb').length) {
            $('.lightbox').stop().fadeOut('fast', function () {
                $('.lightbox').remove();
            });
            $(document).off('handleLightbox');
        }
    }

    $(".conteudo_post img").each(function () {
        if (parseInt($(this).width()) > parseInt($(".materia").width())) {
            $(this).width($(".materia").width());
            $(this).height("auto");
        }
    });

    $(".conteudo_post figure").each(function () {
        if (parseInt($(this).width()) > parseInt($(".materia").width())) {
            $(this).width($(".materia").width());
        }
    });

    $(".materia iframe").each(function () {
        if (parseInt($(this).attr('width')) > parseInt($(".materia").width())) {
            $(this).attr('width', $(".materia").width());
            $(this).attr('height', $(".materia").width() - 100);
        }
    });

    $("#btnCloseNotiApp").click(function () {
        $(".divNotification").hide();
    });

    $("#btnDownloadApp").click(function () {
        if (navigator.userAgent.toLowerCase().indexOf("android") > -1) {
            window.location.href = "market://details?id=ba.bahia";
        }
        if (navigator.userAgent.toLowerCase().indexOf("iphone") > -1) {
            //window.location.href = 'http://itunes.apple.com/lb/app/truecaller-caller-id-number/id448142450?mt=8';
        }
    });

    $(window).scroll(function () {
        if ($(this).scrollTop() != 0) {
            $('.divToTop').fadeIn();
        } else {
            $('.divToTop').fadeOut();
        }
    });

    $('.divToTop').click(function () {
        $('body,html').animate({ scrollTop: 0 }, 800);
    });

    $('.ui.dropdown.dropdownBtnSearchMobile').dropdown({
        onShow: function () {
            $(".divMenu").width($(".container").width() - 21);
        }
    });
    $('.ui.dropdown.dropdownBtnSearchIpad').dropdown({
        onShow: function () {
            $(".divMenu").width($(".container").width() - 430);
        }
    });

    $(".url-link").click(function () {
        var url = $(this).attr("data-url");
        $(location).attr("href", url);
    });

    $('.menuHeader').dropdown({
        on: 'hover'
    });

    $('.ui.dropdown.btnDropdownCategorias').dropdown();

    $('#left-menu')
        .sidebar('setting', {
            transition: 'push',
            onShow: function () {
                $(".pusher a").click(function (e) {
                    e.preventDefault();
                });
            },
            onHidden: function () {
                $(".pusher a")
                    .not('.tabular a')
                    .unbind('click');
            }
        })
        .sidebar('attach events', '.mobile-button')
        ;

    $("#btnSearch").click(function () {
        var url = $("#txtSearch").data("url") + "?s=" + $("#txtSearch").val();
        $(window.document.location).attr('href', url);
    });

    $("#btnSearch2").click(function () {
        var url = $("#txtSearch2").data("url") + "?s=" + $("#txtSearch2").val();
        $(window.document.location).attr('href', url);
    });

    $(document).on('keydown', function (event) {
        if (event.keyCode === 13) {
            if ($(".buscaNav").hasClass('visible')) {
                if ($("#txtSearch").val() != "") {
                    $("#btnSearch").click();
                } else if ($("#txtSearch2").val() != "") {
                    $("#btnSearch2").click();
                }
            }
        }
    });


    if ($(this).scrollTop() >= 280) {
        $('.menuNovo').fadeIn();
    }

    $(window).scroll(function () {
        if ($(this).scrollTop() >= 280) {
            $('.menuNovo').fadeIn();
        } else {
            $('.menuNovo').fadeOut();
        }
    });

    //    $('.ui.basic.modal')
    //      .modal('show')
    //    ;

});

function setCookie(name, value, duration) {
    var cookie = name + "=" + escape(value) +
        ((duration) ? "; duration=" + duration.toGMTString() : "");

    document.cookie = cookie;
}

function getCookie(name) {
    var cookies = document.cookie;
    var prefix = name + "=";
    var begin = cookies.indexOf("; " + prefix);

    if (begin == -1) {

        begin = cookies.indexOf(prefix);

        if (begin != 0) {
            return null;
        }

    } else {
        begin += 2;
    }

    var end = cookies.indexOf(";", begin);

    if (end == -1) {
        end = cookies.length;
    }

    return unescape(cookies.substring(begin + prefix.length, end));
}

function deleteCookie(name) {
    if (getCookie(name)) {
        document.cookie = name + "=" + "; expires=Thu, 01-Jan-70 00:00:01 GMT";
    }
}