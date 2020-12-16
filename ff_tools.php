<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;

Jloader::register('FFToolsHelper', __DIR__ . '/helper.php');

defined('_JEXEC') or die('Restricted access');

class PlgSystemFf_tools extends CMSPlugin
{
    protected $_scripts = array();
    protected $_script = array();
    protected $_styleSheets = array();

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

        $doc = Factory::getDocument();

        if ($this->params->get('minify-js')) {
            FFToolsHelper::minifyJS($this->params->get('js-exclude'));
        }

        if ($this->params->get('minify-css')) {
            FFToolsHelper::minifyCSS($this->params->get('css-exclude'));

            if ($this->params->get('inline-css')) {
                foreach ($doc->_styleSheets as $key => $value) {
                    if (!preg_match('/cache\/ff_tools_css_cache\//', $key)) {
                        continue;
                    }

                    $this->_styleSheets[] = $key;
                }
            }
        }

        if ($this->params->get('script-to-bottom')) {
            $this->_scripts = $doc->_scripts;
            $this->_script = $doc->_script;

            $doc->_scripts = [];
            $doc->_script = [];
        }
    }

    public function onAfterRender()
    {
        $app = Factory::getApplication();
        if ($app->isClient('administrator')) {
            return;
        }

        if ($this->params->get('minify-css') && $this->params->get('inline-css')) {
            FFToolsHelper::inlineCss($this->_styleSheets);
        }

        if ($this->params->get('script-to-bottom')) {
            FFToolsHelper::moveScriptToBottom($this->_scripts, $this->_script);
        }
    }
}
