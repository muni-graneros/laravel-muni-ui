<?php

namespace Muni\Ui\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Muni\Shared\Privacidad\Contratos\TitularDeDatos;
use Muni\Ui\Filament\Privacidad\Contratos\BuscaTitulares;

/**
 * El buscador de titulares del sistema adoptante imaginario: busca por número
 * de padrón y por nombre, que es lo que ESE registro tiene. El paquete no sabe
 * ni tiene por qué saber por qué columna se busca en cada sistema.
 */
class BuscadorDeVecinos implements BuscaTitulares
{
    /** @return array<int|string, string> */
    public function buscar(string $termino): array
    {
        $termino = trim($termino);

        if ($termino === '') {
            return [];
        }

        return Vecino::query()
            ->where(fn ($q) => $q->where('padron', 'like', $termino.'%')
                ->orWhere('nombre_completo', 'like', '%'.$termino.'%'))
            ->orderBy('nombre_completo')
            ->limit(25)
            ->get()
            ->mapWithKeys(fn (Vecino $vecino): array => [
                (int) $vecino->getKey() => $vecino->titularNombre().' — '.$vecino->titularDocumento(),
            ])
            ->all();
    }

    public function encontrar(int|string $clave): (Model&TitularDeDatos)|null
    {
        return Vecino::query()->whereKey($clave)->first();
    }

    public function comoSeBusca(): string
    {
        return 'Busca por número de padrón o por nombre.';
    }
}
