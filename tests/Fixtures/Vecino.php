<?php

namespace Muni\Ui\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Muni\Shared\Privacidad\Contratos\TitularDeDatos;

/**
 * El titular de datos de un sistema adoptante IMAGINARIO, deliberadamente
 * distinto de `App\Models\Persona` de discapacidad.
 *
 * No tiene `nombres`/`apellidos`, no tiene `nro_documento` ni la columna
 * generada `nro_documento_norm`, y se identifica con un número de padrón
 * municipal en vez de un RUT. Si el panel ARCOP funciona contra este modelo,
 * es porque trabaja contra el contrato `TitularDeDatos` y no contra el
 * esquema del sistema donde nació. Probarlo con algo parecido a `Persona`
 * dejaría pasar exactamente el acoplamiento que la extracción vino a cortar.
 */
class Vecino extends Model implements TitularDeDatos
{
    protected $table = 'vecinos';

    protected $guarded = [];

    public bool $sensiblesPurgados = false;

    public bool $fueAnonimizado = false;

    public function titularNombre(): string
    {
        return (string) $this->nombre_completo;
    }

    public function titularDocumento(): string
    {
        return 'Padrón '.$this->padron;
    }

    /** @return array<string, mixed> */
    public function exportarDatosPersonales(): array
    {
        return [
            'padron' => $this->padron,
            'nombre_completo' => $this->nombre_completo,
            'correo' => $this->correo,
        ];
    }

    public function purgarDatosSensibles(): void
    {
        $this->forceFill(['correo' => null])->save();
        $this->sensiblesPurgados = true;
    }

    public function anonimizar(): void
    {
        $this->forceFill(['nombre_completo' => 'ANONIMIZADO', 'padron' => null])->save();
        $this->fueAnonimizado = true;
    }

    /** @return list<string> */
    public function camposRectificables(): array
    {
        // Sin 'padron': lo asigna el municipio, no lo corrige el titular.
        return ['nombre_completo', 'correo'];
    }

    public function fechaNacimientoTitular(): ?\DateTimeInterface
    {
        return $this->fecha_nacimiento ? Carbon::parse($this->fecha_nacimiento) : null;
    }
}
