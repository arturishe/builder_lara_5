<?php

namespace Vis\Builder\Handlers;

use Vis\Builder\JarboeController;

abstract class CustomHandler
{
    protected $controller;

    public function __construct(JarboeController $controller)
    {
        $this->controller = $controller;
    }

    protected function getOption($ident)
    {
        return $this->controller->getOption($ident);
    }

    public function handle()
    {
    }

    public function onGetValue($formField, array &$row, &$postfix)
    {
    }

    public function onGetExportValue($formField, $type, array &$row, &$postfix)
    {
    }

    public function onGetEditInput($formField, array &$row)
    {
    }

    public function onGetListValue($formField, array &$row)
    {
    }

    public function onSelectField($formField, &$db)
    {
    }

    public function onPrepareSearchFilters(array &$filters)
    {
    }

    public function onSearchFilter(&$db, $name, $value)
    {
    }

    public function onUpdateRowResponse(array &$response)
    {
    }

    public function onInsertRowResponse(array &$response)
    {
    }

    public function onDeleteRowResponse(array &$response)
    {
    }

    public function handleDeleteRow($id)
    {
    }

    public function handleInsertRow($values)
    {
    }

    public function handleUpdateRow($values)
    {
    }

    public function onUpdateFastRowResponse(array &$response)
    {
    }

    public function onInsertRowData(array &$data)
    {
    }

    public function onUpdateRowData(array &$data, $row)
    {
    }

    public function onSearchCustomFilter($formField, &$db, $value)
    {
    }

    public function onGetCustomValue($formField, array &$row, &$postfix)
    {
    }

    public function onGetCustomEditInput($formField, array &$row)
    {
    }

    public function onGetCustomListValue($formField, array &$row)
    {
    }

    public function onSelectCustomValue(&$db)
    {
    }

    public function onFileUpload($file)
    {
    }

    public function onPhotoUpload($formField, $file)
    {
    }

    public function onPhotoUploadFromWysiwyg($file)
    {
    }

    public function onInsertButtonFetch($def)
    {
    }

    public function onUpdateButtonFetch($def)
    {
    }

    public function onDeleteButtonFetch($def)
    {
    }
}
