<?php

namespace DoubleThreeDigital\Runway\Http\Requests\CP;

use Illuminate\Foundation\Http\FormRequest;
use Statamic\Facades\User;

class EditRequest extends FormRequest
{
    public function authorize()
    {
        $resource = $this->route('resource');

        return User::current()->can('edit', $resource);
    }
}
