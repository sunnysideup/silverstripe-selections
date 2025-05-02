<?php

declare(strict_types=1);

namespace Sunnysideup\Selections\Model;

use SilverStripe\AnyField\Form\ManyAnyField;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GroupedDropdownField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use Sunnysideup\ClassesAndFieldsInfo\Api\ClassAndFieldInfo;
use Sunnysideup\ClassesAndFieldsInfo\Traits\ClassesAndFieldsTrait;

class Selection extends DataObject
{


    private static $table_name = 'SelectionsSelection';

    private static $db = [
        'ModelClassName' => 'Varchar(255)',
        'Title' => 'Varchar(255)',
        'Description' => 'Text',
        'StartFromRecordNumber' => 'Int',
        'LimitTo' => 'Int',
        'FilterAny' => 'Boolean',
    ];

    private static $has_many = [
        'FilterSelection' => FilterItem::class,
        'SortSelection' => SortItem::class,
        'DisplaySelection' => DisplayItem::class,
    ];

    private static $indexes = [
        'Title' => true,
        'ModelClassName' => true
    ];

    private static $field_labels = [
        'ModelClassName' => 'Record Type',
    ];

    private static $default_sort = 'ID DESC';
    private static array $class_and_field_inclusion_exclusion_schema = [
        // 'only_include_models_with_cmseditlink' => true,
        // 'only_include_models_with_can_create_true' => false,
        // 'only_include_models_with_can_edit_true' => false,
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

    public function getCMSFields()
    {
        if (!$this->HasValidClassName()) {
            return FieldList::create(
                $this->getSelectClassNameField(true)
            );
        } else {
            $fields = parent::getCMSFields();
            $fields->replaceField(
                'ModelClassName',
                $this->getSelectClassNameField(false, true)
            );
            $fields->addFieldsToTab(
                'Root.FilterSelection',
                [
                    $fields->dataFieldByName('FilterAny')
                ],
                'FilterSelection'
            );
            $fields->addFieldsToTab(
                'Root.Main',
                [
                    $gf = GridField::create(
                        'Results',
                        'Matching Records',
                        $this->getSelectionDataList(),
                        GridFieldConfig_RecordEditor::create()
                            ->setPageSize(10)
                    ),
                ],
            );
            $displayFields = $this->getSelectionDisplayFields();
            if (!empty($displayFields)) {
                $gf->getConfig()
                    ->getComponentByType(GridFieldDataColumns::class)
                    ->setDisplayFields($displayFields);
            }
        }
        $fields->addFieldsToTab(
            'Root.SortSelection',
            [
                ManyAnyField::create('SortSelection', 'Sorts'),
            ]
        );
        $fields->addFieldsToTab(
            'Root.FilterSelection',
            [
                ManyAnyField::create('FilterSelection', 'Filters'),
            ]
        );
        $fields->addFieldsToTab(
            'Root.DisplaySelection',
            [
                ManyAnyField::create('DisplaySelection', 'Displays'),
            ]
        );
    }


    protected function HasValidClassName(): bool
    {
        $className = $this->ClassNameToChange;
        if ($className && class_exists($className)) {
            return true;
        }
        return false;
    }


    protected function getSelectClassNameField(?bool $grouped = true): GroupedDropdownField|ReadonlyField
    {
        if ($this->HasValidClassName()) {
            $field = ReadonlyField::create(
                'ModelClassNameNice',
                $this->fieldLabel('ModelClassNameNice'),
                $this->getClassNameToChangeNice()
            );
        } else {
            $field = GroupedDropdownField::create(
                'ModelClassName',
                $this->fieldLabel('ModelClassName'),
                Injector::inst()->get(ClassAndFieldInfo::class)->getListOfClasses(
                    array_replace($this->Config()->get('class_and_field_inclusion_exclusion_schema'), ['grouped' => $grouped]),
                )
            )->setDescription(
                '
                    Please select the record type you want to use for your selection.
                    Once you have this locked in (saved), you can start to make your selection.
                '
            );
        }
        return $field;
    }


    public function getClassNameToChangeNice(): string
    {
        $obj = $this->getRecordSingleton();
        if ($obj) {
            return $obj->i18n_singular_name();
        }
        return 'ERROR: Class not found';
    }


    public function getRecordSingleton()
    {
        if ($this->HasValidClassName()) {
            return Injector::inst()->get($this->ModelClassName);
        }
        return null;
    }

    public function getSelectionDataList(): DataList
    {
        $className = $this->ClassNameToChange;
        $list = $className::get();
        $filter = $this->getSelectionFilterArray();
        if (!empty($filter)) {
            if ($this->FilterAny) {
                $list = $list->filterAny($this->getSelectionFilterArray());
            } else {
                $list = $list->filter($this->getSelectionFilterArray());
            }
        }
        $sort = $this->getSelectionSort();
        if (!empty($sort)) {
            $list = $list->sort($this->getSelectionSortArray());
        }
        if ($this->StartFromRecordNumber) {
            $limit = $this->LimitTo ?: 99999999;
            $list = $list->limit($this->LimitTo, ($this->StartFromRecordNumber - 1));
        } elseif ($this->LimitTo) {
            $list = $list->limit($this->LimitTo);
        }

        return $list;
    }

    public function getSelectionFilterArray(): array
    {
        $filterArray = [];
        foreach ($this->FilterSelection() as $filter) {
            $filterArray[$filter->getFieldNameCalculated()] = $filter->getFieldValueCalculated();
        }
        return $filterArray;
    }

    public function getSelectionSort(): array
    {
        $sortArray = [];
        foreach ($this->SortSelection() as $sort) {
            $sortArray[$sort->FieldName] = $sort->SortDirection;
        }
        return $sortArray;
    }

    public function getSelectionDisplayFields(): array
    {
        $sortArray = [];
        foreach ($this->DisplaySelection() as $display) {
            $displayType = $display->DisplayType ? ':' . $display->DisplayType : '';
            $sortArray[$display->FieldName . $displayType] = $display->Title;
        }
        return $sortArray;
    }

    public function getModelSingleton(): mixed
    {
        $className = $this->ClassNameToChange;
        if ($className && class_exists($className)) {
            return Injector::inst()->get($className);
        }
        return null;
    }

    public function getFieldTypeObject(string $fieldName): DBField
    {
        $singleton = $this->getModelSingleton();
        return Injector::inst()->get(ClassAndFieldInfo::class)
            ->FindFieldTypeObject($singleton, $fieldName);
    }
}
