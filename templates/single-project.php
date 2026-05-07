<?php
/**
 * Detailseite eines Bauprojekts unter /bauprojekt/{slug}.
 *
 * Layout: einspaltig, volle Breite. Inhaltssektionen untereinander
 * (Hero-Galerie → Header → Stats-Grid → Beschreibung → Highlights →
 * Ausstattung → Wohneinheiten → Video → Lage+Karte → Dokumente →
 * Kontakt). Anfrage-Formular liegt in einem Modal.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$immo_max_width  = (int) get_option( 'immo_detail_max_width', 1200 );
$immo_use_wrap   = (string) get_option( 'immo_detail_use_theme_wrapper', '1' ) === '1';
$immo_link_units = ( (string) get_option( 'immo_project_link_units', '1' ) ) !== '0';

if ( $immo_use_wrap ) {
	get_header();
}

$project_slug = get_query_var( 'immo_project_slug' );
$api          = new ImmoAPI();
$project      = $api->get_project_by_slug( $project_slug );

if ( ! $project ) :
	?>
	<div class="immo-container immo-detail-notfound">
		<h1>Projekt nicht gefunden</h1>
		<p>Das gewünschte Bauprojekt ist nicht mehr verfügbar.</p>
	</div>
	<?php
	if ( $immo_use_wrap ) { get_footer(); }
	return;
endif;

// ---------------------------------------------------------------------------
// Daten aus REST entpacken
// ---------------------------------------------------------------------------

$meta       = isset( $project['meta'] ) ? $project['meta'] : array();
$hero       = isset( $project['featured_image'] ) ? $project['featured_image'] : null;
$gallery    = isset( $project['gallery'] ) && is_array( $project['gallery'] ) ? $project['gallery'] : array();
$unit_stats = isset( $project['unit_stats'] ) ? $project['unit_stats'] : array();

// Standort
$city        = (string) ( $meta['city'] ?? '' );
$plz         = (string) ( $meta['postal_code'] ?? '' );
$state_label = (string) ( $meta['region_state_label'] ?? '' );
$dist_label  = (string) ( $meta['region_district_label'] ?? '' );
$address     = (string) ( $meta['address'] ?? '' );
$lat         = (float)  ( $meta['lat'] ?? 0 );
$lng         = (float)  ( $meta['lng'] ?? 0 );

// Projekt-Status (Bug-Fix: korrekte REST-Feldnamen)
$status     = (string) ( $meta['project_status'] ?? '' );
$start_date = (string) ( $meta['project_start_date'] ?? '' );
$completion = (string) ( $meta['project_completion'] ?? '' );

$status_labels = array(
	'planning'  => 'In Planung',
	'building'  => 'In Bau',
	'completed' => 'Fertiggestellt',
);
$status_label = $status_labels[ $status ] ?? '';

// Inhalte
$features_detail = isset( $meta['features_detail'] ) && is_array( $meta['features_detail'] ) ? $meta['features_detail'] : array();
$custom_features = (string) ( $meta['custom_features'] ?? '' );
$documents       = isset( $meta['documents'] ) && is_array( $meta['documents'] ) ? $meta['documents'] : array();
$video_url       = (string) ( $meta['video_url'] ?? '' );
$video_file      = (string) ( $meta['video_file_url'] ?? '' );

// Kontakt
$contact_name  = (string) ( $meta['contact_name'] ?? '' );
$contact_email = (string) ( $meta['contact_email'] ?? '' );
$contact_phone = (string) ( $meta['contact_phone'] ?? '' );
$contact_image = isset( $meta['contact_image'] ) && ! empty( $meta['contact_image']['url_thumbnail'] ) ? $meta['contact_image'] : null;

// Standort-String
$location_str = trim( implode( ' ', array_filter( array( $plz, $city ) ) ) );
if ( $state_label ) { $location_str .= ( $location_str ? ', ' : '' ) . $state_label; }

// Galerie-Slides
$slides = array();
if ( $hero && ! empty( $hero['url'] ) ) { $slides[] = $hero; }
foreach ( $gallery as $g ) {
	if ( ! empty( $g['url'] ) ) { $slides[] = $g; }
}

// Wohneinheiten + Stats
$units_payload = $api->get_project_units( $project['id'] );
$items         = isset( $units_payload['units'] ) ? $units_payload['units'] : array();

$count_total     = (int) ( $unit_stats['total']     ?? count( $items ) );
$count_available = (int) ( $unit_stats['available'] ?? 0 );
$count_reserved  = (int) ( $unit_stats['reserved']  ?? 0 );
$count_sold      = (int) ( $unit_stats['sold']      ?? 0 );
$count_rented    = (int) ( $unit_stats['rented']    ?? 0 );

// Falls Stats nicht vom API kommen: ableiten.
if ( ! isset( $unit_stats['available'] ) && ! empty( $items ) ) {
	foreach ( $items as $u ) {
		switch ( $u['status'] ?? '' ) {
			case 'available': $count_available++; break;
			case 'reserved':  $count_reserved++;  break;
			case 'sold':      $count_sold++;      break;
			case 'rented':    $count_rented++;    break;
		}
	}
}

// Flächen-Range
$area_min = isset( $unit_stats['area_min'] ) ? (float) $unit_stats['area_min'] : 0;
$area_max = isset( $unit_stats['area_max'] ) ? (float) $unit_stats['area_max'] : 0;

// Datum formatieren
$fmt_date = static function ( $iso ) {
	if ( ! $iso ) { return ''; }
	$ts = strtotime( $iso );
	return $ts ? date_i18n( 'd.m.Y', $ts ) : (string) $iso;
};

// Karten-Optionen aus public-settings
$public_settings = ImmoStyles::public_settings();
$map_enabled     = ! empty( $public_settings['map']['enabled'] );
$map_tile_url    = (string) ( $public_settings['map']['tile_url'] ?? 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png' );
$map_attribution = (string) ( $public_settings['map']['attribution'] ?? '&copy; OpenStreetMap contributors' );

$style_attr = $immo_max_width ? ' style="max-width:' . esc_attr( $immo_max_width ) . 'px;"' : '';
?>

<article id="immo-project-<?php echo esc_attr( $project['id'] ); ?>" class="immo-detail immo-project-detail immo-project-singlecol"<?php echo $style_attr; ?>>

	<!-- ========== HERO + HEADER NEBENEINANDER (Desktop: Hero 2fr, Header 1fr) ========== -->
	<div class="immo-project-headblock">

	<!-- ========== HERO-GALERIE ========== -->
	<?php if ( ! empty( $slides ) ) : ?>
	<section class="immo-project-hero">
		<div class="immo-gallery-stage">
			<?php foreach ( $slides as $idx => $img ) : ?>
				<div class="immo-slide<?php echo $idx === 0 ? ' is-active' : ''; ?>"
					data-large="<?php echo esc_url( ! empty( $img['url_large'] ) ? $img['url_large'] : $img['url'] ); ?>">
					<img src="<?php echo esc_url( ! empty( $img['url_large'] ) ? $img['url_large'] : $img['url'] ); ?>"
						alt="<?php echo esc_attr( ! empty( $img['alt'] ) ? $img['alt'] : $project['title'] ); ?>"
						loading="<?php echo $idx === 0 ? 'eager' : 'lazy'; ?>">
				</div>
			<?php endforeach; ?>

			<?php if ( count( $slides ) > 1 ) : ?>
				<button type="button" class="immo-nav-prev" aria-label="Vorheriges Bild">&#8249;</button>
				<button type="button" class="immo-nav-next" aria-label="Nächstes Bild">&#8250;</button>
				<div class="immo-slide-counter"></div>
			<?php endif; ?>
			<button type="button" class="immo-expand" aria-label="Vergrößern" tabindex="-1">⤢</button>

			<?php if ( $status_label ) : ?>
				<span class="immo-project-status-pill immo-project-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status_label ); ?></span>
			<?php endif; ?>
		</div>

		<?php if ( count( $slides ) > 1 ) : ?>
			<div class="immo-thumbs">
				<?php foreach ( $slides as $idx => $img ) : ?>
					<button type="button" class="immo-thumb<?php echo $idx === 0 ? ' is-active' : ''; ?>" aria-label="Bild <?php echo esc_attr( $idx + 1 ); ?>">
						<img src="<?php echo esc_url( ! empty( $img['url_thumbnail'] ) ? $img['url_thumbnail'] : $img['url'] ); ?>" alt="" loading="lazy">
					</button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>
	<?php endif; ?>

	<!-- ========== HEADER (Title + CTAs) ========== -->
	<header class="immo-project-header">
		<h1 class="immo-project-title"><?php echo esc_html( $project['title'] ); ?></h1>
		<?php if ( $location_str || $address ) : ?>
			<p class="immo-project-location">📍 <?php echo esc_html( $location_str ); ?><?php if ( $address ) { echo ' · ' . esc_html( $address ); } ?></p>
		<?php endif; ?>

		<div class="immo-project-cta-row">
			<button type="button" class="immo-btn immo-btn-primary" data-immo-inquiry-open>✉️ Anfrage senden</button>
			<?php if ( $contact_phone ) : ?>
				<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $contact_phone ) ); ?>" class="immo-btn immo-btn-secondary">📞 Jetzt anrufen</a>
			<?php endif; ?>
		</div>
	</header>

	</div><!-- /.immo-project-headblock -->

	<!-- ========== STATS-GRID ========== -->
	<section class="immo-project-stats-grid">
		<?php if ( $status_label ) : ?>
			<div class="immo-project-stat">
				<span class="immo-project-stat-icon" aria-hidden="true">🏗️</span>
				<span class="immo-project-stat-label">Status</span>
				<strong class="immo-project-stat-value"><?php echo esc_html( $status_label ); ?></strong>
			</div>
		<?php endif; ?>
		<?php if ( $start_date ) : ?>
			<div class="immo-project-stat">
				<span class="immo-project-stat-icon" aria-hidden="true">📅</span>
				<span class="immo-project-stat-label">Baubeginn</span>
				<strong class="immo-project-stat-value"><?php echo esc_html( $fmt_date( $start_date ) ); ?></strong>
			</div>
		<?php endif; ?>
		<?php if ( $completion ) : ?>
			<div class="immo-project-stat">
				<span class="immo-project-stat-icon" aria-hidden="true">🏁</span>
				<span class="immo-project-stat-label">Fertigstellung</span>
				<strong class="immo-project-stat-value"><?php echo esc_html( $fmt_date( $completion ) ); ?></strong>
			</div>
		<?php endif; ?>
		<?php if ( $count_total ) : ?>
			<div class="immo-project-stat">
				<span class="immo-project-stat-icon" aria-hidden="true">🏠</span>
				<span class="immo-project-stat-label">Wohneinheiten</span>
				<strong class="immo-project-stat-value"><?php echo (int) $count_total; ?></strong>
			</div>
		<?php endif; ?>
		<?php if ( $area_min > 0 || $area_max > 0 ) : ?>
			<div class="immo-project-stat">
				<span class="immo-project-stat-icon" aria-hidden="true">📐</span>
				<span class="immo-project-stat-label">Flächen</span>
				<strong class="immo-project-stat-value">
					<?php
					if ( $area_min > 0 && $area_max > 0 && $area_min !== $area_max ) {
						echo esc_html( number_format_i18n( $area_min, 0 ) . '–' . number_format_i18n( $area_max, 0 ) ) . ' m²';
					} else {
						$single = $area_max > 0 ? $area_max : $area_min;
						echo esc_html( number_format_i18n( $single, 0 ) ) . ' m²';
					}
					?>
				</strong>
			</div>
		<?php endif; ?>
	</section>

	<!-- ========== 2-SPALTEN-WRAPPER (Main + Sticky-Sidebar) ========== -->
	<div class="immo-project-content-wrap">
	<main class="immo-project-content">

	<!-- ========== BESCHREIBUNG ========== -->
	<?php if ( ! empty( $project['description'] ) ) : ?>
	<section class="immo-section immo-project-description">
		<h2>Über das Projekt</h2>
		<div class="immo-rich-text"><?php echo wp_kses_post( $project['description'] ); ?></div>
	</section>
	<?php endif; ?>

	<!-- ========== HIGHLIGHTS (custom_features) ========== -->
	<?php if ( $custom_features ) : ?>
	<section class="immo-section immo-project-highlights">
		<h2>Highlights</h2>
		<div class="immo-rich-text"><?php echo wp_kses_post( wpautop( $custom_features ) ); ?></div>
	</section>
	<?php endif; ?>

	<!-- ========== AUSSTATTUNG (features_detail nach Kategorie gruppiert) ========== -->
	<?php if ( ! empty( $features_detail ) ) :
		$grouped = array();
		foreach ( $features_detail as $f ) {
			$cat_key   = ! empty( $f['category'] ) ? $f['category'] : 'sonstiges';
			$cat_label = ! empty( $f['category_label'] ) ? $f['category_label'] : 'Sonstiges';
			if ( ! isset( $grouped[ $cat_key ] ) ) {
				$grouped[ $cat_key ] = array( 'label' => $cat_label, 'items' => array() );
			}
			$grouped[ $cat_key ]['items'][] = $f;
		}
	?>
	<section class="immo-section immo-project-features">
		<h2>Gemeinschafts-Ausstattung</h2>
		<?php $first = true; foreach ( $grouped as $cat_key => $group ) : ?>
			<details class="immo-accordion" <?php echo $first ? 'open' : ''; ?>>
				<summary>
					<span class="immo-accordion-title"><?php echo esc_html( $group['label'] ); ?></span>
					<span class="immo-accordion-count"><?php echo count( $group['items'] ); ?></span>
				</summary>
				<ul class="immo-feature-list">
					<?php foreach ( $group['items'] as $f ) : ?>
						<li>
							<?php if ( ! empty( $f['icon'] ) ) : ?>
								<span class="immo-feature-icon" aria-hidden="true"><?php echo esc_html( $f['icon'] ); ?></span>
							<?php endif; ?>
							<span><?php echo esc_html( $f['label'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</details>
		<?php $first = false; endforeach; ?>
	</section>
	<?php endif; ?>

	<!-- ========== STELLPLÄTZE ========== -->
	<?php
	$pk         = $meta['parking'] ?? array();
	$pk_garage  = $pk['garage']  ?? array();
	$pk_outdoor = $pk['outdoor'] ?? array();
	$pk_notes   = (string) ( $pk['notes'] ?? '' );
	$has_garage  = ! empty( $pk_garage['available'] );
	$has_outdoor = ! empty( $pk_outdoor['available'] );
	if ( $has_garage || $has_outdoor || '' !== $pk_notes ) :
		$fmt_money = static function ( $val ) {
			$val = (float) $val;
			return $val > 0 ? number_format_i18n( $val, 0 ) . ' €' : __( 'Preis auf Anfrage', 'immo-client' );
		};
	?>
	<section class="immo-section immo-project-parking">
		<h2><?php esc_html_e( 'Stellplätze', 'immo-client' ); ?></h2>
		<ul class="immo-parking-list">
			<?php if ( $has_garage ) : ?>
				<li class="immo-parking-item">
					<span class="immo-parking-icon" aria-hidden="true">🅿️</span>
					<div class="immo-parking-meta">
						<strong class="immo-parking-title"><?php esc_html_e( 'Tiefgaragenplatz', 'immo-client' ); ?></strong>
						<span class="immo-parking-price"><?php echo esc_html( $fmt_money( $pk_garage['price'] ?? 0 ) ); ?></span>
						<span class="immo-parking-flag immo-parking-flag-<?php echo ! empty( $pk_garage['required'] ) ? 'required' : 'optional'; ?>">
							<?php echo ! empty( $pk_garage['required'] ) ? esc_html__( 'verpflichtend', 'immo-client' ) : esc_html__( 'optional', 'immo-client' ); ?>
						</span>
						<?php if ( (int) ( $pk_garage['total'] ?? 0 ) > 0 ) : ?>
							<span class="immo-parking-total"><?php
								/* translators: %d: Gesamtanzahl */
								printf( esc_html__( '%d Plätze gesamt', 'immo-client' ), (int) $pk_garage['total'] );
							?></span>
						<?php endif; ?>
					</div>
				</li>
			<?php endif; ?>
			<?php if ( $has_outdoor ) : ?>
				<li class="immo-parking-item">
					<span class="immo-parking-icon" aria-hidden="true">🚗</span>
					<div class="immo-parking-meta">
						<strong class="immo-parking-title"><?php esc_html_e( 'Außen-Stellplatz', 'immo-client' ); ?></strong>
						<span class="immo-parking-price"><?php echo esc_html( $fmt_money( $pk_outdoor['price'] ?? 0 ) ); ?></span>
						<span class="immo-parking-flag immo-parking-flag-<?php echo ! empty( $pk_outdoor['required'] ) ? 'required' : 'optional'; ?>">
							<?php echo ! empty( $pk_outdoor['required'] ) ? esc_html__( 'verpflichtend', 'immo-client' ) : esc_html__( 'optional', 'immo-client' ); ?>
						</span>
						<?php if ( (int) ( $pk_outdoor['total'] ?? 0 ) > 0 ) : ?>
							<span class="immo-parking-total"><?php
								/* translators: %d: Gesamtanzahl */
								printf( esc_html__( '%d Plätze gesamt', 'immo-client' ), (int) $pk_outdoor['total'] );
							?></span>
						<?php endif; ?>
					</div>
				</li>
			<?php endif; ?>
		</ul>
		<?php if ( '' !== $pk_notes ) : ?>
			<p class="immo-parking-notes"><?php echo esc_html( $pk_notes ); ?></p>
		<?php endif; ?>
	</section>
	<?php endif; ?>

	<!-- ========== CTA-BANNER zwischen Inhalten und Wohneinheiten ========== -->
	<section class="immo-project-cta-banner" aria-label="Anfrage senden">
		<div class="immo-project-cta-banner-text">
			<h3 class="immo-project-cta-banner-title">Interesse an einer Wohneinheit?</h3>
			<p>Sichern Sie sich jetzt Ihren Wunsch-Top — wir beraten Sie gerne unverbindlich.</p>
		</div>
		<div class="immo-project-cta-banner-actions">
			<button type="button" class="immo-btn immo-btn-primary" data-immo-inquiry-open>✉️ Anfrage senden</button>
			<?php if ( $contact_phone ) : ?>
				<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $contact_phone ) ); ?>" class="immo-btn immo-btn-secondary">📞 Anrufen</a>
			<?php endif; ?>
		</div>
	</section>

	<!-- ========== WOHNEINHEITEN ========== -->
	<section class="immo-section immo-units-list">
		<h2>Wohneinheiten <?php if ( $count_total ) { echo '<span class="immo-units-count">' . esc_html( $count_total ) . '</span>'; } ?></h2>

		<?php if ( $count_total > 0 ) : ?>
			<div class="immo-units-summary" role="group" aria-label="Wohneinheiten nach Status filtern">
				<?php if ( $count_available ) : ?>
					<button type="button" class="immo-units-stat immo-units-stat-available" data-immo-filter-status="available" aria-pressed="false" title="Klicken, um nach Status zu filtern"><strong><?php echo esc_html( $count_available ); ?></strong> Verfügbar</button>
				<?php endif; ?>
				<?php if ( $count_reserved ) : ?>
					<button type="button" class="immo-units-stat immo-units-stat-reserved" data-immo-filter-status="reserved" aria-pressed="false" title="Klicken, um nach Status zu filtern"><strong><?php echo esc_html( $count_reserved ); ?></strong> Reserviert</button>
				<?php endif; ?>
				<?php if ( $count_sold ) : ?>
					<button type="button" class="immo-units-stat immo-units-stat-sold" data-immo-filter-status="sold" aria-pressed="false" title="Klicken, um nach Status zu filtern"><strong><?php echo esc_html( $count_sold ); ?></strong> Verkauft</button>
				<?php endif; ?>
				<?php if ( $count_rented ) : ?>
					<button type="button" class="immo-units-stat immo-units-stat-rented" data-immo-filter-status="rented" aria-pressed="false" title="Klicken, um nach Status zu filtern"><strong><?php echo esc_html( $count_rented ); ?></strong> Vermietet</button>
				<?php endif; ?>
				<span class="immo-units-stat immo-units-stat-total"><strong><?php echo esc_html( $count_total ); ?></strong> Gesamt</span>
			</div>
		<?php endif; ?>

		<?php if ( empty( $items ) ) : ?>
			<p>Aktuell sind keine Einheiten zum Projekt hinterlegt.</p>
		<?php else : ?>
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
						$unit_id       = (int) ( $unit['id'] ?? 0 );
						$u_status      = (string) ( $unit['status'] ?? '' );
						$u_status_lbl  = (string) ( $unit['status_label'] ?? $u_status );
						$u_floor       = $unit['floor'] ?? '';
						$u_price       = (string) ( $unit['price_formatted'] ?? '' );
					?>
						<tr class="immo-unit-row is-clickable" data-status="<?php echo esc_attr( $u_status ); ?>"
							data-immo-unit-id="<?php echo esc_attr( $unit_id ); ?>"
							data-immo-unit-url="<?php echo esc_attr( $detail_url ); ?>">
							<td class="col-nr"><strong><?php echo esc_html( $unit['unit_number'] ?? '' ); ?></strong></td>
							<td class="col-title">
								<?php echo esc_html( $unit_title ); ?>
								<?php
								$extras = array();
								if ( (float) ( $unit['balcony_area'] ?? 0 ) > 0 ) { $extras[] = array( 'label' => __( 'Balkon', 'immo-client' ),     'text' => '🪟 ' . number_format_i18n( (float) $unit['balcony_area'], 0 ) . ' m²' ); }
								if ( (float) ( $unit['loggia_area']  ?? 0 ) > 0 ) { $extras[] = array( 'label' => __( 'Loggia', 'immo-client' ),     'text' => '🏛️ ' . number_format_i18n( (float) $unit['loggia_area'],  0 ) . ' m²' ); }
								if ( (float) ( $unit['terrace_area'] ?? 0 ) > 0 ) { $extras[] = array( 'label' => __( 'Terrasse', 'immo-client' ),   'text' => '⛱️ ' . number_format_i18n( (float) $unit['terrace_area'], 0 ) . ' m²' ); }
								if ( (float) ( $unit['garden_area']  ?? 0 ) > 0 ) { $extras[] = array( 'label' => __( 'Garten', 'immo-client' ),     'text' => '🌳 ' . number_format_i18n( (float) $unit['garden_area'],  0 ) . ' m²' ); }
								if ( (float) ( $unit['cellar_area']  ?? 0 ) > 0 ) { $extras[] = array( 'label' => __( 'Keller', 'immo-client' ),     'text' => '📦 ' . number_format_i18n( (float) $unit['cellar_area'],  0 ) . ' m²' ); }
								if ( (int)   ( $unit['parking']['garage_count']  ?? 0 ) > 0 ) { $extras[] = array( 'label' => __( 'Tiefgaragenplatz', 'immo-client' ),  'text' => '🅿️ ×' . (int) $unit['parking']['garage_count'] ); }
								if ( (int)   ( $unit['parking']['outdoor_count'] ?? 0 ) > 0 ) { $extras[] = array( 'label' => __( 'Außen-Stellplatz',  'immo-client' ), 'text' => '🚗 ×' . (int) $unit['parking']['outdoor_count'] ); }
								if ( $extras ) :
								?>
									<span class="immo-unit-extras">
										<?php foreach ( $extras as $e ) : ?>
											<span class="immo-unit-extra" title="<?php echo esc_attr( $e['label'] ); ?>" aria-label="<?php echo esc_attr( $e['label'] . ': ' . $e['text'] ); ?>"><?php echo esc_html( $e['text'] ); ?></span>
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

			<?php /* Lightbox-Datenpool — pro Unit das HTML, das in die Lightbox geklont wird. */ ?>
			<div id="immo-unit-lightbox-data" class="immo-unit-lightbox-data" hidden aria-hidden="true">
				<?php
				if ( ! function_exists( 'immo_pick_field' ) ) {
					function immo_pick_field( $arr, $keys ) {
						if ( ! is_array( $arr ) ) { return ''; }
						foreach ( $keys as $k ) {
							if ( isset( $arr[ $k ] ) && $arr[ $k ] !== '' && $arr[ $k ] !== null ) {
								return $arr[ $k ];
							}
						}
						return '';
					}
				}
				foreach ( $items as $unit ) :
					$unit_property = ! empty( $unit['property'] ) ? $unit['property'] : null;
					$unit_slug     = (string) ( immo_pick_field( $unit_property, array( 'slug' ) ) ?: immo_pick_field( $unit, array( 'property_slug', 'slug' ) ) );
					$prop_id       = (int) ( immo_pick_field( $unit_property, array( 'id' ) ) ?: immo_pick_field( $unit, array( 'property_id' ) ) );

					$full_property = null;
					if ( $unit_slug ) { $full_property = $api->get_property_by_slug( $unit_slug ); }
					elseif ( $prop_id ) { $full_property = $api->get_property( $prop_id ); }

					if ( ! $unit_slug && is_array( $full_property ) ) {
						$unit_slug = (string) immo_pick_field( $full_property, array( 'slug' ) );
					}
					$fp_meta = ( is_array( $full_property ) && isset( $full_property['meta'] ) ) ? $full_property['meta'] : array();

					$unit_title = $unit_property && ! empty( $unit_property['title'] ) ? $unit_property['title'] : ( 'Wohneinheit ' . ( $unit['unit_number'] ?? '' ) );
					$unit_image = $unit_property && ! empty( $unit_property['image'] ) ? $unit_property['image'] : '';
					if ( ! $unit_image && $full_property && ! empty( $full_property['featured_image']['url_large'] ) ) {
						$unit_image = $full_property['featured_image']['url_large'];
					}

					$unit_excerpt = '';
					if ( $full_property && ! empty( $full_property['description'] ) ) {
						$plain = trim( wp_strip_all_tags( (string) $full_property['description'] ) );
						if ( $plain !== '' ) { $unit_excerpt = wp_trim_words( $plain, 35, ' …' ); }
					}

					$unit_id      = (int) ( $unit['id'] ?? 0 );
					$u_status     = (string) ( $unit['status'] ?? '' );
					$u_status_lbl = (string) ( $unit['status_label'] ?? $u_status );

					$u_area    = isset( $unit['area'] ) && $unit['area'] !== '' ? $unit['area'] : ( $fp_meta['area'] ?? '' );
					$u_rooms   = isset( $unit['rooms'] ) && (int) $unit['rooms'] > 0 ? (int) $unit['rooms'] : (int) ( $fp_meta['rooms'] ?? 0 );
					$u_bath    = (int) ( $fp_meta['bathrooms'] ?? 0 );
					$u_floor   = isset( $unit['floor'] ) && $unit['floor'] !== '' ? $unit['floor'] : ( $fp_meta['floor'] ?? '' );
					$u_built   = (int) ( $fp_meta['built_year'] ?? 0 );
					$u_energy  = (string) ( $fp_meta['energy_class'] ?? '' );
					$u_price   = isset( $unit['price_formatted'] ) && $unit['price_formatted'] !== '' ? $unit['price_formatted'] : ( $fp_meta['price_formatted'] ?? '' );
					$u_address = (string) ( $fp_meta['address'] ?? '' );
					$u_city    = (string) ( $fp_meta['city'] ?? '' );
					$u_plz     = (string) ( $fp_meta['postal_code'] ?? '' );
					$u_full_addr = trim( $u_address . ( $u_plz || $u_city ? ', ' . trim( $u_plz . ' ' . $u_city ) : '' ) );
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
							<?php if ( ! empty( $unit['unit_number'] ) ) : ?>
								<span class="immo-unit-quick-eyebrow"><?php echo esc_html( $unit['unit_number'] ); ?></span>
							<?php endif; ?>
							<h3 class="immo-unit-quick-title"><?php echo esc_html( $unit_title ); ?></h3>
							<?php if ( $u_full_addr ) : ?>
								<p class="immo-unit-quick-address">📍 <?php echo esc_html( $u_full_addr ); ?></p>
							<?php endif; ?>
							<?php if ( $unit_excerpt ) : ?>
								<p class="immo-unit-quick-intro"><?php echo esc_html( $unit_excerpt ); ?></p>
							<?php endif; ?>

							<ul class="immo-unit-quick-facts">
								<?php if ( $u_area !== '' ) : ?><li><span class="ico" aria-hidden="true">📐</span><span class="lab">Wohnfläche</span><strong><?php echo esc_html( $u_area ); ?> m²</strong></li><?php endif; ?>
								<?php if ( $u_rooms > 0 ) : ?><li><span class="ico" aria-hidden="true">🛏️</span><span class="lab">Zimmer</span><strong><?php echo esc_html( $u_rooms ); ?></strong></li><?php endif; ?>
								<?php if ( $u_bath > 0 ) : ?><li><span class="ico" aria-hidden="true">🛁</span><span class="lab">Bad</span><strong><?php echo esc_html( $u_bath ); ?></strong></li><?php endif; ?>
								<?php $floor_lbl = immo_client_floor_label( $u_floor ); if ( $floor_lbl !== '–' ) : ?><li><span class="ico" aria-hidden="true">🏢</span><span class="lab">Etage</span><strong><?php echo esc_html( $floor_lbl ); ?></strong></li><?php endif; ?>
								<?php if ( $u_built ) : ?><li><span class="ico" aria-hidden="true">📅</span><span class="lab">Baujahr</span><strong><?php echo esc_html( $u_built ); ?></strong></li><?php endif; ?>
								<?php if ( $u_energy ) : ?><li><span class="ico" aria-hidden="true">⚡</span><span class="lab">Energieklasse</span><strong><?php echo esc_html( $u_energy ); ?></strong></li><?php endif; ?>
								<?php if ( ! empty( $unit['balcony_area'] ) && (float) $unit['balcony_area'] > 0 ) : ?>
									<li title="Balkon"><span class="ico" aria-hidden="true">🪟</span><span class="lab">Balkon</span><strong><?php echo esc_html( (string) $unit['balcony_area'] ); ?> m²</strong></li>
								<?php endif; ?>
								<?php if ( ! empty( $unit['loggia_area'] ) && (float) $unit['loggia_area'] > 0 ) : ?>
									<li title="Loggia"><span class="ico" aria-hidden="true">🏛️</span><span class="lab">Loggia</span><strong><?php echo esc_html( (string) $unit['loggia_area'] ); ?> m²</strong></li>
								<?php endif; ?>
								<?php if ( ! empty( $unit['terrace_area'] ) && (float) $unit['terrace_area'] > 0 ) : ?>
									<li title="Terrasse"><span class="ico" aria-hidden="true">⛱️</span><span class="lab">Terrasse</span><strong><?php echo esc_html( (string) $unit['terrace_area'] ); ?> m²</strong></li>
								<?php endif; ?>
								<?php if ( ! empty( $unit['garden_area'] ) && (float) $unit['garden_area'] > 0 ) : ?>
									<li title="Garten"><span class="ico" aria-hidden="true">🌳</span><span class="lab">Garten</span><strong><?php echo esc_html( (string) $unit['garden_area'] ); ?> m²</strong></li>
								<?php endif; ?>
								<?php if ( ! empty( $unit['cellar_area'] ) && (float) $unit['cellar_area'] > 0 ) : ?>
									<li title="Keller"><span class="ico" aria-hidden="true">📦</span><span class="lab">Keller</span><strong><?php echo esc_html( (string) $unit['cellar_area'] ); ?> m²</strong></li>
								<?php endif; ?>
								<?php $pk_unit = $unit['parking'] ?? array(); ?>
								<?php if ( ! empty( $pk_unit['garage_count'] ) ) : ?>
									<li><span class="ico" aria-hidden="true">🅿️</span><span class="lab">Tiefgarage</span><strong>inkl. <?php echo (int) $pk_unit['garage_count']; ?>×</strong></li>
								<?php endif; ?>
								<?php if ( ! empty( $pk_unit['outdoor_count'] ) ) : ?>
									<li><span class="ico" aria-hidden="true">🚗</span><span class="lab">Stellplatz</span><strong>inkl. <?php echo (int) $pk_unit['outdoor_count']; ?>×</strong></li>
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
		<?php endif; ?>
	</section>

	<!-- Video-Sektion absichtlich deaktiviert (Source-Probleme). -->

	<!-- ========== LAGE + KARTE ========== -->
	<?php
	$lage_parts = array_filter( array(
		$address,
		trim( $plz . ' ' . $city ),
		$dist_label,
		$state_label,
	) );
	$lage_text = implode( ', ', $lage_parts );
	?>
	<?php if ( $lage_text || ( $map_enabled && $lat && $lng ) ) : ?>
	<section class="immo-section immo-project-lage">
		<h2>Lage</h2>
		<?php if ( $lage_text ) : ?>
			<p class="immo-project-lage-text"><span class="immo-project-lage-icon" aria-hidden="true">📍</span> <?php echo esc_html( $lage_text ); ?></p>
		<?php endif; ?>
		<?php if ( $map_enabled && $lat && $lng ) : ?>
			<div class="immo-project-map"
				data-immo-map="1"
				data-lat="<?php echo esc_attr( $lat ); ?>"
				data-lng="<?php echo esc_attr( $lng ); ?>"
				data-tile-url="<?php echo esc_attr( $map_tile_url ); ?>"
				data-attribution="<?php echo esc_attr( $map_attribution ); ?>"
				data-title="<?php echo esc_attr( $project['title'] ); ?>"></div>
		<?php endif; ?>
	</section>
	<?php endif; ?>

	<!-- ========== DOKUMENTE ========== -->
	<?php if ( ! empty( $documents ) ) : ?>
	<section class="immo-section immo-project-documents">
		<h2>Dokumente &amp; Exposé</h2>
		<ul class="immo-doc-list">
			<?php foreach ( $documents as $doc ) :
				if ( empty( $doc['url'] ) ) { continue; }
			?>
				<li>
					<a href="<?php echo esc_url( $doc['url'] ); ?>" target="_blank" rel="noopener" class="immo-doc-link">
						<span class="immo-doc-icon" aria-hidden="true">📄</span>
						<span class="immo-doc-title"><?php echo esc_html( ! empty( $doc['title'] ) ? $doc['title'] : 'Dokument' ); ?></span>
						<span class="immo-doc-cta">Ansehen →</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
	<?php endif; ?>

	</main><!-- /.immo-project-content -->

	<!-- ========== STICKY SIDEBAR (Kontakt + Anfrage-Formular direkt) ========== -->
	<aside class="immo-project-sticky-aside">
		<?php if ( $contact_name || $contact_email || $contact_phone ) : ?>
			<div class="immo-project-aside-card immo-project-aside-contact">
				<?php if ( $contact_image ) : ?>
					<img src="<?php echo esc_url( $contact_image['url_thumbnail'] ); ?>" alt="<?php echo esc_attr( $contact_name ); ?>" class="immo-project-aside-photo">
				<?php endif; ?>
				<div class="immo-project-aside-meta">
					<?php if ( $contact_name ) : ?>
						<strong class="immo-project-aside-name"><?php echo esc_html( $contact_name ); ?></strong>
					<?php endif; ?>
					<span class="immo-project-aside-role">Ihr Ansprechpartner</span>
					<?php if ( $contact_phone ) : ?>
						<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $contact_phone ) ); ?>" class="immo-project-aside-link">📞 <?php echo esc_html( $contact_phone ); ?></a>
					<?php endif; ?>
					<?php if ( $contact_email ) : ?>
						<a href="mailto:<?php echo esc_attr( $contact_email ); ?>" class="immo-project-aside-link">✉️ <?php echo esc_html( $contact_email ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<div class="immo-project-aside-card immo-project-aside-form">
			<h3>Anfrage senden</h3>
			<p class="immo-project-aside-intro">Stellen Sie Ihre Anfrage — wir melden uns zeitnah bei Ihnen.</p>
			<?php
			$immo_email = (string) get_option( 'immo_notify_email', '' );
			include IMMO_CLIENT_PATH . 'templates/project-inquiry-form.php';
			?>
		</div>
	</aside>

	</div><!-- /.immo-project-content-wrap -->

</article>

<!-- ========== ANFRAGE-MODAL ========== -->
<div id="immo-inquiry-modal" class="immo-inquiry-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="immo-inquiry-modal-title">
	<div class="immo-inquiry-modal-backdrop" data-immo-inquiry-close></div>
	<div class="immo-inquiry-modal-dialog" role="document">
		<button type="button" class="immo-inquiry-modal-close" data-immo-inquiry-close aria-label="Schließen">×</button>
		<h2 id="immo-inquiry-modal-title">Anfrage zu <?php echo esc_html( $project['title'] ); ?></h2>

		<?php if ( $contact_name || $contact_image ) : ?>
			<div class="immo-inquiry-modal-agent">
				<?php if ( $contact_image ) : ?>
					<img src="<?php echo esc_url( $contact_image['url_thumbnail'] ); ?>" alt="<?php echo esc_attr( $contact_name ); ?>">
				<?php endif; ?>
				<div>
					<?php if ( $contact_name ) : ?><strong><?php echo esc_html( $contact_name ); ?></strong><?php endif; ?>
					<?php if ( $contact_phone ) : ?><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $contact_phone ) ); ?>">📞 <?php echo esc_html( $contact_phone ); ?></a><?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php
		$immo_email = (string) get_option( 'immo_notify_email', '' );
		include IMMO_CLIENT_PATH . 'templates/project-inquiry-form.php';
		?>
	</div>
</div>

<?php /* Wohneinheiten-Quick-Info-Lightbox */ ?>
<div id="immo-unit-lightbox" class="immo-unit-lightbox" role="dialog" aria-modal="true" aria-hidden="true" aria-label="Wohneinheit Quick-Info" data-link-units="<?php echo $immo_link_units ? '1' : '0'; ?>">
	<div class="immo-unit-lightbox-backdrop" data-immo-lightbox-close></div>
	<div class="immo-unit-lightbox-dialog" role="document">
		<button type="button" class="immo-unit-lightbox-close" data-immo-lightbox-close aria-label="Schließen">×</button>
		<div class="immo-unit-lightbox-content"></div>
		<?php if ( $immo_link_units ) : ?>
			<div class="immo-unit-quick-actions" id="immo-unit-lightbox-actions">
				<a href="#" class="immo-unit-details-btn" id="immo-unit-lightbox-details-btn">Details ansehen →</a>
			</div>
		<?php endif; ?>
	</div>
</div>

<!-- ========== STICKY MOBILE-CTA-BAR (nur auf Mobile sichtbar via CSS) ========== -->
<div class="immo-project-mobile-cta" aria-label="Schneller Kontakt">
	<button type="button" class="immo-btn immo-btn-primary" data-immo-inquiry-open>✉️ Anfrage</button>
	<?php if ( $contact_phone ) : ?>
		<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $contact_phone ) ); ?>" class="immo-btn immo-btn-secondary">📞 Anrufen</a>
	<?php endif; ?>
</div>
<script>
(function () { if (document.body) document.body.classList.add('has-immo-project-cta'); })();
</script>

<?php
if ( $immo_use_wrap ) {
	get_footer();
}
