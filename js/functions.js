/**
 * Adapted from REDCap Core to work outside the project scope.
 * TODO: refactor this file and adapt display to our needs (e.g. removing the concept of role).
 */

projectSequestrationState.openAddUserPopup = function(pid, role_id) {
    var params = $.isNumeric(role_id) ? { role_id: role_id } : { username: role_id, role_id: 0 };

    // Ajax request
    $.post(app_path_webroot + 'UserRights/edit_user.php?pid=' + pid, params, function(data) {
        if (data === '') {
            alert(woops); return;
        }
        // Add content to div
        $('#user-rights-mask').html(data);
        // Enable expiration datepicker
        $('#expiration').datepicker({yearRange: '-10:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery});
        // If select "edit response" checkbox, then set form-level rights radio button to View & Edit
        $('table#form_rights input[type="checkbox"]').click(function(){
            if ($(this).prop('checked')) {
                var form = $(this).attr('id').substring(14);
                // Deselect all, then select View & Edit
                $('table#form_rights input[name="form-'+form+'"][value="0"]').prop('checked',false);
                $('table#form_rights input[name="form-'+form+'"][value="2"]').prop('checked',false);
                $('table#form_rights input[name="form-'+form+'"][value="1"]').prop('checked',true);
            }
        });

        $('#form_rights tr').each(function() {
            var $cols = $(this).children('td');
            if ($cols.length === 2) {
                // Since we have a generic/fake instrument, let's remove its
                // name.
                $cols.first().remove();
            }
        });

        // Set dialog buttons
        eval($('#user-rights-mask div#submit-buttons').html());

        // Set dialog title
        if ($('#user-rights-mask #dialog_title').length) {
            var title = $('#user-rights-mask #dialog_title').html();
            // Open dialog

            buttons = [];
            $.each(add_user_dialog_btns, function(i, button) {
                if (button.text.toLowerCase() !== 'delete role' && button.text.toLowerCase() !== 'copy role') {
                    buttons.push(button);
                }
            });

            $('#user-rights-mask').dialog({ bgiframe: true, modal: true, width: 800,
                open: function(){
                    // Put bold on the Save button and set focus on it
                    $('.ui-dialog-buttonpane').find('button:last').css({'font-weight':'bold','color':'#222'}).focus();

                    // Fit to screen
                    fitDialog(this);
                },
                title: title, buttons: buttons, close: function(){ $('#user-rights-mask').html('') }
            });
        } else {
            // Error
            simpleDialog(data, 'Alert');
        }
    });
}

// Save user form via ajax
function saveUserFormAjax() {
    // Display progress bar
    showProgress(1);
    if ($('#user-rights-mask').hasClass('ui-dialog-content')) $('#user-rights-mask').dialog('destroy');
    // Serialize form inputs into a JSON object to send via Ajax
    var form_vars = $('form#user_rights_form').serializeObject();
    $.post(app_path_webroot + 'UserRights/edit_user.php?pid=' + projectSequestrationState.targetPid, form_vars, function(data) {
        showProgress(0, 0);
        $('#user_rights_roles_table_parent').html(data);
        simpleDialogAlt($('#user_rights_roles_table_parent div.userSaveMsg'), 1.7);

        if (!$.isNumeric(projectSequestrationState.targetRid)) {
            $.getJSON(projectSequestrationState.getRoleIdUrl, function(data) {
                if (data.roleId) {
                    // Updating role ID.
                    projectSequestrationState.targetRid = data.roleId;
                }
            });
        }
    });
}
