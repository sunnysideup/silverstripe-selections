<?php

declare(strict_types=1);

namespace Sunnysideup\Selections\Model;

use SilverStripe\AnyField\Form\ManyAnyField;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GroupedDropdownField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use Sunnysideup\AddCastedVariables\AddCastedVariablesHelper;
use Sunnysideup\ClassesAndFieldsInfo\Api\ClassAndFieldInfo;
use Sunnysideup\ClassesAndFieldsInfo\Traits\ClassesAndFieldsTrait;
use Sunnysideup\OptionsetFieldGrouped\Forms\OptionsetGroupedField;
use UndefinedOffset\SortableGridField\Forms\GridFieldSortableRows;

class Selection extends DataObject
{


    private static $table_name = 'SelectionsSelection';

    private static $db = [
        'ModelClassName' => 'Varchar(255)',
        'Title' => 'Varchar(255)',
        'Description' => 'Text',
        'LimitTo' => 'Int',
        'StartFromRecordNumber' => 'Int',
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

    private static $summary_fields = [
        'ModelClassNameNice' => 'Record Type',
        'NumberOfRecords' => 'Matches',
    ];

    private static $casting = [
        'ModelClassNameNice' => 'Varchar',
        'NumberOfRecords' => 'Int',
        'RawSqlInfo' => 'HTMLText',
    ];

    private static $default_sort = 'ID DESC';
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
            $limitTo = $fields->dataFieldByName('LimitTo');
            $limitTo->setDescription(
                'Leave empty (0) to show all records. Set to, for example, 10 to show only the first 10 records.'
            );
            $startFromRecordNumber = $fields->dataFieldByName('StartFromRecordNumber');
            $startFromRecordNumber->setDescription(
                'Leave empty (0) to start from the first record.'
            );
            $fields->removeByName('LimitTo');
            $fields->removeByName('StartFromRecordNumber');
            $fields->addFieldsToTab(
                'Root.Main',
                [
                    new FieldGroup(
                        $limitTo,
                        $startFromRecordNumber
                    ),
                ]
            );

            $fields->addFieldsToTab(
                'Root.FilterSelection',
                [
                    $fields->dataFieldByName('FilterAny')
                ],
                'FilterSelection'
            );
            $fields->addFieldsToTab(
                'Root.Matches',
                [
                    $gf = GridField::create(
                        'Results',
                        'Matching Records',
                        $this->getSelectionDataList(),
                        $config = GridFieldConfig_RecordEditor::create()
                    ),
                ],
            );
            $config->removeComponentsByType(GridFieldAddNewButton::class);
            $config->removeComponentsByType(GridFieldDeleteAction::class);
            $gf->setDescription('Note that limits are starting records are applied to the list above.');
            $displayFields = $this->getSelectionDisplayFields();
            if (!empty($displayFields)) {
                $config
                    ->getComponentByType(GridFieldDataColumns::class)
                    ->setDisplayFields($displayFields);
            }
        }
        // $fields->addFieldsToTab(
        //     'Root.SortSelection',
        //     [
        //         ManyAnyField::create('SortSelection', 'Sorts'),
        //     ]
        // );
        // $fields->addFieldsToTab(
        //     'Root.FilterSelection',
        //     [
        //         ManyAnyField::create('FilterSelection', 'Filters'),
        //     ]
        // );
        // $fields->addFieldsToTab(
        //     'Root.DisplaySelection',
        //     [
        //         ManyAnyField::create('DisplaySelection', 'Displays'),
        //     ]
        // );
        foreach (
            [
                'SortSelection',
                'FilterSelection',
                'DisplaySelection',
            ] as $name
        ) {
            $myField = $fields->dataFieldByName($name);
            $config = $myField->getConfig();
            $config->removeComponentsByType(GridFieldFilterHeader::class);
            $config->removeComponentsByType(GridFieldAddExistingAutocompleter::class);
            $config->removeComponentsByType(GridFieldDeleteAction::class);
            $config->addComponent(new GridFieldDeleteAction(false));
            if ($name !== 'FilterSelection') {
                $config->addComponent(new GridFieldSortableRows('SortOrder'));
            }
        }
        Injector::inst()->get(AddCastedVariablesHelper::class)->AddCastingFields(
            $this,
            $fields,
        );
        return $fields;
    }


    protected function HasValidClassName(): bool
    {
        $className = $this->ModelClassName;
        if ($className && class_exists($className)) {
            return true;
        }
        return false;
    }


    protected function getSelectClassNameField(?bool $grouped = true): OptionsetGroupedField|ReadonlyField
    {
        if ($this->HasValidClassName()) {
            $field = ReadonlyField::create(
                'ModelClassNameNice',
                $this->fieldLabel('ModelClassNameNice'),
                $this->getModelClassNameNice()
            );
        } else {
            $field = OptionsetGroupedField::create(
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


    public function getModelClassNameNice(): string
    {
        $obj = $this->getRecordSingleton();
        if ($obj) {
            return $obj->i18n_singular_name();
        }
        return 'ERROR: Class not found';
    }

    public function getNumberOfRecords(): int
    {
        $list = $this->getSelectionDataList();
        if ($list && $list->exists()) {
            return $list->count();
        }
        return 0;
    }

    public function getRawSqlInfo(): string
    {
        $list = $this->getSelectionDataList();
        if ($list) {
            return $list->sql();
        }
        return 'no sql available';
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
        $className = $this->ModelClassName;
        $list = $className::get();
        $filter = $this->getSelectionFilterArray();
        if (!empty($filter)) {
            if ($this->FilterAny) {
                $list = $list->filterAny($this->getSelectionFilterArray());
            } else {
                $list = $list->filter($this->getSelectionFilterArray());
            }
        }
        $sort = $this->getSelectionSortArray();
        if (!empty($sort)) {
            $list = $list->sort($sort);
        }
        if ($this->StartFromRecordNumber) {
            $limit = $this->LimitTo ?: 99999999;
            $list = $list->limit($limit, ($this->StartFromRecordNumber - 1));
        } elseif ($this->LimitTo) {
            $list = $list->limit($this->LimitTo);
        }

        return $list;
    }

    public function getSelectionFilterArray(): array
    {
        $filterArray = [];
        foreach ($this->FilterSelection() as $filter) {
            $key = $filter->getFieldNameCalculated();
            $value = $filter->getFieldValueCalculated();
            if ($key && $value) {
                $filterArray[$filter->getFieldNameCalculated()] = $filter->getFieldValueCalculated();
            }
        }
        return $filterArray;
    }

    public function getSelectionSortArray(): array
    {
        $sortArray = [];
        foreach ($this->SortSelection() as $sort) {
            if (!$sort->FieldName || !$sort->SortDirection) {
                continue;
            }
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
        $className = $this->ModelClassName;
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
