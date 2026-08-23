<?php

it('el tema corrige el contraste del título de tarjeta en oscuro', function () {
    $css = file_get_contents(__DIR__.'/../resources/css/muni-ui-filament.css');

    expect($css)->toContain('.dark .fi-section-header-heading');
});

it('el tema acota el preview de video de FilePond', function () {
    $css = file_get_contents(__DIR__.'/../resources/css/muni-ui-filament.css');

    expect($css)->toContain('has-video-preview');
});
