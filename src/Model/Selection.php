<?php

declare(strict_types=1);

namespace Sunnysideup\Selections\Model;

use SilverStripe\AnyField\Form\ManyAnyField;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\OptionsetField;
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


    protected function getSelectClassNameField(?bool $withInstructions = true, ?bool $onlyShowSelectedvalue = false): OptionsetField
    {
        $field = OptionsetField::create(
            'ModelClassName',
            $this->fieldLabel('ModelClassName'),
            $this->getListOfClasses()
        );
        if ($withInstructions) {
            $field->setDescription(
                '
                    Please select the record type you want to change.
                    This will be used to create a list of records to process.
                    Once selected, please save the record to continue.
                '
            );
        }
        if ($onlyShowSelectedvalue) {
            $source = $field->getSource();
            $field->setSource([
                $this->ClassNameToChange => $source[$this->ClassNameToChange] ?? 'ERROR! Class not found',
            ]);
        }

        return $field;
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
            $filterType = $filter->FilterType ? ':' . $filter->FilterType : '';
            $filterArray[$filter->FieldName . $filterType] = $filter->Value;
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
