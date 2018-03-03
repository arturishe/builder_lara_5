<?php

namespace Vis\Builder\Fields;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Session;

class DatetimeField extends AbstractField
{
    public function onSearchFilter(&$db, $value)
    {
        $table = $this->definition['db']['table'];
        if ($this->getAttribute('is_range')) {
            if (! isset($value['from']) && ! isset($value['to'])) {
                return;
            }

            $dateFrom = isset($value['from']) ? $value['from'] : '28800';
            $dateTo = isset($value['to']) ? $value['to'] : '2146939932';
            $db->whereBetween(
                $table.'.'.$this->getFieldName(),
                [
                    date('Y-m-d H:i:s', $dateFrom),
                    date('Y-m-d H:i:s', $dateTo),
                ]
            );
        } else {
            $db->where(
                $table.'.'.$this->getFieldName(),
                $value
            );
        }
    }

    // end onSearchFilter

    public function prepareQueryValue($value)
    {
        if (! $value) {
            if ($this->getAttribute('is_null')) {
                return;
            }

            if ($this->getFieldName() == 'created_at') {
                return date('Y-m-d H:i:s');
            }

            return '0000-00-00 00:00:00';
        }

        return $value;
    }

    public function getListValue($row)
    {
        if ($this->hasCustomHandlerMethod('onGetListValue')) {
            $res = $this->handler->onGetListValue($this, $row);
            if ($res) {
                return $res;
            }
        }

        if (! $this->getValue($row)) {
            return '';
        }

        return $this->getValue($row);
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

        $value = $this->getValue($row);

        $input = View::make('admin::tb.input_datetime');
        $input->value = $value;
        $input->name = $this->getFieldName();
        $input->months = $this->getAttribute('months');
        $input->prefix = $row ? 'e-' : 'c-';
        $input->comment = $this->getAttribute('comment');

        return $input->render();
    }

    // end getEditInput

    public function getFilterInput()
    {
        if (! $this->getAttribute('filter')) {
            return '';
        }

        if ($this->getAttribute('is_range')) {
            return $this->getFilterRangeInput();
        }

        $definitionName = $this->getOption('def_name');
        $sessionPath = 'table_builder.'.$definitionName.'.filters.'.$this->getFieldName();
        $filter = Session::get($sessionPath, '');

        $input = View::make('admin::tb.filter_datetime');
        $input->name = $this->getFieldName();
        $input->value = $filter;
        $input->months = $this->getAttribute('months');

        return $input->render();
    }

    // end getFilterInput

    private function getFilterRangeInput()
    {
        $definitionName = $this->getOption('def_name');
        $sessionPath = 'table_builder.'.$definitionName.'.filters.'.$this->getFieldName();
        $filter = Session::get($sessionPath, []);

        $input = View::make('admin::tb.filter_datetime_range');
        $input->name = $this->getFieldName();
        $input->valueFrom = isset($filter['from']) ? $filter['from'] : false;
        $input->valueTo = isset($filter['to']) ? $filter['to'] : false;
        $input->months = $this->getAttribute('months');

        return $input->render();
    }

    // end getFilterRangeInput
}
