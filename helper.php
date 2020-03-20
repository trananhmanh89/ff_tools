<?php
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Uri\Uri;

require __DIR__ . '/vendor/autoload.php';

class FFToolsHelper {
    public static function redirect($params)
    {
        $app = Factory::getApplication();

        if ($app->isAdmin()) {
            return;
        }

        $currentUrl = (string) Uri::getInstance();

        $scheme = self::getScheme($params);
        $host = self::getHost($params);

        $targetUrl = $scheme . '://' . $host . $_SERVER['REQUEST_URI'];

        if ($currentUrl !== $targetUrl) {
            header("HTTP/1.1 301 Moved Permanently");
            header('Location: ' . $targetUrl);
            exit();
        }
    }

    protected static function getHost($params)
    {
        $localhost = array('127.0.0.1', '::1', 'localhost');
        if (in_array($_SERVER['SERVER_NAME'], $localhost)) {
            return $_SERVER['SERVER_NAME'];
        }

        $wwwRedirect = $params->get('www');
        if (!$wwwRedirect) {
            return $_SERVER['SERVER_NAME'];
        }

        if ($wwwRedirect === 'none-www') {
            return preg_replace('/^(www\.)(\w+\.\w+)$/', '$2', $_SERVER['SERVER_NAME']);
        }

        $isWWW = preg_match('/^(www\.)(\w+\.\w+)$/', $_SERVER['SERVER_NAME']);
        if ($wwwRedirect === 'www' && !$isWWW) {
            return 'www.' . $_SERVER['SERVER_NAME'];
        }

        return $_SERVER['SERVER_NAME'];
    }

    protected static function getScheme($params)
    {
        $config = Factory::getConfig();

        if ($config->get('force_ssl') === '2') {
            return 'https';
        }

        $sslRedirect = $params->get('ssl');
        return $sslRedirect ? $sslRedirect : (isset($_SERVER['HTTPS']) ? 'https' : 'http');
    }

    public static function mootoolsRemove($params)
    {
        $remove = $params->get('remove-mootools');
        $app = Factory::getApplication();

        if (!$remove) {
            return;
        }

        if ($remove === 'site' && !$app->isClient('site')) {
            return;
        }

        if ($remove === 'admin' && !$app->isClient('administrator')) {
            return;
        }
        
        self::overrideBehavior();
    }

    protected static function overrideBehavior()
    {
        $content = file_get_contents(JPATH_ROOT . '/libraries/cms/html/behavior.php');
        $content = str_replace('abstract class JHtmlBehavior', 'abstract class JHtmlBehavior_Original', $content);
        $content = str_replace('<?php', '', $content);
        eval($content);

        require_once __DIR__ . '/override/behavior.php';
    }

    public static function minifyJS($exclude)
    {
        $chunk = self::getJsChunk($exclude);
        $doc = Factory::getDocument();
        $scripts = $doc->_scripts;
        $doc->_scripts = array();

        $absUriPattern = '/^' . preg_quote(Uri::root(), '/') . '/';
        $relUriPattern = '/^' . preg_quote(Uri::root(true), '/') . '/';

        foreach ($chunk as $value) {
            if (is_string($value)) {
                $doc->_scripts[$value] = $scripts[$value];
                continue;
            }

            if (is_array($value)) {
                $hash = md5(implode('', $value));
                $cachePath = JPATH_ROOT . '/cache/ff_tools_js_cache/cache-' . $hash . '.js';
                $cacheUrl = Uri::root(true) . '/cache/ff_tools_js_cache/cache-' . $hash . '.js';
                if (is_file($cachePath)) {
                    $doc->addScript($cacheUrl);
                    continue;
                }

                $content = '';
                foreach ($value as $file) {
                    $path = preg_replace('/\?.*$/', '', $file);
                    $path = preg_replace($absUriPattern, Uri::root(true) . '/', $path);
                    $path = preg_replace($relUriPattern, '', $path);
                    $content .= is_file(JPATH_ROOT . $path) ? file_get_contents(JPATH_ROOT . $path) : '';
                    $content .= "\n";
                }
                
                $minifier = new MatthiasMullie\Minify\JS($content);
                $cache = $minifier->minify();
                if (File::write($cachePath, $cache)) {
                    $doc->addScript($cacheUrl);
                }
            }
        }
    }

    protected static function getJsChunk($exclude)
    {
        $exclude = explode("\n", $exclude);
        $exclude = array_map('trim', $exclude);

        $doc = Factory::getDocument();
        $scripts = $doc->_scripts;

        $absUriPattern = '/^' . preg_quote(Uri::root(), '/') . '/';
        $relUriPattern = '/^' . preg_quote(Uri::root(true), '/') . '/';

        $chunk = array();
        $bundle = array();
        foreach ($scripts as $key => $script) {
            foreach ($exclude as $ex) {
                if ($ex && mb_strpos($key, $ex) !== false) {
                    if ($bundle) {
                        $chunk[] = $bundle;
                        $bundle = array();
                    }
                    $chunk[] = $key;
                    continue 2;
                }
            }

            $file = preg_replace($relUriPattern, '', $key);
            $file = preg_replace('/\?.*$/', '', $file);
            $isAbsUrl = preg_match($absUriPattern, $key);
            $startWithHttp = preg_match('/^(http|https):\/\//', $key);

            if ((isset($script['defer']) || isset($script['async']))
                || (isset($script['options']['conditional']))
                || ($startWithHttp && !$isAbsUrl)
                || (!is_file(JPATH_ROOT . $file) && !$isAbsUrl)) {
                if ($bundle) {
                    $chunk[] = $bundle;
                    $bundle = array();
                }
                $chunk[] = $key;
                continue;
            }

            $bundle[] = $key;
        }

        if ($bundle) {
            $chunk[] = $bundle;
        }

        return $chunk;
    }


    public static function minifyCSS($exclude)
    {
        $chunk = self::getCssChunk($exclude);
        $doc = Factory::getDocument();
        $styles = $doc->_styleSheets;
        $doc->_styleSheets = array();

        $absUriPattern = '/^' . preg_quote(Uri::root(), '/') . '/';
        $relUriPattern = '/^' . preg_quote(Uri::root(true), '/') . '/';

        foreach ($chunk as $value) {
            if (is_string($value)) {
                $doc->_styleSheets[$value] = $styles[$value];
                continue;
            }

            if (is_array($value)) {
                $hash = md5(implode('', $value));
                $cachePath = JPATH_ROOT . '/cache/ff_tools_css_cache/cache-' . $hash . '.css';
                $cacheUrl = Uri::root(true) . '/cache/ff_tools_css_cache/cache-' . $hash . '.css';
                if (is_file($cachePath)) {
                    $doc->addStyleSheet($cacheUrl);
                    continue;
                }

                $content = '';
                foreach ($value as $file) {
                    $path = preg_replace('/\?.*$/', '', $file);
                    $path = preg_replace($absUriPattern, Uri::root(true) . '/', $path);
                    $path = preg_replace($relUriPattern, '', $path);
                    $content .= is_file(JPATH_ROOT . $path) ? file_get_contents(JPATH_ROOT . $path) : '';
                    $content .= "\n";
                }
                
                $minifier = new MatthiasMullie\Minify\CSS($content);
                $cache = $minifier->minify();
                if (File::write($cachePath, $cache)) {
                    $doc->addStyleSheet($cacheUrl);
                }
            }
        }
    }

    protected static function getCssChunk($exclude)
    {
        $exclude = explode("\n", $exclude);
        $exclude = array_map('trim', $exclude);

        $doc = Factory::getDocument();
        $doc->addStyleSheet(Uri::root() . 'test.css');
        
        $styles = $doc->_styleSheets;
        $absUriPattern = '/^' . preg_quote(Uri::root(), '/') . '/';
        $relUriPattern = '/^' . preg_quote(Uri::root(true), '/') . '/';

        $chunk = array();
        $bundle = array();
        foreach ($styles as $key => $style) {
            foreach ($exclude as $ex) {
                if ($ex && mb_strpos($key, $ex) !== false) {
                    if ($bundle) {
                        $chunk[] = $bundle;
                        $bundle = array();
                    }
                    $chunk[] = $key;
                    continue 2;
                }
            }

            $file = preg_replace('/\?.*$/', '', $key);
            $file = preg_replace($relUriPattern, '', $file);
            $isAbsUrl = preg_match($absUriPattern, $key);
            $startWithHttp = preg_match('/^(http|https):\/\//', $key);

            if ((isset($style['options']['conditional']))
                || ($startWithHttp && !$isAbsUrl)
                || (!is_file(JPATH_ROOT . $file) && !$isAbsUrl)) {
                if ($bundle) {
                    $chunk[] = $bundle;
                    $bundle = array();
                }
                $chunk[] = $key;
                continue;
            }

            $bundle[] = $key;
        }

        if ($bundle) {
            $chunk[] = $bundle;
        }

        return $chunk;
    }
}