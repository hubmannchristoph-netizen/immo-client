<?php

if (!defined('ABSPATH')) {
    exit;
}

class ImmoShortcodes {

    private $api;

    public function __construct() {
        $this->api = new ImmoAPI();
        add_shortcode('immo_property', array($this, 'render_property_shortcode'));
        // Rückwärtskompatibler Alias.
        add_shortcode('immo_unit',     array($this, 'render_property_shortcode'));
        add_shortcode('immo_list',     array($this, 'render_list_shortcode'));
        add_shortcode('immo_project',  array($this, 'render_project_shortcode'));
    }

    /**
     * Gemeinsame Defaults für Farb- und E-Mail-Attribute.
     */
    private function style_defaults() {
        return array(
            'primary'   => '',
            'secondary' => '',
            'accent'    => '',
            'email'     => '',
        );
    }

    /**
     * Container-ID + zugehörige Farben + Inline-Style erzeugen.
     *
     * @param array $atts Shortcode-Atts (mit primary/secondary/accent)
     * @param string $prefix Container-Prefix
     * @return array{id:string, style:string, colors:array, email:string}
     */
    private function prepare_container($atts, $prefix) {
        $colors = array(
            'primary'   => ImmoStyles::resolve_color('primary',   $atts['primary']   ?? ''),
            'secondary' => ImmoStyles::resolve_color('secondary', $atts['secondary'] ?? ''),
            'accent'    => ImmoStyles::resolve_color('accent',    $atts['accent']    ?? ''),
        );
        $id    = ImmoStyles::unique_id($prefix);
        $style = ImmoStyles::inline_css($id, $colors);

        $email = sanitize_email((string) ($atts['email'] ?? ''));
        if ($email === '') {
            $email = sanitize_email((string) get_option('immo_notify_email', ''));
        }

        return array(
            'id'     => $id,
            'style'  => $style,
            'colors' => $colors,
            'email'  => $email,
        );
    }

    /**
     * [immo_property id="123"]  oder  [immo_property slug="schoene-wohnung-graz"]
     */
    public function render_property_shortcode($atts) {
        $atts = shortcode_atts(array_merge(array(
            'id'   => '',
            'slug' => '',
        ), $this->style_defaults()), $atts);

        if (empty($atts['id']) && empty($atts['slug'])) {
            return 'Keine ID oder Slug angegeben.';
        }

        $property = !empty($atts['id'])
            ? $this->api->get_property(absint($atts['id']))
            : $this->api->get_property_by_slug($atts['slug']);

        if (!$property) {
            return 'Immobilie nicht gefunden.';
        }

        $ctx   = $this->prepare_container($atts, 'immo-property');
        $item  = $property; // für das Template
        $immo_email = $ctx['email'];

        ob_start();
        echo $ctx['style'];
        echo '<div id="' . esc_attr($ctx['id']) . '" class="immo-block immo-block-property">';
        include IMMO_CLIENT_PATH . 'templates/shortcode-property.php';
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * [immo_list type="properties" limit="12" status="available" filters="yes"]
     * [immo_list type="projects"   limit="6"]
     */
    public function render_list_shortcode($atts) {
        $atts = shortcode_atts(array_merge(array(
            'type'    => 'properties',
            'limit'   => 12,
            'status'  => '',
            'filters' => 'yes',
        ), $this->style_defaults()), $atts);

        // Abwärtskompatibel: "units" akzeptieren.
        $type = ($atts['type'] === 'units') ? 'properties' : $atts['type'];

        $api_args = array('per_page' => absint($atts['limit']));
        if (!empty($atts['status'])) {
            $api_args['status'] = $atts['status'];
        }

        $items = ($type === 'projects')
            ? $this->api->get_projects($api_args)
            : $this->api->get_properties($api_args);

        $ctx        = $this->prepare_container($atts, 'immo-list');
        $immo_email = $ctx['email'];

        ob_start();
        echo $ctx['style'];
        echo '<div id="' . esc_attr($ctx['id']) . '" class="immo-block immo-block-list" data-immo-email="' . esc_attr($immo_email) . '">';
        if ($atts['filters'] === 'yes' && $type === 'properties') {
            include IMMO_CLIENT_PATH . 'templates/filter-bar.php';
        }

        echo '<div class="immo-item-grid-wrapper">';
        if (empty($items)) {
            echo '<p>Keine Objekte gefunden.</p>';
        } else {
            include IMMO_CLIENT_PATH . 'templates/list-grid.php';
        }
        echo '</div>';
        echo '</div>';

        return ob_get_clean();
    }

    /**
     * Einzel-Projekt via ID oder Slug.
     */
    public function render_project_shortcode($atts) {
        $atts = shortcode_atts(array_merge(array(
            'id'   => '',
            'slug' => '',
        ), $this->style_defaults()), $atts);

        if (empty($atts['id']) && empty($atts['slug'])) {
            return 'Keine Projekt-ID oder Slug angegeben.';
        }

        $project = !empty($atts['id'])
            ? $this->api->get_project(absint($atts['id']))
            : $this->api->get_project_by_slug($atts['slug']);

        if (!$project) {
            return 'Projekt nicht gefunden.';
        }

        // Zugehörige Einheiten separat laden.
        $units_payload = $this->api->get_project_units($project['id']);
        $project_units = isset($units_payload['units']) ? $units_payload['units'] : array();

        $ctx        = $this->prepare_container($atts, 'immo-project');
        $immo_email = $ctx['email'];

        ob_start();
        echo $ctx['style'];
        echo '<div id="' . esc_attr($ctx['id']) . '" class="immo-block immo-block-project">';
        include IMMO_CLIENT_PATH . 'templates/shortcode-project.php';
        echo '</div>';
        return ob_get_clean();
    }
}

new ImmoShortcodes();
