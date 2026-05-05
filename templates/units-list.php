<?php
/**
 * Template: Wohneinheiten als kompakte horizontale Liste (für [immo_units layout="list"]).
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
	<div class="immo-units-summary">
		<?php foreach ( array( 'available', 'reserved', 'sold', 'rented' ) as $st ) :
			if ( empty( $stats[ $st ] ) ) { continue; } ?>
			<span class="immo-units-stat immo-units-stat-<?php echo esc_attr( $st ); ?>">
				<strong><?php echo (int) $stats[ $st ]; ?></strong> <?php echo esc_html( $status_labels[ $st ] ); ?>
			</span>
		<?php endforeach; ?>
		<?php if ( ! empty( $stats['total'] ) ) : ?>
			<span class="immo-units-stat immo-units-stat-total">
				<strong><?php echo (int) $stats['total']; ?></strong> Gesamt
			</span>
		<?php endif; ?>
	</div>
<?php endif; ?>

<ul class="immo-units-flatlist">
	<?php foreach ( $items as $unit ) :
		$prop         = ! empty( $unit['property'] ) ? $unit['property'] : array();
		$unit_number  = isset( $unit['unit_number'] ) ? (string) $unit['unit_number'] : '';
		$unit_label   = $unit_number !== '' ? $unit_number : 'Wohneinheit';
		$prop_title   = $prop && ! empty( $prop['title'] ) ? $prop['title'] : '';
		$display_name = $prop_title !== '' ? $prop_title : ( 'Wohneinheit ' . $unit_number );
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
		<li class="immo-units-listitem is-clickable" data-status="<?php echo esc_attr( $u_status ); ?>"
			data-immo-unit-id="<?php echo esc_attr( $unit_id ); ?>"
			data-immo-unit-url="<?php echo esc_attr( $detail_url ); ?>">
			<?php if ( $image_url ) : ?>
				<div class="immo-units-listitem-thumb">
					<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $display_name ); ?>" loading="lazy">
				</div>
			<?php endif; ?>
			<div class="immo-units-listitem-body">
				<div class="immo-units-listitem-header">
					<span class="immo-units-listitem-nr"><?php echo esc_html( $unit_label ); ?></span>
					<h4 class="immo-units-listitem-title"><?php echo esc_html( $prop_title ?: $unit_label ); ?></h4>
					<?php if ( $u_status ) : ?>
						<span class="immo-status immo-status-<?php echo esc_attr( $u_status ); ?>"><?php echo esc_html( $u_status_lbl ); ?></span>
					<?php endif; ?>
				</div>
				<div class="immo-units-listitem-facts">
					<?php if ( $u_area !== '' ) : ?><span>📐 <?php echo esc_html( $u_area ); ?> m²</span><?php endif; ?>
					<?php if ( $u_rooms > 0 ) : ?><span>🛏️ <?php echo esc_html( $u_rooms ); ?> Zi.</span><?php endif; ?>
					<?php if ( ! empty( $unit['floor'] ) || ( isset( $unit['floor'] ) && $unit['floor'] === '0' ) ) : ?><span>🏢 <?php echo esc_html( $unit['floor'] ); ?>. OG</span><?php endif; ?>
				</div>
			</div>
			<div class="immo-units-listitem-price">
				<?php if ( $u_price ) : ?>
					<strong><?php echo esc_html( $u_price ); ?></strong>
				<?php endif; ?>
				<span class="immo-units-listitem-cta">Quick-Info →</span>
			</div>
		</li>
	<?php endforeach; ?>
</ul>
