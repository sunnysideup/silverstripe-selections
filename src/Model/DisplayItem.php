<?php

declare(strict_types=1);

namespace Sunnysideup\Selections\Model;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use Sunnysideup\ClassesAndFieldsInfo\Api\ClassAndFieldInfo;
use Sunnysideup\OptionsetFieldGrouped\Forms\OptionsetGroupedField;

class DisplayItem extends DataObject
{
    private static $table_name = 'SelectionsDisplayItem';

    private static $casted_variable_options = [
        'CSSClasses' => '',
        'ATT' => '',
        'CDATA' => '',
        'HTML' => '',
        'HTMLATT' => '',
        'JS' => '',
        'RAW' => 'Value is returned as is, no formatting applied.',
        'RAWURLATT' => '',
        'URLATT' => '',
        'XML' => '',
        'ProcessedRAW' => '',
        'LimitCharacters' => '',
        'LimitCharactersToClosestWord' => '',
        'LimitWordCount' => '',
        'LowerCase' => '',
        'UpperCase' => '',
        'Plain' => 'Strips all HTML tags and entities, returning plain text only.',
        'BigSummary' => 'Provides a longer summary from content, usually a few sentences.',
        'ContextSummary' => '',
        'FirstParagraph' => 'Extracts and returns the first paragraph from HTML content.',
        'FirstSentence' => 'Extracts and returns the first sentence from the content.',
        'LimitSentences' => '',
        'Summary' => 'Provides a short summary of the content, similar to an excerpt.',
        'Nice' => 'Nicer version of the value.',
        'AbsoluteLinks' => '',
    ];


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

    public function getFieldNameNice(): string
    {
        $list = $this->getFieldsNamesAvailable();
        return $list[$this->FieldName] ?? $this->FieldName;
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
            'DisplayType',
            OptionsetField::create(
                'DisplayType',
                'Format Type',
                $this->getDisplayTypesAvailable()
            )
                ->setEmptyString('-- no specific formatting (recommended) --')
        );
        $fields->removeByName('SortOrder');
        return $fields;
    }

    protected function getFieldsNamesAvailable(?bool $grouped = false): array
    {
        $selection = $this->Selection();
        return Injector::inst()->get(ClassAndFieldInfo::class)
            ->getListOfFieldNames(
                $selection->ModelClassName,
                ['db', 'casting', 'has_one', 'belongs'],
                array_replace($this->Config()->get('class_and_field_inclusion_exclusion_schema'), ['grouped' => $grouped]),
            );
    }


    protected function getDisplayTypesAvailable(): array
    {
        $obj = $this->getFieldTypeObject();
        if ($obj) {
            $options = [];
            $vars = Config::inst()->get(get_class($obj), 'casting') ?: [];
            $optionsAvailable = Config::inst()->get(self::class, 'casted_variable_options') ?: [];
            foreach ($vars as $key => $value) {
                if (!isset($optionsAvailable[$key])) {
                    $options[$key] = $key;
                } elseif (!empty($optionsAvailable[$key])) {
                    $options[$key] = $key . ': ' . $optionsAvailable[$key];
                } else {
                    // do nothing
                }
            }
            return $options;
        }
        return [];
    }

    protected function getFieldTypeObject(): DBField
    {
        return $this->Selection()->getFieldTypeObject($this->FieldName);
    }

    public function onBeforeWrite(): void
    {
        parent::onBeforeWrite();
        if (!$this->Title) {
            $this->Title = $this->getFieldNameNice();
        }
    }
}
