<!DOCTYPE html>
<html lang="es" data-muni-theme="light">
<head>
    <meta charset="utf-8">
    <title>Formulario Livewire</title>
    <style>{!! file_get_contents(dirname((new ReflectionClass(\Muni\Ui\MuniUiServiceProvider::class))->getFileName(), 2).'/resources/css/muni-ui.css') !!}</style>
    @livewireStyles
</head>
<body style="margin:24px;font-family:system-ui;">
    <livewire:formulario />
    @livewireScripts
</body>
</html>
