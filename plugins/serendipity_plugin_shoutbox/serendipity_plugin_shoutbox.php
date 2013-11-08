<?php #

/* Contributed by Matthias Lange (http://blog.dinnri.de/ml/) */

@serendipity_plugin_api::load_language(dirname(__FILE__));

class serendipity_plugin_shoutbox extends serendipity_plugin
{
    var $title = PLUGIN_SHOUTBOX_NAME;

    function introspect(&$propbag)
    {
        global $serendipity;

        $propbag->add('name',          PLUGIN_SHOUTBOX_NAME);
        $propbag->add('description',   PLUGIN_SHOUTBOX_BLAHBLAH);
        $propbag->add('stackable',     false);
        $propbag->add('author',        'Matthias Lange');
        $propbag->add('version',       '1.02');
        $propbag->add('requirements',  array(
            'serendipity' => '0.8',
            'smarty'      => '2.6.7',
            'php'         => '4.1.0'
        ));

        $propbag->add('configuration', array(
                                             'wordwrap',
                                             'max_chars',
                                             'max_entries',
                                             'dateformat',
                                             'box_cols',
                                             'box_rows'));

        $propbag->add('groups', array('FRONTEND_FEATURES'));
    }

    function introspect_config_item($name, &$propbag)
    {
        switch($name) {
            case 'wordwrap':
                $propbag->add('type', 'string');
                $propbag->add('name', PLUGIN_SHOUTBOX_WORDWRAP);
                $propbag->add('description', PLUGIN_SHOUTBOX_WORDWRAP_BLAHBLAH);
                $propbag->add('default',     30);
                break;

            case 'max_chars':
                $propbag->add('type', 'string');
                $propbag->add('name', PLUGIN_SHOUTBOX_MAXCHARS);
                $propbag->add('description', PLUGIN_SHOUTBOX_MAXCHARS_BLAHBLAH);
                $propbag->add('default',     120);
                break;

            case 'max_entries':
                $propbag->add('type', 'string');
                $propbag->add('name', PLUGIN_SHOUTBOX_MAXENTRIES);
                $propbag->add('description', PLUGIN_SHOUTBOX_MAXENTRIES_BLAHBLAH);
                $propbag->add('default',     15);
                break;

            case 'dateformat':
                $propbag->add('type', 'string');
                $propbag->add('name', GENERAL_PLUGIN_DATEFORMAT);
                $propbag->add('description', sprintf(GENERAL_PLUGIN_DATEFORMAT_BLAHBLAH, '%a, %d.%m.%Y %H:%M'));
                $propbag->add('default',     '%a, %d.%m.%Y %H:%M');
                break;

            case 'box_cols':
                $propbag->add('type', 'string');
                $propbag->add('name', GENERAL_PLUGIN_BOX_COLS);
                $propbag->add('description', GENERAL_PLUGIN_BOX_COLS_BLAHBLAH);
                $propbag->add('default',     '15');
                break;

            case 'box_rows':
                $propbag->add('type', 'string');
                $propbag->add('name', GENERAL_PLUGIN_BOX_ROWS);
                $propbag->add('description', GENERAL_PLUGIN_BOX_ROWS_BLAHBLAH);
                $propbag->add('default',     '4');
                break;

            default:
                    return false;
        }
        return true;
    }

    function generate_content(&$title)
    {
        global $serendipity;

        $title       = $this->title;
        $max_entries = $this->get_config('max_entries');
        $max_chars   = $this->get_config('max_chars');
        $wordwrap    = $this->get_config('wordwrap');
        $dateformat  = $this->get_config('dateformat');
        $box_cols    = $this->get_config('box_cols');
        $box_rows    = $this->get_config('box_rows');

        // Create table, if not yet existant
        if (!$this->get_config('version')) {
            $q   = "CREATE TABLE {$serendipity['dbPrefix']}shoutbox (
                        id {AUTOINCREMENT} {PRIMARY},
                        timestamp int(10) {UNSIGNED} NULL,
                        ip varchar(45) default NULL,
                        body text
                    )";
            $sql = serendipity_db_schema_import($q);
            $this->set_config('version', '2');
        }
        if ($this->get_config('version') == '1.0') {
            $q = "ALTER TABLE {$serendipity['dbPrefix']}shoutbox CHANGE COLUMN ip ip VARCHAR(45)";
            $sql = serendipity_db_schema_import($q);
            $this->set_config('version', '2');
        }

        //Put new shout into the database if necessary
        if (!empty($_REQUEST['action']) && $_REQUEST['action'] == 'fillshoutbox' && $_REQUEST['serendipity']['shouttext'] != '') {

            $sql =  sprintf(
                      "INSERT INTO %sshoutbox (
                            timestamp,
                            ip,
                            body
                       ) VALUES (
                            %s,
                            '%s',
                            '%s'
                       )",

                    $serendipity['dbPrefix'],
                    time(),
                    serendipity_db_escape_string($_SERVER['REMOTE_ADDR']),
                    serendipity_db_escape_string($_REQUEST['serendipity']['shouttext']));
            serendipity_db_query($sql);
        }
        if (!empty($serendipity['GET']['action']) && $serendipity['GET']['action'] == 'shoutboxdelete'
          && $_SESSION['serendipityAuthedUser'] === true) {
            $sql  = sprintf("DELETE from %sshoutbox
                              WHERE id = %d",
                                    $serendipity['dbPrefix'],
                                    (int)$serendipity['GET']['comment_id']);
            serendipity_db_query($sql);
         }

        if (!$max_entries || !is_numeric($max_entries) || $max_entries < 1) {
            $max_entries = 15;
        }

        if (!$max_chars || !is_numeric($max_chars) || $max_chars < 1) {
            $max_chars = 120;
        }

        if (!$wordwrap || !is_numeric($wordwrap) || $wordwrap < 1) {
            $wordwrap = 30;
        }

        if (!$dateformat || strlen($dateformat) < 1) {
            $dateformat = '%a, %d.%m.%Y %H:%M';
        }

        if (!$box_cols || !is_numeric($box_cols) || $box_cols < 1) {
            $box_cols = 15;
        }

        if (!$box_rows || !is_numeric($box_rows) || $box_rows < 1) {
            $box_rows = 4;
        }
       ?>
       <form action="<?php echo serendipity_currentURL(true); ?>" method="post">
           <input type="hidden" name="action" value="fillshoutbox" />
           <textarea name="serendipity[shouttext]" rows="<?php echo $box_rows; ?>" cols="<?php echo $box_cols; ?>" style="width: 90%"></textarea>
           <input name='submit' type='submit' value='<?php echo PLUGIN_SHOUTBOX_SUBMIT; ?>' />
       </form>
<?php
        $q = 'SELECT    s.body              AS comment,
                        s.timestamp         AS stamp,
                        s.id                AS comment_id
                FROM    '.$serendipity['dbPrefix'].'shoutbox AS s
            ORDER BY    s.timestamp DESC
               LIMIT ' . $max_entries;
?>
<div class="serendipity_shoutbox">
<?php
        $sql = serendipity_db_query($q);
        if ($sql && is_array($sql)) {
            foreach($sql AS $key => $row) {
                $comments = wordwrap(strip_tags($row['comment']), $max_chars, '@@@', 1);
                $aComment = explode('@@@', $comments);
                $comment  = $aComment[0];
                if (count($aComment) > 1) {
                    $comment .= ' [...]';
                }

                $deleteLink = "";
                if ($_SESSION['serendipityAuthedUser'] === true) {
                    $deleteLink =  '<a href="' . $serendipity['baseURL']
                                  . '?serendipity[action]=shoutboxdelete&amp;serendipity[comment_id]='
                                  . $row['comment_id'] . '">' . PLUGIN_SHOUTBOX_DELETE . '</a>';
                }
                $entry = array('comment' => $comment);
                serendipity_plugin_api::hook_event('frontend_display', $entry);
                $entry['comment'] = wordwrap($entry['comment'], $wordwrap, "\n", 1);

                echo '<div class="serendipity_shoutbox_date">' . htmlspecialchars(serendipity_strftime($dateformat, $row['stamp'])) . '</div>' . "\n"
                     . '<div class="serendipity_shoutbox_comment">' . $entry['comment'] . '</div>' . "\n"
                     . '<div class="serendipity_shoutbox_delete">' . $deleteLink . '</div>' . "\n\n";
            }
        }
?>
</div>
<?php
    }
}

/* vim: set sts=4 ts=4 expandtab : */
?>
