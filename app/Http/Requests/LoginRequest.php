<?php

namespace App\Http\Requests;

class LoginRequest extends SecureFormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:254', 'email:rfc'],
            'password' => ['required', 'string', 'max:128'],
        ];
    }
}
