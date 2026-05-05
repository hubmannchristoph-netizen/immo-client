<?php
/**
 * Template-Part: Quick-Info-Datenpool für die Wohneinheiten-Lightbox.
 *
 * Wird von [immo_units] mit ausgegeben. Das JavaScript (immo-project.js)
 * sucht über die Klasse .immo-unit-lightbox-data nach allen Pools auf der
 * Seite und holt das passende Unit-HTML per data-unit-id.
 *
 * Erwartete Variablen:
 *
 * @var array     $items    Wohneinheiten (REST-Format).
 * @var ImmoAPI   $api      API-Instance für Property-Nachladen.
 *
 * @package ImmoClient
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="immo-unit-lightbox-data" hidden aria-hidden="true">
	<?php foreach ( $items as $unit ) :
		$prop          = ! empty( $unit['property'] ) ? $unit['property'] : array();
		$prop_slug     = ! empty( $prop['slug'] ) ? (string) $prop['slug'] : '';
		$prop_id       = ! empty( $prop['id'] ) ? (int) $prop['id'] : 0;

		// Volle Property nur dann nachladen, wenn Slug oder ID vorhanden.
		$full = null;
		if ( $prop_slug ) {
			$full = $api->get_property_by_slug( $prop_slug );
		} elseif ( $prop_id ) {
			$full = $api->get_property( $prop_id );
		}
		$fp_meta = ( is_array( $full ) && isset( $full['meta'] ) ) ? $full['meta'] : array();

		$unit_id      = isset( $unit['id'] ) ? (int) $unit['id'] : 0;
		$unit_number  = isset( $unit['unit_number'] ) ? (string) $unit['unit_number'] : '';
		$unit_title   = ! empty( $prop['title'] ) ? $prop['title'] : ( 'Wohneinheit ' . $unit_number );
		$unit_image   = '';
		if ( ! empty( $prop['image'] ) ) {
			$unit_image = $prop['image'];
		} elseif ( $full && ! empty( $full['featured_image']['url_large'] ) ) {
			$unit_image = $full['featured_image']['url_large'];
		}

		$u_status     = isset( $unit['status'] ) ? $unit['status'] : '';
		$u_status_lbl = isset( $unit['status_label'] ) ? $unit['status_label'] : $u_status;

		$u_area    = isset( $unit['area'] ) && $unit['area'] !== '' ? $unit['area'] : ( isset( $fp_meta['area'] ) ? $fp_meta['area'] : '' );
		$u_rooms   = isset( $unit['rooms'] ) && (int) $unit['rooms'] > 0 ? (int) $unit['rooms'] : ( isset( $fp_meta['rooms'] ) ? (int) $fp_meta['rooms'] : 0 );
		$u_bath    = isset( $fp_meta['bathrooms'] ) ? (int) $fp_meta['bathrooms'] : 0;
		$u_floor   = isset( $unit['floor'] ) && $unit['floor'] !== '' ? $unit['floor'] : ( isset( $fp_meta['floor'] ) ? $fp_meta['floor'] : '' );
		$u_built   = isset( $fp_meta['built_year'] ) ? (int) $fp_meta['built_year'] : 0;
		$u_energy  = isset( $fp_meta['energy_class'] ) ? $fp_meta['energy_class'] : '';
		$u_price   = isset( $unit['price_formatted'] ) && $unit['price_formatted'] !== ''
			? $unit['price_formatted']
			: ( isset( $fp_meta['price_formatted'] ) ? $fp_meta['price_formatted'] : '' );

		$u_address = isset( $fp_meta['address'] ) ? $fp_meta['address'] : '';
		$u_city    = isset( $fp_meta['city'] ) ? $fp_meta['city'] : '';
		$u_plz     = isset( $fp_meta['postal_code'] ) ? $fp_meta['postal_code'] : '';
		$u_full_addr = trim( $u_address . ( $u_plz || $u_city ? ', ' . trim( $u_plz . ' ' . $u_city ) : '' ) );

		// Excerpt aus diversen Quellen.
		$u_excerpt = '';
		if ( $full && ! empty( $full['description'] ) ) {
			$plain     = trim( wp_strip_all_tags( (string) $full['description'] ) );
			$u_excerpt = $plain !== '' ? wp_trim_words( $plain, 35, ' …' ) : '';
		}
	?>
		<div data-unit-id="<?php echo esc_attr( $unit_id ); ?>">
			<?php if ( $unit_image ) : ?>
				<div class="immo-unit-quick-hero">
					<img src="<?php echo esc_url( $unit_image ); ?>" alt="<?php echo esc_attr( $unit_title ); ?>">
					<?php if ( $u_status ) : ?>
						<span class="immo-status immo-status-<?php echo esc_attr( $u_status ); ?>"><?php echo esc_html( $u_status_lbl ); ?></span>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="immo-unit-quick-body">
				<?php if ( $unit_number ) : ?>
					<span class="immo-unit-quick-eyebrow"><?php echo esc_html( $unit_number ); ?></span>
				<?php endif; ?>
				<h3 class="immo-unit-quick-title"><?php echo esc_html( $unit_title ); ?></h3>
				<?php if ( $u_full_addr ) : ?>
					<p class="immo-unit-quick-address">📍 <?php echo esc_html( $u_full_addr ); ?></p>
				<?php endif; ?>

				<?php if ( $u_excerpt ) : ?>
					<p class="immo-unit-quick-intro"><?php echo esc_html( $u_excerpt ); ?></p>
				<?php endif; ?>

				<ul class="immo-unit-quick-facts">
					<?php if ( $u_area !== '' ) : ?>
						<li><span class="ico" aria-hidden="true">📐</span><span class="lab">Wohnfläche</span><strong><?php echo esc_html( $u_area ); ?> m²</strong></li>
					<?php endif; ?>
					<?php if ( $u_rooms > 0 ) : ?>
						<li><span class="ico" aria-hidden="true">🛏️</span><span class="lab">Zimmer</span><strong><?php echo esc_html( $u_rooms ); ?></strong></li>
					<?php endif; ?>
					<?php if ( $u_bath > 0 ) : ?>
						<li><span class="ico" aria-hidden="true">🛁</span><span class="lab">Bad</span><strong><?php echo esc_html( $u_bath ); ?></strong></li>
					<?php endif; ?>
					<?php if ( $u_floor !== '' ) : ?>
						<li><span class="ico" aria-hidden="true">🏢</span><span class="lab">Etage</span><strong><?php echo esc_html( $u_floor ); ?>. OG</strong></li>
					<?php endif; ?>
					<?php if ( $u_built ) : ?>
						<li><span class="ico" aria-hidden="true">📅</span><span class="lab">Baujahr</span><strong><?php echo esc_html( $u_built ); ?></strong></li>
					<?php endif; ?>
					<?php if ( $u_energy ) : ?>
						<li><span class="ico" aria-hidden="true">⚡</span><span class="lab">Energieklasse</span><strong><?php echo esc_html( $u_energy ); ?></strong></li>
					<?php endif; ?>
				</ul>

				<?php if ( $u_price ) : ?>
					<div class="immo-unit-quick-price">
						<span class="immo-unit-quick-price-label">Preis</span>
						<span class="immo-unit-quick-price-value"><?php echo esc_html( $u_price ); ?></span>
					</div>
				<?php endif; ?>
			</div>
		</div>
	<?php endforeach; ?>
</div>
