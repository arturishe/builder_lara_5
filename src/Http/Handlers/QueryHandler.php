<?php

namespace Vis\Builder\Handlers;

use Vis\Builder\JarboeController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;

class QueryHandler
{
    protected $controller;
    protected $dbName;
    protected $dbOptions;
    protected $model;
    protected $definition;
    protected $definitionName;
    protected $db;
    protected $extendsTable;
    protected $extendsTableId;
    protected $extendsFields = [];

    public function __construct(JarboeController $controller)
    {
        $this->controller = $controller;
        $this->definition = $controller->getDefinition();
        $this->definitionName = $controller->getOption('def_name');

        if (isset($this->definition['cache']['tags'])) {
            $this->cache = $this->definition['cache']['tags'];
        } else {
            $this->cache = '';
        }

        $this->dbOptions = $this->definition['db'];
        $this->model = $this->definition['options']['model'];
        $this->dbName = $this->definition['db']['table'];

        if (isset($this->definition['options']['extends']) && count($this->definition['options']['extends'])) {
            foreach ($this->definition['options']['extends'] as $extend) {
                $table = $extend['table'];
                $this->extendsTable[$table] = $table;

                if (isset($extend['id'])) {
                    $this->extendsTableId[$table] = $extend['id'];
                } else {
                    $this->extendsTableId[$table] = $this->dbName.'_id';
                }
            }
        }
    }

    protected function getOptionDB($ident)
    {
        return $this->dbOptions[$ident];
    }

    protected function hasOptionDB($ident)
    {
        return isset($this->dbOptions[$ident]);
    }

    public function getRows($isPagination = true, $isUserFilters = true, $betweenWhere = [], $isSelectAll = false)
    {
        $this->checkExistTable();

        $modelName = $this->model;
        $this->db = new $modelName();

        $this->prepareSelectValues();

        if ($isSelectAll) {
            $this->db->addSelect($this->dbName.'.*');
        }

        $this->prepareFilterValues();

        if ($isUserFilters) {
            $this->onSearchFilterQuery();
        }

        if ($this->extendsTable) {
            foreach ($this->extendsTable as $table) {
                $this->db->leftJoin($table, "{$table}.{$this->extendsTableId[$table]}", '=', "{$this->dbName}.id");
            }
        }

        $this->dofilter();

        $sessionPath = 'table_builder.'.$this->definitionName.'.order';
        $order = Session::get($sessionPath, []);

        if ($order && $isUserFilters) {
            $this->db->orderBy($this->dbName.'.'.$order['field'], $order['direction']);
        } elseif ($this->hasOptionDB('order')) {
            $order = $this->getOptionDB('order');

            foreach ($order as $field => $direction) {
                $this->db->orderBy($this->dbName.'.'.$field, $direction);
            }
        }

        if ($betweenWhere) {
            $betweenField = $betweenWhere['field'];
            $betweenValues = $betweenWhere['values'];

            $this->db->whereBetween($betweenField, $betweenValues);
        }

        if ($this->hasOptionDB('pagination') && $isPagination) {
            $pagination = $this->getOptionDB('pagination');
            $perPage = $this->getPerPageAmount($pagination['per_page']);
            $paginator = $this->db->paginate($perPage);

            return $paginator;
        }

        return $this->db->get();
    }

    private function dofilter()
    {
        if (Input::has('filter')) {
            $filters = Input::get('filter');

            foreach ($filters as $nameField => $valueField) {
                if ($valueField) {
                    $this->db->where($nameField, $valueField);
                }
            }
        }
    }

    public function getPerPageAmount($info)
    {
        if (! is_array($info)) {
            return $info;
        }

        $sessionPath = 'table_builder.'.$this->definitionName.'.per_page';
        $perPage = Session::get($sessionPath);

        if (! $perPage) {
            $keys = array_keys($info);
            $perPage = $keys[0];
        }

        return $perPage;
    }

    protected function prepareFilterValues()
    {
        $filters = isset($this->definition['filters']) ? $this->definition['filters'] : [];
        if (is_callable($filters)) {
            $filters($this->db);

            return;
        }

        foreach ($filters as $name => $field) {
            $this->db->where($name, $field['sign'], $field['value']);
        }
    }

    protected function doPrependFilterValues(&$values)
    {
        $filters = isset($this->definition['filters']) ? $this->definition['filters'] : [];
        if (is_callable($filters)) {
            return;
        }

        foreach ($filters as $name => $field) {
            $values[$name] = $field['value'];
        }
    }

    protected function prepareSelectValues()
    {
        $this->db = $this->db->select($this->dbName.'.id');

        if (isset($this->definition['options']['is_sortable']) && $this->definition['options']['is_sortable']) {
            if (! Schema::hasColumn($this->dbName, 'priority')) {
                Schema::table(
                    $this->dbName,
                    function ($table) {
                        $table->integer('priority');
                    }
                );
            }

            $this->db = $this->db->addSelect($this->dbName.'.priority');
        }

        $fields = $this->controller->getFields();

        foreach ($fields as $name => $field) {
            $field->onSelectValue($this->db);
        }
    }

    public function getRow($id)
    {
        $this->db = DB::table($this->dbName);

        if ($this->extendsTable) {
            foreach ($this->extendsTable as $table) {
                $this->db->leftJoin($table, "{$table}.{$this->extendsTableId[$table]}", '=', "{$this->dbName}.id");
            }
        }

        $this->prepareSelectValues();

        $this->db->where($this->dbName.'.id', $id);

        return $this->db->first();
    }

    private function checkExistTable()
    {
        if (! Session::has($this->dbName.'_exist')) {
            if (! Schema::hasTable($this->dbName)) {
                Schema::create($this->dbName, function ($table) {
                    $table->increments('id');
                });
            }
        }

        Session::push($this->dbName.'_exist', 'created');
    }

    protected function onSearchFilterQuery()
    {
        $sessionPath = 'table_builder.'.$this->definitionName.'.filters';

        $filters = Session::get($sessionPath, []);
        foreach ($filters as $name => $value) {
            if ($this->controller->hasCustomHandlerMethod('onSearchFilter')) {
                $res = $this->controller->getCustomHandler()->onSearchFilter($this->db, $name, $value);
                if ($res) {
                    continue;
                }
            }

            $this->controller->getField($name)->onSearchFilter($this->db, $value);
        }
    }

    public function updateRow($values)
    {
        $this->clearCache();

        if ($this->controller->hasCustomHandlerMethod('handleUpdateRow')) {
            $res = $this->controller->getCustomHandler()->handleUpdateRow($values);
            if ($res) {
                return $res;
            }
        }

        $updateData = $this->getRowQueryValues($values);

        $model = $this->model;
        $this->checkFields($updateData);

        if ($this->controller->hasCustomHandlerMethod('onUpdateRowData')) {
            $this->controller->getCustomHandler()->onUpdateRowData($updateData, $values);
        }
        $this->doValidate($updateData);

        $modelObj = $model::find($values['id']);

        if (method_exists($modelObj, 'setFillable')) {
            $modelObj->setFillable(array_keys($updateData));
        }

        foreach ($updateData as $fild => $data) {
            if (is_array($data)) {
                if (isset($def['fields'][$fild]['multi']) && $def['fields'][$fild]['multi']) {
                    foreach ($data as $k => $dataElement) {
                        if (! $dataElement) {
                            unset($data[$k]);
                        }
                    }

                    if (count($data) == 0) {
                        $data = [''];
                    }
                }

                $updateDataRes[$fild] = json_encode($data);
            } else {
                $updateDataRes[$fild] = $data;
            }
        }

        $modelObj->update($updateDataRes);

        foreach ($this->controller->getPatterns() as $pattern) {
            $pattern->update($values, $values['id']);
        }

        $fields = $this->controller->getFields();
        foreach ($fields as $field) {
            if (preg_match('~^many2many~', $field->getFieldName())) {
                $this->onManyToManyValues($field->getFieldName(), $values, $values['id']);
            }

            $this->updateGroupIfUseTable($field, $values['id']);
        }

        $this->updateExtendsTable($values['id']);

        $res = [
            'id'     => $values['id'],
            'values' => $updateData,
        ];
        if ($this->controller->hasCustomHandlerMethod('onUpdateRowResponse')) {
            $this->controller->getCustomHandler()->onUpdateRowResponse($res);
        }

        return $res;
    }

    private function updateGroupIfUseTable($field, $id)
    {
        if ($field->getAttribute('use_table') && $field->getAttribute('type') == 'group') {
            $nameField = $field->getFieldName();
            $group = Input::get($nameField);
            $tableUse = $field->getAttribute('use_table')['table'];
            $fieldForeign = $field->getAttribute('use_table')['id'];
            DB::table($tableUse)->where($fieldForeign, $id)->delete();

            foreach ($group as $name => $arrayValue) {
                foreach ($arrayValue as $k => $item) {
                    $resultArray[$k][$fieldForeign] = $id;
                    $resultArray[$k][$name] = $item;
                }
            }

            if (isset($resultArray)) {
                DB::table($tableUse)->insert($resultArray);
            }
        }
    }

    public function updateExtendsTable($id)
    {
        if (count($this->extendsFields)) {
            foreach ($this->extendsFields as $tableEx => $fields) {
                $table = DB::table($tableEx);

                $hasExistRecord = DB::table($tableEx)->where($this->extendsTableId[$tableEx], $id)->count();
                if ($hasExistRecord) {
                    $table->where($this->extendsTableId[$tableEx], $id)->update($fields);
                } else {
                    $fields[$this->extendsTableId[$tableEx]] = $id;
                    $table->insert($fields);
                }
            }
        }
    }

    public function cloneRow($id)
    {
        $this->clearCache();

        if ($this->controller->hasCustomHandlerMethod('handleCloneRow')) {
            $res = $this->controller->getCustomHandler()->handleCloneRow($id);
            if ($res) {
                return $res;
            }
        }
        $this->db = DB::table($this->dbName);
        $page = (array) $this->db->where('id', $id)->select('*')->first();
        Event::fire('table.clone', [$this->dbName, $id]);

        unset($page['id']);

        $newId = $this->db->insertGetId($page);

        $this->cloneExtendsTables($id, $newId);

        return [
            'id'     => $id,
            'status' => $page,
        ];
    }

    private function cloneExtendsTables($id, $newId)
    {
        if (isset($this->extendsTable) && count($this->extendsTable)) {
            foreach ($this->extendsTable as $table) {
                $page = (array) DB::table($table)->where($this->extendsTableId[$table], $id)->select('*')->first();
                unset($page['id']);
                $page[$this->extendsTableId[$table]] = $newId;
                DB::table($table)->insertGetId($page);
            }
        }
    }

    public function deleteRow($id)
    {
        $this->clearCache();

        if ($this->controller->hasCustomHandlerMethod('handleDeleteRow')) {
            $res = $this->controller->getCustomHandler()->handleDeleteRow($id);
            if ($res) {
                return $res;
            }
        }

        foreach ($this->controller->getPatterns() as $pattern) {
            $pattern->delete($id);
        }

        $model = $this->model;
        $res = $model::find($id)->delete();

        Event::fire('table.delete', [$this->dbName, $id]);

        $res = [
            'id'     => $id,
            'status' => $res,
        ];

        $this->deleteExtendsTables($id);

        if ($this->controller->hasCustomHandlerMethod('onDeleteRowResponse')) {
            $this->controller->getCustomHandler()->onDeleteRowResponse($res);
        }

        return $res;
    }

    private function deleteExtendsTables($id)
    {
        if (isset($this->extendsTable) && count($this->extendsTable)) {
            foreach ($this->extendsTable as $table) {
                DB::table($table)->where($this->extendsTableId[$table], $id)->delete();
            }
        }
    }

    public function fastSave($input)
    {
        $this->clearCache();

        $nameField = $input['name'];
        $valueField = $input['value'];

        $model = $this->model;
        $modelObj = $model::find($input['id']);
        $modelObj->$nameField = $valueField;

        $modelObj->save();
    }

    public function insertRow($values)
    {
        $this->clearCache();

        if ($this->controller->hasCustomHandlerMethod('handleInsertRow')) {
            $res = $this->controller->getCustomHandler()->handleInsertRow($values);
            if ($res) {
                return $res;
            }
        }

        $insertData = $this->getRowQueryValues($values);

        $this->checkFields($insertData);

        $this->doValidate($insertData);
        $id = false;
        if ($this->controller->hasCustomHandlerMethod('onInsertRowData')) {
            $id = $this->controller->getCustomHandler()->onInsertRowData($insertData);
        }

        if (! $id) {
            foreach ($insertData as $fild => $data) {
                if (is_array($data)) {
                    if (isset($this->definition['fields'][$fild]['multi']) && $this->definition['fields'][$fild]['multi']) {
                        foreach ($data as $k => $dataElement) {
                            if (! $dataElement) {
                                unset($data[$k]);
                            }
                        }

                        if (count($data) == 0) {
                            $data = [''];
                        }
                    }

                    $insertDataRes[$fild] = json_encode($data);
                } else {
                    $insertDataRes[$fild] = $data;
                }
            }

            $model = $this->model;

            $objectModel = new $model;

            foreach ($insertDataRes as $key => $value) {
                $objectModel->$key = $value;
            }

            $objectModel->save();
            $id = $objectModel->id;
        }

        foreach ($this->controller->getPatterns() as $pattern) {
            $pattern->insert($values, $id);
        }

        $fields = $this->controller->getFields();
        foreach ($fields as $field) {
            if (preg_match('~^many2many~', $field->getFieldName())) {
                $this->onManyToManyValues($field->getFieldName(), $values, $id);
            }
            $this->updateGroupIfUseTable($field, $id);
        }

        $this->updateExtendsTable($id);

        $res = [
            'id' => $id,
            'values' => $insertData,
        ];

        if ($this->controller->hasCustomHandlerMethod('onInsertRowResponse')) {
            $this->controller->getCustomHandler()->onInsertRowResponse($res);
        }

        return $res;
    }

    private function onManyToManyValues($ident, $values, $id)
    {
        $field = $this->controller->getField($ident);
        $vals = isset($values[$ident]) ? $values[$ident] : [];
        $field->onPrepareRowValues($vals, $id);
    }

    private function doValidate($values)
    {
        $errors = [];
        $fields = $this->definition['fields'];

        foreach ($fields as $ident => $options) {
            try {
                $field = $this->controller->getField($ident);
                if ($field->isPattern()) {
                    continue;
                }

                $tabs = $field->getAttribute('tabs');
                if ($tabs) {
                    if (! $field->getAttribute('extends_table')) {
                        foreach ($tabs as $tab) {
                            $fieldName = $ident.$tab['postfix'];
                            $field->doValidate($values[$fieldName]);
                        }
                    }
                } else {
                    if (array_key_exists($ident, $values)) {
                        $field->doValidate($values[$ident]);
                    }
                }
            } catch (\Exception $e) {
                $errors = array_merge($errors, explode('|', $e->getMessage()));
                continue;
            }
        }

        if ($errors) {
            $errors = implode('|', $errors);

            throw new \RuntimeException($errors);
        }
    }

    private function getRowQueryValues($values)
    {
        $values = $this->unsetFutileFields($values);

        $fields = $this->definition['fields'];

        foreach ($fields as $ident => $options) {
            $field = $this->controller->getField($ident);

            if ($field->isPattern()) {
                continue;
            }

            $tabs = $field->getAttribute('tabs');
            if ($tabs) {
                foreach ($tabs as $tab) {
                    $fieldName = $ident.$tab['postfix'];
                    $values[$fieldName] = $field->prepareQueryValue($values[$fieldName]);

                    if ($field->getAttribute('extends_table') && array_key_exists($fieldName, $values)) {
                        $this->extendsFields[$field->getAttribute('extends_table')][$fieldName] = $field->prepareQueryValue($values[$fieldName]);
                        unset($values[$fieldName]);
                        continue;
                    }
                }
            } else {
                if (array_key_exists($ident, $values)) {
                    if ($field->getAttribute('extends_table')) {
                        $this->extendsFields[$field->getAttribute('extends_table')][$ident] = $field->prepareQueryValue($values[$ident]);
                        unset($values[$ident]);
                        continue;
                    }

                    $values[$ident] = $field->prepareQueryValue($values[$ident]);

                    if ($field->getAttribute('type') == 'group' && $field->getAttribute('use_table')) {
                        unset($values[$ident]);
                    }
                }
            }
        }

        return $values;
    }

    private function unsetFutileFields($values)
    {
        unset($values['id']);
        unset($values['query_type']);

        foreach ($values as $key => $val) {
            if (preg_match('~^many2many~', $key)) {
                unset($values[$key]);
            }
        }

        // patterns
        unset($values['pattern']);

        // for tree
        unset($values['node']);
        unset($values['__node']);

        return $values;
    }

    private function checkFields(&$values)
    {
        $fields = $this->definition['fields'];

        foreach ($fields as $ident => $options) {
            $field = $this->controller->getField($ident);

            if (method_exists($field, 'getNewValueId') && isset($values[$ident.'_new_foreign'])) {
                if ($new_id = $field->getNewValueId($values[$ident.'_new_foreign'])) {
                    $values[$ident] = $new_id;
                }
                unset($values[$ident.'_new_foreign']);
            }

            if ($field->isPattern()) {
                continue;
            }

            $tabs = $field->getAttribute('tabs');

            if ($tabs) {
                foreach ($tabs as $tab) {
                    $this->checkField($values, $ident, $field);
                }
            } else {
                if (isset($values[$ident])) {
                    $this->checkField($values, $ident, $field);
                }
            }
        }
    }

    private function checkField($values, $ident, $field)
    {
        if (! $field->isEditable()) {
            throw new \RuntimeException("Field [{$ident}] is not editable");
        }
    }

    public function clearOrderBy()
    {
        $sessionPath = 'table_builder.'.$this->definitionName.'.order';
        Session::forget($sessionPath);

        return true;
    }

    public function clearCache()
    {
        if (isset($this->definition['cache'])) {
            $cache = $this->definition['cache'];

            if (isset($cache['tags'])) {
                Cache::tags($cache['tags'])->flush();
            }

            if (isset($cache['keys'])) {
                Cache::forget($cache['keys']);
            }
        }
    }

    public function getUploadedFiles()
    {
        $list = File::files(public_path().'/storage/files');

        $data = [
            'status' => 'success',
            'data'   => view('admin::tb.files_list', compact('list'))->render(),
        ];

        return $data;
    }

    public function getUploadedImages($field)
    {
        if ($field->getAttribute('use_image_storage')) {
            return $this->getImagesWithImageStorage();
        } else {
            return $this->getImagesWithDefaultPath();
        }
    }

    private function getImagesWithImageStorage()
    {
        if (class_exists('\Vis\ImageStorage\Image')) {
            $list = \Vis\ImageStorage\Image::orderBy('created_at', 'desc');

            if (Input::get('tag')) {
                $list->leftJoin('vis_tags2entities', 'id_entity', '=', 'vis_images.id')->where('entity_type', 'Vis\ImageStorage\Image')->where('id_tag', Input::get('tag'));
            }

            if (Input::get('gallary')) {
                $list->leftJoin('vis_images2galleries', 'id_image', '=', 'vis_images.id')->where('id_gallery', Input::get('gallary'));
            }

            if (Input::get('q')) {
                $list->where('vis_images.title', 'like', Input::get('q').'%');
            }

            $list = $list->groupBy('vis_images.id')->paginate(18);

            $tags = \Vis\ImageStorage\Tag::where('is_active', 1)->orderBy('title', 'asc')->get();
            $galleries = \Vis\ImageStorage\Gallery::where('is_active', 1)->orderBy('title', 'asc')->get();

            $data = [
                'status' => 'success',
                'data' => view('admin::tb.image_storage_list', compact('list', 'tags', 'galleries'))->render(),
            ];
        } else {
            $data = [
                'status' => 'success',
                'data' => 'Не подключен пакет ImageStorage',
            ];
        }

        return $data;
    }

    private function getImagesWithDefaultPath()
    {
        $files = collect(File::files(public_path('storage/editor/fotos')));
        $page = (int) request('page') ?: 1;
        $onPage = 24;
        $slice = $files->slice(($page - 1) * $onPage, $onPage);

        $list = new \Illuminate\Pagination\LengthAwarePaginator($slice, $files->count(), $onPage);
        $list->setPath(url()->current());

        $data = [
            'status' => 'success',
            'data'   => view('admin::tb.images_list', compact('list'))->render(),
        ];

        return $data;
    }
}
