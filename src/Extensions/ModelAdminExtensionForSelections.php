<?php

namespace Sunnysideup\Selections\Extensions;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\View\Requirements;
use Sunnysideup\Selections\Admin\SelectionsAdmin;
use Sunnysideup\Selections\Model\Selection;

class ModelAdminExtensionForSelections extends Extension
{
    public function updateEditForm(&$form)
    {
        $owner = $this->getOwner();
        $usepredefinedselection = $owner->getRequest()->getVar('usepredefinedselection');
        $selections = Selection::get()
            ->filter(['ModelClassName' => $owner->modelClass])
            ->sort('Title', 'ASC');
        if ($selections->exists()) {
            $form->Fields()->unshift(
                DropdownField::create(
                    'usepredefinedselection',
                    'Use Pre-defined Selection',
                    $selections
                )
                    ->setEmptyString('-- no selection --')
                    ->setAttribute('onchange', 'updatePredefinedSelection(this)')
                    ->setValue($usepredefinedselection)
                    ->setRightTitle(DBHTMLText::create_field('HTMLText', ($this->createLinkToSelectionsModelAdmin())))
            );
        } else {
            $className = $owner->modelClass;
            $count = $className::get()->limit(20)->count();
            if ($count > 17) {
                $form->Fields()->push(
                    LiteralField::create(
                        'usepredefinedselection',
                        '<p class="message success">You can create pre-defined lists <a href="' . Injector::inst()->get(SelectionsAdmin::class)->Link() . '" target="_blank">here</a>.</p>',
                        []
                    )
                );
            }
        }
        $js = <<<JS
function updatePredefinedSelection(select) {
  const id = encodeURIComponent(select.value);
  const url = new URL(window.location.href);
  if(parseInt(id)) {
      url.search = '?usepredefinedselection=' + id;
  } else {
      url.search = '';
  }
  window.location.href = url.toString();
}
JS;
        Requirements::customScript($js);
    }

    public function updateList(&$list)
    {
        $owner = $this->getOwner();
        $usepredefinedselection = $owner->getRequest()->getVar('usepredefinedselection');
        if ($usepredefinedselection) {
            $selection = Selection::get()->byID((int) $usepredefinedselection);
            if ($selection && $selection->exists()) {
                $list = $selection->getSelectionDataList();
            }
        }
    }

    protected function sanitiseClassNameHelper($class)
    {
        return str_replace('\\', '-', $class ?? '');
    }

    private function createLinkToSelectionsModelAdmin(): string
    {
        $owner = $this->getOwner();
        $link = Injector::inst()->get(SelectionsAdmin::class)->Link();
        $filters = [
            'ModelClassName' => addslashes($owner->modelClass),
        ];
        $json = json_encode([
            'GridFieldFilterHeader' => ['Columns' => $filters],
        ]);
        $queryString = 'gridState-Sunnysideup-Selections-Model-Selection-0=' . urlencode($json);
        $description = '<a href="' . $link . '?' . $queryString . '" target="_blank">Edit this list</a>';
        return $description;
    }
}
