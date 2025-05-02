<?php

declare(strict_types=1);

namespace Sunnysideup\Selections\Model;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GroupedDropdownField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\GraphQL\Schema\Field\Field;
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
        'IsEmpty' => 'Boolean',
        'SelectOpposite' => 'Boolean',
    ];

    private static $has_one = [
        'Selection' => Selection::class,
    ];

    private static $indexes = [];

    private static $default_sort = 'ID ASC';

    private static $summary_fields = [
        'FieldNameNice' => 'Field Name',
        'FilterTypeNice' => 'Filter Type',
        'FilterValue' => 'Filter Value',
    ];

    private static $field_labels = [
        'FieldNameNice' => 'Field Name',
        'FilterTypeNice' => 'Filter Type',
        'FilterValue' => 'Filter Value',
        'FieldNameCalculated' => 'Key for filtering',
        'FieldValueCalculated' => 'Value for filtering',
    ];

    private static $casting = [
        'Title' => 'Varchar',
        'FieldNameNice' => 'Varchar',
        'FilterTypeNice' => 'Varchar',
        'FieldNameCalculated' => 'Varchar',
        'FieldValueCalculated' => 'Varchar',
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
                    $this->getFilterTypeNice(),
                    $this->FilterValue,
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

    public function getFieldNameCalculated(): string
    {
        $v = $this->FieldName;
        if ($this->FilterType) {
            $v .= ':' . $this->FilterType;
        }
        if ($this->SelectOpposite) {
            $v .= ':NOT';
        }
        return $v;
    }

    public function getFieldValueCalculated(): string|array
    {
        if ($this->FilterValue || !$this->IsEmpty) {
            return $this->FilterValue;
        }
        return [null, '', 0];
    }

    public function getCMSFields()
    {
        if (!$this->FieldName) {
            return FieldList::create(
                GroupedDropdownField::create(
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
            'FilterType',
            OptionsetField::create(
                'FilterType',
                'Select Filter Type',
                $this->getFilterTypesAvailable()
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

    protected function getFieldsNamesAvailable(?bool $grouped = false): array
    {
        $selection = $this->Selection();
        return Injector::inst()->get(ClassAndFieldInfo::class)
            ->getListOfFieldNames(
                $selection->ModelClassName,
                ['db', 'belongs', 'has_one', 'has_many', 'many_many', 'belongs_many_many'],
                array_replace($this->Config()->get('class_and_field_inclusion_exclusion_schema'), ['grouped' => $grouped]),
            );
    }

    protected function getFilterTypesAvailable(): array
    {
        return [
            'PartialMatch' => 'Contains',
            'StartsWith' => 'Starts With',
            'ExactMatch' => 'Equals',
            'GreaterThan' => 'Greater Than',
            'GreaterThanOrEqual' => 'Greater Than or Equal',
            'LessThan' => 'Less Than',
            'LessThanOrEqual' => 'Less Than or Equal',
        ];
    }

    protected function getFieldTypeObject(): DBField
    {
        return $this->Selection()->getFieldTypeObject($this->FieldName);
    }
}
