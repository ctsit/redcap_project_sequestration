/**
 * Adapted from REDCap Core's openAddUserPopup to work outside the project scope.
 */

projectSequestrationState.openUserRightsConfigDialog = function(pid, role_id) {
    var params = $.isNumeric(role_id) ? { role_id: role_id } : { username: role_id, role_id: 0 };

    // Ajax request.
    $.post(app_path_webroot + 'UserRights/edit_user.php?pid=' + pid, params, function(data) {
        if (data === '') {
            alert(woops); return;
        }

        var $dialog = $('#user-rights-mask');

        // Injecting content.
        $dialog.html(data);
        $('div.darkgreen, div.blue').remove();
        $('[name="role_name_edit"]').parent().parent().remove();

        $('#form_rights tr').each(function() {
            var $cols = $(this).children('td');
            if ($cols.length === 2) {
                // Since we have a generic/fake instrument, let's remove its
                // name.
                $cols.first().remove();
            }
        });

        // If select "edit response" checkbox, then set form-level rights radio
        // button to View & Edit.
        $('table#form_rights input[type="checkbox"]').click(function(){
            if ($(this).prop('checked')) {
                var form = $(this).attr('id').substring(14);

                // Deselect all, then select View & Edit.
                $.each([false, false, true], function(i, flag) {
                    $('table#form_rights input[name="form-' + form + '"][value="' + i + '"]').prop('checked', flag);
                });
            }
        });

        // Set dialog buttons.
        buttons = [
            {
                text: 'Cancel',
                click: function() {
                    $dialog.dialog('destroy');
                }
            },
            {
                text: 'Save',
                click: function() {
                    // Display progress bar
                    showProgress(1);
                    if ($dialog.hasClass('ui-dialog-content')) {
                        $dialog.dialog('destroy');
                    }

                    // Serialize form inputs into a JSON object to send via Ajax
                    var form_vars = $('form#user_rights_form').serializeObject();
                    $.post(app_path_webroot + 'UserRights/edit_user.php?pid=' + projectSequestrationState.maskPid, form_vars, function() {
                        showProgress(0, 0);
                    });
                }
            }
        ];

        // Open dialog.
        $dialog.dialog({
            bgiframe: true,
            modal: true,
            width: 800,
            open: function() {
                // Put bold on the Save button and set focus on it
                $('.ui-dialog-buttonpane').find('button:last').css({'font-weight': 'bold', 'color': '#222'}).focus();

                // Fit to screen
                fitDialog(this);
            },
            title: projectSequestrationState.dialogTitle, buttons: buttons, close: function() {
                $dialog.html('');
            }
        });
    });
}
