<?php
/**
 * Template: Wohneinheiten als Tabelle (für [immo_units layout="table"]).
 *
 * Erwartete Variablen aus dem Shortcode-Renderer:
 *
 * @var array  $items      Wohneinheiten (Format wie REST `units`).
 * @var array  $stats      Statuszählung (available, reserved, sold, rented, total).
 * @var bool   $show_stats Status-Counter über Tabelle anzeigen.
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
				<th class="col-link" aria-label="Aktion"></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $items as $unit ) :
			$prop          = ! empty( $unit['property'] ) ? $unit['property'] : array();
			$unit_title    = $prop && ! empty( $prop['title'] ) ? $prop['title'] : ( 'Wohneinheit ' . ( $unit['unit_number'] ?? '' ) );
			$prop_slug     = $prop && ! empty( $prop['slug'] ) ? $prop['slug'] : '';
			$detail_url    = $prop_slug ? home_url( '/immobilie/' . $prop_slug ) : '';
			$u_status      = isset( $unit['status'] ) ? $unit['status'] : '';
			$u_status_lbl  = isset( $unit['status_label'] ) ? $unit['status_label'] : ( $status_labels[ $u_status ] ?? $u_status );
			$u_floor       = isset( $unit['floor'] ) ? $unit['floor'] : '';
			$u_price       = isset( $unit['price_formatted'] ) ? $unit['price_formatted'] : '';
			$cf_show       = ! empty( $prop['commission_free'] );
			$cf_label      = isset( $prop['commission_free_label'] ) ? $prop['commission_free_label'] : '';
		?>
			<tr class="immo-unit-row" data-status="<?php echo esc_attr( $u_status ); ?>">
				<td class="col-nr"><?php echo esc_html( $unit['unit_number'] ?? '' ); ?></td>
				<td class="col-title">
					<?php if ( $detail_url ) : ?>
						<a href="<?php echo esc_url( $detail_url ); ?>"><?php echo esc_html( $unit_title ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $unit_title ); ?>
					<?php endif; ?>
				</td>
				<td class="col-status">
					<?php if ( $u_status ) : ?>
						<span class="immo-status immo-status-<?php echo esc_attr( $u_status ); ?>"><?php echo esc_html( $u_status_lbl ); ?></span>
					<?php endif; ?>
				</td>
				<td class="col-area"><?php echo ! empty( $unit['area'] ) ? esc_html( $unit['area'] ) . ' m²' : '–'; ?></td>
				<td class="col-rooms"><?php echo ! empty( $unit['rooms'] ) ? esc_html( (int) $unit['rooms'] ) : '–'; ?></td>
				<td class="col-floor"><?php echo $u_floor !== '' ? esc_html( $u_floor ) . '. OG' : '–'; ?></td>
				<td class="col-price">
					<?php echo $u_price ? esc_html( $u_price ) : '–'; ?>
					<?php
					if ( $cf_show ) {
						immo_client_render_cf_badge(
							array(
								'commission_free'       => true,
								'mode'                  => 'sale',
								'commission_free_label' => $cf_label,
							),
							'icon'
						);
					}
					?>
				</td>
				<td class="col-link">
					<?php if ( $detail_url ) : ?>
						<a href="<?php echo esc_url( $detail_url ); ?>" class="immo-units-detail-link" aria-label="Details ansehen">→</a>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
