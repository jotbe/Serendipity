<?php
# Copyright (c) 2003-2005, Jannis Hermanns (on behalf the Serendipity Developer Team)
# All rights reserved.  See LICENSE file for licensing details

if (IN_serendipity !== true) {
    die ("Don't hack!");
}

if (defined('S9Y_FRAMEWORK_PLUGINS_ADMIN')) {
    return;
}
@define('S9Y_FRAMEWORK_PLUGINS_ADMIN', true);

/**
 * Returns the name of a usergroup.
 *
 * If a special constant is defined for the name of the group, the name has been
 * internationalized. This applies to the Admin/Chief Editor/Editor group names,
 * which are different for each language. If such a constant is not present,
 * the simple name of the group will be returned.
 *
 * @access public
 * @param  string  name of the group
 * @return string  realname of the group
 */
function serendipity_groupname($group) {
    if (defined('PLUGIN_GROUP_' . $group)) {
        return constant('PLUGIN_GROUP_' . $group);
    } else {
        return $group;
    }
}

/**
 * Sort the pluginlist by case-insensitive string functions
 *
 * @access public
 * @param  array    Compared Plugin #1
 * @param  array    Compared Plugin #2
 * @return boolean  Return code for array comparison
 */
function serendipity_pluginListSort($x, $y) {
    return strnatcasecmp($x['name'] . ' - ' . $x['description'], $y['name'] . ' - ' . $y['description']);
}

/**
 * Show the list of plugins
 *
 * Shows a HTML list of all installed plugins, complete with config/delete/sort order options
 *
 * @access public
 * @param  boolean  Indicates if event plugins (TRUE) or sidebar plugins (FALSE) shall be shown
 * @return null
 */
function show_plugins($event_only = false, $sidebars = null)
{
    global $serendipity;

    $sql_filter = '';
    if (is_array($sidebars)) {
        foreach($sidebars AS $sidebar) {
            $up = strtoupper($sidebar);
            if ($sidebar == 'hide') {
                $opts[$sidebar] = HIDDEN;
            } elseif (defined('SIDEBAR_' . $up)) {
                $opts[$sidebar] = constant('SIDEBAR_' . $up);
            } elseif (defined($up)) {
                $opts[$sidebar] = constant($up);
            } else {
                $opts[$sidebar] = $up;
            }
            $sql_filter .= "AND placement != '" . serendipity_db_escape_string($sidebar) . "' ";
        }
    }

    if (!$event_only) {
        $sql   = "SELECT * from {$serendipity['dbPrefix']}plugins
                   WHERE placement != 'event'
                     AND placement != 'eventh'
                         " . $sql_filter;
        $invisible_plugins = serendipity_db_query($sql);
        if (is_array($invisible_plugins)) {
            $sidebars[]   = 'NONE';
            $opts['NONE'] = NONE;
        }
    }

    $opts['event'] = PLUGIN_ACTIVE;
    $opts['eventh'] = PLUGIN_INACTIVE;


    $data['event_only'] = $event_only;
    if (!$event_only) {
        $data['is_first'] = true;
    }

    $data['serendipity_setFormToken'] = serendipity_setFormToken();
    $data['serendipity_setFormTokenUrl'] = serendipity_setFormToken('url');

    /* Block display the plugins per placement location. */
    if ($event_only) {
        $plugin_placements = array('event', 'eventh');
    } else {
        $plugin_placements = $sidebars;
    }
    $data['plugin_placements'] = $plugin_placements;

    static $users = array();
    if (empty($users)) {
        $users = serendipity_fetchUsers('', 'hidden');
    }
    $data['users'] = $users;

    $i = 0;
    foreach ($plugin_placements as $plugin_placement) {
        if (!$event_only && $plugin_placement == 'NONE') {
            $is_invisible     = true;
        } else {
            $is_invisible     = false;
        }
        $ptitle = $opts[$plugin_placement];
        $pid    = $plugin_placement;

        echo '<td class="pluginmanager_side pluginmanager_' . ($event_only ? 'event' : 'sidebar') . '">'."\n";
        echo '<div class="heading">' . $ptitle . '</div>'."\n";
        echo '<ol id="' . $pid . '_col" class="pluginmanager_container">'."\n";
        if ($is_invisible) {
            $plugins = $invisible_plugins;
        } else {
            $plugins = serendipity_plugin_api::enum_plugins($plugin_placement);
        }

        if (!is_array($plugins)) {
            echo "</ol>\n</td>\n";
            continue;
        }

        $sort_idx = 0;
        foreach ($plugins as $plugin_data) {
            $i++;
            $plugin  =& serendipity_plugin_api::load_plugin($plugin_data['name'], $plugin_data['authorid']);
            $key     = urlencode($plugin_data['name']);
            $css_key = 's9ycid' . str_replace('%', '-', $key);
            $is_plugin_owner    = ($plugin_data['authorid'] == $serendipity['authorid'] || serendipity_checkPermission('adminPluginsMaintainOthers'));
            $is_plugin_editable = ($is_plugin_owner || $plugin_data['authorid'] == '0');
            $cname = explode(':', $plugin_data['name']);

            if (!is_object($plugin)) {
                $name = $title = ERROR . '!';
                $desc          = ERROR . ': ' . $plugin_data['name'];
                $can_configure = false;
            } else {
                /* query for its name, description and configuration data */

                $bag = new serendipity_property_bag;
                $plugin->introspect($bag);

                $name  = serendipity_specialchars($bag->get('name'));

                $desc  = '<details class="plugin_data">';
                $desc .= '<summary><var class="perm_name">'.$cname[0].'</var></summary>';
                $desc .= '<div class="plugin_desc clearfix">' . serendipity_specialchars($bag->get('description')) . '</div>';
                $desc .= '<span class="block_level">' . VERSION  . ': ' . $bag->get('version') . '</span>';
                $desc .= '</details>';

                $title = serendipity_plugin_api::get_plugin_title($plugin, '[' . $name . ']');

                if ($bag->is_set('configuration') && ($plugin->protected === FALSE || $plugin_data['authorid'] == '0' || $plugin_data['authorid'] == $serendipity['authorid'] || serendipity_checkPermission('adminPluginsMaintainOthers'))) {
                    $can_configure = true;
                } else {
                    $can_configure = false;
                }
            }

            if ($opts === null) {
                $opts = array(
                    'left'  => LEFT,
                    'right' => RIGHT,
                    'hide'  => HIDDEN
                );
            }

            $event_opts = array(
                            'event'  => PLUGIN_ACTIVE,
                            'eventh' => PLUGIN_INACTIVE,
                        );

            if ($event_only) {
                $gopts = $event_opts;
            } else {
                $gopts = $opts;
            }

            $data['placement'][$plugin_placement]['plugin_data'][$i]['sort_idx'] = $sort_idx;
            $data['placement'][$plugin_placement]['plugin_data'][$i]['css_key'] = $css_key;
            $data['placement'][$plugin_placement]['plugin_data'][$i]['is_plugin_editable'] = $is_plugin_editable;
            $data['placement'][$plugin_placement]['plugin_data'][$i]['is_plugin_owner'] = $is_plugin_owner;
            $data['placement'][$plugin_placement]['plugin_data'][$i]['name'] = $plugin_data['name'];
            $data['placement'][$plugin_placement]['plugin_data'][$i]['authorid'] = $plugin_data['authorid'];
            $data['placement'][$plugin_placement]['plugin_data'][$i]['can_configure'] = $can_configure;
            $data['placement'][$plugin_placement]['plugin_data'][$i]['key'] = $key;
            $data['placement'][$plugin_placement]['plugin_data'][$i]['title'] = $title;
            $data['placement'][$plugin_placement]['plugin_data'][$i]['desc'] = $desc;
            $data['placement'][$plugin_placement]['plugin_data'][$i]['placement'] = $plugin_data['placement'];
            $data['placement'][$plugin_placement]['plugin_data'][$i]['gopts'] = $gopts;
            $sort_idx++;
        }

        echo '</ol></td>';
    }

    $data['total'] = $i;
    return serendipity_smarty_show('admin/show_plugins.fnc.tpl', $data);
}

/**
 * Show the plugin configuration
 *
 * @access public
 * @param  object   A plugin object
 * @param  object   The plugins property bag object
 * @param  string   The name of the plugin
 * @param  string   The description of the plugin
 * @param  array    The property bag 'configuration' array, holding the array of config items.
 * @param  boolean  Shows the surrounding HTML table?
 * @param  boolean  Shows the FORM submit button?
 * @param  boolean  Shows a plugin's "example" method output?
 * @param  boolean  Spawn a plugins' configuration WYSIWYG items?
 * @param  string   The array index name of POSTed values ($serendipity['POST'][xxx])
 * @param  array    An array that groups certain config keys
 * @return string   The configuration HTML
 */
function serendipity_plugin_config(&$plugin, &$bag, &$name, &$desc, &$config_names, $showTable = true, $showSubmit = true, $showExample = true, $spawnNuggets = true, $postKey = 'plugin', $config_groups = NULL) {
    global $serendipity;

    if (empty($config_names)) {
        return false;
    }

    $tfile = "/admin/plugin_config_item.tpl";

    $data = array();

    if ($showSubmit && $postKey != 'plugin') {
        $data['showSubmit_head'] = true;
    }

    if ($showTable) {
        $data['showTable'] = true;
    }

    $elcount = 0;
    $htmlnugget = array();

    $plugin_options = array();
    $data['config_names'] = $config_names;

    foreach ($config_names as $config_item) {
        $elcount++;
        $cbag = new serendipity_property_bag;
        $plugin->introspect_config_item($config_item, $cbag);

        $data['cname']  = $cname = serendipity_specialchars($cbag->get('name'));
        $data['cdesc']  = $cdesc = serendipity_specialchars($cbag->get('description'));
        $value          = $plugin->get_config($config_item, 'unset');
        $lang_direction = serendipity_specialchars($cbag->get('lang_direction'));

        if (empty($lang_direction)) {
            $lang_direction = LANG_DIRECTION;
        }

        /* Apparently no value was set for this config item */
        if ($value === 'unset') {
            /* Try and the default value for the config item */
            $value = $cbag->get('default');

            /* Still, we don't have a value, try and get (bool)false - from an old plugin */
            if ($value === '') {
                $value = $plugin->get_config($config_item, false, true);
            }
        }

        if (isset($_POST['serendipity'][$postkey][$config_item])) {
            if (is_array($_POST['serendipity'][$postkey][$config_item])) {
                $hvalue = $_POST['serendipity'][$postkey][$config_item];
                array_walk($hvalue, 'serendipity_specialchars');
            } else {
                $hvalue = serendipity_specialchars($_POST['serendipity'][$postkey][$config_item]);
            }
        } else {
            $hvalue = serendipity_specialchars($value);
        }

        $radio      = array();
        $select     = array();
        $per_row    = null;
        $text_rows  = null;
        $input_type = null;

        $is_multi_select = false;
        $ctype    = $cbag->get('type');

        $assign_plugin_config = function($data) use (&$plugin_options, $tfile, $config_item) {
        $plugin_options[$config_item] = array(
                                'config' => serendipity_smarty_show($tfile, $data),
                                'ctype'  => $data['ctype']
                        );
        };


        switch ($ctype) {
            case 'seperator':// compat, due being misspelled
            case 'separator':
            case 'suboption':
                $assign_plugin_config($data);

                break;

            case 'multiselect':
                $data['is_multi_select'] = $is_multi_select = true;

            case 'select':
                $data['ctype'] = 'select';
                if (is_array($hvalue)) {
                    $selected_options = $hvalue;
                } elseif ($is_multi_select) {
                    $selected_options = explode('^', $hvalue);
                } else {
                    $selected_options = array($hvalue => $hvalue);
                }

                $data['selected_options'] = $selected_options;
                $data['pre_selected']     = $pre_selected = (array)$cbag->get('select_preselected');
                $data['select_size']      = $select_size  = $cbag->get('select_size');
                $data['select']           = $select       = $cbag->get('select_values');

                $assign_plugin_config($data);

                break;

            case 'tristate':
                $data['ctype'] = 'tristate';
                $per_row = 3;
                $radio['value'][] = 'default';
                $radio['desc'][]  = USE_DEFAULT;

            case 'boolean':
                $data['ctype'] = 'boolean';
                $radio['value'][] = 'true';
                $radio['desc'][]  = YES;

                $radio['value'][] = 'false';
                $radio['desc'][]  = NO;

            case 'radio':
                $data['ctype'] = 'radio';
                if (!count($radio) > 0) {
                    $radio = $cbag->get('radio');
                }

                if (empty($per_row)) {
                    $per_row = $cbag->get('radio_per_row');
                    if (empty($per_row)) {
                        $per_row = 2;
                    }
                }
?>
        <tr>
            <td style="border-bottom: 1px solid #000000; vertical-align: top"><strong><?php echo $cname; ?></strong>
<?php
                if ($cdesc != '') {
?>
                <br /><span  style="color: #5E7A94; font-size: 8pt;">&nbsp;<?php echo $cdesc; ?></span>
<?php
                }
?>
            </td>
            <td style="border-bottom: 1px solid #000000; vertical-align: middle;" width="250">
<?php
                $counter = 0;
                foreach($radio['value'] AS $radio_index => $radio_value) {
                    $id = serendipity_specialchars($config_item . $radio_value);
                    $counter++;
                    $checked = "";

                    if ($radio_value == 'true' && ($hvalue === '1' || $hvalue === 'true')) {
                        $checked = " checked";
                    } elseif ($radio_value == 'false' && ($hvalue === '' || $hvalue === 'false')) {
                        $checked = " checked";
                    } elseif ($radio_value == $hvalue) {
                        $checked = " checked";
                    }
                    $data['radio_button'][$radio_index]['id'] = $id;
                    $data['radio_button'][$radio_index]['checked'] = $checked;
                    $data['radio_button'][$radio_index]['counter'] = $counter;
                    $data['radio_button'][$radio_index]['value'] = $radio_value;
                    $data['radio_button'][$radio_index]['index'] = serendipity_specialchars($radio['desc'][$radio_index]);
                }

                $assign_plugin_config($data);

                    if ($counter == 1) {
?>
                <div>
<?php
                    }
?>
                    <input class="direction_<?php echo $lang_direction; ?> input_radio" type="radio" id="serendipity_plugin_<?php echo $id; ?>" name="serendipity[<?php echo $postKey; ?>][<?php echo $config_item; ?>]" value="<?php echo $radio_value; ?>" <?php echo $checked ?> title="<?php echo htmlspecialchars($radio['desc'][$radio_index]); ?>" />
                        <label for="serendipity_plugin_<?php echo $id; ?>"><?php echo htmlspecialchars($radio['desc'][$radio_index]); ?></label>
<?php
                    if ($counter == $per_row) {
                        $counter = 0;
?>
                </div>
<?php
                    }
                }
                // add a closing div tag to the last element here, if uneven elements !! 2013-01-15
                if ($counter == 1) {
?>
                </div>
<?php
                }
?>
            </td>
        </tr>
<?php
                break;

            case 'string':
                $data['ctype'] = 'string';
                if (empty($input_type)) {
                    $input_type = $cbag->get('input_type');
                    if (empty($input_type)) {
                        $input_type = "text";
                    }
                }
                $data['input_type'] = $input_type;
                $assign_plugin_config($data);

                break;

            case 'html':
            case 'text':
                if (empty($text_rows)) {
                    $text_rows = $cbag->get('rows');
                    if (empty($text_rows)) {
                        $text_rows = 20;
                    }
                }
?>
        <tr>
            <td colspan="2"><strong><?php echo $cname; ?></strong>
                &nbsp;<span style="color: #5E7A94; font-size: 8pt;">&nbsp;<?php echo $cdesc; ?></span>
            </td>
        </tr>

        <tr>
            <td colspan="2">
                <div>
                    <textarea class="direction_<?php echo $lang_direction; ?>" style="width: 100%" id="nuggets<?php echo $elcount; ?>" name="serendipity[<?php echo $postKey; ?>][<?php echo $config_item; ?>]" rows="<?php echo $text_rows; ?>" cols="80"><?php echo $hvalue; ?></textarea>
                </div>
            </td>
        </tr>
<?php
                if ($cbag->get('type') == 'html') {
                    $htmlnugget[] = $elcount;
                    if (!function_exists('serendipity_emit_htmlarea_code')) {
                        @include_once dirname(__FILE__) . '/functions_entries_admin.inc.php';
                    }
                    // use SpawnMulti false per default (for multi nugget textareas, eg linklist sidebar plugin) - but where do we use jsname though?
                    serendipity_emit_htmlarea_code("nuggets{$elcount}","nuggets{$elcount}");
                }
                $assign_plugin_config($data);

               break;

            case 'content':
                $data['ctype'] = 'content';
                $data['cbag_default'] = $cbag->get('default');
                $assign_plugin_config($data);

                break;

            case 'custom':
                $data['ctype'] = 'custom';
                $data['cbag_custom'] = $cbag->get('custom');
                $assign_plugin_config($data);

                break;

            case 'hidden':
                $data['ctype'] = 'hidden';
                $data['cbag_value'] = $cbag->get('value');
                $assign_plugin_config($data);

            case 'hidden':
                ?><tr><td colspan="2"><input class="direction_<?php echo $lang_direction; ?>" type="hidden" name="serendipity[<?php echo $postKey; ?>][<?php echo $config_item; ?>]" value="<?php echo $cbag->get('value'); ?>" /></td></tr><?php
                break;

            case 'media':
                $data['ctype'] = 'media';
                // Print the HTML to display the popup media selector
                $preview_width = $cbag->get('preview_width');
                if (!$preview_width || $preview_width == "") {
                  $preview_width = '400px';
                }
                $preview_height = $cbag->get('preview_height');
                if (!$preview_height || $preview_height == "") {
                  $preview_height = '100px';
                }
                $data['preview_width']  = $preview_width;
                $data['preview_height'] = $preview_height;
                $data['value'] = $value;

                $assign_plugin_config($data);

                break;

            case 'sequence':
                $data['ctype'] = 'sequence';
                // For the drag-n-drop to work, the list must be included in
                // a container (probably an <ol>) that JavaScript can access
                // (easiest by ID), with <li> children that have unique IDs,
                // and handles with ids of 'g'.$li_id.
                // I can't get it to work unless there's a class of
                // pluginmanager_container on the ol, either.
                // The drag-n-drop returns the list of IDs in order.
                $sequencejs_output = $serendipity['sequencejs_output'];
                if (!$sequencejs_output) {
                    echo '<script src="' . serendipity_getTemplateFile('dragdrop.js') . '" type="text/javascript"></script>';
                    $serendipity['sequencejs_output'] = true;
                }

                // I want this generic sequence widget to hide the ID, but
                // display a name or description with an optional picture.
                // (This would allow users to identify choices by thumbnail.)
                // Therefore, I need an array with keys 'id', 'display', and
                // 'imgurl' (or similar) to generate each list item.

                // Get the data we need to display the list
                if (!$value) {
                    $value = $eventData['default'];
                }
                $cname = $cbag->get('name');
                $cdesc = $cbag->get('description');
                $checkable = $cbag->get('checkable');

                /** Unordered array of values */
                $items = $cbag->get('values');
                if (!is_array($items)) { $items = null; }
                /** Array specifying order to use values in $items */
                $order = null;
                if ($value) {
                    $store_order = $order = explode(',', $value);
                }
                $uparrow_img = serendipity_getTemplateFile('admin/img/uparrow.png');
                $downarrow_img = serendipity_getTemplateFile('admin/img/downarrow.png');

                // $items is the list of things to sequence.  It's not in
                // order, and reordering PHP arrays is problematic.  So
                // we keep it unordered, and access its values according
                // to another array (appropriately named $order).
                if (is_array($items)) {
                    // Allow simple value for any sequence item
                    foreach ($items as $key => $item) {
                        if (!is_array($item)) {
                            // Replace this item with an empty array
                            unset($items[$key]);
                            $items[$item] = array();
                        }
                    }

                    // Make sure all the items are in the order list; new items
                    // go on the end (new items could have been added without
                    // this config item being updated)
                    // Also fill out thumbnails and display names
                    foreach ($items as $id => $junk) {
                        if ($order == null) {
                            $order = array($id);
                        } else if (!in_array($id, $order)) {
                            $order[] = $id;
                        }
                        // If there's no defined display name, default to the ID
                        if (!isset($items[$id]['display'])) {
                            $items[$id]['display'] = $id;
                        }
                        // If there's no image, we just won't display anything.
                    }

                    // Make sure all the items to be ordered exist!  Otherwise
                    // we could try to sequence nothing.
                    $filtered = array();
                    foreach ($order as $id) {
                        if (array_key_exists($id, $items)) {
                            $filtered[] = $id;
                        }
                    }
                    $order = $filtered;
                } else {
                    // If there's nothing to sequence, make the order in
                    // which to use them valid, but empty
                    $order = array();
                }

                // Start the row, add one cell for the name and description
                print <<<EOS
<tr>
<td style="border-bottom: 1px solid #000000; vertical-align: top">
  <strong>$cname</strong>
  <br /><span style="color: #5E7A94; font-size: 8pt;">$cdesc</span>
</td>

EOS;
                // Now add one cell for the list
                print <<<EOS
<td style="border-bottom: 1px solid #000000; vertical-align: middle">

EOS;
                // Print the list
                print <<<EOS
  <input type="hidden" name="serendipity[$postKey][$config_item]" id="${config_item}_value" value="$value" />
  <noscript>
    <!-- Replace standard submit button when using up/down submits -->
    <input type="hidden" name="SAVECONF" value="Save" />
  </noscript>
  <ol id="$config_item" class="sequence_container pluginmanager_container">

EOS;
                $sort_idx = 0;
                $last = count($order) - 1;
                foreach ($order as $id) {
                    // Create the variables required to print this item
                    if ($sort_idx > 0) {
                        $swapping = $order;
                        $temp = $swapping[(int)$sort_idx];
                        $swapping[(int)$sort_idx] = $swapping[(int)($sort_idx - 1)];
                        $swapping[(int)($sort_idx - 1)] = $temp;
                        $oneup = implode(',' , $swapping);
                    }
                    if ($sort_idx < $last) {
                        $swapping = $order;
                        $temp = $swapping[(int)$sort_idx];
                        $swapping[(int)$sort_idx] = $swapping[(int)($sort_idx + 1)];
                        $swapping[(int)($sort_idx + 1)] = $temp;
                        $onedown = implode(',' , $swapping);
                    }


                    // Print the HTML
                    //
                    // Set the item and its ID
                    print '    <li id="'.$id.'" class="sequence_item pluginmanager_item_even">' . "\n";
                    // Make a handle with ID 'g$id'
                    print '      <div id="g'.$id.'" class="pluginmanager_grablet sequence_grablet"><a href="#"></a></div>' . "\n";

                    if ($checkable) {
                        print '         <input type="checkbox" onclick="sort_' . $config_item . '_Sequence();" name="serendipity[' . $postKey . ']' . '[activate][' . $config_item . '][' . $id . ']" ' . (in_array($id, $store_order) ? ' checked="checked" ' : '') . ' value="true" id="activate_' . $id . '" />' . "\n";
                    }

                    // Add the item contents
                    print '      <span>'.$items[$id]['display'].'</span>' . "\n";
                    if (isset($items[$id]['img'])) {
                        print '      <img src="'.$items[$id]['img'].'" />' . "\n";
                    }
                    // Luddite submit buttons (please, think of the scriptless!)
                    print "<noscript><div>\n";
                    if ($sort_idx == 0) {
                        // Skip the move-up submit button
                        print "&nbsp;\n";
                    } else {
                        print <<<EOS
  <button type="submit" name="serendipity[$postKey][override][$config_item]" value="$oneup">
    <img src="$uparrow_img" alt="Move Up">
  </button>

EOS;
                    }
                    if ($sort_idx == $last) {
                        // Skip the move-down submit button
                        print "&nbsp;\n";
                    } else {
                        print <<<EOS
  <button type="submit" name="serendipity[$postKey][override][$config_item]" value="$onedown">
    <img src="$downarrow_img" alt="Move Down">
  </button>

EOS;
                    }
                    print "</div></noscript>\n";
                    // Close the item
                    print '    </li>'."\n";
                    // Next, please
                    $sort_idx++;
                }
                if (!is_array($items) or empty($order)) {
                    // Print the empty message
                    print(NONE);
                }
                 // added missing closing ol tag - 2013-01-15
                 print <<<EOS
  </ol>
EOS;
                // Print the Javascript to drag-n-drop the list
                // Finish the row
                $assign_plugin_config($data);

    function init_${config_item}_Sequence()
    {
        var lst = document.getElementById("${config_item}");
        DragDrop.makeListContainer(lst, '${config_item}_group');
        lst.onDragOut = function() {
            sort_${config_item}_Sequence();
        };
    }
    addLoadEvent(init_${config_item}_Sequence);
</script>

EOS;
                // Finish the row
                // added missing closing tr tag - 2013-01-15
                print <<<EOS
  </td>
</tr>
EOS;
                break;

            default:
                $data['ctype'] = 'default';
                // Unknown configuration key. Let the plugin handle it.
                $addData = func_get_args();
                $eventData = array(
                    'config_item' => $config_item,
                    'cbag'        => $cbag,
                    'plugin'      => $plugin,
                    'value'       => $value,
                    'bag'         => $bag,
                    'postKey'     => $postKey
                );
                ob_start();
                serendipity_plugin_api::hook_event('backend_pluginconfig_' . $ctype, $eventData, $addData);
                $plugin_options[$config_item]['config'] = ob_get_contents();
                $plugin_options[$config_item]['ctype'] = 'default';
                ob_end_clean();
                break;
        }

    }
    $data['config_groups'] = $config_groups;
    $data['plugin_options'] = $plugin_options;

    if (is_array($config_groups)) {
        $hid = 0;
        $folded = true;
?>
        <tr>
            <td colspan="2">
                <div align="right">
                    <a style="border:0; text-decoration: none" href="#" onClick="showConfigAll(<?php echo sizeof($config_groups); ?>)" title="<?php echo TOGGLE_ALL; ?>"><img src="<?php echo serendipity_getTemplateFile('img/'. ($folded === true ? 'plus' : 'minus') .'.png') ?>" id="optionall" alt="+/-" border="0" />&nbsp;<?php echo TOGGLE_ALL; ?></a></a><br />
                </div>
            </td>
        </tr>
<?php
        foreach($config_groups AS $config_header => $config_groupkeys) {
            $hid++;
            echo '<tr>';
            echo '<td colspan="2">';

            echo '<h2>';
            echo '<a style="border:0; text-decoration: none;" href="#" onClick="showConfig(\'el' . $hid . '\'); return false" title="' . TOGGLE_OPTION . '"><img src="' . serendipity_getTemplateFile('img/'. ($folded === true ? 'plus' : 'minus') .'.png') . '" id="optionel' . $hid . '" alt="+/-" border="0" />&nbsp;';
            echo $config_header;
            echo '</a>';
            echo '</h2>';
            echo '</td>';
            echo '</tr>';

            echo '<tr>';
            echo '<td colspan="2">';
            echo '<table class="plugin_optiongroup" id="el' . $hid . '" border="0" cellspacing="0" cellpadding="3" width="100%">';

            foreach($config_groupkeys AS $config_groupkey) {
                unset($plugin_options[$config_groupkey]);
            }
            echo '</table>';

            echo '<script type="text/javascript" language="JavaScript">';
            echo 'document.getElementById("el' . $hid . '").style.display = "none";' . "\n";
            echo '</script>';

            echo '</td>';
            echo '</tr>';
        }

        echo '<tr><td colspan="2" style="height: 100px" id="configuration_footer">&nbsp;</td></tr>';
    }

    $data['plugin_options_ungrouped'] = $plugin_options;

    if ($showSubmit) {
        $data['showSubmit_foot'] = true;
    }

    if ($showExample && method_exists($plugin, 'example') ) {
        $data['showExample'] = true;
        $data['plugin_example'] = $plugin->example();
    }

    if ($spawnNuggets && isset($serendipity['wysiwyg']) && $serendipity['wysiwyg'] && count($htmlnugget) > 0) {
        $data['spawnNuggets'] = true;
        $ev = array('nuggets' => $htmlnugget, 'skip_nuggets' => false);
        serendipity_plugin_api::hook_event('backend_wysiwyg_nuggets', $ev);

    return serendipity_smarty_show('admin/plugin_config.tpl', $data);
}
