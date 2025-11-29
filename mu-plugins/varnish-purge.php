<?php
/**
 * Plugin Name: Varnish Auto Purge
 * Description: Limpa cache do Varnish automaticamente ao publicar/atualizar conteúdo
 * Version: 1.0
 * Author: Bahia News
 */

if (!defined('ABSPATH')) exit;

class BahiaVarnishPurge {
    
    private $varnish_host = 'varnish';
    private $varnish_port = 80;
    
    public function __construct() {
        add_action('save_post', [$this, 'purge_post'], 10, 2);
        add_action('deleted_post', [$this, 'purge_post_by_id']);
        add_action('wp_trash_post', [$this, 'purge_post_by_id']);
        add_action('edit_term', [$this, 'purge_term'], 10, 3);
        add_action('delete_term', [$this, 'purge_term'], 10, 3);
        add_action('admin_bar_menu', [$this, 'add_admin_bar_purge'], 100);
        add_action('admin_post_varnish_purge_all', [$this, 'purge_all_admin']);
        add_action('admin_notices', [$this, 'show_purge_notice']);
    }
    
    public function purge_post($post_id, $post = null) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        
        if (!$post) {
            $post = get_post($post_id);
        }
        
        if (!$post || $post->post_status !== 'publish') {
            return;
        }
        
        $urls = [
            get_permalink($post_id),
            home_url('/'),
        ];
        
        if ($post->post_type === 'post') {
            $categories = get_the_category($post_id);
            foreach ($categories as $cat) {
                $urls[] = get_category_link($cat->term_id);
            }
            
            $tags = get_the_tags($post_id);
            if ($tags) {
                foreach ($tags as $tag) {
                    $urls[] = get_tag_link($tag->term_id);
                }
            }
        }
        
        $urls[] = get_bloginfo('rss2_url');
        
        foreach (array_unique($urls) as $url) {
            $this->purge_url($url);
        }
    }
    
    public function purge_post_by_id($post_id) {
        $this->purge_post($post_id);
    }
    
    public function purge_term($term_id, $tt_id, $taxonomy) {
        $term_link = get_term_link($term_id, $taxonomy);
        if (!is_wp_error($term_link)) {
            $this->purge_url($term_link);
            $this->purge_url(home_url('/'));
        }
    }
    
    private function purge_url($url) {
        if (empty($url)) return false;
        
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '/';
        
        if (isset($parsed['query'])) {
            $path .= '?' . $parsed['query'];
        }
        
        $purge_url = "http://{$this->varnish_host}:{$this->varnish_port}{$path}";

	// CRÍTICO: Purge para DESKTOP
	$response_desktop = wp_remote_request($purge_url, [
		'method' => 'PURGE',
		'timeout' => 5,
		'headers' => [
			'Host' => $parsed['host'] ?? '',
			'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)' // Desktop
		]
	]);

	// CRÍTICO: Purge para MOBILE
	$response_mobile = wp_remote_request($purge_url, [
		'method' => 'PURGE',
		'timeout' => 5,
		'headers' => [
			'Host' => $parsed['host'] ?? '',
			'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)' // Mobile
		]
	]);

	return !is_wp_error($response_desktop) && !is_wp_error($response_mobile);	
    }
    
    public function purge_all() {
        wp_remote_request("http://{$this->varnish_host}:{$this->varnish_port}/.*", [
            'method' => 'PURGE',
            'timeout' => 10,
        ]);
        return true;
    }
    
    public function add_admin_bar_purge($wp_admin_bar) {
        if (!current_user_can('manage_options')) return;
        
        $wp_admin_bar->add_node([
            'id' => 'varnish-purge',
            'title' => '🔄 Limpar Cache',
            'href' => wp_nonce_url(admin_url('admin-post.php?action=varnish_purge_all'), 'varnish_purge'),
        ]);
    }
    
    public function purge_all_admin() {
        if (!current_user_can('manage_options') || !check_admin_referer('varnish_purge')) {
            wp_die('Sem permissão');
        }
        
        $this->purge_all();
        
        set_transient('varnish_purge_success', true, 30);
        wp_redirect(admin_url());
        exit;
    }
    
    public function show_purge_notice() {
        if (get_transient('varnish_purge_success')) {
            delete_transient('varnish_purge_success');
            echo '<div class="notice notice-success is-dismissible"><p><strong>Cache Varnish limpo com sucesso!</strong></p></div>';
        }
    }
}

new BahiaVarnishPurge();
