<?php

if (!defined('ABSPATH')) {
    exit;
}

class ImmoAJAX {

    /** Option-Key für den letzten fehlgeschlagenen Inquiry-API-Call. */
    const LAST_ERROR_OPTION = 'immo_last_inquiry_error';

    public function __construct() {
        add_action('wp_ajax_immo_filter_list',                   array($this, 'filter_list'));
        add_action('wp_ajax_nopriv_immo_filter_list',            array($this, 'filter_list'));
        add_action('wp_ajax_immo_submit_inquiry',                array($this, 'submit_inquiry'));
        add_action('wp_ajax_nopriv_immo_submit_inquiry',         array($this, 'submit_inquiry'));
        add_action('wp_ajax_immo_submit_project_inquiry',        array($this, 'submit_project_inquiry'));
        add_action('wp_ajax_nopriv_immo_submit_project_inquiry', array($this, 'submit_project_inquiry'));

        // Admin-Notice + Dismiss-Handler.
        add_action('admin_notices',                       array($this, 'render_inquiry_error_notice'));
        add_action('admin_post_immo_dismiss_inquiry_err', array($this, 'dismiss_inquiry_error'));
    }

    /**
     * API-Fehler beim Inquiry-Forward in error_log schreiben und
     * persistierte Admin-Notice füllen.
     *
     * @param string   $context     'property' oder 'project'.
     * @param WP_Error $error       Fehler.
     * @param array    $payload     Übergebene Payload-Felder (zum Debuggen, ohne PII zu loggen).
     */
    private function log_inquiry_api_error($context, $error, array $payload) {
        if (!is_wp_error($error)) {
            return;
        }
        $data    = $error->get_error_data();
        $status  = is_array($data) && isset($data['status']) ? (int) $data['status'] : 0;
        $message = $error->get_error_message();

        // error_log: bewusst ohne Mail/Telefon/Namen.
        $log_line = sprintf(
            '[ImmoClient] Inquiry-Forward (%s) fehlgeschlagen: %s [HTTP %d] property_id=%d source=%s',
            $context,
            $message,
            $status,
            isset($payload['property_id']) ? (int) $payload['property_id'] : 0,
            isset($payload['source_url']) ? (string) $payload['source_url'] : home_url('/')
        );
        error_log($log_line);

        update_option(self::LAST_ERROR_OPTION, array(
            'context'     => (string) $context,
            'message'     => (string) $message,
            'http_status' => $status,
            'property_id' => isset($payload['property_id']) ? (int) $payload['property_id'] : 0,
            'source_url'  => isset($payload['source_url']) ? (string) $payload['source_url'] : home_url('/'),
            'time'        => current_time('mysql'),
        ), false);
    }

    /**
     * Admin-Notice für letzten fehlgeschlagenen Inquiry-Forward.
     */
    public function render_inquiry_error_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $err = get_option(self::LAST_ERROR_OPTION);
        if (!is_array($err) || empty($err['message'])) {
            return;
        }

        $dismiss_url = wp_nonce_url(
            admin_url('admin-post.php?action=immo_dismiss_inquiry_err'),
            'immo_dismiss_inquiry_err'
        );

        echo '<div class="notice notice-error"><p><strong>ImmoClient:</strong> ';
        printf(
            esc_html__('Eine Anfrage konnte nicht an den ImmoManager übertragen werden (%s). Grund: %s', 'immo-client'),
            esc_html($err['context'] ?? '—'),
            esc_html($err['message'])
        );
        if (!empty($err['http_status'])) {
            echo ' (HTTP ' . (int) $err['http_status'] . ')';
        }
        if (!empty($err['property_id'])) {
            echo ' · property_id=' . (int) $err['property_id'];
        }
        if (!empty($err['time'])) {
            echo ' · ' . esc_html($err['time']);
        }
        echo '. <a href="' . esc_url(admin_url('options-general.php?page=immo-client')) . '">Einstellungen prüfen</a>';
        echo ' · <a href="' . esc_url($dismiss_url) . '">Hinweis ausblenden</a>';
        echo '</p></div>';
    }

    /**
     * Dismiss-Action für die Notice.
     */
    public function dismiss_inquiry_error() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Nicht erlaubt.', 'immo-client'));
        }
        check_admin_referer('immo_dismiss_inquiry_err');
        delete_option(self::LAST_ERROR_OPTION);
        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
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
        // Bei API-Fehler nicht abbrechen – Mail zumindest versenden, aber Fehler protokollieren.
        if (is_wp_error($api_result)) {
            $this->log_inquiry_api_error('property', $api_result, $payload);
        } elseif (is_array($api_result) && empty($api_result['success'])) {
            // Manager hat zwar geantwortet (z.B. mit success=false), aber nicht gespeichert.
            $msg = isset($api_result['message']) ? (string) $api_result['message'] : 'Unbekannte API-Antwort.';
            $this->log_inquiry_api_error('property', new WP_Error('immo_api_unexpected', $msg, array('status' => 200)), $payload);
        } else {
            // Erfolgreich → ggf. alten Fehler-Hinweis aufräumen.
            delete_option(self::LAST_ERROR_OPTION);
        }

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

        $project_id     = isset($_POST['project_id']) ? absint($_POST['project_id']) : 0;
        $name           = isset($_POST['inquirer_name'])    ? sanitize_text_field(wp_unslash($_POST['inquirer_name']))        : '';
        $email          = isset($_POST['inquirer_email'])   ? sanitize_email(wp_unslash($_POST['inquirer_email']))            : '';
        $phone          = isset($_POST['inquirer_phone'])   ? sanitize_text_field(wp_unslash($_POST['inquirer_phone']))       : '';
        $message        = isset($_POST['inquirer_message']) ? sanitize_textarea_field(wp_unslash($_POST['inquirer_message'])) : '';
        $consent        = !empty($_POST['consent']);
        $notify_raw     = isset($_POST['notify_email'])     ? sanitize_email(wp_unslash($_POST['notify_email']))              : '';
        // Optionales Wohneinheits-Feld — Unit-ID, Anzeige-Label wird unten aus dem API-Payload gezogen.
        $preferred_unit_id = isset($_POST['preferred_unit']) ? absint($_POST['preferred_unit']) : 0;

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

        // Bevorzugte Wohneinheit auflösen (optional).
        $preferred_unit_label = '';
        if ($preferred_unit_id > 0) {
            $units_payload = $api->get_project_units($project_id);
            $units_list    = isset($units_payload['units']) && is_array($units_payload['units']) ? $units_payload['units'] : array();
            foreach ($units_list as $u) {
                if ((int) ($u['id'] ?? 0) === $preferred_unit_id) {
                    $bits  = array();
                    if (!empty($u['unit_number'])) $bits[] = 'Top ' . $u['unit_number'];
                    if (!empty($u['area']))        $bits[] = $u['area'] . ' m²';
                    if (!empty($u['rooms']))       $bits[] = ((int) $u['rooms']) . ' Zi.';
                    if (!empty($u['status_label'])) $bits[] = $u['status_label'];
                    $preferred_unit_label = implode(' · ', $bits);
                    break;
                }
            }
        }

        $global_setting = sanitize_email((string) get_option('immo_notify_email', ''));
        $to = $notify_raw ?: ($global_setting ?: ($agent_email ?: get_option('admin_email')));
        if (!is_email($to)) {
            wp_send_json_error(array('message' => 'Kein gültiger Empfänger konfiguriert.'), 500);
        }

        $rows_admin = array(
            array('Bauprojekt', $project_url
                ? '<a href="' . esc_url($project_url) . '" style="color:inherit;">' . esc_html($project_title) . '</a>'
                : esc_html($project_title)),
        );
        if ($preferred_unit_label !== '') {
            $rows_admin[] = array('Bevorzugte Wohneinheit', esc_html($preferred_unit_label));
        }
        $rows_admin[] = array('Name',    esc_html($name));
        $rows_admin[] = array('E-Mail',  '<a href="mailto:' . esc_attr($email) . '" style="color:inherit;">' . esc_html($email) . '</a>');
        $rows_admin[] = array('Telefon', esc_html($phone ?: '-'));
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
        );
        if ($preferred_unit_label !== '') {
            $rows_inquirer[] = array('Bevorzugte Wohneinheit', esc_html($preferred_unit_label));
        }
        $rows_inquirer[] = array('Name',    esc_html($name));
        $rows_inquirer[] = array('Telefon', esc_html($phone ?: '-'));
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
