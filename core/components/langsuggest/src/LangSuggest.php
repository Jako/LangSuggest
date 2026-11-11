<?php
/**
 * LangSuggest classfile
 *
 * Copyright 2019-2026 by Thomas Jakobi <office@treehillstudio.com>
 *
 * @package langsuggest
 * @subpackage classfile
 */

namespace TreehillStudio\LangSuggest;

use modContext;
use modX;
use xPDO;

/**
 * Class LangSuggest
 */
class LangSuggest
{
    /**
     * The class added to the body tag, when LangSuggest is shown
     */
    const BODY_CLASS = 'langsuggestActive';

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
    public $version = '1.1.1';

    /**
     * The class options
     * @var array $options
     */
    public $options = [];

    /**
     * Template cache
     * @var array $_tplCache
     */
    private $_tplCache = [
        '@CHUNK' => [],
        '@FILE' => [],
    ];

    /**
     * Valid binding types
     * @var array $_validTypes
     */
    private $_validTypes = [
        '@CHUNK',
        '@FILE',
        '@INLINE'
    ];

    /**
     * LangSuggest constructor
     *
     * @param modX $modx A reference to the modX instance.
     * @param array $options An array of options. Optional.
     */
    public function __construct(modX &$modx, $options = [])
    {
        $this->modx =& $modx;

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
            'debug' => (bool)$this->modx->getOption($this->namespace . '.debug', null, false),
            'cacheKey' => $this->namespace . '.contextmap',
            'chunk_position' => $this->modx->getOption($this->namespace . '.chunk_position', null, 'bottom', true),
            'cookie_expiration' => (int)$this->modx->getOption($this->namespace . '.cookie_expiration', null, 365),
            'cookie_name' => $this->sanitizeCookieName($this->modx->getOption($this->namespace . '.cookie_name', null, 'LangSuggest', true)),
            'display_count' => (int)$this->modx->getOption($this->namespace . '.display_count', null, 3),
            'no_session' => (bool)$this->modx->getOption($this->namespace . '.no_session', null, false),
            'tpl' => $this->modx->getOption($this->namespace . '.tpl', null, 'tplLangSuggestModal', true),
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
        if (!empty($key)) {
            if (is_array($options) && array_key_exists($key, $options)) {
                $option = $options[$key];
            } elseif (array_key_exists($key, $this->options)) {
                $option = $this->options[$key];
            } elseif (array_key_exists("$this->namespace.$key", $this->modx->config)) {
                $option = $this->modx->getOption("$this->namespace.$key");
            }
        }
        return $option;
    }

    /**
     * Restrict a cookie name to characters, that PHP and the browsers handle the same way.
     * PHP converts dots and spaces in cookie names to underscores, other characters are invalid.
     *
     * @param string $name
     * @return string
     */
    private function sanitizeCookieName($name): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '_', trim((string)$name)) ?: 'LangSuggest';
    }

    /**
     * Get contexts and their cultureKeys
     *
     * @param array $contexts
     * @return array cultureKey => context key
     */
    public function contextmap($contexts): array
    {
        $contextmap = [];
        foreach ($contexts as $context) {
            $context = trim($context);
            if ($context === '') {
                continue;
            }
            $ctx = $this->modx->getContext($context);
            if (!$ctx) {
                $this->logError('The "' . $context . '" context does not exist!');
                continue;
            }
            if (!empty($ctx->config['cultureKey'])) {
                $contextmap[trim($ctx->config['cultureKey'])] = $context;
            } else {
                $this->logError('No cultureKey exists in the "' . $context . '" context!');
            }
            if (!empty($ctx->config['cultureKeyAliases'])) {
                foreach ($this->splitList($ctx->config['cultureKeyAliases'], ',') as $cultureKeyAlias) {
                    $contextmap[$cultureKeyAlias] = $context;
                }
            }
        }
        return $contextmap;
    }

    /**
     * Get the cached context map of the Babel contexts
     *
     * @return array cultureKey => context key
     */
    public function getContextmap(): array
    {
        $cacheKey = $this->getOption('cacheKey');
        $contextmap = $this->modx->cacheManager->get($cacheKey);
        if (empty($contextmap)) {
            // Babel context groups are separated by semicolons, contexts by commas
            $babelContexts = $this->splitList($this->modx->getOption('babel.contextKeys', null, 'web'), ',;');
            $contextmap = $this->contextmap($babelContexts);
            $this->modx->cacheManager->set($cacheKey, $contextmap);
        }
        return $contextmap;
    }

    /**
     * Detects client language preferences and returns associative array sorted
     * by importance (q factor)
     *
     * @return array language => q factor
     */
    public function clientLangDetect(): array
    {
        return $this->parseAcceptLanguage($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    }

    /**
     * Parse an Accept-Language header
     *
     * @param string $header
     * @return array Lowercase language => q factor, sorted by q factor and header order
     */
    public function parseAcceptLanguage(string $header): array
    {
        preg_match_all('/([a-z]{1,8}(?:-[a-z0-9]{1,8})*)\s*(?:;\s*q\s*=\s*([01](?:\.[0-9]{0,3})?))?/i', $header, $matches);

        $langs = [];
        $positions = [];
        foreach ($matches[1] as $i => $lang) {
            // A language without q factor has the q factor 1 (RFC 9110)
            $q = ($matches[2][$i] === '') ? 1.0 : (float)$matches[2][$i];

            // q=0 means "not acceptable"
            if ($q <= 0) {
                continue;
            }
            $lang = strtolower($lang);
            if (!isset($langs[$lang])) {
                $positions[$lang] = $i;
                $langs[$lang] = $q;
            } elseif ($langs[$lang] < $q) {
                $langs[$lang] = $q;
            }
        }

        // Sort by q factor, keep the header order for equal q factors
        uksort($langs, function ($a, $b) use ($langs, $positions) {
            return ($langs[$b] <=> $langs[$a]) ?: ($positions[$a] <=> $positions[$b]);
        });

        return $langs;
    }

    /**
     * Detect a context key
     *
     * @param array $contextmap cultureKey => context key
     * @return string
     */
    public function contextKeyDetect($contextmap): string
    {
        // Client languages sorted by q factor (highest first)
        $clientLangs = array_keys($this->clientLangDetect());

        $cultureKeys = [];
        foreach ($contextmap as $cultureKey => $contextKey) {
            $cultureKeys[$this->normalizeLanguage($cultureKey)] = $contextKey;
        }

        foreach ($clientLangs as $clientLang) {
            // Exact match i.e. pt-br => pt-br
            if (isset($cultureKeys[$clientLang])) {
                return $cultureKeys[$clientLang];
            }

            // Base language match i.e. pt-br => pt-pt
            $base = $this->baseLanguage($clientLang);
            $candidates = array_filter($cultureKeys, function ($cultureKey) use ($base) {
                return $this->baseLanguage($cultureKey) === $base;
            }, ARRAY_FILTER_USE_KEY);
            if ($candidates) {
                // Prefer a candidate the client requests explicitly with a lower q factor
                foreach ($clientLangs as $otherLang) {
                    if (isset($candidates[$otherLang])) {
                        return $candidates[$otherLang];
                    }
                }
                // Then the plain base language, then the first candidate
                return $candidates[$base] ?? reset($candidates);
            }
        }

        return trim($this->modx->getOption('babel.contextDefault', null, 'web'));
    }

    /**
     * Normalize a language tag/culture key (lowercase, hyphen separated)
     *
     * @param string $language
     * @return string
     */
    private function normalizeLanguage($language): string
    {
        return strtolower(str_replace('_', '-', trim((string)$language)));
    }

    /**
     * Get the base language of a normalized language tag i.e. pt-br => pt
     *
     * @param string $language
     * @return string
     */
    private function baseLanguage(string $language): string
    {
        return explode('-', $language)[0];
    }

    /**
     * Adds the processed LangSuggest chunk to the generated resource output BODY tag
     *
     * @return bool True, if the LangSuggest chunk was added to the output
     */
    public function showLangSuggest(): bool
    {
        $preferredContextKey = $this->contextKeyDetect($this->getContextmap());
        if ($preferredContextKey === $this->modx->context->get('key')) {
            return false;
        }

        $preferredContext = $this->modx->getContext($preferredContextKey);
        if (!$preferredContext) {
            $this->logError('Preferred context was not found.');
            return false;
        }

        $currentId = $this->modx->resource->get('id');
        $placeholder = [
            'cookie_name' => $this->getOption('cookie_name'),
            'cookie_expiration' => $this->getOption('cookie_expiration'),
            'current_id' => $currentId,
            'redirect_id' => $this->getRedirectId($currentId, $preferredContext),
            'debug' => (string)$this->getOption('debug'),
            'cultureKey' => $preferredContext->getOption('cultureKey')
        ];

        $tpl = $this->getOption('tpl');
        $chunk = $this->getChunk($tpl, $placeholder);
        if (empty($chunk)) {
            $this->logError('The LangSuggest chunk "' . $tpl . '" is empty or was not found.');
            return false;
        }

        $shown = $this->insertChunk($this->modx->resource->_output, $chunk, $this->getOption('chunk_position'));

        $this->logDebug('LangSuggest is ' . ($shown ? 'shown' : 'not shown (no body tag)') . '.');
        $this->logDebug('Placeholder: ' . print_r($placeholder, true));

        return $shown;
    }

    /**
     * Get the id of the Babel translation of a resource or the site start of the context
     *
     * @param int $resourceId
     * @param modContext $context
     * @return int
     */
    private function getRedirectId($resourceId, modContext $context): int
    {
        $translationId = $this->modx->runSnippet('BabelTranslation', [
            'resourceId' => $resourceId,
            'contextKey' => $context->get('key')
        ]);
        return (int)($translationId ?: $context->getOption('site_start'));
    }

    /**
     * Insert the chunk after the opening (position top) or before the closing body tag
     * and add the LangSuggest class to the body tag
     *
     * @param string $output The output is changed by reference
     * @param string $chunk
     * @param string $position top|bottom
     * @return bool True, if the chunk was inserted
     */
    private function insertChunk(&$output, string $chunk, $position): bool
    {
        $count = 0;
        if ($position === 'top') {
            $output = preg_replace_callback('#<body\b[^>]*>#i', function ($match) use ($chunk) {
                return $match[0] . "\n" . $chunk;
            }, $output, 1, $count);
        } else {
            $output = preg_replace_callback('#</body\s*>#i', function ($match) use ($chunk) {
                return $chunk . "\n" . $match[0];
            }, $output, 1, $count);
        }

        if ($count === 0) {
            return false;
        }
        $output = $this->addBodyClass($output, self::BODY_CLASS);
        return true;
    }

    /**
     * Add a class to the class attribute of the opening body tag
     *
     * @param string $output
     * @param string $class
     * @return string
     */
    private function addBodyClass(string $output, string $class): string
    {
        return preg_replace_callback('#<body\b[^>]*>#i', function ($match) use ($class) {
            $tag = $match[0];
            // Match a standalone class attribute (not i.e. data-class) with double, single or no quotes
            $pattern = '#(\sclass\s*=\s*)(?:"([^"]*)"|\'([^\']*)\'|([^\s>"\']+))#i';
            if (preg_match($pattern, $tag)) {
                return preg_replace_callback($pattern, function ($attr) use ($class) {
                    // Only one of the alternative groups is filled
                    $classes = ($attr[2] ?? '') . ($attr[3] ?? '') . ($attr[4] ?? '');
                    return $attr[1] . '"' . trim($class . ' ' . $classes) . '"';
                }, $tag, 1);
            }
            return preg_replace('#\s*/?>$#', ' class="' . $class . '">', $tag, 1);
        }, $output, 1);
    }

    /**
     * Split a list by the separator characters, trim the items and remove empty and duplicate items
     *
     * @param string $list
     * @param string $separators
     * @return array
     */
    private function splitList($list, string $separators): array
    {
        $items = preg_split('/[' . preg_quote($separators, '/') . ']/', (string)$list);
        return array_values(array_unique(array_filter(array_map('trim', $items), 'strlen')));
    }

    /**
     * Log an error message
     *
     * @param string $message
     */
    private function logError(string $message)
    {
        $this->modx->log(xPDO::LOG_LEVEL_ERROR, $message, '', $this->packageName);
    }

    /**
     * Log a debug message, if the debug system setting is enabled
     *
     * @param string $message
     */
    public function logDebug(string $message)
    {
        if ($this->getOption('debug')) {
            $this->logError($message);
        }
    }

    /**
     * Parse a chunk (with template bindings)
     * Modified parseTplElement method from getResources package (https://github.com/opengeek/getResources)
     *
     * @param string $type
     * @param string $source
     * @param array|null $properties
     * @return string|bool
     */
    private function parseChunk($type, $source, $properties = null)
    {
        if (!is_string($type) || !in_array($type, $this->_validTypes)) {
            $type = $this->modx->getOption('tplType', $properties, '@CHUNK');
        }

        switch ($type) {
            case '@FILE':
                $path = $this->modx->getOption('tplPath', $properties, $this->modx->getOption('assets_path', $properties, MODX_ASSETS_PATH) . 'elements/chunks/');
                $key = $path . $source;
                if (!array_key_exists($key, $this->_tplCache['@FILE'])) {
                    $this->_tplCache['@FILE'][$key] = file_exists($key) ? file_get_contents($key) : false;
                }
                $content = $this->_tplCache['@FILE'][$key];
                if (empty($content)) {
                    return false;
                }
                $chunk = $this->modx->newObject('modChunk', ['name' => $key]);
                $chunk->setCacheable(false);
                return $chunk->process($properties, $content);
            case '@INLINE':
                $chunk = $this->modx->newObject('modChunk', ['name' => $type . '-' . uniqid()]);
                $chunk->setCacheable(false);
                return $chunk->process($properties, $source);
            case '@CHUNK':
            default:
                $chunk = null;
                if (!array_key_exists($source, $this->_tplCache['@CHUNK'])) {
                    $chunk = $this->modx->getObject('modChunk', ['name' => $source]);
                    $this->_tplCache['@CHUNK'][$source] = $chunk ? $chunk->toArray('', true) : false;
                } elseif (is_array($this->_tplCache['@CHUNK'][$source])) {
                    $chunk = $this->modx->newObject('modChunk');
                    $chunk->fromArray($this->_tplCache['@CHUNK'][$source], '', true, true, true);
                }
                if (!is_object($chunk)) {
                    return false;
                }
                $chunk->setCacheable(false);
                return $chunk->process($properties);
        }
    }

    /**
     * Get and parse a chunk (with template bindings)
     * Modified parseTpl method from getResources package (https://github.com/opengeek/getResources)
     *
     * @param string $tpl
     * @param array|null $properties
     * @return string|bool
     */
    public function getChunk($tpl, $properties = null)
    {
        if (empty($tpl)) {
            return false;
        }

        $type = '@CHUNK';
        $value = $tpl;
        if (strpos($tpl, '@') === 0) {
            $endPos = strpos($tpl, ' ');
            if ($endPos > 2 && $endPos < 10 && in_array(substr($tpl, 0, $endPos), $this->_validTypes)) {
                $type = substr($tpl, 0, $endPos);
                $value = substr($tpl, $endPos + 1);
            }
        }
        return $this->parseChunk($type, $value, $properties);
    }
}
