<?php 

// Schedule the cron job
if (!wp_next_scheduled('nc_collect_external_wordpress_posts_cron')) {
   nc_do_news_collector_scheduling_event();
}

function nc_do_news_collector_scheduling_event() {
    $recurrence = get_option('nc_recurrence');
    if (!$recurrence) {
        error_log('nc_recurrence option is not set');
        return;
    }
    wp_clear_scheduled_hook('nc_collect_external_wordpress_posts_cron');
    wp_schedule_event(time(), $recurrence, 'nc_collect_external_wordpress_posts_cron');
}

// Hook the function to the cron event
add_action('nc_collect_external_wordpress_posts_cron', 'nc_collect_external_wordpress_posts');

function nc_collect_external_wordpress_posts(){

    require_once('nc-media-upload.php');
    require_once('fetch-tags-and-categs.php');
    
    $endpoint = get_option('nc_external_wordpress_endpoint');
    $per_page = get_option('nc_external_wordpress_per_page', 30);
    $tags = get_option('nc_external_wordpress_tags');
    $categories = get_option('nc_external_wordpress_categories');
    //TODO: optimize this function to fetch tags and categories and avoid script timeout
    
    if (!$endpoint) {
        error_log('External WordPress endpoint not set in wp-options');
        return;
    }
    
    nc_fetch_tags_and_categs($endpoint);

    $chunk_size = 10;
    $total_pages = ceil($per_page / $chunk_size);


    for ($page = 1; $page <= $total_pages; $page++) {
        $url = trailingslashit($endpoint) . "wp-json/wp/v2/posts?per_page={$chunk_size}&page={$page}";
        if($tags){
            $url .= '&tags='.$tags;
        }
        if($categories){
            $url .= '&categories='.$categories;
        }
        error_log('Running news collector on: '.$url);

        $args = array(
            'timeout' => 15, // Set wait timeout for curl response
        );
        
        $response = wp_remote_get($url, $args);
        if (is_wp_error($response)) {
            error_log('Error fetching external WordPress posts: ' . $response->get_error_message());
            continue;
        }

        $body = wp_remote_retrieve_body($response);

        $posts = json_decode($body, true);
        
        if (!is_array($posts)) {
            error_log('Invalid response from external WordPress API');
            continue;
        }

        $all_post_ids = array_map(function($post) {
            return isset($post['id']) ? $post['id'] : null;
        }, $posts);

        global $wpdb;
        $existing_post_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT meta_value FROM $wpdb->postmeta 
            WHERE meta_key = 'external_post_id' 
            AND meta_value IN (" . implode(',', array_fill(0, count($all_post_ids), '%s')) . ")",
            $all_post_ids
        ));

        // Fetch all local tags and categories with remote IDs before processing posts
        $local_tags = get_terms([
            'taxonomy'   => 'post_tag',
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key'     => '_remote_tag_id',
                    'compare' => 'EXISTS',
                ]
            ]
        ]);
        $remote_tag_map = [];
        foreach ($local_tags as $term) {
            $remote_id = get_term_meta($term->term_id, '_remote_tag_id', true);
            if ($remote_id) {
                $remote_tag_map[$remote_id] = $term->term_id;
            }
        }

        $local_cats = get_terms([
            'taxonomy'   => 'category',
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key'     => '_remote_cat_id',
                    'compare' => 'EXISTS',
                ]
            ]
        ]);
        $remote_cat_map = [];
        foreach ($local_cats as $term) {
            $remote_id = get_term_meta($term->term_id, '_remote_cat_id', true);
            if ($remote_id) {
                $remote_cat_map[$remote_id] = $term->term_id;
            }
        }

        foreach ($posts as $post) {

            if(!is_array($post)){
                error_log('Invalid post data: '. $post);
                continue;
            }

            if(in_array($post['id'], $existing_post_ids)) {
                error_log('Post already exists: '. $post['id']);
                continue;
            }

            $post_tags = $post['tags'] ?? [];
            $post_categories = $post['categories'] ?? [];

            // Map remote tag/category IDs to local term IDs using the pre-fetched maps
            $local_tag_ids = [];
            foreach ($post_tags as $remote_tag_id) {
                if (isset($remote_tag_map[$remote_tag_id])) {
                    $local_tag_ids[] = $remote_tag_map[$remote_tag_id];
                }
            }

            $local_cat_ids = [];
            foreach ($post_categories as $remote_cat_id) {
                if (isset($remote_cat_map[$remote_cat_id])) {
                    $local_cat_ids[] = $remote_cat_map[$remote_cat_id];
                }
            }

            $new_post = array(
                'post_title'    => $post['title']['rendered'],
                'post_content'  => $post['content']['rendered'],
                'post_excerpt'  => strip_tags($post['excerpt']['rendered']),
                'post_status'   => 'publish',
                'post_author'   => 1, // Set to an appropriate user ID
                'post_date'     => $post['date'],
                'post_type'     => 'post',
                'tags_input'    => $local_tag_ids,
                'post_category' => $local_cat_ids,
            );

            $post_id = wp_insert_post($new_post);

            if (!is_wp_error($post_id)) {
                update_post_meta($post_id, 'external_post_id', $post['id']);
                update_post_meta($post_id, 'external_post_url', $post['link']);
                
                
                if (isset($post['custom_featured_images']) && !empty($post['custom_featured_images'])) {
                    update_post_meta($post_id, 'custom_featured_images', $post['custom_featured_images']); 
                    // Fetch and upload the custom featured image
                    $image_url = $post['custom_featured_images'];
                    $image_id = media_sideload_image($image_url, $post_id, null, 'id');
            
                    if (!is_wp_error($image_id)) {
                        // Set the uploaded image as the featured image
                        set_post_thumbnail($post_id, $image_id);
                    } else {
                        error_log('Error uploading featured image: ' . $image_id->get_error_message());
                    }
                }
                
                if(isset($post['_links']['wp:featuredmedia'][0]['href']) && !empty($post['_links']['wp:featuredmedia'][0]['href'])){
                    $image_post_url = $post['_links']['wp:featuredmedia'][0]['href'];
                    update_post_meta($post_id, 'nc_featured_media', $image_post_url);
                    nc_attach_featured_media($post_id, $image_post_url);
                }
               
            }
            error_log('Post imported: '. $post['id'].' - '. $post_id);  
        }

        if (count($posts) < $chunk_size) {
            break; // Exit the loop if we've received fewer posts than requested
        }
    }

    error_log('News collector finished');
}

if(isset($_GET['debug_collector'])){
    nc_collect_external_wordpress_posts();
}