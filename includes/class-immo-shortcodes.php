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
        add_shortcode('immo_units',    array($this, 'render_units_shortcode'));
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
     * [immo_list ids="123,456,789" layout="grid"]
     * [immo_list ids="123;456" layout="slider" per_page="3"]
     *
     * Bei gesetzten `ids` werden genau diese Objekte in der angegebenen Reihenfolge geladen,
     * die Filter-Bar wird automatisch ausgeblendet. `layout="slider"` aktiviert Splide.js.
     */
    public function render_list_shortcode($atts) {
        $atts = shortcode_atts(array_merge(array(
            'type'        => 'properties',
            'limit'       => 12,
            'status'      => '',
            'filters'     => 'yes',
            'ids'         => '',
            'layout'      => 'grid',     // grid | slider
            'per_page'    => 3,           // Slider: Slides pro Ansicht (Desktop)
            'per_page_md' => 2,           // Slider: Tablet (≤900px)
            'per_page_sm' => 1,           // Slider: Mobil (≤600px)
            'gap'         => '1.5rem',    // Slider: Abstand zwischen Slides
            'autoplay'    => 'no',
            'loop'        => 'yes',
        ), $this->style_defaults()), $atts);

        // Abwärtskompatibel: "units" akzeptieren.
        $type = ($atts['type'] === 'units') ? 'properties' : $atts['type'];

        // ID-Liste normalisieren (Komma- oder Semikolon-getrennt).
        $ids_csv = '';
        if (!empty($atts['ids'])) {
            $pieces = preg_split('/[,;]/', (string) $atts['ids']) ?: array();
            $ids    = array_values(array_unique(array_filter(array_map('absint', $pieces))));
            $ids_csv = implode(',', $ids);
        }
        $has_ids = $ids_csv !== '';

        $api_args = array();
        if ($has_ids) {
            $api_args['ids'] = $ids_csv;
            // per_page wird Manager-seitig auf count(ids) gesetzt — limit ignorieren.
        } else {
            $api_args['per_page'] = absint($atts['limit']);
        }
        if (!empty($atts['status'])) {
            $api_args['status'] = $atts['status'];
        }

        $items = ($type === 'projects')
            ? $this->api->get_projects($api_args)
            : $this->api->get_properties($api_args);

        $layout = ($atts['layout'] === 'slider') ? 'slider' : 'grid';
        // Slider nur für Properties — bei Projekten Grid erzwingen.
        if ($layout === 'slider' && $type === 'projects') {
            $layout = 'grid';
        }

        // Slider-Assets nur bei Bedarf einbinden.
        if ($layout === 'slider') {
            wp_enqueue_style('immo-client-splide');
            wp_enqueue_style('immo-client-list-slider');
            wp_enqueue_script('immo-client-splide');
            wp_enqueue_script('immo-client-list-slider');
        }

        $ctx        = $this->prepare_container($atts, 'immo-list');
        $immo_email = $ctx['email'];

        ob_start();
        echo $ctx['style'];
        echo '<div id="' . esc_attr($ctx['id']) . '" class="immo-block immo-block-list" data-immo-email="' . esc_attr($immo_email) . '">';

        // Filter-Bar nur, wenn gewünscht UND keine IDs gesetzt UND Properties.
        if (!$has_ids && $atts['filters'] === 'yes' && $type === 'properties') {
            include IMMO_CLIENT_PATH . 'templates/filter-bar.php';
        }

        echo '<div class="immo-item-grid-wrapper">';
        if (empty($items)) {
            echo '<p>Keine Objekte gefunden.</p>';
        } elseif ($layout === 'slider') {
            $slider_config = array(
                'per_page'    => absint($atts['per_page']),
                'per_page_md' => absint($atts['per_page_md']),
                'per_page_sm' => absint($atts['per_page_sm']),
                'gap'         => (string) $atts['gap'],
                'autoplay'    => ($atts['autoplay'] === 'yes') ? 'yes' : 'no',
                'loop'        => ($atts['loop'] === 'no') ? 'no' : 'yes',
            );
            include IMMO_CLIENT_PATH . 'templates/list-slider.php';
        } else {
            include IMMO_CLIENT_PATH . 'templates/list-grid.php';
        }
        echo '</div>';
        echo '</div>';

        return ob_get_clean();
    }

    /**
     * Einzel-Projekt via ID oder Slug — rendert die volle Detailansicht
     * (gleiche Sektionen wie /bauprojekt/{slug}/).
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

        // Bauprojekt-CSS dynamisch nachladen (außerhalb der /bauprojekt/-Route).
        wp_enqueue_style('immo-client-project-detail',
            IMMO_CLIENT_URL . 'assets/css/project-detail.css',
            array('immo-client-style'),
            IMMO_CLIENT_VERSION
        );
        // Project-JS sicherstellen (Modal + Karte + Filter).
        wp_enqueue_script('immo-client-project');

        $ctx          = $this->prepare_container($atts, 'immo-project');
        $immo_email   = $ctx['email'];
        $api          = $this->api;
        $is_shortcode = true;

        ob_start();
        echo $ctx['style'];
        echo '<div id="' . esc_attr($ctx['id']) . '" class="immo-block immo-block-project">';
        include IMMO_CLIENT_PATH . 'templates/shortcode-project.php';
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Hat das Anfrage-Modal (Singleton) bereits ausgegeben?
     *
     * @var bool
     */
    private static $project_inquiry_modal_rendered = false;

    /**
     * Hat der Wohneinheits-Lightbox-Container (Singleton) bereits ausgegeben?
     *
     * @var bool
     */
    private static $project_unit_lightbox_rendered = false;

    /**
     * Hat die Sticky-Mobile-CTA-Bar (Singleton) bereits ausgegeben?
     *
     * @var bool
     */
    private static $project_mobile_cta_rendered = false;

    /**
     * Wird vom shortcode-project.php aufgerufen, um Modal/Lightbox/CTA-Container
     * höchstens EINMAL pro Page-Render auszugeben (auch bei mehreren
     * [immo_project]-Shortcodes auf einer Seite).
     */
    public static function inquiry_modal_rendered() {
        $r = self::$project_inquiry_modal_rendered;
        self::$project_inquiry_modal_rendered = true;
        return $r;
    }
    public static function unit_lightbox_rendered() {
        $r = self::$project_unit_lightbox_rendered;
        self::$project_unit_lightbox_rendered = true;
        return $r;
    }
    public static function mobile_cta_rendered() {
        $r = self::$project_mobile_cta_rendered;
        self::$project_mobile_cta_rendered = true;
        return $r;
    }

    /**
     * Hat der Lightbox-Container (Singleton) bereits ausgegeben?
     *
     * @var bool
     */
    private static $lightbox_rendered = false;

    /**
     * [immo_units project_id="123" status="available,reserved" layout="table" orderby="unit_number" limit="0"]
     * [immo_units project_slug="mein-projekt" layout="grid"]
     *
     * Liefert die Wohneinheiten eines Bauprojekts ohne den restlichen Project-Kontext
     * (keine Galerie, keine Beschreibung, keine Sidebar). Drei Layouts: table | grid | list.
     * Klick auf eine Card/Zeile öffnet eine Quick-Info-Lightbox; im Modal befindet
     * sich ein „Zur Detailseite"-Button (sofern die Property einen Slug hat).
     */
    public function render_units_shortcode($atts) {
        $atts = shortcode_atts(array_merge(array(
            'project_id'    => '',
            'project_slug'  => '',
            'property_id'   => '',
            'property_slug' => '',
            'status'        => '',          // einzeln oder kommagetrennt: available,reserved,sold,rented
            'layout'        => 'table',     // table | grid | list
            'orderby'       => 'unit_number',
            'limit'         => 0,
            'show_stats'    => 'yes',       // yes | no — Status-Counter über der Liste
        ), $this->style_defaults()), $atts);

        $project_id    = absint($atts['project_id']);
        $project_slug  = sanitize_title((string) $atts['project_slug']);
        $property_id   = absint($atts['property_id']);
        $property_slug = sanitize_title((string) $atts['property_slug']);

        if (!$project_id && !$project_slug && !$property_id && !$property_slug) {
            return '<p class="immo-units-error">Bitte project_id/slug oder property_id/slug angeben.</p>';
        }

        // Query-Args an REST durchreichen.
        $api_args = array();
        if (!empty($atts['status']))  { $api_args['status']  = $atts['status']; }
        if (!empty($atts['orderby'])) { $api_args['orderby'] = $atts['orderby']; }
        $limit = max(0, (int) $atts['limit']);
        if ($limit > 0) { $api_args['limit'] = $limit; }

        if ($property_id) {
            $payload = $this->api->get_property_units($property_id, $api_args);
        } elseif ($property_slug) {
            $payload = $this->api->get_property_units_by_slug($property_slug, $api_args);
        } elseif ($project_id) {
            $payload = $this->api->get_project_units($project_id, $api_args);
        } else {
            $payload = $this->api->get_project_units_by_slug($project_slug, $api_args);
        }

        if (!is_array($payload) || !isset($payload['units'])) {
            return '<p class="immo-units-error">Keine Wohneinheiten gefunden.</p>';
        }

        $items     = $payload['units'];
        $stats     = isset($payload['stats']) ? $payload['stats'] : array();
        $layout    = in_array($atts['layout'], array('table', 'grid', 'list'), true) ? $atts['layout'] : 'table';
        $show_stats = ($atts['show_stats'] === 'yes');

        $ctx = $this->prepare_container($atts, 'immo-units');

        ob_start();
        echo $ctx['style'];
        echo '<div id="' . esc_attr($ctx['id']) . '" class="immo-block immo-block-units immo-units-layout-' . esc_attr($layout) . '">';

        if (empty($items)) {
            echo '<p class="immo-units-empty">Keine Wohneinheiten gefunden.</p>';
        } else {
            $api           = $this->api; // im Datenpool-Template benötigt
            $template_file = 'units-' . $layout . '.php';
            include IMMO_CLIENT_PATH . 'templates/' . $template_file;

            // Quick-Info-Datenpool — JS liest pro data-unit-id.
            include IMMO_CLIENT_PATH . 'templates/units-lightbox-data.php';

            // Lightbox-Container nur einmal pro Request ausgeben.
            // Wenn `single-project.php` bereits einen Container rendert,
            // gewinnt der erste — weitere Shortcodes nutzen denselben.
            if ( ! self::$lightbox_rendered && ! $this->page_has_lightbox() ) {
                include IMMO_CLIENT_PATH . 'templates/units-lightbox-container.php';
                self::$lightbox_rendered = true;
            }
        }

        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Prüft heuristisch, ob die aktuelle Seite bereits den Lightbox-Container
     * rendert (z.B. weil es eine /bauprojekt/{slug}/ Seite ist, die single-project.php
     * verwendet). In dem Fall NICHT zusätzlich rendern.
     */
    private function page_has_lightbox() {
        return (bool) get_query_var('immo_project_slug');
    }
}

new ImmoShortcodes();
