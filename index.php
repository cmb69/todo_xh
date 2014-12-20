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

/*
 * Prevent direct access and usage from unsupported CMSimple_XH versions.
 */
if (!defined('CMSIMPLE_XH_VERSION')
    || strpos(CMSIMPLE_XH_VERSION, 'CMSimple_XH') !== 0
    || version_compare(CMSIMPLE_XH_VERSION, 'CMSimple_XH 1.6', 'lt')
) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/plain; charset=UTF-8');
    die(<<<EOT
Todo_XH detected an unsupported CMSimple_XH version.
Uninstall Todo_XH or upgrade to a supported CMSimple_XH version!
EOT
    );
}

/**
 * The controller.
 */
require_once $pth['folder']['plugin_classes'] . 'Controller.php';

/**
 * The plugin version.
 */
define('TODO_VERSION', '@TODO_VERSION@');

/**
 * The main function. Returns the grid widget, and dispatches on all following
 * AJAX requests.
 *
 * @param string $name A to-do list name.
 *
 * @return mixed
 */
function todo($name)
{
    return Todo_Controller::main($name);
}

Todo_Controller::dispatch();

?>
