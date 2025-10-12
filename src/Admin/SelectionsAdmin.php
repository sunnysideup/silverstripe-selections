<?php

namespace Sunnysideup\Selections\Admin;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\LiteralField;
use Sunnysideup\Selections\Model\DisplayItem;
use Sunnysideup\Selections\Model\FilterItem;
use Sunnysideup\Selections\Model\Selection;
use Sunnysideup\Selections\Model\SortItem;

class SelectionsAdmin extends ModelAdmin
{
    private static $url_segment = 'selections';
    private static $menu_title = 'Selections';
    private static $menu_icon_class = 'font-icon-p-search';
    private static $managed_models = [
        Selection::class,
        FilterItem::class,
        SortItem::class,
        DisplayItem::class,
    ];


    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);
        $form->Fields()->unshift(
            LiteralField::create(
                'Instructions',
                '
                <p>
                    Below you can make any record selection you like.
                    You can select your list of records
                    and after that you can choose the filters, sorts and the fields you like to display.
                    These selections will be retained and so you can visit them at any time.
                </p>
                '
            )

        );
        return $form;
    }
}
