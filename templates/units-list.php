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
		$cf_meta      = array(
			'commission_free'       => ! empty( $prop['commission_free'] ),
			'commission_free_label' => isset( $prop['commission_free_label'] ) ? $prop['commission_free_label'] : '',
			'mode'                  => 'sale',
		);
	?>
		<li class="immo-units-listitem" data-status="<?php echo esc_attr( $u_status ); ?>">
			<?php if ( $image_url ) : ?>
				<div class="immo-units-listitem-thumb" style="position:relative;">
					<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $display_name ); ?>" loading="lazy">
					<?php immo_client_render_cf_badge( $cf_meta, 'patch' ); ?>
				</div>
			<?php endif; ?>
			<div class="immo-units-listitem-body">
				<div class="immo-units-listitem-header">
					<span class="immo-units-listitem-nr"><?php echo esc_html( $unit_label ); ?></span>
					<h4 class="immo-units-listitem-title">
						<?php if ( $detail_url && $prop_title ) : ?>
							<a href="<?php echo esc_url( $detail_url ); ?>"><?php echo esc_html( $prop_title ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $prop_title ?: $unit_label ); ?>
						<?php endif; ?>
					</h4>
					<?php if ( $u_status ) : ?>
						<span class="immo-status immo-status-<?php echo esc_attr( $u_status ); ?>"><?php echo esc_html( $u_status_lbl ); ?></span>
					<?php endif; ?>
				</div>
				<div class="immo-units-listitem-facts">
					<?php if ( $u_area !== '' ) : ?><span>📐 <?php echo esc_html( $u_area ); ?> m²</span><?php endif; ?>
					<?php if ( $u_rooms > 0 ) : ?><span>🛏️ <?php echo esc_html( $u_rooms ); ?> Zi.</span><?php endif; ?>
					<?php if ( ! empty( $unit['floor'] ) || ( isset( $unit['floor'] ) && $unit['floor'] === '0' ) ) : ?><span>🏢 <?php echo esc_html( $unit['floor'] ); ?>. OG</span><?php endif; ?>
					<?php if ( ! empty( $prop['commission_free'] ) ) {
						immo_client_render_cf_badge( $cf_meta, 'icon' );
					} ?>
				</div>
			</div>
			<div class="immo-units-listitem-price">
				<?php if ( $u_price ) : ?>
					<strong><?php echo esc_html( $u_price ); ?></strong>
				<?php endif; ?>
				<?php if ( $detail_url ) : ?>
					<a href="<?php echo esc_url( $detail_url ); ?>" class="immo-units-listitem-link">Details ansehen →</a>
				<?php endif; ?>
			</div>
		</li>
	<?php endforeach; ?>
</ul>
