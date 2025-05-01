<?php

declare(strict_types=1);

namespace Sunnysideup\Selections\Model;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\ORM\DataObject;
use Sunnysideup\ClassesAndFieldsInfo\Api\ClassAndFieldInfo;

class SortItem extends DataObject
{
    private static $table_name = 'SelectionsSortItem';

    private static $db = [
        'FieldName' => 'Varchar(255)',
        'SortOrder' => 'Int',
        'SortDirection' => 'Enum("ASC,DESC", "ASC")',
    ];

    private static $has_one = [
        'Selection' => Selection::class,
    ];

    private static $indexes = [
        'Title' => true,
        'SortOrder' => true,
    ];

    private static $default_sort = 'SortOrder ASC, ID DESC';

    private static $casting = [
        'Title' => 'Varchar',
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->replaceField(
            'FieldName',
            OptionsetField::create(
                'FieldName',
                'Select Field',
                $this->getFieldsNamesAvailable()
            )
        );
        $fields->replaceField(
            'SortDirection',
            OptionsetField::create(
                'SortDirection',
                'Select Sort Direction',
                [
                    'ASC' => 'Ascending',
                    'DESC' => 'Descending'
                ]
            )
        );
        $fields->remove('SortOrder');
        return $fields;
    }

    protected function getFieldsNamesAvailable(): array
    {
        $selection = $this->Selection();
        $list = Injector::inst()->get(ClassAndFieldInfo::class)
            ->getListOfFieldNames(
                $selection,
                $selection->ModelClassName,
                ['db']
            );
        $exclude = $this->Selection()->SortSelection()->exclude(['ID' => $this->ID])->map('FieldName', 'FieldName')->toArray();
        return array_diff($list, $exclude);
    }
}
