<?php

/**
 * Plugin Name: Newsfeed Collector
 * Description: Collects newsfeed from various sources and displays them on the website.
 * Version: 1.2
 * Author: Marcio Fão
 * License: GPL2
 * Author uri: https://marciofao.github.io/
 */

add_action('plugins_loaded', function() {
    require_once('news-collector-options-page.php');
    require_once('news-collector.php');
    //TODO: Uncomment the following line to enable cleanup functionality
   // require_once('nc-cleanup.php');
});
