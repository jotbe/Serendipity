<?php

if (IN_serendipity !== true) {
    die ('Don\'t hack!');
}

if (!serendipity_checkPermission('adminUsersGroups')) {
    return;
}

/* Delete a group */
if (isset($_POST['DELETE_YES']) && serendipity_checkFormToken()) {
    $group = serendipity_fetchGroup($serendipity['POST']['group']);
    serendipity_deleteGroup($serendipity['POST']['group']);
    printf('<div class="serendipityAdminMsgSuccess"><img style="height: 22px; width: 22px; border: 0px; padding-right: 4px; vertical-align: middle" src="' . serendipity_getTemplateFile('admin/img/admin_msg_success.png') . '" alt="" />' . DELETED_GROUP . '</div>', htmlspecialchars($serendipity['POST']['group']), htmlspecialchars($group['name']));
}

/* Save new group */
if (isset($_POST['SAVE_NEW']) && serendipity_checkFormToken()) {
    $serendipity['POST']['group'] = serendipity_addGroup($serendipity['POST']['name']);
    $perms = serendipity_getAllPermissionNames();
    serendipity_updateGroupConfig($serendipity['POST']['group'], $perms, $serendipity['POST'], false, $serendipity['POST']['forbidden_plugins'], $serendipity['POST']['forbidden_hooks']);
    printf('<div class="serendipityAdminMsgSuccess"><img style="height: 22px; width: 22px; border: 0px; padding-right: 4px; vertical-align: middle" src="' . serendipity_getTemplateFile('admin/img/admin_msg_success.png') . '" alt="" />' . CREATED_GROUP . '</div>', '#' . htmlspecialchars($serendipity['POST']['group']) . ', ' . htmlspecialchars($serendipity['POST']['name']));
}

/* Edit a group */
if (isset($_POST['SAVE_EDIT']) && serendipity_checkFormToken()) {
    $perms = serendipity_getAllPermissionNames();
    serendipity_updateGroupConfig($serendipity['POST']['group'], $perms, $serendipity['POST'], false, $serendipity['POST']['forbidden_plugins'], $serendipity['POST']['forbidden_hooks']);
    printf('<div class="serendipityAdminMsgSuccess"><img style="height: 22px; width: 22px; border: 0px; padding-right: 4px; vertical-align: middle" src="' . serendipity_getTemplateFile('admin/img/admin_msg_success.png') . '" alt="" />' . MODIFIED_GROUP . '</div>', htmlspecialchars($serendipity['POST']['name']));
}

if ( $serendipity['GET']['adminAction'] != 'delete' ) {
    $data['delete'] = false;

    if (serendipity_checkPermission('adminUsersMaintainOthers')) {
        $groups = serendipity_getAllGroups();
    } elseif (serendipity_checkPermission('adminUsersMaintainSame')) {
        $groups = serendipity_getAllGroups($serendipity['authorid']);
    } else {
        $groups = array();
    }
    $data['groups'] = $groups;
    if ( ! (isset($_POST['NEW']) || $serendipity['GET']['adminAction'] == 'new') ) {
        $data['start'] = true;
    }
    $data['deleteFormToken'] = serendipity_setFormToken('url');
}

if ($serendipity['GET']['adminAction'] == 'edit' || isset($_POST['NEW']) || $serendipity['GET']['adminAction'] == 'new') {
    if (isset($_POST['NEW']) || $serendipity['GET']['adminAction'] == 'new') {
        $data['new'] = true;
    } else {
        $data['edit'] = true;
    }
    $data['formToken'] = serendipity_setFormToken();

    if ($serendipity['GET']['adminAction'] == 'edit') {
        $group = serendipity_fetchGroup($serendipity['GET']['group']);
        $from = &$group;
    } else {
        $from = array();
    }
    $data['from'] = $from;

    $allusers = serendipity_fetchUsers();
    $users    = serendipity_getGroupUsers($from['id']);

$selected = array();
foreach((array)$users AS $user) {
    $selected[$user['id']] = true;
}

foreach($allusers AS $user) {
    echo '<option value="' . (int)$user['authorid'] . '" ' . (isset($selected[$user['authorid']]) ? 'selected="selected"' : '') . '>' . htmlspecialchars($user['realname']) . '</option>' . "\n";
}
?>
            </select>
        </td>
    </tr>
    <tr>
        <td colspan="2">&nbsp;</td>
    </tr>
<?php
    $perms = serendipity_getAllPermissionNames();
    ksort($perms);
    foreach($perms AS $perm => $userlevels) {
        if (substr($perm, 0, 2) == 'f_') {
            continue;
        }

        if (isset($from[$perm]) && $from[$perm] === 'true') {
            $selected = 'checked="checked"';
        } else {
            $selected = '';
        }

        if (!isset($section)) {
            $section = $perm;
        }

        if ($section != $perm && substr($perm, 0, strlen($section)) == $section) {
            $indent  = '&nbsp;&nbsp;';
            $indentB = '';
        } elseif ($section != $perm) {
            $indent  = '<br />';
            $indentB = '<br />';
            $section = $perm;
        }

        if (defined('PERMISSION_' . strtoupper($perm))) {
            list($name, $note) = explode(":", constant('PERMISSION_' . strtoupper($perm)));
            $data['perms'][$perm]['permission_name'] = $name;
            $data['perms'][$perm]['permission_note'] = $note;
        } else {
            $permname = $perm;
        }

        if (!serendipity_checkPermission($perm) && $perm != 'hiddenGroup') {
            echo "<tr>\n";
            echo "<td>$indent" . htmlspecialchars($permname) . "</td>\n";
            echo '<td>' . $indentB . ' ' . (!empty($selected) ? YES : NO) . '</td>' . "\n";
            echo "</tr>\n";
        } else {
            echo "<tr>\n";
            echo "<td>$indent<label for=\"" . htmlspecialchars($perm) . "\">" . splitEntryMark(htmlspecialchars($permname)) . "</label></td>\n";
            echo '<td>' . $indentB . '<input class="input_checkbox" id="' . htmlspecialchars($perm) . '" type="checkbox" name="serendipity[' . htmlspecialchars($perm) . ']" value="true" ' . $selected . ' /></td>' . "\n";
            echo "</tr>\n";
        }
    }

    if ($serendipity['enablePluginACL']) {
        $allplugins =& serendipity_plugin_api::get_event_plugins();
        $allhooks   = array();
        $data['allplugins'] = $allplugins;
        foreach($allplugins AS $plugid => $currentplugin) {
            foreach($currentplugin['b']->properties['event_hooks'] AS $hook => $set) {
                $allhooks[$hook] = array();
            }
            $data['allplugins'][$plugid]['has_permission'] = serendipity_hasPluginPermissions($plugid, $from['id']);
        }
        ksort($allhooks);

        $data['allhooks'] = $allhooks;
        foreach($allhooks AS $hook => $set) {
            $data['allhooks'][$hook]['has_permission'] = serendipity_hasPluginPermissions($hook, $from['id']);
        }
    }
?>
</table>

<?php
if ($serendipity['GET']['adminAction'] == 'edit') { ?>
        <input type="submit" name="SAVE_EDIT"   value="<?php echo SAVE; ?>" class="serendipityPrettyButton input_button" />
        <?php echo ' - ' . WORD_OR . ' - ' ?>
        <input type="submit" name="SAVE_NEW"   value="<?php echo CREATE_NEW_GROUP; ?>" class="serendipityPrettyButton input_button" />
<?php } else { ?>
        <input type="submit" name="SAVE_NEW" value="<?php echo CREATE_NEW_GROUP; ?>" class="serendipityPrettyButton input_button" />
<?php } ?>

    </div>
</form>
<?php
} elseif ($serendipity['GET']['adminAction'] == 'delete') {
    $group = serendipity_fetchGroup($serendipity['GET']['group']);
?>
<form action="?serendipity[adminModule]=groups" method="post">
    <div>
    <?php printf(DELETE_GROUP, (int)$serendipity['GET']['group'], htmlspecialchars($group['name'])); ?>
        <br /><br />
        <?php echo serendipity_setFormToken(); ?>
        <input type="hidden" name="serendipity[group]" value="<?php echo htmlspecialchars($serendipity['GET']['group']); ?>" />
        <input type="submit" name="DELETE_YES" value="<?php echo DUMP_IT; ?>" class="serendipityPrettyButton input_button" />
        <input type="submit" name="NO" value="<?php echo NOT_REALLY; ?>" class="serendipityPrettyButton input_button" />
    </div>
</form>
<?php
}

echo serendipity_smarty_show('admin/groups.inc.tpl', $data);

/* vim: set sts=4 ts=4 expandtab : */
?>
