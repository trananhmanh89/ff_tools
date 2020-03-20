<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;

defined('_JEXEC') or die('Restricted access');

class PlgSystemFf_tools extends CMSPlugin
{
    public function onAfterRender()
    {
        $remove = $this->params->get('remove');
        $app = Factory::getApplication();

        if (!$remove) {
            return;
        }

        if ($remove === 'site' && !$app->isClient('site')) {
            return;
        }

        if ($remove === 'admin' && !$app->isClient('admin')) {
            return;
        }

        $buffer = $app->getBody();
        $buffer = preg_replace('/<script src=".*?\/media\/system\/js\/mootools.*?"><\/script>/', '', $buffer);
        $app->setBody($buffer);
    }
}