<?php
/**
 * Shortcode-Ausgabe für eine einzelne Immobilie: [immo_property id="…"].
 *
 * Erwartet: $item = Property-Response der ImmoManager-API.
 */

if (!defined('ABSPATH')) {
    exit;
}

$meta  = isset($item['meta']) ? $item['meta'] : array();
$hero  = isset($item['featured_image']) ? $item['featured_image'] : null;

$price_display = isset($meta['price_formatted']) && $meta['price_formatted'] ? $meta['price_formatted'] : '';
$rent_display  = isset($meta['rent_formatted'])  && $meta['rent_formatted']  ? $meta['rent_formatted']  : '';

$has_priced_units = ! empty( $meta['project']['has_priced_units'] );
if ( $has_priced_units ) {
	$price_display = immo_client_price_or_pricelist( '', true );
	$rent_display  = '';
}

$area          = isset($meta['area'])  ? (float) $meta['area']  : 0;
$rooms         = isset($meta['rooms']) ? (int)   $meta['rooms'] : 0;

$detail_url = home_url('/immobilie/' . (isset($item['slug']) ? $item['slug'] : ''));
?>
<div class="immo-card immo-card-shortcode" style="border:1px solid #ddd;border-radius:8px;overflow:hidden;background:#fff;max-width:520px;">
    <?php if ($hero && !empty($hero['url_medium'])) : ?>
        <div class="immo-card-image" style="position:relative;">
            <img src="<?php echo esc_url($hero['url_medium']); ?>" alt="<?php echo esc_attr($hero['alt'] ?: $item['title']); ?>" style="width:100%;height:260px;object-fit:cover;">
            <?php immo_client_render_cf_badge( $meta, 'patch' ); ?>
        </div>
    <?php elseif ( ! empty( $meta['commission_free'] ) ) : ?>
        <div class="immo-card-image" style="position:relative;min-height:40px;padding:8px;">
            <?php immo_client_render_cf_badge( $meta, 'patch' ); ?>
        </div>
    <?php endif; ?>
    <div class="immo-card-content" style="padding:18px;">
        <h3 style="margin:0 0 8px;"><?php echo esc_html($item['title']); ?></h3>
        <?php if ($price_display || $rent_display) :
            $parts = array_filter(array($price_display, $rent_display)); ?>
            <p class="price" style="font-weight:bold;margin:0 0 8px;"><?php echo esc_html(implode(' / ', $parts)); ?></p>
        <?php endif; ?>
        <p class="meta" style="color:#666;font-size:.9em;margin:0 0 12px;">
            <?php if ($area > 0) echo esc_html(number_format_i18n($area, 0)) . ' m²'; ?>
            <?php if ($area > 0 && $rooms > 0) echo ' | '; ?>
            <?php if ($rooms > 0) echo esc_html($rooms) . ' Zimmer'; ?>
        </p>
        <?php
        $pk_unit    = $meta['unit']['parking'] ?? $meta['parking'] ?? array();
        $pk_garage  = (int) ( $pk_unit['garage_count'] ?? 0 );
        $pk_outdoor = (int) ( $pk_unit['outdoor_count'] ?? 0 );
        if ( $pk_garage > 0 || $pk_outdoor > 0 ) : ?>
            <p class="immo-card-parking" style="margin:0 0 8px; font-size:.9em; color:#555;">
                <?php if ( $pk_garage > 0 ) : ?>
                    🅿️ ×<?php echo $pk_garage; ?>
                <?php endif; ?>
                <?php if ( $pk_outdoor > 0 ) : ?>
                    🚗 ×<?php echo $pk_outdoor; ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <a href="<?php echo esc_url($detail_url); ?>" class="button" style="display:inline-block;padding:8px 16px;background:var(--immo-primary,#0073aa);color:#fff;text-decoration:none;border-radius:4px;">
            Details ansehen
        </a>
    </div>
</div>
