<?php

if (!defined('ABSPATH')) {
    exit;
}

class ImmoAJAX {

    public function __construct() {
        add_action('wp_ajax_immo_filter_list',                   array($this, 'filter_list'));
        add_action('wp_ajax_nopriv_immo_filter_list',            array($this, 'filter_list'));
        add_action('wp_ajax_immo_submit_inquiry',                array($this, 'submit_inquiry'));
        add_action('wp_ajax_nopriv_immo_submit_inquiry',         array($this, 'submit_inquiry'));
        add_action('wp_ajax_immo_submit_project_inquiry',        array($this, 'submit_project_inquiry'));
        add_action('wp_ajax_nopriv_immo_submit_project_inquiry', array($this, 'submit_project_inquiry'));
    }

    public function filter_list() {
        check_ajax_referer('immo-filter-nonce', 'nonce');

        $raw_filters = array();
        if (isset($_POST['filters'])) {
            parse_str((string) wp_unslash($_POST['filters']), $raw_filters);
        }

        $allowed = array(
            'status', 'mode', 'type', 'region_state', 'region_district',
            'price_min', 'price_max', 'area_min', 'area_max',
            'rooms', 'energy_class', 'project_id', 'orderby',
            'page', 'per_page',
        );
        $api_args = array();
        foreach ($allowed as $key) {
            if (isset($raw_filters[$key]) && $raw_filters[$key] !== '') {
                $api_args[$key] = is_array($raw_filters[$key])
                    ? implode(',', array_map('sanitize_text_field', $raw_filters[$key]))
                    : sanitize_text_field((string) $raw_filters[$key]);
            }
        }

        $api   = new ImmoAPI();
        $items = $api->get_properties($api_args);

        if (empty($items)) {
            wp_send_json_success(array('html' => '<p>Keine Objekte gefunden.</p>'));
        }

        ob_start();
        include IMMO_CLIENT_PATH . 'templates/list-grid.php';
        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html));
    }

    /**
     * Property-Anfrage:
     * 1. An Manager senden (mit skip_notifications + source_url) → nur Speicherung.
     * 2. Mail aus dem Client mit eigenem Branding versenden.
     */
    public function submit_inquiry() {
        check_ajax_referer('immo-inquiry-nonce', 'nonce');

        $property_id = isset($_POST['property_id']) ? absint($_POST['property_id']) : 0;
        $name        = isset($_POST['inquirer_name'])    ? sanitize_text_field(wp_unslash($_POST['inquirer_name']))    : '';
        $email       = isset($_POST['inquirer_email'])   ? sanitize_email(wp_unslash($_POST['inquirer_email']))        : '';
        $phone       = isset($_POST['inquirer_phone'])   ? sanitize_text_field(wp_unslash($_POST['inquirer_phone']))   : '';
        $message     = isset($_POST['inquirer_message']) ? sanitize_textarea_field(wp_unslash($_POST['inquirer_message'])) : '';
        $consent     = !empty($_POST['consent']);
        $notify_raw  = isset($_POST['notify_email']) ? sanitize_email(wp_unslash($_POST['notify_email'])) : '';

        $notify_email = $notify_raw ?: sanitize_email((string) get_option('immo_notify_email', ''));

        $errors = array();
        if (!$property_id) $errors['property_id']    = 'Pflichtfeld.';
        if (!$name)        $errors['inquirer_name']  = 'Pflichtfeld.';
        if (!$email)       $errors['inquirer_email'] = 'Gültige E-Mail erforderlich.';
        if (!$consent)     $errors['consent']        = 'Datenschutz-Einwilligung erforderlich.';

        if ($errors) {
            wp_send_json_error(array(
                'message' => 'Eingabe fehlerhaft.',
                'errors'  => $errors,
            ), 422);
        }

        // Property aus Manager-API holen (Titel, Permalink, Makler-Mail).
        $api      = new ImmoAPI();
        $property = $api->get_property($property_id);
        if (!$property) {
            wp_send_json_error(array('message' => 'Immobilie nicht gefunden.'), 404);
        }

        $property_title = (string) ($property['title'] ?? ('Immobilie #' . $property_id));
        $property_meta  = isset($property['meta']) && is_array($property['meta']) ? $property['meta'] : array();
        $agent_email    = isset($property_meta['contact_email']) ? sanitize_email((string) $property_meta['contact_email']) : '';

        // Permalink: bevorzugt eigene Detailseite auf der Client-Site.
        $client_permalink = !empty($property['slug']) ? home_url('/immobilie/' . $property['slug'] . '/') : '';

        // 1. Manager benachrichtigen, aber Mail-Versand dort überspringen.
        $payload = array(
            'property_id'        => $property_id,
            'inquirer_name'      => $name,
            'inquirer_email'     => $email,
            'inquirer_phone'     => $phone,
            'inquirer_message'   => $message,
            'consent'            => true,
            'skip_notifications' => true,
            'source_url'         => home_url('/'),
        );
        if ($notify_email) {
            $payload['notify_email'] = $notify_email;
        }
        $api_result = $api->create_inquiry($payload);
        // Bei API-Fehler nicht abbrechen – Mail zumindest versenden.

        // 2. Mail-Empfänger ermitteln: Override → globale Setting → Makler → admin_email.
        $to = $notify_email ?: ($agent_email ?: get_option('admin_email'));

        // 3. Branded Mails versenden (Admin + Auto-Responder).
        $sent_admin = $this->send_property_admin_mail($to, $property_title, $client_permalink, $name, $email, $phone, $message);
        $this->send_property_inquirer_mail($email, $name, $property_title, $client_permalink, $phone, $message);

        if (!$sent_admin && is_wp_error($api_result)) {
            wp_send_json_error(array('message' => 'Anfrage konnte nicht zugestellt werden.'), 500);
        }

        wp_send_json_success(array(
            'message' => 'Vielen Dank! Ihre Anfrage wurde gesendet.',
        ));
    }

    private function send_property_admin_mail($to, $property_title, $property_url, $name, $email, $phone, $message) {
        $rows = array(
            array('Immobilie', $property_url
                ? '<a href="' . esc_url($property_url) . '" style="color:inherit;">' . esc_html($property_title) . '</a>'
                : esc_html($property_title)),
            array('Name',      esc_html($name)),
            array('E-Mail',    '<a href="mailto:' . esc_attr($email) . '" style="color:inherit;">' . esc_html($email) . '</a>'),
            array('Telefon',   esc_html($phone ?: '-')),
        );
        return ImmoMailer::send(
            $to,
            sprintf('Neue Anfrage zu %s', $property_title),
            $rows,
            'Es wurde eine neue Anfrage über Ihre Immobilien-Detailseite eingereicht:',
            $message,
            array(
                'heading'        => $property_title,
                'cta_url'        => $property_url,
                'cta_label'      => $property_url ? 'Zur Immobilie' : '',
                'reply_to'       => $email,
                'reply_to_name'  => $name,
            )
        );
    }

    private function send_property_inquirer_mail($to, $name, $property_title, $property_url, $phone, $message) {
        $rows = array(
            array('Immobilie', $property_url
                ? '<a href="' . esc_url($property_url) . '" style="color:inherit;">' . esc_html($property_title) . '</a>'
                : esc_html($property_title)),
            array('Name',      esc_html($name)),
            array('Telefon',   esc_html($phone ?: '-')),
        );
        return ImmoMailer::send(
            $to,
            sprintf('Ihre Anfrage zu %s', $property_title),
            $rows,
            sprintf('Hallo %s,<br><br>vielen Dank für Ihre Anfrage. Wir melden uns in Kürze bei Ihnen. Nachstehend die Zusammenfassung:', esc_html($name)),
            $message,
            array(
                'heading'   => $property_title,
                'cta_url'   => $property_url,
                'cta_label' => $property_url ? 'Zum Exposé' : '',
            )
        );
    }

    /**
     * Bauprojekt-Anfrage: ohne Manager, vollständig im Client mit Branding.
     */
    public function submit_project_inquiry() {
        check_ajax_referer('immo-project-inquiry-nonce', 'nonce');

        if (!empty($_POST['website'])) {
            wp_send_json_success(array('message' => 'Vielen Dank! Ihre Anfrage wurde gesendet.'));
        }

        $project_id = isset($_POST['project_id']) ? absint($_POST['project_id']) : 0;
        $name       = isset($_POST['inquirer_name'])    ? sanitize_text_field(wp_unslash($_POST['inquirer_name']))        : '';
        $email      = isset($_POST['inquirer_email'])   ? sanitize_email(wp_unslash($_POST['inquirer_email']))            : '';
        $phone      = isset($_POST['inquirer_phone'])   ? sanitize_text_field(wp_unslash($_POST['inquirer_phone']))       : '';
        $message    = isset($_POST['inquirer_message']) ? sanitize_textarea_field(wp_unslash($_POST['inquirer_message'])) : '';
        $consent    = !empty($_POST['consent']);
        $notify_raw = isset($_POST['notify_email']) ? sanitize_email(wp_unslash($_POST['notify_email'])) : '';

        $errors = array();
        if (!$project_id) $errors['project_id']     = 'Pflichtfeld.';
        if (!$name)       $errors['inquirer_name']  = 'Pflichtfeld.';
        if (!$email)      $errors['inquirer_email'] = 'Gültige E-Mail erforderlich.';
        if (!$consent)    $errors['consent']        = 'Datenschutz-Einwilligung erforderlich.';

        if ($errors) {
            wp_send_json_error(array(
                'message' => 'Eingabe fehlerhaft.',
                'errors'  => $errors,
            ), 422);
        }

        $api     = new ImmoAPI();
        $project = $api->get_project($project_id);
        if (!$project || empty($project['id'])) {
            wp_send_json_error(array('message' => 'Projekt nicht gefunden.'), 404);
        }

        $project_title = isset($project['title']) ? (string) $project['title'] : ('Bauprojekt #' . $project_id);
        $project_url   = !empty($project['slug']) ? home_url('/bauprojekt/' . $project['slug'] . '/') : '';
        $agent_email   = isset($project['meta']['contact_email']) ? sanitize_email((string) $project['meta']['contact_email']) : '';

        $global_setting = sanitize_email((string) get_option('immo_notify_email', ''));
        $to = $notify_raw ?: ($global_setting ?: ($agent_email ?: get_option('admin_email')));
        if (!is_email($to)) {
            wp_send_json_error(array('message' => 'Kein gültiger Empfänger konfiguriert.'), 500);
        }

        $rows_admin = array(
            array('Bauprojekt', $project_url
                ? '<a href="' . esc_url($project_url) . '" style="color:inherit;">' . esc_html($project_title) . '</a>'
                : esc_html($project_title)),
            array('Name',     esc_html($name)),
            array('E-Mail',   '<a href="mailto:' . esc_attr($email) . '" style="color:inherit;">' . esc_html($email) . '</a>'),
            array('Telefon',  esc_html($phone ?: '-')),
        );
        $sent_admin = ImmoMailer::send(
            $to,
            sprintf('Neue Anfrage zu %s', $project_title),
            $rows_admin,
            'Es wurde eine neue Anfrage über das Bauprojekt-Formular eingereicht:',
            $message,
            array(
                'heading'       => $project_title,
                'cta_url'       => $project_url,
                'cta_label'     => $project_url ? 'Zum Bauprojekt' : '',
                'reply_to'      => $email,
                'reply_to_name' => $name,
            )
        );

        $rows_inquirer = array(
            array('Bauprojekt', $project_url
                ? '<a href="' . esc_url($project_url) . '" style="color:inherit;">' . esc_html($project_title) . '</a>'
                : esc_html($project_title)),
            array('Name',     esc_html($name)),
            array('Telefon',  esc_html($phone ?: '-')),
        );
        ImmoMailer::send(
            $email,
            sprintf('Ihre Anfrage zu %s', $project_title),
            $rows_inquirer,
            sprintf('Hallo %s,<br><br>wir haben Ihre Anfrage erhalten und melden uns in Kürze bei Ihnen.', esc_html($name)),
            $message,
            array(
                'heading'   => $project_title,
                'cta_url'   => $project_url,
                'cta_label' => $project_url ? 'Zum Bauprojekt' : '',
            )
        );

        if (!$sent_admin) {
            wp_send_json_error(array('message' => 'Anfrage konnte nicht versendet werden. Bitte später erneut versuchen.'), 500);
        }

        wp_send_json_success(array(
            'message' => 'Vielen Dank! Ihre Anfrage wurde gesendet.',
        ));
    }
}

new ImmoAJAX();
