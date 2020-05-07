<?php

/*
 * changelanguage Extension for Contao Open Source CMS
 *
 * @copyright  Copyright (c) 2008-2019, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 * @link       http://github.com/terminal42/contao-changelanguage
 */

namespace Terminal42\ChangeLanguage\EventListener\Navigation;

use Contao\Model;
use Terminal42\ChangeLanguage\Event\ChangelanguageNavigationEvent;

abstract class AbstractNavigationListener
{
    /**
     * Find record based on languageMain field and parent master archive.
     *
     * @param ChangelanguageNavigationEvent $event
     */
    public function onChangelanguageNavigation(ChangelanguageNavigationEvent $event)
    {
        $current = $this->findCurrent();

        if (null === $current) {
            return;
        }

        $navigationItem = $event->getNavigationItem();

        if ($navigationItem->isCurrentPage()) {
            $event->getUrlParameterBag()->setUrlAttribute($this->getUrlKey(), $current->alias);

            return;
        }

        // Remove the news/event/faq alias from the URL if there is no actual reader page assigned
        if (!$navigationItem->isDirectFallback()) {
            $event->getUrlParameterBag()->removeUrlAttribute($this->getUrlKey());
        }

        $t = $current::getTable();
        $parent = $current->getRelated('pid');

        if (0 === (int) $parent->master) {
            $mainId = (int) $current->id;
            $masterId = (int) $current->pid;
        } else {
            $mainId = (int) $current->languageMain;
            $masterId = (int) $parent->master;
        }

        // Abort if current record has no translated version
        if (0 === $mainId || 0 === $masterId) {
            $navigationItem->setIsDirectFallback(false);

            return;
        }

        $translated = $this->findPublishedBy(
            [
                "($t.id=? OR $t.languageMain=?)",
                sprintf('%s.pid=(SELECT id FROM %s WHERE (id=? OR master=?) AND jumpTo=?)', $t, $parent::getTable()),
            ],
            [$mainId, $mainId, $masterId, $masterId, $navigationItem->getTargetPage()->id]
        );

        if (null === $translated) {
            $navigationItem->setIsDirectFallback(false);

            return;
        }

        $event->getUrlParameterBag()->setUrlAttribute($this->getUrlKey(), $translated->alias ?: $translated->id);
    }

    /**
     * Adds publishing conditions to Model query columns if backend user is not logged in.
     *
     * @param array  $columns
     * @param string $table
     * @param bool   $addStartStop
     *
     * @return array
     */
    protected function addPublishedConditions(array $columns, $table, $addStartStop = true)
    {
        if (true !== BE_USER_LOGGED_IN) {
            $columns[] = "$table.published='1'";

            if ($addStartStop) {
                $time = \Date::floorToMinute();
                $columns[] = "($table.start='' OR $table.start<='$time')";
                $columns[] = "($table.stop='' OR $table.stop>'".($time + 60)."')";
            }
        }

        return $columns;
    }

    /**
     * @return string
     */
    abstract protected function getUrlKey();

    /**
     * @return Model|null
     */
    abstract protected function findCurrent();

    /**
     * @param array $columns
     * @param array $values
     * @param array $options
     *
     * @return Model|null
     */
    abstract protected function findPublishedBy(array $columns, array $values = [], array $options = []);
}
