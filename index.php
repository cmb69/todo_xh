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


define('TODO_VERSION', '1alpha1');



function todo_is_member() {
    if (session_id() == '') {session_start();}
    return isset($_SESSION['Name']);
}


function todo_data_folder() {
    global $pth;
    
    return $pth['folder']['plugins'].'todo/data/';
}


function todo_data($name, $ndata = NULL) {
    $fn = todo_data_folder().$name.'.dat';
    if (!isset($ndata)) { // read
	$cnt = file_get_contents($fn);
	$data[$name] = $cnt !== FALSE ? unserialize($cnt) : array();
    } else { // write
	$data[$name] = $ndata;
	$fh = fopen($fn, 'wb');
	fwrite($fh, serialize($data[$name]));
	fclose($fh);
    }
    return $data[$name];
}


/**
 * @global string $hjs
 */
function todo_hjs() {
    global $pth, $hjs, $plugin_cf, $plugin_tx;
    
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
	    .'/* ]]> */</script>'."\n";
}


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


function todo_json_record($name, $rec) {
    global $plugin_tx;
    
    $o = '{';
    foreach (array('task', 'link', 'notes', 'resp', 'state', 'date') as $fld) {
	if ($fld != 'task') {$o .= ', ';}
	$val = $_GET['todo_act'] == 'list'
		? htmlspecialchars($rec[$fld], ENT_QUOTES, 'UTF-8')
		: addcslashes($rec[$fld], "\0..\37\"\\");
	if ($fld == 'link' && $_GET['todo_act'] == 'list') {
	    $val = '<a href=\"'.$val.'\">Discussion</a>';
	} elseif ($fld == 'state') {
	    if ($_GET['todo_act'] == 'list') {
		$colors = array('idea' => 'black', 'todo' => 'green', 'inprogress' => 'red', 'done' => 'orange');
		$val = '<span style=\"color: '.$colors[$val].'\">'
			.htmlspecialchars($plugin_tx['todo']['state_'.$val], ENT_QUOTES, 'UTF-8').'</span>';
	    }
	} elseif ($fld == 'date') {
	    $val = empty($val) ? '' : date('Y-m-d', $val);
	}
	$o .= '"'.$fld.'": "'.$val.'"';
    }
    $o .= '}';
    return $o;
}


function todo_sorted($name) {
    $fld = $_GET['sortname'];
    $data = todo_data($name);
    uasort($data, create_function('$a, $b', "return strcmp(\$a['$fld'], \$b['$fld']);"));
    if ($_GET['sortorder'] == 'notes') {$data = array_reverse($data);}
    return $data;
}

function todo_json($name) {
    $data = todo_data($name);
    $page = $_GET['page'];
    $rp = $_GET['rp'];
    $start = ($page - 1) * $rp;
    $qtype = $_GET['qtype'];
    $query = stsl($_GET['query']);
    if (!empty($query)) {
	$data = array_filter($data, create_function('$x', "return strpos(\$x['$qtype'], '$query') !== FALSE;"));
    }
    $total = count($data);
    $data = todo_sorted($name);
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


function todo_get($name) {
    $data = todo_data($name);
    $id = $_GET['todo_id'];
    return todo_json_record($name, $data[$id]);
}


function todo_post($name) {
    $data = todo_data($name);
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
    echo print_r($rec);
    todo_data($name, $data);
}


function todo_delete($name) {
    $data = todo_data($name);
    foreach ($_POST['todo_ids'] as $id) {
	unset($data[$id]);
    }
    todo_data($name, $data);    
}


function todo_move($name) {
    $dname = stsl($_POST['todo_dest']);
    $src = todo_data($name);
    $dst = todo_data($dname);
    foreach ($_POST['todo_ids'] as $id) {
	$dst[$id] = $src[$id];
	unset($src[$id]);
    }
    todo_data($dname, $dst);
    todo_data($name, $src);
}


function todo_move_dlg() {
    $todos = glob(todo_data_folder().'*.dat');
    $todos = array_map(create_function('$x', 'return basename($x, \'.dat\');'), $todos);
    $o = '<form id="todo_move">'
	    .'<select>';
    foreach ($todos as $todo) {
	$o .= '<option>'.$todo.'</option>';
    }
    $o .= '</select>'
	    .'</form>';
    return $o;
}

function todo($name) {
    global $hjs, $su;
    
    if (isset($_GET['todo_name']) && $_GET['todo_name'] == $name) {
	switch ($_GET['todo_act']) {
	    case 'list': echo todo_json($name); exit;
	    case 'get': echo todo_get($name); exit;
	    case 'post': todo_post($name); exit;
	    case 'delete': echo todo_delete($name); exit;
	    case 'move': echo todo_move($name); exit;
	}
    }
    todo_hjs();
    $o = '<table id="todo_grid_'.$name.'"></table>';
    $o .= '<form id="todo_edit">'
	    .'<label for="todo_task" class="todo_label">Name</label>'
	    .tag('input type="text" id="todo_task" name="todo_task"').tag('br')
	    .'<label for="todo_link" class="todo_label">Link</label>'
	    .tag('input type="text" id="todo_link" name="todo_link"').tag('br')
	    .'<label for="todo_notes" class="todo_label">Notes</label>'
	    .'<textarea id="todo_notes" name="todo_notes" cols="80" rows="5">'.'</textarea>'.tag('br')
	    .'<label for="todo_resp" class="todo_label">Respons.</label>'
	    .tag('input type="text" id="todo_resp" name="todo_resp"').tag('br')
	    .'<label for="todo_state" class="todo_label">State</label>'
	    .todo_state_select().tag('br')
	    .'<label for="todo_date" class="todo_label">Date</label>'
	    .tag('input type="text" name="todo_date"').tag('br')
	    //.tag('input type="submit"')
	    .'</form>';
    $o .= todo_move_dlg();
    $hjs .= '<script type="text/javascript">/* <![CDATA[ */'
	    ."jQuery(function() {Todo.init('$su', '$name')})"
	    .'/* ]]> */</script>';
    return $o;
}

?>
