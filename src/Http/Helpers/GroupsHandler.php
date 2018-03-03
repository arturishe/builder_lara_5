<?php

namespace Vis\Builder\Helpers;

use Group;
use Illuminate\Support\Facades\View;
use Vis\Builder\Handlers\CustomHandler;
use Cartalyst\Sentinel\Laravel\Facades\Sentinel;

class GroupsHandler extends CustomHandler
{
    public function onGetListValue($formField, array &$row)
    {
        if ($formField->getFieldName() == 'permissions') {
            return View::make('admin::tb.group_access_list');
        }
    }

    public function onGetEditInput($formField, array &$row)
    {
        if ($formField->getFieldName() == 'permissions') {
            $permissions = config('builder.tb-definitions.groups.fields.permissions.permissions');

            if (isset($permissions['generatePermissions']) && $permissions['generatePermissions']) {
                return $this->generatePermissions($row);
            } else {
                if (isset($row['id'])) {
                    $groupPermissionsThis = $this->getPermissionsThis($row['id']);
                } else {
                    $groupPermissionsThis = [];
                }

                return View::make('admin::tb.group_access_list', compact('permissions', 'groupPermissionsThis'));
            }
        }
    }

    private function generatePermissions(array &$row)
    {
        $permissions = config('builder.tb-definitions.groups.fields.permissions.permissions');
        unset($permissions['generatePermissions']);
        $permissionsMenu = config('builder.admin.menu');

        foreach ($permissionsMenu as $permission) {
            if (isset($permission['link']) && isset($permission['title'])) {
                $slug = str_replace('/', '', $permission['link']);

                $actions = config('builder.tb-definitions.'.$slug.'.actions');

                if (count($actions)) {
                    foreach ($actions as $slugAction => $action) {
                        if (isset($action['caption'])) {
                            $permissions[$permission['title']][$slug.'.'.$slugAction] = $action['caption'];
                        }
                    }
                } else {
                    $actions = config('builder.'.$slug.'.actions');

                    if (count($actions)) {
                        $permissions[$permission['title']][$slug.'.view'] = 'Просмотр';
                        foreach ($actions as $slugAction => $action) {
                            if (isset($action['caption'])) {
                                $permissions[$permission['title']][$slug.'.'.$slugAction] = $action['caption'];
                            }
                        }
                    } else {
                        $permissions[$permission['title']][$slug.'.view'] = 'Просмотр';
                    }
                }
            } else {
                if (isset($permission['submenu'])) {
                    foreach ($permission['submenu'] as $subMenu) {
                        if (isset($subMenu['link'])) {
                            $slug = str_replace('/', '', $subMenu['link']);
                            $actions = config('builder.tb-definitions.'.$slug.'.actions');

                            if (isset($subMenu['link']) && isset($subMenu['title'])) {
                                $permissions[$permission['title']][$subMenu['title']][$slug.'.view'] = 'Просмотр';

                                if (count($actions)) {
                                    foreach ($actions as $slugAction => $action) {
                                        $permissions[$permission['title']][$subMenu['title']][$slug.'.'.$slugAction]
                                            = $action['caption'];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $groupPermissionsThis = isset($row['id']) ? $this->getPermissionsThis($row['id']) : [];

        return View::make('admin::tb.group_access_list_auto', compact('permissions', 'groupPermissionsThis'));
    }

    private function getPermissionsThis($id)
    {
        if (isset($id)) {
            $group = Group::find($id);
            $groupPermissionsThis = $group->permissions;
        } else {
            $groupPermissionsThis = [];
        }

        return $groupPermissionsThis;
    }

    public function onAddSelectField($field, $db)
    {
        if ($field->getFieldName() == 'permissions') {
            return true;
        }
    }

    public function onUpdateRowData(array &$value, $row)
    {
        if (isset($row['permissions'])) {
            $role = Sentinel::findRoleById($row['id']);

            foreach ($row['permissions'] as $key => $permissions) {
                $permissionResult[$key] = $permissions ? true : false;
            }

            $role->permissions = $permissionResult;
            $role->save();

            unset($value['permissions']);
        }
    }

    public function onInsertRowData(array &$value)
    {
        if (isset($value['permissions'])) {
            foreach ($value['permissions'] as $key => $permissions) {
                $permissionResult[$key] = $permissions ? true : false;
            }

            $value['permissions'] = $permissionResult;

            $group = new Group();

            foreach ($value as $alias => $result) {
                $group->$alias = $result;
            }

            $group->save();

            return $group->id;
        }
    }
}
