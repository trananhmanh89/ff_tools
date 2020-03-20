<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;

Jloader::register('FFToolsHelper', __DIR__ . '/helper.php');

defined('_JEXEC') or die('Restricted access');

class PlgSystemFf_tools extends CMSPlugin
{
    public function onAfterInitialise()
    {
        FFToolsHelper::redirect($this->params);
        FFToolsHelper::mootoolsRemove($this->params);
    }

    public function onBeforeCompileHead()
    {
        if (Factory::getApplication()->isClient('administrator')) {
            return;
        }

        if ($this->params->get('minify-js')) {
            FFToolsHelper::minifyJS($this->params->get('js-exclude'));
        }

        if ($this->params->get('minify-css')) {
            FFToolsHelper::minifyCSS($this->params->get('css-exclude'));
        }
    }
}