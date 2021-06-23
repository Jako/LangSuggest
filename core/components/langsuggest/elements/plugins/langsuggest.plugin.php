<?php
/**
 * LangSuggest Plugin
 *
 * @package langsuggest
 * @subpackage plugin
 *
 * @var modX $modx
 * @var array $scriptProperties
 */

$className = 'TreehillStudio\LangSuggest\Plugins\Events\\' . $modx->event->name;

$corePath = $modx->getOption('langsuggest.core_path', null, $modx->getOption('core_path') . 'components/langsuggest/');
/** @var LangSuggest $langsuggest */
$langsuggest = $modx->getService('langsuggest', 'LangSuggest', $corePath . 'model/langsuggest/', array(
    'core_path' => $corePath
));

if ($langsuggest) {
    if (class_exists($className)) {
        $handler = new $className($modx, $scriptProperties);
        if (get_class($handler) == $className) {
            $handler->run();
        } else {
            $modx->log(xPDO::LOG_LEVEL_ERROR, $className. ' could not be initialized!', '', 'LangSuggest Plugin');
        }
    } else {
        $modx->log(xPDO::LOG_LEVEL_ERROR, $className. ' was not found!', '', 'LangSuggest Plugin');
    }
}

return;