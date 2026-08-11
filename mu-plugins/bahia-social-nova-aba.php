<?php
/**
 * Plugin Name: Bahia.ba - Redes sociais em nova aba (cabeçalho e rodapé)
 * Description: Garante target="_blank" + rel="noopener noreferrer" em todo link de rede
 *              social do site.
 *
 * ---------------------------------------------------------------------------
 * POR QUE ISTO EXISTE, SE "JÁ TINHA SIDO FEITO"
 *
 * Estado encontrado na rodada 6, lendo o HTML servido de hml.bahia.ba:
 *
 *   cabeçalho (bloco tdi_50)  <a href="https://www.facebook.com/bahiapontoba"  title="Facebook" ...>
 *                             -> SEM target, SEM rel
 *   rodapé    (bloco tdi_159) <a href="..." target="_blank" title="Facebook" ...>
 *                             -> COM target, SEM rel
 *
 * Ou seja: não é uma regressão dos dois, são dois defeitos diferentes. O rodapé foi
 * corrigido de fato (o `target` está lá) mas nunca ganhou o `rel`. O cabeçalho não foi
 * corrigido no template que renderiza — é o padrão que o AUDITORIA-templates.md descreve:
 * o header VIVO é o tdb_template 547414, e os "Default PRO" (entre eles o header 547301)
 * são código morto. Uma correção feita no 547301 não aparece em lugar nenhum. Mesma
 * armadilha do Pinterest.
 *
 * A opção "abrir em nova aba" do bloco social do tagDiv é um atributo salvo DENTRO do
 * tdb_template, no banco. Corrigir por lá significaria (a) editar o 547414 pelo painel,
 * o que faz o tagDiv RENUMERAR os `.tdi_NN` e pode derrubar CSS ancorado neles, e
 * (b) somar mais uma alteração de banco ao inventário de migração para produção. Uma
 * reescrita no HTML de saída viaja no git, vale para cabeçalho e rodapé de uma vez, e
 * continua valendo se o template for reeditado.
 *
 * O `rel="noopener"` não é detalhe: sem ele a página aberta recebe `window.opener` e pode
 * navegar a aba de origem para outro endereço.
 *
 * @see bahia-html-saida.php  buffer de saída único do site (filtro bahia_hs_html)
 * @see scratchpad/AUDITORIA-templates.md
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Domínios tratados como rede social externa. */
function bahia_social_dominios() {
    return array(
        'facebook.com', 'instagram.com', 'twitter.com', 'x.com', 'youtube.com',
        'linkedin.com', 'tiktok.com', 'threads.net', 'threads.com', 'whatsapp.com',
        'telegram.me', 't.me', 'pinterest.com', 'flipboard.com', 'bsky.app',
    );
}

/**
 * Acrescenta target/rel aos links de rede social do HTML servido.
 *
 * Escopo deliberadamente estreito: só <a> cujo href aponta para um dos domínios acima.
 * Não mexe em link nenhum de conteúdo editorial que porventura cite uma rede social? —
 * mexe, sim, e isso é aceitável: abrir rede social em nova aba é o comportamento
 * desejado em qualquer lugar do site. O que NÃO é tocado são os links de
 * compartilhamento gerados com `sharer.php`/`intent/tweet`, que o tema já abre em popup
 * por JS; um `target` ali seria redundante mas inofensivo, e por isso ficam de fora
 * apenas para não alterar comportamento já validado.
 */
function bahia_social_nova_aba($html) {
    $dominios = bahia_social_dominios();

    return preg_replace_callback('/<a\b[^>]*>/i', function ($m) use ($dominios) {
        $tag = $m[0];

        if (!preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/i', $tag, $h)) {
            return $tag;
        }
        $url  = html_entity_decode($h[2], ENT_QUOTES, 'UTF-8');
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return $tag;
        }
        $host = strtolower(preg_replace('/^www\./', '', $host));

        $ehSocial = false;
        foreach ($dominios as $d) {
            if ($host === $d || substr($host, -strlen('.' . $d)) === '.' . $d) {
                $ehSocial = true;
                break;
            }
        }
        if (!$ehSocial) {
            return $tag;
        }

        // Links de compartilhamento: o tema já os abre em popup por JS. Não mexer.
        if (preg_match('#(sharer\.php|/intent/|/share|share\?|/sharing/)#i', $url)) {
            return $tag;
        }

        // target: acrescenta se não houver; se houver outro valor, respeita o autor.
        if (!preg_match('/\btarget\s*=/i', $tag)) {
            $tag = preg_replace('/^<a\b/i', '<a target="_blank"', $tag, 1);
        }

        // rel: funde com o que já existir, sem duplicar.
        if (preg_match('/\brel\s*=\s*(["\'])(.*?)\1/i', $tag, $r)) {
            $vals = preg_split('/\s+/', trim($r[2]), -1, PREG_SPLIT_NO_EMPTY);
            foreach (array('noopener', 'noreferrer') as $novo) {
                if (!in_array($novo, $vals, true)) {
                    $vals[] = $novo;
                }
            }
            $tag = str_replace($r[0], 'rel="' . implode(' ', $vals) . '"', $tag);
        } else {
            $tag = preg_replace('/^<a\b/i', '<a rel="noopener noreferrer"', $tag, 1);
        }

        return $tag;
    }, $html);
}
add_filter('bahia_hs_html', 'bahia_social_nova_aba');
