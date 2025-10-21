<?php

namespace Sunnysideup\Selections\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\DropdownField;
use SilverStripe\View\Requirements;
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
            );
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
}
