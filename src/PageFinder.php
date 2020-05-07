<?php

/*
 * changelanguage Extension for Contao Open Source CMS
 *
 * @copyright  Copyright (c) 2008-2019, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 * @link       http://github.com/terminal42/contao-changelanguage
 */

namespace Terminal42\ChangeLanguage;

use Contao\Date;
use Contao\Model\Collection;
use Contao\PageModel;

class PageFinder
{
    /**
     * @param PageModel $page
     * @param bool      $skipCurrent
     * @param bool      $publishedOnly
     *
     * @return \Contao\PageModel[]
     */
    public function findRootPagesForPage(PageModel $page, $skipCurrent = false, $publishedOnly = true)
    {
        $page->loadDetails();
        $t = $page::getTable();

        $columns = [
            "$t.type='root'",
            "(
                $t.dns=? 
                OR $t.dns IN (
                    SELECT dns 
                    FROM tl_page 
                    WHERE type='root' AND fallback='1' AND id = (
                        SELECT languageRoot FROM tl_page WHERE type='root' AND fallback='1' AND dns=? LIMIT 1
                    )
                ) 
                OR $t.dns IN (
                    SELECT dns 
                    FROM tl_page 
                    WHERE type='root' AND fallback='1' AND languageRoot = (
                        SELECT id FROM tl_page WHERE type='root' AND fallback='1' AND dns=? LIMIT 1
                    )
                ) 
                OR $t.dns IN (
                    SELECT dns 
                    FROM tl_page 
                    WHERE type='root' AND fallback='1' AND languageRoot != 0 AND languageRoot = (
                        SELECT languageRoot FROM tl_page WHERE type='root' AND fallback='1' AND dns=? LIMIT 1
                    )
                )
            )",
        ];

        $values = [$page->domain, $page->domain, $page->domain, $page->domain];

        if ($skipCurrent) {
            $columns[] = "$t.id!=?";
            $values[] = $page->rootId;
        }

        if ($publishedOnly) {
            $this->addPublishingConditions($columns, $t);
        }

        return $this->findPages($columns, $values, ['order' => 'sorting']);
    }

    /**
     * Finds the root page of fallback language for the given page.
     *
     * @param PageModel $page
     *
     * @return \PageModel|null
     */
    public function findMasterRootForPage(PageModel $page)
    {
        $page->loadDetails();
        $t = $page::getTable();

        $columns = [
            "$t.type='root'",
            "$t.fallback='1'",
            "$t.languageRoot=0",
            "(
                $t.dns=? 
                OR $t.dns IN (SELECT dns FROM tl_page WHERE type='root' AND fallback='1' AND id IN (SELECT languageRoot FROM tl_page WHERE type='root' AND fallback='1' AND dns=?)) 
                OR $t.dns IN (SELECT dns FROM tl_page WHERE type='root' AND fallback='1' AND languageRoot IN (SELECT id FROM tl_page WHERE type='root' AND fallback='1' AND dns=?))
            )",
        ];

        return PageModel::findOneBy(
            $columns,
            [$page->domain, $page->domain, $page->domain]
        );
    }

    /**
     * @param PageModel $page
     * @param bool      $skipCurrent
     *
     * @return \Contao\PageModel[]
     */
    public function findAssociatedForPage(PageModel $page, $skipCurrent = false, array $rootPages = null)
    {
        if ('root' === $page->type) {
            return $this->findRootPagesForPage($page, $skipCurrent);
        }

        if (null === $rootPages) {
            $rootPages = $this->findRootPagesForPage($page, $skipCurrent);
        }

        $page->loadDetails();
        $t = $page::getTable();

        if ($page->rootIsFallback && null !== ($root = PageModel::findByPk($page->rootId)) && !$root->languageRoot) {
            $values = [$page->id, $page->id];
        } elseif (!$page->languageMain) {
            return $skipCurrent ? [] : [$page];
        } else {
            $values = [$page->languageMain, $page->languageMain];
        }

        $columns = ["($t.id=? OR $t.languageMain=?)"];

        if ($skipCurrent) {
            $columns[] = "$t.id!=?";
            $values[] = $page->id;
        }

        $this->addPublishingConditions($columns, $t);

        return array_filter(
            $this->findPages($columns, $values),
            function (PageModel $page) use ($rootPages) {
                $page->loadDetails();

                return array_key_exists($page->rootId, $rootPages);
            }
        );
    }

    /**
     * @param PageModel $page
     * @param string    $language
     *
     * @return PageModel
     */
    public function findAssociatedForLanguage(PageModel $page, $language)
    {
        $language = Language::toLocaleID($language);
        $associated = $this->findAssociatedForPage($page);

        foreach ($associated as $model) {
            $model->loadDetails();

            if (Language::toLocaleID($model->language) === $language) {
                return $model;
            }
        }

        // No page found, find for parent
        return $this->findAssociatedParentForLanguage($page, $language);
    }

    /**
     * @param PageModel $page
     *
     * @return PageModel|null
     */
    public function findAssociatedInMaster(PageModel $page)
    {
        $page->loadDetails();
        $masterRoot = $this->findMasterRootForPage($page);

        if ($masterRoot->id === $page->rootId) {
            return null;
        }

        $associated = $this->findAssociatedForPage($page);

        foreach ($associated as $model) {
            $model->loadDetails();

            if ($model->rootId === $masterRoot->id) {
                return $model;
            }
        }

        return null;
    }

    /**
     * @param PageModel $page
     * @param string    $language
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     *
     * @return PageModel
     */
    public function findAssociatedParentForLanguage(PageModel $page, $language)
    {
        // Stop loop if we're at the top
        if (0 === $page->pid || 'root' === $page->type) {
            $rootPages = $this->findRootPagesForPage($page);

            foreach ($rootPages as $model) {
                if (Language::toLocaleID($model->language) === $language) {
                    return $model;
                }
            }

            throw new \InvalidArgumentException(
                sprintf('There\'s no language "%s" related to root page ID "%s"', $language, $page->id)
            );
        }

        $parent = PageModel::findPublishedById($page->pid);

        if (!$parent instanceof PageModel) {
            throw new \RuntimeException(sprintf('Parent page for page ID "%s" not found', $page->id));
        }

        return $this->findAssociatedForLanguage($parent, $language);
    }

    /**
     * @param array  $columns
     * @param string $table
     */
    private function addPublishingConditions(array &$columns, $table)
    {
        if ('BE' !== TL_MODE && true !== BE_USER_LOGGED_IN) {
            $start = Date::floorToMinute();
            $stop = $start + 60;

            $columns[] = "$table.published='1'";
            $columns[] = "($table.start='' OR $table.start<$start)";
            $columns[] = "($table.stop='' OR $table.stop>$stop)";
        }
    }

    /**
     * @param array $columns
     * @param array $values
     * @param array $options
     *
     * @return \Contao\PageModel[]
     */
    private function findPages(array $columns, array $values, array $options = [])
    {
        /** @var Collection $collection */
        $collection = PageModel::findBy($columns, $values, $options);

        if (!$collection instanceof Collection) {
            return [];
        }

        $models = [];

        foreach ($collection as $model) {
            $models[$model->id] = $model;
        }

        return $models;
    }
}
