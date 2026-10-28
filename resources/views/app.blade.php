<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DevSecOps Vault</title>
    {{-- Vite adds the CSP nonce (SecurityHeaders) to every tag it renders, including the React refresh preamble in dev. --}}
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
