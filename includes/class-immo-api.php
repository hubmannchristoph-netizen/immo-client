<?php
/**
 * Client-Wrapper für die REST-API des ImmoManager (Namespace: immo-manager/v1).
 *
 * Lese- und Schreibrouten senden den API-Key bei jeder Anfrage im Header
 * X-Immo-API-Key mit, sofern in den Settings hinterlegt.
 */

if (!defined('ABSPATH')) {
    exit;
}

class ImmoAPI {

    const NAMESPACE_PATH = 'immo-manager/v1';

    private $api_base;      // z.B. https://example.com/wp-json/
    private $api_key;       // wird bei jeder Anfrage mitgesendet
    private $cache_duration;

    public function __construct() {
        $raw_url   = (string) get_option('immo_api_url', '');
        $raw_url   = trim($raw_url);
        $raw_url   = rtrim($raw_url, '/');

        // Wenn der Nutzer schon /wp-json am Ende hat, nicht doppelt anhängen.
        if ($raw_url !== '' && !preg_match('~/wp-json$~', $raw_url)) {
            $raw_url .= '/wp-json';
        }

        $this->api_base       = $raw_url === '' ? '' : $raw_url . '/';
        $this->api_key        = (string) get_option('immo_api_key', '');
        $this->cache_duration = (int) get_option('immo_cache_duration', 3600);
    }

    private function default_headers() {
        $headers = array('Accept' => 'application/json');
        if ($this->api_key !== '') {
            $headers['X-Immo-API-Key'] = $this->api_key;
        }
        return $headers;
    }

    /**
     * Interner HTTP-GET mit Transient-Cache.
     */
    public function get($endpoint, $params = array()) {
        if ($this->api_base === '') {
            return null;
        }

        $cache_key   = 'immo_' . md5($endpoint . wp_json_encode($params));
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }

        $url = $this->api_base . self::NAMESPACE_PATH . '/' . ltrim($endpoint, '/');
        if (!empty($params)) {
            $url = add_query_arg($params, $url);
        }

        $response = wp_remote_get($url, array(
            'headers' => $this->default_headers(),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            return null;
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status >= 400) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (is_array($data) && $this->cache_duration > 0) {
            set_transient($cache_key, $data, $this->cache_duration);
        }
        return is_array($data) ? $data : null;
    }

    /**
     * Interner HTTP-POST ohne Cache.
     */
    public function post($endpoint, $body = array()) {
        if ($this->api_base === '') {
            return new WP_Error('immo_api_no_url', 'API URL nicht konfiguriert.');
        }

        $url     = $this->api_base . self::NAMESPACE_PATH . '/' . ltrim($endpoint, '/');
        $headers = $this->default_headers();
        $headers['Content-Type'] = 'application/json';

        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body'    => wp_json_encode($body),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $data   = json_decode(wp_remote_retrieve_body($response), true);
        $status = wp_remote_retrieve_response_code($response);
        if ($status >= 400) {
            $msg = is_array($data) && !empty($data['message']) ? $data['message'] : 'API-Fehler';
            return new WP_Error('immo_api_error', $msg, array('status' => $status, 'data' => $data));
        }
        return is_array($data) ? $data : array();
    }

    // =========================================================================
    // Properties
    // =========================================================================

    public function get_properties($args = array()) {
        $data = $this->get('properties', $args);
        return isset($data['properties']) ? $data['properties'] : array();
    }

    public function get_properties_payload($args = array()) {
        return $this->get('properties', $args);
    }

    public function get_property($id) {
        return $this->get('properties/' . absint($id));
    }

    public function get_property_by_slug($slug) {
        $slug = sanitize_title($slug);
        if ($slug === '') return null;
        return $this->get('properties/by-slug/' . rawurlencode($slug));
    }

    public function get_similar($property_id, $limit = 3) {
        $data = $this->get('properties/' . absint($property_id) . '/similar', array('limit' => (int) $limit));
        return isset($data['properties']) ? $data['properties'] : array();
    }

    // =========================================================================
    // Projekte
    // =========================================================================

    public function get_projects($args = array()) {
        $data = $this->get('projects', $args);
        return isset($data['projects']) ? $data['projects'] : array();
    }

    public function get_projects_payload($args = array()) {
        return $this->get('projects', $args);
    }

    public function get_project($id) {
        return $this->get('projects/' . absint($id));
    }

    public function get_project_by_slug($slug) {
        $slug = sanitize_title($slug);
        if ($slug === '') return null;
        return $this->get('projects/by-slug/' . rawurlencode($slug));
    }

    public function get_project_units($project_id, $args = array()) {
        return $this->get('projects/' . absint($project_id) . '/units', $args);
    }

    // =========================================================================
    // Referenzdaten & Settings
    // =========================================================================

    public function get_regions() {
        return $this->get('regions');
    }

    public function get_districts($state) {
        return $this->get('regions/' . sanitize_key($state) . '/districts');
    }

    public function get_features() {
        return $this->get('features');
    }

    /**
     * Öffentliche Settings vom Manager (Farben, Währung, Karte).
     * Cache ist im get() bereits enthalten.
     */
    public function get_public_settings() {
        $data = $this->get('settings/public');
        return is_array($data) ? $data : array();
    }

    public function search($q, $per_page = 5) {
        return $this->get('search', array('q' => $q, 'per_page' => (int) $per_page));
    }

    // =========================================================================
    // Anfragen
    // =========================================================================

    public function create_inquiry($data) {
        return $this->post('inquiries', $data);
    }
}
