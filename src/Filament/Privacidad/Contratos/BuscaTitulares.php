<?php

namespace Muni\Ui\Filament\Privacidad\Contratos;

use Illuminate\Database\Eloquent\Model;
use Muni\Shared\Privacidad\Contratos\TitularDeDatos;

/**
 * Cómo encuentra el sistema adoptante a la persona que viene al mesón.
 *
 * Es el punto de extensión que este panel NO puede resolver por su cuenta:
 * discapacidad busca por RUT contra la columna generada `nro_documento_norm`
 * de `personas`, otro sistema buscará por padrón, por número de licencia o por
 * correo. Adivinar el esquema del adoptante es exactamente lo que hacía que
 * este recurso viviera en un solo repo.
 *
 * Las dos operaciones son distintas a propósito: el formulario busca sobre lo
 * que el funcionario tipea, y la recepción resuelve la clave elegida en un
 * titular de verdad. Un solo método obligaría a volver a buscar por texto para
 * recuperar a alguien que ya se había elegido.
 */
interface BuscaTitulares
{
    /**
     * Titulares que calzan con lo tipeado, como opciones del selector.
     *
     * @return array<int|string, string> clave del titular => etiqueta visible
     */
    public function buscar(string $termino): array;

    /**
     * El titular con esa clave, o `null` si ya no está.
     *
     * El tipo de retorno exige las dos caras a la vez —modelo Eloquent y
     * `TitularDeDatos`— porque el módulo de privacidad necesita las dos: el
     * morph guarda la clave y el tipo, y el ciclo ARCOP conversa con el
     * contrato.
     */
    public function encontrar(int|string $clave): (Model&TitularDeDatos)|null;

    /**
     * Qué se escribe en el buscador, en las palabras del sistema adoptante.
     *
     * Vive en el contrato y no en una opción suelta del plugin porque es el
     * mismo objeto el que sabe por qué columnas busca: separarlos hace que la
     * ayuda diga «busca por RUT» en un sistema que dejó de buscar por RUT.
     */
    public function comoSeBusca(): string;
}
