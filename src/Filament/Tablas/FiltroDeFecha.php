<?php

namespace Muni\Ui\Filament\Tablas;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Filtro de fecha para tablas Filament con atajos («Hoy», «Ayer», «Esta
 * semana», «Este mes», «Rango»).
 *
 *     ->filters([FiltroDeFecha::desdeHoy('ocurrido_en')])
 *     ->persistFiltersInSession()
 *
 * Por qué guarda el ATAJO y no una fecha: con `persistFiltersInSession()`
 * Filament deja de usar los valores por defecto en cuanto la sesión tiene el
 * estado del filtro. Si lo guardado fuera la fecha de hoy, mañana la tabla
 * seguiría mostrando el día de ayer. Guardando «hoy», el rango se calcula de
 * nuevo en CADA consulta.
 *
 * La zona horaria sale de `app.timezone` (America/Santiago si no hay ninguna):
 * es la zona en que la aplicación guarda sus fechas, así que los límites del
 * día se comparan sin conversión. El límite superior es siempre abierto
 * (`>= inicio` y `< fin`), nunca `whereDate`, para que la consulta pueda usar
 * el índice de la columna.
 */
final class FiltroDeFecha
{
    private const ZONA_POR_DEFECTO = 'America/Santiago';

    private const FORMATO_VISIBLE = 'd/m/Y';

    /** @var array<string, string> */
    private const ATAJOS = [
        'todo' => 'Todo',
        'hoy' => 'Hoy',
        'ayer' => 'Ayer',
        'semana' => 'Esta semana',
        'mes' => 'Este mes',
        'rango' => 'Rango',
    ];

    /** Filtro que arranca en «Todo». */
    public static function make(string $columna = 'created_at', ?string $nombre = null): Filter
    {
        return self::armar($columna, $nombre, 'todo');
    }

    /** El mismo filtro, pero arranca en «Hoy». */
    public static function desdeHoy(string $columna = 'created_at', ?string $nombre = null): Filter
    {
        return self::armar($columna, $nombre, 'hoy');
    }

    private static function armar(string $columna, ?string $nombre, string $atajoPorDefecto): Filter
    {
        // Con `schema()` propio Filament ignora `Filter::default()`: el valor
        // por defecto va en el campo.
        return Filter::make($nombre ?? 'fecha')
            ->label('Fecha')
            ->schema([
                Select::make('atajo')
                    ->label('Fecha')
                    ->options(self::ATAJOS)
                    ->default($atajoPorDefecto)
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->live(),
                self::selectorDeDia('desde', 'Desde'),
                self::selectorDeDia('hasta', 'Hasta'),
            ])
            ->query(fn (Builder $query, array $data): Builder => self::aplicar($query, $columna, $data))
            ->indicateUsing(fn (array $data): ?string => self::indicador($data));
    }

    private static function selectorDeDia(string $nombre, string $etiqueta): DatePicker
    {
        return DatePicker::make($nombre)
            ->label($etiqueta)
            ->visible(fn (Get $get): bool => $get('atajo') === 'rango')
            ->native(false)
            ->locale('es')
            ->displayFormat(self::FORMATO_VISIBLE)
            ->firstDayOfWeek(1)
            ->timezone(fn (): string => self::zona());
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $data
     * @return Builder<Model>
     */
    private static function aplicar(Builder $query, string $columna, array $data): Builder
    {
        [$inicio, $fin] = self::limites($data);

        if ($inicio !== null) {
            $query->where($columna, '>=', $inicio);
        }

        if ($fin !== null) {
            $query->where($columna, '<', $fin);
        }

        return $query;
    }

    /**
     * El rango [inicio, fin) del estado del filtro, calculado AHORA.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private static function limites(array $data): array
    {
        $hoy = CarbonImmutable::now(self::zona())->startOfDay();

        switch ($data['atajo'] ?? 'todo') {
            case 'hoy':
                return [$hoy, $hoy->addDay()];
            case 'ayer':
                return [$hoy->subDay(), $hoy];
            case 'semana':
                $lunes = $hoy->startOfWeek(CarbonInterface::MONDAY);

                return [$lunes, $lunes->addWeek()];
            case 'mes':
                $primero = $hoy->startOfMonth();

                return [$primero, $primero->addMonth()];
            case 'rango':
                [$desde, $hasta] = self::extremos($data);

                return [$desde, $hasta?->addDay()];
            default:
                return [null, null];
        }
    }

    /**
     * Los días «desde» y «hasta» del rango, a las 00:00 y ya ordenados.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private static function extremos(array $data): array
    {
        $desde = self::dia($data['desde'] ?? null);
        $hasta = self::dia($data['hasta'] ?? null);

        if ($desde !== null && $hasta !== null && $desde->greaterThan($hasta)) {
            return [$hasta, $desde];
        }

        return [$desde, $hasta];
    }

    private static function dia(mixed $valor): ?CarbonImmutable
    {
        if ($valor instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($valor)->setTimezone(self::zona())->startOfDay();
        }

        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($valor, self::zona())->startOfDay();
        } catch (\Throwable) {
            // El estado del filtro viaja por el navegador: una fecha ilegible
            // no restringe, en vez de tumbar la tabla.
            return null;
        }
    }

    /** @param  array<string, mixed>  $data */
    private static function indicador(array $data): ?string
    {
        $atajo = $data['atajo'] ?? 'todo';

        if ($atajo === 'rango') {
            [$desde, $hasta] = self::extremos($data);

            return match (true) {
                $desde !== null && $hasta !== null => 'Fecha: del '.$desde->format(self::FORMATO_VISIBLE).' al '.$hasta->format(self::FORMATO_VISIBLE),
                $desde !== null => 'Fecha: desde el '.$desde->format(self::FORMATO_VISIBLE),
                $hasta !== null => 'Fecha: hasta el '.$hasta->format(self::FORMATO_VISIBLE),
                default => null,
            };
        }

        if (! is_string($atajo) || $atajo === 'todo' || ! isset(self::ATAJOS[$atajo])) {
            return null;
        }

        return 'Fecha: '.self::ATAJOS[$atajo];
    }

    private static function zona(): string
    {
        $zona = config('app.timezone');

        return is_string($zona) && $zona !== '' ? $zona : self::ZONA_POR_DEFECTO;
    }
}
