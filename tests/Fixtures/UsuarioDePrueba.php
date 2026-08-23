<?php

namespace Muni\Ui\Tests\Fixtures;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Funcionario del panel de prueba. Los permisos son una lista en la fila y no
 * Shield: el paquete no depende de Shield y esta prueba tampoco debe.
 */
class UsuarioDePrueba extends Authenticatable implements FilamentUser
{
    protected $table = 'users';

    protected $guarded = [];

    /** @var array<int, string> */
    public array $permisos = [];

    /** @param array<int, string> $permisos */
    public function conPermisos(array $permisos): static
    {
        $this->permisos = $permisos;

        return $this;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
