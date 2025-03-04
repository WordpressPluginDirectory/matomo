<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Plugins\CoreAdminHome;

use Piwik\Common;
use Piwik\Nonce;
use Piwik\Piwik;
use Piwik\Plugins\LanguagesManager\API as APILanguagesManager;
use Piwik\Plugins\LanguagesManager\LanguagesManager;
use Piwik\Plugins\PrivacyManager\DoNotTrackHeaderChecker;
use Piwik\Request;
use Piwik\Tracker\IgnoreCookie;
use Piwik\Url;
use Piwik\UrlHelper;
use Piwik\View;
/*
 * There are three different opt-out choices:
 *
 * iFrame        : an <iframe> tag is added to the webpage with a Matomo URL as the source, this URL serves the opt-out
 *                 content and sets the opt-out cookie for the Matomo URL domain. Translation and styling is done
 *                 server side, a third party cookie is set. Not well supported with modern browser third party cookie
 *                 restrictions, no longer offered as an option in the UI but the content URL is supported for existing
 *                 webpages still using the iFrame code.
 *
 * JavaScript    : an empty <div> tag is added to the webpage along with a <script> reference which loads dynamic
 *                 JavaScript from a Matomo URL, the JavaScript then populates the empty div with the opt-out content.
 *                 Translation and styling is read server side and built into the JavaScript, the Matomo tracker is
 *                 used to set a first party cookie if it is loaded, otherwise the first party cookie is set directly.
 *                 Can be broken by ad blockers that prevent third party scripts.
 *
 * Self-Contained: an empty <div> tag is added to the webpage along with an inline <script> tag containing the entire
 *                 opt-out JavaScript. Translation and styling are built into the script when it is generated by the UI
 *                 and any changes require modifying the code on each webpage. A first party cookie is set. It is
 *                 unlikely that the script will be blocked as it is fully self-contained and part of the webpage.
 *
 */
class OptOutManager
{
    /** @var DoNotTrackHeaderChecker */
    private $doNotTrackHeaderChecker;
    /** @var array */
    private $javascripts;
    /** @var array */
    private $stylesheets;
    /** @var string */
    private $title;
    /** @var View|null */
    private $view;
    /** @var array */
    private $queryParameters = array();
    /**
     * @param DoNotTrackHeaderChecker|null $doNotTrackHeaderChecker
     */
    public function __construct(?DoNotTrackHeaderChecker $doNotTrackHeaderChecker = null)
    {
        $this->doNotTrackHeaderChecker = $doNotTrackHeaderChecker ?: new DoNotTrackHeaderChecker();
        $this->javascripts = array('inline' => array(), 'external' => array());
        $this->stylesheets = array('inline' => array(), 'external' => array());
    }
    /**
     * Add a javascript file|code into the OptOut View
     * Note: This method will not escape the inline javascript code!
     *
     * @param string $javascript
     * @param bool $inline
     */
    public function addJavaScript($javascript, $inline = \true)
    {
        $type = $inline ? 'inline' : 'external';
        $this->javascripts[$type][] = $javascript;
    }
    /**
     * @return array
     */
    public function getJavaScripts()
    {
        return $this->javascripts;
    }
    /**
     * Add a stylesheet file|code into the OptOut View
     * Note: This method will not escape the inline css code!
     *
     * @param string $stylesheet Escaped stylesheet
     * @param bool $inline
     */
    public function addStylesheet($stylesheet, $inline = \true)
    {
        $type = $inline ? 'inline' : 'external';
        $this->stylesheets[$type][] = $stylesheet;
    }
    /**
     * @return array
     */
    public function getStylesheets()
    {
        return $this->stylesheets;
    }
    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }
    /**
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }
    /**
     * @param string $key
     * @param string $value
     * @param bool $override
     *
     * @return bool
     */
    public function addQueryParameter($key, $value, $override = \true)
    {
        if (!isset($this->queryParameters[$key]) || \true === $override) {
            $this->queryParameters[$key] = $value;
            return \true;
        }
        return \false;
    }
    /**
     * @param array $items
     * @param bool|true $override
     */
    public function addQueryParameters(array $items, $override = \true)
    {
        foreach ($items as $key => $value) {
            $this->addQueryParameter($key, $value, $override);
        }
    }
    /**
     * @param $key
     */
    public function removeQueryParameter($key)
    {
        unset($this->queryParameters[$key]);
    }
    /**
     * @return array
     */
    public function getQueryParameters()
    {
        return $this->queryParameters;
    }
    /**
     * Return the HTML code to be added to pages for the JavaScript opt-out
     *
     * @param string $matomoUrl
     * @param string $language
     * @param string $backgroundColor
     * @param string $fontColor
     * @param string $fontSize
     * @param string $fontFamily
     * @param bool   $applyStyling
     * @param bool   $showIntro
     *
     * @return string
     */
    public function getOptOutJSEmbedCode(string $matomoUrl, string $language, string $backgroundColor, string $fontColor, string $fontSize, string $fontFamily, bool $applyStyling, bool $showIntro) : string
    {
        $parsedUrl = parse_url($matomoUrl);
        if (!empty($matomoUrl) && \false === $parsedUrl || !empty($parsedUrl['scheme']) && !in_array(strtolower($parsedUrl['scheme']), ['http', 'https']) || (empty($parsedUrl['host']) || !Url::isValidHost($parsedUrl['host']))) {
            throw new \Piwik\Exception\Exception('The provided URL is invalid.');
        }
        // We put together the url based on the parsed parameters manually to ensure it might not include unexpected values
        // for protocol less urls starting with //, we need to prepend the double slash again
        $matomoUrl = (strpos($matomoUrl, '//') === 0 ? '//' : '') . UrlHelper::getParseUrlReverse($parsedUrl);
        return '<div id="matomo-opt-out"></div>
<script src="' . rtrim($matomoUrl, '/') . '/index.php?module=CoreAdminHome&action=optOutJS&divId=matomo-opt-out&language=' . $language . ($applyStyling ? '&backgroundColor=' . $backgroundColor . '&fontColor=' . $fontColor . '&fontSize=' . $fontSize . '&fontFamily=' . $fontFamily : '') . '&showIntro=' . ($showIntro ? '1' : '0') . '"></script>';
    }
    /**
     * Return the HTML code to be added to pages for the self-contained opt-out
     *
     * @param string $backgroundColor
     * @param string $fontColor
     * @param string $fontSize
     * @param string $fontFamily
     * @param bool   $applyStyling
     * @param bool   $showIntro
     *
     * @return string
     */
    public function getOptOutSelfContainedEmbedCode(string $backgroundColor, string $fontColor, string $fontSize, string $fontFamily, bool $applyStyling, bool $showIntro) : string
    {
        $cookiePath = Common::getRequestVar('cookiePath', '', 'string');
        $cookieDomain = Common::getRequestVar('cookieDomain', '', 'string');
        $settings = ['showIntro' => $showIntro, 'divId' => 'matomo-opt-out', 'useSecureCookies' => \true, 'cookiePath' => $cookiePath !== '' ? $cookiePath : null, 'cookieDomain' => $cookieDomain !== '' ? $cookieDomain : null, 'cookieSameSite' => Common::getRequestVar('cookieSameSite', 'Lax', 'string')];
        // Self contained code translations are static and always use the language of the user who generated the embed code
        $settings = array_merge($settings, $this->getTranslations());
        $settingsString = 'var settings = ' . json_encode($settings) . ';';
        $styleSheet = $this->optOutStyling($fontSize, $fontColor, $fontFamily, $backgroundColor, \true);
        $code = <<<HTML
<div id="matomo-opt-out" style=""></div>
<script>    
    var settings = {};         
    document.addEventListener('DOMContentLoaded', function() {                             
        window.MatomoConsent.init(settings.useSecureCookies, settings.cookiePath, settings.cookieDomain, settings.cookieSameSite);                
        showContent(window.MatomoConsent.hasConsent());        
    });    
    
    window.MatomoConsent = {  };
</script>
HTML;
        return str_replace('window.MatomoConsent = {  };', $this->getOptOutCommonJS(), str_replace('style=""', $applyStyling ? 'style="' . $styleSheet . '"' : '', str_replace("var settings = {};", $settingsString, $code)));
    }
    /**
     * Generate and return JavaScript to show the opt-out option
     *
     * All optOutJS URL params:
     *     backgroundColor
     *     fontColor
     *     fontSize
     *     fontFamily
     *     language (default "auto")          Language code for the translations or "auto" to use the browser language
     *     showIntro (default 1)              Should the opt-out intro text be shown?
     *     divId (default "matomo-opt-out")   The id of the div which will contain the opt-out form
     *     useCookiesIfNoTracker (default 1)  Should consent cookies be read/written directly if the tracker can't be found?
     *     useCookiesTimeout (default 10)     How long to wait for the tracker to be detected?
     *     useSecureCookies (default 1)       Set secure cookies?
     *     cookiePath (default blank)         Use this path for consent cookies
     *     cookieDomain (default blank)       Use this domain for consent cookies
     *
     * @return string
     */
    public function getOptOutJS() : string
    {
        $language = Common::getRequestVar('language', 'auto', 'string');
        $showIntro = Common::getRequestVar('showIntro', 1, 'int');
        $divId = Common::getRequestVar('divId', 'matomo-opt-out', 'string');
        $useCookiesIfNoTracker = Common::getRequestVar('useCookiesIfNoTracker', 1, 'int');
        $useCookiesTimeout = Common::getRequestVar('useCookiesTimeout', 10, 'int');
        $useSecureCookies = Common::getRequestVar('useSecureCookies', 1, 'int');
        $cookiePath = Common::getRequestVar('cookiePath', '', 'string');
        $cookieDomain = Common::getRequestVar('cookieDomain', '', 'string');
        // If the language parameter is 'auto' then use the browser language
        if ($language === 'auto') {
            $language = Common::extractLanguageAndRegionCodeFromBrowserLanguage(Common::getBrowserLanguage(), APILanguagesManager::getInstance()->getAvailableLanguages());
        }
        $settings = ['showIntro' => $showIntro, 'divId' => $divId, 'useSecureCookies' => $useSecureCookies, 'cookiePath' => $cookiePath !== '' ? $cookiePath : null, 'cookieDomain' => $cookieDomain !== '' ? $cookieDomain : null, 'useCookiesIfNoTracker' => $useCookiesIfNoTracker, 'useCookiesTimeout' => $useCookiesTimeout];
        // Self contained code translations are static and always use the language of the user who generated the embed code
        $translations = $this->getTranslations($language);
        $translations['OptOutErrorNoTracker'] = Piwik::translate('CoreAdminHome_OptOutErrorNoTracker', [], $language);
        $settings = array_merge($settings, $translations);
        $settingsString = 'var settings = ' . json_encode($settings) . ';';
        $styleSheet = $this->optOutStyling(null, null, null, null, \true);
        /** @lang JavaScript */
        $code = <<<JS

        var settings = {};          
        var checkForTrackerTried = 0;
        var checkForTrackerTries = (settings.useCookiesTimeout * 4);
        var checkForTrackerInterval = 250;
        var optOutDiv = null;
        
        function optOutInit() {
            optOutDiv = document.getElementById(settings.divId);
            if (optOutDiv) {
                optOutDiv.style.cssText += 'stylecss'; // Appending css to avoid overwritting existing inline div styles
            } else {
                showContent(false, null, true); // will show unable to find opt-out div error
                return;                
            }
            checkForMatomoTracker();
        }
        
        function checkForMatomoTracker() {
            if (typeof _paq !== 'undefined') {
                showOptOutTracker();
                return;
            }
            
            if (checkForTrackerTried < checkForTrackerTries) {
                setTimeout(checkForMatomoTracker, checkForTrackerInterval);
                checkForTrackerTried++;
                return;
            }
            
            if (settings.useCookiesIfNoTracker) {
                showOptOutDirect();
                return;
            }
            
            console.log('Matomo OptOutJS: failed to find Matomo tracker after '+(checkForTrackerTries*checkForTrackerInterval / 1000)+' seconds');
        }
        
        function showOptOutTracker() {             
            _paq.push([function () {
                if (settings.cookieDomain) {
                    _paq.push(['setCookieDomain', settings.cookieDomain]);
                }
                if (settings.cookiePath) {
                    _paq.push(['setCookiePath', settings.cookiePath]);
                }
                if (this.isUserOptedOut()) {
                    showContent(false, null, true);
                } else {
                    showContent(true, null, true);
                }
            }]);
        }
        
        function showOptOutDirect() {
            window.MatomoConsent.init(settings.useSecureCookies, settings.cookiePath, settings.cookieDomain, settings.cookieSameSite);                
            showContent(window.MatomoConsent.hasConsent());
        }
        
        document.addEventListener('DOMContentLoaded', optOutInit());
        
        window.MatomoConsent = {  };        
JS;
        return str_replace('window.MatomoConsent = {  };', $this->getOptOutCommonJS(), str_replace('stylecss', $styleSheet, str_replace("var settings = {};", $settingsString, $code)));
    }
    /**
     * Return the shared opt-out JavaScript (used by self-contained and tracker versions)
     *
     * @return string
     */
    private function getOptOutCommonJS() : string
    {
        /** @lang JavaScript */
        return <<<JS

        function showContent(consent, errorMessage = null, useTracker = false) {
    
            var errorBlock = '<p style="color: red; font-weight: bold;">';
    
            var div = document.getElementById(settings.divId);
            if (!div) {
                var warningDiv = document.createElement("div");
                var msg = 'Unable to find opt-out content div: "'+settings.divId+'"';
                warningDiv.id = settings.divId+'-warning';
                warningDiv.innerHTML = errorBlock+msg+'</p>';
                document.body.insertBefore(warningDiv, document.body.firstChild);
                console.log(msg);
                return;
            }
            
            if (!navigator || !navigator.cookieEnabled) {
                div.innerHTML = errorBlock+settings.OptOutErrorNoCookies+'</p>';
                return;
            }

            if (errorMessage !== null) {
                div.innerHTML = errorBlock+errorMessage+'</p>';
                return;
            }

            var content = '';        

            if (location.protocol !== 'https:') {
                content += errorBlock + settings.OptOutErrorNotHttps + '</p>';
            }

            if (consent) {
                if (settings.showIntro) {
                    content += '<p>'+settings.YouMayOptOut2+' '+settings.YouMayOptOut3+'</p>';                       
                }
                if (useTracker) {
                    content += '<input onclick="_paq.push([\\'optUserOut\\']);showContent(false, null, true);" id="trackVisits" type="checkbox" checked="checked" />';
                } else {
                    content += '<input onclick="window.MatomoConsent.consentRevoked();showContent(false);" id="trackVisits" type="checkbox" checked="checked" />';
                }
                content += '<label for="trackVisits"><strong><span>'+settings.YouAreNotOptedOut+' '+settings.UncheckToOptOut+'</span></strong></label>';                               
            } else {
                if (settings.showIntro) {
                    content += '<p>'+settings.OptOutComplete+' '+settings.OptOutCompleteBis+'</p>';
                }
                if (useTracker) {
                    content += '<input onclick="_paq.push([\\'forgetUserOptOut\\']);showContent(true, null, true);" id="trackVisits" type="checkbox" />';
                } else {
                    content += '<input onclick="window.MatomoConsent.consentGiven();showContent(true);" id="trackVisits" type="checkbox" />';
                }
                content += '<label for="trackVisits"><strong><span>'+settings.YouAreOptedOut+' '+settings.CheckToOptIn+'</span></strong></label>';
            }                   
            div.innerHTML = content;      
        };   

        window.MatomoConsent = {                         
            cookiesDisabled: (!navigator || !navigator.cookieEnabled),        
            CONSENT_COOKIE_NAME: 'mtm_consent', CONSENT_REMOVED_COOKIE_NAME: 'mtm_consent_removed', 
            cookieIsSecure: false, useSecureCookies: true, cookiePath: '', cookieDomain: '', cookieSameSite: 'Lax',     
            init: function(useSecureCookies, cookiePath, cookieDomain, cookieSameSite) {
                this.useSecureCookies = useSecureCookies; this.cookiePath = cookiePath;
                this.cookieDomain = cookieDomain; this.cookieSameSite = cookieSameSite;
                if(useSecureCookies && location.protocol !== 'https:') {
                    console.log('Error with setting useSecureCookies: You cannot use this option on http.');             
                } else {
                    this.cookieIsSecure = useSecureCookies;
                }
            },               
            hasConsent: function() {
                var consentCookie = this.getCookie(this.CONSENT_COOKIE_NAME);
                var removedCookie = this.getCookie(this.CONSENT_REMOVED_COOKIE_NAME);
                if (!consentCookie && !removedCookie) {
                    return true; // No cookies set, so opted in
                }
                if (removedCookie && consentCookie) {                
                    this.setCookie(this.CONSENT_COOKIE_NAME, '', -129600000);              
                    return false;
                }                
                return (consentCookie || consentCookie !== 0);            
            },        
            consentGiven: function() {                                                        
                this.setCookie(this.CONSENT_REMOVED_COOKIE_NAME, '', -129600000);
                this.setCookie(this.CONSENT_COOKIE_NAME, new Date().getTime(), 946080000000);
            },      
            consentRevoked: function() {    
                this.setCookie(this.CONSENT_COOKIE_NAME, '', -129600000);
                this.setCookie(this.CONSENT_REMOVED_COOKIE_NAME, new Date().getTime(), 946080000000);                
            },                   
            getCookie: function(cookieName) {            
                var cookiePattern = new RegExp('(^|;)[ ]*' + cookieName + '=([^;]*)'), cookieMatch = cookiePattern.exec(document.cookie);
                return cookieMatch ? window.decodeURIComponent(cookieMatch[2]) : 0;
            },        
            setCookie: function(cookieName, value, msToExpire) {                       
                var expiryDate = new Date();
                expiryDate.setTime((new Date().getTime()) + msToExpire);            
                document.cookie = cookieName + '=' + window.encodeURIComponent(value) +
                    (msToExpire ? ';expires=' + expiryDate.toGMTString() : '') +
                    ';path=' + (this.cookiePath || '/') +
                    (this.cookieDomain ? ';domain=' + this.cookieDomain : '') +
                    (this.cookieIsSecure ? ';secure' : '') +
                    ';SameSite=' + this.cookieSameSite;               
                if ((!msToExpire || msToExpire >= 0) && this.getCookie(cookieName) !== String(value)) {
                    console.log('There was an error setting cookie `' + cookieName + '`. Please check domain and path.');                
                }
            }
        };           
JS;
    }
    /**
     * Get translations used by the opt-out popup
     *
     * @param string|null $language
     *
     * @return array
     */
    private function getTranslations(?string $language = null) : array
    {
        return ['OptOutComplete' => Piwik::translate('CoreAdminHome_OptOutComplete', [], $language), 'OptOutCompleteBis' => Piwik::translate('CoreAdminHome_OptOutCompleteBis', [], $language), 'YouMayOptOut2' => Piwik::translate('CoreAdminHome_YouMayOptOut2', [], $language), 'YouMayOptOut3' => Piwik::translate('CoreAdminHome_YouMayOptOut3', [], $language), 'OptOutErrorNoCookies' => Piwik::translate('CoreAdminHome_OptOutErrorNoCookies', [], $language), 'OptOutErrorNotHttps' => Piwik::translate('CoreAdminHome_OptOutErrorNotHttps', [], $language), 'YouAreNotOptedOut' => Piwik::translate('CoreAdminHome_YouAreNotOptedOut', [], $language), 'UncheckToOptOut' => Piwik::translate('CoreAdminHome_UncheckToOptOut', [], $language), 'YouAreOptedOut' => Piwik::translate('CoreAdminHome_YouAreOptedOut', [], $language), 'CheckToOptIn' => Piwik::translate('CoreAdminHome_CheckToOptIn', [], $language)];
    }
    /**
     * Return the content of the iFrame opt out
     *
     * @return View
     * @throws \Exception
     */
    public function getOptOutViewIFrame()
    {
        if ($this->view) {
            return $this->view;
        }
        $trackVisits = !IgnoreCookie::isIgnoreCookieFound();
        $dntFound = $this->getDoNotTrackHeaderChecker()->isDoNotTrackFound();
        $setCookieInNewWindow = Common::getRequestVar('setCookieInNewWindow', \false, 'int');
        if ($setCookieInNewWindow) {
            $nonce = Common::getRequestVar('nonce', \false);
            if ($nonce !== \false && !Nonce::verifyNonce('Piwik_OptOut', $nonce)) {
                Nonce::discardNonce('Piwik_OptOut');
                $nonce = '';
            }
            $reloadUrl = Url::getCurrentQueryStringWithParametersModified(array('showConfirmOnly' => 1, 'setCookieInNewWindow' => 0, 'nonce' => $nonce ?: ''));
        } else {
            $reloadUrl = \false;
            $requestNonce = Common::getRequestVar('nonce', \false);
            if ($requestNonce !== \false && Nonce::verifyNonce('Piwik_OptOut', $requestNonce)) {
                Nonce::discardNonce('Piwik_OptOut');
                IgnoreCookie::setIgnoreCookie();
                $trackVisits = !$trackVisits;
            }
        }
        $language = Common::getRequestVar('language', '', 'string');
        $lang = APILanguagesManager::getInstance()->isLanguageAvailable($language) ? $language : LanguagesManager::getLanguageCodeForCurrentUser();
        $nonce = Nonce::getNonce('Piwik_OptOut', 3600);
        $this->addQueryParameters(array('module' => 'CoreAdminHome', 'action' => 'optOut', 'language' => $lang, 'setCookieInNewWindow' => 1, 'nonce' => $nonce), \false);
        if (Common::getRequestVar('applyStyling', 1, 'int')) {
            $this->addStylesheet($this->optOutStyling());
        }
        $this->view = new View("@CoreAdminHome/optOut");
        $this->addJavaScript('plugins/CoreAdminHome/javascripts/optOut.js', \false);
        $this->view->setXFrameOptions('allow');
        $this->view->dntFound = $dntFound;
        $this->view->trackVisits = $trackVisits;
        $this->view->nonce = $nonce;
        $this->view->language = $lang;
        $this->view->showIntro = Common::getRequestVar('showIntro', 1, 'int');
        $this->view->showConfirmOnly = Common::getRequestVar('showConfirmOnly', \false, 'int');
        $this->view->reloadUrl = $reloadUrl;
        $this->view->javascripts = $this->getJavaScripts();
        $this->view->stylesheets = $this->getStylesheets();
        $this->view->title = $this->getTitle();
        $this->view->queryParameters = $this->getQueryParameters();
        return $this->view;
    }
    /**
     * Provide a CSS style sheet based on the chosen opt out style options
     *
     * @param string|null $fontSize
     * @param string|null $fontColor
     * @param string|null $fontFamily
     * @param string|null $backgroundColor
     * @param bool        $noBody
     *
     * @return string
     * @throws \Exception
     */
    private function optOutStyling(?string $fontSize = null, ?string $fontColor = null, ?string $fontFamily = null, ?string $backgroundColor = null, bool $noBody = \false) : string
    {
        $cssfontsize = $fontSize ?: Request::fromRequest()->getStringParameter('fontSize', '');
        $cssfontcolour = $fontColor ?: Request::fromRequest()->getStringParameter('fontColor', '');
        $cssfontfamily = $fontFamily ?: Request::fromRequest()->getStringParameter('fontFamily', '');
        $cssbackgroundcolor = $backgroundColor ?: Request::fromRequest()->getStringParameter('backgroundColor', '');
        if (!$noBody) {
            $cssbody = 'body { ';
        } else {
            $cssbody = '';
        }
        $hexstrings = array('fontColor' => $cssfontcolour, 'backgroundColor' => $cssbackgroundcolor);
        foreach ($hexstrings as $key => $testcase) {
            if ($testcase && !(ctype_xdigit($testcase) && in_array(strlen($testcase), array(3, 6), \true))) {
                throw new \Exception("The URL parameter {$key} value of '{$testcase}' is not valid. Expected value is for example 'ffffff' or 'fff'.\n");
            }
        }
        /** @noinspection RegExpRedundantEscape */
        if ($cssfontsize && preg_match("/^[0-9]+[\\.]?[0-9]*(px|pt|em|rem|%)\$/", $cssfontsize)) {
            $cssbody .= 'font-size: ' . $cssfontsize . '; ';
        } elseif ($cssfontsize) {
            throw new \Exception("The URL parameter fontSize value of '{$cssfontsize}' is not valid. Expected value is for example '15pt', '1.2em' or '13px'.\n");
        }
        /** @noinspection RegExpRedundantEscape */
        if ($cssfontfamily && preg_match('/^[a-zA-Z0-9-\\ ,\'"]+$/', $cssfontfamily)) {
            $cssbody .= 'font-family: ' . $cssfontfamily . '; ';
        } elseif ($cssfontfamily) {
            throw new \Exception("The URL parameter fontFamily value of '{$cssfontfamily}' is not valid. Expected value is for example 'sans-serif' or 'Monaco, monospace'.\n");
        }
        if ($cssfontcolour) {
            $cssbody .= 'color: #' . $cssfontcolour . '; ';
        }
        if ($cssbackgroundcolor) {
            $cssbody .= 'background-color: #' . $cssbackgroundcolor . '; ';
        }
        if (!$noBody) {
            $cssbody .= '}';
        }
        return $cssbody;
    }
    /**
     * @return DoNotTrackHeaderChecker
     */
    protected function getDoNotTrackHeaderChecker()
    {
        return $this->doNotTrackHeaderChecker;
    }
}
