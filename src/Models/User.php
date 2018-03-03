<?php

namespace Vis\Builder;

use DB;
use Cartalyst\Sentinel\Users\EloquentUser;

class User extends EloquentUser
{
    protected $table = 'users';

    public function setFillable(array $params)
    {
        $this->fillable = $params;
    }

    public function getAvatar(array $imgParam)
    {
        if ($this->image) {
            $image = $this->image;
        } else {
            $image = '/packages/vis/builder/img/blank_avatar.gif';
        }

        return glide($image, $imgParam);
    }

    public function getFullName()
    {
        return $this->first_name.' '.$this->last_name;
    }

    public function getIdsTreeAccess()
    {
        return DB::table('role_users')
            ->leftJoin('roles2tree', 'roles2tree.id_role', '=', 'role_users.role_id')
            ->where('user_id', $this->id)
            ->where('id_tree', '!=', null)
            ->select('roles2tree.id_tree')
            ->get();
    }
}
