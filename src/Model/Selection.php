<?php

declare(strict_types=1);

namespace Sunnysideup\Selections\Model;

use SilverStripe\AnyField\Form\ManyAnyField;
use SilverStripe\Core\ClassInfo;
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
use Sunnysideup\Selections\Admin\SelectionsAdmin;
use UndefinedOffset\SortableGridField\Forms\GridFieldSortableRows;

class Selection extends DataObject
{

    protected static $selection_cache_var = [];
    public static function selection_cache($id)
    {
        if (!isset(self::$selection_cache_var[$id])) {
            self::$selection_cache_var[$id] = self::get()->byID($id);
        }
        return self::$selection_cache_var[$id];
    }


    private static $table_name = 'SelectionsSelection';

    private static $singular_name = 'Record Selection';

    private static $plural_name = 'Record Selections';

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
        'Title' => 'Name of selection',
        'ModelClassName' => 'Record Type',
        'ModelClassNameNice' => 'Record Type',
        'LimitTo' => 'Maximum number of records (0 = all)',
        'FilterAny' => 'Include records that match any of the filters (instead of all) - note that if you include two or more filters for the same field, they will also be treated as a match any condition.',
        'FilterSelectionSummary' => 'Filter Summary',
    ];

    private static $summary_fields = [
        'Created.Ago' => 'Created',
        'ModelClassNameNice' => 'Record Type',
        'Title' => 'Name',
        'NumberOfRecords' => 'Matches',
    ];

    private static $casting = [
        'ModelClassNameNice' => 'Varchar',
        'NumberOfRecords' => 'Int',
        'FilterSelectionSummary' => 'Text',
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
        'minimum_class_count' => 5,
    ];

    public function getCMSFields()
    {
        if (!$this->HasValidClassName()) {
            return FieldList::create(
                $this->getSelectClassNameField(true)
            );
        } else {
            $this->write();
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
            $list = $this->getSelectionDataList();
            if ($list) {
                $fields->addFieldsToTab(
                    'Root.Matches',
                    [
                        $gf = GridField::create(
                            'Results',
                            'Matching Records',
                            $list,
                            $config = GridFieldConfig_RecordEditor::create()
                        ),
                    ],
                );
                $fields->fieldByName('Root.Matches')?->setTitle(
                    'Matches: ' . ($list->count())
                );
            }
            $config->removeComponentsByType(GridFieldAddNewButton::class);
            $config->removeComponentsByType(GridFieldDeleteAction::class);
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
                'FilterSelection' => 'Here you select the filters that will be used to filter the records. If you do not select any filters, all records will be shown.',
                'SortSelection' => 'Sorts first by the first item, then by the second item, etc.',
                'DisplaySelection' => 'Here you select the fields shown in the list. If you do not select any fields, a default selection of fields will be used.',
            ] as $name => $description
        ) {
            $myField = $fields->dataFieldByName($name);
            $myField->setDescription($description);
            $config = $myField->getConfig();
            $config->removeComponentsByType(GridFieldFilterHeader::class);
            $config->removeComponentsByType(GridFieldAddExistingAutocompleter::class);
            $config->removeComponentsByType(GridFieldDeleteAction::class);
            $config->addComponent(new GridFieldDeleteAction(false));
            if ($name !== 'FilterSelection') {
                $config->addComponent(new GridFieldSortableRows('SortOrder'));
            }
        }
        $fields->dataFieldByName('Description')
            ->setRows(3)
            ->setDescription(
                'Optional space to enter a more detailed description of this selection.'
            );
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
                    array_replace(
                        $this->Config()->get('class_and_field_inclusion_exclusion_schema'),
                        ['grouped' => $grouped]
                    ),
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

    public function getFilterSelectionSummary()
    {
        $parts = [];
        $maxCount = 5;
        foreach ($this->FilterSelection() as $i => $filter) {
            $parts[] = $filter->getTitle();
            if ($i === $maxCount - 1) {
                $parts[] = '...';
                break;
            }
        }
        if (count($parts)) {
            $glue = $this->FilterAny ? ' OR ' : ' AND ';
            return implode($glue, $parts);
        }
        return 'No filters selected';
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

    public function getSelectionDataList(): ?DataList
    {
        $className = $this->ModelClassName;
        if (!$className || !class_exists($className)) {
            return null;
        }
        $list = $className::get();
        $filter = $this->getSelectionFilterArray();
        if (!empty($filter)) {
            if ($this->FilterAny) {
                $list = $list->filterAny($filter);
            } else {
                $list = $list->filter($filter);
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
            $newValue = $filter->getFieldValueCalculatedAsArrayOrString();
            if (isset($filterArray[$key])) {
                $existing = $filterArray[$key];
                if (!is_array($existing)) {
                    // not an array, make it one
                    $existing = [$existing];
                }
                // already an array, add to it
                if (is_array($newValue)) {
                    $filterArray[$key] = array_merge($existing, $newValue);
                } else {
                    $existing[] = $newValue;
                    $filterArray[$key] = $existing;
                }
            } else if ($key) {
                $filterArray[$key] = $newValue;
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
            $displayType = $display->DisplayType ? '.' . $display->DisplayType : '';
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

    public function getFieldTypeObject(string $fieldName): ?DBField
    {
        $singleton = $this->getModelSingleton();
        return Injector::inst()->get(ClassAndFieldInfo::class)
            ->FindFieldTypeObject($singleton, $fieldName);
    }

    public function getFieldTypeObjectName(string $fieldName): string
    {
        $obj = $this->getFieldTypeObject($fieldName);
        if ($obj) {
            return ClassAndFieldInfo::standard_short_field_type_name($obj, true);
        }
        return 'ERROR: Field ' . $fieldName . 'not found';
    }

    protected function onBeforeWrite(): void
    {
        parent::onBeforeWrite();
        if ($this->HasValidClassName()) {
            if (!$this->Title) {
                $this->Title = $this->getModelClassNameNice();
            }
        }
        if ($this->Title && !$this->isInDB() || $this->isChanged('Title')) {
            $this->Title = $this->ensureUniqueTitle((string) $this->Title);
        }
    }

    public function CMSEditLink(): string
    {
        return Injector::inst()->get(SelectionsAdmin::class)
            ->getCMSEditLinkForManagedDataObject($this);
    }

    /**
     * This will convert:
     * /item/1/edit
     * /item/0
     * /item/2/edit?
     * ...into:
     * /item/new
     *
     * @return string
     */
    public function CMSAddLink(): string
    {
        return  preg_replace('#/item/\d+(/edit)?/?$#', '/item/new',  $this->CMSEditLink());
    }

    protected function ensureUniqueTitle(?string $baseTitle = null): string
    {
        if (!$baseTitle) {
            return '';
        }
        $suffix = 1;
        $newTitle = $baseTitle;

        while (
            $suffix < 99 &&
            Selection::get()->filter(['Title' => $newTitle])->exclude(['ID' => $this->ID ?: 0])->exists()
        ) {
            $suffix++;
            $newTitle = $baseTitle . ' #' . $suffix;
        }

        return $newTitle;
    }
}
