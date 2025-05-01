<?php

declare(strict_types=1);

namespace Sunnysideup\Selections\Model;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
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

    private static $default_sort = 'SortOrder ASC, ID ASC';

    private static $summary_fields = [
        'FieldNameNice' => 'Field Name',
        'SortDirectionNice' => 'Sort Direction',
    ];

    private static $casting = [
        'Title' => 'Varchar',
        'FieldNameNice' => 'Varchar',
        'SortDirectionNice' => 'Varchar',
    ];

    public function getTitle(): string
    {
        return implode(
            ' - ',
            array_filter(
                [
                    $this->getFieldNameNice(),
                    $this->getSortDirectionNice(),
                ]
            )
        );
    }

    public function getFieldNameNice(): string
    {
        $list = $this->getFieldsNamesAvailable();
        return $list[$this->FieldName] ?? $this->FieldName;
    }

    public function getFilterTypeNice(): string
    {
        $list = $this->getFilterTypesAvailable();
        return $list[$this->FilterType] ?? $this->FilterType;
    }


    public function getCMSFields()
    {
        $fieldNameField = OptionsetField::create(
            'FieldName',
            'Select Field',
            $this->getFieldsNamesAvailable()
        );
        if (!$this->FieldName) {
            return FieldList::create(
                $fieldNameField
            );
        }
        $fields = parent::getCMSFields();
        $fields->replaceField(
            'FieldName',
            $fieldNameField
                ->setTitle('Selected Field')
                ->performDisabledTransformation()
        );
        $fields->replaceField(
            'SortDirection',
            OptionsetField::create(
                'SortDirection',
                'Select Sort Direction',
                $this->getSortDirectionsAvailable()
            )
        );
        $fields->remove('SortOrder');
        return $fields;
    }



    protected function getFieldsNamesAvailable(): array
    {
        $selection = $this->Selection();
        return Injector::inst()->get(ClassAndFieldInfo::class)
            ->getListOfFieldNames(
                $selection,
                $selection->ModelClassName,
                ['db']
            );
    }

    protected function getSortDirectionsAvailable(): array
    {
        return [
            'ASC' => 'Ascending',
            'DESC' => 'Descending',
        ];
    }
}
