<?php
/**
 * Abstract plugin
 *
 * @package langsuggest
 * @subpackage plugin
 */

namespace TreehillStudio\LangSuggest\Plugins;

use modX;
use LangSuggest;

/**
 * Class Plugin
 */
abstract class Plugin
{
    /** @var modX $modx */
    protected $modx;
    /** @var LangSuggest $langsuggest */
    protected $langsuggest;
    /** @var array $scriptProperties */
    protected $scriptProperties;

    /**
     * Plugin constructor.
     *
     * @param $modx
     * @param $scriptProperties
     */
    public function __construct($modx, &$scriptProperties)
    {
        $this->scriptProperties = &$scriptProperties;
        $this->modx = &$modx;
        $corePath = $this->modx->getOption('langsuggest.core_path', null, $this->modx->getOption('core_path') . 'components/langsuggest/');
        $this->langsuggest = $this->modx->getService('langsuggest', 'LangSuggest', $corePath . 'model/langsuggest/', [
            'core_path' => $corePath
        ]);
    }

    /**
     * Run the plugin event.
     */
    public function run()
    {
        $init = $this->init();
        if ($init !== true) {
            return;
        }

        $this->process();
    }

    /**
     * Initialize the plugin event.
     *
     * @return bool
     */
    public function init(): bool
    {
        return true;
    }

    /**
     * Process the plugin event code.
     *
     * @return mixed
     */
    abstract public function process();
}