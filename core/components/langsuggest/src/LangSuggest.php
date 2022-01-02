<?php
/**
 * LangSuggest classfile
 *
 * Copyright 2019-2021 by Thomas Jakobi <office@treehillstudio.com>
 *
 * @package langsuggest
 * @subpackage classfile
 */

namespace TreehillStudio\LangSuggest;

use modX;
use xPDO;

/**
 * Class LangSuggest
 */
class LangSuggest
{
    /**
     * A reference to the modX instance
     * @var modX $modx
     */
    public $modx;

    /**
     * The namespace
     * @var string $namespace
     */
    public $namespace = 'langsuggest';

    /**
     * The package name
     * @var string $packageName
     */
    public $packageName = 'LangSuggest';

    /**
     * The version
     * @var string $version
     */
    public $version = '1.1.0';

    /**
     * The class options
     * @var array $options
     */
    public $options = [];

    /**
     * Template cache
     * @var array $_tplCache
     */
    private $_tplCache;

    /**
     * Valid binding types
     * @var array $_validTypes
     */
    private $_validTypes = array(
        '@CHUNK',
        '@FILE',
        '@INLINE'
    );

    /**
     * LangSuggest constructor
     *
     * @param modX $modx A reference to the modX instance.
     * @param array $options An array of options. Optional.
     */
    public function __construct(modX &$modx, $options = [])
    {
        $this->modx = &$modx;
        $this->namespace = $this->getOption('namespace', $options, $this->namespace);

        $corePath = $this->getOption('core_path', $options, $this->modx->getOption('core_path', null, MODX_CORE_PATH) . 'components/' . $this->namespace . '/');
        $assetsPath = $this->getOption('assets_path', $options, $this->modx->getOption('assets_path', null, MODX_ASSETS_PATH) . 'components/' . $this->namespace . '/');
        $assetsUrl = $this->getOption('assets_url', $options, $this->modx->getOption('assets_url', null, MODX_ASSETS_URL) . 'components/' . $this->namespace . '/');

        // Load some default paths for easier management
        $this->options = array_merge([
            'namespace' => $this->namespace,
            'version' => $this->version,
            'corePath' => $corePath,
            'modelPath' => $corePath . 'model/',
            'vendorPath' => $corePath . 'vendor/',
            'chunksPath' => $corePath . 'elements/chunks/',
            'pagesPath' => $corePath . 'elements/pages/',
            'snippetsPath' => $corePath . 'elements/snippets/',
            'pluginsPath' => $corePath . 'elements/plugins/',
            'controllersPath' => $corePath . 'controllers/',
            'processorsPath' => $corePath . 'processors/',
            'templatesPath' => $corePath . 'templates/',
            'assetsPath' => $assetsPath,
            'assetsUrl' => $assetsUrl,
            'jsUrl' => $assetsUrl . 'js/',
            'cssUrl' => $assetsUrl . 'css/',
            'imagesUrl' => $assetsUrl . 'images/',
            'connectorUrl' => $assetsUrl . 'connector.php'
        ], $options);

        // Add default options
        $this->options = array_merge($this->options, [
            'debug' => (bool)$this->modx->getOption($this->namespace . '.debug', null, '0') == 1,
            'cacheKey' => $this->namespace . '.contextmap',
            'cookie_expiration' => (int)$this->modx->getOption($this->namespace . '.cookie_expiration', null, '365'),
            'cookie_name' => $this->modx->getOption($this->namespace . '.cookie_name', null, 'LangSuggest'),
            'display_count' => (int)$this->modx->getOption($this->namespace . '.display_count', null, '3'),
            'tpl' => $this->modx->getOption($this->namespace . '.tpl', null, 'tplLangSuggestModal'),
        ]);

        $lexicon = $this->modx->getService('lexicon', 'modLexicon');
        $lexicon->load($this->namespace . ':default');
    }

    /**
     * Get a local configuration option or a namespaced system setting by key.
     *
     * @param string $key The option key to search for.
     * @param array $options An array of options that override local options.
     * @param mixed $default The default value returned if the option is not found locally or as a
     * namespaced system setting; by default this value is null.
     * @return mixed The option value or the default value specified.
     */
    public function getOption(string $key, $options = [], $default = null)
    {
        $option = $default;
        if (!empty($key) && is_string($key)) {
            if ($options != null && array_key_exists($key, $options)) {
                $option = $options[$key];
            } elseif (array_key_exists($key, $this->options)) {
                $option = $this->options[$key];
            } elseif (array_key_exists("{$this->namespace}.{$key}", $this->modx->config)) {
                $option = $this->modx->getOption("{$this->namespace}.{$key}");
            }
        }
        return $option;
    }

    /**
     * Get contexts and their cultureKeys
     *
     * @param array $contexts
     * @return array
     */
    public function contextmap($contexts): array
    {
        $contextmap = array();
        foreach ($contexts as $context) {
            $ctx = $this->modx->getContext($context);
            if (isset($ctx->config['cultureKey'])) {
                $contextmap[$ctx->config['cultureKey']] = trim($context);
            } else {
                $this->modx->log(xpdo::LOG_LEVEL_ERROR, 'No cultureKey exists in the "' . $context . '" context!', '', 'LangSuggest');
            }
            if (isset($ctx->config['cultureKeyAliases'])) {
                $cultureKeyAliases = explode(',', $ctx->config['cultureKeyAliases']);
                foreach ($cultureKeyAliases as $cultureKeyAlias) {
                    $contextmap[$cultureKeyAlias] = trim($context);
                }
            }
        }
        return $contextmap;
    }

    /**
     * Detects client language preferences and returns associative array sorted
     * by importance (q factor)
     *
     * @return array
     */
    public function clientLangDetect(): array
    {
        $langs = array();

        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            // break up string into pieces (languages and q factors)
            preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $lang_parse);

            if (count($lang_parse[1])) {
                $langs = array_combine($lang_parse[1], $lang_parse[4]);

                // set default to 1 (or decremented by 0.01) for any language without q factor
                $q = 1;
                foreach ($langs as $lang => $val) {
                    if ($val === '') {
                        $langs[$lang] = $q;
                        $q = strval($q - 0.01);
                    } else {
                        $q = strval($val - 0.01);
                    }
                }
                arsort($langs, SORT_NUMERIC);
            }
        }
        return $langs;
    }

    /**
     * Detect a context key
     *
     * @param array $contextmap
     * @return string
     */
    public function contextKeyDetect($contextmap): string
    {
        $clientLangs = array_flip($this->clientLangDetect());

        $clientCultureKeys = array();
        foreach ($contextmap as $k => $v) {
            $context = preg_split('/[-_]/', $k);
            $matches = preg_grep('/^' . $context[0] . '/', $clientLangs);
            if (count($matches) > 0) {
                // Get the q factor of the current clientLang
                $clientCultureKeys[$k] = floatval(key($matches));
            }
        }
        arsort($clientCultureKeys, SORT_NUMERIC);

        if (count($clientCultureKeys)) {
            $cultureKey = key($clientCultureKeys);
            $contextKey = $contextmap[$cultureKey];
        } else {
            $contextKey = trim($this->modx->getOption('babel.contextDefault', null, 'web'));
        }
        return $contextKey;
    }

    /**
     * Appends the processed cookie consent chunk to the generated resource output BODY tag
     */
    public function showLangSuggest()
    {
        // Get contexts and their cultureKeys
        $cacheKey = $this->namespace . '.contextmap';
        $contextmap = $this->modx->cacheManager->get($cacheKey);
        if (empty($contextmap)) {
            $babelContexts = explode(',', $this->modx->getOption('babel.contextKeys', null, 'en'));
            $contextmap = $this->contextmap($babelContexts);
            $this->modx->cacheManager->set($cacheKey, $contextmap);
        }

        $preferredContextKey = $this->contextKeyDetect($contextmap);

        if ($preferredContextKey !== $this->modx->context->get('key')) {
            $tpl = $this->getOption('tpl');
            $preferredContext = $this->modx->getContext($preferredContextKey);

            if ($preferredContext) {
                $currentId = $this->modx->resource->get('id');
                if (!$foreignId = $this->modx->runSnippet('BabelTranslation', array(
                    'resourceId' => $currentId,
                    'contextKey' => $preferredContextKey
                ))) {
                    $foreignId = $preferredContext->getOption('site_start');
                }

                $placeholder = array(
                    'cookie_name' => $this->getOption('cookie_name'),
                    'cookie_expiration' => $this->getOption('cookie_expiration'),
                    'current_id' => $currentId,
                    'redirect_id' => $foreignId,
                    'debug' => strval($this->getOption('debug')),
                    'cultureKey' => $preferredContext->getOption('cultureKey')
                );

                if ($this->getOption('debug')) {
                    $this->modx->log(xPDO::LOG_LEVEL_ERROR, 'LangSuggest is shown.', '', 'LangSuggest');
                    $this->modx->log(xPDO::LOG_LEVEL_ERROR, 'Placeholder: ' . print_r($placeholder, true), '', 'LangSuggest');
                }

                $chunk = $this->getChunk($tpl, $placeholder);
                $output = &$this->modx->resource->_output;

                if ($this->getOption('chunk_position') === 'top') {
                    // Emulate regClient for the javascript
                    $output = preg_replace_callback('#<body[^>]*>#', function ($match) use ($chunk) {
                        if (strpos($match[0], 'class="') !== false) {
                            $match[0] = str_replace('class="', 'class="langsuggestActive ', $match[0]);
                        } else {
                            $match[0] = str_replace('>', ' class="langsuggestActive">', $match[0]);
                        }
                        return $match[0] . "\n" . $chunk;
                    }, $output, 1);

                } else {
                    // Emulate regClient for the chunk code
                    $output = preg_replace_callback('#</body>#', function ($match) use ($chunk) {
                        return $chunk . "\n" . $match[0];
                    }, $output, 1);
                }
            } else {
                $this->modx->log(xPDO::LOG_LEVEL_ERROR, 'Preferred context was not found.', '', 'LangSuggest');
            }
        }
    }

    /**
     * Parse a chunk (with template bindings)
     * Modified parseTplElement method from getResources package (https://github.com/opengeek/getResources)
     *
     * @param $type
     * @param $source
     * @param null $properties
     * @return string
     */
    private function parseChunk($type, $source, $properties = null): string
    {
        $output = false;

        if (!is_string($type) || !in_array($type, $this->_validTypes)) {
            $type = $this->modx->getOption('tplType', $properties, '@CHUNK');
        }

        $content = false;
        switch ($type) {
            case '@FILE':
                $path = $this->modx->getOption('tplPath', $properties, $this->modx->getOption('assets_path', $properties, MODX_ASSETS_PATH) . 'elements/chunks/');
                $key = $path . $source;
                if (!isset($this->_tplCache['@FILE'])) {
                    $this->_tplCache['@FILE'] = array();
                }
                if (!array_key_exists($key, $this->_tplCache['@FILE'])) {
                    if (file_exists($key)) {
                        $content = file_get_contents($key);
                    }
                    $this->_tplCache['@FILE'][$key] = $content;
                } else {
                    $content = $this->_tplCache['@FILE'][$key];
                }
                if (!empty($content) && $content !== '0') {
                    $chunk = $this->modx->newObject('modChunk', array('name' => $key));
                    $chunk->setCacheable(false);
                    $output = $chunk->process($properties, $content);
                }
                break;
            case '@INLINE':
                $uniqid = uniqid();
                $chunk = $this->modx->newObject('modChunk', array('name' => "$type-$uniqid"));
                $chunk->setCacheable(false);
                $output = $chunk->process($properties, $source);
                break;
            case '@CHUNK':
            default:
                $chunk = null;
                if (!isset($this->_tplCache['@CHUNK'])) {
                    $this->_tplCache['@CHUNK'] = array();
                }
                if (!array_key_exists($source, $this->_tplCache['@CHUNK'])) {
                    $chunk = $this->modx->getObject('modChunk', array('name' => $source));
                    if ($chunk) {
                        $this->_tplCache['@CHUNK'][$source] = $chunk->toArray('', true);
                    } else {
                        $this->_tplCache['@CHUNK'][$source] = false;
                    }
                } elseif (is_array($this->_tplCache['@CHUNK'][$source])) {
                    $chunk = $this->modx->newObject('modChunk');
                    $chunk->fromArray($this->_tplCache['@CHUNK'][$source], '', true, true, true);
                }
                if (is_object($chunk)) {
                    $chunk->setCacheable(false);
                    $output = $chunk->process($properties);
                }
                break;
        }
        return $output;
    }

    /**
     * Get and parse a chunk (with template bindings)
     * Modified parseTpl method from getResources package (https://github.com/opengeek/getResources)
     *
     * @param $tpl
     * @param null $properties
     * @return string|bool
     */
    public function getChunk($tpl, $properties = null)
    {
        $output = false;
        if (!empty($tpl)) {
            $bound = array(
                'type' => '@CHUNK',
                'value' => $tpl
            );
            if (strpos($tpl, '@') === 0) {
                $endPos = strpos($tpl, ' ');
                if ($endPos > 2 && $endPos < 10) {
                    $tt = substr($tpl, 0, $endPos);
                    if (in_array($tt, $this->_validTypes)) {
                        $bound['type'] = $tt;
                        $bound['value'] = substr($tpl, $endPos + 1);
                    }
                }
            }
            if (is_array($bound) && isset($bound['type']) && isset($bound['value'])) {
                $output = $this->parseChunk($bound['type'], $bound['value'], $properties);
            }
        }
        return $output;
    }
}
