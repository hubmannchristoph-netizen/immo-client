<?php
/**
 * Style-Helper: liest Default-Farben aus /settings/public des Managers
 * und liefert Inline-CSS, das pro Shortcode-Container ausgegeben wird.
 *
 * Reihenfolge der Auflösung:
 *   1. Shortcode-Attribute (primary/secondary/accent)
 *   2. Public-Settings vom Manager
 *   3. Hardcodierte Defaults
 */

if (!defined('ABSPATH')) {
    exit;
}

class ImmoStyles {

    /** @var array|null */
    private static $public_settings = null;

    /**
     * Globale Farbvariablen + optional CSS gegen doppelte Theme-Titel.
     */
    public static function init() {
        add_action('wp_head',     array(__CLASS__, 'global_inline_css'), 100);
        add_filter('body_class',  array(__CLASS__, 'body_class'));
    }

    public static function body_class($classes) {
        if (get_query_var('immo_unit_slug')) {
            $classes[] = 'immo-detail-page';
            $classes[] = 'immo-property-page';
        }
        if (get_query_var('immo_project_slug')) {
            $classes[] = 'immo-detail-page';
            $classes[] = 'immo-project-page';
        }
        return $classes;
    }

    public static function global_inline_css() {
        $primary   = self::resolve_color('primary');
        $secondary = self::resolve_color('secondary');
        $accent    = self::resolve_color('accent');

        echo "<style id=\"immo-globals\">\n";
        echo ":root, body {";
        if ($primary)   echo " --immo-primary: "   . esc_html($primary)   . ";";
        if ($secondary) echo " --immo-secondary: " . esc_html($secondary) . ";";
        if ($accent)    echo " --immo-accent: "    . esc_html($accent)    . ";";
        echo " }\n";

        // Bei Detailseiten: doppelten Theme-Page-Title verstecken (wenn aktiviert).
        $hide = (string) get_option('immo_detail_hide_theme_title', '1') === '1';
        if ($hide && (get_query_var('immo_unit_slug') || get_query_var('immo_project_slug'))) {
            echo "body.immo-detail-page .entry-header > .entry-title,";
            echo "body.immo-detail-page .page-header,";
            echo "body.immo-detail-page .ast-page-title-area,";
            echo "body.immo-detail-page .elementor-page-title,";
            echo "body.immo-detail-page .elementor-widget-theme-page-title,";
            echo "body.immo-detail-page .wp-block-post-title,";
            echo "body.immo-detail-page header.entry-header h1,";
            echo "body.immo-detail-page .page-title-bar { display: none !important; }\n";
        }
        echo "</style>\n";
    }

    /** @var array */
    private static $defaults = array(
        'primary'   => '#1e88e5',
        'secondary' => '#43a047',
        'accent'    => '#ff9800',
    );

    /**
     * Public-Settings vom Manager holen (gecacht via Transient).
     */
    public static function public_settings() {
        if (self::$public_settings !== null) {
            return self::$public_settings;
        }
        $api = new ImmoAPI();
        self::$public_settings = $api->get_public_settings();
        return self::$public_settings;
    }

    /**
     * Eine Farbe auflösen.
     * Reihenfolge: Shortcode-Override > Client-Setting > Manager-Setting > Default.
     */
    public static function resolve_color($key, $override = '') {
        $override = self::sanitize_color($override);
        if ($override !== '') {
            return $override;
        }

        $option_map = array(
            'primary'   => 'immo_color_primary',
            'secondary' => 'immo_color_secondary',
            'accent'    => 'immo_color_accent',
        );
        if (isset($option_map[$key])) {
            $local = self::sanitize_color((string) get_option($option_map[$key], ''));
            if ($local !== '') {
                return $local;
            }
        }

        $settings = self::public_settings();
        $map      = array(
            'primary'   => 'primary_color',
            'secondary' => 'secondary_color',
            'accent'    => 'accent_color',
        );
        if (isset($map[$key], $settings[$map[$key]])) {
            $val = self::sanitize_color($settings[$map[$key]]);
            if ($val !== '') {
                return $val;
            }
        }

        return isset(self::$defaults[$key]) ? self::$defaults[$key] : '';
    }

    /**
     * Hex-Farbe (#abc oder #aabbcc) prüfen, sonst leerer String.
     */
    public static function sanitize_color($value) {
        $value = trim((string) $value);
        if ($value === '') return '';
        if (preg_match('/^#([a-f0-9]{3}|[a-f0-9]{6})$/i', $value)) {
            return $value;
        }
        return '';
    }

    /**
     * Eindeutige ID für einen Shortcode-Container.
     */
    public static function unique_id($prefix = 'immo-block') {
        static $counter = 0;
        $counter++;
        return $prefix . '-' . $counter . '-' . wp_rand(1000, 9999);
    }

    /**
     * Inline <style>-Block für einen Container erzeugen.
     *
     * @param string $container_id ID des umschließenden Elements.
     * @param array  $colors       ['primary' => '#xxx', 'secondary' => '#xxx', 'accent' => '#xxx']
     */
    public static function inline_css($container_id, $colors) {
        $primary   = self::sanitize_color($colors['primary']   ?? '');
        $secondary = self::sanitize_color($colors['secondary'] ?? '');
        $accent    = self::sanitize_color($colors['accent']    ?? '');

        $vars = array();
        if ($primary)   $vars[] = '--immo-primary: '   . $primary . ';';
        if ($secondary) $vars[] = '--immo-secondary: ' . $secondary . ';';
        if ($accent)    $vars[] = '--immo-accent: '    . $accent . ';';

        if (empty($vars)) return '';

        return sprintf(
            '<style>#%1$s { %2$s }</style>',
            esc_attr($container_id),
            implode(' ', $vars)
        );
    }
}
