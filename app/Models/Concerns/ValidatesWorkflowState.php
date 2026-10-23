<?php

namespace App\Models\Concerns;

use App\Support\WorkflowState;

trait ValidatesWorkflowState
{
    protected static function bootValidatesWorkflowState(): void
    {
        static::saving(fn ($model) => WorkflowState::validateModel($model));
    }
}
