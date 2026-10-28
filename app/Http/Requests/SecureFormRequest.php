<?php

namespace App\Http\Requests;

use App\Support\SecurityLog;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/** Logs rejected input (field names and rule names only, never the values) before the normal 422/redirect. */
abstract class SecureFormRequest extends FormRequest
{
    protected function failedValidation(Validator $validator): void
    {
        SecurityLog::event('input.rejected', SecurityLog::INPUT, [
            'outcome' => 'failure',
            'form' => class_basename(static::class),
            'fields' => array_map(fn ($rules) => array_keys($rules), $validator->failed()),
        ], 'notice');

        parent::failedValidation($validator);
    }
}
