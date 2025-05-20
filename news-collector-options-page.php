<?php

// Add submenu item under Tools for the News Collector options page
function news_collector_tools_menu()
{
    add_submenu_page(
        'tools.php', // Parent slug
        'Coletor Notícias', // Page title
        'Coletor Notícias', // Menu title
        'manage_options', // Capability
        'news-collector-options', // Menu slug
        'news_collector_options_page' // Callback function
    );
}
add_action('admin_menu', 'news_collector_tools_menu');

// Add settings link to the plugin entry in the plugins list page
function news_collector_settings_link($links)
{
    $settings_link = '<a href="tools.php?page=news-collector-options">Configurações</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'news_collector_settings_link');

// Create the options page
function news_collector_options_page()
{
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    // Save settings if the form is submitted
    if (isset($_POST['submit'])) {
        update_option('nc_external_wordpress_endpoint', sanitize_text_field($_POST['nc_external_wordpress_endpoint']));
        update_option('nc_external_wordpress_per_page', intval($_POST['nc_external_wordpress_per_page']));
        update_option('nc_external_wordpress_tags', sanitize_text_field($_POST['nc_external_wordpress_tags']));
        update_option('nc_external_wordpress_tags', sanitize_text_field($_POST['external_wordpress_categories']));
        update_option('nc_recurrence', sanitize_text_field($_POST['nc_recurrence']));

        nc_do_news_collector_scheduling_event();

        echo '<div class="updated"><p>Configurações salvas.</p></div>';
    }

    // Trigger news collection if the button is clicked
    if (isset($_POST['collect_news'])) {
        do_action('nc_collect_external_wordpress_posts_cron');
        echo '<div class="updated"><p>Coleta de notícias finalizada! 👍</p></div>';
    }

    // Get current option values
    $endpoint = get_option('nc_external_wordpress_endpoint', '');
    $per_page = get_option('nc_external_wordpress_per_page', 30);
    $tags = get_option('nc_external_wordpress_tags', '');
    $categories = get_option('external_wordpress_categories', '');

?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="nc_external_wordpress_endpoint">Endereço WordPress Externo *</label></th>
                    <td><input name="nc_external_wordpress_endpoint" type="text" id="nc_external_wordpress_endpoint" value="<?php echo esc_attr($endpoint); ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="nc_external_wordpress_per_page">Quant. Notícias buscar por vez:*</label></th>
                    <td><input name="nc_external_wordpress_per_page" type="number" id="nc_external_wordpress_per_page" value="<?php echo esc_attr($per_page); ?>" class="small-text"  required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="nc_external_wordpress_tags">Tags</label></th>
                    <td>
                        <input name="nc_external_wordpress_tags" type="text" id="nc_external_wordpress_tags" value="<?php echo esc_attr($tags); ?>" class="regular-text" >
                        <br>
                        <small>ID numérico das tags separadas por virgula, ou vazio para buscar todas as notícias sem filtro</small>
                    </td>
                    
                </tr>
                <tr>
                    <th scope="row"><label for="external_wordpress_categories">Categorias</label></th>
                    <td>
                        <input name="external_wordpress_categories" type="text" id="external_wordpress_categories" value="<?php echo esc_attr($categories); ?>" class="regular-text" >
                        <br>
                        <small>ID numérico das categorias separadas por virgula, ou vazio para buscar todas as notícias sem filtro</small>
                    </td>
                    
                </tr>
                <tr>
                    <th scope="row"><label for="nc_recurrence">Recorrência</label></th>
                    <td>
                        <select name="nc_recurrence" id="nc_recurrence">
                            <option value="hourly" <?php selected(get_option('nc_recurrence', 'daily'), 'hourly'); ?>>Horária</option>
                            <option value="twicedaily" <?php selected(get_option('nc_recurrence', 'daily'), 'twicedaily'); ?>>Duas vezes ao dia</option>
                            <option value="daily" <?php selected(get_option('nc_recurrence', 'daily'), 'daily'); ?>>Diária</option>
                            <option value="weekly" <?php selected(get_option('nc_recurrence', 'daily'), 'weekly'); ?>>Semanal</option>
                        </select>
                    </td>
                </tr>

            </table>
            <?php submit_button('Salvar Configurações'); ?>
        </form>
        <form method="post" action="">
            <?php submit_button('Coletar Notícias Agora', 'secondary', 'collect_news'); ?>
        </form>
    </div>
<?php
}
