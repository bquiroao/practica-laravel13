<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Hospital Nido: protección y cuidado.">
    <title>{{ config('app.name', 'Hospital Nido') }}</title>
    <link rel="icon" type="image/svg+xml" href="/nido.svg">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icon-192.png">
    <meta name="theme-color" content="#0F5257">
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/main.tsx'])
</head>
<body class="antialiased">
<div id="app"></div>
</body>
</html>
