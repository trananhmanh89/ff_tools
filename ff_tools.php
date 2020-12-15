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

        $doc = Factory::getDocument();
        $this->_scripts = $doc->_scripts;
        $this->_script = $doc->_script;

        $doc->_scripts = [];
        $doc->_script = [];
    }

    public function onAfterRender()
    {
        $app = Factory::getApplication();
        if ($app->isClient('administrator')) {
            return;
        }

        $document = Factory::getDocument();
        $body = $app->getBody();

        $lnEnd        = $document->_getLineEnd();
        $tab          = $document->_getTab();
        $buffer       = '';
        $mediaVersion = $document->getMediaVersion();
        $defaultJsMimes         = array('text/javascript', 'application/javascript', 'text/x-javascript', 'application/x-javascript');
        $html5NoValueAttributes = array('defer', 'async');
        // Generate script file links
        foreach ($this->_scripts as $src => $attribs) {
            // Check if script uses IE conditional statements.
            $conditional = isset($attribs['options']) && isset($attribs['options']['conditional']) ? $attribs['options']['conditional'] : null;

            // Check if script uses media version.
            if (
                isset($attribs['options']['version']) && $attribs['options']['version'] && strpos($src, '?') === false
                && ($mediaVersion || $attribs['options']['version'] !== 'auto')
            ) {
                $src .= '?' . ($attribs['options']['version'] === 'auto' ? $mediaVersion : $attribs['options']['version']);
            }

            $buffer .= $tab;

            // This is for IE conditional statements support.
            if (!is_null($conditional)) {
                $buffer .= '<!--[if ' . $conditional . ']>';
            }

            $buffer .= '<script src="' . $src . '"';

            // Add script tag attributes.
            foreach ($attribs as $attrib => $value) {
                // Don't add the 'options' attribute. This attribute is for internal use (version, conditional, etc).
                if ($attrib === 'options') {
                    continue;
                }

                // Don't add type attribute if document is HTML5 and it's a default mime type. 'mime' is for B/C.
                if (in_array($attrib, array('type', 'mime')) && $document->isHtml5() && in_array($value, $defaultJsMimes)) {
                    continue;
                }

                // B/C: If defer and async is false or empty don't render the attribute.
                if (in_array($attrib, array('defer', 'async')) && !$value) {
                    continue;
                }

                // Don't add type attribute if document is HTML5 and it's a default mime type. 'mime' is for B/C.
                if ($attrib === 'mime') {
                    $attrib = 'type';
                }
                // B/C defer and async can be set to yes when using the old method.
                elseif (in_array($attrib, array('defer', 'async')) && $value === true) {
                    $value = $attrib;
                }

                // Add attribute to script tag output.
                $buffer .= ' ' . htmlspecialchars($attrib, ENT_COMPAT, 'UTF-8');

                if (!($document->isHtml5() && in_array($attrib, $html5NoValueAttributes))) {
                    // Json encode value if it's an array.
                    $value = !is_scalar($value) ? json_encode($value) : $value;

                    $buffer .= '="' . htmlspecialchars($value, ENT_COMPAT, 'UTF-8') . '"';
                }
            }

            $buffer .= '></script>';

            // This is for IE conditional statements support.
            if (!is_null($conditional)) {
                $buffer .= '<![endif]-->';
            }

            $buffer .= $lnEnd;
        }

        // Generate script declarations
        foreach ($this->_script as $type => $content) {
            $buffer .= $tab . '<script';

            if (!is_null($type) && (!$document->isHtml5() || !in_array($type, $defaultJsMimes))) {
                $buffer .= ' type="' . $type . '"';
            }

            $buffer .= '>' . $lnEnd;

            // This is for full XHTML support.
            if ($document->_mime != 'text/html') {
                $buffer .= $tab . $tab . '//<![CDATA[' . $lnEnd;
            }

            $buffer .= $content . $lnEnd;

            // See above note
            if ($document->_mime != 'text/html') {
                $buffer .= $tab . $tab . '//]]>' . $lnEnd;
            }

            $buffer .= $tab . '</script>' . $lnEnd;
        }

        $body = str_replace('</body>', $buffer . '</body>', $body);
        $app->setBody($body);
    }
}
