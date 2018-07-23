<?php
$role_id = empty($_GET['pid']) ? false : $module->getMaskRid($_GET['pid']);
echo json_encode(array('roleId' => $role_id));
