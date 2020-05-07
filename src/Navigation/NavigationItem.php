<?php

/*
 * changelanguage Extension for Contao Open Source CMS
 *
 * @copyright  Copyright (c) 2008-2019, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 * @link       http://github.com/terminal42/contao-changelanguage
 */

namespace Terminal42\ChangeLanguage\Navigation;

use Contao\PageModel;
use Terminal42\ChangeLanguage\Language;

class NavigationItem
{
    /**
     * @var PageModel
     */
    private $rootPage;

    /**
     * @var PageModel|null
     */
    private $targetPage;

    /**
     * @var string
     */
    private $linkLabel;

    /**
     * @var bool
     */
    private $newWindow;

    /**
     * @var bool
     */
    private $isDirectFallback = false;

    /**
     * @var bool
     */
    private $isCurrentPage = false;

    /**
     * Constructor.
     *
     * @param PageModel   $rootPage
     * @param string|null $label
     */
    public function __construct(PageModel $rootPage, $label = null)
    {
        if ('root' !== $rootPage->type) {
            throw new \RuntimeException(
                sprintf('Page ID "%s" has type "%s" but should be "root"', $rootPage->id, $rootPage->type)
            );
        }

        $this->rootPage = $rootPage->loadDetails();
        $this->linkLabel = $label;

        if (null === $label) {
            $this->linkLabel = strtoupper($this->getLanguageTag());
        }
    }

    /**
     * @return bool
     */
    public function hasTargetPage()
    {
        return $this->targetPage instanceof PageModel;
    }

    /**
     * @return bool
     */
    public function isDirectFallback()
    {
        return $this->isDirectFallback;
    }

    /**
     * @param $isDirectFallback
     */
    public function setIsDirectFallback($isDirectFallback)
    {
        $this->isDirectFallback = (bool) $isDirectFallback;
    }

    /**
     * @return bool
     */
    public function isCurrentPage()
    {
        return $this->isCurrentPage;
    }

    /**
     * @return PageModel
     */
    public function getRootPage()
    {
        return $this->rootPage;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->linkLabel;
    }

    public function setLabel($label)
    {
        $this->linkLabel = (string) $label;
    }

    /**
     * @return PageModel|null
     */
    public function getTargetPage()
    {
        return $this->targetPage;
    }

    /**
     * @param PageModel $targetPage
     * @param bool      $isDirectFallback
     * @param bool      $isCurrentPage
     */
    public function setTargetPage(PageModel $targetPage, $isDirectFallback, $isCurrentPage = false)
    {
        $this->targetPage = $targetPage->loadDetails();
        $this->isDirectFallback = (bool) $isDirectFallback;
        $this->isCurrentPage = (bool) $isCurrentPage;
    }

    /**
     * @param bool $isCurrentPage
     */
    public function setIsCurrentPage($isCurrentPage)
    {
        $this->isCurrentPage = $isCurrentPage;
    }

    /**
     * @return bool
     */
    public function isNewWindow()
    {
        if (null === $this->newWindow) {
            $targetPage = $this->targetPage ?: $this->rootPage;

            return 'redirect' === $targetPage->type && $targetPage->target;
        }

        return $this->newWindow;
    }

    /**
     * @param bool|null $newWindow
     */
    public function setNewWindow($newWindow)
    {
        $this->newWindow = $newWindow;
    }

    /**
     * @return string
     */
    public function getNormalizedLanguage()
    {
        return strtolower($this->getLocaleId());
    }

    /**
     * Returns the language formatted as IETF Language Tag (BCP 47)
     * Example: en, en-US, de-CH.
     *
     * @return string
     *
     * @see http://www.w3.org/International/articles/language-tags/
     */
    public function getLanguageTag()
    {
        return Language::toLanguageTag($this->rootPage->language);
    }

    /**
     * Returns the language formatted as ICU Locale ID
     * Example: en, en_US, de_CH.
     *
     * @return string
     *
     * @see http://userguide.icu-project.org/locale
     */
    public function getLocaleId()
    {
        return Language::toLocaleID($this->rootPage->language);
    }

    /**
     * @param UrlParameterBag $urlParameterBag
     *
     * @return string
     */
    public function getHref(UrlParameterBag $urlParameterBag)
    {
        $targetPage = $this->targetPage ?: $this->rootPage;

        if ('root' === $targetPage->type) {
            $targetPage = PageModel::findFirstPublishedRegularByPid($targetPage->id) ?: $targetPage;
        }

        $href = $targetPage->getAbsoluteUrl($urlParameterBag->generateParameters());

        if (null !== ($queryString = $urlParameterBag->generateQueryString())) {
            $href .= '?'.$queryString;
        }

        return $href;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->targetPage ? $this->targetPage->title : $this->rootPage->title;
    }
}
