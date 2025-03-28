<?php
/**
 * Plugin Name: Skjul Menupunkter
 * Plugin URI: https://magio.dk/
 * Description: Tilføjer en mulighed for at skjule menupunkter i WordPress-menuer. Skjulte menupunkter vil ikke vises på frontend, men forbliver synlige i backend.
 * Version: 1.1.0
 * Author: Magio
 * Author URI: https://magio.dk/
 * License: GPL2
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SkjulMenupunkter {
    public function __construct() {
        // Classic Editor Hooks
        add_filter('wp_nav_menu_item_custom_fields', [$this, 'add_menu_hide_option'], 10, 4);
        add_action('wp_update_nav_menu_item', [$this, 'save_menu_hide_option'], 10, 3);
        
        // Gutenberg Block Editor Hooks
        add_filter('rest_prepare_nav_menu_item', [$this, 'gutenberg_add_hide_field'], 10, 3);
        add_filter('rest_pre_insert_nav_menu_item', [$this, 'gutenberg_save_hide_field'], 10, 2);
        
        // Common Hooks
        add_filter('wp_get_nav_menu_items', [$this, 'filter_menu_items'], 10, 3);
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_plugin_updates']);
        add_filter('plugins_api', [$this, 'add_plugin_details'], 10, 3);
    }

    /**
     * Add custom field to menu items in the classic editor
     */
    public function add_menu_hide_option($item_id, $item, $depth, $args) {
        $hide_item = get_post_meta($item_id, '_menu_item_hide', true);
        ?>
        <p class="field-hide-menu-item description description-wide">
            <label for="edit-menu-item-hide-<?php echo esc_attr($item_id); ?>">
                <input type="checkbox" id="edit-menu-item-hide-<?php echo esc_attr($item_id); ?>" 
                       name="menu-item-hide[<?php echo esc_attr($item_id); ?>]" 
                       value="1" <?php checked($hide_item, 1); ?> />
                <?php _e('Skjul menu-punkt', 'skjul-menupunkter'); ?>
            </label>
        </p>
        <?php
    }

    /**
     * Save hide option for classic editor
     */
    public function save_menu_hide_option($menu_id, $menu_item_db_id, $args) {
        if (isset($_POST['menu-item-hide'][$menu_item_db_id])) {
            update_post_meta($menu_item_db_id, '_menu_item_hide', 1);
        } else {
            delete_post_meta($menu_item_db_id, '_menu_item_hide');
        }
    }

    /**
     * Add custom field for Gutenberg block editor
     */
    public function gutenberg_add_hide_field($response, $menu_item, $request) {
        $hide_item = get_post_meta($menu_item->ID, '_menu_item_hide', true);
        $response->data['skjul_menupunkter_hide'] = (bool)$hide_item;
        return $response;
    }

    /**
     * Save hide option for Gutenberg block editor
     */
    public function gutenberg_save_hide_field($prepared_post, $request) {
        $hide_item = $request->get_param('skjul_menupunkter_hide');
        
        if ($hide_item) {
            update_post_meta($prepared_post->ID, '_menu_item_hide', 1);
        } else {
            delete_post_meta($prepared_post->ID, '_menu_item_hide');
        }
        
        return $prepared_post;
    }

    /**
     * Filter menu items based on hide setting
     */
    public function filter_menu_items($items, $menu, $args) {
        if (is_admin()) {
            foreach ($items as $item) {
                $hide_item = get_post_meta($item->ID, '_menu_item_hide', true);
                if ($hide_item) {
                    $item->title = esc_html($item->title) . ' <span style="color: red; font-weight: bold;">[Skjult]</span>';
                    $item->post_title = esc_html($item->post_title) . ' [Skjult]';
                }
            }
        } else {
            foreach ($items as $key => $item) {
                $hide_item = get_post_meta($item->ID, '_menu_item_hide', true);
                if ($hide_item) {
                    unset($items[$key]);
                }
            }
        }
        return $items;
    }

    /**
     * GitHub updater: Check for plugin updates
     */
    public function check_plugin_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $plugin_slug = 'skjul-menupunkter';
        $plugin_file = 'skjul-menupunkter/skjul-menupunkter.php';
        $github_repo = 'marvinfpham/skjul-menupunkter';

        // Ensure the plugin exists in checked list before proceeding
        if (!isset($transient->checked[$plugin_file])) {
            return $transient;
        }

        $response = wp_remote_get("https://api.github.com/repos/{$github_repo}/releases/latest");
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return $transient;
        }

        $release = json_decode(wp_remote_retrieve_body($response));
        if (!$release || empty($release->tag_name)) {
            return $transient;
        }

        $new_version = str_replace('v', '', $release->tag_name);
        $current_version = isset($transient->checked[$plugin_file]) ? $transient->checked[$plugin_file] : '0.0.0';

        // Ensure $current_version is always a string
        if (!is_string($current_version)) {
            $current_version = '0.0.0';
        }

        if (version_compare($new_version, $current_version, '>')) {
            $transient->response[$plugin_file] = (object) [
                'slug'        => $plugin_slug,
                'new_version' => $new_version,
                'package'     => $release->zipball_url,
                'url'         => $release->html_url,
            ];
        }

        return $transient;
    }

    /**
     * Add plugin details in WordPress plugin information screen
     */
    public function add_plugin_details($res, $action, $args) {
        if ($action !== 'plugin_information' || $args->slug !== 'skjul-menupunkter') {
            return $res;
        }

        $github_repo = 'marvinfpham/skjul-menupunkter';

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
    }
}

// Initialize the plugin
function skjul_menupunkter_init() {
    new SkjulMenupunkter();
}
add_action('plugins_loaded', 'skjul_menupunkter_init');
