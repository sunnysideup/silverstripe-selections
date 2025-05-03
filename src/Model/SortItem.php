<?php

declare(strict_types=1);

namespace Sunnysideup\Selections\Model;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GroupedDropdownField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use Sunnysideup\ClassesAndFieldsInfo\Api\ClassAndFieldInfo;
use Sunnysideup\OptionsetFieldGrouped\Forms\OptionsetGroupedField;

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
    private static array $class_and_field_inclusion_exclusion_schema = [
        // 'only_include_models_with_cmseditlink' => true,
        // 'only_include_models_with_can_create' => false,
        // 'only_include_models_with_can_edit' => false,
        // 'only_include_models_with_records' => true,
        // 'excluded_models' => [],
        // 'included_models' => [],
        // 'excluded_fields' => [],
        // 'included_fields' => [],
        // 'excluded_field_types' => [],
        // 'included_field_types' => [],
        // 'excluded_class_field_combos' => [],
        // 'included_class_field_combos' => [],
        'grouped' => true,
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


    public function getSortDirectionNice(): string
    {
        $list = $this->getSortDirectionsAvailable();
        return $list[$this->SortDirection] ?? $this->SortDirection ?: 'ASC';
    }



    public function getCMSFields()
    {

        if (!$this->FieldName) {
            return FieldList::create(
                OptionsetGroupedField::create(
                    'FieldName',
                    'Select Field',
                    $this->getFieldsNamesAvailable(true)
                )
            );
        }
        $fields = parent::getCMSFields();
        $fields->replaceField(
            'FieldName',
            ReadonlyField::create(
                'FieldNameNice',
                'Selected Field',
                $this->getFieldNameNice()
            )
        );
        $fields->replaceField(
            'SortDirection',
            OptionsetField::create(
                'SortDirection',
                'Select Sort Direction',
                $this->getSortDirectionsAvailable()
            )
        );
        $fields->removeByName('SortOrder');
        return $fields;
    }



    protected function getFieldsNamesAvailable(?bool $grouped = false): array
    {
        $selection = $this->Selection();
        return Injector::inst()->get(ClassAndFieldInfo::class)
            ->getListOfFieldNames(
                $selection->ModelClassName,
                ['db'],
                array_replace($this->Config()->get('class_and_field_inclusion_exclusion_schema'), ['grouped' => $grouped]),
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
