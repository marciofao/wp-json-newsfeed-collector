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
add_action('nc_collect_external_wordpress_posts_cron', 'collect_external_wordpress_posts');

function collect_external_wordpress_posts(){
    
    // DISABLED THE OPTION CHECK IF IS RUNNING, AS IT MAY BE CAUSING ISSUE OF NEVER UPDATING THE OPTION IN CASE OF MID-RUN ERROR
    // if (!isset($_GET['debug_collector']) && get_option('is_news_collector_running', false)) {
    //     error_log('News collector is already running');
    //     return;
    // }

    update_option('is_news_collector_running', true);

    $endpoint = get_option('nc_external_wordpress_endpoint');
    $per_page = get_option('nc_external_wordpress_per_page', 30);
    $tags = get_option('nc_external_wordpress_tags');
    $categories = get_option('external_wordpress_categories');

    if (!$endpoint) {
        error_log('External WordPress endpoint not set in wp-options');
        update_option('is_news_collector_running', false);
        return;
    }

   
    
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

        foreach ($posts as $post) {

            if(!is_array($post)){
                error_log('Invalid post data: '. $post);
                continue;
            }

            if(in_array($post['id'], $existing_post_ids)) {
                error_log('Post already exists: '. $post['id']);
                continue;
            }

            $new_post = array(
                'post_title' => $post['title']['rendered'],
                'post_content' => $post['content']['rendered'],
                'post_excerpt' => strip_tags($post['excerpt']['rendered']),
                'post_status'  => 'publish',
                'post_author'  => 1,                              // Set to an appropriate user ID
                'post_date'    => $post['date'],
                'post_type'    => 'post',
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
                    
                    update_post_meta($post_id, 'nc_featured_media', $post['_links']['wp:featuredmedia'][0]['href']);
                    $media_id = nc_attach_featured_media($post_id, $post['_links']['wp:featuredmedia'][0]['href']);
                }
               // die('output');

              //  update_post_meta($post_id, 'original_post_json', $post);    // may be too large for postmeta           
            }
           // die('end');
            error_log('Post imported: '. $post['id'].' - '. $post_id);  
        }

        if (count($posts) < $chunk_size) {
            break; // Exit the loop if we've received fewer posts than requested
        }
    }

    update_option('is_news_collector_running', false);
    error_log('News collector finished');
}

if(isset($_GET['debug_collector'])){
    collect_external_wordpress_posts();
}