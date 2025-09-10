<?php

declare(strict_types=1);

namespace Sunnysideup\Selections\Model;

use BcMath\Number;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\GroupedDropdownField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\TextField;
use SilverStripe\GraphQL\Schema\Field\Field;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBDecimal;
use SilverStripe\ORM\FieldType\DBDouble;
use SilverStripe\ORM\FieldType\DBEnum;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBFloat;
use SilverStripe\ORM\FieldType\DBInt;
use SilverStripe\ORM\FieldType\DBPercentage;
use SilverStripe\ORM\FieldType\DBString;
use SilverStripe\ORM\FieldType\DBTime;
use SilverStripe\ORM\Search\SearchContext;
use Sunnysideup\AddCastedVariables\AddCastedVariablesHelper;
use Sunnysideup\ClassesAndFieldsInfo\Api\ClassAndFieldInfo;
use Sunnysideup\OptionsetFieldGrouped\Forms\OptionsetGroupedField;
use Sunnysideup\Selections\Admin\SelectionsAdmin;

class FilterItem extends DataObject
{
    private static $table_name = 'SelectionsFilterItem';

    private static $db = [
        'UseAdvancedFieldSelection' => 'Boolean(0)',
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
        'FilterValueNice' => 'Filter Value',
    ];

    private static $field_labels = [
        'FieldNameNice' => 'Field Name',
        'FilterTypeNice' => 'Filter Type',
        'FilterValue' => 'Filter Value',
        'FilterValueNice' => 'Filter Value (human readable)',
        'FieldNameCalculated' => 'Key for filtering',
        'FieldValueCalculated' => 'Value for filtering',
        'FilterTypeCalculated' => 'Filter for ',
        'IsEmpty' => 'Filter for empty values',
        'SelectOpposite' => 'Flip: Select records not matching the filter',
        'FilterType' => 'Filter Type',
    ];

    private static $casting = [
        'Title' => 'Varchar',
        'FieldNameNice' => 'Varchar',
        'FieldType' => 'Varchar',
        'FilterTypeNice' => 'Varchar',
        'FilterValueNice' => 'Varchar',
        'FieldNameCalculated' => 'Varchar',
        'FieldValueCalculated' => 'Varchar',
        'FilterTypeCalculated' => 'Varchar',
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
        foreach ($list as $fields) {
            if (is_array($fields)) {
                if (isset($fields[$this->FieldName])) {
                    return $fields[$this->FieldName];
                }
            }
        }
        return (string) $this->FieldName ?: 'ERROR';
    }

    public function getFieldType(): string
    {
        // print_r($this->getSearchFields());
        if ((bool) $this->UseAdvancedFieldSelection === false) {
            return '';
        }
        return Selection::selection_cache($this->SelectionID)?->getFieldTypeObjectName($this->FieldName);
    }

    public function getFilterTypeNice(): string
    {
        $list = $this->getFilterTypesAvailable();
        return $list[$this->FilterType] ?? $this->FilterType ?: 'Exact Match';
    }

    public function getFilterValueNice(): string
    {
        if ((bool) $this->UseAdvancedFieldSelection === false) {
            $f = $this->getFieldValueCalculated(false, true);
            if ($f instanceof FormField) {
                $f->setValue($this->FilterValue);
                if ($f->hasMethod('getFormattedValue')) {
                    $filterValue = $f->getFormattedValue();
                } elseif ($f->hasMethod('getSource')) {
                    $source = $f->getSource();
                    if (is_object($source)) {
                        $source = $source->toArray();
                    }
                    $filterValue = $source[$this->FilterValue] ?? $this->FilterValue;
                }
            }
        }
        if ($this->IsEmpty) {
            $filterValue = '[Empty Value]';
        } else {
            if (!isset($filterValue)) {
                $filterValue = $this->getFieldValueCalculated();
            }
            if ($filterValue === 0 || $filterValue === '0' || $filterValue === false) {
                $filterValue = 'NO, ZERO OR FALSE';
            }
            if ($filterValue === 1 || $filterValue === '1' || $filterValue === true) {
                $filterValue = 'YES, TRUE OR ONE';
            }
        }
        return $filterValue ?: 'filter value not set';
    }

    public function getFieldNameCalculated(): string
    {
        $v = (string) $this->FieldName;
        $filterType = $this->getFilterTypeCalculated();

        if ($filterType && $filterType !== 'ExactMatch') {
            $v .= ':' . $filterType;
        }
        if ($this->SelectOpposite) {
            $v .= ':NOT';
        }
        return $v;
    }

    public function getFilterTypeCalculated(): string
    {
        $v = '';
        if ((bool) $this->UseAdvancedFieldSelection === false) {
            $fields = $this->getSearchFilters();
            if (!empty($fields[$this->FieldName]['filter'])) {
                $v = $fields[$this->FieldName]['filter'];
            }
        } else {
            $v = $this->FilterType;
        }
        if ($v && ! class_exists($v) && ! class_exists('DataList.' . $v)) {
            if (str_ends_with($v, 'Filter')) {
                $v = substr($v, 0, -6);
            }
        }
        return $v ?: 'ExactMatch';
    }


    protected static $field_value_cache = [];

    public function getFieldValueCalculated(?bool $getInstruction = false, ?bool $getField = false): mixed
    {
        $key = $this->ID;
        if (isset(self::$field_value_cache[$key])) {
            $v = self::$field_value_cache[$key]['v'];
            $f = self::$field_value_cache[$key]['f'];
            $i = self::$field_value_cache[$key]['i'];
        } else {
            if ((bool) $this->UseAdvancedFieldSelection === false) {
                $fields = $this->getSearchFields();
                $f = $fields->fieldByName($this->FieldName);
                if ($f && $f instanceof FormField) {
                    $f->setName('FilterValue');
                }
                $v = trim((string) $this->FilterValue);
                $i = '';
            } else {
                $type = $this->getFieldTypeObject();
                if (is_object($type)) {
                    $type = get_class($type);
                }
                $v = trim((string) $this->FilterValue);
                $f = TextField::create('FilterValue', 'Filter Value', $v);
                switch ($type) {
                    case 'Boolean':
                    case DBBoolean::class:
                        if ($this->IsEmpty) {
                            $v = [null];
                        } else {
                            $v = strtolower($v);
                            if ($v === '1' || $v === 'true' || $v === 'yes' || $v === 'on' || $v === 1) {
                                $v = true;
                            } else {
                                $v = false;
                            }
                            $f = OptionsetField::create(
                                'FilterValue',
                                'Filter Value',
                                [
                                    1 => 'Yes / True',
                                    0 => 'No / False',
                                ],
                                $v ? 1 : 0
                            )->setEmptyString('-- select yes or no --');
                        }
                        $i = 'Please enter one of these: "yes" or "no", "true" or "false", "1" or "0".';
                        break;
                    case 'Int':
                    case DBInt::class:
                        if ($this->IsEmpty) {
                            $v = [null, 0];
                        } else {

                            $v = (int) $v;
                        }
                        $i = 'Please enter a whole number, e.g. "1" or "2" or "3" or "-10".';
                        $f = NumericField::create('FilterValue', 'Filter Value', (string) $v);
                        break;
                    case 'Float':
                    case 'Decimal':
                    case 'Double':
                    case 'Percentage':
                    case DBFloat::class:
                    case DBDecimal::class:
                    case DBDouble::class:
                    case DBPercentage::class:
                        if ($this->IsEmpty) {
                            $v = [null, 0];
                        } else {
                            $v = (float) $v;
                        }
                        $i = 'Please enter a number, e.g. "1" or "2" or "3" or "-10" or "1.5" or "2.5" or "3.5".';
                        $f = NumericField::create('FilterValue', 'Filter Value', (string) $v);
                        break;
                    case 'Date':
                    case DBDate::class:
                        if ($this->IsEmpty) {
                            $v = [null];
                        } else {
                            $v = DBDate::create_field(DBTime::class, (string) $v)->getValue();
                        }
                        $i = 'Please enter a date, e.g. "2023-01-01" or "tomorrow" or "yesterday" or "+3 days" or "next week" or "last week" or "next month" or "last month" or "next year" or "last year".';
                        break;
                    case 'Time':
                    case DBTime::class:
                        if ($this->IsEmpty) {
                            $v = [null];
                        } else {
                            $v = DBTime::create_field(DBTime::class, (string) $v)->getValue();
                        }
                        $i = 'Please enter a time, e.g. "12:00" or "12:00:00" or "12:00:00 AM" or "12:00:00 PM" or "12:00 AM" or "12:00 PM".';
                        break;
                    case 'Datetime':
                    case DBDatetime::class:
                        if ($this->IsEmpty) {
                            $v = [null];
                        } else {
                            $v = DBField::create_field(DBDatetime::class, (string) $v)->getValue();
                        }
                        $i = 'You can enter anything like "2023-01-01 12:00:00" or "tomorrow midday" or "yesterday 3pm" or "+3 hours" or "next week" or "last week" or "next month" or "last month" or "next year" or "last year".';
                        break;
                    case 'Varchar':
                    case 'Text':
                    case 'HTMLText':
                    case 'HTMLVarchar':
                    case 'DBHTMLText':
                    case DBString::class:
                    default:
                        if ($this->IsEmpty) {
                            $v = [null, ''];
                        } else {
                            // do nothing
                        }
                        $i = 'Please enter one or more words, e.g. "hello" or "world"  or "hello world".';
                }
            }
            self::$field_value_cache[$key] = ['v' => $v, 'f' => $f, 'i' => $i];
        }

        if ($getField) {
            if (empty($f)) {
                $f = TextField::create('FilterValue', 'Filter Value', (string) $this->FilterValue);
            }
            $f->setDescription($f->getDescription() . ' ' . $i);
            return $f;
        }
        if ($getInstruction) {
            return $i;
        }
        return $v;
    }

    public function getCMSFields()
    {

        if (!$this->FieldName) {
            return FieldList::create(
                CheckboxField::create('UseAdvancedFieldSelection', 'Use advanced field list?')
                    ->setDescription('Select from all fields of selected record.'),
                OptionsetGroupedField::create(
                    'FieldName',
                    'Select Field',
                    $this->getFieldsNamesAvailable(true)
                ),
            );
        }
        $fields = parent::getCMSFields();
        // $fields->removeByName('UseAdvancedFieldSelection');
        $fields->replaceField(
            'FieldName',
            ReadonlyField::create(
                'FieldNameNice',
                'Selected Field',
                $this->getFieldNameNice()
            )
        );
        if ($this->UseAdvancedFieldSelection) {
            $fields->replaceField(
                'FilterType',
                OptionsetField::create(
                    'FilterType',
                    'Select Filter Type',
                    $this->getFilterTypesAvailable()
                )->setEmptyString('Exact Match')
            );
        } else {
            $fields->removeByName('FilterType');
            $fields->removeByName('IsEmpty');
        }
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
            $fields->replaceField(
                'FilterValue',
                $this->getFieldValueCalculated(true, true)
            );
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
                (string) $this->FilterValue
            );
        }
        if ($this->Empty) {
            $this->FilterType = '';
        }
    }

    protected function getFieldsNamesAvailable(?bool $grouped = false): array
    {
        $model = $this->getModelSingleton();
        if (! $model) {
            return [];
        }
        if (!$this->UseAdvancedFieldSelection) {
            $mainList = [];
            foreach ($this->getSearchFilters() as $k => $searchFilter) {
                $mainList[$k] = isset($searchFilter['title']) ? $searchFilter['title'] : (is_string($searchFilter) ? $searchFilter : $k);
            }
            return ['Main Fields' => $mainList];
        }
        $otherLists = Injector::inst()->get(ClassAndFieldInfo::class)
            ->getListOfFieldNames(
                $model->ClassName,
                ['db', 'belongs', 'has_one', 'has_many', 'many_many', 'belongs_many_many'],
                array_replace($this->Config()->get('class_and_field_inclusion_exclusion_schema'), ['grouped' => $grouped]),
            );
        return $otherLists;
    }

    protected function getSearchContext(): ?SearchContext
    {
        return $this->getModelSingleton()?->getDefaultSearchContext();
    }

    protected function getSearchFilters(): ?array
    {
        return $this->getModelSingleton()?->searchableFields();
    }
    protected function getSearchFields(): ?FieldList
    {
        return $this->getSearchContext()?->getSearchFields();
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

    public function CMSEditLink(): string
    {
        return Injector::inst()->get(SelectionsAdmin::class)
            ->getCMSEditLinkForManagedDataObject($this);
    }

    protected function getModelSingleton(): ?DataObject
    {
        $selection = Selection::selection_cache($this->SelectionID);
        if (!$selection || !$selection->exists() || !$selection->ModelClassName) {
            return null;
        }
        return Injector::inst()->get($selection->ModelClassName);
    }
}
