<?php

namespace App\Http\Requests;

use App\Support\InputRules;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends SecureFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60', 'regex:'.InputRules::NAME],
            'email' => ['required', 'string', 'max:254', 'email:rfc,strict', 'unique:users,email'],
            // OWASP/NIST 800-63B: length over complexity, allow long passphrases, reject known breached passwords.
            'password' => ['required', 'string', 'confirmed', Password::min(12)->max(128)->uncompromised()],
        ];
    }
}
