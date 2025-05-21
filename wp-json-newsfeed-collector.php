<?php 

/**
 * Plugin Name: Newsfeed Collector
 * Description: Collects newsfeed from various sources and displays them on the website.
 * Version: 1.1
 * Author: Marcio Fão
 * License: GPL2
 * Author uri: https://marciofao.github.io/
 */

 add_action( 'init', function() {
    require_once('news-collector-options-page.php');
    require_once('media-upload.php');
    require_once('news-collector.php');
 } );
