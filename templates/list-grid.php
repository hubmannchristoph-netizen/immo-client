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

        $price_display = isset($meta['price_formatted']) && $meta['price_formatted'] ? $meta['price_formatted'] : '';
        $rent_display  = isset($meta['rent_formatted'])  && $meta['rent_formatted']  ? $meta['rent_formatted']  : '';

        $area  = isset($meta['area'])  ? (float) $meta['area']  : 0;
        $rooms = isset($meta['rooms']) ? (int)   $meta['rooms'] : 0;

        $detail_slug = isset($item['slug']) ? $item['slug'] : '';
        $detail_url  = home_url( ($is_project ? '/bauprojekt/' : '/immobilie/') . $detail_slug );
    ?>
        <div class="immo-card" style="border: 1px solid #ddd; border-radius: 8px; overflow: hidden; background: #fff;">
            <?php if ($image && !empty($image['url_thumbnail'])) : ?>
                <div class="immo-card-image">
                    <img src="<?php echo esc_url($image['url_thumbnail']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" style="width: 100%; height: 200px; object-fit: cover;">
                </div>
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
                <?php endif; ?>

                <a href="<?php echo esc_url($detail_url); ?>" class="button" style="display: inline-block; padding: 8px 16px; background: var(--immo-primary, #0073aa); color: #fff; text-decoration: none; border-radius: 4px;">
                    Details ansehen
                </a>
            </div>
        </div>
    <?php endforeach; ?>
</div>
