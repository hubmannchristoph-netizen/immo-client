<?php
/**
 * Template-Part: Lightbox-Container für Wohneinheiten.
 *
 * Wird einmal pro Seite ausgegeben (Singleton via static im Renderer).
 * Wenn `single-project.php` bereits seinen eigenen Container rendert,
 * sollte dieser Part nicht zusätzlich ausgegeben werden.
 *
 * @package ImmoClient
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="immo-unit-lightbox" class="immo-unit-lightbox" role="dialog" aria-modal="true" aria-hidden="true" aria-label="Wohneinheit Quick-Info">
	<div class="immo-unit-lightbox-backdrop" data-immo-lightbox-close></div>
	<div class="immo-unit-lightbox-dialog" role="document">
		<button type="button" class="immo-unit-lightbox-close" data-immo-lightbox-close aria-label="Schließen">×</button>
		<div class="immo-unit-lightbox-content"></div>
		<div class="immo-unit-quick-actions" id="immo-unit-lightbox-actions">
			<a href="#" class="immo-unit-details-btn" id="immo-unit-lightbox-details-btn">Zur Detailseite →</a>
		</div>
	</div>
</div>
