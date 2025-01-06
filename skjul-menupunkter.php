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
