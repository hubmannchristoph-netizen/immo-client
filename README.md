# ImmoClient

WordPress-Plugin, das Immobilien und Bauprojekte aus einer entfernten **ImmoManager**-Installation per REST-API in eine beliebige WordPress-Seite einbindet. Das Frontend wird vollständig im Client gerendert (Shortcodes, eigene Detail-URLs, Anfrage-Formulare). Farben, Empfänger-Adressen für Anfragen und Caching sind pro Seite und global konfigurierbar.

> Hinweis: Dieses Plugin funktioniert nur in Kombination mit dem [ImmoManager](../immo-manager) als Datenquelle.

---

## Features

- **REST-Anbindung** an `immo-manager/v1` (Lese- und Schreibrouten).
- **Shortcodes** für Listen, einzelne Immobilien und Bauprojekte – mit Filterleiste.
- **Eigene Detailseiten** unter `/immobilie/{slug}` und `/bauprojekt/{slug}`.
- **Zwei Anfrage-Formulare**:
  - Immobilie → wird über den Manager gespeichert und versendet.
  - Bauprojekt → wird vom Client direkt per `wp_mail()` versendet, unabhängig von einer `property_id`.
- **Empfänger-Override** pro Shortcode-Block oder global in den Einstellungen.
- **Farb-Customization** über CSS-Variablen pro Block, Defaults aus dem Manager (`/settings/public`).
- **Honeypot-Spam-Schutz** beim Bauprojekt-Formular.
- **Transient-Cache** für API-Antworten mit konfigurierbarer Dauer.
- **„Provisionsfrei"-Badge** — gelber Patch auf Hero-Bildern und Listing-Cards bei Kauf-Immobilien mit `meta.commission_free=true` (Helper `immo_client_render_cf_badge()`). Beschriftung kommt aus `meta.commission_free_label` (vom Manager konfigurierbar).

---

## Anforderungen

- WordPress ≥ 6.0
- PHP ≥ 7.4
- Eine erreichbare ImmoManager-Installation (Plugin im Schwester-Repo) mit aktivem Namespace `immo-manager/v1`.

---

## Installation

1. Repository klonen oder als ZIP herunterladen.
2. Den Ordner `immo-client/` nach `wp-content/plugins/` kopieren.
3. Im WordPress-Backend unter *Plugins* aktivieren.
4. Permalinks einmal speichern (*Einstellungen → Permalinks → Speichern*), damit die Detail-URLs greifen.

```bash
cd wp-content/plugins
git clone https://github.com/<dein-account>/immo-client.git
```

---

## Konfiguration

Unter **Einstellungen → ImmoClient**:

| Feld | Beschreibung |
|------|--------------|
| Basis-URL | URL der Manager-Installation. `/wp-json` wird automatisch ergänzt. |
| API-Key | Wird bei jeder Anfrage als Header `X-Immo-API-Key` gesendet. |
| Empfänger-E-Mail | Default-Adresse für Anfrage-Mails. Pro Shortcode überschreibbar. |
| Cache-Dauer | Sekunden für die Transient-Cache-Speicherung von GET-Antworten. |

Eine ausführliche Hilfe ist im Backend unter **Einstellungen → ImmoClient Hilfe** verfügbar.

---

## Shortcodes

### Liste mit Filter

```text
[immo_list type="properties" limit="12" status="available" filters="yes"
           primary="#0c5b97" secondary="#22c55e" accent="#f59e0b"
           email="vermietung@example.com"]
```

| Attribut | Werte | Default | Beschreibung |
|----------|-------|---------|--------------|
| `type` | `properties` \| `projects` | `properties` | Listentyp |
| `limit` | Zahl | `12` | Maximale Treffer |
| `status` | `available`, `reserved`, `sold`, `rented` | – | Status-Filter |
| `filters` | `yes` \| `no` | `yes` | Filterleiste anzeigen (nur Properties) |
| `primary`, `secondary`, `accent` | Hex-Farben | aus Manager | Farbüberschreibung pro Block |
| `email` | E-Mail | globale Setting | Override-Empfänger für Anfragen |

### Einzelne Immobilie

```text
[immo_property id="123"]
[immo_property slug="schoene-wohnung-graz"]
```

### Einzelnes Bauprojekt

```text
[immo_project id="45"]
[immo_project slug="bauprojekt-graz" primary="#1e3a8a"]
```

Beide Shortcodes akzeptieren zusätzlich `primary`, `secondary`, `accent`, `email`.

### Wohneinheiten eines Bauprojekts (isoliert)

Rendert ausschließlich die Wohneinheits-Liste eines Projekts ohne Projekt-Galerie, Beschreibung oder Sidebar — flexibel platzierbar in Elementor, Gutenberg oder Theme-Templates.

```text
[immo_units project_id="45"]
[immo_units project_slug="bauprojekt-graz" status="available" layout="grid"]
[immo_units project_slug="bauprojekt-graz" status="available,reserved" layout="list" show_stats="no"]
```

| Attribut | Werte | Default | Beschreibung |
|----------|-------|---------|--------------|
| `project_id` | Zahl | – | Projekt-ID (alternativ zu `project_slug`, hat Vorrang) |
| `project_slug` | Slug | – | Projekt-Slug (z. B. `bauprojekt-graz`) |
| `status` | einzeln oder kommagetrennt: `available`, `reserved`, `sold`, `rented` | – (alle) | Status-Filter |
| `layout` | `table` \| `grid` \| `list` | `table` | Darstellungsform |
| `orderby` | `unit_number`, `floor`, `price`, `area`, … | `unit_number` | Sortier-Schlüssel |
| `limit` | Zahl ≥ 0 | `0` (alle) | Maximale Anzahl |
| `show_stats` | `yes` \| `no` | `yes` | Status-Counter über der Liste |
| `primary`, `secondary`, `accent` | Hex-Farben | aus Manager | Farb-Override pro Block |

---

## Detailseiten

| URL-Schema | Template |
|------------|----------|
| `/immobilie/{slug}` | `templates/single-unit.php` |
| `/bauprojekt/{slug}` | `templates/single-project.php` |

Templates können über das aktive Theme überschrieben werden, indem eine Datei unter `wp-content/themes/<theme>/immo-client/<filename>.php` abgelegt wird.

---

## Anfragen-Logik

| Quelle | Versand | Speicherung | Empfänger-Reihenfolge |
|--------|---------|-------------|------------------------|
| Immobilie | Manager via `POST /inquiries` | Manager-DB | Form-Override → Makler-Mail (Manager) → Manager-Settings |
| Bauprojekt | Client via `wp_mail()` | nicht persistent | Shortcode-Override → globale Setting → Projekt-Makler aus API → `admin_email` |

Beide Formulare verlangen eine Datenschutz-Einwilligung. Das Bauprojekt-Formular nutzt zusätzlich ein Honeypot-Feld.

---

## Farbsystem

Pro Shortcode-Block wird ein `<style>`-Block mit CSS-Variablen ausgegeben:

```css
#immo-list-1-4831 {
  --immo-primary: #0c5b97;
  --immo-secondary: #22c55e;
  --immo-accent: #f59e0b;
}
```

Die Variablen werden in `assets/css/immo-client.css` für Buttons, Status-Badges und Form-Fokus genutzt. Eigene Themes können sie selbst referenzieren.

Auflösungs-Reihenfolge:
1. Shortcode-Attribut.
2. `/settings/public` vom Manager (`primary_color`, `secondary_color`, `accent_color`).
3. Hardcodierte Defaults: `#1e88e5`, `#43a047`, `#ff9800`.

---

## Architektur

```
immo-client/
├── immo-client.php                # Plugin-Bootstrap, Asset-Enqueue
├── includes/
│   ├── class-immo-api.php         # REST-Wrapper (GET + POST), Header-Auth, Caching
│   ├── class-immo-ajax.php        # WP-AJAX-Handler (Filter, Property-Inquiry, Project-Inquiry)
│   ├── class-immo-help.php        # Admin-Hilfsseite
│   ├── class-immo-routing.php     # Rewrite-Rules + Template-Loader
│   ├── class-immo-settings.php    # Settings-Seite
│   ├── class-immo-shortcodes.php  # Shortcodes inkl. Container-CSS
│   └── class-immo-styles.php      # Farb-Auflösung + Inline-CSS
├── templates/
│   ├── filter-bar.php
│   ├── inquiry-form.php           # Property-Anfrage
│   ├── list-grid.php
│   ├── project-inquiry-form.php   # Projekt-Anfrage (Honeypot)
│   ├── shortcode-project.php
│   ├── shortcode-property.php
│   ├── single-project.php
│   └── single-unit.php
└── assets/
    ├── css/immo-client.css
    └── js/immo-filter.js
```

---

## Manager-Endpunkte (vorausgesetzt)

```
GET  /wp-json/immo-manager/v1/properties
GET  /wp-json/immo-manager/v1/properties/{id}
GET  /wp-json/immo-manager/v1/properties/by-slug/{slug}
GET  /wp-json/immo-manager/v1/properties/{id}/similar
GET  /wp-json/immo-manager/v1/projects
GET  /wp-json/immo-manager/v1/projects/{id}
GET  /wp-json/immo-manager/v1/projects/by-slug/{slug}
GET  /wp-json/immo-manager/v1/projects/{id}/units
GET  /wp-json/immo-manager/v1/regions
GET  /wp-json/immo-manager/v1/regions/{state}/districts
GET  /wp-json/immo-manager/v1/features
GET  /wp-json/immo-manager/v1/settings/public
GET  /wp-json/immo-manager/v1/search
POST /wp-json/immo-manager/v1/inquiries        # akzeptiert optional notify_email
```

Die `by-slug`-Routen und das `notify_email`-Feld setzen den ImmoManager in der mit diesem Client kompatiblen Version voraus.

---

## Troubleshooting

| Symptom | Vorgehen |
|---------|----------|
| Detailseite 404 | Permalinks neu speichern, danach `/immobilie/{slug}` testen. |
| Liste leer | API-URL & Key in Settings prüfen, Cache-Dauer kurz auf `0`. |
| `401` beim Senden einer Anfrage | API-Key prüfen oder im Manager Key-Pflicht ausschalten. |
| Mails kommen nicht an | Empfänger-Mail prüfen; SMTP-Plugin im Client einrichten. |
| Farben werden nicht angewendet | Theme überschreibt CSS-Variablen → Shortcode-Attribute oder höhere Spezifität nutzen. |

---

## Entwicklung

Cache während der Entwicklung deaktivieren:
- *Einstellungen → ImmoClient → Cache-Dauer = 0*

Templates im aktiven Theme überschreiben:
- `wp-content/themes/<theme>/immo-client/single-unit.php`
- `wp-content/themes/<theme>/immo-client/single-project.php`

---

## Lizenz

GPL-2.0-or-later (analog zur WordPress-Konvention). Siehe `LICENSE`, falls vorhanden.

---

## Mitwirken

Pull Requests sind willkommen. Vor größeren Änderungen bitte ein Issue eröffnen, damit Scope und Schnittstellen zum ImmoManager abgeglichen werden können.
