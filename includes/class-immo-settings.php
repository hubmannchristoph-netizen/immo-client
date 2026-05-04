<?php

if (!defined('ABSPATH')) {
    exit;
}

class ImmoSettings {

    public function __construct() {
        add_action('admin_menu',            array($this, 'add_settings_page'));
        add_action('admin_init',            array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_color_picker'));
    }

    public function enqueue_color_picker($hook) {
        if ($hook !== 'settings_page_immo-client') {
            return;
        }
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_media();
        wp_add_inline_script(
            'wp-color-picker',
            'jQuery(function($){ $(".immo-color-field").wpColorPicker(); });'
        );
        wp_add_inline_script(
            'jquery',
            'jQuery(function($){
                $(".immo-logo-upload").on("click", function(e){
                    e.preventDefault();
                    var btn = $(this);
                    var frame = wp.media({ title: "Logo wählen", multiple: false, library: { type: "image" } });
                    frame.on("select", function(){
                        var att = frame.state().get("selection").first().toJSON();
                        $("#immo_email_logo_id").val(att.id);
                        $("#immo-logo-preview").html(att.url ? "<img src=\"" + att.url + "\" style=\"max-height:60px;border:1px solid #ddd;border-radius:4px;padding:4px;background:#fff;\">" : "");
                        $(".immo-logo-remove").show();
                    });
                    frame.open();
                });
                $(".immo-logo-remove").on("click", function(e){
                    e.preventDefault();
                    $("#immo_email_logo_id").val("");
                    $("#immo-logo-preview").html("");
                    $(this).hide();
                });
            });'
        );
    }

    public function add_settings_page() {
        add_options_page(
            'ImmoClient Einstellungen',
            'ImmoClient',
            'manage_options',
            'immo-client',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('immo_client_options', 'immo_api_url');
        register_setting('immo_client_options', 'immo_api_key');
        register_setting('immo_client_options', 'immo_cache_duration');
        register_setting('immo_client_options', 'immo_notify_email');
        register_setting('immo_client_options', 'immo_detail_layout');
        register_setting('immo_client_options', 'immo_detail_max_width');
        register_setting('immo_client_options', 'immo_detail_use_theme_wrapper');
        register_setting('immo_client_options', 'immo_detail_hide_theme_title');
        register_setting('immo_client_options', 'immo_project_link_units');
        register_setting('immo_client_options', 'immo_color_primary');
        register_setting('immo_client_options', 'immo_color_secondary');
        register_setting('immo_client_options', 'immo_color_accent');
        register_setting('immo_client_options', 'immo_email_logo_id');
        register_setting('immo_client_options', 'immo_email_sender_name');
        register_setting('immo_client_options', 'immo_email_sender_email');
        register_setting('immo_client_options', 'immo_email_footer_note');
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>ImmoClient Einstellungen</h1>

            <div class="notice notice-info inline" style="margin-top: 15px; padding: 10px 20px;">
                <h2>Hilfe &amp; Einrichtung</h2>
                <p>Willkommen beim ImmoClient Plugin!</p>
                <p><strong>Wichtig:</strong> Dieses Plugin funktioniert ausschließlich in Kombination mit einem <strong>ImmoManager</strong>. Die Daten werden über dessen REST-API (<code>/wp-json/immo-manager/v1/</code>) abgerufen.</p>
                <p><strong>So verwenden Sie das Plugin:</strong></p>
                <ol>
                    <li>Tragen Sie unten die <strong>Basis-URL</strong> Ihres ImmoManagers ein – z.&nbsp;B. <code>https://immobilien.example.com</code> oder <code>https://immobilien.example.com/wp-json</code>. Das Plugin ergänzt <code>/wp-json</code> bei Bedarf automatisch.</li>
                    <li><strong>API-Key:</strong> Wird im Header aller Anfragen mitgeschickt. Erforderlich, wenn der Manager eine Key-Prüfung aktiviert hat.</li>
                    <li><strong>Empfänger-E-Mail:</strong> An diese Adresse werden Kontaktanfragen aus dem Frontend gesendet. Pro Shortcode kann sie mit dem Attribut <code>email="…"</code> überschrieben werden.</li>
                    <li>Speichern Sie die Einstellungen. Anschließend können Sie die Immobilien über Shortcodes einbinden.</li>
                </ol>
                <p><strong>Shortcodes:</strong></p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><code>[immo_list type="properties" limit="12" filters="yes" primary="#0073aa" accent="#155724" email="info@example.com"]</code></li>
                    <li><code>[immo_list type="projects" limit="6"]</code></li>
                    <li><code>[immo_property id="123"]</code> oder <code>[immo_property slug="schoene-wohnung"]</code></li>
                    <li><code>[immo_project id="45"]</code> oder <code>[immo_project slug="bauprojekt-graz"]</code></li>
                </ul>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields('immo_client_options');
                do_settings_sections('immo_client_options');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Basis-URL</th>
                        <td>
                            <input type="url" name="immo_api_url" value="<?php echo esc_attr(get_option('immo_api_url')); ?>" class="regular-text" placeholder="https://immobilien.example.com" />
                            <p class="description">Mit oder ohne <code>/wp-json</code>. Ohne abschließenden Slash.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">API-Key</th>
                        <td>
                            <input type="password" name="immo_api_key" value="<?php echo esc_attr(get_option('immo_api_key')); ?>" class="regular-text" autocomplete="off" />
                            <p class="description">Wird bei jeder Anfrage im Header <code>X-Immo-API-Key</code> mitgesendet.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Empfänger-E-Mail (Anfragen)</th>
                        <td>
                            <input type="email" name="immo_notify_email" value="<?php echo esc_attr(get_option('immo_notify_email')); ?>" class="regular-text" placeholder="info@example.com" />
                            <p class="description">An diese Adresse werden Anfragen aus dem Kontaktformular gesendet. Überschreibbar pro Shortcode mit <code>email="…"</code>.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Cache-Dauer (Sekunden)</th>
                        <td><input type="number" name="immo_cache_duration" value="<?php echo esc_attr(get_option('immo_cache_duration', 3600)); ?>" class="small-text" min="0" /></td>
                    </tr>
                </table>

                <h2 class="title">Detailseiten-Layout</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Sidebar-Position</th>
                        <td>
                            <?php $layout = get_option('immo_detail_layout', 'right_sidebar'); ?>
                            <select name="immo_detail_layout">
                                <option value="right_sidebar" <?php selected($layout, 'right_sidebar'); ?>>Sidebar rechts (Standard)</option>
                                <option value="left_sidebar"  <?php selected($layout, 'left_sidebar');  ?>>Sidebar links</option>
                                <option value="full_width"    <?php selected($layout, 'full_width');    ?>>Volle Breite (Sidebar unten)</option>
                                <option value="no_sidebar"    <?php selected($layout, 'no_sidebar');    ?>>Ohne Sidebar (kein Anfrageblock)</option>
                            </select>
                            <p class="description">Anordnung der Anfrage-/Kontakt-Sidebar auf den Detailseiten.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Container-Breite (px)</th>
                        <td>
                            <input type="number" name="immo_detail_max_width" value="<?php echo esc_attr(get_option('immo_detail_max_width', 1200)); ?>" class="small-text" min="600" max="2000" />
                            <p class="description">Maximale Breite des Detailseiten-Inhalts. Standard: 1200&nbsp;px.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Theme-Wrapper</th>
                        <td>
                            <?php $use_wrap = get_option('immo_detail_use_theme_wrapper', '1'); ?>
                            <label>
                                <input type="checkbox" name="immo_detail_use_theme_wrapper" value="1" <?php checked($use_wrap, '1'); ?> />
                                Theme-Header und -Footer einbinden (<code>get_header()</code>/<code>get_footer()</code>)
                            </label>
                            <p class="description">Ausschalten, falls dein Theme/Pagebuilder bereits einen eigenen Container um den Inhalt setzt.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Theme-Page-Titel verstecken</th>
                        <td>
                            <?php $hide_title = get_option('immo_detail_hide_theme_title', '1'); ?>
                            <label>
                                <input type="checkbox" name="immo_detail_hide_theme_title" value="1" <?php checked($hide_title, '1'); ?> />
                                Doppelten Page-Titel des Themes ausblenden
                            </label>
                            <p class="description">Versteckt typische Theme-Title-Bereiche (<code>.entry-title</code>, <code>.page-title</code>, <code>.elementor-page-title</code>, <code>.ast-page-title-area</code>) auf den Detailseiten, damit nur noch der Immobilienname als H1 sichtbar ist.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Wohneinheiten verlinken</th>
                        <td>
                            <?php $link_units = (string) get_option('immo_project_link_units', '1'); ?>
                            <input type="hidden" name="immo_project_link_units" value="0" />
                            <label>
                                <input type="checkbox" name="immo_project_link_units" value="1" <?php checked($link_units !== '0'); ?> />
                                „Immobilien-Details"-Button bei Wohneinheiten auf der Bauprojekt-Detailseite anzeigen
                            </label>
                            <p class="description">Wenn aktiviert, erscheint unter jeder Einheit auf der Projektseite ein Button, der auf die Einzelansicht der Immobilie verlinkt. Deaktivieren, wenn die Einheiten nicht öffentlich verlinkt werden sollen.</p>
                        </td>
                    </tr>
                </table>

                <h2 class="title">Farben</h2>
                <p class="description" style="margin-bottom: 10px;">Diese Farben überschreiben die Defaults aus dem Manager und werden für Buttons, Status-Badges, Akzente und Gradients verwendet. Sie können pro Shortcode mit <code>primary="…"</code> usw. überschrieben werden.</p>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Primärfarbe</th>
                        <td>
                            <input type="text" name="immo_color_primary" class="immo-color-field" value="<?php echo esc_attr(get_option('immo_color_primary', '')); ?>" data-default-color="#1e88e5" />
                            <p class="description">Hauptfarbe für Buttons, Links, Preis-Box-Gradient.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Sekundärfarbe</th>
                        <td>
                            <input type="text" name="immo_color_secondary" class="immo-color-field" value="<?php echo esc_attr(get_option('immo_color_secondary', '')); ?>" data-default-color="#43a047" />
                            <p class="description">Sekundärfarbe für „Verfügbar"-Status, Erfolgsmeldungen, Gradient-Endpunkt.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Akzentfarbe</th>
                        <td>
                            <input type="text" name="immo_color_accent" class="immo-color-field" value="<?php echo esc_attr(get_option('immo_color_accent', '')); ?>" data-default-color="#ff9800" />
                            <p class="description">Akzent für Highlights, „Reserviert"-Status, sekundäre Buttons.</p>
                        </td>
                    </tr>
                </table>

                <h2 class="title">E-Mail-Branding</h2>
                <p class="description" style="margin-bottom: 10px;">Diese Einstellungen werden für alle vom Plugin versendeten E-Mails (Anfragen + Auto-Responder) verwendet, damit der Empfänger sieht, dass die Mail von dieser Site kommt – nicht vom ImmoManager.</p>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Logo</th>
                        <td>
                            <?php
                            $logo_id  = (int) get_option('immo_email_logo_id', 0);
                            $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';
                            ?>
                            <input type="hidden" id="immo_email_logo_id" name="immo_email_logo_id" value="<?php echo esc_attr($logo_id); ?>" />
                            <div id="immo-logo-preview">
                                <?php if ($logo_url) : ?>
                                    <img src="<?php echo esc_url($logo_url); ?>" style="max-height:60px;border:1px solid #ddd;border-radius:4px;padding:4px;background:#fff;">
                                <?php endif; ?>
                            </div>
                            <p style="margin-top:8px;">
                                <button type="button" class="button immo-logo-upload">Logo aus Mediathek wählen</button>
                                <button type="button" class="button immo-logo-remove" style="<?php echo $logo_id ? '' : 'display:none;'; ?>">Logo entfernen</button>
                            </p>
                            <p class="description">Wird oben in jeder versendeten E-Mail angezeigt. Empfohlen: PNG/SVG, max. 60&nbsp;px Höhe.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Absendername</th>
                        <td>
                            <input type="text" name="immo_email_sender_name" value="<?php echo esc_attr(get_option('immo_email_sender_name', get_bloginfo('name'))); ?>" class="regular-text" />
                            <p class="description">Wird im „From:"-Header verwendet. Default: Site-Name.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Absender-E-Mail</th>
                        <td>
                            <input type="email" name="immo_email_sender_email" value="<?php echo esc_attr(get_option('immo_email_sender_email', '')); ?>" class="regular-text" placeholder="noreply@<?php echo esc_attr(wp_parse_url(home_url(), PHP_URL_HOST)); ?>" />
                            <p class="description">Default: <code>noreply@&lt;deine-domain&gt;</code>. Leer lassen für automatischen Wert.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Footer-Hinweis</th>
                        <td>
                            <input type="text" name="immo_email_footer_note" value="<?php echo esc_attr(get_option('immo_email_footer_note', '')); ?>" class="regular-text" placeholder="z. B. &quot;Diese Mail wurde über deine-domain.de gesendet.&quot;" />
                            <p class="description">Optionaler Zusatz im E-Mail-Footer. Leer lassen für „Diese E-Mail wurde automatisch von &lt;Site-Name&gt; gesendet."</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

new ImmoSettings();
