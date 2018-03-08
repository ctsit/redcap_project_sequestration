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

    if (typeof settings.oldStatus === 'undefined') {
        return;
    }

    var $table = $('.chklisthdr.delete-target');
    if ($table.length === 0) {
        return;
    }

    // Removing Project Management button based on the former status.
    switch (settings.oldStatus) {
        case '1':
            // Removing "move to production" button.
            var $target = $('button[onclick="btnMoveToProd()"]').parent().parent();
            break;
        case '3':
            // Removing archive button.
            var $target = $('#row_archive');
            break;
        default:
            // Removing "move to development" button.
            var $target = $('button[onclick="MoveToDev(0,0)"]').parent().parent();
            break;
    }

    $target.remove();
});
