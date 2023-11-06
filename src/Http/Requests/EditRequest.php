<?php

namespace DoubleThreeDigital\Runway\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Statamic\Facades\User;

class EditRequest extends FormRequest
{
    public function authorize()
    {
        $resource = $this->route('resource');

        return User::current()->can('edit', $resource);
    }

    public function rules()
    {
        return [];
    }
}
