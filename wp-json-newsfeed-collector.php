<?php

/**
 * Plugin Name: Newsfeed Collector
 * Description: Collects newsfeed from various sources and displays them on the website.
 * Version: 1.1
 * Author: Marcio Fão
 * License: GPL2
 * Author uri: https://marciofao.github.io/
 */

add_action('plugins_loaded', function() {
    require_once('news-collector-options-page.php');
    require_once('media-upload.php');
    require_once('news-collector.php');
});

add_filter('wp_get_attachment_url', function($url, $post_id) {
    $post = get_post($post_id);
    if ($post && $post->post_type === 'attachment') {
        // If the guid is an external URL, use it
        if (filter_var($post->guid, FILTER_VALIDATE_URL) && !str_contains($post->guid, home_url())) {
            return $post->guid;
        }
    }
    return $url;
}, 10, 2);