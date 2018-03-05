<?php
/**
 * @file
 * Provides ExternalModule class for Project Sequestration State module.
 */

namespace ProjectSequestrationState\ExternalModule;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;
use RCView;
use UserRights;
use DateTime;
use System;

// TODO: Add documentation to every function and complex structures.

// TODO: Unhardcode this - create fake project and role automatically.
define('PSS_PID', 123);
define('PSS_RID', 123);

/**
 * ExternalModule class for Project Sequestration State module.
 */
class ExternalModule extends AbstractExternalModule {

    protected $userRights;
    protected $settings = array();
    protected $jsFiles = array();

    /**
     * @inheritdoc
     */
    function redcap_every_page_before_render($project_id) {
        if (!$project_id) {
            return;
        }

        if (strpos(PAGE, 'ExternalModules/manager/ajax/save-settings.php') !== false) {
            if ($_GET['moduleDirectoryPrefix'] == $this->PREFIX && !SUPER_USER && !ACCOUNT_MANAGER) {
                // Adding an extra security layer, to make sure no one but
                // global admins is able to save configuration.
                echo 'Access denied';
                exit;
            }

            return;
        }

        if ($project_id == PSS_PID) {
            if (PAGE == 'UserRights/edit_user.php') {
                global $Proj;

                // Creating fake row on data entry user rights table.
                $Proj->surveys = true;
                $Proj->forms = array('default' => array('menu' => 'Default', 'survey_id' => -1));

                return;
            }

            // Redirecting to Home when trying to access fake project.
            redirect(APP_PATH_WEBROOT);
        }

        if (!$this->projectIsSequestered($project_id)) {
            return;
        }

        $this->updateProjectStatus($project_id);

        // Overriding user rights and checking access to the current page.
        if ($this->updateUserRights($project_id) && !$this->checkPageAccess()) {
            $this->settings['warningMsg'] = false;
            $this->showAccessDeniedContents();
        }
    }

    /**
     * @inheritdoc
     */
    function redcap_every_page_top($project_id) {
        if ($project_id) { 
            if ($this->projectIsSequestered($project_id)) {
                $this->jsFiles[] = 'js/project.js';
                $this->settings['icon'] = $this->getSystemSetting('icon');

                if (!isset($this->userRights)) {
                    // This is important for access denied pages, where hook
                    // every_page_before_render is not executed, so the left menu
                    // is rendered according to the overriden permissions.
                    $this->updateUserRights($project_id);
                }
                elseif (!isset($this->settings['warningMsg'])) {
                    // Display warning message about project state.
                    $this->enableWarningMessage($project_id);
                }
            }
        }
        elseif (
            strpos(PAGE, 'ControlCenter/view_projects.php') !== false ||
            (strpos(PAGE, 'index.php') !== false && !empty($_GET['action']) && $_GET['action'] == 'myprojects')
        ) {
            // Handling projects tables.
            $this->jsFiles[] = 'js/projects-list.js';

            $this->settings += array(
                'name' => $this->getSystemSetting('name'),
                'icon' => $this->getSystemSetting('icon'),
                'targetPid' => PSS_PID,
                'sequesteredProjects' => array(),
            );

            // TODO: verify performance for "enabled on all projects".
            foreach (ExternalModules::getEnabledProjects($this->PREFIX) as $project_info) {
                $pid = $project_info['project_id'];

                if ($this->projectIsSequestered($pid)) {
                    $this->settings['sequesteredProjects'][$pid] = $pid;
                }
            }

            // Hiding table until all JS manipulation is done.
            $this->hideElement('#table-proj_table');
        }

        if (
            strpos(PAGE, 'ExternalModules/manager/control_center.php') !== false ||
            strpos(PAGE, 'ExternalModules/manager/project.php') !== false
        ) {
            $this->jsFiles[] = 'js/functions.js';
            $this->jsFiles[] = 'js/config.js';

            $target_rid = PSS_RID;
            if ($project_id) {
                if (!$target_rid = $this->getSeqProjectRoleId($project_id)) {
                    $target_rid = 'sequestered____' . $project_id;
                }

                // Hiding table until all JS manipulation is done.
                $this->hideElement('#external-modules-enabled');
            }

            $this->settings += array(
                'modulePrefix' => $this->PREFIX,
                'currentPid' => $project_id,
                'targetPid' => PSS_PID,
                'targetRid' => $target_rid,
                'getRoleIdUrl' => $this->getUrl('plugins/ajax_get_seq_project_rid.php'),
            );
        }

        // Applying JS settings.
        $this->setJsSettings();
        $this->setJsFiles();
    }

    function projectIsSequestered($project_id) {
        $mode = $this->getProjectSetting('mode', $project_id);
        if ($mode == 'scheduled') {
            if (!$date = $this->getProjectSetting('date', $project_id)) {
                return false;
            }

            $date = new DateTime($date);
            return $date->getTimestamp() < time();
        }
        elseif ($mode == 'switch') {
            return $this->getProjectSetting('sequestered', $project_id);
        }

        return false;
    }

    function getSeqProjectRoleId($project_id) {
        $sql = 'SELECT role_id FROM redcap_user_roles WHERE project_id = ' . PSS_PID . ' AND role_name = "sequestered____' . db_escape($project_id) . '" ORDER BY role_id DESC LIMIT 1';
        $q = $this->query($sql);
        if (!db_num_rows($q)) {
            return false;
        }

        $row = db_fetch_assoc($q);
        return $row['role_id'];
    }

    function projectOverridesDefaults($project_id) {
        return $this->getProjectSetting('override_defaults', $project_id);
    }

    protected function updateProjectStatus($project_id) {
        global $status, $lang;

        if ($this->getSequesteredSetting('inactive', $project_id)) {
            // Setting project status as inactive.
            $status = 2;
        }

        switch ($status) {
            case 1:
                $string_id = '30';
                break;
            case 2:
                $string_id = '31';
                break;
            case 3:
                $string_id = '26';
                break;
            default:
                $string_id = '29';
        }

        // A little trick to display the sequestered status label.
        $lang['global_' . $string_id] = RCView::span(array('class' => 'sequestered'), htmlspecialchars($this->getSystemSetting('name')));
    }

    protected function updateUserRights($project_id) {
        if (USERID && !SUPER_USER && !ACCOUNT_MANAGER) {
            global $user_rights;
            $user_rights = $this->userRights = $this->getUserRightsMask($project_id);
        }

        return $this->userRights;
    }

    protected function checkPageAccess() {
        global $user_rights;

        // Check Data Entry page rights (edit/read-only/none), if we're on that page.
        if (PAGE == 'DataEntry/index.php') {
            // If user has no access to form, kick out; otherwise set as full access or disabled.
            return !empty($user_rights['forms'][$_GET['page']]);
        }

        $page_rights = new UserRights();
        $page_rights = $page_rights->page_rights;

        // Determine if user has rights to current page.
        if (isset($page_rights[PAGE])) {
            return !empty($user_rights[$page_rights[PAGE]]);
        }

        return true;
    }

    protected function getUserRightsMask($project_id) {
        if (!$this->projectOverridesDefaults($project_id) || !($role_id = $this->getSeqProjectRoleId($project_id))) {
            $role_id = PSS_RID;
        }

        $sql = 'SELECT * FROM redcap_user_roles WHERE role_id = ' . $role_id;
        $q = $this->query($sql);

        $user_rights = array('username' => USERID);
        if (!db_num_rows($q)) {
            return $user_rights;
        }

        $user_rights += db_fetch_assoc($q);
        if (!empty($user_rights['external_module_config'])) {
            $value = json_decode($user_rights['external_module_config'], true);
            if (!is_array($value)) {
                $value = array();
            }

            $user_rights['external_module_config'] = $value;
        }


        list(, $access_flag) = explode(',', substr($user_rights['data_entry'], 1, -1));
        unset($user_rights['data_entry']);

        global $Proj;
        $user_rights['forms'] = array();

        foreach (array_keys($Proj->forms) as $form_name) {
            $user_rights['forms'][$form_name] = $access_flag;
        }

        return $user_rights;
    }

    protected function showAccessDeniedContents() {
        extract($GLOBALS);

        include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
        renderPageTitle();

        $output = RCView::img(array('src' => APP_PATH_IMAGES . 'exclamation.png'));
        $output .= ' ' . RCView::b($lang['global_05']) . RCView::br() . RCView::br() . $lang['config_02'];

        if ($project_contact_email && $project_contact_name) {
            $output .= ' ' . RCView::a(array('href' => 'mailto:' . $project_contact_email), $project_contact_name);
        }

        $output .= ' ' . $lang['config_03'];
        echo RCView::div(array('class' => 'red'), $output);

        include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
        exit;
    }

    protected function enableWarningMessage($project_id) {
        if ($msg = $this->getSequesteredSetting('warning_message', $project_id)) {
            global $lang;

            // Overriding dialog message.
            $lang['bottom_50'] = $msg;

            // Placing warning message at the top of page.
            $msg = RCView::div(array(), RCView::img(array('src' => APP_PATH_IMAGES . 'warning.png')) . ' ' . RCView::b('NOTICE')) . $msg;
            $this->settings['warningMsg'] = RCView::div(array('class' => 'yellow'), $msg);
        }
    }

    protected function getSequesteredSetting($key, $project_id) {
        if ($this->projectOverridesDefaults($project_id)) {
            return reset($this->getProjectSetting($key, $project_id));
        }

        return $this->getSystemSetting($key);
    }

    protected function hideElement($selector) {
        echo '<style>' . $selector . ' {display: none;}</style>';
    }

    /**
     * Sets local JS files.
     */
    protected function setJsFiles() {
        foreach ($this->jsFiles as $path) {
            echo '<script src="' . $this->getUrl($path) . '"></script>';
        }
    }

    /**
     * Sets JS settings.
     *
     * @param mixed $settings
     *   The setting settings.
     */
    protected function setJsSettings() {
        if (!empty($this->settings)) {
            echo '<script>projectSequestrationState = ' . json_encode($this->settings) . ';</script>';
        }
    }
}
