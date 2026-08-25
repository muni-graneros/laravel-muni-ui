<?php

namespace Muni\Ui\Filament\Privacidad\Contratos;

use Muni\Shared\Privacidad\Contratos\BuscaTitulares as ContratoDelModulo;

/**
 * @deprecated Se movió a Muni\Shared\Privacidad\Contratos\BuscaTitulares, para
 *             que un panel sin Filament también lo pueda implementar —si los dos
 *             paneles buscan titulares distinto, el mismo vecino recibe
 *             respuestas distintas según qué mesón lo atendió—. Esta interfaz
 *             queda para no romper a los adoptantes que ya la implementan y se
 *             saca en la próxima mayor.
 */
interface BuscaTitulares extends ContratoDelModulo {}
