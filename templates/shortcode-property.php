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

$has_priced_units = ! empty( $meta['has_priced_units'] )
	|| ! empty( $meta['project']['has_priced_units'] );
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

<?php
// Nebenkosten- & Finanzierungsrechner.
// Mit zugeordneten Units: Dropdown mit verfügbaren Units, günstigste als Default.
// Ohne Units: klassisch Property-Preis.
$prop_price        = (float) ( $meta['price'] ?? 0 );
$calc_units        = array();
$calc_base_price   = 0.0;
$calc_cf           = false;

if ( $has_priced_units && ! empty( $item['slug'] ) ) {
    $sc_api  = new ImmoAPI();
    $sc_rows = $sc_api->get_property_units_by_slug( (string) $item['slug'] );
    if ( ! empty( $sc_rows['units'] ) && is_array( $sc_rows['units'] ) ) {
        foreach ( $sc_rows['units'] as $u ) {
            $up = (float) ( $u['price'] ?? 0 );
            $us = (string) ( $u['status'] ?? '' );
            if ( $up <= 0 || 'available' !== $us ) {
                continue;
            }
            $u_no   = (string) ( $u['unit_number'] ?? '' );
            $u_area = (float)  ( $u['area'] ?? 0 );
            $u_area_disp  = $u_area > 0 ? number_format_i18n( $u_area, 0 ) . ' m²' : '';
            $u_price_disp = ! empty( $u['price_formatted'] ) ? (string) $u['price_formatted'] : ( number_format_i18n( $up, 0 ) . ' €' );
            $label_parts  = array_filter( array(
                '' !== $u_no ? sprintf( __( 'Whg. %s', 'immo-client' ), $u_no ) : '',
                $u_area_disp,
                $u_price_disp,
            ) );
            $calc_units[] = array(
                'id'              => (int) ( $u['id'] ?? 0 ),
                'label'           => implode( ' · ', $label_parts ),
                'price'           => $up,
                'commission_free' => (bool) ( $meta['commission_free'] ?? false ),
            );
        }
        usort( $calc_units, static function ( $a, $b ) { return $a['price'] <=> $b['price']; } );
        if ( ! empty( $calc_units ) ) {
            $calc_base_price = (float) $calc_units[0]['price'];
            $calc_cf         = (bool)  $calc_units[0]['commission_free'];
        }
    }
}

$show_calc = ! empty( $calc_units ) || ( ! $has_priced_units && $prop_price > 0 );
if ( $show_calc ) {
    $calc_context = ! empty( $calc_units )
        ? array(
            'base_price'      => $calc_base_price,
            'commission_free' => $calc_cf,
            'units'           => $calc_units,
        )
        : array(
            'base_price'      => $prop_price,
            'commission_free' => (bool) ( $meta['commission_free'] ?? false ),
            'units'           => array(),
        );
    wp_enqueue_style( 'immo-client-calculator' );
    wp_enqueue_script( 'immo-client-calculator' );
    ?>
    <div class="immo-property-calculator" style="max-width:520px;margin-top:1rem;">
        <?php include IMMO_CLIENT_PATH . 'templates/parts/calculator.php'; ?>
    </div>
    <?php
}
?>
