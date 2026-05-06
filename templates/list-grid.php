<?php
/**
 * Generic Grid Template für Immobilien oder Projekte.
 *
 * Erwartet $items = Array von Property- oder Project-Response-Objekten
 * im Format der ImmoManager-REST-API.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="immo-item-grid">
    <?php foreach ($items as $item) {
        include IMMO_CLIENT_PATH . 'templates/partial-property-card.php';
    } ?>
</div>
