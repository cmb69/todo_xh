<?php

/**
 * Front-end of Todo_XH.
 *
 * Copyright (c) 2012 Christoph M. Becker (see license.txt)
 */


if (!defined('CMSIMPLE_XH_VERSION')) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}


define('TODO_VERSION', '1alpha2');


/**
 * Returns whether a member is logged in (Memberpages or Register)
 *
 * @return bool
 */
function todo_is_member() {
    if (session_id() == '') {session_start();}
    return isset($_SESSION['Name']) || isset($_SESSION['username']);
}


/**
 * Returns the data folder's path.
 *
 * @param string $forum  The name of the forum.
 * @return string
 */
function todo_data_folder() {
    global $pth, $plugin_cf;

    $pcf = $plugin_cf['todo'];
    if (empty($pcf['folder_data'])) {
	$fn = $pth['folder']['plugins'].'todo/data/';
    } else {
	$fn = $pth['folder']['base'].$pcf['folder_data'];
	if ($fn{strlen($fn) - 1} != '/') {$fn .= '/';}
    }
    if (file_exists($fn)) {
	if (!is_dir($fn)) {e('cntopen', 'folder', $fn);}
    } else {
	if (!mkdir($fn, 0777, TRUE)) {e('cntsave', 'folder', $fn);}
    }
    return $fn;
}


/**
 * Lock resp. unlocks the TODO list.
 *
 * @param string $name  The name of the TODO list.
 * @param int $op  The lock operation.
 * @return void
 */
function todo_lock($name, $op) {
    static $fh = array();

    $fn = todo_data_folder().$name.'.lck';
    switch ($op) {
	case LOCK_SH: case LOCK_EX:
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
 * Returns the TODO list.
 *
 * @param string $name  The name of the TODO list.
 * @return array
 */
function todo_read_data($name) {
    $fn = todo_data_folder().$name.'.dat';
    $cnt = file_get_contents($fn);
    $data = $cnt !== FALSE ? unserialize($cnt) : array();
    return $data;
}


/**
 * Saves the TODO list.
 *
 * @param string $name  The name of the TODO list.
 * @param array $data
 * @return void
 */
function todo_write_data($name, $data) {
    $fn = todo_data_folder().$name.'.dat';
    if (($fh = fopen($fn, 'wb')) === FALSE || fwrite($fh, serialize($data)) === FALSE) {
	e('cntsave', 'file', $fn); // TODO: error reporting for AJAX
    }
    if ($fh !== FALSE) {fclose($fh);}
}


/**
 * Writes JS and CSS to <head>.
 *
 * @global string $hjs
 * @return void
 */
function todo_hjs() {
    global $pth, $hjs, $plugin_cf, $plugin_tx;

    $pcf = $plugin_cf['todo'];
    include_once $pth['folder']['plugins'].'jquery/jquery.inc.php';
    include_jquery();
    include_jqueryui();
    $hjs .= tag('link rel="stylesheet" href="'.$pth['folder']['plugins'].'todo/flexigrid/css/flexigrid.pack.css" type="text/css"')."\n";
    include_jqueryplugin('flexigrid', $pth['folder']['plugins'].'todo/flexigrid/js/flexigrid.pack.js');
    $hjs .= '<script type="text/javascript" src="'.$pth['folder']['plugins'].'todo/todo.js"></script>'."\n";
    $hjs .= '<script type="text/javascript">/* <![CDATA[ */'."\n"
	    .'Todo.isMember = '.(todo_is_member() ? 'true' : 'false').';'."\n"
	    .'Todo.TX = {';
    $first = TRUE;
    foreach ($plugin_tx['todo'] as $key => $val) {
	if (strpos($key, 'js_') === 0) {
	    if ($first) {$first = FALSE;} else {$hjs .= ', ';}
	    $hjs .= strtoupper(substr($key, 3)).': \''.addcslashes($val, "\0..\37\\\'").'\'';
	}
    }
    $hjs .= '}'."\n"
	    .'Todo.COLS = ['.$pcf['col_widths'].'];'
	    .'/* ]]> */</script>'."\n";
}


/**
 * Returns a single record in JSON format.
 *
 * @param string $name  The name of the TODO list.
 * @param array $rec
 * @return string
 */
function todo_json_record($name, $rec) {
    global $plugin_tx;

    $ptx = $plugin_tx['todo'];
    $o = '{';
    foreach (array('task', 'link', 'notes', 'resp', 'state', 'date') as $fld) {
	if ($fld != 'task') {$o .= ', ';}
	$val = $_GET['todo_act'] == 'list'
		? preg_replace('/\r\n|\n|\r/u', tag('br'), htmlspecialchars($rec[$fld], ENT_QUOTES, 'UTF-8'))
		: addcslashes($rec[$fld], "\0..\37\"\\");
	if ($fld == 'link' && $_GET['todo_act'] == 'list') {
	    $val = empty($val) ? '' : '<a href=\"'.$val.'\">'.$ptx['link_text'].'</a>';
	} elseif ($fld == 'state') {
	    if ($_GET['todo_act'] == 'list') {
		$val = '<span class=\"todo_state_'.$val.'\">'
			.htmlspecialchars($ptx['state_'.$val], ENT_QUOTES, 'UTF-8').'</span>';
	    }
	} elseif ($fld == 'date') {
	    $val = empty($val) ? '' : date('Y-m-d', $val);
	}
	$o .= '"'.$fld.'": "'.$val.'"';
    }
    $o .= '}';
    return $o;
}


/**
 * Returns the sorted TODO list.
 *
 * @param array $data  The TODO list.
 * @return array
 */
function todo_sorted($data) {
    $fld = $_GET['sortname'];
    uasort($data, create_function('$a, $b', "return strcmp(\$a['$fld'], \$b['$fld']);"));
    if ($_GET['sortorder'] == 'desc') {$data = array_reverse($data);}
    return $data;
}


/**
 * Returns the requested records in JSON format.
 *
 * @param string name  The name of the TODO list.
 * @return string
 */
function todo_list($name) {
    todo_lock($name, LOCK_SH);
    $data = todo_read_data($name);
    todo_lock($name, LOCK_UN);
    $page = $_GET['page'];
    $rp = $_GET['rp'];
    $start = ($page - 1) * $rp;
    $qtype = $_GET['qtype'];
    $query = stsl($_GET['query']);
    if (!empty($query)) {
	$data = array_filter($data, create_function('$x', "return strpos(\$x['$qtype'], '$query') !== FALSE;"));
    }
    $total = count($data);
    $data = todo_sorted($data);
    $data = array_slice($data, $start, $rp);
    $o = '{"page": '.$page.', "total": '.$total.', "rows": [';
    $first = key($data);
    foreach ($data as $id => $rec) {
	if ($id != $first) {$o .= ', ';}
	$o.= '{"id": "'.$id.'", "cell": ';
	$o .= todo_json_record($name, $rec);
	$o .= '}';
    }
    $o .= ']}';
    return $o;
}


/**
 * Returns the requested record in JSON format.
 *
 * @param string $name  The name of the TODO list.
 * @return string
 */
function todo_get($name) {
    todo_lock($name, LOCK_SH);
    $data = todo_read_data($name);
    todo_lock($name, LOCK_UN);
    $id = $_GET['todo_id'];
    return todo_json_record($name, $data[$id]);
}


/**
 * Adds the posted task to the TODO list.
 *
 * @param string name  The name of the TODO list.
 * @return void
 */
function todo_post($name) {
    todo_lock($name, LOCK_EX);
    $data = todo_read_data($name);
    $rec = array();
    foreach (array('task', 'link', 'notes', 'resp', 'state', 'date') as $fld) {
	$rec[$fld] = stsl($_POST['todo_'.$fld]);
	switch ($fld) {
	    case 'date':
		$rec[$fld] = empty($rec[$fld]) ? NULL : strtotime(stsl($rec[$fld]));
		break;
	}
    }
    $id = isset($_GET['todo_id']) ? $_GET['todo_id'] : uniqid();
    $data[$id] = $rec;
    todo_write_data($name, $data);
    todo_lock($name, LOCK_UN);
}


/**
 * Deletes the requested records from the TODO list.
 *
 * @param string name  The name of the TODO list.
 * @return void
 */
function todo_delete($name) {
    todo_lock($name, LOCK_EX);
    $data = todo_read_data($name);
    foreach ($_POST['todo_ids'] as $id) {
	unset($data[$id]);
    }
    todo_write_data($name, $data);
    todo_lock($name, LOCK_UN);
}


/**
 * Moves the requested records from to another TODO list.
 *
 * @param string name  The name of the source TODO list.
 * @return void
 */
function todo_move($name) {
    $dname = stsl($_POST['todo_dest']);
    todo_lock($name, LOCK_EX);
    $src = todo_read_data($name);
    todo_lock($dname, LOCK_EX);
    $dst = todo_read_data($dname);
    foreach ($_POST['todo_ids'] as $id) {
	$dst[$id] = $src[$id];
	unset($src[$id]);
    }
    todo_write_data($dname, $dst);
    todo_lock($dname, LOCK_UN);
    todo_write_data($name, $src);
    todo_lock($name, LOCK_UN);
}


/**
 * Returns the state selectbox.
 *
 * @return string  The (X)HTML.
 */
function todo_state_select() {
    global $plugin_tx;

    $ptx = $plugin_tx['todo'];
    $o = '<select id="todo_state" name="todo_state">';
    foreach (array('idea', 'todo', 'inprogress', 'done') as $state) {
	$o .= '<option value="'.$state.'">'.$ptx['state_'.$state].'</option>';
    }
    $o .= '</select>';
    return $o;
}


/**
 * Returns the "edit" dialog.
 *
 * @return string  The (X)HTML.
 */
function todo_edit_dlg() {
    global $plugin_tx;

    $ptx = $plugin_tx['todo'];
    return '<form id="todo_edit" style="display: none">'
	    .'<label for="todo_task" class="todo_label">'.$ptx['js_task'].'</label>'
	    .tag('input type="text" id="todo_task" name="todo_task"').tag('br')
	    .'<label for="todo_link" class="todo_label">'.$ptx['js_link'].'</label>'
	    .tag('input type="text" id="todo_link" name="todo_link"').tag('br')
	    .'<label for="todo_notes" class="todo_label">'.$ptx['js_notes'].'</label>'
	    .'<textarea id="todo_notes" name="todo_notes" cols="80" rows="5">'.'</textarea>'.tag('br')
	    .'<label for="todo_resp" class="todo_label">'.$ptx['js_responsible'].'</label>'
	    .tag('input type="text" id="todo_resp" name="todo_resp"').tag('br')
	    .'<label for="todo_state" class="todo_label">'.$ptx['js_state'].'</label>'
	    .todo_state_select().tag('br')
	    .'<label for="todo_date" class="todo_label">'.$ptx['js_date'].'</label>'
	    .tag('input type="text" name="todo_date"').tag('br')
	    .'</form>';
}

/**
 * Returns the "move" dialog.
 *
 * @return string  The (X)HTML.
 */
function todo_move_dlg() {
    global $plugin_tx;

    $ptx = $plugin_tx['todo'];
    $todos = glob(todo_data_folder().'*.dat');
    $todos = array_map(create_function('$x', 'return basename($x, \'.dat\');'), $todos);
    $o = '<form id="todo_move" title="'.$ptx['move_title'].'" style="display: none">'
	    .'<label for="todo_lists" class="todo_label">'.$ptx['move_destination'].'</label>'
	    .'<select id="todo_lists">';
    foreach ($todos as $todo) {
	$o .= '<option value="'.$todo.'">'.$todo.'</option>';
    }
    $o .= '</select>'
	    .'</form>';
    return $o;
}


/**
 * The main function. Returns the grid widget,
 * and dispatches on all following AJAX requests.
 *
 * @access public
 * @param string $name  The name of the TODO list.
 * @return mixed
 */
function todo($name) {
    global $hjs, $su, $e, $plugin_tx;
    static $again = FALSE;

    $ptx = $plugin_tx['todo'];
    if (!preg_match('/^[a-z0-9\-]+$/u', $name)) {
	$e .= '<li><b>'.$ptx['msg_invalid_name'].'</b>'.tag('br').$name.'</li>'."\n";
	return FALSE;
    }

    if (isset($_GET['todo_name']) && $_GET['todo_name'] == $name) {
	switch ($_GET['todo_act']) {
	    case 'list': echo todo_list($name); exit;
	    case 'get': echo todo_get($name); exit;
	    case 'post': todo_post($name); exit;
	    case 'delete': echo todo_delete($name); exit;
	    case 'move': echo todo_move($name); exit;
	}
    }

    $o = '';
    if (!$again) {
	todo_hjs();
	$o .= todo_edit_dlg().todo_move_dlg();
	$again = TRUE;
    }
    $o .= '<table id="todo_grid_'.$name.'" class="todo_grid"></table>'
	    .'<noscript class="cmsimplecore_warning">'.$ptx['msg_no_js'].'</noscript>'
	    .'<div class="todo_powered_by">'.$ptx['msg_powered_by'].'</div>';
    $hjs .= '<script type="text/javascript">/* <![CDATA[ */'
	    ."jQuery(function() {Todo.init('$su', '$name')})"
	    .'/* ]]> */</script>';
    return $o;
}

?>
