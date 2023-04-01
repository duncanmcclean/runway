<?php

namespace DuncanMcClean\Runway\Http\Requests;

use DuncanMcClean\Runway\Runway;
use Illuminate\Foundation\Http\FormRequest;
use Statamic\Facades\User;

class CreateRequest extends FormRequest
{
    public function authorize()
    {
        $resource = Runway::findResource($this->resourceHandle);

        if ($resource->readOnly()) {
            return false;
        }

        return User::current()->can('create', $resource);
    }

    public function rules()
    {
        return [];
    }
}
