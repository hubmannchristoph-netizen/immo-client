<?php

if (!defined('ABSPATH')) {
    exit;
}

class ImmoRouting {

    /**
     * Erhöhen, wenn sich Rewrite-Regeln ändern, damit auf bestehenden Sites
     * automatisch ein Flush ausgelöst wird.
     */
    const REWRITE_VERSION = '4';

    public function __construct() {
        add_action('init',              array($this, 'add_rewrite_rules'),    10);
        add_action('init',              array($this, 'maybe_flush'),          11);
        add_filter('query_vars',        array($this, 'add_query_vars'));
        add_action('parse_query',       array($this, 'inject_virtual_post'), 99);
        add_filter('template_include',  array($this, 'load_immo_template'),  99);
        add_filter('the_title',         array($this, 'filter_title'), 10, 2);
        add_filter('document_title_parts', array($this, 'filter_document_title'));
    }

    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^immobilie/([^/]+)/?$',
            'index.php?immo_unit_slug=$matches[1]',
            'top'
        );
        add_rewrite_rule(
            '^bauprojekt/([^/]+)/?$',
            'index.php?immo_project_slug=$matches[1]',
            'top'
        );
    }

    public function maybe_flush() {
        if (get_option('immo_rewrite_version') !== self::REWRITE_VERSION) {
            flush_rewrite_rules(false);
            update_option('immo_rewrite_version', self::REWRITE_VERSION);
        }
    }

    public function add_query_vars($vars) {
        $vars[] = 'immo_unit_slug';
        $vars[] = 'immo_project_slug';
        return $vars;
    }

    /**
     * Vor dem Template-Loader einen virtuellen Post in die Hauptquery
     * einhängen. So denken Themes "echte Singular-Page" und rendern Header,
     * Layout-Optionen, Header-Builder usw. korrekt.
     */
    public function inject_virtual_post($query) {
        if (!$query->is_main_query()) {
            return;
        }

        $unit_slug    = $query->get('immo_unit_slug');
        $project_slug = $query->get('immo_project_slug');
        if (!$unit_slug && !$project_slug) {
            return;
        }

        $is_unit = (bool) $unit_slug;
        $slug    = $is_unit ? $unit_slug : $project_slug;

        // Titel aus dem API-Cache lesen (sehr günstig durch Transient).
        $api  = new ImmoAPI();
        $data = $is_unit ? $api->get_property_by_slug($slug) : $api->get_project_by_slug($slug);
        $title = ($data && !empty($data['title']))
            ? (string) $data['title']
            : ($is_unit ? 'Immobilie' : 'Bauprojekt');

        $virtual = new WP_Post((object) array(
            'ID'             => 0,
            'post_author'    => 0,
            'post_date'      => current_time('mysql'),
            'post_date_gmt'  => current_time('mysql', 1),
            'post_content'   => '',
            'post_title'     => $title,
            'post_excerpt'   => '',
            'post_status'    => 'publish',
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
            'post_password'  => '',
            'post_name'      => sanitize_title($slug),
            'post_modified'  => current_time('mysql'),
            'post_modified_gmt' => current_time('mysql', 1),
            'post_parent'    => 0,
            'guid'           => home_url('/' . ($is_unit ? 'immobilie' : 'bauprojekt') . '/' . $slug . '/'),
            'menu_order'     => 0,
            'post_type'      => 'page',
            'post_mime_type' => '',
            'comment_count'  => 0,
            'filter'         => 'raw',
        ));

        $query->is_404         = false;
        $query->is_home        = false;
        $query->is_singular    = true;
        $query->is_page        = true;
        $query->is_single      = false;
        $query->found_posts    = 1;
        $query->post_count     = 1;
        $query->max_num_pages  = 1;
        $query->posts          = array($virtual);
        $query->post           = $virtual;
        $query->queried_object = $virtual;
        $query->queried_object_id = 0;

        global $post;
        $post = $virtual;
        setup_postdata($virtual);

        status_header(200);
    }

    public function filter_title($title, $post_id = null) {
        if ($post_id === 0 || $post_id === null) {
            $unit_slug    = get_query_var('immo_unit_slug');
            $project_slug = get_query_var('immo_project_slug');
            if ($unit_slug || $project_slug) {
                $api  = new ImmoAPI();
                $data = $unit_slug ? $api->get_property_by_slug($unit_slug) : $api->get_project_by_slug($project_slug);
                if ($data && !empty($data['title'])) {
                    return (string) $data['title'];
                }
            }
        }
        return $title;
    }

    public function filter_document_title($parts) {
        $unit_slug    = get_query_var('immo_unit_slug');
        $project_slug = get_query_var('immo_project_slug');
        if (!$unit_slug && !$project_slug) {
            return $parts;
        }
        $api  = new ImmoAPI();
        $data = $unit_slug ? $api->get_property_by_slug($unit_slug) : $api->get_project_by_slug($project_slug);
        if ($data && !empty($data['title'])) {
            $parts['title'] = (string) $data['title'];
        }
        return $parts;
    }

    public function load_immo_template($template) {
        $unit_slug    = get_query_var('immo_unit_slug');
        $project_slug = get_query_var('immo_project_slug');

        if (!$unit_slug && !$project_slug) {
            return $template;
        }

        $filename = $unit_slug ? 'single-unit.php' : 'single-project.php';

        // Theme-Override unter <theme>/immo-client/<file>.php zulassen.
        $theme_path = get_stylesheet_directory() . '/immo-client/' . $filename;
        if (file_exists($theme_path)) {
            return $theme_path;
        }

        $plugin_template = IMMO_CLIENT_PATH . 'templates/' . $filename;
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }

        return $template;
    }
}

new ImmoRouting();
