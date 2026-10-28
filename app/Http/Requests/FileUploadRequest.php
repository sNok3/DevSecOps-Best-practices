<?php

namespace App\Http\Requests;

use App\Support\InputRules;
use Illuminate\Validation\Validator;

class FileUploadRequest extends SecureFormRequest
{
    public function rules(): array
    {
        $types = config('vault.allowed_mimes');

        return [
            // `mimes` checks the real content type (magic bytes), not only the extension the client sends.
            'file' => ['required', 'file', 'max:'.config('vault.max_upload_kb'), 'mimes:'.implode(',', $types)],
        ];
    }

    /** The client-supplied file name is input too: it is shown back to the user, so it is allow-listed. */
    public function after(): array
    {
        return [function (Validator $validator) {
            $file = $this->file('file');
            if ($file && ! preg_match(InputRules::FILE_NAME, $file->getClientOriginalName())) {
                $validator->errors()->add('file', 'The file name contains characters that are not allowed.');
            }
        }];
    }
}
