<?php

namespace Vis\Builder;

use Vis\Builder\Handlers\ViewHandler;
use Vis\Builder\Handlers\QueryHandler;
use Vis\Builder\Handlers\ExportHandler;
use Vis\Builder\Handlers\ImportHandler;
use Vis\Builder\Handlers\ActionsHandler;
use Vis\Builder\Handlers\ButtonsHandler;
use Vis\Builder\Handlers\RequestHandler;
use Vis\Builder\Handlers\DefinitionHandler;
use Vis\Builder\Handlers\CustomClosureHandler;

class JarboeController
{
    protected $currentID = false;

    protected $options;
    protected $definition;

    protected $handler;
    protected $callbacks;
    protected $fields;
    protected $groupFields;
    protected $patterns = [];

    public $view;
    public $request;
    public $query;
    public $actions;
    public $export;
    public $import;
    public $imageStorage;
    public $fileStorage;
    public $definitionClass;

    protected $allowedIds;

    public function __construct($options)
    {
        $this->options = $options;
        $this->definition = $this->getTableDefinition($this->getOption('def_name'));
        $this->definitionClass = new DefinitionHandler($this->definition, $this);

        $this->doPrepareDefinition();

        $this->handler = $this->createCustomHandlerInstance();
        if (isset($this->definition['callbacks'])) {
            $this->callbacks = new CustomClosureHandler($this->definition['callbacks'], $this);
        }
        $this->fields = $this->loadFields();
        $this->groupFields = $this->loadGroupFields();

        $this->actions = new ActionsHandler($this->definition['actions'], $this);

        if ($this->definition['export']) {
            $this->export = new ExportHandler($this->definition['export'], $this);
        }

        if ($this->definition['import']) {
            $this->import = new ImportHandler($this->definition['import'], $this);
        }

        if (isset($this->definition['buttons'])) {
            $this->buttons = new ButtonsHandler($this->definition['buttons'], $this);
        }
        $this->query = new QueryHandler($this);
        $this->view = new ViewHandler($this);
        $this->request = new RequestHandler($this);

        $this->currentID = request('id');
    }

    public function getCurrentID()
    {
        return $this->currentID;
    }

    private function doPrepareDefinition()
    {
        if (! isset($this->definition['export'])) {
            $this->definition['export'] = [];
        }
        if (! isset($this->definition['import'])) {
            $this->definition['import'] = [];
        }

        if (! isset($this->definition['actions'])) {
            $this->definition['actions'] = [];
        }

        if (! isset($this->definition['db']['pagination']['uri'])) {
            $this->definition['db']['pagination']['uri'] = $this->options['url'];
        }
    }

    // end doPrepareDefinition

    public function handle()
    {
        if ($this->hasCustomHandlerMethod('handle')) {
            $res = $this->getCustomHandler()->handle();
            if ($res) {
                return $res;
            }
        }

        return $this->request->handle();
    }

    // end handle

    public function isAllowedID($id)
    {
        return in_array($id, $this->allowedIds);
    }

    // end isAllowedID

    protected function getPreparedOptions($opt)
    {
        $options = $opt;
        $options['def_path'] = app_path().$opt['def_path'];

        return $options;
    }

    // end getPreparedOptions

    protected function createCustomHandlerInstance()
    {
        if (isset($this->definition['options']['handler'])) {
            $handler = '\\'.$this->definition['options']['handler'];

            return new $handler($this);
        }

        return false;
    }

    // end createCustomHandlerInstance

    public function hasCustomHandlerMethod($methodName)
    {
        return $this->getCustomHandler() && is_callable([$this->getCustomHandler(), $methodName]);
    }

    // end hasCustomHandlerMethod

    public function isSetDefinitionCallback($methodName)
    {
        //
    }

    // end isSetDefinitionCallback

    public function getCustomHandler()
    {
        return $this->handler ?: $this->callbacks;
    }

    // end getCustomHandler

    public function getField($ident)
    {
        if (isset($this->fields[$ident])) {
            return $this->fields[$ident];
        } elseif (isset($this->patterns[$ident])) {
            return $this->patterns[$ident];
        } elseif (isset($this->groupFields[$ident])) {
            return $this->groupFields[$ident];
        }

        throw new \RuntimeException("Field [{$ident}] does not exist for current scheme.");
    }

    // end getField

    public function getFields()
    {
        return $this->fields;
    }

    // end getFields

    public function getOption($ident)
    {
        if (isset($this->options[$ident])) {
            return $this->options[$ident];
        }

        throw new \RuntimeException("Undefined option [{$ident}].");
    }

    // end getOption

    public function getDefinitionName()
    {
        $definition = explode('.', $this->getOption('def_name'));

        return $definition[0];
    }

    public function getUrlAction()
    {
        return '/admin/handle/'.$this->getDefinitionName();
    }

    public function getAdditionalOptions()
    {
        if (isset($this->options['additional'])) {
            return $this->options['additional'];
        }

        return [];
    }

    // end getAdditionalOptions

    public function getDefinition()
    {
        return $this->definition;
    }

    // end getDefinition

    protected function loadFields()
    {
        $definition = $this->getDefinition();

        $fields = [];
        foreach ($definition['fields'] as $name => $info) {
            if ($this->isPatternField($name)) {
                $this->patterns[$name] = $this->createPatternInstance($name, $info);
            } else {
                $fields[$name] = $this->createFieldInstance($name, $info);
            }
        }

        return $fields;
    }

    // end loadFields

    protected function loadGroupFields()
    {
        $definition = $this->getDefinition();
        $fields = [];
        foreach ($definition['fields'] as $name => $info) {
            if ($info['type'] == 'group' && count($info['filds'])) {
                foreach ($info['filds'] as $nameGroup => $infoGroup) {
                    $fields[$nameGroup] = $this->createFieldInstance($nameGroup, $infoGroup);
                }
            }
        }

        return $fields;
    }

    public function getPatterns()
    {
        return $this->patterns;
    }

    // end getPatterns

    public function isPatternField($name)
    {
        return preg_match('~^pattern\.~', $name);
    }

    // end isPatternField

    protected function createPatternInstance($name, $info)
    {
        return new Fields\PatternField(
            $name,
            $info,
            $this->options,
            $this->getDefinition(),
            $this->getCustomHandler()
        );
    }

    // end createPatternInstance

    protected function createFieldInstance($name, $info)
    {
        $className = 'Vis\\Builder\\Fields\\'.ucfirst(camel_case($info['type'])).'Field';

        return new $className(
            $name,
            $info,
            $this->options,
            $this->getDefinition(),
            $this->getCustomHandler()
        );
    }

    // end createFieldInstance

    protected function getTableDefinition($table)
    {
        $table = preg_replace('~\.~', '/', $table);
        $path = config_path().'/builder/tb-definitions/'.$table.'.php';

        if (! file_exists($path)) {
            throw new \RuntimeException("Definition \n[{$path}]\n does not exist.");
        }

        $definition = require $path;

        if (! $definition) {
            throw new \RuntimeException('Empty definition?');
        }

        $definition['is_searchable'] = $this->isSearchable($definition);
        $definition['options']['admin_uri'] = \Config::get('builder.admin.uri');

        return $definition;
    }

    // end getTableDefinition

    private function isSearchable($definition)
    {
        $isSearchable = false;

        foreach ($definition['fields'] as $field) {
            if (isset($field['filter'])) {
                $isSearchable = true;
                break;
            }
        }

        return $isSearchable;
    }

    public function getFiltersDefinition()
    {
        $defName = $this->getOption('def_name');

        return session('table_builder.'.$defName.'.filters', []);
    }

    public function getOrderDefinition()
    {
        $defName = $this->getOption('def_name');

        return session('table_builder.'.$defName.'.order', []);
    }
}
