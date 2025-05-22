<?php function nc_attach_featured_media($post_id, $media_api_url) {
    // Include image.php to use wp_generate_attachment_metadata()
    if (!function_exists('download_url')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }
    if (!function_exists('wp_generate_attachment_metadata')) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
    }
    if (!function_exists('media_handle_sideload')) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
    }
    // Check if the media URL is valid
    if (empty($media_api_url)) {
        return;
    }

    $args = array(
        'timeout' => 10, // Set wait timeout for curl response
    );

    $response = wp_remote_get($media_api_url, $args);
    if (is_wp_error($response)) {
        
        error_log('Error fetching external WordPress media post: ' . $response->get_error_message());
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $post = json_decode($body, true);

    $media_url = $post['guid']['rendered'];

    // Get the file name from the URL
    $file_name = basename($media_url);

    // Check if the file already exists in the media library
    $attachment_id = attachment_url_to_postid($media_url);
    if ($attachment_id) {
        // If it exists, set it as the featured image
        set_post_thumbnail($post_id, $attachment_id);
        return $attachment_id;
    }

    // Download the image to a temporary location
    $tmp = download_url($media_url);
    // Check for errors
    if (is_wp_error($tmp)) {
        error_log('Error downloading image: ' . $tmp->get_error_message());
        return;
    }

    // Prepare an array of post data for the attachment
    $file_array = array(
        'name'     => $file_name,
        'tmp_name' => $tmp,
    );

    // Move the file to the uploads directory
    $upload = wp_handle_sideload($file_array, array('test_form' => false));
    if (isset($upload['error'])) {
        error_log('Upload error: ' . $upload['error']);
        @unlink($tmp);
        return;
    }

    // Prepare attachment data
    $attachment = array(
        'post_mime_type' => $upload['type'],
        'post_title'     => sanitize_file_name($file_name),
        'post_content'   => '',
        'post_status'    => 'inherit'
    );

    // Insert the attachment into the media library
    $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);

    // Generate metadata for the attachment
    $attach_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);

    // Update the attachment metadata in the database
    wp_update_attachment_metadata($attachment_id, $attach_data);

    // Set the attachment as the featured image for the post
    set_post_thumbnail($post_id, $attachment_id);

    // Clean up the temporary file if it still exists
    if (file_exists($tmp)) {
        @unlink($tmp);
    }

    // Return the attachment ID
    return $attachment_id;
}

function nc_attach_external_image_as_featured($post_id, $media_api_url) {

    $args = array(
        'timeout' => 10, // Set wait timeout for curl response
    );

    $response = wp_remote_get($media_api_url, $args);
    if (is_wp_error($response)) {
        error_log('Error fetching external WordPress media post: ' . $response->get_error_message());
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $post = json_decode($body, true);

    $image_url = $post['guid']['rendered'];

    // Check if an attachment already exists for this URL
    global $wpdb;
    $attachment_id = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND guid = %s LIMIT 1",
        $image_url
    ));

    if (!$attachment_id) {
        // Create attachment post
        $attachment = array(
            'guid'           => $image_url,
            'post_mime_type' => 'image/jpeg', // You may want to detect mime type
            'post_title'     => sanitize_file_name(basename($image_url)),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_type'      => 'attachment',
        );
        $attachment_id = wp_insert_post($attachment);

        // Optionally, add meta so WP knows it's an image
        update_post_meta($attachment_id, '_wp_attached_file', $image_url);
        update_post_meta($attachment_id, '_wp_attachment_metadata', array(
            'width' => 0,
            'height' => 0,
            'file' => $image_url,
            'sizes' => array(),
        ));
    }

    // Set as featured image
    set_post_thumbnail($post_id, $attachment_id);
}