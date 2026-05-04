<?php
/**
 * Shortcode-Ausgabe für ein einzelnes Bauprojekt: [immo_project id="…"].
 *
 * Erwartet:
 *   $project       = Project-Response
 *   $project_units = Array der Einheiten (bereits separat geladen)
 */

if (!defined('ABSPATH')) {
    exit;
}

$meta         = isset($project['meta']) ? $project['meta'] : array();
$hero         = isset($project['featured_image']) ? $project['featured_image'] : null;
$unit_stats   = isset($project['unit_stats']) ? $project['unit_stats'] : array();

$city         = isset($meta['city'])               ? $meta['city']               : '';
$state_label  = isset($meta['region_state_label']) ? $meta['region_state_label'] : '';
$location     = trim(implode(', ', array_filter(array($city, $state_label))));

$detail_url   = home_url('/bauprojekt/' . (isset($project['slug']) ? $project['slug'] : ''));
?>
<div class="immo-project-card" style="border:1px solid #ddd;border-radius:8px;overflow:hidden;background:#fff;">
    <?php if ($hero && !empty($hero['url_large'])) : ?>
        <img src="<?php echo esc_url($hero['url_large']); ?>" alt="<?php echo esc_attr($hero['alt'] ?: $project['title']); ?>" style="width:100%;height:300px;object-fit:cover;">
    <?php endif; ?>
    <div style="padding:20px;">
        <h3 style="margin:0 0 6px;"><?php echo esc_html($project['title']); ?></h3>
        <?php if ($location) : ?>
            <p style="color:#666;margin:0 0 12px;"><?php echo esc_html($location); ?></p>
        <?php endif; ?>

        <?php if (!empty($unit_stats['total'])) : ?>
            <p style="margin:0 0 12px;font-size:.95em;">
                <?php
                $available = isset($unit_stats['available']) ? (int) $unit_stats['available'] : 0;
                $total     = (int) $unit_stats['total'];
                printf(esc_html('%1$d von %2$d Einheiten verfügbar'), $available, $total);
                ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($project['excerpt'])) : ?>
            <p style="margin:0 0 16px;"><?php echo esc_html($project['excerpt']); ?></p>
        <?php endif; ?>

        <a href="<?php echo esc_url($detail_url); ?>" class="button" style="display:inline-block;padding:8px 16px;background:var(--immo-primary,#0073aa);color:#fff;text-decoration:none;border-radius:4px;">
            Zum Projekt
        </a>

        <div class="immo-project-contact" style="margin-top:24px;">
            <h4 style="margin:0 0 10px;font-size:1em;">Interesse?</h4>
            <?php include IMMO_CLIENT_PATH . 'templates/project-inquiry-form.php'; ?>
        </div>
    </div>
</div>
