<?php

namespace Vis\Builder\Fields;

use Illuminate\Support\Facades\View;

class ColorField extends AbstractField
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

        return '<span style="height: 20px; width:20px; display:inline-block; background-color: '.$this->getValue($row).';"></spans>';
    }

    // end getListValue

    public function getEditInput($row = [])
    {
        if ($this->hasCustomHandlerMethod('onGetEditInput')) {
            $res = $this->handler->onGetEditInput($this, $row);
            if ($res) {
                return $res;
            }
        }

        $input = View::make('admin::tb.input_color');
        $input->value = $this->getValue($row);
        $input->name = $this->getFieldName();
        $input->type = $this->getAttribute('color_type', 'hex');
        $input->default = $this->getAttribute('default', '');
        $input->comment = $this->getAttribute('comment');

        return $input->render();
    }

    // end getEditInput
}
