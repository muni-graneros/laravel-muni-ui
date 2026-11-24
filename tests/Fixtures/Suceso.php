<?php

namespace Muni\Ui\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Un registro cualquiera con fecha de creación y fecha del hecho, distintas a
 * propósito: lo que se filtra en un panel municipal rara vez es `created_at`.
 */
class Suceso extends Model
{
    protected $table = 'sucesos';

    protected $guarded = [];
}
