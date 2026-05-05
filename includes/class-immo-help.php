<?php
/**
 * Hilfsseite im WordPress-Admin: Einstellungen → ImmoClient Hilfe.
 *
 * Stellt Setup-Anleitung, Shortcode-Referenz, Anfrage-Logik,
 * Farbsystem und Troubleshooting-Tipps bereit.
 */

if (!defined('ABSPATH')) {
    exit;
}

class ImmoHelp {

    public function __construct() {
        add_action('admin_menu',           array($this, 'add_help_page'));
        add_action('admin_post_immo_flush', array($this, 'handle_flush'));
    }

    public function add_help_page() {
        add_options_page(
            'ImmoClient Hilfe',
            'ImmoClient Hilfe',
            'manage_options',
            'immo-client-help',
            array($this, 'render')
        );
    }

    public function handle_flush() {
        if (!current_user_can('manage_options')) {
            wp_die('Nicht berechtigt.');
        }
        check_admin_referer('immo_flush_rewrite');

        // Re-Register und hart flushen.
        if (class_exists('ImmoRouting')) {
            $r = new ImmoRouting();
            $r->add_rewrite_rules();
        }
        delete_option('immo_rewrite_version');
        flush_rewrite_rules(true);

        wp_safe_redirect(add_query_arg('flushed', '1', admin_url('options-general.php?page=immo-client-help')));
        exit;
    }

    private function rewrite_status() {
        global $wp_rewrite;
        $rules = get_option('rewrite_rules');
        if (!is_array($rules)) {
            $rules = $wp_rewrite ? $wp_rewrite->wp_rewrite_rules() : array();
        }
        $found = array();
        foreach ((array) $rules as $pattern => $target) {
            if (strpos($pattern, 'immobilie') !== false || strpos($pattern, 'bauprojekt') !== false) {
                $found[$pattern] = $target;
            }
        }
        return $found;
    }

    public function render() {
        $api_base = (string) get_option('immo_api_url', '');
        $rules    = $this->rewrite_status();
        $flushed  = isset($_GET['flushed']);
        ?>

        <?php if ($flushed) : ?>
            <div class="notice notice-success is-dismissible" style="margin: 15px 0;">
                <p>Rewrite-Regeln wurden neu geschrieben.</p>
            </div>
        <?php endif; ?>

        <div class="wrap" style="margin-top: 20px;">
            <h2 class="title">Diagnose &amp; Wartung</h2>
            <p>
                Wenn die URLs <code>/immobilie/{slug}</code> oder <code>/bauprojekt/{slug}</code> einen 404-Fehler werfen,
                hier einmal manuell die Rewrite-Regeln neu schreiben:
            </p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom: 20px;">
                <?php wp_nonce_field('immo_flush_rewrite'); ?>
                <input type="hidden" name="action" value="immo_flush_rewrite">
                <?php submit_button('Rewrite-Regeln neu schreiben', 'primary', 'submit', false); ?>
            </form>

            <p><strong>Aktive ImmoClient-Rewrite-Regeln:</strong></p>
            <?php if (!empty($rules)) : ?>
                <table class="widefat striped" style="max-width:900px;">
                    <thead><tr><th style="width:50%;">Pattern</th><th>Ziel</th></tr></thead>
                    <tbody>
                        <?php foreach ($rules as $pattern => $target) : ?>
                            <tr><td><code><?php echo esc_html($pattern); ?></code></td><td><code><?php echo esc_html($target); ?></code></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p style="color:#b91c1c;">Keine Regeln registriert. Klicke oben auf „Rewrite-Regeln neu schreiben".</p>
            <?php endif; ?>

            <p style="margin-top:10px;">
                Permalinks-Struktur: <code><?php echo esc_html(get_option('permalink_structure', '(plain)')); ?></code>
                <?php if (get_option('permalink_structure') === '') : ?>
                    – <strong style="color:#b91c1c;">Achtung:</strong> Plain-Permalinks sind aktiv. Wechsle unter
                    <a href="<?php echo esc_url(admin_url('options-permalink.php')); ?>">Einstellungen → Permalinks</a> auf eine andere Struktur (z.&nbsp;B. „Beitragsname").
                <?php endif; ?>
            </p>
        </div>

        <div class="wrap immo-help">
            <h1>ImmoClient – Dokumentation &amp; Hilfe</h1>

            <p>Diese Seite erklärt die Einrichtung und Nutzung des Plugins. Die Einstellungen findest du unter
                <a href="<?php echo esc_url(admin_url('options-general.php?page=immo-client')); ?>">Einstellungen → ImmoClient</a>.
            </p>

            <h2 class="title">1. Voraussetzungen</h2>
            <ul style="list-style: disc; margin-left: 20px;">
                <li>Eine zweite WordPress-Installation mit dem Plugin <strong>ImmoManager</strong> (mindestens v1.0).</li>
                <li>Die REST-API des Managers ist erreichbar unter <code>https://manager.example.com/wp-json/immo-manager/v1/</code>.</li>
                <li>Optional: ein API-Key, falls der Manager schreibende Endpunkte schützt.</li>
            </ul>

            <h2 class="title">2. Einrichtung</h2>
            <ol>
                <li>Plugin installieren und aktivieren.</li>
                <li>Unter <em>Einstellungen → ImmoClient</em> die <strong>Basis-URL</strong> des Managers eintragen
                    (z.&nbsp;B. <code>https://immobilien.example.com</code>). <code>/wp-json</code> wird automatisch ergänzt.</li>
                <li><strong>API-Key</strong> hinterlegen. Er wird bei jeder Anfrage als Header
                    <code>X-Immo-API-Key</code> mitgeschickt.</li>
                <li><strong>Empfänger-E-Mail</strong> für Kontaktanfragen eintragen
                    (überschreibbar pro Shortcode).</li>
                <li>Permalinks neu speichern (<em>Einstellungen → Permalinks → Speichern</em>),
                    damit die Detail-URLs <code>/immobilie/{slug}</code> und <code>/bauprojekt/{slug}</code> funktionieren.</li>
            </ol>

            <?php if ($api_base) : ?>
                <p><strong>Aktuell konfigurierte API-Basis:</strong> <code><?php echo esc_html($api_base); ?></code></p>
            <?php else : ?>
                <p style="color:#b91c1c;"><strong>Hinweis:</strong> Es ist noch keine API-URL hinterlegt.</p>
            <?php endif; ?>

            <h2 class="title">3. Shortcodes</h2>

            <h3>3.1 Liste mit Filter</h3>
            <p><code>[immo_list]</code> – rendert eine Übersicht von Immobilien oder Bauprojekten.</p>
            <table class="widefat striped">
                <thead>
                    <tr><th>Attribut</th><th>Werte</th><th>Default</th><th>Beschreibung</th></tr>
                </thead>
                <tbody>
                    <tr><td><code>type</code></td><td><code>properties</code> / <code>projects</code></td><td><code>properties</code></td><td>Typ der Liste.</td></tr>
                    <tr><td><code>limit</code></td><td>Zahl</td><td><code>12</code></td><td>Maximale Anzahl Einträge.</td></tr>
                    <tr><td><code>status</code></td><td><code>available</code>, <code>reserved</code>, <code>sold</code>, <code>rented</code></td><td>–</td><td>Optional auf Status filtern.</td></tr>
                    <tr><td><code>filters</code></td><td><code>yes</code> / <code>no</code></td><td><code>yes</code></td><td>Filterleiste anzeigen (nur bei <code>type=properties</code>).</td></tr>
                    <tr><td><code>primary</code></td><td>Hex-Farbe</td><td>aus Manager</td><td>Primärfarbe nur für diesen Block.</td></tr>
                    <tr><td><code>secondary</code></td><td>Hex-Farbe</td><td>aus Manager</td><td>Sekundärfarbe.</td></tr>
                    <tr><td><code>accent</code></td><td>Hex-Farbe</td><td>aus Manager</td><td>Akzentfarbe.</td></tr>
                    <tr><td><code>email</code></td><td>E-Mail</td><td>globale Setting</td><td>Override-Empfänger für Anfragen aus diesem Block.</td></tr>
                </tbody>
            </table>
            <p><strong>Beispiel:</strong></p>
            <pre style="background:#f3f4f6;padding:10px;border-radius:6px;">[immo_list type="properties" limit="9" status="available" primary="#0c5b97" accent="#22c55e" email="vermietung@example.com"]</pre>

            <h3>3.2 Einzelne Immobilie</h3>
            <p><code>[immo_property id="123"]</code> oder <code>[immo_property slug="schoene-wohnung-graz"]</code></p>
            <p>Akzeptiert zusätzlich <code>primary</code>, <code>secondary</code>, <code>accent</code>, <code>email</code>.</p>

            <h3>3.3 Einzelnes Bauprojekt</h3>
            <p><code>[immo_project id="45"]</code> oder <code>[immo_project slug="bauprojekt-graz"]</code></p>
            <p>Akzeptiert dieselben Farb- und E-Mail-Attribute.</p>

            <h3>3.4 Wohneinheiten eines Bauprojekts (isoliert) <span style="display:inline-block;padding:2px 8px;background:#dcfce7;color:#166534;font-size:11px;font-weight:600;border-radius:4px;letter-spacing:0.04em;text-transform:uppercase;vertical-align:middle;margin-left:6px;">Neu</span></h3>
            <p><code>[immo_units]</code> – rendert ausschließlich die Wohneinheiten-Liste eines Projekts ohne Galerie, Beschreibung oder Sidebar. Ideal für Elementor- oder Gutenberg-Seiten, in die nur die Einheiten dynamisch eingebaut werden sollen.</p>
            <table class="widefat striped">
                <thead>
                    <tr><th>Attribut</th><th>Werte</th><th>Default</th><th>Beschreibung</th></tr>
                </thead>
                <tbody>
                    <tr><td><code>project_id</code></td><td>Zahl</td><td>–</td><td>Projekt-ID. Hat Vorrang vor <code>project_slug</code>, wenn beide gesetzt sind.</td></tr>
                    <tr><td><code>project_slug</code></td><td>Slug</td><td>–</td><td>Projekt-Slug (z.&nbsp;B. <code>bauprojekt-graz</code>) – Alternative zur ID.</td></tr>
                    <tr><td><code>status</code></td><td>einzeln oder kommagetrennt: <code>available</code>, <code>reserved</code>, <code>sold</code>, <code>rented</code></td><td>– (alle)</td><td>Filtert die Wohneinheiten nach Status. Mehrfach-Filter via Komma: <code>status="available,reserved"</code>.</td></tr>
                    <tr><td><code>layout</code></td><td><code>table</code> / <code>grid</code> / <code>list</code></td><td><code>table</code></td><td>Darstellungsform: Tabelle, Card-Grid oder horizontale Liste mit Thumbs.</td></tr>
                    <tr><td><code>orderby</code></td><td><code>unit_number</code>, <code>floor</code>, <code>price</code>, <code>area</code></td><td><code>unit_number</code></td><td>Sortier-Schlüssel.</td></tr>
                    <tr><td><code>limit</code></td><td>Zahl ≥ 0</td><td><code>0</code> (alle)</td><td>Maximale Anzahl Treffer.</td></tr>
                    <tr><td><code>show_stats</code></td><td><code>yes</code> / <code>no</code></td><td><code>yes</code></td><td>Status-Counter über der Liste anzeigen.</td></tr>
                    <tr><td><code>primary</code> / <code>secondary</code> / <code>accent</code></td><td>Hex-Farbe</td><td>aus Manager</td><td>Farb-Override pro Block.</td></tr>
                </tbody>
            </table>
            <p><strong>Beispiele:</strong></p>
            <pre style="background:#f3f4f6;padding:10px;border-radius:6px;">[immo_units project_slug="bauprojekt-graz"]
[immo_units project_id="45" status="available" layout="grid"]
[immo_units project_slug="bauprojekt-graz" status="available,reserved" layout="list" show_stats="no" limit="6"]</pre>
            <p>
                Klick auf eine Card/Zeile öffnet eine <strong>Quick-Info-Lightbox</strong> mit Bild, Eckdaten, Adresse und Preis – im Modal befindet sich ein primary-farbener Button „Zur Detailseite", sofern die Wohneinheit mit einer Property verknüpft ist (sonst zeigt das Modal nur die Daten ohne Button). Mehrere <code>[immo_units]</code> auf derselben Seite teilen sich automatisch eine Lightbox.
            </p>

            <h2 class="title">4. Detailseiten</h2>
            <p>Pro Eintrag erzeugt das Plugin automatisch eine eigene URL:</p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><code>https://deine-seite.de/immobilie/{slug}</code></li>
                <li><code>https://deine-seite.de/bauprojekt/{slug}</code></li>
            </ul>
            <p>Slugs kommen aus den Manager-Posts. Bei 404 die Permalinks neu speichern.</p>

            <h2 class="title">5. Anfragen-Logik</h2>
            <table class="widefat">
                <thead><tr><th>Quelle</th><th>Versand</th><th>Speicherung</th><th>Empfänger</th></tr></thead>
                <tbody>
                    <tr>
                        <td>Immobilien-Formular<br>(<em>single-unit.php</em>)</td>
                        <td>Manager (<code>POST /inquiries</code>)</td>
                        <td>In der Manager-DB</td>
                        <td>Override aus Form → Makler-Mail der Immobilie → Manager-Settings</td>
                    </tr>
                    <tr>
                        <td>Bauprojekt-Formular<br>(<em>single-project.php</em> &amp; Shortcode)</td>
                        <td>Direkt vom Client per <code>wp_mail()</code></td>
                        <td>Wird nicht im Manager gespeichert</td>
                        <td>Override aus Shortcode → globale Setting → Projekt-Makler aus API → <code>admin_email</code></td>
                    </tr>
                </tbody>
            </table>

            <p><strong>Spam-Schutz:</strong> Beide Formulare nutzen das Consent-Häkchen, das Bauprojekt-Formular zusätzlich ein verstecktes Honeypot-Feld <code>website</code>.</p>

            <h2 class="title">6. Farben &amp; Design</h2>
            <p>Die Farben werden in dieser Reihenfolge aufgelöst:</p>
            <ol>
                <li>Shortcode-Attribut (<code>primary</code>, <code>secondary</code>, <code>accent</code>)</li>
                <li>Public-Settings vom Manager (<code>/settings/public</code>)</li>
                <li>Hardcodierte Defaults (<code>#1e88e5</code>, <code>#43a047</code>, <code>#ff9800</code>)</li>
            </ol>
            <p>Pro Shortcode-Container wird ein <code>&lt;style&gt;</code>-Block mit CSS-Variablen ausgegeben:
                <code>--immo-primary</code>, <code>--immo-secondary</code>, <code>--immo-accent</code>.
                Für eigenes Styling reichen diese Variablen in deinem Theme aus.</p>

            <h2 class="title">7. Cache</h2>
            <p>API-Antworten werden per Transient gecacht (Dauer in den Settings). Wenn neue Inhalte im Manager nicht erscheinen:</p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li>Cache-Dauer in den Settings kurzzeitig auf <code>0</code> setzen und speichern.</li>
                <li>Oder: Object-Cache leeren (z.&nbsp;B. via Caching-Plugin).</li>
            </ul>

            <h2 class="title">8. REST-Endpunkte (Manager)</h2>
            <p>Diese Endpunkte muss der ImmoManager bereitstellen. Mit aktuellem Manager &gt;= v1.0 sind sie alle vorhanden.</p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><code>GET /properties</code>, <code>/properties/{id}</code>, <code>/properties/by-slug/{slug}</code>, <code>/properties/{id}/similar</code></li>
                <li><code>GET /projects</code>, <code>/projects/{id}</code>, <code>/projects/by-slug/{slug}</code></li>
                <li><code>GET /projects/{id}/units</code> und <code>/projects/by-slug/{slug}/units</code> – Wohneinheiten eines Projekts. Akzeptiert <code>?status=</code> (einzeln oder kommagetrennt: <code>available,reserved</code>), <code>?orderby=</code>, <code>?limit=</code>. Antwort enthält <code>units</code>, <code>stats</code> (alle Status-Counts) und <code>applied_status</code>.</li>
                <li><code>GET /regions</code>, <code>/regions/{state}/districts</code>, <code>/features</code></li>
                <li><code>GET /settings/public</code>, <code>/search</code></li>
                <li><code>POST /inquiries</code> (akzeptiert optional <code>notify_email</code>)</li>
            </ul>
            <p>Property-Antworten enthalten zusätzlich <code>meta.commission_free</code> (boolean) und <code>meta.commission_free_label</code> (Beschriftung des „Provisionsfrei"-Badges, im Manager konfigurierbar).</p>

            <h2 class="title">9. Provisionsfrei-Badge</h2>
            <p>Properties, die im Manager als „provisionsfrei" markiert sind, zeigen automatisch ein gut sichtbares gelbes Patch-Badge:</p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li>Auf Listing-Cards (<code>[immo_list]</code>, <code>[immo_units]</code>) – Sticker oben-rechts auf dem Vorschaubild.</li>
                <li>Auf den Detail-Seiten <code>/immobilie/{slug}</code> – Sticker auf der Hero-Galerie.</li>
                <li>In der Tabellen-Ansicht des <code>[immo_units]</code>-Shortcodes – kompaktes Icon in der Preis-Spalte.</li>
            </ul>
            <p>
                Bedingung: Property ist als Kauf markiert (<code>mode = sale</code> oder <code>both</code>). Bei reiner Miete erscheint kein Badge.
                Beschriftung wird zentral im Manager konfiguriert (<em>Immo Manager → Einstellungen → Rechner → „Provisionsfrei-Badge: Beschriftung"</em>) und über REST als <code>meta.commission_free_label</code> geliefert – das Client-Plugin zeigt automatisch denselben Text.
            </p>

            <h2 class="title">10. Troubleshooting</h2>
            <table class="widefat striped">
                <thead><tr><th>Problem</th><th>Lösung</th></tr></thead>
                <tbody>
                    <tr><td>Detailseite zeigt „Immobilie nicht gefunden"</td><td>Permalinks neu speichern. Prüfen, ob der Manager <code>/properties/by-slug/</code> beantwortet.</td></tr>
                    <tr><td>Liste leer trotz Daten im Manager</td><td>API-URL und API-Key prüfen, Cache reduzieren.</td></tr>
                    <tr><td><code>[immo_units]</code> liefert „Projekt nicht gefunden"</td><td>Manager-Version prüfen: Slug-Endpoint <code>/projects/by-slug/{slug}/units</code> ist neu. Bei älterem Manager stattdessen <code>project_id</code> verwenden.</td></tr>
                    <tr><td>Anfrage-Formular liefert 401</td><td>API-Key fehlt oder ist falsch.</td></tr>
                    <tr><td>Mails kommen nicht an</td><td>Empfänger-E-Mail prüfen; Mailversand vom Server testen (z.&nbsp;B. SMTP-Plugin).</td></tr>
                    <tr><td>Farben greifen nicht</td><td>Theme überschreibt CSS-Variablen. Höhere Spezifität oder Shortcode-Attribute nutzen.</td></tr>
                    <tr><td>Provisionsfrei-Badge fehlt obwohl im Manager aktiv</td><td>Cache leeren (Cache-Dauer in Settings auf <code>0</code>). Property muss Modus „Kauf" oder „Beides" haben.</td></tr>
                </tbody>
            </table>

            <h2 class="title">11. Beispielseite anlegen</h2>
            <ol>
                <li>Neue Seite in WordPress erstellen, Titel z.&nbsp;B. „Aktuelle Immobilien".</li>
                <li>Inhalt: <code>[immo_list type="properties" limit="12" filters="yes"]</code></li>
                <li>Veröffentlichen. Filter und Detail-Links funktionieren ohne weitere Konfiguration.</li>
            </ol>
            <p>Für eine reine Wohneinheiten-Übersicht eines Projekts: <code>[immo_units project_slug="bauprojekt-graz" layout="grid"]</code> – funktioniert auch in jeder Elementor-Section per „Shortcode"-Widget.</p>
        </div>
        <?php
    }
}

new ImmoHelp();
