<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Muni\Shared\Privacidad\DiscoEvidenciaNoConfigurado;
use Muni\Shared\Privacidad\EstadoDeSolicitud;
use Muni\Shared\Privacidad\Modelos\EntradaBitacora;
use Muni\Shared\Privacidad\Modelos\Solicitud;
use Muni\Shared\Privacidad\ResultadoVerificacion;
use Muni\Shared\Privacidad\Solicitante;
use Muni\Shared\Privacidad\Solicitudes;
use Muni\Shared\Privacidad\TipoDeSolicitud;
use Muni\Ui\Filament\Privacidad\SolicitudResource;
use Muni\Ui\Filament\Privacidad\SolicitudResource\Pages\CreateSolicitud;
use Muni\Ui\Filament\Privacidad\SolicitudResource\Pages\ListSolicitudes;
use Muni\Ui\Tests\Fixtures\UsuarioDePrueba;
use Muni\Ui\Tests\Fixtures\Vecino;

use function Pest\Livewire\livewire;

/*
 * El panel ARCOP montado en un sistema adoptante que NO es discapacidad.
 *
 * Todo lo que sigue corre contra `Vecino` —sin RUT, sin `nombres`/`apellidos`,
 * identificado por padrón municipal—, con su propio buscador y su propio
 * verificador de identidad. Ese es el punto entero del archivo: si estas
 * pruebas pasan, el recurso trabaja contra los contratos y no contra el
 * esquema del sistema donde nació. Probarlo con un modelo parecido a `Persona`
 * dejaría pasar justo el acoplamiento que la extracción vino a cortar.
 */

function vecino(array $atributos = []): Vecino
{
    return Vecino::create(array_merge([
        'padron' => (string) random_int(10_000, 99_999),
        'nombre_completo' => 'Ana Reyes Soto',
        'correo' => 'ana@example.test',
        // Adulta: el módulo exige la fecha acreditada para aplicar el régimen
        // de edad (NNA vs. mayor de edad).
        'fecha_nacimiento' => '1985-03-10',
    ], $atributos));
}

/** @param array<int, string> $permisos */
function funcionario(array $permisos): UsuarioDePrueba
{
    $usuario = UsuarioDePrueba::create([
        'name' => 'Funcionaria de prueba',
        'email' => 'f'.random_int(1, 999_999).'@example.test',
        'password' => bcrypt('secreto'),
    ]);

    return $usuario->conPermisos($permisos);
}

function solicitudDePrueba(TipoDeSolicitud $tipo, Vecino $titular): Solicitud
{
    return app(Solicitudes::class)->registrar(
        $titular,
        $tipo,
        'Lo que pide el vecino.',
        new ResultadoVerificacion(true, 'padron_presencial'),
    );
}

/**
 * Lo que el funcionario ve en pantalla.
 *
 * No se usa `assertNotified()`: compara solo el TÍTULO, y acá lo que hay que
 * probar es el CUERPO. Se leen las dos claves de sesión que mira
 * `Filament\Notifications\Livewire\Notifications`, porque durante la petición
 * de Livewire el aviso se mueve de una a la otra.
 */
function avisos(): string
{
    /** @var array<int, array<string, mixed>> $avisos */
    $avisos = array_merge(
        (array) session()->get('filament.claimed_notifications', []),
        (array) session()->get('filament.notifications', []),
    );

    return collect($avisos)
        ->map(fn (array $aviso): string => ((string) ($aviso['title'] ?? '')).' | '.((string) ($aviso['body'] ?? '')))
        ->implode("\n");
}

beforeEach(function () {
    // Fuera de una petición HTTP nadie corre ShareErrorsFromSession, y Livewire
    // da por hecho que `errors` está compartido: sin esto, renderizar cualquier
    // página del panel truena con un ViewErrorBag nulo.
    View::share('errors', new ViewErrorBag);

    Filament::setCurrentPanel(Filament::getPanel('arcop'));
    Filament::bootCurrentPanel();

    // Los permisos del adoptante imaginario viven en la fila, no en Shield: el
    // paquete no depende de Shield y esta prueba tampoco debe.
    Gate::before(fn (UsuarioDePrueba $usuario, string $permiso) => in_array($permiso, $usuario->permisos, true) ?: null);

    $this->resolutora = funcionario(['view_any_solicitud', 'create_solicitud', 'resolver_solicitud_arcop']);
});

// ─── El titular no es Persona ────────────────────────────────────────────────

it('lista las solicitudes de un titular que no es Persona', function () {
    $solicitud = solicitudDePrueba(TipoDeSolicitud::Acceso, vecino());

    $this->actingAs($this->resolutora);

    livewire(ListSolicitudes::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$solicitud]);

    // Y el nombre sale del contrato, no de columnas adivinadas.
    expect(SolicitudResource::etiquetaTitular($solicitud))->toBe('Ana Reyes Soto (Padrón '.$solicitud->titular->padron.')');
});

it('el buscador de titulares lo pone el adoptante', function () {
    $vecina = vecino(['nombre_completo' => 'Marta Pino', 'padron' => '54321']);
    vecino(['nombre_completo' => 'Otro Vecino', 'padron' => '99999']);

    $this->actingAs($this->resolutora);

    // Se busca por padrón, que es lo que este registro tiene: si el recurso
    // hubiera conservado la búsqueda por RUT de discapacidad, acá no habría
    // ningún resultado.
    expect(SolicitudResource::buscarTitulares('54321'))
        ->toBe([$vecina->id => 'Marta Pino — Padrón 54321']);
});

it('recibe una solicitud acreditando la identidad como lo decide el adoptante', function () {
    $vecina = vecino();

    $this->actingAs($this->resolutora);

    livewire(CreateSolicitud::class)
        ->fillForm([
            'titular_id' => $vecina->id,
            'tipo' => TipoDeSolicitud::Acceso->value,
            'detalle' => 'Pide copia íntegra de sus datos.',
            'credencial' => (string) $vecina->padron,
            'solicitante' => Solicitante::Titular->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $solicitud = Solicitud::where('titular_type', $vecina->getMorphClass())
        ->where('titular_id', $vecina->getKey())
        ->latest('id')
        ->first();

    expect($solicitud)->not->toBeNull()
        ->and($solicitud->getAttribute('sistema'))->toBe('prueba')
        ->and($solicitud->estado)->toBe(EstadoDeSolicitud::Recibida)
        // El método de verificación es el del adoptante, no el de discapacidad.
        ->and($solicitud->verificacion_identidad['metodo'])->toBe('padron_presencial')
        ->and($solicitud->verificacion_identidad['evidencia'])->toHaveKey('padron_hash')
        ->and($solicitud->getAttribute('user_registro_id'))->toBe($this->resolutora->id);

    expect(EntradaBitacora::where('evento', 'solicitud.registrada')->count())->toBe(1);
});

it('una credencial que no acredita al titular no registra nada y muestra el motivo del adoptante', function () {
    $vecina = vecino();

    $this->actingAs($this->resolutora);

    livewire(CreateSolicitud::class)
        ->fillForm([
            'titular_id' => $vecina->id,
            'tipo' => TipoDeSolicitud::Acceso->value,
            'detalle' => 'Pide copia íntegra de sus datos.',
            'credencial' => '00000',
            'solicitante' => Solicitante::Titular->value,
        ])
        ->call('create')
        // Sin errores de formulario: la negativa viene del verificador
        // enchufado, no de una validación de Filament.
        ->assertHasNoFormErrors();

    expect(Solicitud::count())->toBe(0)
        ->and(avisos())->toContain('el padrón presentado no corresponde al titular');
});

// ─── Los permisos son del adoptante ──────────────────────────────────────────

it('los nombres de permiso no están horneados en el paquete', function () {
    $solicitud = solicitudDePrueba(TipoDeSolicitud::Acceso, vecino());

    // El adoptante bautiza sus permisos como quiera.
    Filament::getPanel('arcop')->getPlugin('panel-arcop')->permisos(
        ver: 'ver_arcop',
        recibir: 'recibir_arcop',
        resolver: 'resolver_arcop',
    );

    // Quien trae los nombres por defecto de Shield ya no entra…
    $this->actingAs(funcionario(['view_any_solicitud', 'create_solicitud', 'resolver_solicitud_arcop']));
    livewire(ListSolicitudes::class)->assertForbidden();

    // …y quien trae los del adoptante, sí.
    $this->actingAs(funcionario(['ver_arcop']));
    livewire(ListSolicitudes::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$solicitud]);
});

it('sin permiso de recepción no se abre el formulario', function () {
    $this->actingAs(funcionario(['view_any_solicitud']));

    livewire(CreateSolicitud::class)->assertForbidden();
});

// ─── Resolver ────────────────────────────────────────────────────────────────

it('tomar y resolver pasan por el módulo, no por un update del panel', function () {
    $solicitud = solicitudDePrueba(TipoDeSolicitud::Acceso, vecino());

    $this->actingAs($this->resolutora);

    livewire(ListSolicitudes::class)
        ->callAction(TestAction::make('tomar')->table($solicitud));

    expect($solicitud->refresh()->estado)->toBe(EstadoDeSolicitud::EnTramite);

    livewire(ListSolicitudes::class)
        ->callAction(TestAction::make('resolver')->table($solicitud), [
            'resultado' => EstadoDeSolicitud::Acogida->value,
            'fundamento' => 'Se entrega copia íntegra de sus datos.',
        ]);

    expect($solicitud->refresh()->estado)->toBe(EstadoDeSolicitud::Acogida)
        ->and($solicitud->fundamento_resolucion)->toBe('Se entrega copia íntegra de sus datos.')
        // Y quedó la evidencia: el panel no escribe la fila, la escribe el
        // módulo (que nombra la entrada con el estado con que se cerró).
        ->and(EntradaBitacora::where('evento', 'solicitud.'.EstadoDeSolicitud::Acogida->value)->count())->toBe(1);
});

it('resolver sin fundamento no resuelve nada y le llega la exigencia del módulo', function () {
    $solicitud = solicitudDePrueba(TipoDeSolicitud::Acceso, vecino());

    $this->actingAs($this->resolutora);

    livewire(ListSolicitudes::class)
        ->callAction(TestAction::make('resolver')->table($solicitud), [
            'resultado' => EstadoDeSolicitud::Acogida->value,
            'fundamento' => '   ',
        ]);

    expect($solicitud->refresh()->estado)->toBe(EstadoDeSolicitud::Recibida)
        ->and(avisos())->toContain('Toda resolución debe ir fundada');
});

// ─── El cese que el paquete NO puede garantizar ──────────────────────────────

it('no le promete al vecino un cese que el adoptante no declaró', function () {
    $solicitud = solicitudDePrueba(TipoDeSolicitud::Oposicion, vecino());

    $this->actingAs($this->resolutora);

    livewire(ListSolicitudes::class)
        ->callAction(TestAction::make('resolver')->table($solicitud), [
            'resultado' => EstadoDeSolicitud::Acogida->value,
            'fundamento' => 'Se acoge la oposición.',
        ]);

    // El paquete entrega la superficie para recibir y resolver; el candado que
    // hace cesar el tratamiento lo escribe cada sistema. Mientras no declare
    // QUÉ deja de hacer, el aviso no puede afirmar que algo cesó.
    expect(avisos())
        ->toContain('no declaró')
        ->not->toContain('deja de mandarle correos');
});

it('dice exactamente lo que el adoptante declaró que cesa', function () {
    $solicitud = solicitudDePrueba(TipoDeSolicitud::Oposicion, vecino());

    Filament::getPanel('arcop')->getPlugin('panel-arcop')
        ->alcanceDelCese('Para las finalidades bloqueadas el sistema deja de emitir el aviso de contribuciones.');

    $this->actingAs($this->resolutora);

    livewire(ListSolicitudes::class)
        ->callAction(TestAction::make('resolver')->table($solicitud), [
            'resultado' => EstadoDeSolicitud::Acogida->value,
            'fundamento' => 'Se acoge la oposición.',
        ]);

    expect(avisos())->toContain('deja de emitir el aviso de contribuciones');
});

// ─── Aislamiento entre sistemas ──────────────────────────────────────────────

it('no muestra las solicitudes de otro sistema del ecosistema', function () {
    $propia = solicitudDePrueba(TipoDeSolicitud::Acceso, vecino());

    $ajena = Solicitud::create([
        'sistema' => 'otro-municipio',
        'titular_type' => Vecino::class,
        'titular_id' => vecino()->id,
        'tipo' => TipoDeSolicitud::Acceso,
        'estado' => EstadoDeSolicitud::Recibida,
        'recibida_en' => now(),
        'vence_en' => now()->addDays(30),
        'detalle' => 'Solicitud de otro sistema.',
        'verificacion_identidad' => ['metodo' => 'padron_presencial', 'evidencia' => []],
        'solicitante' => 'titular',
    ]);

    $this->actingAs($this->resolutora);

    livewire(ListSolicitudes::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$propia])
        ->assertCanNotSeeTableRecords([$ajena]);
});

// ─── El documento de acreditación es un dato personal ────────────────────────

/**
 * El campo de la acreditación tal como lo arma el recurso.
 *
 * La página va sin montar: recorrer el árbol exige un componente Livewire
 * dueño del schema, pero no una petición. `withHidden: true` porque el campo
 * solo se muestra cuando no viene el propio titular, y evaluar esa condición
 * no es lo que se prueba acá.
 */
function campoAcreditacion(): FileUpload
{
    $schema = SolicitudResource::form(Schema::make(new CreateSolicitud));

    foreach ($schema->getFlatComponents(withHidden: true) as $componente) {
        if ($componente instanceof FileUpload && $componente->getName() === 'acreditacion_path') {
            return $componente;
        }
    }

    throw new LogicException('El formulario de recepción no tiene el campo acreditacion_path.');
}

it('no arma el formulario si el disco de la acreditación no está configurado', function () {
    // Es lo que hay en un adoptante sin PRIVACIDAD_DISCO_EVIDENCIA: la clave
    // existe (la fusiona muni-shared) pero vale null. Antes, `(string) null`
    // era '' y Filament caía en silencio a `filament.default_filesystem_disk`.
    config()->set('privacidad.disco_evidencia', null);

    expect(fn () => SolicitudResource::form(Schema::make()))
        ->toThrow(DiscoEvidenciaNoConfigurado::class, 'PRIVACIDAD_DISCO_EVIDENCIA');
});

it('tampoco acepta la variable presente pero vacía', function () {
    // `PRIVACIDAD_DISCO_EVIDENCIA=` en el .env da '' y no null: `??` no lo atrapa.
    config()->set('privacidad.disco_evidencia', '  ');

    expect(fn () => SolicitudResource::form(Schema::make()))
        ->toThrow(DiscoEvidenciaNoConfigurado::class);
});

it('guarda la acreditación en el disco declarado y como privada', function () {
    $campo = campoAcreditacion();

    expect($campo->getDiskName())->toBe('evidencia')
        // Explícito y no por defecto: es el segundo candado de Filament, el
        // que manda a `local` (y no a `public`) si el disco no resolviera.
        ->and($campo->getCustomVisibility())->toBe('private');
});

it('la acreditación no acepta SVG ni permite abrirlo en el navegador', function () {
    $campo = campoAcreditacion();

    // `image/*` incluía SVG: XML con scripts que, abierto en el origen del
    // panel, corre con la sesión y el MFA del funcionario ya superados.
    expect($campo->getAcceptedFileTypes())->toBe(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
        ->and($campo->getMaxSize())->toBe(10240)
        ->and($campo->isOpenable())->toBeFalse()
        ->and($campo->isDownloadable())->toBeTrue();
});
