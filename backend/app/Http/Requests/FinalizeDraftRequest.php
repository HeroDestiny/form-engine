<?php

namespace App\Http\Requests;

class FinalizeDraftRequest extends UpdateDraftRequest
{
    protected function isDraft(): bool
    {
        return false;
    }
}
