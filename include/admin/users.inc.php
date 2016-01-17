<?php
# Copyright (c) 2003-2005, Jannis Hermanns (on behalf the Serendipity Developer Team)
# All rights reserved.  See LICENSE file for licensing details

if (IN_serendipity !== true) {
    die ('Don\'t hack!');
}

if (!serendipity_checkPermission('adminUsers')) {
    return;
}

require_once(S9Y_INCLUDE_PATH . 'include/functions_installer.inc.php');

/* Delete a user */
if (isset($_POST['DELETE_YES']) && serendipity_checkFormToken()) {
    $user = serendipity_fetchUsers($serendipity['POST']['user']);
    if (($serendipity['serendipityUserlevel'] < USERLEVEL_ADMIN && $user[0]['userlevel'] >= $serendipity['serendipityUserlevel']) || !serendipity_checkPermission('adminUsersDelete')) {
        echo '<div class="serendipityAdminMsgError"><img style="width: 22px; height: 22px; border: 0px; padding-right: 4px; vertical-align: middle" src="' . serendipity_getTemplateFile('admin/img/admin_msg_error.png') . '" alt="" />' . CREATE_NOT_AUTHORIZED . '</div>';
    } elseif ($_POST['userlevel'] > $serendipity['serendipityUserlevel']) {
        echo '<div class="serendipityAdminMsgError"><img style="width: 22px; height: 22px; border: 0px; padding-right: 4px; vertical-align: middle" src="' . serendipity_getTemplateFile('admin/img/admin_msg_error.png') . '" alt="" />' . CREATE_NOT_AUTHORIZED_USERLEVEL . '</div>';
    } else {
        $group_intersect = serendipity_intersectGroup($user[0]['authorid']);

        if (serendipity_checkPermission('adminUsersMaintainOthers') ||
            (serendipity_checkPermission('adminUsersMaintainSame') && $group_intersect)) {
            serendipity_deleteAuthor($user[0]['authorid']);
            printf('<div class="serendipityAdminMsgSuccess"><img style="height: 22px; width: 22px; border: 0px; padding-right: 4px; vertical-align: middle" src="' . serendipity_getTemplateFile('admin/img/admin_msg_success.png') . '" alt="" />' . DELETED_USER . '</div>', htmlspecialchars($serendipity['POST']['user']), htmlspecialchars($user[0]['realname']));
            serendipity_plugin_api::hook_event('backend_users_delete', $user[0]);
        } else {
            echo '<div class="serendipityAdminMsgError"><img style="width: 22px; height: 22px; border: 0px; padding-right: 4px; vertical-align: middle" src="' . serendipity_getTemplateFile('admin/img/admin_msg_error.png') . '" alt="" />' . CREATE_NOT_AUTHORIZED_USERLEVEL . '</div>';
        }
    }
}


/* Save new user */
if (isset($_POST['SAVE_NEW']) && serendipity_checkFormToken()) {
    if (($serendipity['serendipityUserlevel'] < USERLEVEL_ADMIN && $_POST['userlevel'] >= $serendipity['serendipityUserlevel']) || !serendipity_checkPermission('adminUsersCreateNew')) {
        echo '<div class="serendipityAdminMsgError"><img style="width: 22px; height: 22px; border: 0px; padding-right: 4px; vertical-align: middle" src="' . serendipity_getTemplateFile('admin/img/admin_msg_error.png') . '" alt="" />' . CREATE_NOT_AUTHORIZED . '</div>';
    } else {
        $serendipity['POST']['user'] = serendipity_addAuthor($_POST['username'], $_POST['pass'], $_POST['realname'], $_POST['email'], $_POST['userlevel'], 1);

        $valid_groups = serendipity_getGroups($serendipity['authorid'], true);
        /* Save all the properties */
        $config = serendipity_parseTemplate(S9Y_CONFIG_USERTEMPLATE);
        foreach($config as $category) {
            foreach ($category['items'] as $item) {
                if (in_array('groups', $item['flags'])) {
                    if (serendipity_checkPermission('adminUsersMaintainOthers')) {

                        // Void, no fixing neccessarry

                    } elseif (serendipity_checkPermission('adminUsersMaintainSame')) {
                        // Check that no user may assign groups he's not allowed to.
                        foreach($_POST[$item['var']] AS $groupkey => $groupval) {
                            if (in_array($groupval, $valid_groups)) {
                                continue;
                            } elseif ($groupval == 2 && in_array(3, $valid_groups)) {
                                // Admin is allowed to assign users to chief editors
                                continue;
                            } elseif ($groupval == 1 && in_array(2, $valid_groups)) {
                                // Chief is allowed to assign users to editors
                                continue;
                            }

                            unset($_POST[$item['var']][$groupkey]);
                        }

                    } else {
                        continue;
                    }

                    if (count($_POST[$item['var']]) < 1) {
                        echo '<div class="serendipityAdminMsgError"><img style="width: 22px; height: 22px; border: 0px; padding-right: 4px; vertical-align: middle" src="' . serendipity_getTemplateFile('admin/img/admin_msg_error.png') . '" alt="" />' . WARNING_NO_GROUPS_SELECTED . '</div>';
                    } else {
                        serendipity_updateGroups($_POST[$item['var']], $serendipity['POST']['user'], false);
                    }
                    continue;
                }

                if (serendipity_checkConfigItemFlags($item, 'local')) {
                    serendipity_set_user_var($item['var'], $_POST[$item['var']], $serendipity['POST']['user'], ($serendipity['authorid'] == $serendipity['POST']['authorid'] ? true : false));
                }

                if (serendipity_checkConfigItemFlags($item, 'configuration')) {
                    serendipity_set_config_var($item['var'], $_POST[$item['var']], $serendipity['POST']['user']);
                }
            }
        }

        serendipity_plugin_api::hook_event('backend_users_add', $serendipity['POST']['user']);
        printf('<div class="serendipityAdminMsgSuccess"><img style="height: 22px; width: 22px; border: 0px; padding-right: 4px; vertical-align: middle" src="' . serendipity_getTemplateFile('admin/img/admin_msg_success.png') . '" alt="" />' . CREATED_USER . '</div>', '#' . htmlspecialchars($serendipity['POST']['user']) . ', ' . htmlspecialchars($_POST['realname']));
    }
}


/* Edit a user */
if (isset($_POST['SAVE_EDIT']) && serendipity_checkFormToken()) {
    $user = serendipity_fetchUsers($serendipity['POST']['user']);
    if (!serendipity_checkPermission('adminUsersMaintainOthers') && $user[0]['userlevel'] >= $serendipity['serendipityUserlevel']) {
        echo '<div class="serendipityAdminMsgError"><img style="width: 22px; height: 22px; border: 0px; padding-right: 4px; vertical-align: middle" src="' . serendipity_getTemplateFile('admin/img/admin_msg_error.png') . '" alt="" />' . CREATE_NOT_AUTHORIZED . '</div>';
    } elseif ($_POST['userlevel'] > $serendipity['serendipityUserlevel']) {
        echo '<div class="serendipityAdminMsgError"><img style="width: 22px; height: 22px; border: 0px; padding-right: 4px; vertical-align: middle" src="' . serendipity_getTemplateFile('admin/img/admin_msg_error.png') . '" alt="" />' . CREATE_NOT_AUTHORIZED_USERLEVEL . '</div>';
    } else {
        $valid_groups = serendipity_getGroups($serendipity['authorid'], true);
        $config = serendipity_parseTemplate(S9Y_CONFIG_USERTEMPLATE);
        foreach($config as $category) {
            foreach ($category['items'] as $item) {
                if (in_array('groups', $item['flags'])) {
                    if (serendipity_checkPermission('adminUsersMaintainOthers')) {

                        // Void, no fixing neccessarry

                    } elseif (serendipity_checkPermission('adminUsersMaintainSame')) {

                        // Check that no user may assign groups he's not allowed to.
                        foreach($_POST[$item['var']] AS $groupkey => $groupval) {
                            if (in_array($groupval, $valid_groups)) {
                                continue;
                            } elseif ($groupval == 2 && in_array(3, $valid_groups)) {
                                // Admin is allowed to assign users to chief editors
                                continue;
                            } elseif ($groupval == 1 && in_array(2, $valid_groups)) {
                                // Chief is allowed to assign users to editors
                                continue;
                            }

                            unset($_POST[$item['var']][$groupkey]);
                        }

                    } else {
                        continue;
                    }

                    if (count($_POST[$item['var']]) < 1) {
                        echo '<div class="serendipityAdminMsgError"><img style="width: 22px; height: 22px; border: 0px; padding-right: 4px; vertical-align: middle" src="' . serendipity_getTemplateFile('admin/img/admin_msg_error.png') . '" alt="" />' . WARNING_NO_GROUPS_SELECTED . '</div>';
                    } else {
                        serendipity_updateGroups($_POST[$item['var']], $serendipity['POST']['user'], false);
                    }
                    continue;
                }

                if (serendipity_checkConfigItemFlags($item, 'local')) {
                    serendipity_set_user_var($item['var'], $_POST[$item['var']], $serendipity['POST']['user'], ($serendipity['authorid'] == $serendipity['POST']['user'] ? true : false));
                }

                if (serendipity_checkConfigItemFlags($item, 'configuration')) {
                    serendipity_set_config_var($item['var'], $_POST[$item['var']], $serendipity['POST']['user']);
                }
            }
        }

        $pl_data = array(
            'id'       => $serendipity['POST']['user'],
            'authorid' => $serendipity['POST']['user'],
            'username' => $_POST['username'],
            'realname' => $_POST['realname'],
            'email'    => $_POST['email']
        );
        serendipity_updatePermalink($pl_data, 'author');

        serendipity_plugin_api::hook_event('backend_users_edit', $pl_data);
        printf('<div class="serendipityAdminMsgSuccess"><img style="height: 22px; width: 22px; border: 0px; padding-right: 4px; vertical-align: middle" src="' . serendipity_getTemplateFile('admin/img/admin_msg_success.png') . '" alt="" />' . MODIFIED_USER . '</div>', htmlspecialchars($_POST['realname']));
    }
}

if ($serendipity['GET']['adminAction'] != 'delete') {
?>
    <table width="100%">
        <tr>
            <td><strong><?php echo USER; ?></strong></td>
            <td width="100" align="center"><strong><?php echo USER_LEVEL ?></strong></td>
            <td width="300">&nbsp;</td>
        </tr>
        <tr>
            <td colspan="3">
<?php
if (serendipity_checkPermission('adminUsersMaintainOthers')) {
    $users = serendipity_fetchUsers('');
} elseif (serendipity_checkPermission('adminUsersMaintainSame')) {
    $users = serendipity_fetchUsers('', serendipity_getGroups($serendipity['authorid'], true));
} else {
    $users = serendipity_fetchUsers($serendipity['authorid']);
}

$i = 0;
if (is_array($users)) {
    foreach($users as $user) {
        if ($user['userlevel'] < $serendipity['serendipityUserlevel'] || $user['authorid'] == $serendipity['authorid'] || $serendipity['serendipityUserlevel'] >= USERLEVEL_ADMIN ) {
            if ( $user['userlevel'] >= USERLEVEL_ADMIN ) {
                $img = serendipity_getTemplateFile('admin/img/user_admin.png');
            } elseif ( $user['userlevel'] >= USERLEVEL_CHIEF ) {
                $img = serendipity_getTemplateFile('admin/img/user_chief.png');
            } else {
                $img = serendipity_getTemplateFile('admin/img/user_editor.png');
            }
            $data['users'][$user]['userlevel_name'] = $serendipity['permissionLevels'][$userdata['userlevel']];
        }
    }

    if ( ! (isset($_POST['NEW']) || $serendipity['GET']['adminAction'] == 'new') && serendipity_checkPermission('adminUsersCreateNew')) {
        $data['new'] = true;
    }
}


if ( ($serendipity['GET']['adminAction'] == 'edit' && serendipity_checkPermission('adminUsersDelete')) || ((isset($_POST['NEW']) || $serendipity['GET']['adminAction'] == 'new')  && serendipity_checkPermission('adminUsersCreateNew')) ) {
    $data['adminAction'] = $serendipity['GET']['adminAction'];
    $data['show_form'] = true;
    $data['formToken'] = serendipity_setFormToken();

    if ($serendipity['GET']['adminAction'] == 'edit') {
        $user = serendipity_fetchUsers($serendipity['GET']['userid']);
        $group_intersect = serendipity_intersectGroup($user[0]['authorid']);
        if ($user[0]['userlevel'] >= $serendipity['serendipityUserlevel'] && $user[0]['authorid'] != $serendipity['authorid'] && !serendipity_checkPermission('adminUsersMaintainOthers')) {
            $data['no_create_permission'] = true;
            $from = array();
        } elseif (serendipity_checkPermission('adminUsersMaintainOthers') ||
                (serendipity_checkPermission('adminUsersMaintainSame') && $group_intersect)) {
            $data['create_permission'] = true;
            $from = &$user[0];
            unset($from['password']);
        } else {

            $from = array();
        }
    } else {
        echo '<strong>' . CREATE_NOT_AUTHORIZED . '</strong><br />';
        echo EDIT;
        $from = array();
    }
} else {
    echo CREATE;
    $from = array();
}
?>
    </h3>

<?php
$config = serendipity_parseTemplate(S9Y_CONFIG_USERTEMPLATE);
if (!empty($serendipity['GET']['userid'])) {
    $from['groups'] = serendipity_getGroups($serendipity['GET']['userid']);
} else {
    $from['groups'] = array();
}

serendipity_printConfigTemplate($config, $from, true, false, true, true);

    $data['config'] = serendipity_printConfigTemplate($config, $from, true, false, true, true);

    </div>
</form>
<?php
} elseif ($serendipity['GET']['adminAction'] == 'delete' && serendipity_checkPermission('adminUsersDelete')) {
    $user = serendipity_fetchUsers($serendipity['GET']['userid']);
    $group_intersect = serendipity_intersectGroup($user[0]['authorid']);

    if (serendipity_checkPermission('adminUsersMaintainOthers') ||
                (serendipity_checkPermission('adminUsersMaintainSame') && $group_intersect)) {
?>
<form action="?serendipity[adminModule]=users" method="post">
    <div>
    <?php printf(DELETE_USER, (int)$serendipity['GET']['userid'], htmlspecialchars($user[0]['realname'])); ?>
        <br /><br />
        <?php echo serendipity_setFormToken(); ?>
        <input type="hidden" name="serendipity[user]" value="<?php echo (int)$serendipity['GET']['userid']; ?>" />
        <input type="submit" name="DELETE_YES" value="<?php echo DUMP_IT; ?>" class="serendipityPrettyButton input_button" />
        <input type="submit" name="NO" value="<?php echo NOT_REALLY; ?>" class="serendipityPrettyButton input_button" />
    </div>
</form>
<?php
    }
}

echo serendipity_smarty_show('admin/users.inc.tpl', $data);

/* vim: set sts=4 ts=4 expandtab : */
?>
