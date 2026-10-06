<?php
/**
 * Globale Render-Helper für Badges (z. B. „Provisionsfrei").
 *
 * Wird von templates/* eingebunden.
 *
 * @package ImmoClient
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'immo_client_floor_label' ) ) {
	/**
	 * Liefert eine sprechende Etagen-Beschriftung.
	 * - Leerer Wert / kein Stockwerk gesetzt → '–'
	 * - 0  → 'EG' (Erdgeschoss)
	 * - >0 → '<n>. OG'
	 * - <0 → '<|n|>. UG' (Untergeschoss)
	 *
	 * @param mixed $floor Roher Etagen-Wert aus REST (string/int).
	 *
	 * @return string Beschriftung.
	 */
	function immo_client_floor_label( $floor ) {
		if ( $floor === '' || $floor === null ) {
			return '–';
		}
		$n = (int) $floor;
		if ( 0 === $n ) {
			return 'EG';
		}
		if ( $n < 0 ) {
			return abs( $n ) . '. UG';
		}
		return $n . '. OG';
	}
}

if ( ! function_exists( 'immo_client_render_cf_badge' ) ) {
	/**
	 * Rendert das „Provisionsfrei"-Badge, wenn das Property-Meta-Array
	 * `commission_free === true` enthält UND es sich um eine Kauf-Immobilie
	 * handelt (`mode in [sale, both]`). Bei Miete erscheint kein Badge.
	 *
	 * @param array  $meta    Property-Meta-Array (REST-Format).
	 * @param string $variant 'patch' (Sticker auf Bild) | 'icon' (Inline-Pill).
	 *
	 * @return void
	 */
	function immo_client_render_cf_badge( $meta, $variant = 'patch' ) {
		$mode = isset( $meta['mode'] ) ? (string) $meta['mode'] : 'sale';
		$show = ! empty( $meta['commission_free'] )
			&& in_array( $mode, array( 'sale', 'both' ), true );

		if ( ! $show ) {
			return;
		}

		$label = isset( $meta['commission_free_label'] ) && $meta['commission_free_label'] !== ''
			? (string) $meta['commission_free_label']
			: __( 'Provisionsfrei', 'immo-client' );

		$class = 'icon' === $variant ? 'immo-cf-icon' : 'immo-cf-patch';

		printf(
			'<span class="immo-cf-badge %1$s" aria-label="%2$s" title="%2$s">%3$s</span>',
			esc_attr( $class ),
			esc_attr( $label ),
			esc_html( $label )
		);
	}
}

if ( ! function_exists( 'immo_client_energy_bits' ) ) {
	/**
	 * Kompakte Energieausweis-Angabe für Listing-Cards: "HWB 68 · EEB 118".
	 *
	 * EAVG § 3 (Novelle 1.7.2026): Heizwärmebedarf (HWB) und Endenergiebedarf (EEB)
	 * gehören in jedes Inserat. fGEE nur noch als Übergangsregel bei Altausweisen
	 * (wenn kein EEB vorliegt). Werte kommen vom ImmoManager ab Version 1.4.0
	 * (meta.energy_hwb, meta.energy_eeb, meta.energy_fgee); ältere Manager liefern
	 * kein EEB – dann erscheint nur HWB.
	 *
	 * @param array $meta REST-meta-Array einer Immobilie.
	 *
	 * @return string Leer, wenn keine Werte vorhanden.
	 */
	function immo_client_energy_bits( $meta ) {
		$bits = array();
		$hwb  = isset( $meta['energy_hwb'] ) ? (float) $meta['energy_hwb'] : 0;
		$eeb  = isset( $meta['energy_eeb'] ) ? (float) $meta['energy_eeb'] : 0;
		$fgee = isset( $meta['energy_fgee'] ) ? (float) $meta['energy_fgee'] : 0;
		if ( $hwb > 0 ) {
			$bits[] = 'HWB ' . number_format_i18n( $hwb, 0 );
		}
		if ( $eeb > 0 ) {
			$bits[] = 'EEB ' . number_format_i18n( $eeb, 0 );
		} elseif ( $fgee > 0 ) {
			$bits[] = 'fGEE ' . number_format_i18n( $fgee, 2 );
		}
		return implode( ' · ', $bits );
	}
}

if ( ! function_exists( 'immo_client_energy_rows' ) ) {
	/**
	 * Energieausweis-Zeilen (Label, Wert) für Detailansichten.
	 *
	 * @param array $meta REST-meta-Array einer Immobilie.
	 *
	 * @return array<int, array{0: string, 1: string}>
	 */
	function immo_client_energy_rows( $meta ) {
		$rows  = array();
		$class = isset( $meta['energy_class'] ) ? (string) $meta['energy_class'] : '';
		$hwb   = isset( $meta['energy_hwb'] ) ? (float) $meta['energy_hwb'] : 0;
		$eeb   = isset( $meta['energy_eeb'] ) ? (float) $meta['energy_eeb'] : 0;
		$fgee  = isset( $meta['energy_fgee'] ) ? (float) $meta['energy_fgee'] : 0;
		if ( $class !== '' ) {
			$rows[] = array( __( 'Energieeffizienzklasse', 'immo-client' ), $class );
		}
		if ( $hwb > 0 ) {
			$rows[] = array( __( 'Heizwärmebedarf (HWB)', 'immo-client' ), number_format_i18n( $hwb, 1 ) . ' kWh/m²a' );
		}
		if ( $eeb > 0 ) {
			$rows[] = array( __( 'Endenergiebedarf (EEB)', 'immo-client' ), number_format_i18n( $eeb, 1 ) . ' kWh/m²a' );
		} elseif ( $fgee > 0 ) {
			$rows[] = array( __( 'fGEE (Altausweis)', 'immo-client' ), number_format_i18n( $fgee, 2 ) );
		}
		return $rows;
	}
}
