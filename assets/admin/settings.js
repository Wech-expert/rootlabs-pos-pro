jQuery(document).ready(function ($) {
    var mediaFrame = null;

    function updateRemoveButton() {
        var attachmentId = parseInt($('#mx_pos_ticket_logo_attachment_id').val(), 10) || 0;
        if (attachmentId > 0) {
            $('#mx-pos-remove-logo').show();
        } else {
            $('#mx-pos-remove-logo').hide();
        }
    }

    $('#mx-pos-select-logo').on('click', function (e) {
        e.preventDefault();

        if (mediaFrame) {
            mediaFrame.open();
            return;
        }

        mediaFrame = wp.media({
            title: 'Select Logo',
            button: {
                text: 'Use this logo'
            },
            library: {
                type: 'image'
            },
            multiple: false
        });

        mediaFrame.on('select', function () {
            var attachment = mediaFrame.state().get('selection').first().toJSON();
            $('#mx_pos_ticket_logo_attachment_id').val(attachment.id);

            var previewUrl = (attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url)
                ? attachment.sizes.medium.url
                : attachment.url;

            var filename = attachment.filename || '';

            $('#mx-pos-logo-preview').html(
                '<img src="' + previewUrl + '" alt="' + 'Logo preview' + '" style="max-width:150px;height:auto;display:block;margin:0 auto;" />' +
                (filename ? '<p class="description" style="margin-top:4px;">' + filename + '</p>' : '')
            );

            updateRemoveButton();
        });

        mediaFrame.open();
    });

    $('#mx-pos-remove-logo').on('click', function (e) {
        e.preventDefault();
        $('#mx_pos_ticket_logo_attachment_id').val('0');
        $('#mx-pos-logo-preview').html('<p class="description">No logo selected.</p>');
        updateRemoveButton();
    });

    updateRemoveButton();
});
