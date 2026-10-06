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
<div class="immo-item-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
    <?php foreach ($items as $item) :
        $meta  = isset($item['meta']) && is_array($item['meta']) ? $item['meta'] : array();
        $image = !empty($item['featured_image']) && is_array($item['featured_image']) ? $item['featured_image'] : null;

        // Property vs. Project erkennen: Properties haben z. B. meta.property_type.
        $is_project = isset($meta['project_status']) && !isset($meta['property_type']);

        // Preisregel des Managers: bei zugeordneten Wohneinheiten nur "ab <guenstigste Einheit>",
        // nie den Property-Gesamtpreis (identisch zu templates/parts/property-card.php im Manager).
        $unit_stats = isset($item['unit_stats']) && is_array($item['unit_stats']) ? $item['unit_stats'] : array();
        $has_units  = !empty($meta['has_units']) || (int) ($unit_stats['total'] ?? 0) > 0;
        if ($has_units) {
            $price_display = !empty($unit_stats['min_price_formatted']) ? 'ab ' . $unit_stats['min_price_formatted'] : '';
            $rent_display  = (!$price_display && !empty($unit_stats['min_rent_formatted'])) ? 'ab ' . $unit_stats['min_rent_formatted'] . ' / Monat' : '';
        } else {
            $price_display = isset($meta['price_formatted']) && $meta['price_formatted'] ? $meta['price_formatted'] : '';
            $rent_display  = isset($meta['rent_formatted'])  && $meta['rent_formatted']  ? $meta['rent_formatted']  : '';
        }

        $area  = isset($meta['area'])  ? (float) $meta['area']  : 0;
        $rooms = isset($meta['rooms']) ? (int)   $meta['rooms'] : 0;

        $detail_slug = isset($item['slug']) ? $item['slug'] : '';
        $detail_url  = home_url( ($is_project ? '/bauprojekt/' : '/immobilie/') . $detail_slug );
    ?>
        <div class="immo-card" style="border: 1px solid #ddd; border-radius: 8px; overflow: hidden; background: #fff;">
            <?php if ($image && !empty($image['url_thumbnail'])) : ?>
                <div class="immo-card-image" style="position: relative;">
                    <img src="<?php echo esc_url($image['url_thumbnail']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" style="width: 100%; height: 200px; object-fit: cover;">
                    <?php if ( ! $is_project ) { immo_client_render_cf_badge( $meta, 'patch' ); } ?>
                </div>
            <?php else : ?>
                <?php if ( ! $is_project && ! empty( $meta['commission_free'] ) ) : ?>
                    <div class="immo-card-image" style="position: relative; min-height: 40px; padding: 8px;">
                        <?php immo_client_render_cf_badge( $meta, 'patch' ); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="immo-card-content" style="padding: 15px;">
                <h3 style="margin-top: 0;"><?php echo esc_html($item['title']); ?></h3>

                <?php if (!$is_project && ($price_display || $rent_display)) : ?>
                    <p class="price" style="font-weight: bold; color: #222;">
                        <?php
                        $parts = array_filter(array($price_display, $rent_display));
                        echo esc_html(implode(' / ', $parts));
                        ?>
                    </p>
                <?php endif; ?>

                <?php if (!$is_project) : ?>
                    <div class="meta" style="font-size: 0.9em; color: #666; margin-bottom: 15px;">
                        <?php if ($area > 0) echo esc_html(number_format_i18n($area, 0)) . ' m²'; ?>
                        <?php if ($area > 0 && $rooms > 0) echo ' | '; ?>
                        <?php if ($rooms > 0) echo esc_html($rooms) . ' Zimmer'; ?>
                    </div>
                    <?php
                    // Energieausweis-Pflichtangaben (EAVG § 3, seit 1.7.2026) auch auf der Card.
                    $energy_class = isset($meta['energy_class']) ? (string) $meta['energy_class'] : '';
                    $energy_bits  = immo_client_energy_bits($meta);
                    if ($energy_class || $energy_bits) : ?>
                        <div class="meta immo-card-energy" style="font-size: 0.85em; color: #666; margin: -8px 0 15px;" title="Energieausweis (kWh/m²a)">
                            <?php if ($energy_class) echo '⚡ ' . esc_html($energy_class); ?>
                            <?php if ($energy_class && $energy_bits) echo ' · '; ?>
                            <?php echo esc_html($energy_bits); ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <a href="<?php echo esc_url($detail_url); ?>" class="button" style="display: inline-block; padding: 8px 16px; background: var(--immo-primary, #0073aa); color: #fff; text-decoration: none; border-radius: 4px;">
                    Details ansehen
                </a>
            </div>
        </div>
    <?php endforeach; ?>
</div>
