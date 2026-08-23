<?php

namespace Muni\Ui\Filament\Privacidad\SolicitudResource\Pages;

use Muni\Ui\Filament\Privacidad\SolicitudResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Muni\Shared\Privacidad\Contratos\VerificadorIdentidad;
use Muni\Shared\Privacidad\EdadNoAcreditada;
use Muni\Shared\Privacidad\IdentidadNoVerificada;
use Muni\Shared\Privacidad\RepresentacionNoAcreditada;
use Muni\Shared\Privacidad\RepresentacionRequerida;
use Muni\Shared\Privacidad\Solicitante;
use Muni\Shared\Privacidad\Solicitudes;
use Muni\Shared\Privacidad\TipoDeSolicitud;
use Muni\Ui\Filament\Privacidad\PanelArcopPlugin;

/**
 * Recepción de una solicitud ARCOP en el mesón.
 *
 * Esta página existe para NO escribir la fila. `handleRecordCreation()` no crea
 * un `Solicitud`: arma el `ResultadoVerificacion` con el verificador enchufado
 * en el contenedor y le pasa los argumentos a `Solicitudes::registrar()`, que es
 * donde el módulo aplica la verificación de identidad, el plazo legal, el
 * régimen de edad de NNA, la acreditación de la representación y la entrada de
 * bitácora. Un `Solicitud::create()` acá se saltaría las cinco.
 */
class CreateSolicitud extends CreateRecord
{
    protected static string $resource = SolicitudResource::class;

    protected static ?string $title = 'Recibir una solicitud ARCOP';

    public function getBreadcrumb(): string
    {
        return 'Recepción';
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Solicitud recibida: el plazo legal de respuesta empezó a correr.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        // Quién es el titular lo resuelve el buscador que declaró el
        // adoptante: el paquete conoce el contrato `TitularDeDatos` y ningún
        // modelo concreto.
        $titular = PanelArcopPlugin::actual()->buscador()->encontrar($data['titular_id']);

        if ($titular === null) {
            $this->rechazar(
                'Ese titular ya no está en el registro',
                'El registro elegido no existe hoy en este sistema. Volvé a buscarlo antes de recibir la solicitud: '
                .'una solicitud sin titular no se puede tramitar ni responder.',
            );
        }

        $solicitante = Solicitante::from((string) $data['solicitante']);

        // La acreditación la construye el verificador enchufado, no esta
        // página: qué cuenta como identidad acreditada es una decisión del
        // sistema adoptante —la cédula en el mesón en uno, otra cosa en otro— y
        // el panel solo le entrega el contexto del mostrador. La credencial va
        // con el nombre que le puso el adoptante en el formulario.
        $verificacion = app(VerificadorIdentidad::class)->verificar([
            'titular' => $titular,
            'credencial' => (string) ($data['credencial'] ?? ''),
            'funcionario_id' => auth()->id(),
        ]);

        try {
            return app(Solicitudes::class)->registrar(
                $titular,
                TipoDeSolicitud::from((string) $data['tipo']),
                (string) $data['detalle'],
                $verificacion,
                $solicitante,
                $this->rutaDeAcreditacion($data),
            );
        } catch (IdentidadNoVerificada $e) {
            $this->rechazar('La cédula presentada no acredita al titular', $e->getMessage());
        } catch (EdadNoAcreditada $e) {
            $this->rechazar('Falta acreditar la fecha de nacimiento del titular', $e->getMessage());
        } catch (RepresentacionRequerida $e) {
            $this->rechazar('El titular es menor de edad: tiene que ejercer su representante legal', $e->getMessage());
        } catch (RepresentacionNoAcreditada $e) {
            // Desde ESTE formulario no se llega: `acreditacion_path` es
            // obligatorio en cuanto el solicitante no es el titular, así que la
            // validación de Filament corta antes. Se atrapa igual porque el
            // formulario y el servicio son dos guardias distintas y solo la del
            // servicio es la que manda; si un día se agrega otra vía de entrada
            // (una acción, una importación), el funcionario ve el mensaje del
            // módulo y no una excepción sin manejar.
            $this->rechazar('Falta el documento que acredita la representación', $e->getMessage());
        }
    }

    /**
     * Ruta del documento de representación, o `null` cuando actúa el titular.
     *
     * @param  array<string, mixed>  $data
     */
    private function rutaDeAcreditacion(array $data): ?string
    {
        $ruta = trim((string) ($data['acreditacion_path'] ?? ''));

        return $ruta === '' ? null : $ruta;
    }

    /**
     * Traduce una negativa del módulo en un aviso accionable y detiene la
     * creación.
     *
     * El cuerpo es el mensaje del módulo TAL CUAL: cada excepción dice una cosa
     * distinta que hacer —que la cédula no calza, que hay que acreditar la fecha
     * de nacimiento, que tiene que venir el representante legal, que falta el
     * poder—, y reemplazarlo por un «no se pudo registrar» dejaría al
     * funcionario sin lo único que le sirve. El título nombra cuál de los cuatro
     * casos es, para distinguirlos de un vistazo. Que el cuerpo conserve la
     * instrucción está probado por los tests de `SolicitudRegistroTest`, y se
     * comprobó que lo detectan sustituyéndolo por un texto genérico.
     *
     * @throws Halt
     */
    private function rechazar(string $titulo, string $mensajeDelModulo): never
    {
        Notification::make()
            ->danger()
            ->title($titulo)
            ->body($mensajeDelModulo)
            ->persistent()
            ->send();

        // Se pide el rollback por si el panel algún día activa
        // `databaseTransactions()`; HOY no las tiene activadas, así que la
        // llamada no hace nada. Que no quede fila escrita no lo sostiene esta
        // línea: lo sostiene que `Solicitudes::registrar()` lanza sus cuatro
        // negativas ANTES de abrir su propia transacción. Los tests lo
        // verifican contando las filas de la tabla, no confiando en esto.
        throw (new Halt)->rollBackDatabaseTransaction();
    }
}
