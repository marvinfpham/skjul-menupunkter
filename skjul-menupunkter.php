<?php
/**
 * Plugin Name: Skjul Menupunkter
 * Plugin URI: https://magio.dk/
 * Description: Tilføjer en mulighed for at skjule menupunkter i WordPress-menuer. Skjulte menupunkter vil ikke vises på frontend, men forbliver synlige i backend.
 * Version: 1.0.4
 * Author: Magio
 * Author URI: https://magio.dk/
 * License: GPL2
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add custom field to menu items in the admin menu editor
add_filter('wp_nav_menu_item_custom_fields', function ($item_id, $item, $depth, $args) {
    $hide_item = get_post_meta($item_id, '_menu_item_hide', true);
    ?>
    <p class="field-hide-menu-item description description-wide">
        <label for="edit-menu-item-hide-<?php echo $item_id; ?>">
            <input type="checkbox" id="edit-menu-item-hide-<?php echo $item_id; ?>" 
                   name="menu-item-hide[<?php echo $item_id; ?>]" 
                   value="1" <?php checked($hide_item, 1); ?> />
            <?php _e('Skjul menu-punkt', 'skjul-menupunkter'); ?>
        </label>
    </p>
    <?php
}, 10, 4);

// Save custom field value
add_action('wp_update_nav_menu_item', function ($menu_id, $menu_item_db_id, $args) {
    if (isset($_POST['menu-item-hide'][$menu_item_db_id])) {
        update_post_meta($menu_item_db_id, '_menu_item_hide', 1);
    } else {
        delete_post_meta($menu_item_db_id);
    }
}, 10, 3);

// Add indicator for hidden items in the backend (collapsed view)
add_filter('wp_get_nav_menu_items', function ($items, $menu, $args) {
    // Check if this is the backend
    if (is_admin()) {
        foreach ($items as $item) {
            $hide_item = get_post_meta($item->ID, '_menu_item_hide', true);
            if ($hide_item) {
                $item->title = esc_html($item->title) . ' <span style="color: red; font-weight: bold;">[Skjult]</span>';
                $item->post_title = esc_html($item->post_title) . ' [Skjult]'; // For collapsed view
            }
        }
    } else {
        // Frontend: exclude hidden items
        foreach ($items as $key => $item) {
            $hide_item = get_post_meta($item->ID, '_menu_item_hide', true);
            if ($hide_item) {
                unset($items[$key]);
            }
        }
    }
    return $items;
}, 10, 3);

// GitHub updater: Check for plugin updates
add_filter('pre_set_site_transient_update_plugins', function ($transient) {
    // Check for transient object
    if (empty($transient->checked)) {
        return $transient;
    }

    $plugin_slug = 'skjul-menupunkter';
    $plugin_file = 'skjul-menupunkter/skjul-menupunkter.php';
    $github_repo = 'marvinfpham/skjul-menupunkter';

    // GitHub API URL for the latest release
    $response = wp_remote_get("https://api.github.com/repos/{$github_repo}/releases/latest");
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return $transient;
    }

    $release = json_decode(wp_remote_retrieve_body($response));
    if (!$release || empty($release->tag_name)) {
        return $transient;
    }

    $new_version = str_replace('v', '', $release->tag_name); // Remove 'v' from version
    $current_version = $transient->checked[$plugin_file];

    // Compare versions
    if (version_compare($new_version, $current_version, '>')) {
        $transient->response[$plugin_file] = (object) [
            'slug'        => $plugin_slug,
            'new_version' => $new_version,
            'package'     => $release->zipball_url, // GitHub ZIP URL
            'url'         => $release->html_url,   // Release page
        ];
    }

    return $transient;
});

// Add plugin details in WordPress plugin information screen
add_filter('plugins_api', function ($res, $action, $args) {
    if ($action !== 'plugin_information' || $args->slug !== 'skjul-menupunkter') {
        return $res;
    }

    $github_repo = 'marvinfpham/skjul-menupunkter';

    // GitHub API URL for the latest release
    $response = wp_remote_get("https://api.github.com/repos/{$github_repo}/releases/latest");
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return $res;
    }

    $release = json_decode(wp_remote_retrieve_body($response));
    if (!$release) {
        return $res;
    }

    $res = (object) [
        'name'        => 'Skjul Menupunkter',
        'slug'        => 'skjul-menupunkter',
        'version'     => str_replace('v', '', $release->tag_name),
        'author'      => '<a href="https://magio.dk/">Magio</a>',
        'homepage'    => $release->html_url,
        'sections'    => [
            'description' => 'Tilføjer en mulighed for at skjule menupunkter i WordPress-menuer.',
        ],
        'download_link' => $release->zipball_url,
    ];

    return $res;
}, 10, 3);
