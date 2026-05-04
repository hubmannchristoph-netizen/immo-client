<?php
/**
 * Anfrage-Formular für ein Bauprojekt – wird vom Client selbst per Mail versendet.
 *
 * Erwartet:
 *   $project    = API-Response (mit id)
 *   $immo_email = (optional) Override-Empfänger; wenn leer, wird die globale Setting genutzt
 */

if (!defined('ABSPATH')) {
    exit;
}

if (empty($project) || empty($project['id'])) {
    return;
}

$project_id = (int) $project['id'];
$notify_to  = isset($immo_email) ? (string) $immo_email : (string) get_option('immo_notify_email', '');
$form_id    = 'immo-project-inquiry-' . $project_id . '-' . wp_rand(1000, 9999);
?>
<form class="immo-inquiry-form immo-project-inquiry-form" id="<?php echo esc_attr($form_id); ?>" method="post" novalidate>
    <input type="hidden" name="project_id"   value="<?php echo esc_attr($project_id); ?>">
    <input type="hidden" name="notify_email" value="<?php echo esc_attr($notify_to); ?>">

    <div class="immo-form-row">
        <label for="<?php echo esc_attr($form_id); ?>-name">Name *</label>
        <input type="text" id="<?php echo esc_attr($form_id); ?>-name" name="inquirer_name" required>
    </div>

    <div class="immo-form-row">
        <label for="<?php echo esc_attr($form_id); ?>-email">E-Mail *</label>
        <input type="email" id="<?php echo esc_attr($form_id); ?>-email" name="inquirer_email" required>
    </div>

    <div class="immo-form-row">
        <label for="<?php echo esc_attr($form_id); ?>-phone">Telefon</label>
        <input type="tel" id="<?php echo esc_attr($form_id); ?>-phone" name="inquirer_phone">
    </div>

    <div class="immo-form-row">
        <label for="<?php echo esc_attr($form_id); ?>-message">Nachricht</label>
        <textarea id="<?php echo esc_attr($form_id); ?>-message" name="inquirer_message" rows="5"></textarea>
    </div>

    <div class="immo-form-row immo-form-consent">
        <label>
            <input type="checkbox" name="consent" value="1" required>
            Ich willige ein, dass meine Daten zur Bearbeitung der Anfrage verarbeitet werden. *
        </label>
    </div>

    <!-- Honeypot: für Menschen unsichtbar, nicht ausfüllen. -->
    <div class="immo-honeypot" aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">
        <label>Website (bitte leer lassen)
            <input type="text" name="website" tabindex="-1" autocomplete="off">
        </label>
    </div>

    <div class="immo-form-actions">
        <button type="submit" class="button immo-inquiry-submit">Anfrage senden</button>
    </div>

    <div class="immo-inquiry-message" role="status" aria-live="polite"></div>
</form>
