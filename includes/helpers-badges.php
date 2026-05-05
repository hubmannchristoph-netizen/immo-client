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
