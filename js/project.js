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
});
