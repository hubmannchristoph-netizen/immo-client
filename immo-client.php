<?php
/**
 * Plugin Name: ImmoClient
 * Description: Client-Anbindung für den ImmoManager via REST-API.
 * Version: 1.0.0
 * Author: Gemini CLI
 * Text Domain: immo-client
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main ImmoClient Class
 */
class ImmoClient {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    private function define_constants() {
        define('IMMO_CLIENT_VERSION', '1.0.0');
        define('IMMO_CLIENT_PATH', plugin_dir_path(__FILE__));
        define('IMMO_CLIENT_URL', plugin_dir_url(__FILE__));
    }

    private function includes() {
        require_once IMMO_CLIENT_PATH . 'includes/class-immo-api.php';
        require_once IMMO_CLIENT_PATH . 'includes/class-immo-styles.php';
        require_once IMMO_CLIENT_PATH . 'includes/class-immo-mailer.php';
        require_once IMMO_CLIENT_PATH . 'includes/class-immo-settings.php';
        require_once IMMO_CLIENT_PATH . 'includes/class-immo-help.php';
        require_once IMMO_CLIENT_PATH . 'includes/class-immo-routing.php';
        require_once IMMO_CLIENT_PATH . 'includes/class-immo-shortcodes.php';
        require_once IMMO_CLIENT_PATH . 'includes/class-immo-ajax.php';
    }

    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        register_activation_hook(__FILE__, array($this, 'activate'));

        if (class_exists('ImmoStyles') && method_exists('ImmoStyles', 'init')) {
            ImmoStyles::init();
        }
    }

    public function enqueue_assets() {
        wp_enqueue_style('immo-client-style', IMMO_CLIENT_URL . 'assets/css/immo-client.css', array(), IMMO_CLIENT_VERSION);
        wp_enqueue_script('immo-client-filter',  IMMO_CLIENT_URL . 'assets/js/immo-filter.js',  array('jquery'), IMMO_CLIENT_VERSION, true);
        wp_enqueue_script('immo-client-gallery', IMMO_CLIENT_URL . 'assets/js/immo-gallery.js', array(),         IMMO_CLIENT_VERSION, true);
        wp_enqueue_script('immo-client-project', IMMO_CLIENT_URL . 'assets/js/immo-project.js', array(),         IMMO_CLIENT_VERSION, true);

        wp_localize_script('immo-client-filter', 'immo_ajax', array(
            'ajax_url'              => admin_url('admin-ajax.php'),
            'filter_nonce'          => wp_create_nonce('immo-filter-nonce'),
            'inquiry_nonce'         => wp_create_nonce('immo-inquiry-nonce'),
            'project_inquiry_nonce' => wp_create_nonce('immo-project-inquiry-nonce'),
            // Kompatibilität mit älteren Templates:
            'nonce'                 => wp_create_nonce('immo-filter-nonce'),
        ));
    }

    public function activate() {
        // Required for the rewrite rules in class-immo-routing.php
        $routing = new ImmoRouting();
        $routing->add_rewrite_rules();
        flush_rewrite_rules();
    }
}

// Kickoff
add_action('plugins_loaded', array('ImmoClient', 'get_instance'));
