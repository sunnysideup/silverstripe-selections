<?php

namespace Sunnysideup\Selections\Admin;

use SilverStripe\Admin\ModelAdmin;
use Sunnysideup\Selections\Model\Selection;

class SelectionsAdmin extends ModelAdmin
{
    private static $url_segment = 'selections';
    private static $menu_title = 'Selections';
    private static $menu_icon_class = 'font-icon-block-content';
    private static $managed_models = [
        Selection::class,
    ];
}
