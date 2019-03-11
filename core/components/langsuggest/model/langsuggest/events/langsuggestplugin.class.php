<?php

/**
 * @package langsuggest
 * @subpackage plugin
 */
abstract class LangsuggestPlugin
{
    /** @var modX $modx */
    protected $modx;
    /** @var Langsuggest $langsuggest */
    protected $langsuggest;
    /** @var array $scriptProperties */
    protected $scriptProperties;

    public function __construct($modx, &$scriptProperties)
    {
        $this->scriptProperties =& $scriptProperties;
        $this->modx = &$modx;
        $corePath = $this->modx->getOption('langsuggest.core_path', null, $this->modx->getOption('core_path') . 'components/langsuggest/');
        $this->langsuggest = $this->modx->getService('langsuggest', 'Langsuggest', $corePath . 'model/langsuggest/', array(
            'core_path' => $corePath
        ));
    }

    abstract public function run();
}
