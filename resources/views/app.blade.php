<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#072949">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="PMS Drive">
    <link rel="icon" type="image/png" href="/pwa/favicon-64.png">
    <link rel="apple-touch-icon" href="/pwa/apple-touch-icon.png">
    <link rel="mask-icon" href="/pwa/mask-icon.svg" color="#072949">
    @if (file_exists(public_path('build/manifest.webmanifest')))
        <link rel="manifest" href="/build/manifest.webmanifest">
    @endif
    <title>{{ config('app.name', 'Drive') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
