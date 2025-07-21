<?php
namespace App\Traits;

use App\Builders\BaseBuilder;

trait HasBaseBuilder
{
    public function newEloquentBuilder($query): BaseBuilder
    {
        return new BaseBuilder($query);
    }
}
