<?php

namespace App\Http\Requests;

class SaveDraftRequest extends SubmitFormRequest
{
    protected function isDraft(): bool
    {
        return true;
    }
}
