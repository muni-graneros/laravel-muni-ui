<?php

/*
|--------------------------------------------------------------------------
| FiltroDeFecha: atajos de fecha para tablas Filament, en hora de Chile
|--------------------------------------------------------------------------
|
| «Hoy» en estas pruebas es el MIÉRCOLES 25 de noviembre de 2026, 10:00 en
| America/Santiago. Las fechas de los registros se escriben como cadenas en esa
| misma zona, que es como las guarda una aplicación con `app.timezone` en
| America/Santiago.
*/

use Filament\Tables\Filters\Filter;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Muni\Ui\Filament\Tablas\FiltroDeFecha;
use Muni\Ui\Tests\Fixtures\Suceso;
use Muni\Ui\Tests\Fixtures\TablaDeSucesos;

beforeEach(function (): void {
    config(['app.timezone' => 'America/Santiago']);
    date_default_timezone_set('America/Santiago');

    $this->travelTo(Carbon::parse('2026-11-25 10:00:00', 'America/Santiago'));
});

afterEach(function (): void {
    date_default_timezone_set('UTC');
});

/**
 * Crea un suceso por cada fecha (el nombre ES la fecha, en ambas columnas
 * salvo que se indique otra para `ocurrido_en`).
 *
 * @param  list<string>  $fechas
 */
function sucesosEnFechas(array $fechas): void
{
    foreach ($fechas as $fecha) {
        Suceso::query()->create(['nombre' => $fecha, 'created_at' => $fecha, 'ocurrido_en' => $fecha]);
    }
}

/**
 * Los nombres de los sucesos que deja pasar el filtro con ese estado.
 *
 * @param  array<string, mixed>  $estado
 * @return list<string>
 */
function sucesosQueDejaPasar(Filter $filtro, array $estado): array
{
    return $filtro->apply(Suceso::query(), $estado)->orderBy('id')->pluck('nombre')->all();
}

/**
 * Los textos de los indicadores del filtro de la tabla de prueba con ese estado.
 *
 * @param  array<string, mixed>  $estado
 * @return list<string>
 */
function indicadoresDelFiltroDeFecha(array $estado): array
{
    $tabla = Livewire::test(TablaDeSucesos::class)->set('tableFilters.fecha', $estado);

    return array_values(array_map(
        fn ($indicador): string => (string) $indicador->getLabel(),
        $tabla->instance()->getTable()->getFilter('fecha')->getIndicators(),
    ));
}

it('«Hoy» incluye las 00:00 y las 23:59 de hoy y excluye ayer y mañana', function (): void {
    sucesosEnFechas([
        '2026-11-24 23:59:59',
        '2026-11-25 00:00:00',
        '2026-11-25 23:59:59',
        '2026-11-26 00:00:00',
    ]);

    expect(sucesosQueDejaPasar(FiltroDeFecha::make(), ['atajo' => 'hoy']))
        ->toBe(['2026-11-25 00:00:00', '2026-11-25 23:59:59']);
});

it('«Ayer» incluye solo el día anterior completo', function (): void {
    sucesosEnFechas([
        '2026-11-23 23:59:59',
        '2026-11-24 00:00:00',
        '2026-11-24 23:59:59',
        '2026-11-25 00:00:00',
    ]);

    expect(sucesosQueDejaPasar(FiltroDeFecha::make(), ['atajo' => 'ayer']))
        ->toBe(['2026-11-24 00:00:00', '2026-11-24 23:59:59']);
});

it('«Esta semana» parte el lunes: con hoy miércoles incluye el lunes y excluye el domingo anterior', function (): void {
    sucesosEnFechas([
        '2026-11-22 23:59:59', // domingo anterior
        '2026-11-23 00:00:00', // lunes
        '2026-11-29 23:59:59', // domingo de esta semana
        '2026-11-30 00:00:00', // lunes siguiente
    ]);

    expect(sucesosQueDejaPasar(FiltroDeFecha::make(), ['atajo' => 'semana']))
        ->toBe(['2026-11-23 00:00:00', '2026-11-29 23:59:59']);
});

it('«Este mes» incluye el día 1 y excluye el último día del mes anterior', function (): void {
    sucesosEnFechas([
        '2026-10-31 23:59:59',
        '2026-11-01 00:00:00',
        '2026-11-30 23:59:59',
        '2026-12-01 00:00:00',
    ]);

    expect(sucesosQueDejaPasar(FiltroDeFecha::make(), ['atajo' => 'mes']))
        ->toBe(['2026-11-01 00:00:00', '2026-11-30 23:59:59']);
});

describe('«Rango»', function (): void {
    beforeEach(function (): void {
        sucesosEnFechas([
            '2026-11-09 23:59:59',
            '2026-11-10 00:00:00',
            '2026-11-15 23:59:59',
            '2026-11-16 00:00:00',
        ]);
    });

    it('con ambos extremos incluye los dos días completos', function (): void {
        expect(sucesosQueDejaPasar(FiltroDeFecha::make(), ['atajo' => 'rango', 'desde' => '2026-11-10', 'hasta' => '2026-11-15']))
            ->toBe(['2026-11-10 00:00:00', '2026-11-15 23:59:59']);
    });

    it('solo con «desde» no pone límite superior', function (): void {
        expect(sucesosQueDejaPasar(FiltroDeFecha::make(), ['atajo' => 'rango', 'desde' => '2026-11-10', 'hasta' => null]))
            ->toBe(['2026-11-10 00:00:00', '2026-11-15 23:59:59', '2026-11-16 00:00:00']);
    });

    it('solo con «hasta» no pone límite inferior', function (): void {
        expect(sucesosQueDejaPasar(FiltroDeFecha::make(), ['atajo' => 'rango', 'desde' => null, 'hasta' => '2026-11-15']))
            ->toBe(['2026-11-09 23:59:59', '2026-11-10 00:00:00', '2026-11-15 23:59:59']);
    });

    it('con los extremos invertidos los intercambia', function (): void {
        expect(sucesosQueDejaPasar(FiltroDeFecha::make(), ['atajo' => 'rango', 'desde' => '2026-11-15', 'hasta' => '2026-11-10']))
            ->toBe(['2026-11-10 00:00:00', '2026-11-15 23:59:59']);
    });
});

it('«Todo» no filtra', function (): void {
    sucesosEnFechas(['2020-01-01 12:00:00', '2026-11-25 12:00:00', '2030-06-15 12:00:00']);

    expect(sucesosQueDejaPasar(FiltroDeFecha::make(), ['atajo' => 'todo']))->toHaveCount(3);
});

it('filtra por la columna que se le indica', function (): void {
    Suceso::query()->create(['nombre' => 'creado hoy, ocurrió ayer', 'created_at' => '2026-11-25 09:00:00', 'ocurrido_en' => '2026-11-24 09:00:00']);
    Suceso::query()->create(['nombre' => 'creado ayer, ocurrió hoy', 'created_at' => '2026-11-24 09:00:00', 'ocurrido_en' => '2026-11-25 09:00:00']);

    expect(sucesosQueDejaPasar(FiltroDeFecha::make('ocurrido_en'), ['atajo' => 'hoy']))->toBe(['creado ayer, ocurrió hoy'])
        ->and(sucesosQueDejaPasar(FiltroDeFecha::make(), ['atajo' => 'hoy']))->toBe(['creado hoy, ocurrió ayer']);
});

it('se llama «fecha» salvo que se le dé otro nombre', function (): void {
    expect(FiltroDeFecha::make()->getName())->toBe('fecha')
        ->and(FiltroDeFecha::make()->getLabel())->toBe('Fecha')
        ->and(FiltroDeFecha::desdeHoy()->getName())->toBe('fecha')
        ->and(FiltroDeFecha::make('ocurrido_en', 'ocurrencia')->getName())->toBe('ocurrencia')
        ->and(FiltroDeFecha::desdeHoy('ocurrido_en', 'ocurrencia')->getName())->toBe('ocurrencia');
});

it('desdeHoy() arranca en «Hoy» y make() arranca en «Todo»', function (): void {
    sucesosEnFechas(['2026-11-24 12:00:00', '2026-11-25 12:00:00']);

    $ayer = Suceso::query()->where('nombre', '2026-11-24 12:00:00')->firstOrFail();
    $hoy = Suceso::query()->where('nombre', '2026-11-25 12:00:00')->firstOrFail();

    Livewire::test(TablaDeSucesos::class, ['desdeHoy' => true])
        ->assertSet('tableFilters.fecha.atajo', 'hoy')
        ->assertCanSeeTableRecords([$hoy])
        ->assertCanNotSeeTableRecords([$ayer]);

    Livewire::test(TablaDeSucesos::class)
        ->assertSet('tableFilters.fecha.atajo', 'todo')
        ->assertCanSeeTableRecords([$hoy, $ayer]);
});

it('indica el atajo activo y no indica nada con «Todo»', function (array $estado, array $esperado): void {
    expect(indicadoresDelFiltroDeFecha($estado))->toBe($esperado);
})->with([
    'todo' => [['atajo' => 'todo'], []],
    'hoy' => [['atajo' => 'hoy'], ['Fecha: Hoy']],
    'ayer' => [['atajo' => 'ayer'], ['Fecha: Ayer']],
    'semana' => [['atajo' => 'semana'], ['Fecha: Esta semana']],
    'mes' => [['atajo' => 'mes'], ['Fecha: Este mes']],
    'rango' => [['atajo' => 'rango', 'desde' => '2026-11-01', 'hasta' => '2026-11-15'], ['Fecha: del 01/11/2026 al 15/11/2026']],
    'rango invertido' => [['atajo' => 'rango', 'desde' => '2026-11-15', 'hasta' => '2026-11-01'], ['Fecha: del 01/11/2026 al 15/11/2026']],
    'rango solo desde' => [['atajo' => 'rango', 'desde' => '2026-11-01', 'hasta' => null], ['Fecha: desde el 01/11/2026']],
    'rango solo hasta' => [['atajo' => 'rango', 'desde' => null, 'hasta' => '2026-11-15'], ['Fecha: hasta el 15/11/2026']],
    'rango sin extremos' => [['atajo' => 'rango', 'desde' => null, 'hasta' => null], []],
]);

it('«Hoy» es el día de Chile: las 22:00 de hoy no se corren a mañana por UTC', function (): void {
    // 22:30 en Santiago ya es el día 26 en UTC.
    $this->travelTo(Carbon::parse('2026-11-25 22:30:00', 'America/Santiago'));

    expect(now('UTC')->toDateString())->toBe('2026-11-26');

    sucesosEnFechas(['2026-11-24 22:00:00', '2026-11-25 22:00:00']);

    expect(sucesosQueDejaPasar(FiltroDeFecha::make(), ['atajo' => 'hoy']))->toBe(['2026-11-25 22:00:00']);
});

it('sin zona configurada usa America/Santiago', function (): void {
    config(['app.timezone' => null]);
    date_default_timezone_set('UTC');
    $this->travelTo(Carbon::parse('2026-11-25 22:30:00', 'America/Santiago'));

    sucesosEnFechas(['2026-11-25 22:00:00', '2026-11-26 00:30:00']);

    expect(sucesosQueDejaPasar(FiltroDeFecha::make(), ['atajo' => 'hoy']))->toBe(['2026-11-25 22:00:00']);
});
