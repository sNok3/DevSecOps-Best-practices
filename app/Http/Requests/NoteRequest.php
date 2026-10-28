<?php

namespace App\Http\Requests;

use App\Support\InputRules;

class NoteRequest extends SecureFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120', 'regex:'.InputRules::TITLE],
            'body' => ['required', 'string', 'max:10000', 'regex:'.InputRules::MULTILINE],
        ];
    }
}
