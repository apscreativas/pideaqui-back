<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->guard('web')->check()) {
            $builder->where($model->getTable().'.restaurant_id', auth()->guard('web')->user()->restaurant_id);
        }
    }
}
