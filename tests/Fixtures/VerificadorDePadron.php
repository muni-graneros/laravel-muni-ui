<?php

namespace Muni\Ui\Tests\Fixtures;

use Muni\Shared\Privacidad\Contratos\VerificadorIdentidad;
use Muni\Shared\Privacidad\ResultadoVerificacion;

/**
 * Cómo acredita la identidad el sistema adoptante imaginario: NO con la cédula
 * en el mesón (eso es de discapacidad), sino con el número de padrón que el
 * vecino trae en su aviso de contribuciones.
 *
 * Existe para probar que el panel no sabe nada de RUN ni de cédulas: lo único
 * que hace es entregarle el contexto del mostrador al verificador enchufado.
 */
class VerificadorDePadron implements VerificadorIdentidad
{
    /** @param array<string, mixed> $contexto */
    public function verificar(array $contexto): ResultadoVerificacion
    {
        $titular = $contexto['titular'] ?? null;
        $presentado = trim((string) ($contexto['credencial'] ?? ''));

        if (! $titular instanceof Vecino || $presentado === '') {
            return ResultadoVerificacion::fallida('padron_presencial', 'faltan el titular o el número de padrón');
        }

        if ($presentado !== (string) $titular->padron) {
            return ResultadoVerificacion::fallida('padron_presencial', 'el padrón presentado no corresponde al titular');
        }

        return new ResultadoVerificacion(true, 'padron_presencial', [
            'padron_hash' => hash('sha256', $presentado),
            'funcionario_id' => $contexto['funcionario_id'] ?? null,
        ]);
    }
}
