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
		<?php foreach ( $items as $unit ) :
			$prop          = ! empty( $unit['property'] ) ? $unit['property'] : array();
			$unit_title    = $prop && ! empty( $prop['title'] ) ? $prop['title'] : ( 'Wohneinheit ' . ( $unit['unit_number'] ?? '' ) );
			$prop_slug     = $prop && ! empty( $prop['slug'] ) ? $prop['slug'] : '';
			$detail_url    = $prop_slug ? home_url( '/immobilie/' . $prop_slug ) : '';
			$unit_id       = isset( $unit['id'] ) ? (int) $unit['id'] : 0;
			$u_status      = isset( $unit['status'] ) ? $unit['status'] : '';
			$u_status_lbl  = isset( $unit['status_label'] ) ? $unit['status_label'] : ( $status_labels[ $u_status ] ?? $u_status );
			$u_floor       = isset( $unit['floor'] ) ? $unit['floor'] : '';
			$u_price       = isset( $unit['price_formatted'] ) ? $unit['price_formatted'] : '';
		?>
			<tr class="immo-unit-row is-clickable" data-status="<?php echo esc_attr( $u_status ); ?>"
				data-immo-unit-id="<?php echo esc_attr( $unit_id ); ?>"
				data-immo-unit-url="<?php echo esc_attr( $detail_url ); ?>">
				<td class="col-nr"><?php echo esc_html( $unit['unit_number'] ?? '' ); ?></td>
				<td class="col-title">
					<?php echo esc_html( $unit_title ); ?>
					<?php
					$extras = array();
					if ( (float) ( $unit['balcony_area'] ?? 0 ) > 0 ) { $extras[] = '🏔️ ' . number_format_i18n( (float) $unit['balcony_area'], 0 ) . ' m²'; }
					if ( (float) ( $unit['loggia_area']  ?? 0 ) > 0 ) { $extras[] = '🏛️ ' . number_format_i18n( (float) $unit['loggia_area'],  0 ) . ' m²'; }
					if ( (float) ( $unit['garden_area']  ?? 0 ) > 0 ) { $extras[] = '🌿 ' . number_format_i18n( (float) $unit['garden_area'],  0 ) . ' m²'; }
					if ( (float) ( $unit['cellar_area']  ?? 0 ) > 0 ) { $extras[] = '🏚️ ' . number_format_i18n( (float) $unit['cellar_area'],  0 ) . ' m²'; }
					if ( (int)   ( $unit['parking']['garage_count']  ?? 0 ) > 0 ) { $extras[] = '🅿️ ×' . (int) $unit['parking']['garage_count']; }
					if ( (int)   ( $unit['parking']['outdoor_count'] ?? 0 ) > 0 ) { $extras[] = '🚗 ×' . (int) $unit['parking']['outdoor_count']; }
					if ( $extras ) :
					?>
						<span class="immo-unit-extras">
							<?php foreach ( $extras as $e ) : ?>
								<span class="immo-unit-extra"><?php echo esc_html( $e ); ?></span>
							<?php endforeach; ?>
						</span>
					<?php endif; ?>
				</td>
				<td class="col-status">
					<?php if ( $u_status ) : ?>
						<span class="immo-status immo-status-<?php echo esc_attr( $u_status ); ?>"><?php echo esc_html( $u_status_lbl ); ?></span>
					<?php endif; ?>
				</td>
				<td class="col-area"><?php echo ! empty( $unit['area'] ) ? esc_html( $unit['area'] ) . ' m²' : '–'; ?></td>
				<td class="col-rooms"><?php echo ! empty( $unit['rooms'] ) ? esc_html( (int) $unit['rooms'] ) : '–'; ?></td>
				<td class="col-floor"><?php echo esc_html( immo_client_floor_label( $u_floor ) ); ?></td>
				<td class="col-price"><?php echo $u_price ? esc_html( $u_price ) : '–'; ?></td>
				<td class="col-info">
					<span class="immo-unit-info-btn" aria-label="Quick-Info anzeigen" title="Quick-Info anzeigen">
						<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
					</span>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
