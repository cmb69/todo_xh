<?php

/**
 * The plugin controller.
 *
 * PHP version 5
 *
 * @category  CMSimple_XH
 * @package   Todo
 * @author    Christoph M. Becker <cmbecker69@gmx.de>
 * @copyright 2012-2014 Christoph M. Becker <http://3-magi.net/>
 * @license   http://www.gnu.org/licenses/gpl-3.0.en.html GNU GPLv3
 * @link      http://3-magi.net/?CMSimple_XH/Todo_XH
 */

/**
 * The plugin controller.
 *
 * @category CMSimple_XH
 * @package  Todo
 * @author   Christoph M. Becker <cmbecker69@gmx.de>
 * @license  http://www.gnu.org/licenses/gpl-3.0.en.html GNU GPLv3
 * @link     http://3-magi.net/?CMSimple_XH/Todo_XH
 */
class Todo_Controller
{
    /**
     * Dispatches on plugin related requests.
     *
     * @return void
     */
    public static function dispatch()
    {
        if (XH_ADM) {
            if (self::isAdministrationRequested()) {
                self::handleAdministration();
            }
        }
    }

    /**
     * Returns whether the administration has been requested.
     *
     * @return bool
     *
     * @global string Whether the plugin administration has been requested.
     */
    protected static function isAdministrationRequested()
    {
        global $todo;

        return isset($todo) && $todo == 'true';
    }

    /**
     * Handles the plugin administration.
     *
     * @return void
     *
     * @global string The value of the <var>admin</var> GP parameter.
     * @global string The value of the <var>action</var> GP parameter.
     * @global string The (X)HTML fragment of the contents area.
     */
    protected static function handleAdministration()
    {
        global $admin, $action, $o;

        $o .= print_plugin_admin('on');
        switch ($admin) {
        case '':
            $o .= self::version() . tag('hr') . self::systemCheck();
            break;
        case 'plugin_main':
            switch ($action) {
            case 'reset_votes':
                $o .= self::resetVotes();
                break;
            default:
                $o .= self::adminMain();
            }
            break;
        default:
            $o .= plugin_admin_common($action, $admin, 'todo');
        }
    }

    /**
     * Returns the version information view.
     *
     * @return string (X)HTML.
     *
     * @global array The paths of system files and folders.
     */
    protected static function version()
    {
        global $pth;

        return '<h1><a href="http://3-magi.net/?CMSimple_XH/Todo_XH">Todo_XH'
            . '</a></h1>'
            . "\n"
            . tag(
                'img class="todo_plugin_icon" src="' . $pth['folder']['plugins']
                . 'todo/todo.png" alt="Plugin icon"'
            ) . "\n"
            . '<p style="margin-top: 1em">Version: ' . TODO_VERSION . '</p>' . "\n"
            . '<p>Copyright &copy; 2012-2014 <a href="http://3-magi.net/">'
            . 'Christoph M. Becker</a></p>' . "\n"
            . '<p class="todo_license">'
            . 'This program is free software: you can redistribute it and/or modify'
            . ' it under the terms of the GNU General Public License as published by'
            . ' the Free Software Foundation, either version 3 of the License, or'
            . ' (at your option) any later version.</p>' . "\n"
            . '<p class="todo_license">'
            . 'This program is distributed in the hope that it will be useful,'
            . ' but WITHOUT ANY WARRANTY; without even the implied warranty of'
            . ' MERCHAN&shy;TABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the'
            . ' GNU General Public License for more details.</p>' . "\n"
            . '<p class="todo_license">'
            . 'You should have received a copy of the GNU General Public License'
            . ' along with this program.  If not, see'
            . ' <a href="http://www.gnu.org/licenses/">http://www.gnu.org/licenses/'
            . '</a>.</p>' . "\n";
    }

    /**
     * Returns the requirements information view.
     *
     * @return string (X)HTML.
     *
     * @global array The paths of system files and folders.
     * @global array The localization of the core.
     * @global array The localization of the plugins.
     */
    protected static function systemCheck()
    {
        global $pth, $tx, $plugin_tx;

        define('TODO_PHP_VERSION', '4.3.0');
        $ptx = $plugin_tx['todo'];
        $imgdir = $pth['folder']['plugins'] . 'todo/images/';
        $ok = tag('img src="' . $imgdir . 'ok.png" alt="ok"');
        $warn = tag('img src="' . $imgdir . 'warn.png" alt="warning"');
        $fail = tag('img src="' . $imgdir . 'fail.png" alt="failure"');
        $o = '<h4>' . $ptx['syscheck_title'] . '</h4>'
            . (version_compare(PHP_VERSION, TODO_PHP_VERSION) >= 0 ? $ok : $fail)
            . '&nbsp;&nbsp;' . sprintf($ptx['syscheck_phpversion'], TODO_PHP_VERSION)
            . tag('br') . "\n";
        foreach (array('date', 'mbstring', 'pcre', 'session') as $ext) {
            $o .= (extension_loaded($ext) ? $ok : $fail)
                . '&nbsp;&nbsp;' . sprintf($ptx['syscheck_extension'], $ext)
                . tag('br') . "\n";
        }
        $o .= (!get_magic_quotes_runtime() ? $ok : $fail)
            . '&nbsp;&nbsp;'.$ptx['syscheck_magic_quotes']
            . tag('br') . tag('br') . "\n";
        $o .= (strtoupper($tx['meta']['codepage']) == 'UTF-8' ? $ok : $warn)
            . '&nbsp;&nbsp;' . $ptx['syscheck_encoding'] . tag('br') . "\n";
        $filename = $pth['folder']['plugins'] . 'jquery/jquery.inc.php';
        $o .= (file_exists($filename) ? $ok : $fail)
            . '&nbsp;&nbsp;' . $ptx['syscheck_jquery']
            . tag('br') . tag('br') . "\n";
        foreach (array('config/', 'css/', 'languages/') as $folder) {
            $folders[] = $pth['folder']['plugins'] . 'todo/' . $folder;
        }
        $folders[] = self::dataFolder();
        foreach ($folders as $folder) {
            $o .= (is_writable($folder) ? $ok : $warn)
                . '&nbsp;&nbsp;' . sprintf($ptx['syscheck_writable'], $folder)
                . tag('br') . "\n";
        }
        return $o;
    }

    /**
     * Resets all votes and returns the main administration view.
     *
     * @return string (X)HTML.
     */
    protected static function resetVotes()
    {
        $name = $_GET['todo_name'];
        self::lock($name, LOCK_EX);
        $todos = self::readData($name);
        foreach ($todos as $key => $todo) {
            $todos[$key]['votes'] = array();
        }
        self::writeData($name, $todos);
        self::lock($name, LOCK_UN);
        return self::adminMain();
    }

    /**
     * Returns the main administration view.
     *
     * @return string (X)HTML.
     *
     * @global array The localization of the plugins.
     */
    protected static function adminMain()
    {
        global $plugin_tx;

        $todos = glob(self::dataFolder() . '*.dat');
        $o = '<div class="plugineditcaption">Todo</div>'
            . '<ul>';
        foreach ($todos as $todo) {
            $name = basename($todo, '.dat');
            $url = '?todo&amp;admin=plugin_main&amp;action=reset_votes&amp;'
                . 'todo_name=' . $name;
            $onclick = 'return confirm(\'' . $plugin_tx['todo']['confirm_reset']
                . '\')';
            $o .= '<li><a href="' . $url . '" onclick="' . $onclick . '">'
                . htmlspecialchars($name, ENT_COMPAT, 'UTF-8')
                . '</a></li>';
        }
        $o .= '</ul>';
        return $o;
    }

    /**
     * The main function. Returns the grid widget, and dispatches on all following
     * AJAX requests.
     *
     * @param string $name A to-do list name.
     *
     * @return mixed
     *
     * @global string The (X)HTML fragment to insert into the head element.
     * @global string The URL of the current page.
     * @global string The (X)HTML fragment to output as error messages.
     * @global array  The localization of the core.
     *
     * @staticvar bool Whether the function has already been called.
     */
    public static function main($name)
    {
        global $hjs, $su, $e, $plugin_tx;
        static $again = false;

        $ptx = $plugin_tx['todo'];
        if (!preg_match('/^[a-z0-9\-]+$/u', $name)) {
            $e .= '<li><b>' . $ptx['msg_invalid_name'] . '</b>' . tag('br')
                . $name . '</li>' . "\n";
            return false;
        }

        if (isset($_GET['todo_name']) && $_GET['todo_name'] == $name) {
            switch ($_GET['todo_act']) {
            case 'list':
                echo self::getList($name);
                exit;
            case 'get':
                echo self::get($name);
                exit;
            case 'post':
                self::post($name);
                exit;
            case 'delete':
                echo self::delete($name);
                exit;
            case 'move':
                echo self::move($name);
                exit;
            case 'voting':
                echo self::voting($name);
                exit;
            }
        }

        $o = '';
        if (!$again) {
            self::hjs();
            $o .= self::editDlg() . self::moveDlg()
                . self::votingDlg();
            $again = true;
        }
        $o .= '<table id="todo_grid_' . $name . '" class="todo_grid"></table>'
            . '<noscript class="cmsimplecore_warning">' . $ptx['msg_no_js']
            . '</noscript>';
        $hjs .= '<script type="text/javascript">/* <![CDATA[ */'
            . "jQuery(function() {Todo.init('$su', '$name')})"
            . '/* ]]> */</script>';
        return $o;
    }

    /**
     * Returns the "edit" dialog.
     *
     * @return string (X)HTML.
     *
     * @global array The localization of the plugins.
     */
    protected static function editDlg()
    {
        global $plugin_tx;

        $ptx = $plugin_tx['todo'];
        return '<form id="todo_edit" style="display: none">'
            . '<label for="todo_task" class="todo_label">' . $ptx['js_task']
            . '</label>'
            . tag('input type="text" id="todo_task" name="todo_task"') . tag('br')
            . '<label for="todo_link" class="todo_label">' . $ptx['js_link']
            . '</label>'
            . tag('input type="text" id="todo_link" name="todo_link"') . tag('br')
            . '<label for="todo_notes" class="todo_label">' . $ptx['js_notes']
            . '</label>'
            . '<textarea id="todo_notes" name="todo_notes" cols="80" rows="5">'
            .'</textarea>' . tag('br')
            . '<label for="todo_resp" class="todo_label">' . $ptx['js_responsible']
            . '</label>'
            . tag('input type="text" id="todo_resp" name="todo_resp"') . tag('br')
            . '<label for="todo_state" class="todo_label">' . $ptx['js_state']
            . '</label>'
            . self::stateSelect() . tag('br')
            . '<label for="todo_date" class="todo_label">' . $ptx['js_date']
            . '</label>'
            . tag('input type="text" name="todo_date"') . tag('br')
            . '<label for="todo_vote" class="todo_label">' . $ptx['js_vote']
            . '</label>'
            . self::voteSelect() . tag('br')
            . '</form>';
    }

    /**
     * Returns the "move" dialog.
     *
     * @return string (X)HTML.
     *
     * @global array The localization of the plugins.
     */
    protected static function moveDlg()
    {
        global $plugin_tx;

        $ptx = $plugin_tx['todo'];
        $todos = glob(self::dataFolder() . '*.dat');
        $todos = array_map(
            create_function('$x', 'return basename($x, \'.dat\');'), $todos
        );
        $o = '<form id="todo_move" title="' . $ptx['move_title']
            . '" style="display: none">'
            . '<label for="todo_lists" class="todo_label">'
            . $ptx['move_destination'] . '</label>'
            . '<select id="todo_lists">';
        foreach ($todos as $todo) {
            $o .= '<option value="' . $todo . '">' . $todo . '</option>';
        }
        $o .= '</select>'
            . '</form>';
        return $o;
    }

    /**
     * Returns the "voting" dialog.
     *
     * @return string (X)HTML.
     *
     * @global array The localization of the plugins.
     */
    protected static function votingDlg()
    {
        global $plugin_tx;

        $ptx = $plugin_tx['todo'];
        $o = '<div id="todo_voting" title="' . $ptx['js_voting'] . '">'
            . '</div>';
        return $o;
    }

    /**
     * Returns the state selectbox.
     *
     * @return string (X)HTML.
     *
     * @global array The localization of the plugins.
     */
    protected static function stateSelect()
    {
        global $plugin_tx;

        $ptx = $plugin_tx['todo'];
        $o = '<select id="todo_state" name="todo_state">';
        foreach (array('idea', 'todo', 'inprogress', 'done') as $state) {
            $o .= '<option value="' . $state . '">' . $ptx['state_' . $state]
                . '</option>';
        }
        $o .= '</select>';
        return $o;
    }

    /**
     * Returns the vote selectbox.
     *
     * @return string (X)HTML.
     *
     * @global array The localization of the plugins.
     */
    protected static function voteSelect()
    {
        global $plugin_tx;

        $ptx = $plugin_tx['todo'];
        $o = '<select id="todo_vote" name="todo_vote">';
        foreach (array('', 'now', 'later', 'never') as $opt) {
            $o .= '<option value="' . $opt . '">' . $ptx['vote_' . $opt]
                .'</option>';
        }
        $o .= '</select>';
        return $o;
    }

    /**
     * Returns the requested records in JSON format.
     *
     * @param string $name A to-do list name.
     *
     * @return string
     */
    protected static function getList($name)
    {
        self::lock($name, LOCK_SH);
        $data = self::readData($name);
        self::lock($name, LOCK_UN);
        $page = $_GET['page'];
        $rp = $_GET['rp'];
        $start = ($page - 1) * $rp;
        $qtype = $_GET['qtype'];
        $query = stsl($_GET['query']);
        if (!empty($query)) {
            include_once UTF8 . '/stripos.php';
            $data = array_filter(
                $data,
                create_function(
                    '$x',
                    "return utf8_stripos(\$x['$qtype'], '$query', 0) !== false;"
                )
            );
        }
        $total = count($data);
        $data = self::sorted($data);
        $data = array_slice($data, $start, $rp);
        $o = '{"page": ' . $page . ', "total": ' . $total . ', "rows": [';
        $first = key($data);
        foreach ($data as $id => $rec) {
            if ($id != $first) {
                $o .= ', ';
            }
            $o.= '{"id": "' . $id . '", "cell": ';
            $o .= self::jsonRecord($name, $rec, $id);
            $o .= '}';
        }
        $o .= ']}';
        return $o;
    }

    /**
     * Returns the requested record in JSON format.
     *
     * @param string $name A to-do list name.
     *
     * @return string
     */
    protected static function get($name)
    {
        self::lock($name, LOCK_SH);
        $data = self::readData($name);
        self::lock($name, LOCK_UN);
        $id = $_GET['todo_id'];
        return self::jsonRecord($name, $data[$id], $id);
    }

    /**
     * Adds the posted task to the to-do list.
     *
     * @param string $name A to-do list name.
     *
     * @return void
     */
    protected static function post($name)
    {
        self::lock($name, LOCK_EX);
        $data = self::readData($name);
        if (isset($_GET['todo_id'])) {
            $id = $_GET['todo_id'];
            $rec = $data[$id];
        } else {
            $id = uniqid();
            $rec = array('votes' => array());
        }
        $fields = array('task', 'link', 'notes', 'resp', 'state', 'date', 'votes');
        foreach ($fields as $fld) {
            switch ($fld) {
            case 'date':
                $rec[$fld] = empty($_POST['todo_date'])
                    ? null : strtotime($_POST['todo_date']);
                break;
            case 'votes':
                $rec[$fld][self::member()] = $_POST['todo_vote'];
                break;
            default:
                $rec[$fld] = stsl($_POST['todo_' . $fld]);
            }
        }
        $data[$id] = $rec;
        self::writeData($name, $data);
        self::lock($name, LOCK_UN);
    }

    /**
     * Deletes the requested records from the to-do list.
     *
     * @param string $name A to-do list name.
     *
     * @return void
     */
    protected static function delete($name)
    {
        self::lock($name, LOCK_EX);
        $data = self::readData($name);
        foreach ($_POST['todo_ids'] as $id) {
            unset($data[$id]);
        }
        self::writeData($name, $data);
        self::lock($name, LOCK_UN);
    }

    /**
     * Moves the requested records from to another TODO list.
     *
     * @param string $name A source to-do list name.
     *
     * @return void
     */
    protected static function move($name)
    {
        $dname = stsl($_POST['todo_dest']);
        self::lock($name, LOCK_EX);
        $src = self::readData($name);
        self::lock($dname, LOCK_EX);
        $dst = self::readData($dname);
        foreach ($_POST['todo_ids'] as $id) {
            $dst[$id] = $src[$id];
            unset($src[$id]);
        }
        self::writeData($dname, $dst);
        self::lock($dname, LOCK_UN);
        self::writeData($name, $src);
        self::lock($name, LOCK_UN);
    }

    /**
     * Returns the detailed voting results.
     *
     * @param string $name A to-do list name.
     *
     * @return string (X)HTML.
     *
     * @global array The localization of the plugins.
     */
    protected static function voting($name)
    {
        global $plugin_tx;

        $ptx = $plugin_tx['todo'];
        self::lock($name, LOCK_SH);
        $data = self::readData($name);
        self::lock($name, LOCK_UN);
        $id = $_GET['todo_id'];
        $votes = $data[$id]['votes'];
        $o = '<table>';
        foreach ($votes as $user => $vote) {
            $o .= '<tr>' . '<td>' . $user . '</td>'
            . '<td>' . $ptx['vote_'.$vote] . '</td>' . '</tr>';
        }
        $o .= '</table>';
        return $o;
    }

    /**
     * Returns the sorted to-do list.
     *
     * @param array $data A to-do list.
     *
     * @return array
     */
    protected static function sorted($data)
    {
        $fld = $_GET['sortname'];
        // FIXME: sort locale aware (but see
        //    http://sgehrig.wordpress.com/2008/12/08/update-on-strcoll-utf-8-issue/)
        // FIXME: use efficent sorting
        if ($fld != 'id') {
            uasort(
                $data,
                create_function(
                    '$a, $b',
                    "return strcmp(mb_strtolower(\$a['$fld']),"
                    . "mb_strtolower(\$b['$fld']));"
                )
            );
        }
        if ($_GET['sortorder'] == 'desc') {
            $data = array_reverse($data);
        }
        return $data;
    }

    /**
     * Returns a single record in JSON format.
     *
     * @param string $name A to-do list name.
     * @param array  $rec  A to-do record.
     * @param string $id   A to-do ID.
     *
     * @return string
     *
     * @global array The localization of the plugins.
     */
    protected static function jsonRecord($name, $rec, $id)
    {
        global $plugin_tx;

        $ptx = $plugin_tx['todo'];
        $o = '{';
        $fields = array(
            'id', 'task', 'link', 'notes', 'resp', 'state', 'date', 'votes'
        );
        foreach ($fields as $fld) {
            if ($fld != 'id') {
                $o .= ', ';
            }
            if ($fld == 'id') {
                $val = $id;
            } elseif ($fld != 'votes') {
                $val = $_GET['todo_act'] == 'list'
                    ? preg_replace(
                        '/\r\n|\n|\r/u', tag('br'),
                        htmlspecialchars($rec[$fld], ENT_QUOTES, 'UTF-8')
                    )
                    : addcslashes($rec[$fld], "\0..\37\"\\");
            } else {
                if ($_GET['todo_act'] == 'list') {
                    $val = self::votingResult($rec[$fld]);
                } else {
                    $val = addcslashes($rec[$fld][self::member()], "\0..\37\"\\");
                    $fld = 'vote';
                }
            }
            if ($fld == 'link' && $_GET['todo_act'] == 'list') {
                $val = empty($val) ? '' : '<a href=\"' . $val . '\">'
                    . $ptx['link_text'] . '</a>';
            } elseif ($fld == 'state') {
                if ($_GET['todo_act'] == 'list') {
                    $val = '<span class=\"todo_state_' . $val . '\">'
                        . htmlspecialchars($ptx['state_'.$val], ENT_QUOTES, 'UTF-8')
                        . '</span>';
                }
            } elseif ($fld == 'date') {
                $val = empty($val) ? '' : date('Y-m-d', $val);
            }
            $o .= '"' . $fld . '": "' . $val . '"';
        }
        $o .= '}';
        return $o;
    }

    /**
     * Returns the voting result for the list view.
     *
     * @param array $voting An array of votes.
     *
     * @return string (X)HTML.
     */
    protected static function votingResult($voting)
    {
        $votes = array_count_values($voting);
        $res = array();
        foreach (array('now', 'later', 'never') as $opt) {
            $res[$opt] = isset($votes[$opt]) ? $votes[$opt] : 0;
        }
        $voted = !empty($voting[self::member()]);
        return ($voted ? '<span class=\"todo_voted\">' : '') . implode(' : ', $res)
            . ($voted ? '</span>' : '');
    }

    /**
     * Writes the JS and CSS to <head>.
     *
     * @global array  The paths of system files and folders.
     * @global string The (X)HTML fragment to insert into the head element.
     * @global array  The configuration of the plugins.
     * @global array  The localization of the plugins.
     *
     * @return void
     */
    protected static function hjs()
    {
        global $pth, $hjs, $plugin_cf, $plugin_tx;

        $pcf = $plugin_cf['todo'];
        include_once $pth['folder']['plugins'] . 'jquery/jquery.inc.php';
        include_jquery();
        include_jqueryui();
        $hjs .= tag(
            'link rel="stylesheet" href="' . $pth['folder']['plugins']
            . 'todo/flexigrid/css/flexigrid.pack.css" type="text/css"'
        ) . "\n";
        include_jqueryplugin(
            'flexigrid',
            $pth['folder']['plugins'] . 'todo/flexigrid/js/flexigrid.pack.js'
        );
        $hjs .= '<script type="text/javascript" src="' . $pth['folder']['plugins']
            . 'todo/todo.js"></script>' . "\n";
        $hjs .= '<script type="text/javascript">/* <![CDATA[ */' . "\n"
            . 'Todo.isMember = ' . (self::member() ? 'true' : 'false') . ';' . "\n"
            . 'Todo.TX = {';
        $first = true;
        foreach ($plugin_tx['todo'] as $key => $val) {
            if (strpos($key, 'js_') === 0) {
                if ($first) {
                    $first = false;
                } else {
                    $hjs .= ', ';
                }
                $hjs .= strtoupper(substr($key, 3)) . ': \''
                    . addcslashes($val, "\0..\37\\\'") . '\'';
            }
        }
        $hjs .= '}' . "\n"
            . 'Todo.COLS = [' . $pcf['col_widths'] . '];'
            . '/* ]]> */</script>' . "\n";
    }

    /**
     * Returns the to-do list.
     *
     * @param string $name A to-do list name.
     *
     * @return array
     */
    protected static function readData($name)
    {
        $fn = self::dataFolder() . $name . '.dat';
        $cnt = file_get_contents($fn);
        $data = $cnt !== false ? unserialize($cnt) : array();
        return $data;
    }

    /**
     * Saves the to-do list.
     *
     * @param string $name A to-do list name.
     * @param array  $data An array of to-do records.
     *
     * @return void
     */
    protected static function writeData($name, $data)
    {
        $fn = self::dataFolder() . $name . '.dat';
        if (($fh = fopen($fn, 'wb')) === false
            || fwrite($fh, serialize($data)) === false
        ) {
            e('cntsave', 'file', $fn); // TODO: error reporting for AJAX
        }
        if ($fh !== false) {
            fclose($fh);
        }
    }

    /**
     * Lock resp. unlocks the to-do list.
     *
     * @param string $name A to-do list name.
     * @param int    $op   A lock operation code.
     *
     * @return void
     *
     * @staticvar array The lock file handles.
     */
    protected static function lock($name, $op)
    {
        static $fh = array();

        $fn = self::dataFolder() . $name . '.lck';
        switch ($op) {
        case LOCK_SH:
        case LOCK_EX:
            touch($fn);
            $fh[$name] = fopen($fn, 'r+b');
            flock($fh[$name], $op);
            break;
        case LOCK_UN:
            flock($fh[$name], $op);
            fclose($fh[$name]);
            break;
        }
    }

    /**
     * Returns the data folder's path.
     *
     * @return string
     *
     * @global array The paths of system files and folders.
     * @global array The configuration of the plugins.
     */
    protected static function dataFolder()
    {
        global $pth, $plugin_cf;

        $pcf = $plugin_cf['todo'];
        if (empty($pcf['folder_data'])) {
            $fn = $pth['folder']['plugins'] . 'todo/data/';
        } else {
            $fn = $pth['folder']['base'] . $pcf['folder_data'];
            if ($fn{strlen($fn) - 1} != '/') {
                $fn .= '/';
            }
        }
        if (file_exists($fn)) {
            if (!is_dir($fn)) {
                e('cntopen', 'folder', $fn);
            }
        } else {
            $mkdir = 'mkdir';
            if (!$mkdir($fn, 0777, true)) {
                e('cntsave', 'folder', $fn);
            }
        }
        return $fn;
    }

    /**
     * Returns the currently logged in user (Memberpages or Register);
     * FALSE, if the visitor is not logged in.
     *
     * @return string
     */
    protected static function member()
    {
        if (session_id() == '') {
            session_start();
        }
        return isset($_SESSION['Name']) ? $_SESSION['Name']
            : (isset($_SESSION['username']) ? $_SESSION['username'] : false);
    }

}

?>
