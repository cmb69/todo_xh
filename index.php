<?php

/**
 * Front-end of Todo_XH.
 *
 * PHP versions 4 and 5
 *
 * @category  CMSimple_XH
 * @package   Todo
 * @author    Christoph M. Becker <cmbecker69@gmx.de>
 * @copyright 2012-2014 Christoph M. Becker <http://3-magi.net>
 * @license   http://www.gnu.org/licenses/gpl-3.0.en.html GNU GPLv3
 * @link      http://3-magi.net/?CMSimple_XH/Todo_XH
 */

if (!defined('CMSIMPLE_XH_VERSION')) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

define('TODO_VERSION', '1alpha5');

/**
 * Backward compatibility.
 */
if (!function_exists('mb_stripos')) {
    /**
     * Finds position of first occurrence of a string within another,
     * case insensitive
     *
     * @param string $haystack A haystack.
     * @param string $needle   A needle.
     * @param int    $offset   A position to start searching.
     * @param string $encoding A character enconding name.
     *
     * @return int
     */
// @codingStandardsIgnoreStart
    function mb_stripos($haystack, $needle, $offset = 0, $encoding = null)
    {
// @codingStandardsIgnoreEnd
        if (!isset($encoding)) {
            $encoding = mb_internal_encoding();
        }
        return mb_stripos(
            mb_strtolower($haystack, $encoding),
            mb_strtolower($needle, $encoding), $offset, $encoding
        );
    }
}

/**
 * Returns the currently logged in user (Memberpages or Register);
 * FALSE, if the visitor is not logged in.
 *
 * @return string
 */
function Todo_member()
{
    if (session_id() == '') {
        session_start();
    }
    return isset($_SESSION['Name']) ? $_SESSION['Name']
        : (isset($_SESSION['username']) ? $_SESSION['username'] : false);
}

/**
 * Returns the data folder's path.
 *
 * @return string
 *
 * @global array The paths of system files and folders.
 * @global array The configuration of the plugins.
 */
function Todo_dataFolder()
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
        if (!mkdir($fn, 0777, true)) {
            e('cntsave', 'folder', $fn);
        }
    }
    return $fn;
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
function Todo_lock($name, $op)
{
    static $fh = array();

    $fn = Todo_dataFolder() . $name . '.lck';
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

// TODO: error handling

/**
 * Returns the to-do list.
 *
 * @param string $name A to-do list name.
 *
 * @return array
 */
function Todo_readData($name)
{
    $fn = Todo_dataFolder() . $name . '.dat';
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
function Todo_writeData($name, $data)
{
    $fn = Todo_dataFolder() . $name . '.dat';
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
 * Writes the JS and CSS to <head>.
 *
 * @global array  The paths of system files and folders.
 * @global string $hjs The (X)HTML fragment to insert into the head element.
 * @global array  The configuration of the plugins.
 * @global array  The localization of the plugins.
 *
 * @return void
 */
function Todo_hjs()
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
        . 'Todo.isMember = ' . (Todo_member() ? 'true' : 'false') . ';' . "\n"
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
 * Returns the voting result for the list view.
 *
 * @param array $voting An array of votes.
 *
 * @return string (X)HTML.
 */
function Todo_votingResult($voting)
{
    $votes = array_count_values($voting);
    $res = array();
    foreach (array('now', 'later', 'never') as $opt) {
        $res[$opt] = isset($votes[$opt]) ? $votes[$opt] : 0;
    }
    $voted = !empty($voting[Todo_member()]);
    return ($voted ? '<span class=\"todo_voted\">' : '') . implode(' : ', $res)
        . ($voted ? '</span>' : '');
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
function Todo_jsonRecord($name, $rec, $id)
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
                $val = Todo_votingResult($rec[$fld]);
            } else {
                $val = addcslashes($rec[$fld][Todo_member()], "\0..\37\"\\");
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
 * Returns the sorted to-do list.
 *
 * @param array $data A to-do list.
 *
 * @return array
 */
function Todo_sorted($data)
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
 * Returns the requested records in JSON format.
 *
 * @param string $name A to-do list name.
 *
 * @return string
 */
function Todo_list($name)
{
    Todo_lock($name, LOCK_SH);
    $data = Todo_readData($name);
    Todo_lock($name, LOCK_UN);
    $page = $_GET['page'];
    $rp = $_GET['rp'];
    $start = ($page - 1) * $rp;
    $qtype = $_GET['qtype'];
    $query = stsl($_GET['query']);
    if (!empty($query)) {
        $data = array_filter(
            $data,
            create_function(
                '$x',
                "return mb_stripos(\$x['$qtype'], '$query', 0, 'UTF-8') !== false;"
            )
        );
    }
    $total = count($data);
    $data = Todo_sorted($data);
    $data = array_slice($data, $start, $rp);
    $o = '{"page": ' . $page . ', "total": ' . $total . ', "rows": [';
    $first = key($data);
    foreach ($data as $id => $rec) {
        if ($id != $first) {
            $o .= ', ';
        }
        $o.= '{"id": "' . $id . '", "cell": ';
        $o .= Todo_jsonRecord($name, $rec, $id);
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
function Todo_get($name)
{
    Todo_lock($name, LOCK_SH);
    $data = Todo_readData($name);
    Todo_lock($name, LOCK_UN);
    $id = $_GET['todo_id'];
    return Todo_jsonRecord($name, $data[$id], $id);
}

/**
 * Adds the posted task to the to-do list.
 *
 * @param string $name A to-do list name.
 *
 * @return void
 */
function Todo_post($name)
{
    Todo_lock($name, LOCK_EX);
    $data = Todo_readData($name);
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
            $rec[$fld][Todo_member()] = $_POST['todo_vote'];
            break;
        default:
            $rec[$fld] = stsl($_POST['todo_' . $fld]);
        }
    }
    $data[$id] = $rec;
    Todo_writeData($name, $data);
    Todo_lock($name, LOCK_UN);
}

/**
 * Deletes the requested records from the to-do list.
 *
 * @param string $name A to-do list name.
 *
 * @return void
 */
function Todo_delete($name)
{
    Todo_lock($name, LOCK_EX);
    $data = Todo_readData($name);
    foreach ($_POST['todo_ids'] as $id) {
        unset($data[$id]);
    }
    Todo_writeData($name, $data);
    Todo_lock($name, LOCK_UN);
}

/**
 * Moves the requested records from to another TODO list.
 *
 * @param string $name A source to-do list name.
 *
 * @return void
 */
function Todo_move($name)
{
    $dname = stsl($_POST['todo_dest']);
    Todo_lock($name, LOCK_EX);
    $src = Todo_readData($name);
    Todo_lock($dname, LOCK_EX);
    $dst = Todo_readData($dname);
    foreach ($_POST['todo_ids'] as $id) {
        $dst[$id] = $src[$id];
        unset($src[$id]);
    }
    Todo_writeData($dname, $dst);
    Todo_lock($dname, LOCK_UN);
    Todo_writeData($name, $src);
    Todo_lock($name, LOCK_UN);
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
function Todo_voting($name)
{
    global $plugin_tx;

    $ptx = $plugin_tx['todo'];
    Todo_lock($name, LOCK_SH);
    $data = Todo_readData($name);
    Todo_lock($name, LOCK_UN);
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
 * Returns the state selectbox.
 *
 * @return string (X)HTML.
 *
 * @global array The localization of the plugins.
 */
function Todo_stateSelect()
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
function Todo_voteSelect()
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
 * Returns the "edit" dialog.
 *
 * @return string (X)HTML.
 *
 * @global array The localization of the plugins.
 */
function Todo_editDlg()
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
        . Todo_stateSelect() . tag('br')
        . '<label for="todo_date" class="todo_label">' . $ptx['js_date']
        . '</label>'
        . tag('input type="text" name="todo_date"') . tag('br')
        . '<label for="todo_vote" class="todo_label">' . $ptx['js_vote']
        . '</label>'
        . Todo_voteSelect() . tag('br')
        . '</form>';
}

/**
 * Returns the "move" dialog.
 *
 * @return string (X)HTML.
 *
 * @global array The localization of the plugins.
 */
function Todo_moveDlg()
{
    global $plugin_tx;

    $ptx = $plugin_tx['todo'];
    $todos = glob(Todo_dataFolder() . '*.dat');
    $todos = array_map(
        create_function('$x', 'return basename($x, \'.dat\');'), $todos
    );
    $o = '<form id="todo_move" title="' . $ptx['move_title']
        . '" style="display: none">'
        . '<label for="todo_lists" class="todo_label">' . $ptx['move_destination']
        . '</label>'
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
function Todo_votingDlg()
{
    global $plugin_tx;

    $ptx = $plugin_tx['todo'];
    $o = '<div id="todo_voting" title="' . $ptx['js_voting'] . '">'
        . '</div>';
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
function todo($name)
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
            echo Todo_list($name);
            exit;
        case 'get':
            echo Todo_get($name);
            exit;
        case 'post':
            Todo_post($name);
            exit;
        case 'delete':
            echo Todo_delete($name);
            exit;
        case 'move':
            echo Todo_move($name);
            exit;
        case 'voting':
            echo Todo_voting($name);
            exit;
        }
    }

    $o = '';
    if (!$again) {
        Todo_hjs();
        $o .= Todo_editDlg() . Todo_moveDlg() . Todo_votingDlg();
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

?>
