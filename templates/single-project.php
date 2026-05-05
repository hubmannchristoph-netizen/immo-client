<?php
/**
 * Detailseite eines Bauprojekts unter /bauprojekt/{slug}.
 * Layout analog single-unit.php: links Inhalt + Wohneinheiten,
 * rechts Sidebar mit Statistik, Standort und Anfrageformular.
 */

if (!defined('ABSPATH')) {
    exit;
}

$immo_layout    = (string) get_option('immo_detail_layout', 'right_sidebar');
$immo_max_width = (int)    get_option('immo_detail_max_width', 1200);
$immo_use_wrap  = (string) get_option('immo_detail_use_theme_wrapper', '1') === '1';
// Default: aktiviert. Nur wenn explizit auf '0' gesetzt, wird der Button versteckt.
$immo_link_units = ((string) get_option('immo_project_link_units', '1')) !== '0';

if ($immo_use_wrap) {
    get_header();
}

$project_slug = get_query_var('immo_project_slug');
$api          = new ImmoAPI();
$project      = $api->get_project_by_slug($project_slug);

if (!$project) :
    ?>
    <div class="immo-container immo-detail-notfound">
        <h1>Projekt nicht gefunden</h1>
        <p>Das gewünschte Bauprojekt ist nicht mehr verfügbar.</p>
    </div>
    <?php
    if ($immo_use_wrap) {
        get_footer();
    }
    return;
endif;

$meta        = isset($project['meta']) ? $project['meta'] : array();
$hero        = isset($project['featured_image']) ? $project['featured_image'] : null;
$gallery     = isset($project['gallery']) && is_array($project['gallery']) ? $project['gallery'] : array();
$unit_stats  = isset($project['unit_stats']) ? $project['unit_stats'] : array();

$city        = isset($meta['city'])               ? $meta['city']               : '';
$plz         = isset($meta['postal_code'])        ? $meta['postal_code']        : '';
$state_label = isset($meta['region_state_label']) ? $meta['region_state_label'] : '';
$dist_label  = isset($meta['region_district_label']) ? $meta['region_district_label'] : '';
$address     = isset($meta['address'])            ? $meta['address']            : '';
$status      = isset($meta['status'])             ? $meta['status']             : '';
$status_label = isset($meta['status_label'])      ? $meta['status_label']       : '';
$start_date  = isset($meta['construction_start']) ? $meta['construction_start'] : '';
$completion  = isset($meta['completion_date'])    ? $meta['completion_date']    : '';

$location_str = trim(implode(' ', array_filter(array($plz, $city))));
if ($state_label) $location_str .= ($location_str ? ', ' : '') . $state_label;

// Slides für Galerie (Hero + zusätzliche Bilder).
$slides = array();
if ($hero && !empty($hero['url'])) {
    $slides[] = $hero;
}
foreach ($gallery as $g) {
    if (!empty($g['url'])) {
        $slides[] = $g;
    }
}

// Einheiten und Statuszählung.
$units_payload = $api->get_project_units($project['id']);
$items         = isset($units_payload['units']) ? $units_payload['units'] : array();

$count_total     = (int) (isset($unit_stats['total'])     ? $unit_stats['total']     : count($items));
$count_available = (int) (isset($unit_stats['available']) ? $unit_stats['available'] : 0);
$count_reserved  = (int) (isset($unit_stats['reserved'])  ? $unit_stats['reserved']  : 0);
$count_sold      = (int) (isset($unit_stats['sold'])      ? $unit_stats['sold']      : 0);
$count_rented    = (int) (isset($unit_stats['rented'])    ? $unit_stats['rented']    : 0);

// Falls Statuszählung nicht vom API kommt: aus Items ableiten.
if (!isset($unit_stats['available']) && !empty($items)) {
    foreach ($items as $u) {
        switch (isset($u['status']) ? $u['status'] : '') {
            case 'available': $count_available++; break;
            case 'reserved':  $count_reserved++;  break;
            case 'sold':      $count_sold++;      break;
            case 'rented':    $count_rented++;    break;
        }
    }
}

$layout_class       = 'immo-layout-' . sanitize_key($immo_layout);
$style_attr         = $immo_max_width ? ' style="max-width:' . esc_attr($immo_max_width) . 'px;"' : '';
$title_in_sidebar   = in_array($immo_layout, array('right_sidebar', 'left_sidebar'), true);
?>

<article id="immo-project-<?php echo esc_attr($project['id']); ?>" class="immo-detail immo-project-detail <?php echo esc_attr($layout_class); ?>"<?php echo $style_attr; ?>>

    <div class="immo-detail-grid">

        <main class="immo-main">

            <?php if (!$title_in_sidebar) : ?>
                <header class="immo-title-block">
                    <?php if ($status_label || $status) : ?>
                        <span class="immo-status immo-status-<?php echo esc_attr($status); ?>"><?php echo esc_html($status_label ?: $status); ?></span>
                    <?php endif; ?>
                    <h1><?php echo esc_html($project['title']); ?></h1>
                    <?php if ($location_str || $address) : ?>
                        <p class="immo-location"><?php echo esc_html($location_str); ?><?php if ($address) echo ' · ' . esc_html($address); ?></p>
                    <?php endif; ?>
                </header>
            <?php endif; ?>

            <?php if (!empty($slides)) : ?>
                <section class="immo-gallery" aria-label="Bildergalerie">
                    <div class="immo-gallery-stage">
                        <?php foreach ($slides as $idx => $img) : ?>
                            <div class="immo-slide<?php echo $idx === 0 ? ' is-active' : ''; ?>"
                                 data-large="<?php echo esc_url(!empty($img['url_large']) ? $img['url_large'] : $img['url']); ?>">
                                <img src="<?php echo esc_url(!empty($img['url_large']) ? $img['url_large'] : $img['url']); ?>"
                                     alt="<?php echo esc_attr(!empty($img['alt']) ? $img['alt'] : $project['title']); ?>"
                                     loading="<?php echo $idx === 0 ? 'eager' : 'lazy'; ?>">
                            </div>
                        <?php endforeach; ?>

                        <?php if (count($slides) > 1) : ?>
                            <button type="button" class="immo-nav-prev" aria-label="Vorheriges Bild">&#8249;</button>
                            <button type="button" class="immo-nav-next" aria-label="Nächstes Bild">&#8250;</button>
                            <div class="immo-slide-counter"></div>
                        <?php endif; ?>
                        <button type="button" class="immo-expand" aria-label="Vergrößern" tabindex="-1">⤢</button>
                    </div>

                    <?php if (count($slides) > 1) : ?>
                        <div class="immo-thumbs">
                            <?php foreach ($slides as $idx => $img) : ?>
                                <button type="button" class="immo-thumb<?php echo $idx === 0 ? ' is-active' : ''; ?>" aria-label="Bild <?php echo esc_attr($idx + 1); ?>">
                                    <img src="<?php echo esc_url(!empty($img['url_thumbnail']) ? $img['url_thumbnail'] : $img['url']); ?>" alt="" loading="lazy">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if (!empty($project['description'])) : ?>
                <section class="immo-section immo-description">
                    <h2>Beschreibung</h2>
                    <?php echo wp_kses_post($project['description']); ?>
                </section>
            <?php endif; ?>

            <section class="immo-section immo-units-list">
                <h2>Wohneinheiten <?php if ($count_total) echo '<span class="immo-units-count">' . esc_html($count_total) . '</span>'; ?></h2>

                <?php if ($count_total > 0) : ?>
                    <div class="immo-units-summary">
                        <?php if ($count_available) : ?>
                            <button type="button" class="immo-units-stat immo-units-stat-available" data-immo-filter-status="available" aria-pressed="false" title="Nur verfügbare Einheiten anzeigen"><strong><?php echo esc_html($count_available); ?></strong> Verfügbar</button>
                        <?php endif; ?>
                        <?php if ($count_reserved) : ?>
                            <button type="button" class="immo-units-stat immo-units-stat-reserved" data-immo-filter-status="reserved" aria-pressed="false" title="Nur reservierte Einheiten anzeigen"><strong><?php echo esc_html($count_reserved); ?></strong> Reserviert</button>
                        <?php endif; ?>
                        <?php if ($count_sold) : ?>
                            <button type="button" class="immo-units-stat immo-units-stat-sold" data-immo-filter-status="sold" aria-pressed="false" title="Nur verkaufte Einheiten anzeigen"><strong><?php echo esc_html($count_sold); ?></strong> Verkauft</button>
                        <?php endif; ?>
                        <?php if ($count_rented) : ?>
                            <button type="button" class="immo-units-stat immo-units-stat-rented" data-immo-filter-status="rented" aria-pressed="false" title="Nur vermietete Einheiten anzeigen"><strong><?php echo esc_html($count_rented); ?></strong> Vermietet</button>
                        <?php endif; ?>
                        <span class="immo-units-stat immo-units-stat-total"><strong><?php echo esc_html($count_total); ?></strong> Gesamt</span>
                    </div>
                <?php endif; ?>

                <?php
                // URL-Lookup einmal vorab berechnen (mit voller Property-Auflösung über Slug ODER ID).
                if (!function_exists('immo_pick_field')) {
                    function immo_pick_field($arr, $keys) {
                        if (!is_array($arr)) return '';
                        foreach ($keys as $k) {
                            if (isset($arr[$k]) && $arr[$k] !== '' && $arr[$k] !== null) {
                                return $arr[$k];
                            }
                        }
                        return '';
                    }
                }
                $unit_urls = array();
                foreach ($items as $u) {
                    $u_id   = isset($u['id']) ? (int) $u['id'] : 0;
                    $u_p    = !empty($u['property']) ? $u['property'] : array();
                    $u_slug = '';

                    // 1) Slug aus den üblichen Feldnamen.
                    $u_slug = (string) (
                        immo_pick_field($u_p, array('slug','post_name','permalink_slug','property_slug','name'))
                        ?: immo_pick_field($u, array('property_slug','slug','post_name','property_name'))
                    );

                    // 2) Slug aus URL/Link-Feldern extrahieren (letztes Pfad-Segment).
                    if (!$u_slug) {
                        $u_link = (string) (
                            immo_pick_field($u_p, array('url','link','permalink','href','detail_url','public_url'))
                            ?: immo_pick_field($u, array('url','link','permalink','href','detail_url','public_url','property_url','property_link'))
                        );
                        if ($u_link !== '') {
                            $path = parse_url($u_link, PHP_URL_PATH);
                            if ($path) {
                                $segments = array_values(array_filter(explode('/', trim($path, '/'))));
                                if (!empty($segments)) $u_slug = end($segments);
                            }
                        }
                    }

                    // 3) Property-ID nutzen, um vollständige Property nachzuladen und Slug zu extrahieren.
                    if (!$u_slug) {
                        $u_pid = (int) (
                            immo_pick_field($u_p, array('id','ID','post_id','property_id'))
                            ?: immo_pick_field($u, array('property_id','property_ID','post_id','immo_property_id'))
                        );
                        if ($u_pid) {
                            $fp = $api->get_property($u_pid);
                            if (is_array($fp)) {
                                $u_slug = (string) immo_pick_field($fp, array('slug','post_name'));
                                if (!$u_slug) {
                                    $fp_link = (string) immo_pick_field($fp, array('url','link','permalink','href','detail_url','public_url'));
                                    if ($fp_link !== '') {
                                        $path = parse_url($fp_link, PHP_URL_PATH);
                                        if ($path) {
                                            $segments = array_values(array_filter(explode('/', trim($path, '/'))));
                                            if (!empty($segments)) $u_slug = end($segments);
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // 4) Letzter Fallback: Titel zu Slug konvertieren.
                    if (!$u_slug) {
                        $title_for_slug = (string) (immo_pick_field($u_p, array('title','name')) ?: immo_pick_field($u, array('title','name')));
                        if ($title_for_slug !== '') {
                            $u_slug = sanitize_title($title_for_slug);
                        }
                    }

                    // URL immer gegen die eigene Domain bauen.
                    $unit_urls[$u_id] = $u_slug ? home_url('/immobilie/' . $u_slug) : '';
                }
                ?>

                <?php if (empty($items)) : ?>
                    <p>Aktuell sind keine Einheiten zum Projekt hinterlegt.</p>
                <?php else : ?>
                    <div class="immo-unit-table-wrapper">
                        <table class="immo-unit-table">
                            <thead>
                                <tr>
                                    <th class="col-nr">Nr.</th>
                                    <th class="col-title">Bezeichnung</th>
                                    <th class="col-status">Status</th>
                                    <th class="col-area">Wohnfläche</th>
                                    <th class="col-rooms">Zimmer</th>
                                    <th class="col-floor">Etage</th>
                                    <th class="col-price">Preis</th>
                                    <th class="col-info" aria-label="Info"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $unit) :
                                    $unit_property = !empty($unit['property']) ? $unit['property'] : null;
                                    $unit_title    = $unit_property && !empty($unit_property['title']) ? $unit_property['title'] : ('Wohneinheit ' . (isset($unit['unit_number']) ? $unit['unit_number'] : ''));
                                    $unit_number   = isset($unit['unit_number']) ? $unit['unit_number'] : '';
                                    $unit_id       = isset($unit['id']) ? (int) $unit['id'] : 0;
                                    $u_status      = isset($unit['status']) ? $unit['status'] : '';
                                    $u_status_lbl  = isset($unit['status_label']) ? $unit['status_label'] : $u_status;
                                    $u_area        = isset($unit['area']) ? $unit['area'] : '';
                                    $u_rooms       = isset($unit['rooms']) ? (int) $unit['rooms'] : 0;
                                    $u_floor       = isset($unit['floor']) ? $unit['floor'] : '';
                                    $u_price       = isset($unit['price_formatted']) ? $unit['price_formatted'] : '';
                                ?>
                                    <?php $row_url = isset($unit_urls[$unit_id]) ? $unit_urls[$unit_id] : ''; ?>
                                    <tr class="immo-unit-row" data-immo-unit-id="<?php echo esc_attr($unit_id); ?>" data-status="<?php echo esc_attr($u_status); ?>" data-immo-unit-url="<?php echo esc_attr($row_url); ?>">
                                        <td class="col-nr"><?php echo esc_html($unit_number); ?></td>
                                        <td class="col-title"><?php echo esc_html($unit_title); ?></td>
                                        <td class="col-status">
                                            <?php if ($u_status) : ?>
                                                <span class="immo-status immo-status-<?php echo esc_attr($u_status); ?>"><?php echo esc_html($u_status_lbl); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="col-area"><?php echo $u_area !== '' ? esc_html($u_area) . ' m²' : '–'; ?></td>
                                        <td class="col-rooms"><?php echo $u_rooms > 0 ? esc_html($u_rooms) : '–'; ?></td>
                                        <td class="col-floor"><?php echo $u_floor !== '' ? esc_html($u_floor) . '. OG' : '–'; ?></td>
                                        <td class="col-price">
                                            <?php echo $u_price ? esc_html($u_price) : '–'; ?>
                                            <?php
                                            // „Provisionsfrei"-Icon, wenn die Property das Flag trägt
                                            // UND ein Kaufpreis vorhanden ist.
                                            if ( ! empty( $unit_property['commission_free'] ) && $u_price ) {
                                                immo_client_render_cf_badge(
                                                    array(
                                                        'commission_free'       => true,
                                                        'mode'                  => 'sale',
                                                        'commission_free_label' => isset( $unit_property['commission_free_label'] ) ? $unit_property['commission_free_label'] : '',
                                                    ),
                                                    'icon'
                                                );
                                            }
                                            ?>
                                        </td>
                                        <td class="col-info">
                                            <span class="immo-unit-info-btn" role="button" tabindex="-1" aria-label="Quick-Info zu <?php echo esc_attr($unit_title); ?>">
                                                <svg class="immo-unit-info-svg" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php /* Verstecktes Datenpool für die Lightbox – pro Einheit das gerenderte HTML */ ?>
                    <div id="immo-unit-lightbox-data" hidden aria-hidden="true">
                        <?php
                        // Hilfsfunktion: erstes nicht-leeres Feld aus mehreren möglichen Keys.
                        if (!function_exists('immo_pick_field')) {
                            function immo_pick_field($arr, $keys) {
                                if (!is_array($arr)) return '';
                                foreach ($keys as $k) {
                                    if (isset($arr[$k]) && $arr[$k] !== '' && $arr[$k] !== null) {
                                        return $arr[$k];
                                    }
                                }
                                return '';
                            }
                        }
                        foreach ($items as $unit) :
                            $unit_property = !empty($unit['property']) ? $unit['property'] : null;

                            // Slug aus möglichst vielen Feldnamen erkennen.
                            $unit_slug = (string) (immo_pick_field($unit_property, array('slug', 'post_name', 'permalink_slug'))
                                ?: immo_pick_field($unit, array('property_slug', 'slug', 'post_name')));

                            // Property-ID ermitteln.
                            $prop_id = (int) (immo_pick_field($unit_property, array('id', 'ID', 'post_id'))
                                ?: immo_pick_field($unit, array('property_id', 'property_ID', 'post_id')));

                            // Vollständige Property nachladen (Cache).
                            $full_property = null;
                            if ($unit_slug) {
                                $full_property = $api->get_property_by_slug($unit_slug);
                            } elseif ($prop_id) {
                                $full_property = $api->get_property($prop_id);
                            }
                            // Slug aus voller Antwort nachreichen.
                            if (!$unit_slug && is_array($full_property)) {
                                $unit_slug = (string) immo_pick_field($full_property, array('slug', 'post_name'));
                            }
                            $fp_meta = (is_array($full_property) && isset($full_property['meta'])) ? $full_property['meta'] : array();

                            $unit_title    = $unit_property && !empty($unit_property['title']) ? $unit_property['title'] : ('Wohneinheit ' . (isset($unit['unit_number']) ? $unit['unit_number'] : ''));
                            $unit_image    = $unit_property && !empty($unit_property['image']) ? $unit_property['image'] : '';
                            if (!$unit_image && $full_property && !empty($full_property['featured_image']['url_large'])) {
                                $unit_image = $full_property['featured_image']['url_large'];
                            }
                            // Einleitungstext: viele mögliche Quellen abklappern.
                            $unit_excerpt = (string) (
                                immo_pick_field($unit_property, array('excerpt', 'short_description', 'summary'))
                                ?: immo_pick_field($unit, array('excerpt', 'short_description', 'summary', 'description'))
                                ?: immo_pick_field($full_property, array('excerpt', 'short_description', 'summary'))
                            );
                            // Fallback: lange Beschreibung gekürzt.
                            if (!$unit_excerpt) {
                                $long = (string) (
                                    immo_pick_field($full_property, array('description', 'content', 'post_content'))
                                    ?: immo_pick_field($unit_property, array('description', 'content'))
                                );
                                if ($long !== '') {
                                    $plain = trim(wp_strip_all_tags($long));
                                    if ($plain !== '') {
                                        $unit_excerpt = wp_trim_words($plain, 35, ' …');
                                    }
                                }
                            }
                            // Letzter Fallback: Projektbeschreibung gekürzt.
                            if (!$unit_excerpt && !empty($project['description'])) {
                                $plain = trim(wp_strip_all_tags((string) $project['description']));
                                if ($plain !== '') {
                                    $unit_excerpt = wp_trim_words($plain, 30, ' …');
                                }
                            }

                            $unit_url      = $unit_slug ? home_url('/immobilie/' . $unit_slug) : '';
                            $unit_id       = isset($unit['id']) ? (int) $unit['id'] : 0;
                            $u_status      = isset($unit['status']) ? $unit['status'] : '';
                            $u_status_lbl  = isset($unit['status_label']) ? $unit['status_label'] : $u_status;

                            // Werte aus Unit oder voller Property fallback.
                            $u_area    = isset($unit['area']) && $unit['area'] !== '' ? $unit['area'] : (isset($fp_meta['area']) ? $fp_meta['area'] : '');
                            $u_rooms   = isset($unit['rooms']) && (int)$unit['rooms'] > 0 ? (int) $unit['rooms'] : (isset($fp_meta['rooms']) ? (int) $fp_meta['rooms'] : 0);
                            $u_bath    = isset($fp_meta['bathrooms']) ? (int) $fp_meta['bathrooms'] : 0;
                            $u_floor   = isset($unit['floor']) && $unit['floor'] !== '' ? $unit['floor'] : (isset($fp_meta['floor']) ? $fp_meta['floor'] : '');
                            $u_built   = isset($fp_meta['built_year']) ? (int) $fp_meta['built_year'] : 0;
                            $u_energy  = isset($fp_meta['energy_class']) ? $fp_meta['energy_class'] : '';
                            $u_price   = isset($unit['price_formatted']) && $unit['price_formatted'] !== '' ? $unit['price_formatted'] : (isset($fp_meta['price_formatted']) ? $fp_meta['price_formatted'] : '');
                            $u_address = isset($fp_meta['address']) ? $fp_meta['address'] : '';
                            $u_city    = isset($fp_meta['city']) ? $fp_meta['city'] : '';
                            $u_plz     = isset($fp_meta['postal_code']) ? $fp_meta['postal_code'] : '';
                            $u_full_addr = trim($u_address . ($u_plz || $u_city ? ', ' . trim($u_plz . ' ' . $u_city) : ''));

                            // Provisionsfrei-Status für Quick-Info aus voller Property holen.
                            $u_cf      = ! empty( $fp_meta['commission_free'] ) || ( $unit_property && ! empty( $unit_property['commission_free'] ) );
                            $u_cf_lbl  = isset( $fp_meta['commission_free_label'] ) ? $fp_meta['commission_free_label']
                                       : ( $unit_property && isset( $unit_property['commission_free_label'] ) ? $unit_property['commission_free_label'] : '' );
                            $u_cf_meta = array(
                                'commission_free'       => $u_cf,
                                'commission_free_label' => $u_cf_lbl,
                                'mode'                  => 'sale',
                            );
                        ?>
                            <div data-unit-id="<?php echo esc_attr($unit_id); ?>">
                                <?php if ($unit_image) : ?>
                                    <div class="immo-unit-quick-hero" style="position:relative;">
                                        <img src="<?php echo esc_url($unit_image); ?>" alt="<?php echo esc_attr($unit_title); ?>">
                                        <?php if ($u_status) : ?>
                                            <span class="immo-status immo-status-<?php echo esc_attr($u_status); ?>"><?php echo esc_html($u_status_lbl); ?></span>
                                        <?php endif; ?>
                                        <?php immo_client_render_cf_badge( $u_cf_meta, 'patch' ); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="immo-unit-quick-body">
                                    <h3 class="immo-unit-quick-title"><?php echo esc_html($unit_title); ?></h3>
                                    <?php if ($u_full_addr) : ?>
                                        <p class="immo-unit-quick-address">📍 <?php echo esc_html($u_full_addr); ?></p>
                                    <?php endif; ?>

                                    <?php if ($unit_excerpt) : ?>
                                        <p class="immo-unit-quick-intro"><?php echo esc_html($unit_excerpt); ?></p>
                                    <?php endif; ?>

                                    <ul class="immo-unit-quick-facts">
                                        <?php if ($u_area !== '') : ?>
                                            <li><span class="ico" aria-hidden="true">📐</span><span class="lab">Wohnfläche</span><strong><?php echo esc_html($u_area); ?> m²</strong></li>
                                        <?php endif; ?>
                                        <?php if ($u_rooms > 0) : ?>
                                            <li><span class="ico" aria-hidden="true">🛏️</span><span class="lab">Zimmer</span><strong><?php echo esc_html($u_rooms); ?></strong></li>
                                        <?php endif; ?>
                                        <?php if ($u_bath > 0) : ?>
                                            <li><span class="ico" aria-hidden="true">🛁</span><span class="lab">Bad</span><strong><?php echo esc_html($u_bath); ?></strong></li>
                                        <?php endif; ?>
                                        <?php if ($u_floor !== '') : ?>
                                            <li><span class="ico" aria-hidden="true">🏢</span><span class="lab">Etage</span><strong><?php echo esc_html($u_floor); ?>. OG</strong></li>
                                        <?php endif; ?>
                                        <?php if ($u_built) : ?>
                                            <li><span class="ico" aria-hidden="true">📅</span><span class="lab">Baujahr</span><strong><?php echo esc_html($u_built); ?></strong></li>
                                        <?php endif; ?>
                                        <?php if ($u_energy) : ?>
                                            <li><span class="ico" aria-hidden="true">⚡</span><span class="lab">Energieklasse</span><strong><?php echo esc_html($u_energy); ?></strong></li>
                                        <?php endif; ?>
                                    </ul>

                                    <?php if ($u_price) : ?>
                                        <div class="immo-unit-quick-price">
                                            <span class="immo-unit-quick-price-label">Preis</span>
                                            <span class="immo-unit-quick-price-value"><?php echo esc_html($u_price); ?></span>
                                        </div>
                                    <?php endif; ?>

                                </div>

                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <?php
            $lage_parts = array_filter(array(
                $address,
                trim($plz . ' ' . $city),
                $dist_label,
                $state_label,
            ));
            $lage_text = implode(', ', $lage_parts);
            ?>
            <?php if ($lage_text) : ?>
                <section class="immo-section immo-project-lage">
                    <h2>Lage</h2>
                    <p class="immo-project-lage-text">
                        <span class="immo-project-lage-icon" aria-hidden="true">📍</span>
                        <?php echo esc_html($lage_text); ?>
                    </p>
                </section>
            <?php endif; ?>

        </main>

        <?php if ($immo_layout !== 'no_sidebar') : ?>
        <aside class="immo-sidebar">
            <div class="immo-sidebar-inner">

                <?php if ($title_in_sidebar) : ?>
                    <header class="immo-title-block immo-title-sidebar">
                        <?php if ($status_label || $status) : ?>
                            <span class="immo-status immo-status-<?php echo esc_attr($status); ?>"><?php echo esc_html($status_label ?: $status); ?></span>
                        <?php endif; ?>
                        <h1><?php echo esc_html($project['title']); ?></h1>
                        <?php if ($location_str || $address) : ?>
                            <p class="immo-location"><?php echo esc_html($location_str); ?><?php if ($address) echo ' · ' . esc_html($address); ?></p>
                        <?php endif; ?>
                    </header>
                <?php endif; ?>

                <?php if ($count_total > 0) : ?>
                    <div class="immo-project-stats">
                        <div class="immo-project-stats-grid" role="group" aria-label="Wohneinheiten nach Status filtern">
                            <button type="button" class="immo-project-stat immo-project-stat-available" data-immo-filter-status="available" aria-pressed="false" title="Klicken, um nur verfügbare Einheiten anzuzeigen">
                                <span class="immo-project-stat-value"><?php echo esc_html($count_available); ?></span>
                                <span class="immo-project-stat-label">Verfügbar</span>
                            </button>
                            <button type="button" class="immo-project-stat immo-project-stat-reserved" data-immo-filter-status="reserved" aria-pressed="false" title="Klicken, um nur reservierte Einheiten anzuzeigen">
                                <span class="immo-project-stat-value"><?php echo esc_html($count_reserved); ?></span>
                                <span class="immo-project-stat-label">Reserviert</span>
                            </button>
                            <button type="button" class="immo-project-stat immo-project-stat-sold" data-immo-filter-status="sold" aria-pressed="false" title="Klicken, um nur verkaufte Einheiten anzuzeigen">
                                <span class="immo-project-stat-value"><?php echo esc_html($count_sold); ?></span>
                                <span class="immo-project-stat-label">Verkauft</span>
                            </button>
                        </div>
                        <?php $standort = trim($city . ($state_label ? ', ' . $state_label : '')); ?>
                        <?php if ($standort) : ?>
                            <div class="immo-project-standort">
                                <span class="immo-project-standort-icon" aria-hidden="true">📍</span>
                                <span class="immo-project-standort-text"><?php echo esc_html($standort); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="immo-inquiry-wrapper">
                    <h3>Anfrage senden</h3>
                    <?php
                    $immo_email = (string) get_option('immo_notify_email', '');
                    include IMMO_CLIENT_PATH . 'templates/project-inquiry-form.php';
                    ?>
                </div>

            </div>
        </aside>
        <?php endif; ?>

    </div>
</article>

<?php /* Lightbox-Container für Wohneinheiten-Quick-Info */ ?>
<div id="immo-unit-lightbox" class="immo-unit-lightbox" role="dialog" aria-modal="true" aria-hidden="true" aria-label="Wohneinheit Quick-Info" data-link-units="<?php echo $immo_link_units ? '1' : '0'; ?>">
    <div class="immo-unit-lightbox-backdrop" data-immo-lightbox-close></div>
    <div class="immo-unit-lightbox-dialog" role="document">
        <button type="button" class="immo-unit-lightbox-close" data-immo-lightbox-close aria-label="Schließen">×</button>
        <div class="immo-unit-lightbox-content"></div>
        <?php if ($immo_link_units) : ?>
            <div class="immo-unit-quick-actions" id="immo-unit-lightbox-actions">
                <a href="#" class="immo-unit-details-btn" id="immo-unit-lightbox-details-btn">Details ansehen →</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
if ($immo_use_wrap) {
    get_footer();
}
