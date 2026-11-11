<?php
/**
 * @package langsuggest
 * @subpackage plugin
 */

namespace TreehillStudio\LangSuggest\Plugins\Events;

use modX;
use TreehillStudio\LangSuggest\Plugins\Plugin;

class OnWebPagePrerender extends Plugin
{
    /**
     * The cookie name, also used as session key for the display count
     * @var string $cookieName
     */
    private $cookieName;

    public function process()
    {
        $this->cookieName = $this->langsuggest->getOption('cookie_name');

        if ($this->modx->getOption('langsuggest_reset', $_REQUEST, false)) {
            $this->reset();
        }

        if (isset($_COOKIE[$this->cookieName])) {
            $this->langsuggest->logDebug('LangSuggest is hidden by the cookie.');
            return;
        }

        if ($this->hasActiveSession()) {
            $this->showWithDisplayCount();
        } elseif ($this->langsuggest->getOption('no_session')) {
            $this->langsuggest->showLangSuggest();
        }
    }

    /**
     * Show LangSuggest, until the display count of the session is reached
     */
    private function showWithDisplayCount()
    {
        $displayCount = (int)$this->modx->getOption($this->cookieName, $_SESSION, $this->langsuggest->getOption('display_count'));

        // Decrease the display count only if the LangSuggest chunk was really shown
        if ($displayCount > 0 && $this->langsuggest->showLangSuggest()) {
            $displayCount--;
        }
        $_SESSION[$this->cookieName] = $displayCount;
    }

    /**
     * Reset the display count and remove the cookie
     */
    private function reset()
    {
        unset($_SESSION[$this->cookieName], $_COOKIE[$this->cookieName]);
        setcookie($this->cookieName, '', time() - 3600, '/');
    }

    /**
     * @return bool
     */
    private function hasActiveSession(): bool
    {
        return in_array($this->modx->getSessionState(), [
            modX::SESSION_STATE_INITIALIZED,
            modX::SESSION_STATE_EXTERNAL
        ], true);
    }
}
