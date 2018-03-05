$(document).ready(function() {
    var getPidMyProjects = function($element) {
        return $element.find('*[class^="pid-cntr-"]')[0].className.substr(9);
    }

    var getPidAllProjects = function($element) {
        return $element.find('.browseProjPid').text().substr(4);
    }

    var callback = $('.browseProdPid').length === 0 ? getPidMyProjects : getPidAllProjects;
    var settings = projectSequestrationState;

    $('#table-proj_table tr').each(function() {
        pid = parseInt(callback($(this)));

        if (pid === settings.targetPid) {
            $(this).remove();
            return;
        }

        if (typeof settings.sequesteredProjects[pid] === 'undefined') {
            return;
        }

        var $icon = $(this).children('td').last().find('.glyphicon');

        // Changing status icon to sequestered.
        $icon[0].className = '';
        $icon.addClass('glyphicon').addClass('glyphicon-' + settings.icon).prop('title', settings.name).css('color', '#800000');
    });

    // Showing table after all manipulation is done.
    $('#table-proj_table').show();
});
