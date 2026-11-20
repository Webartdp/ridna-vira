<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class OrderPolicy extends ShopPolicy
{
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $record): bool
    {
        return ! $record->trashed() && parent::update($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return ! $record->trashed() && $user->canManage('admin');
    }

    public function restore(User $user, Model $record): bool
    {
        return $record->trashed() && $user->canManage('admin');
    }

    public function restoreAny(User $user): bool
    {
        return $user->canManage('admin');
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
