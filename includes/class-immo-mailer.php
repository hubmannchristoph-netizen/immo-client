<?php
/**
 * Zentraler Mail-Versender mit Client-Branding (Logo + Primärfarbe).
 *
 * Wird von Property- und Projekt-Anfragen genutzt, damit alle E-Mails
 * konsistent im Branding der Client-Site versendet werden – auch dann,
 * wenn die Daten ursprünglich vom ImmoManager kommen.
 */

if (!defined('ABSPATH')) {
    exit;
}

class ImmoMailer {

    /**
     * Hauptversand-Funktion.
     *
     * @param string|array $to       Empfänger.
     * @param string       $subject  Betreff.
     * @param array        $rows     Zwei-Spalten-Daten für die Tabelle: [['Label', 'Wert'], …].
     * @param string       $intro    Optional: Einleitung über der Tabelle.
     * @param string       $message  Optional: lange Nachricht (z.B. inquirer_message).
     * @param array        $opts     ['cta_url'=>..., 'cta_label'=>..., 'reply_to'=>..., 'heading'=>...].
     * @return bool
     */
    public static function send($to, $subject, $rows, $intro = '', $message = '', $opts = array()) {
        $body    = self::render_template($subject, $intro, $rows, $message, $opts);
        $headers = self::build_headers($opts);

        add_filter('wp_mail_content_type', array(__CLASS__, 'set_html'));
        $result = wp_mail($to, $subject, $body, $headers);
        remove_filter('wp_mail_content_type', array(__CLASS__, 'set_html'));

        return $result;
    }

    public static function set_html() {
        return 'text/html';
    }

    private static function build_headers($opts) {
        $sender_name  = (string) get_option('immo_email_sender_name', get_bloginfo('name'));
        $sender_email = (string) get_option('immo_email_sender_email', '');
        if ($sender_email === '' || !is_email($sender_email)) {
            $domain = wp_parse_url(home_url(), PHP_URL_HOST);
            if (strpos((string) $domain, 'www.') === 0) {
                $domain = substr((string) $domain, 4);
            }
            $sender_email = 'noreply@' . $domain;
        }

        $headers = array('From: ' . $sender_name . ' <' . $sender_email . '>');
        if (!empty($opts['reply_to']) && is_email($opts['reply_to'])) {
            $reply_name = !empty($opts['reply_to_name']) ? $opts['reply_to_name'] : '';
            $headers[]  = 'Reply-To: ' . ($reply_name ? $reply_name . ' <' . $opts['reply_to'] . '>' : $opts['reply_to']);
        }
        return $headers;
    }

    /**
     * Rendert das HTML-Template einer Mail.
     */
    private static function render_template($subject, $intro, $rows, $message, $opts) {
        $primary    = ImmoStyles::resolve_color('primary');
        $secondary  = ImmoStyles::resolve_color('secondary');
        $bg         = '#f3f4f6';
        $heading    = isset($opts['heading']) ? (string) $opts['heading'] : $subject;

        $logo_id  = (int) get_option('immo_email_logo_id', 0);
        $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : '';

        $footer_note = (string) get_option('immo_email_footer_note', '');
        if ($footer_note === '') {
            $footer_note = sprintf(
                /* translators: %s = Site-Name */
                'Diese E-Mail wurde automatisch von %s gesendet.',
                get_bloginfo('name')
            );
        }

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title><?php echo esc_html($subject); ?></title>
        </head>
        <body style="background-color:<?php echo esc_attr($bg); ?>;margin:0;padding:24px 12px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#111827;line-height:1.6;">
            <div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.06);border:1px solid #e5e7eb;">

                <?php if ($logo_url) : ?>
                    <div style="padding:22px 24px;border-bottom:1px solid #f3f4f6;background:#ffffff;">
                        <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" style="max-height:60px;max-width:240px;height:auto;display:inline-block;">
                    </div>
                <?php else : ?>
                    <div style="padding:22px 24px;border-bottom:1px solid #f3f4f6;background:#ffffff;font-weight:700;font-size:18px;color:<?php echo esc_attr($primary); ?>;">
                        <?php echo esc_html(get_bloginfo('name')); ?>
                    </div>
                <?php endif; ?>

                <div style="height:4px;background:linear-gradient(90deg,<?php echo esc_attr($primary); ?>,<?php echo esc_attr($secondary); ?>);"></div>

                <div style="padding:28px 24px;">
                    <h2 style="color:<?php echo esc_attr($primary); ?>;margin:0 0 18px;font-size:20px;"><?php echo esc_html($heading); ?></h2>

                    <?php if ($intro) : ?>
                        <p style="margin:0 0 18px;"><?php echo wp_kses_post($intro); ?></p>
                    <?php endif; ?>

                    <?php if (!empty($rows) && is_array($rows)) : ?>
                        <table style="width:100%;border-collapse:collapse;font-size:15px;margin-bottom:22px;">
                            <?php foreach ($rows as $row) : if (!is_array($row) || count($row) < 2) continue; ?>
                                <tr>
                                    <th style="text-align:left;padding:10px 8px 10px 0;border-bottom:1px solid #f3f4f6;width:35%;color:#6b7280;font-weight:500;"><?php echo esc_html($row[0]); ?></th>
                                    <td style="padding:10px 0 10px 8px;border-bottom:1px solid #f3f4f6;font-weight:500;"><?php echo wp_kses_post((string) $row[1]); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>

                    <?php if ($message) : ?>
                        <h3 style="font-size:15px;margin:0 0 8px;color:#374151;">Nachricht</h3>
                        <div style="background:#f9fafb;padding:14px 16px;border-radius:6px;border:1px solid #e5e7eb;font-size:15px;white-space:pre-wrap;margin-bottom:22px;"><?php echo esc_html($message); ?></div>
                    <?php endif; ?>

                    <?php if (!empty($opts['cta_url']) && !empty($opts['cta_label'])) : ?>
                        <div style="text-align:center;margin-top:8px;">
                            <a href="<?php echo esc_url($opts['cta_url']); ?>" style="display:inline-block;background:<?php echo esc_attr($primary); ?>;color:#ffffff;padding:12px 26px;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;">
                                <?php echo esc_html($opts['cta_label']); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="background:#f9fafb;padding:14px 18px;text-align:center;font-size:12px;color:#6b7280;border-top:1px solid #e5e7eb;">
                    <?php echo esc_html($footer_note); ?>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
