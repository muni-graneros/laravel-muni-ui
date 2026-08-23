<?php

namespace Muni\Ui\Filament\Privacidad;

use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Muni\Ui\Filament\Privacidad\Contratos\BuscaTitulares;

/**
 * El ciclo ARCOP de la Ley 21.719 —recepción y resolución de solicitudes— como
 * plugin de panel, para que los sistemas del ecosistema lo hereden en vez de
 * reimplementarlo cada uno.
 *
 * Uso en el PanelProvider del adoptante:
 *
 *   ->plugin(
 *       PanelArcopPlugin::make()
 *           ->titulares(BuscadorDePersonas::class)
 *           ->permisos(resolver: Permisos::RESOLVER_SOLICITUD_ARCOP)
 *           ->credencial(etiqueta: 'RUN leído de la cédula')
 *           ->alcanceDelCese(CeseDeTratamiento::queCesa())
 *   )
 *
 * LO QUE ESTE PLUGIN NO HACE, y es la parte que importa: darle a un sistema la
 * pantalla para recibir y resolver solicitudes NO lo hace cumplir. El candado
 * que hace cesar de verdad un tratamiento —qué pantalla, qué CSV, qué correo y
 * qué job dejan de tocar a esa persona— depende del mapeo tratamiento→finalidad
 * de cada sistema, que este paquete no conoce. Un adoptante que monte el panel
 * y no escriba su candado va a certificarle por escrito a un vecino un cese que
 * no ocurre. Por eso {@see self::alcanceDelCese()} arranca sin declarar y el
 * aviso, mientras no se declare, dice que no se declaró.
 *
 * La configuración vive en el plugin —o sea, en el panel— y no en el
 * contenedor: dos paneles del mismo sistema pueden atender a titulares
 * distintos, y un singleton global los mezclaría. En esta tabla mezclar
 * sistemas no es un detalle cosmético, es fuga de datos entre organismos.
 */
class PanelArcopPlugin implements Plugin
{
    public const ID = 'panel-arcop';

    private BuscaTitulares|string|null $titulares = null;

    private string $permisoVer = 'view_any_solicitud';

    private string $permisoRecibir = 'create_solicitud';

    private string $permisoResolver = 'resolver_solicitud_arcop';

    private string $etiquetaCredencial = 'Credencial que presenta';

    private ?string $ayudaCredencial = null;

    private ?string $comoSeAcredita = null;

    private ?string $alcanceDelCese = null;

    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * El plugin del panel en curso.
     *
     * Truena con un mensaje que dice qué falta en vez de un `null` que reviente
     * tres capas más abajo: si alguien llegó a una pantalla del recurso sin el
     * plugin registrado, lo que falta es una línea en el PanelProvider.
     */
    public static function actual(): static
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        if ($panel?->hasPlugin(self::ID) !== true) {
            throw new PanelArcopNoRegistrado(
                'El panel ARCOP no está registrado en este panel. Agregá '
                .'`->plugin(PanelArcopPlugin::make()->titulares(...))` en el PanelProvider del sistema.',
            );
        }

        $plugin = $panel->getPlugin(self::ID);

        if (! $plugin instanceof static) {
            throw new PanelArcopNoRegistrado('El plugin registrado con el id «'.self::ID.'» no es el panel ARCOP.');
        }

        return $plugin;
    }

    public function getId(): string
    {
        return self::ID;
    }

    /**
     * Quién es el titular y cómo se lo busca. Es lo único obligatorio: sin esto
     * el paquete tendría que adivinar el esquema del adoptante.
     *
     * @param  BuscaTitulares|class-string<BuscaTitulares>  $buscador
     */
    public function titulares(BuscaTitulares|string $buscador): static
    {
        $this->titulares = $buscador;

        return $this;
    }

    /**
     * Los nombres de permiso del adoptante.
     *
     * Los defaults son los que genera Shield para este recurso
     * (`view_any_solicitud`, `create_solicitud`), así que un sistema con Shield
     * no tiene que declarar los dos primeros. El de resolver NO lo genera
     * Shield: existe para que el municipio pueda separar recibir de resolver
     * sin tocar código, y por eso se puede nombrar como quiera.
     */
    public function permisos(?string $ver = null, ?string $recibir = null, ?string $resolver = null): static
    {
        $this->permisoVer = $ver ?? $this->permisoVer;
        $this->permisoRecibir = $recibir ?? $this->permisoRecibir;
        $this->permisoResolver = $resolver ?? $this->permisoResolver;

        return $this;
    }

    /**
     * Cómo se llama, en el mesón de ESTE sistema, lo que el solicitante
     * presenta para acreditar su identidad.
     *
     * El panel no sabe si es una cédula, un padrón o un número de licencia: lo
     * único que hace es pasarle lo tipeado al `VerificadorIdentidad` que el
     * adoptante enchufó en el contenedor. Estos textos son para que el
     * funcionario sepa qué escribir.
     */
    public function credencial(string $etiqueta, ?string $ayuda = null, ?string $comoSeAcredita = null): static
    {
        $this->etiquetaCredencial = $etiqueta;
        $this->ayudaCredencial = $ayuda;
        $this->comoSeAcredita = $comoSeAcredita;

        return $this;
    }

    /**
     * Qué deja de hacer ESTE sistema cuando un bloqueo queda vigente.
     *
     * Es la frase que el funcionario le repite al vecino, así que tiene que
     * decir lo que el candado del adoptante ejecuta y nada más. Sin declararla,
     * el panel no afirma que nada haya cesado: ver {@see self::ID} arriba.
     */
    public function alcanceDelCese(?string $texto): static
    {
        $this->alcanceDelCese = $texto;

        return $this;
    }

    public function buscador(): BuscaTitulares
    {
        if ($this->titulares === null) {
            throw new PanelArcopNoRegistrado(
                'El panel ARCOP no sabe quién es el titular de datos de este sistema. Declaralo con '
                .'`PanelArcopPlugin::make()->titulares(TuBuscador::class)`: el paquete solo conoce el contrato '
                .'TitularDeDatos, no el esquema de nadie.',
            );
        }

        return is_string($this->titulares) ? app($this->titulares) : $this->titulares;
    }

    public function permisoVer(): string
    {
        return $this->permisoVer;
    }

    public function permisoRecibir(): string
    {
        return $this->permisoRecibir;
    }

    public function permisoResolver(): string
    {
        return $this->permisoResolver;
    }

    public function etiquetaCredencial(): string
    {
        return $this->etiquetaCredencial;
    }

    public function ayudaCredencial(): ?string
    {
        return $this->ayudaCredencial;
    }

    public function comoSeAcredita(): ?string
    {
        return $this->comoSeAcredita;
    }

    /**
     * El alcance declarado del cese, o el aviso de que nadie lo declaró.
     *
     * El texto por defecto no es un relleno: mientras el adoptante no diga qué
     * deja de hacer, lo único cierto es que el panel no lo sabe, y decírselo al
     * funcionario es más útil —y más honesto con el vecino— que una frase
     * tranquilizadora que el paquete no puede sostener.
     */
    public function textoDelCese(): string
    {
        return $this->alcanceDelCese
            ?? 'Este sistema no declaró qué deja de hacer con los datos de esta persona cuando el bloqueo queda '
            .'vigente. Antes de decirle al titular que su tratamiento cesó, confirmarlo con informática: el bloqueo '
            .'queda anotado, pero que se respete depende de un candado que este sistema tiene que haber escrito.';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([SolicitudResource::class]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
