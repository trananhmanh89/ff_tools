<?php
defined('_JEXEC') or die('Restricted access');

require __DIR__ . '/vendor/autoload.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Uri\Uri;

class FFToolsHelper
{
    public static function redirect($params)
    {
        $app = Factory::getApplication();

        if ($app->isClient('administrator')) {
            return;
        }

        $uri = Uri::getInstance();
        $currentSchema = $uri->getScheme();
        $currentHost = $uri->getHost();
        $config = Factory::getConfig();

        if ($config->get('force_ssl') === '2' || $params->get('ssl') === 'https') {
            $uri->setScheme('https');
        } else {
            $uri->setScheme('http');
        }

        $localhost = array('127.0.0.1', '::1', 'localhost');
        $host = $currentHost;
        if (!in_array($currentHost, $localhost)) {
            $wwwRedirect = $params->get('www');

            if ($wwwRedirect === 'none-www') {
                $host = preg_replace('/^(www\.)(\w+\.\w+)$/', '$2', $currentHost);
            }

            $isWWW = preg_match('/^(www\.)(\w+\.\w+)$/', $currentHost);
            if ($wwwRedirect === 'www' && !$isWWW) {
                $host = 'www.' . $_SERVER['SERVER_NAME'];
            }
        }

        $uri->setHost($host);

        if ($currentHost !== $uri->getHost() || $currentSchema !== $uri->getScheme()) {
            header("HTTP/1.1 301 Moved Permanently");
            header('Location: ' . $uri->toString());
            exit();
        }
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
                $cacheUrl = Uri::root(true) . '/cache/ff_tools_js_cache/cache-' . $hash . '.js?' . md5(date('Y-m-d'));
                if (is_file($cachePath)) {
                    $doc->addScript($cacheUrl);
                    continue;
                }

                $cache = '';
                foreach ($value as $file) {
                    $path = preg_replace('/\?.*$/', '', $file);
                    $path = preg_replace($absUriPattern, Uri::root(true) . '/', $path);
                    $path = preg_replace($relUriPattern, '', $path);
                    $content = is_file(JPATH_ROOT . $path) ? file_get_contents(JPATH_ROOT . $path) : '';
                    $minifier = new MatthiasMullie\Minify\JS($content);
                    $cache .= $minifier->minify();
                    $cache .= ";\n";
                }

                if (File::write($cachePath, $cache)) {
                    $doc->addScript($cacheUrl);
                }
            }
        }
    }

    protected static function getJsChunk($exclude)
    {
        $exclude = explode("\n", $exclude);
        $exclude = array_filter($exclude, function ($value) {
            return $value ? trim($value) : false;
        });
        $exclude = array_map('trim', $exclude);
        $exclude[] = 'media/editors/tinymce/tinymce.min.js';

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
                || (!is_file(JPATH_ROOT . $file) && !$isAbsUrl)
            ) {
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
                $cacheUrl = Uri::root(true) . '/cache/ff_tools_css_cache/cache-' . $hash . '.css?' . md5(date('Y-m-d'));
                if (is_file($cachePath)) {
                    $doc->addStyleSheet($cacheUrl);
                    continue;
                }

                $cache = '';
                foreach ($value as $file) {
                    $path = preg_replace('/\?.*$/', '', $file);
                    $path = preg_replace($absUriPattern, Uri::root(true) . '/', $path);
                    $path = preg_replace($relUriPattern, '', $path);
                    $content = is_file(JPATH_ROOT . $path) ? file_get_contents(JPATH_ROOT . $path) : '';

                    $output = self::_minifyCss($content);
                    $output = self::correctCssUrlPath(JPATH_ROOT . $path, $output);
                    $output = self::correctCssImportPath(JPATH_ROOT . $path, $output);

                    $cache .= "/* $file */ ";
                    $cache .= $output;
                    $cache .= "\n";
                }

                $cache = self::moveCssImportToTop($cache);

                if (File::write($cachePath, $cache)) {
                    $doc->addStyleSheet($cacheUrl);
                }
            }
        }
    }

    protected static function _minifyCss($css)
    {
        $css = preg_replace('~\s+~', ' ', $css);
        $css = preg_replace('~/\*.*?\*/~s', '', $css);
        $css = str_replace('; ', ';', $css);
        $css = str_replace(': ', ':', $css);
        $css = str_replace(' {', '{', $css);
        $css = str_replace('{ ', '{', $css);
        $css = str_replace(', ', ',', $css);
        $css = str_replace('} ', '}', $css);
        $css = str_replace(';}', '}', $css);

        return trim($css);
    }

    protected static function moveCssImportToTop($str)
    {
        $import = '';

        preg_match_all('/@import .*?;/', $str, $matches);
        if (!empty($matches[0])) {
            foreach ($matches[0] as $match) {
                $str = str_replace($match, '', $str);
                $import .= "$match\n";
            }
        }

        return $import . $str;
    }

    protected static function correctCssImportPath($file, $str)
    {
        $file = realpath($file);
        $info = pathinfo($file);
        $basePath = $info['dirname'] . '/';

        preg_match_all('/@import ([\'|"].*?[\'|"]).*?;/', $str, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $match) {
                $url = trim($match, '"');
                $url = trim($url, "'");

                preg_match('/[\?|#].*?$/', $url, $tail);
                $url = preg_replace('/[\?|#].*?$/', '', $url);

                $path = realpath($basePath . $url);

                if (!$path) {
                    continue;
                }

                $relUrl = Uri::root(true) . str_replace(JPATH_ROOT, '', $path);
                $relUrl = str_replace('\\', '/', $relUrl) . (isset($tail[0]) ? $tail[0] : '');
                $relUrl = "'$relUrl'";
                $str = str_replace('@import ' . $match, '@import ' . $relUrl, $str);
            }
        }

        return $str;
    }

    protected static function correctCssUrlPath($file, $str)
    {
        $file = realpath($file);
        $info = pathinfo($file);
        $basePath = $info['dirname'] . '/';

        preg_match_all('/url\((.*?)\)/', $str, $matches);
        if (isset($matches[1])) {
            foreach ($matches[1] as $match) {
                $url = trim($match, '"');
                $url = trim($url, "'");

                preg_match('/[\?|#].*?$/', $url, $tail);
                $url = preg_replace('/[\?|#].*?$/', '', $url);

                $path = realpath($basePath . $url);

                if (!$path) {
                    continue;
                }

                $relUrl = Uri::root(true) . str_replace(JPATH_ROOT, '', $path);
                $relUrl = str_replace('\\', '/', $relUrl) . (isset($tail[0]) ? $tail[0] : '');
                $relUrl = "'$relUrl'";
                $str = str_replace('url(' . $match . ')', 'url(' . $relUrl . ')', $str);
            }
        }

        return $str;
    }

    protected static function getCssChunk($exclude)
    {
        $exclude = explode("\n", $exclude);
        $exclude = array_map('trim', $exclude);

        $doc = Factory::getDocument();

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
                || (!is_file(JPATH_ROOT . $file) && !$isAbsUrl)
            ) {
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

    public static function moveScriptToBottom($scripts, $inlineScripts)
    {
        $document = Factory::getDocument();
        $app = Factory::getApplication();
        $body = $app->getBody();

        $lnEnd        = $document->_getLineEnd();
        $tab          = $document->_getTab();
        $buffer       = '';
        $mediaVersion = $document->getMediaVersion();
        $defaultJsMimes         = array('text/javascript', 'application/javascript', 'text/x-javascript', 'application/x-javascript');
        $html5NoValueAttributes = array('defer', 'async');
        // Generate script file links
        foreach ($scripts as $src => $attribs) {
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
        foreach ($inlineScripts as $type => $content) {
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

    public static function inlineCss($styleSheets)
    {
        $app = Factory::getApplication();
        $body = $app->getBody();
        foreach ($styleSheets as $value) {
            $frags = explode('?', $value);
            $file = JPATH_ROOT . $frags[0];
            $content = "<style>\n" . file_get_contents($file) . "</style>";
            $search = '/<link href="' . preg_quote($value, '/') . '.*?\/>/';
            $body = preg_replace($search, $content, $body);
        }

        $app->setBody($body);
    }
}
