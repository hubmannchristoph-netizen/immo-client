<?php
/**
 * SEO + Schema.org JSON-LD für die Detailseiten des immo-client.
 *
 * Erzeugt:
 * - Title-Tag mit Eckdaten (Zimmer/Fläche/Stadt) statt nur Property-Title
 * - Meta-Description aus Excerpt/Beschreibung (max 158 Zeichen)
 * - Open Graph-Basis (title/description/image)
 * - Schema.org JSON-LD analog zur Manager-Klasse `\ImmoManager\Schema`,
 *   aber aus REST-Daten statt aus get_post_meta gebaut.
 *
 * @package ImmoClient
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ImmoSEO {

	/** @var array<string, mixed>|null Cache der REST-Daten pro Request. */
	private static $cached = null;

	/** @var string|null 'property' | 'project' | null */
	private static $cached_kind = null;

	public static function init() {
		add_filter( 'document_title_parts', array( __CLASS__, 'document_title_parts' ), 20 );
		// pre_get_document_title mit Prio 99 — nach Yoast/RankMath/AIOSEO.
		add_filter( 'pre_get_document_title', array( __CLASS__, 'pre_get_document_title' ), 99 );
		// Yoast SEO.
		add_filter( 'wpseo_title',    array( __CLASS__, 'plugin_title' ), 99 );
		add_filter( 'wpseo_metadesc', array( __CLASS__, 'plugin_description' ), 99 );
		add_filter( 'wpseo_opengraph_title', array( __CLASS__, 'plugin_title' ), 99 );
		add_filter( 'wpseo_opengraph_desc',  array( __CLASS__, 'plugin_description' ), 99 );
		// RankMath.
		add_filter( 'rank_math/frontend/title',       array( __CLASS__, 'plugin_title' ), 99 );
		add_filter( 'rank_math/frontend/description', array( __CLASS__, 'plugin_description' ), 99 );
		// All-in-One SEO.
		add_filter( 'aioseo_title',       array( __CLASS__, 'plugin_title' ), 99 );
		add_filter( 'aioseo_description', array( __CLASS__, 'plugin_description' ), 99 );

		add_action( 'wp_head', array( __CLASS__, 'render_meta_description' ), 5 );
		add_action( 'wp_head', array( __CLASS__, 'render_og_tags' ), 6 );
		add_action( 'wp_head', array( __CLASS__, 'render_schema' ), 30 );
	}

	/**
	 * Robuste Title-Override (überschreibt komplett den default-WP-Mechanismus).
	 *
	 * @param string $title Existierender Title.
	 * @return string
	 */
	public static function pre_get_document_title( $title ) {
		$ctx = self::context();
		if ( ! $ctx ) { return $title; }
		$built = self::build_title( $ctx );
		if ( '' === $built ) { return $title; }
		// Site-Name anhängen wie WP-Default.
		$sep  = apply_filters( 'document_title_separator', '-' );
		$site = get_bloginfo( 'name' );
		if ( '' !== $site ) { $built .= ' ' . trim( $sep ) . ' ' . $site; }
		return $built;
	}

	/**
	 * Filter-Callback für SEO-Plugins (Yoast, RankMath, AIOSEO).
	 * Liefert nur den eigenen Titel ohne Site-Suffix — Plugins hängen das selbst an.
	 *
	 * @param string $title
	 * @return string
	 */
	public static function plugin_title( $title ) {
		$ctx = self::context();
		if ( ! $ctx ) { return $title; }
		$built = self::build_title( $ctx );
		return '' !== $built ? $built : $title;
	}

	public static function plugin_description( $desc ) {
		$ctx = self::context();
		if ( ! $ctx ) { return $desc; }
		$built = self::build_description( $ctx['data'] );
		return '' !== $built ? $built : $desc;
	}

	/**
	 * Title-Bau-Logik (zentralisiert für alle Filter).
	 *
	 * @param array $ctx
	 * @return string
	 */
	private static function build_title( array $ctx ): string {
		$d    = $ctx['data'];
		$meta = isset( $d['meta'] ) && is_array( $d['meta'] ) ? $d['meta'] : array();

		$title = (string) ( $d['title'] ?? '' );
		$facts = array();

		if ( 'property' === $ctx['kind'] ) {
			$rooms = (int)   ( $meta['rooms'] ?? 0 );
			$area  = (float) ( $meta['area']  ?? 0 );
			$city  = (string) ( $meta['city'] ?? '' );

			if ( $rooms > 0 ) {
				$facts[] = sprintf( _n( '%d Zimmer', '%d Zimmer', $rooms, 'immo-client' ), $rooms );
			}
			if ( $area > 0 ) {
				$dec     = ( floor( $area ) == $area ) ? 0 : 1;
				$facts[] = number_format_i18n( $area, $dec ) . ' m²';
			}
			if ( '' !== $city ) { $facts[] = $city; }
		} else {
			$city    = (string) ( $meta['city'] ?? '' );
			$facts[] = __( 'Bauprojekt', 'immo-client' );
			if ( '' !== $city ) { $facts[] = $city; }
		}

		if ( ! empty( $facts ) ) {
			$title .= ' – ' . implode( ' · ', $facts );
		}
		return $title;
	}

	/**
	 * Liefert die REST-Daten für die aktuelle Detailseite (gecached pro Request).
	 *
	 * @return array{kind: string, data: array}|null
	 */
	private static function context(): ?array {
		$unit_slug    = get_query_var( 'immo_unit_slug' );
		$project_slug = get_query_var( 'immo_project_slug' );

		if ( ! $unit_slug && ! $project_slug ) {
			return null;
		}

		if ( null !== self::$cached ) {
			return array( 'kind' => self::$cached_kind, 'data' => self::$cached );
		}

		$api = new ImmoAPI();
		if ( $unit_slug ) {
			$data = $api->get_property_by_slug( (string) $unit_slug );
			$kind = 'property';
		} else {
			$data = $api->get_project_by_slug( (string) $project_slug );
			$kind = 'project';
		}

		if ( ! is_array( $data ) || empty( $data ) ) {
			return null;
		}

		self::$cached      = $data;
		self::$cached_kind = $kind;

		return array( 'kind' => $kind, 'data' => $data );
	}

	/**
	 * Title-Tag suchmaschinenoptimiert zusammensetzen.
	 *
	 * @param array $parts WP-Title-Parts (title, page, tagline, site).
	 * @return array
	 */
	public static function document_title_parts( $parts ) {
		$ctx = self::context();
		if ( ! $ctx ) { return $parts; }
		$built = self::build_title( $ctx );
		if ( '' !== $built ) { $parts['title'] = $built; }
		return $parts;
	}

	/**
	 * Erkennt aktive SEO-Plugins (Yoast / RankMath / AIOSEO).
	 * Wenn aktiv, geben sie selbst meta-description aus — wir doppeln nicht.
	 */
	private static function seo_plugin_active(): bool {
		return defined( 'WPSEO_VERSION' )
			|| class_exists( 'RankMath' )
			|| defined( 'AIOSEO_VERSION' )
			|| defined( 'AIOSEO_FILE' );
	}

	/**
	 * Meta-Description aus Excerpt oder Beschreibung (max 158 Zeichen).
	 */
	public static function render_meta_description() {
		$ctx = self::context();
		if ( ! $ctx ) { return; }
		// SEO-Plugin aktiv? Dann nicht doppelt ausgeben — der Plugin-Filter (wpseo_metadesc etc.) greift.
		if ( self::seo_plugin_active() ) { return; }
		$desc = self::build_description( $ctx['data'] );
		if ( '' === $desc ) { return; }
		echo "\n" . '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
	}

	/**
	 * Open Graph + Twitter-Card Basis-Tags.
	 */
	public static function render_og_tags() {
		$ctx = self::context();
		if ( ! $ctx ) { return; }

		$d    = $ctx['data'];
		$meta = isset( $d['meta'] ) && is_array( $d['meta'] ) ? $d['meta'] : array();

		$title = (string) ( $d['title'] ?? '' );
		$desc  = self::build_description( $d );
		$url   = self::current_url();
		$img   = '';
		if ( ! empty( $d['featured_image']['url_large'] ) ) {
			$img = (string) $d['featured_image']['url_large'];
		} elseif ( ! empty( $d['featured_image']['url'] ) ) {
			$img = (string) $d['featured_image']['url'];
		}

		echo "\n";
		echo '<meta property="og:type" content="' . ( 'project' === $ctx['kind'] ? 'website' : 'article' ) . '">' . "\n";
		if ( '' !== $title ) { echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n"; }
		if ( '' !== $desc )  { echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n"; }
		if ( '' !== $url )   { echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n"; }
		if ( '' !== $img )   { echo '<meta property="og:image" content="' . esc_url( $img ) . '">' . "\n"; }
		echo '<meta name="twitter:card" content="' . ( '' !== $img ? 'summary_large_image' : 'summary' ) . '">' . "\n";
	}

	/**
	 * Schema.org JSON-LD.
	 */
	public static function render_schema() {
		$ctx = self::context();
		if ( ! $ctx ) { return; }

		if ( 'property' === $ctx['kind'] ) {
			$data = self::build_property_schema( $ctx['data'] );
		} else {
			$data = self::build_project_schema( $ctx['data'] );
		}
		if ( $data ) { self::output_jsonld( $data ); }

		$crumbs = self::build_breadcrumbs( $ctx['data'], $ctx['kind'] );
		if ( $crumbs ) { self::output_jsonld( $crumbs ); }
	}

	/* ============================================================
	   Helpers
	   ============================================================ */

	private static function build_description( array $d ): string {
		$desc = (string) ( $d['excerpt'] ?? '' );
		if ( '' === $desc && ! empty( $d['description'] ) ) {
			$desc = wp_trim_words( wp_strip_all_tags( (string) $d['description'] ), 30, '…' );
		}
		$desc = trim( wp_strip_all_tags( $desc ) );
		// Auf 158 Zeichen kürzen für Google-SERP.
		if ( mb_strlen( $desc ) > 158 ) {
			$desc = rtrim( mb_substr( $desc, 0, 155 ) ) . '…';
		}
		return $desc;
	}

	private static function current_url(): string {
		$scheme = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ) ? 'https' : 'http';
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		return $host ? $scheme . '://' . $host . $uri : '';
	}

	private static function in_language(): string {
		return str_replace( '_', '-', get_locale() );
	}

	private static function output_jsonld( array $data ): void {
		$json = wp_json_encode(
			$data,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
				| JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);
		if ( false === $json ) { return; }
		echo "\n" . '<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>' . "\n";
	}

	private static function map_property_type( string $type ): string {
		$t = strtolower( trim( $type ) );
		if ( in_array( $t, array( 'apartment', 'wohnung', 'penthouse' ), true ) )    { return 'Apartment'; }
		if ( in_array( $t, array( 'house', 'haus', 'villa' ), true ) )               { return 'House'; }
		if ( in_array( $t, array( 'single_family', 'einfamilienhaus' ), true ) )     { return 'SingleFamilyResidence'; }
		return 'Residence';
	}

	private static function map_availability( string $status ): string {
		switch ( $status ) {
			case 'reserved': return 'https://schema.org/LimitedAvailability';
			case 'sold':     return 'https://schema.org/SoldOut';
			case 'rented':   return 'https://schema.org/OutOfStock';
			default:         return 'https://schema.org/InStock';
		}
	}

	private static function build_address( array $meta ): ?array {
		$street  = (string) ( $meta['address'] ?? '' );
		$postal  = (string) ( $meta['postal_code'] ?? '' );
		$city    = (string) ( $meta['city'] ?? '' );
		$region  = (string) ( $meta['region_state'] ?? '' );
		$country = (string) ( $meta['country'] ?? 'AT' );

		if ( '' === $street && '' === $postal && '' === $city ) { return null; }

		$out = array( '@type' => 'PostalAddress' );
		if ( '' !== $street )  { $out['streetAddress']   = $street; }
		if ( '' !== $postal )  { $out['postalCode']      = $postal; }
		if ( '' !== $city )    { $out['addressLocality'] = $city; }
		if ( '' !== $region )  { $out['addressRegion']   = $region; }
		if ( '' !== $country ) { $out['addressCountry']  = $country; }
		return $out;
	}

	private static function build_geo( array $meta ): ?array {
		$lat = (float) ( $meta['lat'] ?? 0 );
		$lng = (float) ( $meta['lng'] ?? 0 );
		if ( 0.0 === $lat || 0.0 === $lng ) { return null; }
		return array( '@type' => 'GeoCoordinates', 'latitude' => $lat, 'longitude' => $lng );
	}

	private static function build_image_list( array $d ): array {
		$images = array();
		$seen   = array();
		$add    = static function ( $img ) use ( &$images, &$seen ) {
			if ( ! is_array( $img ) || empty( $img['url_large'] ) && empty( $img['url'] ) ) { return; }
			$url = ! empty( $img['url_large'] ) ? $img['url_large'] : $img['url'];
			if ( isset( $seen[ $url ] ) ) { return; }
			$seen[ $url ] = true;
			$obj = array( '@type' => 'ImageObject', 'url' => $url );
			if ( ! empty( $img['width'] ) )  { $obj['width']  = (int) $img['width']; }
			if ( ! empty( $img['height'] ) ) { $obj['height'] = (int) $img['height']; }
			$images[] = $obj;
		};
		if ( ! empty( $d['featured_image'] ) ) { $add( $d['featured_image'] ); }
		if ( ! empty( $d['gallery'] ) && is_array( $d['gallery'] ) ) {
			foreach ( $d['gallery'] as $g ) { $add( $g ); }
		}
		return $images;
	}

	private static function build_property_schema( array $d ): ?array {
		$meta = isset( $d['meta'] ) && is_array( $d['meta'] ) ? $d['meta'] : array();

		$main = array( '@type' => self::map_property_type( (string) ( $meta['property_type'] ?? '' ) ) );

		if ( (float) ( $meta['area'] ?? 0 ) > 0 ) {
			$main['floorSize'] = array( '@type' => 'QuantitativeValue', 'value' => (float) $meta['area'], 'unitCode' => 'MTK' );
		}
		if ( (int) ( $meta['rooms'] ?? 0 ) > 0 )      { $main['numberOfRooms']           = (int) $meta['rooms']; }
		if ( (int) ( $meta['bedrooms'] ?? 0 ) > 0 )   { $main['numberOfBedrooms']        = (int) $meta['bedrooms']; }
		if ( (int) ( $meta['bathrooms'] ?? 0 ) > 0 )  { $main['numberOfBathroomsTotal']  = (int) $meta['bathrooms']; }
		if ( (int) ( $meta['built_year'] ?? 0 ) > 0 ) { $main['yearBuilt']               = (int) $meta['built_year']; }

		$addr = self::build_address( $meta );
		if ( $addr ) { $main['address'] = $addr; }
		$geo = self::build_geo( $meta );
		if ( $geo )  { $main['geo']     = $geo; }

		$additional = array();
		$ec = (string) ( $meta['energy_class'] ?? '' );
		if ( '' !== $ec ) { $additional[] = array( '@type' => 'PropertyValue', 'name' => 'energyClass', 'value' => $ec ); }
		if ( (float) ( $meta['energy_hwb'] ?? 0 ) > 0 ) { $additional[] = array( '@type' => 'PropertyValue', 'name' => 'HWB', 'value' => (float) $meta['energy_hwb'], 'unitText' => 'kWh/m²a' ); }
		$h = (string) ( $meta['heating'] ?? '' );
		if ( '' !== $h ) { $additional[] = array( '@type' => 'PropertyValue', 'name' => 'heating', 'value' => $h ); }
		if ( ! empty( $additional ) ) { $main['additionalProperty'] = $additional; }

		$url = self::current_url();
		$out = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'RealEstateListing',
			'@id'        => $url,
			'url'        => $url,
			'name'       => (string) ( $d['title'] ?? '' ),
			'inLanguage' => self::in_language(),
			'mainEntity' => $main,
		);
		if ( ! empty( $d['created_at'] ) ) { $out['datePosted'] = (string) $d['created_at']; }

		$desc = self::build_description( $d );
		if ( '' !== $desc ) { $out['description'] = $desc; }

		$imgs = self::build_image_list( $d );
		if ( ! empty( $imgs ) ) { $out['image'] = $imgs; }

		// Offers.
		$mode   = (string) ( $meta['mode'] ?? 'sale' );
		$status = (string) ( $meta['status'] ?? 'available' );
		$price  = (float)  ( $meta['price'] ?? 0 );
		$rent   = (float)  ( $meta['rent']  ?? 0 );
		$avail  = (string) ( $meta['available_from'] ?? '' );

		$offers = array();
		if ( in_array( $mode, array( 'sale', 'both' ), true ) && $price > 0 ) {
			$offer = array(
				'@type'              => 'Offer',
				'priceSpecification' => array( '@type' => 'PriceSpecification', 'minPrice' => $price, 'priceCurrency' => 'EUR' ),
				'availability'       => self::map_availability( $status ),
			);
			if ( '' !== $avail ) { $offer['availabilityStarts'] = $avail; }
			$offers[] = $offer;
		}
		if ( in_array( $mode, array( 'rent', 'both' ), true ) && $rent > 0 ) {
			$offers[] = array(
				'@type'              => 'Offer',
				'priceSpecification' => array( '@type' => 'UnitPriceSpecification', 'price' => $rent, 'priceCurrency' => 'EUR', 'unitText' => 'MONTH' ),
				'availability'       => self::map_availability( $status ),
			);
		}
		if ( ! empty( $offers ) ) { $out['offers'] = 1 === count( $offers ) ? $offers[0] : $offers; }

		return $out;
	}

	private static function build_project_schema( array $d ): ?array {
		$meta = isset( $d['meta'] ) && is_array( $d['meta'] ) ? $d['meta'] : array();

		$ac = array( '@type' => 'ApartmentComplex', 'name' => (string) ( $d['title'] ?? '' ) );

		$addr = self::build_address( $meta );
		if ( $addr ) { $ac['address'] = $addr; }
		$geo = self::build_geo( $meta );
		if ( $geo )  { $ac['geo']     = $geo; }

		$total = (int) ( $d['unit_stats']['total'] ?? 0 );
		if ( $total > 0 ) { $ac['numberOfAccommodationUnits'] = $total; }

		$imgs = self::build_image_list( $d );
		if ( ! empty( $imgs ) ) { $ac['image'] = $imgs; }

		$url = self::current_url();
		$out = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'ApartmentComplex',
			'@id'        => $url,
			'url'        => $url,
			'name'       => (string) ( $d['title'] ?? '' ),
			'inLanguage' => self::in_language(),
		);
		// Vermeide doppelte name-/address-/geo-Felder: einbettung statt mainEntity bei ApartmentComplex.
		$out = array_merge( $out, $ac );

		$desc = self::build_description( $d );
		if ( '' !== $desc ) { $out['description'] = $desc; }

		return $out;
	}

	private static function build_breadcrumbs( array $d, string $kind ): ?array {
		$items   = array();
		$home    = home_url( '/' );
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => 1,
			'name'     => __( 'Startseite', 'immo-client' ),
			'item'     => $home,
		);
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => 2,
			'name'     => 'project' === $kind ? __( 'Bauprojekte', 'immo-client' ) : __( 'Immobilien', 'immo-client' ),
		);
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => 3,
			'name'     => (string) ( $d['title'] ?? '' ),
			'item'     => self::current_url(),
		);
		return array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		);
	}
}

ImmoSEO::init();
