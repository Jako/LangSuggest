<?php
/**
 * LangSuggest Plugin
 *
 * @package langsuggest
 * @subpackage pluginfile
 *
 * @var modX $modx
 * @var array $scriptProperties
 */

$className = 'LangSuggest' . $modx->event->name;

$corePath = $modx->getOption('langsuggest.core_path', null, $modx->getOption('core_path') . 'components/langsuggest/');
/** @var LangSuggest $langsuggest */
$langsuggest = $modx->getService('langsuggest', 'LangSuggest', $corePath . 'model/langsuggest/', array(
    'core_path' => $corePath
));

$modx->loadClass('LangsuggestPlugin', $langsuggest->getOption('modelPath') . 'langsuggest/events/', true, true);
$modx->loadClass($className, $langsuggest->getOption('modelPath') . 'langsuggest/events/', true, true);
if (class_exists($className)) {
    /** @var LangsuggestPlugin $handler */
    $handler = new $className($modx, $scriptProperties);
    $handler->run();
}

return;
