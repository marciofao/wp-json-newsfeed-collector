<?php

function nc_fetch_terms($endpoint, $taxonomy, $remote_type, $meta_key) {
    $page = 1;
    $per_page = 100;

    while (true) {
        $url = rtrim($endpoint, '/') . "/wp-json/wp/v2/{$remote_type}?per_page=$per_page&page=$page";
        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            break;
        }

        $terms = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($terms)) {
            break;
        }

        foreach ($terms as $remote_term) {
            $term = get_term_by('slug', $remote_term['slug'], $taxonomy);
            $args = [
                'description' => $remote_term['description'],
                'slug'        => $remote_term['slug'],
            ];
            // Add parent for categories if present
            if ($taxonomy === 'category' && isset($remote_term['parent'])) {
                $args['parent'] = $remote_term['parent'];
            }
            if (!$term) {
                $result = wp_insert_term($remote_term['name'], $taxonomy, $args);
                if (is_wp_error($result)) {
                    continue;
                }
                $term_id = $result['term_id'];
            } else {
                $term_id = $term->term_id;
            }
            update_term_meta($term_id, $meta_key, $remote_term['id']);
        }

        $page++;
    }
}

// Usage:
function nc_fetch_tags_and_categs($endpoint) {
    nc_fetch_terms($endpoint, 'post_tag', 'tags', '_remote_tag_id');
    nc_fetch_terms($endpoint, 'category', 'categories', '_remote_cat_id');
}