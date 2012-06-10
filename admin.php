<?php

/**
 * Back-end of Todo_XH.
 *
 * Copyright (c) 2012 Christoph M. Becker (see license.txt)
 */


if (!defined('CMSIMPLE_XH_VERSION')) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}


/**
 * Returns the version information view.
 *
 * @return string  The (X)HTML.
 */
function todo_version() {
    global $pth;

    return '<h1><a href="http://3-magi.net/?CMSimple_XH/Todo_XH">Todo_XH</a></h1>'."\n"
	    .tag('img class="todo_plugin_icon" src="'.$pth['folder']['plugins'].'todo/todo.png" alt="Plugin icon"')."\n"
	    .'<p style="margin-top: 1em">Version: '.TODO_VERSION.'</p>'."\n"
	    .'<p>Copyright &copy; 2012 <a href="http://3-magi.net/">Christoph M. Becker</a></p>'."\n"
	    .'<p class="todo_license">This program is free software: you can redistribute it and/or modify'
	    .' it under the terms of the GNU General Public License as published by'
	    .' the Free Software Foundation, either version 3 of the License, or'
	    .' (at your option) any later version.</p>'."\n"
	    .'<p class="todo_license">This program is distributed in the hope that it will be useful,'
	    .' but WITHOUT ANY WARRANTY; without even the implied warranty of'
	    .' MERCHAN&shy;TABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the'
	    .' GNU General Public License for more details.</p>'."\n"
	    .'<p class="todo_license">You should have received a copy of the GNU General Public License'
	    .' along with this program.  If not, see'
	    .' <a href="http://www.gnu.org/licenses/">http://www.gnu.org/licenses/</a>.</p>'."\n";
}


/**
 * Returns the requirements information view.
 *
 * @return string  The (X)HTML.
 */
function todo_system_check() { // RELEASE-TODO
    global $pth, $tx, $plugin_tx;

    define('TODO_PHP_VERSION', '4.3.0');
    $ptx = $plugin_tx['todo'];
    $imgdir = $pth['folder']['plugins'].'todo/images/';
    $ok = tag('img src="'.$imgdir.'ok.png" alt="ok"');
    $warn = tag('img src="'.$imgdir.'warn.png" alt="warning"');
    $fail = tag('img src="'.$imgdir.'fail.png" alt="failure"');
    $o = '<h4>'.$ptx['syscheck_title'].'</h4>'
	    .(version_compare(PHP_VERSION, TODO_PHP_VERSION) >= 0 ? $ok : $fail)
	    .'&nbsp;&nbsp;'.sprintf($ptx['syscheck_phpversion'], TODO_PHP_VERSION)
	    .tag('br')."\n";
    foreach (array('date', 'pcre', 'session') as $ext) {
	$o .= (extension_loaded($ext) ? $ok : $fail)
		.'&nbsp;&nbsp;'.sprintf($ptx['syscheck_extension'], $ext).tag('br')."\n";
    }
    $o .= (!get_magic_quotes_runtime() ? $ok : $fail)
	    .'&nbsp;&nbsp;'.$ptx['syscheck_magic_quotes'].tag('br').tag('br')."\n";
    $o .= (strtoupper($tx['meta']['codepage']) == 'UTF-8' ? $ok : $warn)
	    .'&nbsp;&nbsp;'.$ptx['syscheck_encoding'].tag('br')."\n";
    $o .= (file_exists($pth['folder']['plugins'].'jquery/jquery.inc.php') ? $ok : $fail)
	    .'&nbsp;&nbsp;'.$ptx['syscheck_jquery'].tag('br').tag('br')."\n";
    foreach (array('config/', 'css/', 'languages/') as $folder) {
	$folders[] = $pth['folder']['plugins'].'todo/'.$folder;
    }
    $folders[] = todo_data_folder();
    foreach ($folders as $folder) {
	$o .= (is_writable($folder) ? $ok : $warn)
		.'&nbsp;&nbsp;'.sprintf($ptx['syscheck_writable'], $folder).tag('br')."\n";
    }
    return $o;
}


if (isset($todo) && $todo == 'true') {
    $o .= print_plugin_admin('off');
    switch ($admin) {
	case '':
	    $o .= todo_version().tag('hr').todo_system_check();
	    break;
	default:
	    $o .= plugin_admin_common($action, $admin, $plugin);
    }
}


?>
