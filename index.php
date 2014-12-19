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

/**
 * The controller.
 */
require_once $pth['folder']['plugin_classes'] . 'Controller.php';

/**
 * The plugin version.
 */
define('TODO_VERSION', '@TODO_VERSION@');

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
