$(document).ready(function() {
    var $modal = $('#external-modules-configure-modal');
    var settings = projectSequestrationState;

    if (settings.currentPid) {
        if (!super_user) {
            // Hiding module configuration from non global admins.
            $('[data-module="' + settings.modulePrefix + '"]').remove();
        }

        // Showing external modules table after deleting content from it.
        $('#external-modules-enabled').show();
    }

    ExternalModules.Settings.prototype.configureSettingsOld = ExternalModules.Settings.prototype.configureSettings;
    ExternalModules.Settings.prototype.configureSettings = function() {
        ExternalModules.Settings.prototype.configureSettingsOld();

        // Making sure we are overriding this modules's modal only.
        if ($modal.data('module') !== settings.modulePrefix) {
            return;
        }

        var button = '<button id="user-rights-override-btn">Configure permissions</button>';
        var dialog = '<div id="user-rights-mask" class="simpleDialog"></div>';

        $modal.find('[field="override_user_rights"] .external-modules-input-td').append('<div>' + button + dialog + '</div>');

        var clickButtonCallback = function() {
            settings.openUserRightsConfigDialog(settings.maskPid, settings.maskRid);
        };

        var setElementVisibility = function($source, $target) {
            var op = $source.is(':checked') ? 'show' : 'hide';
            $target[op]();
        };

        var branchingLogic = [
            {
                source: 'input[name^="override_user_rights"]',
                target: '[field="override_user_rights"] button'
            }
        ];

        if (settings.currentPid) {
            branchingLogic.push({
                source: 'input[name="override_defaults"]',
                target: '[field="override_defaults_wrapper"], [field="inactive"], [field="warning_message"], [field="override_user_rights"]'
            });

            branchingLogic.push({
                source: 'input[name="mode"][value="switch"]',
                target: '[field="sequestered"]'
            });

            branchingLogic.push({
                source: 'input[name="mode"][value="scheduled"]',
                target: '[field="date"]'
            });

            var $date = $modal.find('input[name="date"]');

            $date.datepicker({yearRange: '-1:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery});
            $date.change(function() {
                redcap_validate(this, '', '', 'hard', 'date_' + user_date_format_validation, 1, 1, user_date_format_delimiter);
            });

            $modal.find('.save').click(function() {
                if (!redcap_validate($date[0], '', '', 'hard', 'date_' + user_date_format_validation, 1, 1, user_date_format_delimiter)) {
                    return false;
                }
            });

            var clickButtonCallbackOld = clickButtonCallback;

            clickButtonCallback = function() {
                if ($.isNumeric(settings.maskRid)) {
                    clickButtonCallbackOld();
                }
                else {
                    // Checking if role exists before creating a new one.
                    $.getJSON(projectSequestrationState.getRoleIdUrl, function(data) {
                        if (data.roleId) {
                            settings.maskRid = data.roleId;
                        }

                        clickButtonCallbackOld();
                    });
                }
            }
        }

        $('#user-rights-override-btn').click(clickButtonCallback);

        branchingLogic.forEach(function(bl) {
            var $source = $(bl.source);
            var $target = $(bl.target);
            var $parent = $('input[name="' + $source[0].name + '"]');

            setElementVisibility($source, $target);

            $parent.change(function() {
                setElementVisibility($source, $target);
            });
        });
    }
});
