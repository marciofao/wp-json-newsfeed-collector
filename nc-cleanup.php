<?php

function nc_cleanup_unused_terms() {
    // Delete unused tags
    $unused_tags = get_terms([
        'taxonomy'   => 'post_tag',
        'hide_empty' => true,
        'fields'     => 'ids',
    ]);
    $all_tags = get_terms([
        'taxonomy'   => 'post_tag',
        'hide_empty' => false,
        'fields'     => 'ids',
    ]);
    $unused_tag_ids = array_diff($all_tags, $unused_tags);
    foreach ($unused_tag_ids as $tag_id) {
        wp_delete_term($tag_id, 'post_tag');
    }

    // Delete unused categories (except default category, usually ID 1)
    $unused_cats = get_terms([
        'taxonomy'   => 'category',
        'hide_empty' => true,
        'fields'     => 'ids',
        'exclude'    => [1], // Do not delete default category
    ]);
    $all_cats = get_terms([
        'taxonomy'   => 'category',
        'hide_empty' => false,
        'fields'     => 'ids',
        'exclude'    => [1],
    ]);
    $unused_cat_ids = array_diff($all_cats, $unused_cats);
    foreach ($unused_cat_ids as $cat_id) {
        wp_delete_term($cat_id, 'category');
    }
}

if(isset($_GET['cleanup_terms'])) {
    nc_cleanup_unused_terms();
    echo '<div class="updated"><p>Limpeza de termos concluída! 👍</p></div>';
}
