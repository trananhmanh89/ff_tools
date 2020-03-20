<?php
/**
 * @package     Joomla.Libraries
 * @subpackage  HTML
 *
 * @copyright   Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('JPATH_PLATFORM') or die;

/**
 * Utility class for JavaScript behaviors
 *
 * @since  1.5
 */
abstract class JHtmlBehavior extends JHtmlBehavior_Original
{
    public static function framework($extras = false, $debug = null)
    {
        return;
    }

    public static function formvalidation()
    {
        return;
    }

    public static function tree($id, $params = array(), $root = array())
    {
        return;
    }

    public static function tooltip($selector = '.hasTip', $params = array())
    {
        return;
    }

    public static function modal($selector = 'a.modal', $params = array())
    {
        return;
    }
}