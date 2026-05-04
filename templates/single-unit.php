<?php
/**
 * Detailseite einer Immobilie unter /immobilie/{slug}.
 * Layout: Galerie + Hauptbereich links (2/3), Sidebar rechts (1/3) mit Preis,
 * Keyfacts, Kontakt und Anfrageformular.
 */

if (!defined('ABSPATH')) {
    exit;
}

$immo_layout    = (string) get_option('immo_detail_layout', 'right_sidebar');
$immo_max_width = (int)    get_option('immo_detail_max_width', 1200);
$immo_use_wrap  = (string) get_option('immo_detail_use_theme_wrapper', '1') === '1';

if ($immo_use_wrap) {
    get_header();
}

$property_slug = get_query_var('immo_unit_slug');
$api           = new ImmoAPI();
$property      = $api->get_property_by_slug($property_slug);

if (!$property) :
    ?>
    <div class="immo-container immo-detail-notfound">
        <h1>Immobilie nicht gefunden</h1>
        <p>Das gewünschte Objekt ist nicht mehr verfügbar.</p>
    </div>
    <?php
    if ($immo_use_wrap) {
        get_footer();
    }
    return;
endif;

$meta    = isset($property['meta']) ? $property['meta'] : array();
$gallery = isset($property['gallery']) && is_array($property['gallery']) ? $property['gallery'] : array();
$hero    = isset($property['featured_image']) ? $property['featured_image'] : null;

$slides = array();
if ($hero && !empty($hero['url'])) {
    $slides[] = $hero;
}
foreach ($gallery as $g) {
    if (!empty($g['url'])) {
        $slides[] = $g;
    }
}

$status         = isset($meta['status']) ? $meta['status'] : '';
$status_labels  = array(
    'available' => 'Verfügbar',
    'reserved'  => 'Reserviert',
    'sold'      => 'Verkauft',
    'rented'    => 'Vermietet',
);
$status_label   = isset($status_labels[$status]) ? $status_labels[$status] : $status;

$mode_labels = array('sale' => 'Kaufpreis', 'rent' => 'Miete', 'both' => 'Kauf / Miete');
$mode        = isset($meta['mode']) ? $meta['mode'] : '';
$mode_label  = isset($mode_labels[$mode]) ? $mode_labels[$mode] : '';

$price_display = !empty($meta['price_formatted']) ? $meta['price_formatted'] : '';
$rent_display  = !empty($meta['rent_formatted'])  ? $meta['rent_formatted']  : '';

$area        = (float) ($meta['area']        ?? 0);
$usable_area = (float) ($meta['usable_area'] ?? 0);
$land_area   = (float) ($meta['land_area']   ?? 0);
$rooms       = (int)   ($meta['rooms']       ?? 0);
$bedrooms    = (int)   ($meta['bedrooms']    ?? 0);
$bathrooms   = (int)   ($meta['bathrooms']   ?? 0);
$floor_no    = (int)   ($meta['floor']       ?? 0);
$total_fl    = (int)   ($meta['total_floors']?? 0);
$built       = (int)   ($meta['built_year']  ?? 0);
$reno        = (int)   ($meta['renovation_year'] ?? 0);
$en_class    = (string)($meta['energy_class'] ?? '');
$en_hwb      = (float) ($meta['energy_hwb']   ?? 0);
$heating     = (string)($meta['heating']      ?? '');
$op_costs    = (float) ($meta['operating_costs'] ?? 0);
$deposit     = (float) ($meta['deposit']      ?? 0);
$commission  = (string)($meta['commission']   ?? '');
$comm_free   = !empty($meta['commission_free']);
$avail_from  = (string)($meta['available_from'] ?? '');
$ptype       = (string)($meta['property_type']  ?? '');
$address     = (string)($meta['address']        ?? '');
$plz         = (string)($meta['postal_code']    ?? '');
$city        = (string)($meta['city']           ?? '');
$state_label = (string)($meta['region_state_label']    ?? '');
$dist_label  = (string)($meta['region_district_label'] ?? '');
$lat         = (float) ($meta['lat']            ?? 0);
$lng         = (float) ($meta['lng']            ?? 0);
$features    = isset($meta['features_detail']) && is_array($meta['features_detail']) ? $meta['features_detail'] : array();
$custom_feat = (string) ($meta['custom_features'] ?? '');
$documents   = isset($meta['documents']) && is_array($meta['documents']) ? $meta['documents'] : array();

$contact_name  = (string) ($meta['contact_name']  ?? '');
$contact_email = (string) ($meta['contact_email'] ?? '');
$contact_phone = (string) ($meta['contact_phone'] ?? '');
$contact_image = isset($meta['contact_image']) && !empty($meta['contact_image']['url_thumbnail']) ? $meta['contact_image'] : null;

$video_url   = (string) ($meta['video_url']      ?? '');
$video_file  = (string) ($meta['video_file_url'] ?? '');

$project = isset($meta['project']) && is_array($meta['project']) ? $meta['project'] : null;

// Features nach Kategorie gruppieren.
$features_grouped = array();
foreach ($features as $f) {
    $cat_key   = !empty($f['category'])       ? $f['category']       : 'sonstiges';
    $cat_label = !empty($f['category_label']) ? $f['category_label'] : 'Sonstiges';
    if (!isset($features_grouped[$cat_key])) {
        $features_grouped[$cat_key] = array('label' => $cat_label, 'items' => array());
    }
    $features_grouped[$cat_key]['items'][] = $f;
}

$location_str = trim(implode(' ', array_filter(array($plz, $city))));
if ($state_label) $location_str .= ($location_str ? ', ' : '') . $state_label;

$detail_rows_basis = array_filter(array(
    $ptype ? array('Typ', $ptype) : null,
    $rooms ? array('Zimmer', $rooms) : null,
    $bedrooms ? array('Schlafzimmer', $bedrooms) : null,
    $bathrooms ? array('Badezimmer', $bathrooms) : null,
    $floor_no ? array('Etage', $floor_no . ($total_fl ? ' von ' . $total_fl : '')) : null,
    $built ? array('Baujahr', $built) : null,
    $reno  ? array('Renoviert', $reno)  : null,
    $avail_from ? array('Verfügbar ab', $avail_from) : null,
));

$detail_rows_flaeche = array_filter(array(
    $area        ? array('Wohnfläche',     number_format_i18n($area, 0) . ' m²')        : null,
    $usable_area ? array('Nutzfläche',     number_format_i18n($usable_area, 0) . ' m²') : null,
    $land_area   ? array('Grundstück',     number_format_i18n($land_area, 0) . ' m²')   : null,
));

$detail_rows_energie = array_filter(array(
    $en_class ? array('Energieklasse', $en_class) : null,
    $en_hwb   ? array('HWB', number_format_i18n($en_hwb, 1) . ' kWh/m²a') : null,
    $heating  ? array('Heizung', $heating) : null,
));

$detail_rows_kosten = array_filter(array(
    $price_display ? array('Kaufpreis', $price_display) : null,
    $rent_display  ? array('Miete',     $rent_display)  : null,
    $op_costs ? array('Betriebskosten', number_format_i18n($op_costs, 2) . ' €') : null,
    $deposit  ? array('Kaution',         number_format_i18n($deposit, 2) . ' €') : null,
    $comm_free ? array('Provision', 'Provisionsfrei') : ($commission ? array('Provision', $commission) : null),
));

$primary_amount = $price_display ?: $rent_display;

$layout_class = 'immo-layout-' . sanitize_key($immo_layout);
$style_attr   = $immo_max_width ? ' style="max-width:' . esc_attr($immo_max_width) . 'px;"' : '';
?>

<article id="immo-property-<?php echo esc_attr($property['id']); ?>" class="immo-detail <?php echo esc_attr($layout_class); ?>"<?php echo $style_attr; ?>>

    <?php $title_in_sidebar = in_array($immo_layout, array('right_sidebar', 'left_sidebar'), true); ?>

    <div class="immo-detail-grid">

        <main class="immo-main">

            <?php if (!$title_in_sidebar) : ?>
                <header class="immo-title-block">
                    <?php if ($status) : ?>
                        <span class="immo-status immo-status-<?php echo esc_attr($status); ?>"><?php echo esc_html($status_label); ?></span>
                    <?php endif; ?>
                    <h1><?php echo esc_html($property['title']); ?></h1>
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
                                     alt="<?php echo esc_attr(!empty($img['alt']) ? $img['alt'] : $property['title']); ?>"
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

            <?php if (!empty($property['description'])) : ?>
                <section class="immo-section immo-description">
                    <h2>Beschreibung</h2>
                    <?php echo wp_kses_post($property['description']); ?>
                </section>
            <?php endif; ?>

            <?php if ($custom_feat) : ?>
                <section class="immo-section immo-highlights">
                    <h2>Highlights</h2>
                    <div class="immo-highlights-text"><?php echo wp_kses_post(wpautop($custom_feat)); ?></div>
                </section>
            <?php endif; ?>

            <?php if (!empty($features_grouped)) : ?>
                <section class="immo-section immo-feature-groups">
                    <h2>Ausstattung</h2>
                    <?php $first = true; foreach ($features_grouped as $cat_key => $group) : ?>
                        <details class="immo-accordion" <?php echo $first ? 'open' : ''; ?>>
                            <summary>
                                <span class="immo-accordion-title"><?php echo esc_html($group['label']); ?></span>
                                <span class="immo-accordion-count"><?php echo count($group['items']); ?></span>
                            </summary>
                            <ul class="immo-feature-list">
                                <?php foreach ($group['items'] as $f) : ?>
                                    <li>
                                        <?php if (!empty($f['icon'])) : ?>
                                            <span class="immo-feature-icon" aria-hidden="true"><?php echo esc_html($f['icon']); ?></span>
                                        <?php endif; ?>
                                        <span><?php echo esc_html($f['label']); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php $first = false; endforeach; ?>
                </section>
            <?php endif; ?>

            <section class="immo-section immo-detail-blocks">
                <h2>Details</h2>

                <?php if (!empty($detail_rows_basis)) : ?>
                    <details class="immo-accordion" open>
                        <summary><span class="immo-accordion-title">Basisdaten</span></summary>
                        <table class="immo-data-table">
                            <tbody>
                                <?php foreach ($detail_rows_basis as $row) : ?>
                                    <tr><th><?php echo esc_html($row[0]); ?></th><td><?php echo esc_html($row[1]); ?></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </details>
                <?php endif; ?>

                <?php if (!empty($detail_rows_flaeche)) : ?>
                    <details class="immo-accordion">
                        <summary><span class="immo-accordion-title">Flächen</span></summary>
                        <table class="immo-data-table">
                            <tbody>
                                <?php foreach ($detail_rows_flaeche as $row) : ?>
                                    <tr><th><?php echo esc_html($row[0]); ?></th><td><?php echo esc_html($row[1]); ?></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </details>
                <?php endif; ?>

                <?php if (!empty($detail_rows_energie)) : ?>
                    <details class="immo-accordion">
                        <summary><span class="immo-accordion-title">Energie &amp; Technik</span></summary>
                        <table class="immo-data-table">
                            <tbody>
                                <?php foreach ($detail_rows_energie as $row) : ?>
                                    <tr><th><?php echo esc_html($row[0]); ?></th><td><?php echo esc_html($row[1]); ?></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </details>
                <?php endif; ?>

                <?php if (!empty($detail_rows_kosten)) : ?>
                    <details class="immo-accordion">
                        <summary><span class="immo-accordion-title">Kosten</span></summary>
                        <table class="immo-data-table">
                            <tbody>
                                <?php foreach ($detail_rows_kosten as $row) : ?>
                                    <tr><th><?php echo esc_html($row[0]); ?></th><td><?php echo esc_html($row[1]); ?></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </details>
                <?php endif; ?>

                <?php if ($address || $city || $state_label || $dist_label) : ?>
                    <details class="immo-accordion">
                        <summary><span class="immo-accordion-title">Lage</span></summary>
                        <table class="immo-data-table">
                            <tbody>
                                <?php if ($address) : ?><tr><th>Adresse</th><td><?php echo esc_html($address); ?></td></tr><?php endif; ?>
                                <?php if ($plz || $city) : ?><tr><th>Ort</th><td><?php echo esc_html(trim($plz . ' ' . $city)); ?></td></tr><?php endif; ?>
                                <?php if ($dist_label) : ?><tr><th>Bezirk</th><td><?php echo esc_html($dist_label); ?></td></tr><?php endif; ?>
                                <?php if ($state_label) : ?><tr><th>Bundesland</th><td><?php echo esc_html($state_label); ?></td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </details>
                <?php endif; ?>
            </section>

            <?php if ($video_url || $video_file) : ?>
                <section class="immo-section immo-video">
                    <h2>Video</h2>
                    <?php if ($video_file) : ?>
                        <video controls preload="metadata" style="max-width:100%;border-radius:8px;">
                            <source src="<?php echo esc_url($video_file); ?>">
                        </video>
                    <?php else : ?>
                        <p><a href="<?php echo esc_url($video_url); ?>" target="_blank" rel="noopener">Video ansehen</a></p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if (!empty($documents)) : ?>
                <section class="immo-section immo-documents">
                    <h2>Dokumente</h2>
                    <ul class="immo-doc-list">
                        <?php foreach ($documents as $doc) : ?>
                            <?php if (!empty($doc['url'])) : ?>
                                <li>
                                    <a href="<?php echo esc_url($doc['url']); ?>" target="_blank" rel="noopener">
                                        <?php echo esc_html(!empty($doc['title']) ? $doc['title'] : 'Dokument'); ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php if ($project && !empty($project['title'])) : ?>
                <section class="immo-section immo-project-link">
                    <h2><?php echo esc_html($project['title']); ?></h2>
                    <a href="<?php echo esc_url(home_url('/bauprojekt/' . (!empty($project['slug']) ? $project['slug'] : ''))); ?>" class="immo-project-card-link">
                        <?php if (!empty($project['image'])) : ?>
                            <img src="<?php echo esc_url($project['image']); ?>" alt="">
                        <?php endif; ?>
                        <div>
                            <strong><?php echo esc_html($project['title']); ?></strong>
                            <?php if (isset($project['available_units'], $project['total_units'])) : ?>
                                <span><?php echo esc_html(sprintf('%d von %d Einheiten verfügbar', (int) $project['available_units'], (int) $project['total_units'])); ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                </section>
            <?php endif; ?>

        </main>

        <?php if ($immo_layout !== 'no_sidebar') : ?>
        <aside class="immo-sidebar">
            <div class="immo-sidebar-inner">

                <?php if ($title_in_sidebar) : ?>
                    <header class="immo-title-block immo-title-sidebar">
                        <?php if ($status) : ?>
                            <span class="immo-status immo-status-<?php echo esc_attr($status); ?>"><?php echo esc_html($status_label); ?></span>
                        <?php endif; ?>
                        <h1><?php echo esc_html($property['title']); ?></h1>
                        <?php if ($location_str || $address) : ?>
                            <p class="immo-location"><?php echo esc_html($location_str); ?><?php if ($address) echo ' · ' . esc_html($address); ?></p>
                        <?php endif; ?>
                    </header>
                <?php endif; ?>

                <?php if ($primary_amount) : ?>
                    <div class="immo-price-box">
                        <span class="immo-price-label"><?php echo esc_html($mode_label ?: 'Preis'); ?></span>
                        <span class="immo-price-value"><?php echo esc_html($primary_amount); ?></span>
                        <?php if ($price_display && $rent_display) : ?>
                            <span class="immo-price-extra">Miete: <?php echo esc_html($rent_display); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="immo-sidebar-keyfacts">
                    <?php if ($area) : ?>
                        <div class="kf"><span class="kf-label">Wohnfläche</span><span class="kf-value"><?php echo esc_html(number_format_i18n($area, 0)); ?> m²</span></div>
                    <?php endif; ?>
                    <?php if ($rooms) : ?>
                        <div class="kf"><span class="kf-label">Zimmer</span><span class="kf-value"><?php echo esc_html($rooms); ?></span></div>
                    <?php endif; ?>
                    <?php if ($bathrooms) : ?>
                        <div class="kf"><span class="kf-label">Bad</span><span class="kf-value"><?php echo esc_html($bathrooms); ?></span></div>
                    <?php endif; ?>
                    <?php if ($built) : ?>
                        <div class="kf"><span class="kf-label">Baujahr</span><span class="kf-value"><?php echo esc_html($built); ?></span></div>
                    <?php endif; ?>
                    <?php if ($en_class) : ?>
                        <div class="kf"><span class="kf-label">Energieklasse</span><span class="kf-value"><?php echo esc_html($en_class); ?></span></div>
                    <?php endif; ?>
                    <?php if ($ptype) : ?>
                        <div class="kf"><span class="kf-label">Typ</span><span class="kf-value"><?php echo esc_html($ptype); ?></span></div>
                    <?php endif; ?>
                </div>

                <?php if ($contact_name || $contact_email || $contact_phone) : ?>
                    <div class="immo-contact-card">
                        <?php if ($contact_image) : ?>
                            <img src="<?php echo esc_url($contact_image['url_thumbnail']); ?>" alt="<?php echo esc_attr($contact_name); ?>" class="immo-contact-photo">
                        <?php endif; ?>
                        <div class="immo-contact-meta">
                            <?php if ($contact_name) : ?>
                                <strong><?php echo esc_html($contact_name); ?></strong>
                            <?php endif; ?>
                            <?php if ($contact_phone) : ?>
                                <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $contact_phone)); ?>" class="immo-contact-link">📞 <?php echo esc_html($contact_phone); ?></a>
                            <?php endif; ?>
                            <?php if ($contact_email) : ?>
                                <a href="mailto:<?php echo esc_attr($contact_email); ?>" class="immo-contact-link">✉ <?php echo esc_html($contact_email); ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="immo-inquiry-wrapper">
                    <h3>Anfrage senden</h3>
                    <?php
                    $immo_email = (string) get_option('immo_notify_email', '');
                    include IMMO_CLIENT_PATH . 'templates/inquiry-form.php';
                    ?>
                </div>

            </div>
        </aside>
        <?php endif; ?>

    </div>
</article>

<?php
if ($immo_use_wrap) {
    get_footer();
}
