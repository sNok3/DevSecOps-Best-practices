@php
    $messages = [403 => 'You are not allowed to do this.', 404 => 'This page does not exist.', 419 => 'Your session expired. Please try again.', 429 => 'Too many requests. Please wait a moment.', 500 => 'Something went wrong on our side.'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>{{ $code }}</title></head>
<body>
<main>
    <h1>{{ $code }}</h1>
    <p>{{ $messages[$code] ?? 'Error' }}</p>
    {{-- Only a reference for support; no stack trace, query or input is ever shown. --}}
    <p>Reference: <code>{{ \Illuminate\Support\Facades\Context::get('request_id') }}</code></p>
    <p><a href="{{ url('/') }}">Back to the vault</a></p>
</main>
</body>
</html>
