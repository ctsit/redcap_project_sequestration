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

/**
 * ExternalModule class for Project Sequestration State module.
 */
class ExternalModule extends AbstractExternalModule {

    protected $maskPid;
    protected $maskRid;
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

        if (
            strpos(PAGE, 'ExternalModules/manager/ajax/save-settings.php') !== false ||
            strpos(PAGE, 'ExternalModules/manager/ajax/disable-module.php') !== false
        ) {
            if ($_GET['moduleDirectoryPrefix'] == $this->PREFIX && !SUPER_USER && !ACCOUNT_MANAGER) {
                // Adding an extra security layer, to make sure no one but
                // global admins is able to save configuration or disable the
                // module.
                echo 'Access denied';
                exit;
            }

            return;
        }

        if ($project_id == $this->getMaskPid()) {
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
            PAGE == 'ControlCenter/view_projects.php' ||
            (strpos(PAGE, substr(APP_PATH_WEBROOT_PARENT, 1) . 'index.php') === 0 && !empty($_GET['action']) && $_GET['action'] == 'myprojects')
        ) {
            // Handling projects tables.
            $this->jsFiles[] = 'js/projects-list.js';

            $this->settings += array(
                'name' => $this->getSystemSetting('name'),
                'icon' => $this->getSystemSetting('icon'),
                'maskPid' => $this->getMaskPid(),
                'sequesteredProjects' => $this->getSequesteredProjectsIds(),
            );

            // Hiding table until all JS manipulation is done.
            $this->hideElement('#table-proj_table');
        }

        if (
            strpos(PAGE, 'ExternalModules/manager/control_center.php') !== false ||
            strpos(PAGE, 'ExternalModules/manager/project.php') !== false
        ) {
            $this->jsFiles[] = 'js/user-rights-dialog.js';
            $this->jsFiles[] = 'js/config.js';

            $mask_rid = $this->getMaskRid();
            if ($project_id) {
                if (!$mask_rid = $this->getMaskRid($project_id)) {
                    $mask_rid = 'sequestered____' . $project_id;
                }

                // Hiding table until all JS manipulation is done.
                $this->hideElement('#external-modules-enabled');
            }

            $this->settings += array(
                'modulePrefix' => $this->PREFIX,
                'maskPid' => $this->getMaskPid(),
                'maskRid' => $mask_rid,
                'getRoleIdUrl' => $this->getUrl('plugins/ajax_get_project_mask_rid.php'),
                'dialogTitle' => 'Configure user rights',
            );
        }

        // Applying JS settings.
        $this->setJsSettings();
        $this->setJsFiles();
    }

    /**
     * Gets mask project ID.
     */
    function getMaskPid() {
        if (!empty($this->maskPid) || ($this->maskPid = $this->getSystemSetting('mask_pid'))) {
            return $this->maskPid;
        }

        // TODO: move this to redcap_system_module_enable when this hook
        // becomes available.

        global $auth_meth_global;
        $sql = 'INSERT INTO redcap_projects (project_name, app_title, auth_meth)
                VALUES ("project_sequestration_state", "Project Sequestration State", "' . $auth_meth_global . '")';

        if (!$this->query($sql)) {
            return false;
        }

        $this->maskPid = db_insert_id();
        $this->setSystemSetting('mask_pid', $this->maskPid);

        // Enabling module on mask project, so we can use hooks on it.
        ExternalModules::enableForProject($this->PREFIX, $this->VERSION, $this->maskPid);

        return $this->maskPid;
    }

    /**
     * Gets mask role ID globally or for the given project.
     */
    function getMaskRid($project_id = null) {
        if (!$mask_pid = db_escape($this->getMaskPid())) {
            return false;
        }

        if (!$project_id && !empty($this->maskRid)) {
            return $this->maskRid;
        }

        $role_name = 'sequestered';
        if ($project_id) {
            $role_name .= '____' . db_escape($project_id);
        }

        $sql = 'SELECT role_id FROM redcap_user_roles WHERE project_id = ' . $mask_pid . ' AND role_name = "' . $role_name . '" ORDER BY role_id DESC LIMIT 1';
        $q = $this->query($sql);
        if (db_num_rows($q)) {
            $row = db_fetch_assoc($q);
            if (!$project_id) {
                $this->maskRid = $row['role_id'];
            }

            return $row['role_id'];
        }

        if ($project_id) {
            return false;
        }

        $sql = 'INSERT INTO redcap_user_roles (project_id, role_name) VALUES (' . $mask_pid . ', "sequestered")';
        if (!$this->query($sql)) {
            return false;
        }

        $this->maskRid = db_insert_id();
        return $this->maskRid;
    }

    /**
     * Checks whether the given project is sequestered.
     */
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

    /**
     * Gets a list of sequestered projects IDs.
     */
    function getSequesteredProjectsIds() {
        $ids = array();

        $q = $this->query('SELECT external_module_id FROM redcap_external_modules WHERE directory_prefix = "' . $this->PREFIX . '"');
        $module_id = db_fetch_assoc($q);
        $module_id = $module_id['external_module_id'];

        // This query is an optimization for the case of a huge amount of projects.
        $sql = 'SELECT m.project_id
                FROM (
                    SELECT project_id, value as mode
                    FROM redcap_external_module_settings
                    WHERE external_module_id = ' . $module_id . ' AND `key` = "mode"
                ) m
                LEFT JOIN redcap_external_module_settings s ON
                    s.external_module_id = ' . $module_id . ' AND s.project_id = m.project_id AND s.key = "sequestered"
                LEFT JOIN redcap_external_module_settings d ON
                    d.external_module_id = ' . $module_id . ' AND d.project_id = m.project_id AND d.key = "date"
                WHERE (m.mode = "switch" AND s.value = "true") OR (m.mode = "scheduled" AND UNIX_TIMESTAMP(STR_TO_DATE(d.value, "%m/%d/%Y")) < ' . time() . ')';

        $q = $this->query($sql);
        if (db_num_rows($q)) {
            while ($row = db_fetch_assoc($q)) {
                $ids[$row['project_id']] = $row['project_id'];
            }
        }

        return $ids;
    }

    /**
     * Checks whether the given project overrides the global configuration
     * defaults.
     */
    function projectOverridesDefaults($project_id) {
        return $this->getProjectSetting('override_defaults', $project_id);
    }

    /**
     * Sets project as inactive (if configured) and updates project status label
     * and icon to Sequestered.
     */
    protected function updateProjectStatus($project_id) {
        global $status, $lang;

        if ($this->getSequesteredSetting('inactive', $project_id) && $status != 2) {
            $this->settings['oldStatus'] = $status;

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

    /**
     * Updates user rights of a given sequestered project.
     */
    protected function updateUserRights($project_id) {
        if (USERID && !SUPER_USER && !ACCOUNT_MANAGER && $this->getSequesteredSetting('override_user_rights', $project_id)) {
            if (!$this->projectOverridesDefaults($project_id)) {
                $project_id = null;
            }

            global $user_rights;
            $user_rights = $this->userRights = $this->getUserRightsMask($project_id);
        }
        else {
            $this->userRights = false;
        }

        return $this->userRights;
    }

    /**
     * Checks whether the current user has access to the current page.
     */
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

    /**
     * Gets user rights mask.
     */
    protected function getUserRightsMask($project_id = null) {
        if (!$project_id || !($role_id = $this->getMaskRid($project_id))) {
            $role_id = $this->getMaskRid();
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

        // Set the same permission for all instruments.
        foreach (array_keys($Proj->forms) as $form_name) {
            $user_rights['forms'][$form_name] = $access_flag;
        }

        return $user_rights;
    }

    /**
     * Displays access denied contents on screen.
     */
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

    /**
     * Enables a sequestered project warning message to be displayed on screen.
     */
    protected function enableWarningMessage($project_id) {
        if (!$msg = $this->getSequesteredSetting('warning_message', $project_id)) {
            return;
        }

        global $lang;

        // Overriding dialog message.
        $lang['bottom_50'] = $msg;

        // Placing warning message at the top of page.
        $msg = RCView::div(array(), RCView::img(array('src' => APP_PATH_IMAGES . 'warning.png')) . ' ' . RCView::b('NOTICE')) . $msg;
        $this->settings['warningMsg'] = RCView::div(array('class' => 'yellow', 'style' => 'margin-bottom:25px;'), $msg);
    }

    /**
     * Gets a setting value of a given project.
     *
     * If the project chooses to not override the global settings, the global
     * setting is returned instead.
     */
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
