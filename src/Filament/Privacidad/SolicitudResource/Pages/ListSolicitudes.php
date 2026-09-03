<?php

namespace Muni\Ui\Filament\Privacidad\SolicitudResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Muni\Ui\Filament\Privacidad\SolicitudResource;

class ListSolicitudes extends ListRecords
{
    protected static string $resource = SolicitudResource::class;

    /**
     * La recepción es la única escritura del recurso; la `CreateAction` se
     * oculta sola cuando el usuario no tiene `create_solicitud`, porque
     * consulta `SolicitudResource::canCreate()`.
     *
     * @return array<int, CreateAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Recibir una solicitud'),
        ];
    }
}
