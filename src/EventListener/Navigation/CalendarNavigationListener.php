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

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\PageModel;
use Haste\Input\Input;

/**
 * Translate URL parameters for calendar events.
 */
class CalendarNavigationListener extends AbstractNavigationListener
{
    /**
     * {@inheritdoc}
     */
    protected function getUrlKey()
    {
        return 'events';
    }

    /**
     * {@inheritdoc}
     */
    protected function findCurrent()
    {
        $alias = (string) Input::getAutoItem($this->getUrlKey(), false, true);

        if ('' === $alias) {
            return null;
        }

        /* @var PageModel $objPage */
        global $objPage;

        if (null === ($calendars = CalendarModel::findBy('jumpTo', $objPage->id))) {
            return null;
        }

        return CalendarEventsModel::findPublishedByParentAndIdOrAlias($alias, $calendars->fetchEach('id'));
    }

    /**
     * {@inheritdoc}
     */
    protected function findPublishedBy(array $columns, array $values = [], array $options = [])
    {
        return CalendarEventsModel::findOneBy(
            $this->addPublishedConditions($columns, CalendarEventsModel::getTable()),
            $values,
            $options
        );
    }
}
