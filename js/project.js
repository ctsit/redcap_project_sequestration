$(document).ready(function() {
    var settings = projectSequestrationState;
    var $status = $('.sequestered');

    // Changing project status icon.
    $status.parent().css('color', '#800000');
    $status.siblings('.glyphicon').each(function() {
        this.className = '';
        $(this).addClass('glyphicon').addClass('glyphicon-' + settings.icon);
    });

    // Placing sequestered project warning message.
    if (typeof settings.warningMsg !== 'undefined') {
        $('#subheader').after(settings.warningMsg);
    }

    // Disabling project status change buttons.
    function disableStatusButton($button) {
        $button.prop('disabled', 'disabled');
        $button.css('opacity', '0.5').css('background', 'none');
        $button.removeAttr('onclick');
    }

    if ($('.chklisthdr.delete-target').length === 0) {
        var $button = $('button[onclick="btnMoveToProd()"]');
        if ($button.length !== 0) {
            disableStatusButton($button);
        }

        return;
    }

    var buttons = {
        0: $('button[onclick="MoveToDev(0,0)"]').parent().parent(),
        1: $('button[onclick="btnMoveToProd()"]').parent().parent(),
        3: $('#row_archive')
    };

    if (typeof settings.oldStatus !== 'undefined') {
        buttons[settings.oldStatus].remove();
        delete buttons[settings.OldStatus];
    }

    $.each(buttons, function(i, $button) {
        disableStatusButton($button.find('button'));
    });
});
