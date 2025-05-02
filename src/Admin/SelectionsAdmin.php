<?php

namespace Sunnysideup\Selections\Admin;

use SilverStripe\Admin\ModelAdmin;
use Sunnysideup\Selections\Model\DisplayItem;
use Sunnysideup\Selections\Model\FilterItem;
use Sunnysideup\Selections\Model\Selection;
use Sunnysideup\Selections\Model\SortItem;

class SelectionsAdmin extends ModelAdmin
{
    private static $url_segment = 'selections';
    private static $menu_title = 'Selections';
    private static $menu_icon_class = 'font-icon-block-content';
    private static $managed_models = [
        Selection::class,
        FilterItem::class,
        SortItem::class,
        DisplayItem::class,
    ];
}
