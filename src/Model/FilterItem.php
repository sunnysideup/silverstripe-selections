<?php

declare(strict_types=1);

namespace Sunnysideup\Selections\Model;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBField;
use Sunnysideup\ClassesAndFieldsInfo\Api\ClassAndFieldInfo;

class FilterItem extends DataObject
{
    private static $table_name = 'SelectionsFilterItem';

    private static $db = [
        'FieldName' => 'Varchar(255)',
        'FilterType' => 'Varchar(255)',
        'FilterValue' => 'Varchar(255)',
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
            'FilterType',
            OptionsetField::create(
                'FilterType',
                'Select Filter Type',
                $this->getFilterTypeAvailable()
            )->setEmptyString('Exact Match')
        );
        $obj = $this->getFieldTypeObject();
        $obj->setName('FilterValue');
        $newField = $obj->scaffoldFormField(
            'Value for filtering',
        );
        $fields->replaceField(
            'FilterValue',
            $newField
        );
        return $fields;
    }

    protected function getFieldsNamesAvailable(): array
    {
        $selection = $this->Selection();
        $list = Injector::inst()->get(ClassAndFieldInfo::class)
            ->getListOfFieldNames(
                $selection,
                $selection->ModelClassName,
                ['db', 'belongs', 'has_one', 'has_many', 'many_many', 'belongs_many_many']
            );
        // exclude existing ones?
        return $list;
    }

    protected function getFilterTypeAvailable(): array
    {
        return [
            'PartialMatch' => 'Contains',
            'StartsWith' => 'Starts With',
            'ExactMatch' => 'Equals',
            'GreaterThan' => 'Greater Than',
            'GreaterThanOrEqual' => 'Greater Than or Equal',
            'LessThan' => 'Less Than',
            'LessThanOrEqual' => 'Less Than or Equal',
            'Not' => 'Exclude',
        ];
    }

    protected function getFieldTypeObject(): DBField
    {
        return $this->Selection()->getFieldTypeObject($this->FieldName);
    }
}
