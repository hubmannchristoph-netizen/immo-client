<?php
/**
 * Gemeinsames Card-Markup für Property- und Project-Listen.
 *
 * Wird inkludiert von:
 *   - templates/list-grid.php
 *   - templates/list-slider.php
 *
 * Erwartet:
 *   $item — Property- oder Project-Response-Objekt aus der Manager-REST-API.
 *
 * Hinweis: Properties und Projects haben unterschiedliche Felder. Project-Cards
 * zeigen weniger Details (kein Preis, keine Spec-Reihe), Property-Cards die volle
 * Information.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!isset($item) || !is_array($item)) {
    return;
}

$meta  = isset($item['meta']) && is_array($item['meta']) ? $item['meta'] : array();
$image = !empty($item['featured_image']) && is_array($item['featured_image']) ? $item['featured_image'] : null;

// Property vs. Project erkennen.
$is_project = isset($meta['project_status']) && !isset($meta['property_type']);

$detail_slug = isset($item['slug']) ? (string) $item['slug'] : '';
$detail_url  = home_url(($is_project ? '/bauprojekt/' : '/immobilie/') . $detail_slug);
$title       = isset($item['title']) ? (string) $item['title'] : '';

// Anzeige-Werte für Properties.
$price_display = isset($meta['price_formatted']) && $meta['price_formatted'] ? (string) $meta['price_formatted'] : '';
$rent_display  = isset($meta['rent_formatted'])  && $meta['rent_formatted']  ? (string) $meta['rent_formatted']  : '';
$has_priced_units = ! empty( $meta['has_priced_units'] )
	|| ! empty( $meta['project']['has_priced_units'] );
if ( $has_priced_units ) {
	// "ab MIN €" wenn der Manager den günstigsten verfügbaren Unit-Preis
	// liefert; sonst Fallback auf den Pricelist-Hinweis.
	$min_price_fmt = isset( $item['unit_stats']['min_price_formatted'] ) ? (string) $item['unit_stats']['min_price_formatted'] : '';
	$min_rent_fmt  = isset( $item['unit_stats']['min_rent_formatted']  ) ? (string) $item['unit_stats']['min_rent_formatted']  : '';
	$mode_card     = isset( $meta['mode'] ) ? (string) $meta['mode'] : 'sale';
	if ( 'rent' === $mode_card && '' !== $min_rent_fmt ) {
		/* translators: %s: günstigster Mietpreis (formatiert) */
		$price_display = sprintf( __( 'ab %s / Monat', 'immo-client' ), $min_rent_fmt );
		$rent_display  = '';
	} elseif ( '' !== $min_price_fmt ) {
		/* translators: %s: günstigster Kaufpreis (formatiert) */
		$price_display = sprintf( __( 'ab %s', 'immo-client' ), $min_price_fmt );
		$rent_display  = '';
	} else {
		$price_display = immo_client_price_or_pricelist( '', true );
		$rent_display  = '';
	}
}

$area_living = isset($meta['area'])        ? (float) $meta['area']        : 0;
$area_usable = isset($meta['usable_area']) ? (float) $meta['usable_area'] : 0;
$area_land   = isset($meta['land_area'])   ? (float) $meta['land_area']   : 0;

// Fallback-Kette für die Card-Anzeige: Wohnfläche → Nutzfläche → Grundstücksfläche.
// Suffix differenziert die Quelle, Wohnfläche bleibt suffix-los (Standardfall).
$area_value  = 0;
$area_suffix = '';
if ($area_living > 0) {
    $area_value  = $area_living;
    $area_suffix = '';
} elseif ($area_usable > 0) {
    $area_value  = $area_usable;
    $area_suffix = __('Nutzfläche', 'immo-client');
} elseif ($area_land > 0) {
    $area_value  = $area_land;
    $area_suffix = __('Grund', 'immo-client');
}

$rooms     = isset($meta['rooms'])     ? (int)   $meta['rooms']     : 0;
$bathrooms = isset($meta['bathrooms']) ? (int)   $meta['bathrooms'] : 0;
$energy    = isset($meta['energy_class']) ? trim((string) $meta['energy_class']) : '';

// Wohneinheiten-Verfügbarkeit (kommt als Top-Level-Feld aus der Manager-REST).
$unit_total = isset($item['unit_stats']['total'])     ? (int) $item['unit_stats']['total']     : 0;
$unit_avail = isset($item['unit_stats']['available']) ? (int) $item['unit_stats']['available'] : 0;

$property_type = isset($meta['property_type']) ? trim((string) $meta['property_type']) : '';
$city          = isset($meta['city'])          ? trim((string) $meta['city'])          : '';
$district      = isset($meta['region_district_label']) ? trim((string) $meta['region_district_label']) : '';
$location      = $city ?: $district;

// Caption oben: Typ · Lage (was vorhanden ist).
$caption_parts = array();
if ($property_type !== '') { $caption_parts[] = $property_type; }
if ($location !== '')      { $caption_parts[] = $location; }
$caption = implode(' · ', $caption_parts);
?>
<article class="immo-card immo-card--listing<?php echo $is_project ? ' immo-card--project' : ''; ?>">
    <a href="<?php echo esc_url($detail_url); ?>" class="immo-card__media" aria-label="<?php echo esc_attr($title); ?>">
        <?php if ($image && !empty($image['url_thumbnail'])) : ?>
            <img src="<?php echo esc_url($image['url_thumbnail']); ?>" alt="<?php echo esc_attr(isset($image['alt']) ? $image['alt'] : $title); ?>" loading="lazy">
        <?php else : ?>
            <span class="immo-card__media-placeholder" aria-hidden="true"></span>
        <?php endif; ?>
        <?php if (!$is_project) { immo_client_render_cf_badge($meta, 'patch'); } ?>
    </a>

    <div class="immo-card__body">
        <?php if (!$is_project && $caption !== '') : ?>
            <p class="immo-card__caption"><?php echo esc_html($caption); ?></p>
        <?php endif; ?>

        <h3 class="immo-card__title">
            <a href="<?php echo esc_url($detail_url); ?>"><?php echo esc_html($title); ?></a>
        </h3>

        <?php if (!$is_project && ($price_display || $rent_display)) :
            $parts = array_filter(array($price_display, $rent_display));
        ?>
            <p class="immo-card__price"><?php echo esc_html(implode(' / ', $parts)); ?></p>
        <?php endif; ?>

        <?php if (!$is_project && ($area_value > 0 || $rooms > 0 || $bathrooms > 0 || $energy !== '' || $unit_total > 0)) : ?>
            <ul class="immo-card__specs">
                <?php if ($area_value > 0) :
                    $area_title = $area_suffix !== '' ? $area_suffix : __('Wohnfläche', 'immo-client');
                ?>
                    <li class="immo-card__spec" title="<?php echo esc_attr($area_title); ?>">
                        <svg class="immo-card__spec-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M3 3h18v18H3V3zm2 2v14h14V5H5zm2 2h2v2H7V7zm0 4h2v2H7v-2zm0 4h2v2H7v-2zm4-8h2v2h-2V7zm0 4h2v2h-2v-2zm0 4h2v2h-2v-2zm4-8h2v2h-2V7zm0 4h2v2h-2v-2zm0 4h2v2h-2v-2z"/></svg>
                        <span><?php
                            /* translators: %s: Flächen-Wert mit m²-Suffix */
                            echo esc_html( sprintf( __('ca. %s', 'immo-client'), number_format_i18n($area_value, 0) ) ) . '&nbsp;m²';
                            if ($area_suffix !== '') {
                                echo ' ' . esc_html($area_suffix);
                            }
                        ?></span>
                    </li>
                <?php endif; ?>
                <?php if ($rooms > 0) : ?>
                    <li class="immo-card__spec" title="<?php esc_attr_e('Zimmer', 'immo-client'); ?>">
                        <svg class="immo-card__spec-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M3 21V10l9-7 9 7v11h-7v-7H10v7H3z"/></svg>
                        <span><?php
                        /* translators: %d: Zimmeranzahl */
                        printf(esc_html(_n('%d Zimmer', '%d Zimmer', $rooms, 'immo-client')), $rooms);
                        ?></span>
                    </li>
                <?php endif; ?>
                <?php if ($bathrooms > 0) : ?>
                    <li class="immo-card__spec" title="<?php esc_attr_e('Badezimmer', 'immo-client'); ?>">
                        <svg class="immo-card__spec-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M7 7a3 3 0 0 1 6 0v3h7v2h-1v3a4 4 0 0 1-2.5 3.71L17 21h-2l-.5-2H9.5L9 21H7l.5-2.29A4 4 0 0 1 5 15v-3H4v-2h3V7zm2 0v3h2V7a1 1 0 1 0-2 0z"/></svg>
                        <span><?php
                        /* translators: %d: Anzahl Badezimmer */
                        printf(esc_html(_n('%d Bad', '%d Bäder', $bathrooms, 'immo-client')), $bathrooms);
                        ?></span>
                    </li>
                <?php endif; ?>
                <?php if ($energy !== '') : ?>
                    <li class="immo-card__spec" title="<?php esc_attr_e('Energieklasse', 'immo-client'); ?>">
                        <svg class="immo-card__spec-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M11 21h-1l1-7H6l8-13h1l-1 7h5l-8 13z"/></svg>
                        <span><?php echo esc_html($energy); ?></span>
                    </li>
                <?php endif; ?>
                <?php if ($unit_total > 0) : ?>
                    <li class="immo-card__spec" title="<?php esc_attr_e('Wohneinheiten', 'immo-client'); ?>">
                        <svg class="immo-card__spec-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M3 21V3h10v8h8v10H3zm2-2h6V5H5v14zm8 0h6v-6h-6v6zM7 7h2v2H7V7zm0 4h2v2H7v-2zm0 4h2v2H7v-2z"/></svg>
                        <span><?php
                        if ($unit_avail > 0) {
                            /* translators: 1: Anzahl verfügbarer Wohneinheiten, 2: Gesamtanzahl */
                            echo esc_html(sprintf(__('%1$d von %2$d verfügbar', 'immo-client'), $unit_avail, $unit_total));
                        } else {
                            /* translators: %d: Gesamtanzahl der Wohneinheiten */
                            echo esc_html(sprintf(_n('%d Wohneinheit – ausverkauft', '%d Wohneinheiten – ausverkauft', $unit_total, 'immo-client'), $unit_total));
                        }
                        ?></span>
                    </li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>

        <a href="<?php echo esc_url($detail_url); ?>" class="immo-card__button">
            <?php esc_html_e('Details ansehen', 'immo-client'); ?>
        </a>
    </div>
</article>
