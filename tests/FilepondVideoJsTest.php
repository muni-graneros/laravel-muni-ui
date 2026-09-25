<?php

it('el observador de FilePond existe en el paquete', function () {
    $js = file_get_contents(__DIR__.'/../resources/js/filepond-video.js');

    expect($js)->not->toBeFalse();
});

it('el observador documenta por qué el script se carga con data-navigate-once', function () {
    $js = file_get_contents(__DIR__.'/../resources/js/filepond-video.js');

    expect($js)->toContain('data-navigate-once');
});

it('el observador se vuelve a enganchar tras cada navegación de Livewire', function () {
    $js = file_get_contents(__DIR__.'/../resources/js/filepond-video.js');

    expect($js)->toContain('livewire:navigated');
});
