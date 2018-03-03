<?php

namespace Vis\Builder\Handlers;

class DefinitionHandler
{
    private $def;
    private $controller;

    public function __construct(array $definition, &$controller)
    {
        $this->def = $definition;
        $this->controller = $controller;
    }

    public function isSortable()
    {
        return isset($this->def['options']['is_sortable']) && $this->def['options']['is_sortable'];
    }

    public function isMultiActions()
    {
        return isset($this->def['multi_actions']);
    }

    public function isShowInsert()
    {
        return isset($this->def['actions']['insert']);
    }

    public function getFieldsList()
    {
        $result = [];
        foreach ($this->def['fields'] as $name => $value) {
            if ($this->checkShowList($value)) {
                $result[] = $this->controller->getField($name);
            }
        }

        return $result;
    }

    private function checkShowList($value)
    {
        if ($value['type'] == 'pattern' || $value['type'] == 'definition') {
            return false;
        }

        if (isset($value['hide_list']) && $value['hide_list']) {
            return false;
        }

        return true;
    }
}
