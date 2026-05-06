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
            <?php foreach ($items as $item) : ?>
                <li class="splide__slide">
                    <?php include IMMO_CLIENT_PATH . 'templates/partial-property-card.php'; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
