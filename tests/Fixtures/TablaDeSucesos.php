<?php

namespace Muni\Ui\Tests\Fixtures;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;
use Muni\Ui\Filament\Tablas\FiltroDeFecha;

/**
 * Una tabla Filament mínima con `FiltroDeFecha`, montada como componente
 * Livewire: el estado por defecto del filtro y sus indicadores solo existen
 * dentro de una tabla viva, no en el objeto `Filter` suelto.
 */
class TablaDeSucesos extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public bool $desdeHoy = false;

    public function table(Table $table): Table
    {
        return $table
            ->query(Suceso::query())
            ->columns([TextColumn::make('nombre')])
            ->filters([
                $this->desdeHoy ? FiltroDeFecha::desdeHoy() : FiltroDeFecha::make(),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->table }}</div>';
    }
}
