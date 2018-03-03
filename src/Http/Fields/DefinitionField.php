<?php

namespace Vis\Builder\Fields;

use Illuminate\Support\Facades\View;

class DefinitionField extends AbstractField
{
    public function onSearchFilter(&$db, $value)
    {
        $table = $this->definition['db']['table'];
        $db->where($table.'.'.$this->getFieldName(), 'LIKE', '%'.$value.'%');
    }

    // end onSearchFilter

    public function getListValue($row)
    {
        if ($this->hasCustomHandlerMethod('onGetListValue')) {
            $res = $this->handler->onGetListValue($this, $row);
            if ($res) {
                return $res;
            }
        }
    }

    // end getListValue

    public function onSelectValue(&$db)
    {
    }

    public function getAttribute($ident, $default = false)
    {
        if ($ident == 'hide_list') {
            return true;
        }

        return parent::getAttribute($ident, $default);
    }

    // end getAttribute

    public function getEditInput($row = [])
    {
        if ($this->hasCustomHandlerMethod('onGetEditInput')) {
            $res = $this->handler->onGetEditInput($this, $row);
            if ($res) {
                return $res;
            }
        }

        $this->attributes['name'] = $this->getFieldName();
        $this->attributes['table'] = config('builder.tb-definitions.'.$this->getAttribute('definition').'.db.table');

        $input = View::make('admin::tb.input_definition');
        $input->nameDefinition = $this->getAttribute('definition');
        $input->foreignField = $this->getAttribute('foreign_field');
        $input->name = $this->getFieldName();
        $input->table = $this->attributes['table'];
        $input->attributes = json_encode($this->attributes);

        return $input->render();
    }

    // end getEditInput
}
