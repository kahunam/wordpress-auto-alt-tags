/**
 * Media Library column — per-image regenerate button handler.
 *
 * @package AutoAltTags
 */
(function($) {
    'use strict';

    $(document).on('click', '.auto-alt-regenerate', function(e) {
        e.preventDefault();

        var $link = $(this);
        var id    = $link.data('id');
        var nonce = $link.data('nonce');

        $link.text('Generating\u2026').prop('disabled', true);

        $.post(autoAltMedia.ajaxurl, {
            action: 'auto_alt_regenerate_single',
            id:     id,
            nonce:  nonce
        }, function(response) {
            if (response.success) {
                var $cell = $link.closest('td');
                var text  = response.data.alt_text;
                var display = text.length > 60 ? text.substring(0, 60) + '\u2026' : text;
                $cell.find('span').first()
                    .css('color', '#1e8a44')
                    .text(display);
                $link.text('Regenerate').prop('disabled', false);
            } else {
                alert('Error: ' + response.data);
                $link.text('Regenerate').prop('disabled', false);
            }
        });
    });

})(jQuery);
