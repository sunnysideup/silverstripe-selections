<?php

declare(strict_types=1);

namespace Sunnysideup\Selections\Model;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GroupedDropdownField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\GraphQL\Schema\Field\Field;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBEnum;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBFloat;
use SilverStripe\ORM\FieldType\DBInt;
use SilverStripe\ORM\FieldType\DBString;
use SilverStripe\ORM\FieldType\DBTime;
use Sunnysideup\AddCastedVariables\AddCastedVariablesHelper;
use Sunnysideup\ClassesAndFieldsInfo\Api\ClassAndFieldInfo;
use Sunnysideup\OptionsetFieldGrouped\Forms\OptionsetGroupedField;

class FilterItem extends DataObject
{
    private static $table_name = 'SelectionsFilterItem';

    private static $db = [
        'FieldName' => 'Varchar(255)',
        'FilterType' => 'Varchar(255)',
        'IsEmpty' => 'Boolean',
        'FilterValue' => 'Varchar(255)',
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
        'IsEmpty' => 'Filter for empty values',
        'SelectOpposite' => 'Flip: Select records not matching the filter',
        'FilterType' => 'Filter Type',
    ];

    private static $casting = [
        'Title' => 'Varchar',
        'FieldNameNice' => 'Varchar',
        'FieldType' => 'Varchar',
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
        return $list[$this->FieldName] ?? (string) $this->FieldName ?: 'ERROR';
    }

    public function getFieldType(): string
    {
        return Selection::selection_cache($this->SelectionID)?->getFieldTypeObjectName($this->FieldName);
    }

    public function getFilterTypeNice(): string
    {
        $list = $this->getFilterTypesAvailable();
        return $list[$this->FilterType] ?? $this->FilterType ?: 'Exact Match';
    }

    public function getFilterValueNice(): string
    {
        if ($this->IsEmpty) {
            return '[Empty Value]';
        }
        return $this->FilterValue ?: 'filter value not set';
    }

    public function getFieldNameCalculated(): string
    {
        $v = (string) $this->FieldName;
        if ($this->FilterType) {
            $v .= ':' . $this->FilterType;
        }
        if ($this->SelectOpposite) {
            $v .= ':NOT';
        }
        return $v;
    }

    public function getFieldValueCalculated(?bool $getInstruction = false): mixed
    {
        if (!$getInstruction) {
            if ($this->IsEmpty || !$this->FilterValue) {
                return [null, '', 0];
            }
        }
        $type = $this->getFieldTypeObject();
        if (is_object($type)) {
            $type = get_class($type);
        }
        $v = trim((string) $this->FilterValue);
        switch ($type) {
            case 'Boolean':
            case DBBoolean::class:
                $v = strtolower($v);
                if ($v === '1' || $v === 'true' || $v === 'yes' || $v === 'on' || $v === 1) {
                    $v = true;
                } else {
                    $v = false;
                }
                $i = 'Please enter one of these: "yes" or "no", "true" or "false", "1" or "0".';
                break;
            case 'Int':
            case DBInt::class:
                $v = (int) $v;
                $i = 'Please enter a whole number, e.g. "1" or "2" or "3" or "-10".';
                break;
            case 'Float':
            case DBFloat::class:
                $v = (float) $v;
                $i = 'Please enter a number, e.g. "1" or "2" or "3" or "-10" or "1.5" or "2.5" or "3.5".';
                break;
            case 'Date':
            case DBDate::class:
                $v = DBDate::create_field(DBTime::class, (string) $v)->getValue();
                $i = 'Please enter a date, e.g. "2023-01-01" or "tomorrow" or "yesterday" or "+3 days" or "next week" or "last week" or "next month" or "last month" or "next year" or "last year".';
                break;
            case 'Time':
            case DBTime::class:
                $v = DBTime::create_field(DBTime::class, (string) $v)->getValue();
                $i = 'Please enter a time, e.g. "12:00" or "12:00:00" or "12:00:00 AM" or "12:00:00 PM" or "12:00 AM" or "12:00 PM".';
                break;
            case 'Datetime':
            case DBDatetime::class:
                $v = DBField::create_field(DBDatetime::class, (string) $v)->getValue();
                $i = 'You can enter anything like "2023-01-01 12:00:00" or "tomorrow midday" or "yesterday 3pm" or "+3 hours" or "next week" or "last week" or "next month" or "last month" or "next year" or "last year".';
                break;
            case 'Varchar':
            case 'Text':
            case 'HTMLText':
            case 'HTMLVarchar':
            case 'DBHTMLText':
            case DBString::class:
            default:
                // do nothing
                $i = 'Please enter one or more words, e.g. "hello" or "world"  or "hello world".';
        }
        if ($getInstruction) {
            return $i;
        }
        return $v;
    }

    public function getFieldValueAdditionalInformation(): string
    {
        return $this->getFieldValueCalculated(true);
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
            'FilterType',
            OptionsetField::create(
                'FilterType',
                'Select Filter Type',
                $this->getFilterTypesAvailable()
            )->setEmptyString('Exact Match')
        );
        if ($this->IsEmpty) {
            $fields->replaceField(
                'FilterValue',
                ReadonlyField::create(
                    'FilterValueNice',
                    $this->fieldLabel('FilterValue'),
                    'Filter for empty values'
                )
            );
        } else {
            $fields->dataFieldByName('FilterValue')
                ->setDescription($this->getFieldValueAdditionalInformation());
        }
        Injector::inst()->get(AddCastedVariablesHelper::class)->AddCastingFields(
            $this,
            $fields,
        );
        return $fields;
    }

    public function onBeforeWrite(): void
    {
        parent::onBeforeWrite();
        if ($this->FilterValue) {
            $this->FilterValue = str_replace(
                ['"', "'", '(', ')'],
                '',
                $this->FilterValue
            );
        }
    }

    protected function getFieldsNamesAvailable(?bool $grouped = false): array
    {
        $selection = Selection::selection_cache($this->SelectionID);
        if (!$selection->exists() || !$selection->ModelClassName) {
            return [];
        }
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
            'GreaterThan' => 'Greater Than',
            'GreaterThanOrEqual' => 'Greater Than or Equal',
            'LessThan' => 'Less Than',
            'LessThanOrEqual' => 'Less Than or Equal',
        ];
    }

    protected function getFieldTypeObject(): ?DBField
    {
        return Selection::selection_cache($this->SelectionID)?->getFieldTypeObject($this->FieldName);
    }
}
