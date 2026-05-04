jQuery(document).ready(function($) {

    // Filter-Bar -> Liste neu laden
    $(document).on('change', '.immo-filters select', function() {
        var $form    = $(this).closest('form');
        var $block   = $form.closest('.immo-block-list');
        var $wrapper = $block.length
            ? $block.find('.immo-item-grid-wrapper')
            : $('.immo-item-grid-wrapper');

        $wrapper.addClass('immo-loading').css('opacity', 0.5);

        $.ajax({
            url: immo_ajax.ajax_url,
            type: 'POST',
            data: {
                action:  'immo_filter_list',
                nonce:   immo_ajax.filter_nonce || immo_ajax.nonce,
                filters: $form.serialize()
            },
            success: function(response) {
                if (response && response.success) {
                    $wrapper.html(response.data.html);
                }
            },
            complete: function() {
                $wrapper.removeClass('immo-loading').css('opacity', 1);
            }
        });
    });

    // Anfrage-Formular -> WP-AJAX (Property an Manager, Projekt direkt per Mail im Client)
    $(document).on('submit', '.immo-inquiry-form', function(e) {
        e.preventDefault();

        var $form    = $(this);
        var $submit  = $form.find('.immo-inquiry-submit');
        var $message = $form.find('.immo-inquiry-message');
        var isProject = $form.hasClass('immo-project-inquiry-form');

        $message.removeClass('is-success is-error').text('');
        $submit.prop('disabled', true).text('Wird gesendet …');

        var data = $form.serializeArray();
        data.push({
            name: 'action',
            value: isProject ? 'immo_submit_project_inquiry' : 'immo_submit_inquiry'
        });
        data.push({
            name: 'nonce',
            value: isProject ? immo_ajax.project_inquiry_nonce : immo_ajax.inquiry_nonce
        });

        $.ajax({
            url:  immo_ajax.ajax_url,
            type: 'POST',
            data: $.param(data)
        }).done(function(response) {
            if (response && response.success) {
                $message.addClass('is-success').text(response.data.message || 'Anfrage gesendet.');
                $form.find('input[type=text], input[type=email], input[type=tel], textarea').val('');
                $form.find('input[type=checkbox]').prop('checked', false);
            } else {
                var msg = (response && response.data && response.data.message) ? response.data.message : 'Anfrage fehlgeschlagen.';
                $message.addClass('is-error').text(msg);
            }
        }).fail(function(xhr) {
            var msg = 'Fehler beim Senden.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                msg = xhr.responseJSON.data.message;
            }
            $message.addClass('is-error').text(msg);
        }).always(function() {
            $submit.prop('disabled', false).text('Anfrage senden');
        });
    });
});
