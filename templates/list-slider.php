<?php
/**
 * Slider-Template für [immo_list ids="..." layout="slider"].
 *
 * Erwartet $items = Array aus REST-API (Properties oder Projekte).
 * Erwartet $slider_config = array mit per_page, per_page_md, per_page_sm,
 *          gap, autoplay, loop.
 */

if (!defined('ABSPATH')) {
    exit;
}

$slider_config = isset($slider_config) && is_array($slider_config) ? $slider_config : array();
$cfg = wp_parse_args($slider_config, array(
    'per_page'    => 3,
    'per_page_md' => 2,
    'per_page_sm' => 1,
    'gap'         => '1.5rem',
    'autoplay'    => 'no',
    'loop'        => 'yes',
));
?>
<div class="immo-list-slider splide"
     role="region"
     aria-label="Immobilien-Slider"
     data-per-page="<?php echo (int) $cfg['per_page']; ?>"
     data-per-page-md="<?php echo (int) $cfg['per_page_md']; ?>"
     data-per-page-sm="<?php echo (int) $cfg['per_page_sm']; ?>"
     data-gap="<?php echo esc_attr((string) $cfg['gap']); ?>"
     data-autoplay="<?php echo esc_attr($cfg['autoplay'] === 'yes' ? 'yes' : 'no'); ?>"
     data-loop="<?php echo esc_attr($cfg['loop'] === 'no' ? 'no' : 'yes'); ?>">
    <div class="splide__track">
        <ul class="splide__list">
            <?php foreach ($items as $item) :
                $meta  = isset($item['meta']) && is_array($item['meta']) ? $item['meta'] : array();
                $image = !empty($item['featured_image']) && is_array($item['featured_image']) ? $item['featured_image'] : null;

                $is_project = isset($meta['project_status']) && !isset($meta['property_type']);

                $price_display = isset($meta['price_formatted']) && $meta['price_formatted'] ? $meta['price_formatted'] : '';
                $rent_display  = isset($meta['rent_formatted'])  && $meta['rent_formatted']  ? $meta['rent_formatted']  : '';

                $area  = isset($meta['area'])  ? (float) $meta['area']  : 0;
                $rooms = isset($meta['rooms']) ? (int)   $meta['rooms'] : 0;

                $detail_slug = isset($item['slug']) ? $item['slug'] : '';
                $detail_url  = home_url(($is_project ? '/bauprojekt/' : '/immobilie/') . $detail_slug);
            ?>
                <li class="splide__slide">
                    <div class="immo-card" style="border: 1px solid #ddd; border-radius: 8px; overflow: hidden; background: #fff; width: 100%;">
                        <?php if ($image && !empty($image['url_thumbnail'])) : ?>
                            <div class="immo-card-image" style="position: relative;">
                                <img src="<?php echo esc_url($image['url_thumbnail']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" style="width: 100%; height: 200px; object-fit: cover;">
                                <?php if (!$is_project) { immo_client_render_cf_badge($meta, 'patch'); } ?>
                            </div>
                        <?php else : ?>
                            <?php if (!$is_project && !empty($meta['commission_free'])) : ?>
                                <div class="immo-card-image" style="position: relative; min-height: 40px; padding: 8px;">
                                    <?php immo_client_render_cf_badge($meta, 'patch'); ?>
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
                            <?php endif; ?>

                            <a href="<?php echo esc_url($detail_url); ?>" class="button" style="display: inline-block; padding: 8px 16px; background: var(--immo-primary, #0073aa); color: #fff; text-decoration: none; border-radius: 4px;">
                                Details ansehen
                            </a>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
