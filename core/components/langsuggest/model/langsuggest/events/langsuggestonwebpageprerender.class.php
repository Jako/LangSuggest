<?php

/**
 * @package langsuggest
 * @subpackage plugin
 */
class LangsuggestOnWebPagePrerender extends LangsuggestPlugin
{
    public function run()
    {
        $cookieName = $this->langsuggest->getOption('cookie_name');
        $activeSession = in_array($this->modx->getSessionState(), array(
            modX::SESSION_STATE_INITIALIZED, modX::SESSION_STATE_EXTERNAL
        ), true);

        if ($this->modx->getOption('langsuggest_reset', $_REQUEST, false)) {
            unset($_SESSION[$cookieName]);
            unset($_COOKIE[$cookieName]);
            setcookie($cookieName, '', time() - 3600);
        }

        if (!isset($_COOKIE[$cookieName])) {
            if ($activeSession) {
                $_SESSION[$cookieName] = $this->modx->getOption($cookieName, $_SESSION, intval($this->langsuggest->getOption('display_count')));
                if ($_SESSION[$cookieName] > 0) {
                    $this->langsuggest->showLangSuggest();
                    $_SESSION[$cookieName]--;
                }
            } elseif ($this->langsuggest->getOption('no_session')) {
                $this->langsuggest->showLangSuggest();
            }
        } elseif ($this->langsuggest->getOption('debug')) {
            $this->modx->log(xPDO::LOG_LEVEL_ERROR, 'LangSuggest is hidden.', '', 'LangSuggest OnWebPagePrerender');
        }
    }
}
