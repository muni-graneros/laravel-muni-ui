<?php

namespace Muni\Ui\Filament\Privacidad;

use RuntimeException;

/**
 * El panel ARCOP se usó sin declarar lo que el paquete no puede adivinar.
 *
 * No es una excepción defensiva: sin buscador de titulares el formulario no
 * tiene a quién ofrecer, y sin plugin registrado el recurso no tiene de dónde
 * leer los permisos del adoptante. Fallar acá, con el nombre del método que
 * falta, es mucho más barato que un panel que abre y no encuentra a nadie.
 */
class PanelArcopNoRegistrado extends RuntimeException {}
