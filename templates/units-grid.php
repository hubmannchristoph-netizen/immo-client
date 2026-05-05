<?php
/**
 * Template: Wohneinheiten als Card-Grid (für [immo_units layout="grid"]).
 *
 * @var array $items      Wohneinheiten.
 * @var array $stats      Statuszählung.
 * @var bool  $show_stats Status-Counter anzeigen.
 *
 * @package ImmoClient
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$status_labels = array(
	'available' => 'Verfügbar',
	'reserved'  => 'Reserviert',
	'sold'      => 'Verkauft',
	'rented'    => 'Vermietet',
);
?>

<?php if ( $show_stats && ! empty( $stats ) ) : ?>
	<div class="immo-units-summary" role="group" aria-label="Wohneinheiten nach Status filtern">
		<?php foreach ( array( 'available', 'reserved', 'sold', 'rented' ) as $st ) :
			if ( empty( $stats[ $st ] ) ) { continue; } ?>
			<button type="button" class="immo-units-stat immo-units-stat-<?php echo esc_attr( $st ); ?>"
				data-immo-filter-status="<?php echo esc_attr( $st ); ?>"
				aria-pressed="false"
				title="Klicken, um nach Status zu filtern">
				<strong><?php echo (int) $stats[ $st ]; ?></strong> <?php echo esc_html( $status_labels[ $st ] ); ?>
			</button>
		<?php endforeach; ?>
		<?php if ( ! empty( $stats['total'] ) ) : ?>
			<span class="immo-units-stat immo-units-stat-total">
				<strong><?php echo (int) $stats['total']; ?></strong> Gesamt
			</span>
		<?php endif; ?>
	</div>
<?php endif; ?>

<div class="immo-units-grid">
	<?php foreach ( $items as $unit ) :
		$prop         = ! empty( $unit['property'] ) ? $unit['property'] : array();
		$unit_number  = isset( $unit['unit_number'] ) ? (string) $unit['unit_number'] : '';
		$unit_label   = $unit_number !== '' ? $unit_number : 'Wohneinheit';
		$prop_title   = $prop && ! empty( $prop['title'] ) ? $prop['title'] : '';
		$prop_slug    = $prop && ! empty( $prop['slug'] ) ? $prop['slug'] : '';
		$detail_url   = $prop_slug ? home_url( '/immobilie/' . $prop_slug ) : '';
		$image_url    = $prop && ! empty( $prop['image'] ) ? $prop['image'] : '';
		$u_status     = isset( $unit['status'] ) ? $unit['status'] : '';
		$u_status_lbl = isset( $unit['status_label'] ) ? $unit['status_label'] : ( $status_labels[ $u_status ] ?? $u_status );
		$u_price      = isset( $unit['price_formatted'] ) ? $unit['price_formatted'] : '';
		$u_area       = isset( $unit['area'] ) ? $unit['area'] : '';
		$u_rooms      = isset( $unit['rooms'] ) ? (int) $unit['rooms'] : 0;
		$unit_id      = isset( $unit['id'] ) ? (int) $unit['id'] : 0;
	?>
		<article class="immo-units-card is-clickable" data-status="<?php echo esc_attr( $u_status ); ?>"
			data-immo-unit-id="<?php echo esc_attr( $unit_id ); ?>"
			data-immo-unit-url="<?php echo esc_attr( $detail_url ); ?>">
			<div class="immo-units-card-image">
				<?php if ( $image_url ) : ?>
					<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $prop_title ?: $unit_label ); ?>" loading="lazy">
				<?php else : ?>
					<div class="immo-units-card-noimage">🏠</div>
				<?php endif; ?>
				<?php if ( $u_status ) : ?>
					<span class="immo-status immo-status-<?php echo esc_attr( $u_status ); ?>"><?php echo esc_html( $u_status_lbl ); ?></span>
				<?php endif; ?>
			</div>
			<div class="immo-units-card-body">
				<span class="immo-units-card-eyebrow"><?php echo esc_html( $unit_label ); ?></span>
				<?php if ( $prop_title ) : ?>
					<h3 class="immo-units-card-title"><?php echo esc_html( $prop_title ); ?></h3>
				<?php endif; ?>
				<?php if ( $u_price ) : ?>
					<p class="immo-units-card-price"><strong><?php echo esc_html( $u_price ); ?></strong></p>
				<?php endif; ?>
				<ul class="immo-units-card-facts">
					<?php if ( $u_area !== '' ) : ?><li>📐 <?php echo esc_html( $u_area ); ?> m²</li><?php endif; ?>
					<?php if ( $u_rooms > 0 ) : ?><li>🛏️ <?php echo esc_html( $u_rooms ); ?> Zi.</li><?php endif; ?>
					<?php $floor_lbl = immo_client_floor_label( $unit['floor'] ?? '' ); if ( $floor_lbl !== '–' ) : ?><li>🏢 <?php echo esc_html( $floor_lbl ); ?></li><?php endif; ?>
				</ul>
				<span class="immo-units-card-cta">Quick-Info anzeigen</span>
			</div>
		</article>
	<?php endforeach; ?>
</div>
