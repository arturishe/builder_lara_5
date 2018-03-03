<?php

namespace Vis\Builder\Fields;

class ReadonlyField extends AbstractField
{
    public function getEditInput($row = [])
    {
        return e($this->getValue($row));
    }

    // end getEditInput

    public function isReadonly()
    {
        return true;
    }

    // end isReadonly

    public function isEditable()
    {
        return false;
    }

    // end isEditable

    public function onSearchFilter(&$db, $value)
    {
        $table = $this->definition['db']['table'];
        $db->where($table.'.'.$this->getFieldName(), 'LIKE', '%'.$value.'%');
    }

    // end onSearchFilter
}
