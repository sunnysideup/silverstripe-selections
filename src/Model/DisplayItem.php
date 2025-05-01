<?php

declare(strict_types=1);

namespace Sunnysideup\Selections\Model;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\OptionsetField;
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

    protected function getFieldsNamesAvailable(): array
    {
        $selection = $this->Selection();
        $list = Injector::inst()->get(ClassAndFieldInfo::class)
            ->getListOfFieldNames(
                $selection,
                $selection->ModelClassName,
                ['db', 'casting', 'has_one', 'belongs']
            );
        $exclude = $this->Selection()->DisplayItem()->exclude(['ID' => $this->ID])->map('FieldName', 'FieldName')->toArray();
        return array_diff($list, $exclude);
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
