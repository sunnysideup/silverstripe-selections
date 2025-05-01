<?php

declare(strict_types=1);

namespace Sunnysideup\Selections\Model;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
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
    ];

    private static $has_one = [
        'Selection' => Selection::class,
    ];

    private static $indexes = [
        'Title' => true,
        'SortOrder' => true,
    ];

    private static $default_sort = 'SortOrder ASC, ID DESC';

    private static $summary_fields = [
        'FieldNameNice' => 'Field Name',
        'FilterTypeNice' => 'Filter Type',
        'FilterValue' => 'Filter Value',
    ];

    private static $casting = [
        'Title' => 'Varchar',
        'FieldNameNice' => 'Varchar',
        'FilterTypeNice' => 'Varchar',
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

    protected function getFieldsNamesAvailable(): array
    {
        $selection = $this->Selection();
        return Injector::inst()->get(ClassAndFieldInfo::class)
            ->getListOfFieldNames(
                $selection,
                $selection->ModelClassName,
                ['db', 'belongs', 'has_one', 'has_many', 'many_many', 'belongs_many_many']
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
            'Not' => 'Select not matching values',
        ];
    }

    protected function getFieldTypeObject(): DBField
    {
        return $this->Selection()->getFieldTypeObject($this->FieldName);
    }
}
