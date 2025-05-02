<?php

declare(strict_types=1);

namespace Sunnysideup\Selections\Model;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GroupedDropdownField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use Sunnysideup\ClassesAndFieldsInfo\Api\ClassAndFieldInfo;

class DisplayItem extends DataObject
{
    private static $table_name = 'SelectionsDisplayItem';

    private static $db = [
        'Title' => 'Varchar(255)',
        'FieldName' => 'Varchar(255)',
        'DisplayType' => 'Varchar(255)',
        'SortOrder' => 'Int',
    ];

    private static $has_one = [
        'Selection' => Selection::class,
    ];

    private static $indexes = [
        'SortOrder' => true,
    ];

    private static $default_sort = 'SortOrder ASC, ID ASC';

    private static $field_labels = [
        'Title' => 'Header',
        'FieldNameNice' => 'Shows',
        'DisplayType' => 'Formatting',
    ];

    private static $summary_fields = [
        'Title' => 'Header',
        'FieldNameNice' => 'Shows',
        'DisplayType' => 'Formatting',
    ];

    private static $casting = [
        'FieldNameNice' => 'Varchar',
    ];

    public function getFieldNameNice(): string
    {
        $list = $this->getFieldsNamesAvailable();
        return $list[$this->FieldName] ?? $this->FieldName;
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
            'DisplayType',
            OptionsetField::create(
                'DisplayType',
                'Format Type',
                $this->getDisplayTypesAvailable()
            )
                ->setEmptyString('-- no specific formatting --')
        );
        $fields->remove('SortOrder');
    }

    protected function getFieldsNamesAvailable(?bool $grouped = false): array
    {
        $selection = $this->Selection();
        return Injector::inst()->get(ClassAndFieldInfo::class)
            ->getListOfFieldNames(
                $selection->ModelClassName,
                ['db', 'casting', 'has_one', 'belongs']
            );
    }


    protected function getDisplayTypesAvailable(): array
    {
        $obj = $this->getFieldTypeObject();
        $vars = Config::inst()->get($obj->ClassName, 'casting') ?: [];
        return array_keys($vars);
    }

    protected function getFieldTypeObject(): DBField
    {
        return $this->Selection()->getFieldTypeObject($this->FieldName);
    }
}
