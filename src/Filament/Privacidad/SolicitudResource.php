<?php

namespace Muni\Ui\Filament\Privacidad;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use Muni\Shared\Privacidad\Contratos\PropagaSupresion;
use Muni\Shared\Privacidad\Contratos\TitularDeDatos;
use Muni\Shared\Privacidad\EstadoDeSolicitud;
use Muni\Shared\Privacidad\ExportacionDeDatos;
use Muni\Shared\Privacidad\Modelos\Solicitud;
use Muni\Shared\Privacidad\Rectificaciones;
use Muni\Shared\Privacidad\RectificacionNoPropagada;
use Muni\Shared\Privacidad\ResolucionInvalida;
use Muni\Shared\Privacidad\ResultadoDeSupresion;
use Muni\Shared\Privacidad\Solicitante;
use Muni\Shared\Privacidad\Solicitudes;
use Muni\Shared\Privacidad\Supresiones;
use Muni\Shared\Privacidad\SupresionNoProcede;
use Muni\Shared\Privacidad\SupresionNoPropagada;
use Muni\Shared\Privacidad\TipoDeSolicitud;
use Muni\Ui\Filament\Privacidad\Contratos\BuscaTitulares;
use Muni\Ui\Filament\Privacidad\SolicitudResource\Pages\CreateSolicitud;
use Muni\Ui\Filament\Privacidad\SolicitudResource\Pages\ListSolicitudes;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * El ciclo ARCOP (Ley 21.719) en el panel: listado, recepción de una solicitud
 * nueva y resolución. No hay edición ni borrado: una solicitud recibida hace
 * correr un plazo legal y no se retoca; lo único que le pasa es que avanza de
 * estado, y eso lo decide el módulo.
 *
 * NADA de esto escribe la fila desde acá. La recepción va por
 * {@see CreateSolicitud} → `Solicitudes::registrar()`; la resolución va por las
 * acciones de {@see self::table()} → `Solicitudes::tomar()/acoger()/
 * acogerParcialmente()/rechazar()`, `Rectificaciones::aplicar()`,
 * `Supresiones::aplicar()` y `ExportacionDeDatos::paraSolicitud()`. Ahí viven la verificación de
 * identidad, el plazo legal, el régimen de edad, la exigencia de fundamento,
 * la lista blanca de campos rectificables, la propagación al maestro, el
 * levantamiento del bloqueo y la bitácora. Un `Solicitud::update()` en el panel
 * se las saltaría todas.
 *
 * `privacidad_solicitudes` es una tabla COMPARTIDA por todo el ecosistema
 * (cada sistema adoptante escribe ahí con su propio `sistema`), así que
 * {@see self::getEloquentQuery()} filtra por el sistema de ESTE panel:
 * mostrar la solicitud de otro municipio/sistema no sería un bug cosmético,
 * sería una fuga de datos entre organismos.
 */
class SolicitudResource extends Resource
{
    protected static ?string $model = Solicitud::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Privacidad';

    protected static ?string $navigationLabel = 'Solicitudes ARCOP';

    protected static ?string $modelLabel = 'solicitud';

    protected static ?string $pluralModelLabel = 'Solicitudes ARCOP';

    protected static ?string $slug = 'solicitudes-arcop';

    /**
     * Solo el sistema de ESTE panel. Ver la solicitud de otro sistema del
     * ecosistema (o de otro municipio) no es cosmético: es aislamiento de
     * datos entre organismos, sobre una tabla que todos comparten.
     *
     * @return Builder<Solicitud>
     */
    public static function getEloquentQuery(): Builder
    {
        return Solicitud::query()
            ->where('sistema', (string) config('privacidad.sistema'))
            ->with('titular');
    }

    /**
     * Este recurso autoriza con overrides y NO con una policy, al revés que
     * todos los demás del repo, y conviene saber por qué antes de "corregirlo".
     *
     * `Solicitud` vive en `Muni\Shared\Privacidad\Modelos`, no en el
     * `App\Models` del adoptante, así que el autodescubrimiento de Laravel
     * —que empareja `App\Models\X` con `App\Policies\XPolicy`— no la
     * encuentra. Una `App\Policies\SolicitudPolicy` escrita siguiendo la
     * convención de un repo queda **inerte**: se llegó a escribir una en
     * discapacidad, se comprobó con
     * `Gate::getPolicyFor()` que Laravel no resolvía ninguna, y se borró. Un
     * archivo de autorización que no autoriza es peor que no tenerlo, porque el
     * siguiente que lo lea va a creer que la regla vive ahí.
     *
     * Si alguna vez se quiere volver a la policy, hay que registrarla explícita
     * (`Gate::policy(Solicitud::class, …)`) y recién ahí quitar estos overrides.
     *
     * El `?? false` no es defensivo por costumbre: sin sesión no hay a quién
     * atribuirle el acceso a las solicitudes ARCOP de los vecinos.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->can(self::plugin()->permisoVer()) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can(self::plugin()->permisoRecibir()) ?? false;
    }

    /**
     * Quién puede resolver. Permiso PROPIO, distinto del de recepción
     * (`create_solicitud`), para que el municipio pueda separar las dos
     * funciones desde la pantalla «Roles» sin tocar código.
     *
     * No es una prohibición de acumularlas: ver
     * {@see self::advertenciaSeparacionDeFunciones()}.
     */
    public static function canResolver(): bool
    {
        return auth()->user()?->can(self::plugin()->permisoResolver()) ?? false;
    }

    /** La configuración que declaró el sistema adoptante en su PanelProvider. */
    protected static function plugin(): PanelArcopPlugin
    {
        return PanelArcopPlugin::actual();
    }

    /**
     * El formulario de recepción del mesón.
     *
     * Nada de lo que se llena acá se escribe tal cual: {@see CreateSolicitud}
     * traduce este estado en una llamada a
     * `Muni\Shared\Privacidad\Solicitudes::registrar()`. Por eso hay campos que
     * NO son columnas de `privacidad_solicitudes` (`titular_id` sin su
     * `titular_type`, `run_cedula`): son los argumentos del servicio, no la fila.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Quién viene y por quién')
                ->description(self::plugin()->comoSeAcredita())
                ->columns(2)
                ->schema([
                    Select::make('titular_id')
                        ->label('Titular de los datos')
                        ->helperText(self::plugin()->buscador()->comoSeBusca())
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => self::buscarTitulares($search))
                        ->getOptionLabelUsing(fn (mixed $value): ?string => self::etiquetaDeTitular(
                            self::plugin()->buscador()->encontrar($value),
                        ))
                        ->columnSpanFull(),
                    // Lo que el solicitante presenta para acreditar su
                    // identidad. El panel no sabe qué es —cédula, padrón,
                    // licencia—: se lo entrega tal cual al VerificadorIdentidad
                    // que enchufó el adoptante, que es quien decide.
                    TextInput::make('credencial')
                        ->label(self::plugin()->etiquetaCredencial())
                        ->helperText(self::plugin()->ayudaCredencial())
                        ->required()
                        ->maxLength(50),
                    Select::make('solicitante')
                        ->label('Quién ejerce el derecho')
                        ->required()
                        ->live()
                        ->default(Solicitante::Titular->value)
                        ->options(collect(Solicitante::cases())
                            ->mapWithKeys(fn (Solicitante $quien): array => [$quien->value => $quien->etiqueta()])
                            ->all()),
                    // El módulo guarda la RUTA del documento, no la identidad del
                    // representante: esa vive en el papel, que es donde ya vive.
                    FileUpload::make('acreditacion_path')
                        ->label('Documento que acredita la representación')
                        ->helperText('Obligatorio cuando no viene el propio titular.')
                        ->disk((string) config('privacidad.disco_evidencia'))
                        ->directory('acreditaciones')
                        ->acceptedFileTypes(['application/pdf', 'image/*'])
                        ->openable()
                        ->downloadable()
                        ->visible(fn (Get $get): bool => self::exigeAcreditacion($get('solicitante')))
                        ->required(fn (Get $get): bool => self::exigeAcreditacion($get('solicitante')))
                        ->columnSpanFull(),
                ]),
            Section::make('Qué pide')
                ->columns(2)
                ->schema([
                    Select::make('tipo')
                        ->label('Derecho que ejerce')
                        ->required()
                        ->options(collect(TipoDeSolicitud::cases())
                            ->mapWithKeys(fn (TipoDeSolicitud $tipo): array => [$tipo->value => $tipo->etiqueta()])
                            ->all()),
                    Textarea::make('detalle')
                        ->label('Detalle que dicta el ciudadano')
                        ->required()
                        ->rows(4)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /**
     * Los titulares que calzan con lo que el funcionario tipeó.
     *
     * El paquete no busca: le pregunta al buscador que declaró el adoptante
     * ({@see BuscaTitulares}). Acá nació el acoplamiento más caro de este
     * recurso —una consulta a `personas.nro_documento_norm`, que solo existe en
     * discapacidad—, y por eso la búsqueda es un contrato y no una
     * configuración de columnas: qué es "buscar una persona" cambia de sistema
     * en sistema, y adivinarlo es lo que ataba el panel a un solo repo.
     *
     * Público porque es lo que un test puede ejercitar sin montar el selector.
     *
     * @return array<int|string, string>
     */
    public static function buscarTitulares(string $search): array
    {
        return self::plugin()->buscador()->buscar($search);
    }

    /**
     * Cómo se nombra a un titular en pantalla: por el contrato, nunca por
     * columnas del adoptante.
     *
     * El documento se muestra TAL COMO lo devuelve `titularDocumento()` y no
     * reformateado: hay registros con pasaporte y hay sistemas que no
     * identifican por RUT, y darle formato de RUT a eso los vuelve
     * irreconocibles en el buscador.
     */
    public static function etiquetaDeTitular(?TitularDeDatos $titular): ?string
    {
        return $titular === null
            ? null
            : $titular->titularNombre().' — '.$titular->titularDocumento();
    }

    /** Un tercero tiene que acompañar el documento; el titular solo acredita su identidad. */
    public static function exigeAcreditacion(mixed $solicitante): bool
    {
        return Solicitante::tryFrom((string) $solicitante)?->exigeAcreditarRepresentacion() ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            // Las que vencen antes, primero: es la urgencia real del mesón.
            ->defaultSort('vence_en')
            ->columns([
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (TipoDeSolicitud $state): string => $state->etiqueta())
                    ->color(fn (TipoDeSolicitud $state): string => match ($state) {
                        TipoDeSolicitud::Supresion, TipoDeSolicitud::Oposicion => 'danger',
                        TipoDeSolicitud::Rectificacion => 'warning',
                        TipoDeSolicitud::Acceso, TipoDeSolicitud::Portabilidad => 'gray',
                    }),
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (EstadoDeSolicitud $state): string => self::etiquetaEstado($state))
                    ->color(fn (EstadoDeSolicitud $state): string => match ($state) {
                        EstadoDeSolicitud::Recibida => 'gray',
                        EstadoDeSolicitud::EnTramite => 'info',
                        EstadoDeSolicitud::Acogida, EstadoDeSolicitud::AcogidaParcial => 'success',
                        EstadoDeSolicitud::Rechazada => 'danger',
                    }),
                TextColumn::make('recibida_en')
                    ->label('Recepción')
                    ->dateTime('d/m/Y')
                    ->sortable(),
                // El semáforo de plazo: lo único que hoy nadie puede ver.
                TextColumn::make('vence_en')
                    ->label('Vencimiento')
                    ->formatStateUsing(fn (Solicitud $record): string => self::etiquetaPlazo($record))
                    ->badge()
                    ->color(fn (Solicitud $record): string => self::colorPlazo($record))
                    ->sortable(),
                // El titular es un morph, y puede estar huérfano (anonimizado):
                // se resuelve por el contrato, sin asumir ningún modelo.
                TextColumn::make('titular')
                    ->label('Titular')
                    ->getStateUsing(fn (Solicitud $record): string => self::etiquetaTitular($record))
                    ->badge(fn (Solicitud $record): bool => self::estaAnonimizada($record))
                    ->color(fn (Solicitud $record): string => self::estaAnonimizada($record) ? 'gray' : 'primary'),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(collect(EstadoDeSolicitud::cases())
                        ->mapWithKeys(fn (EstadoDeSolicitud $estado): array => [$estado->value => self::etiquetaEstado($estado)])
                        ->all()),
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(collect(TipoDeSolicitud::cases())
                        ->mapWithKeys(fn (TipoDeSolicitud $tipo): array => [$tipo->value => $tipo->etiqueta()])
                        ->all()),
                // El plazo no es una columna: es el cruce de `vence_en` con el
                // estado, y son los scopes del módulo los que lo definen. Se
                // usan esos y no un `whereBetween` propio para que el filtro, el
                // semáforo de la columna y los conteos del widget de dashboard
                // ({@see SolicitudesArcopPorVencerWidget}) no puedan decir tres
                // cosas distintas sobre la misma solicitud.
                SelectFilter::make('plazo')
                    ->label('Plazo legal')
                    ->options([
                        'vencidas' => 'Vencidas',
                        'por_vencer' => 'Por vencer (5 días)',
                        'pendientes' => 'Sin resolver',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        /** @var Builder<Solicitud> $query los scopes viven en el modelo del módulo */
                        return match ($data['value'] ?? null) {
                            'vencidas' => $query->vencidas(),
                            'por_vencer' => $query->porVencer(),
                            'pendientes' => $query->pendientes(),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                self::accionTomar(),
                self::accionExportar(),
                self::accionRectificar(),
                self::accionSuprimir(),
                self::accionResolver(),
            ]);
    }

    // ── La resolución ────────────────────────────────────────────────────────
    //
    // Cuatro acciones, ninguna de las cuales toca la fila: cada una traduce el
    // modal en una llamada al servicio del módulo y traduce de vuelta su
    // negativa en un aviso que le sirva al funcionario.

    /** Pasar la solicitud a «en trámite». No resuelve nada. */
    private static function accionTomar(): Action
    {
        return Action::make('tomar')
            ->label('Tomar')
            ->icon('heroicon-o-hand-raised')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading('Tomar la solicitud')
            ->modalDescription('Queda «en trámite»: se ve que alguien la está atendiendo. No la resuelve.')
            ->modalSubmitActionLabel('Tomar')
            ->visible(fn (Solicitud $record): bool => self::canResolver()
                && $record->estado === EstadoDeSolicitud::Recibida)
            ->action(function (Solicitud $record): void {
                try {
                    app(Solicitudes::class)->tomar($record);
                } catch (ResolucionInvalida $e) {
                    self::avisarNegativa('La solicitud no se puede tomar', $e->getMessage());
                }

                Notification::make()->success()->title('Solicitud en trámite.')->send();
            });
    }

    /**
     * Sellar la solicitud: acogida, acogida parcial o rechazada.
     *
     * `fundamento` NO lleva `required()` a propósito, y no es un descuido: la
     * exigencia de fundar vive en `Solicitudes::resolver()`, y su mensaje —«Toda
     * resolución debe ir fundada: es lo que se le responde al titular»— es
     * exactamente lo que el funcionario necesita leer. Duplicar la regla en el
     * formulario la reemplazaría por un «campo obligatorio» genérico y dejaría
     * sin ejercitar la única guardia que de verdad manda.
     */
    private static function accionResolver(): Action
    {
        return Action::make('resolver')
            ->label('Resolver')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->modalHeading('Resolver la solicitud')
            ->modalDescription(fn (Solicitud $record): ?string => self::advertenciaSeparacionDeFunciones($record))
            ->modalSubmitActionLabel('Resolver')
            ->visible(fn (Solicitud $record): bool => self::canResolver()
                && ! $record->estado->estaResuelta())
            ->schema(fn (Solicitud $record): array => [
                Radio::make('resultado')
                    ->label('Cómo se resuelve')
                    ->required()
                    ->options(self::resultadosDisponibles($record))
                    ->helperText(self::notaDeResultados($record)),
                Textarea::make('fundamento')
                    ->label('Fundamento de la resolución')
                    ->helperText('Es literalmente lo que se le responde al titular. Va a la fila y a la bitácora.')
                    ->rows(4)
                    ->columnSpanFull(),
            ])
            ->action(function (Solicitud $record, array $data): void {
                $servicio = app(Solicitudes::class);
                $fundamento = (string) ($data['fundamento'] ?? '');
                $resultado = EstadoDeSolicitud::from((string) $data['resultado']);

                try {
                    match ($resultado) {
                        EstadoDeSolicitud::Acogida => $servicio->acoger($record, $fundamento),
                        EstadoDeSolicitud::AcogidaParcial => $servicio->acogerParcialmente($record, $fundamento),
                        EstadoDeSolicitud::Rechazada => $servicio->rechazar($record, $fundamento),
                        // Los dos estados no resolutivos no llegan acá: no están
                        // entre las opciones del radio, que además valida contra
                        // ellas. Se cubre igual para no depender de eso.
                        default => self::avisarNegativa(
                            'Ese no es un resultado con el que se cierre una solicitud',
                            'Una solicitud se cierra acogida, acogida parcialmente o rechazada.',
                        ),
                    };
                } catch (ResolucionInvalida $e) {
                    self::avisarNegativa('La solicitud no se pudo resolver', $e->getMessage());
                }

                Notification::make()
                    ->success()
                    ->title('Solicitud '.mb_strtolower(self::etiquetaEstado($resultado)).'.')
                    ->body(self::efectoSobreElBloqueo($record, $resultado))
                    ->persistent()
                    ->send();
            });
    }

    /**
     * Qué pasó con el bloqueo, que no es lo mismo según cómo se resolvió.
     *
     * El texto anterior —«Si había un bloqueo por esta solicitud, el módulo lo
     * levantó»— era falso exactamente en el caso que más importa: una oposición
     * ACOGIDA no levanta el bloqueo, lo vuelve definitivo
     * (`Solicitudes::resolver()`). Quien leyera ese aviso entendería que el
     * vecino quedó como antes, justo cuando se le dio la razón.
     */
    private static function efectoSobreElBloqueo(Solicitud $solicitud, EstadoDeSolicitud $resultado): string
    {
        $cesa = $resultado->esAcogida()
            && $solicitud->tipo === TipoDeSolicitud::Oposicion;

        if (! $cesa) {
            return 'Si había un bloqueo por esta solicitud, el módulo lo levantó.';
        }

        return 'El bloqueo queda DEFINITIVO: el tratamiento cesa. '.self::queCesaDeVerdad();
    }

    /**
     * Qué deja de hacer ESTE sistema cuando un bloqueo queda vigente.
     *
     * La frase es la que el funcionario le repite al vecino, así que la escribe
     * el adoptante ({@see PanelArcopPlugin::alcanceDelCese()}) y no el paquete:
     * el mapeo tratamiento→finalidad —qué pantalla, qué CSV, qué correo y qué
     * job dejan de tocar a esa persona— es propio de cada sistema, y este
     * paquete no lo conoce ni lo puede ejecutar.
     *
     * Que el default diga «no lo declaró» en vez de una frase tranquilizadora
     * es deliberado: recibir y resolver solicitudes es la SUPERFICIE del
     * cumplimiento, no el cumplimiento. Un sistema que herede el panel y no
     * escriba su candado le certificaría por escrito a un vecino un cese que no
     * ocurre — que es exactamente lo que pasaba en el sistema donde nació este
     * recurso hasta que se escribió el candado.
     */
    public static function queCesaDeVerdad(): string
    {
        return self::plugin()->textoDelCese();
    }

    /**
     * Aplicar la rectificación pedida.
     *
     * Va por `Rectificaciones::aplicar()` y no por `Solicitudes::acoger()`
     * porque acoger a secas sellaría la solicitud como corregida sin corregir
     * ni propagar nada. Por lo mismo, {@see self::resultadosDisponibles()} no
     * ofrece «acogida» a una rectificación desde {@see self::accionResolver()}.
     */
    private static function accionRectificar(): Action
    {
        return Action::make('rectificar')
            ->label('Rectificar')
            ->icon('heroicon-o-pencil-square')
            ->color('warning')
            ->modalHeading('Aplicar la rectificación y acoger')
            ->modalDescription(fn (Solicitud $record): ?string => self::advertenciaSeparacionDeFunciones($record))
            ->modalSubmitActionLabel('Rectificar y acoger')
            ->visible(fn (Solicitud $record): bool => self::canResolver()
                && $record->tipo === TipoDeSolicitud::Rectificacion
                && ! $record->estado->estaResuelta()
                && $record->titular instanceof TitularDeDatos)
            ->schema(fn (Solicitud $record): array => self::modalDeRectificacion($record))
            ->action(function (Solicitud $record, array $data): void {
                try {
                    app(Rectificaciones::class)->aplicar(
                        $record,
                        self::cambiosPedidos($record, $data),
                        (string) ($data['fundamento'] ?? ''),
                    );
                } catch (ResolucionInvalida $e) {
                    self::avisarNegativa('La rectificación no se puede acoger así', $e->getMessage());
                } catch (RectificacionNoPropagada $e) {
                    self::avisarNegativa('La rectificación no se propagó al maestro de personas', $e->getMessage());
                } catch (RequestException $e) {
                    // El transporte del ecosistema hace `$resp->throw()`, así que
                    // el rechazo del maestro llega casi siempre por acá y no como
                    // RectificacionNoPropagada. Se dice lo que se sabe —el maestro
                    // contestó y no aceptó— sin volcarle al funcionario el cuerpo
                    // de una respuesta HTTP.
                    self::avisarNegativa(
                        'El maestro de personas no aceptó la rectificación',
                        'El registro local quedó como estaba y la solicitud sigue en trámite: el municipio no puede '
                        .'certificar una corrección que el maestro no aceptó. Reintentar; si insiste, avisar a informática.',
                    );
                } catch (Throwable) {
                    // Cualquier otra falla de la transacción (el maestro
                    // inalcanzable, un choque de constraint). NO se afirma que el
                    // maestro haya rechazado nada, porque desde acá no se sabe.
                    self::avisarNegativa(
                        'La rectificación no se pudo aplicar',
                        'No se cambió ningún dato y la solicitud sigue en trámite. Reintentar; si insiste, avisar a informática.',
                    );
                }

                Notification::make()
                    ->success()
                    ->title('Rectificación aplicada y solicitud acogida.')
                    ->body('El cambio quedó propagado al maestro de personas.')
                    ->send();
            });
    }

    /**
     * Ejercer el derecho de supresión: suprimir hasta donde la ley deja, y
     * recién ahí resolver.
     *
     * Va por `Supresiones::aplicar()` y no por `Solicitudes::acoger()` por lo
     * mismo que la rectificación, y con más consecuencias: acoger a secas
     * sellaba la solicitud como cumplida y no borraba nada. El vecino ejercía su
     * derecho, el municipio le certificaba por escrito que lo había cumplido, y
     * sus datos seguían enteros — el plazo legal dejaba de correr y nadie volvía
     * a mirar el caso. Por eso {@see self::resultadosDisponibles()} tampoco
     * ofrece «acogida» ni «acogida parcial» a una supresión.
     *
     * Los tres desenlaces del módulo se reflejan tal cual, incluido el tercero:
     * cuando el RAT obliga a conservar en TODAS las finalidades, `aplicar()`
     * truena con `SupresionNoProcede` y deja la solicitud EN TRÁMITE. Este panel
     * no la convierte en un rechazo: muestra la norma y el plazo que impiden, y
     * el funcionario decide. Rechazar es una resolución fundada que le responde
     * a un ciudadano y que alguien firma; escribirla desde acá sería inventar el
     * fundamento de un acto administrativo.
     */
    private static function accionSuprimir(): Action
    {
        return Action::make('suprimir')
            ->label('Suprimir')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->modalHeading('Aplicar la supresión y resolver')
            ->modalDescription(fn (Solicitud $record): string => self::antesDeSuprimir($record))
            ->modalSubmitActionLabel('Suprimir y resolver')
            ->visible(fn (Solicitud $record): bool => self::canResolver()
                && $record->tipo === TipoDeSolicitud::Supresion
                && ! $record->estado->estaResuelta()
                && $record->titular instanceof TitularDeDatos)
            ->schema([
                Textarea::make('fundamento')
                    ->label('Fundamento de la resolución')
                    // Sin required(), igual que en «Resolver»: la exigencia vive
                    // en el módulo y su mensaje es el que le sirve al funcionario.
                    ->helperText('Es lo que se le responde al titular. El módulo no suprime sin él.')
                    ->rows(4)
                    ->columnSpanFull(),
            ])
            ->action(function (Solicitud $record, array $data): void {
                try {
                    $resultado = app(Supresiones::class)->aplicar($record, (string) ($data['fundamento'] ?? ''));
                } catch (SupresionNoProcede $e) {
                    // El mensaje del módulo trae la norma y el plazo: es la
                    // negativa fundada que hay que poder citarle al titular.
                    self::avisarNegativa(
                        'Todavía no se puede suprimir',
                        $e->getMessage().' La solicitud queda EN TRÁMITE y no se destruyó nada. Si corresponde '
                        .'rechazarla, hazlo desde «Resolver» con tu propio fundamento: el sistema no lo escribe por ti.',
                    );
                } catch (ResolucionInvalida $e) {
                    self::avisarNegativa('La supresión no se puede aplicar así', $e->getMessage());
                } catch (SupresionNoPropagada $e) {
                    self::avisarNegativa('La supresión no se propagó al maestro de personas', $e->getMessage());
                } catch (RequestException) {
                    // Igual que en la rectificación, el transporte hace
                    // `$resp->throw()` y el rechazo del maestro llega por acá.
                    // La baja al maestro va ANTES de todo lo destructivo, así
                    // que en este punto lo local sigue intacto.
                    self::avisarNegativa(
                        'El maestro de personas no aceptó la baja',
                        'No se destruyó ningún dato local y la solicitud sigue en trámite: suprimir solo acá dejaría '
                        .'al titular vivo y consultable por RUT en el registro federado mientras se le certifica lo '
                        .'contrario. Reintentar; si insiste, avisar a informática.',
                    );
                } catch (Throwable) {
                    // Cualquier otra falla. NO se afirma que no haya quedado
                    // nada a medias: la baja en el maestro y el borrado de un
                    // documento del disco ocurren antes de sellar la resolución
                    // y no los deshace ningún rollback.
                    self::avisarNegativa(
                        'La supresión no se pudo aplicar',
                        'La solicitud sigue en trámite y el módulo revirtió lo que escribió en la base. Antes de '
                        .'reintentar, comprobar en qué quedó el caso: la baja en el maestro de personas y el borrado '
                        .'de un documento del disco no se deshacen con la transacción.',
                    );
                }

                self::avisarSupresion($resultado);
            });
    }

    /**
     * Lo que el funcionario ve en el modal ANTES de suprimir: hasta dónde llega
     * el derecho de este titular según el RAT.
     *
     * `Supresiones::evaluar()` no escribe nada —existe justamente para poder
     * mostrar esto antes de resolver— y su explicación cita la norma y el plazo,
     * que es lo que el funcionario tiene que copiar en el fundamento. El costo
     * es que la evaluación corre dos veces, una acá y otra dentro de
     * `aplicar()`: recorre los vencidos de cada finalidad del sistema.
     */
    public static function previaDeSupresion(Solicitud $solicitud): ?string
    {
        $titular = $solicitud->titular;

        if (! $titular instanceof TitularDeDatos) {
            return null;
        }

        return app(Supresiones::class)->evaluar($titular)->explicacion();
    }

    /** La previa, junto con el aviso de separación de funciones si corresponde. */
    private static function antesDeSuprimir(Solicitud $solicitud): string
    {
        return trim(implode("\n\n", array_filter([
            self::advertenciaSeparacionDeFunciones($solicitud),
            self::previaDeSupresion($solicitud),
        ])));
    }

    /**
     * Qué pasó realmente, que es lo que hay que poder decirle al titular.
     *
     * Los dos desenlaces se cuentan distinto a propósito: una acogida parcial NO
     * borró nada, y anunciarla con el mismo «listo» que la supresión total sería
     * la misma confusión que esta acción existe para cerrar.
     *
     * Y dentro de la total hay una segunda distinción, que es la que este aviso
     * no sabía hacer: destruir el dato local no es lo mismo que sacar al vecino
     * del ecosistema. El módulo entrega en `ResultadoDeSupresion::$propagacion`
     * qué contestó el maestro de personas, y hasta que eso diga «aceptada» el
     * panel no tiene con qué afirmar que la identidad dejó de servirse por RUT a
     * los otros siete sistemas.
     */
    private static function avisarSupresion(ResultadoDeSupresion $resultado): void
    {
        if ($resultado->total) {
            // El barrido viene solo en la supresión total; se lee con `??` para
            // no depender de eso.
            $suprimidos = $resultado->barrido->archivosSuprimidos ?? 0;
            $noEncontrados = $resultado->barrido->archivosNoEncontrados ?? 0;

            $cuerpo = 'El registro quedó anonimizado, se purgaron sus datos sensibles y el módulo borró '
                .$suprimidos.' documento(s) del disco. La solicitud queda acogida.';

            if ($noEncontrados > 0) {
                // No es cosmético: una ruta sin archivo en el disco declarado
                // puede significar que el documento sigue vivo en OTRO disco, y
                // el módulo ya no tiene con qué encontrarlo.
                $cuerpo .= ' Ojo: '.$noEncontrados.' ruta(s) no tenían archivo donde el módulo buscó. Avisar a '
                    .'informática para descartar que el documento haya quedado en otro disco.';
            }

            $propagado = $resultado->propagacion?->loAceptoElMaestro() ?? false;

            if (! $propagado) {
                // Título distinto y no solo un párrafo más: es la frase que el
                // funcionario le repite al vecino, y «Supresión aplicada.» a
                // secas afirma del registro federado algo que nadie comprobó.
                Notification::make()
                    ->warning()
                    ->title('Supresión aplicada solo en este sistema.')
                    ->body($cuerpo.' Nadie habló con el maestro de personas: '
                        .self::motivoDeNoPropagacion($resultado)
                        .' NO se le puede decir al titular que sus datos ya no están en el ecosistema: '
                        .'su identidad puede seguir viva y consultable por RUT en el registro federado, o sea '
                        .'disponible para los otros sistemas municipales. Avisar a informática para completar la baja allá.')
                    ->persistent()
                    ->send();

                return;
            }

            Notification::make()
                ->success()
                ->title('Supresión aplicada.')
                ->body($cuerpo.' El maestro de personas aceptó la baja: la identidad deja de servirse por RUT '
                    .'al resto del ecosistema.')
                ->persistent()
                ->send();

            return;
        }

        // La acogida parcial no destruyó nada, así que no hubo ninguna baja que
        // propagar y `$propagacion` viene en null por diseño del módulo: acá no
        // corresponde hablar del maestro.
        $normas = collect($resultado->evaluacion->normasQueImpiden())
            ->map(fn (string $norma, string $codigo): string => "{$codigo} ({$norma})")
            ->implode('; ');

        Notification::make()
            ->warning()
            ->title('Supresión acogida parcialmente: no se borró ningún dato.')
            ->body('Cesa el tratamiento para: '.implode(', ', $resultado->evaluacion->codigosQueCesan())
                .'. Se conserva por norma: '.$normas.'. El cese queda como bloqueo definitivo. '
                .self::queCesaDeVerdad())
            ->persistent()
            ->send();
    }

    /**
     * Por qué no se propagó, en las palabras del propagador.
     *
     * El motivo lo escribe la implementación de {@see PropagaSupresion} que
     * enchufó el sistema adoptante. En discapacidad esa implementación lo
     * redacta en términos de configuración y nunca del titular, porque la misma
     * cadena termina en `privacidad_bitacora.datos`, que no está cifrada. El
     * módulo no comprueba eso —lo dice en sus límites declarados—, este método
     * tampoco (solo lo muestra) y el paquete no puede comprobárselo a nadie:
     * quien escriba su propio propagador tiene que cuidarlo.
     *
     * El `null` no debería ocurrir en una supresión total (el contrato exige
     * motivo en `noCorrespondia()` y el rechazo aborta antes de llegar acá),
     * pero se contempla para no dejar al funcionario con una frase cortada.
     */
    private static function motivoDeNoPropagacion(ResultadoDeSupresion $resultado): string
    {
        // `->` y no `?->`: el `??` ya cubre el caso de `propagacion` en null
        // (PHP no avisa al leer una propiedad de null a la izquierda de `??`).
        $motivo = trim((string) ($resultado->propagacion->motivo ?? ''));

        if ($motivo === '') {
            return 'el módulo no dejó escrito por qué.';
        }

        return rtrim($motivo, '.').'.';
    }

    /**
     * Entregar el expediente del titular (acceso y portabilidad).
     *
     * Va por `ExportacionDeDatos::paraSolicitud()` —no por `paraTitular()`—
     * porque es la entrada que exige una solicitud con identidad verificada y
     * deja la entrada `datos.exportados` en la bitácora. Tomar un id del request
     * y llamar a `paraTitular()` sería un IDOR sin rastro.
     *
     * No sella la solicitud: entregar la copia y responder por escrito son dos
     * cosas, y el fundamento de la resolución se escribe en «Resolver».
     */
    private static function accionExportar(): Action
    {
        return Action::make('exportar')
            ->label('Descargar el expediente')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            // Oculta en una solicitud RECHAZADA: entregar la copia de todos modos
            // sería la comunicación de datos que la resolución acaba de negar.
            ->visible(fn (Solicitud $record): bool => self::canResolver()
                && in_array($record->tipo, [TipoDeSolicitud::Acceso, TipoDeSolicitud::Portabilidad], true)
                && $record->estado !== EstadoDeSolicitud::Rechazada
                && $record->titular instanceof TitularDeDatos)
            ->action(fn (Solicitud $record): StreamedResponse => self::expediente($record));
    }

    /**
     * El expediente del titular como JSON descargable.
     *
     * Público porque la acción es privada y esto es lo que un test puede
     * ejercitar sin montar el modal.
     */
    public static function expediente(Solicitud $solicitud): StreamedResponse
    {
        try {
            $datos = app(ExportacionDeDatos::class)->paraSolicitud($solicitud);
        } catch (ResolucionInvalida $e) {
            self::avisarNegativa('No se puede entregar el expediente', $e->getMessage());
        }

        $json = json_encode(
            $datos,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        return response()->streamDownload(
            function () use ($json): void {
                echo $json;
            },
            'expediente-solicitud-'.$solicitud->getKey().'.json',
            ['Content-Type' => 'application/json'],
        );
    }

    /**
     * Con qué resultados se puede cerrar esta solicitud.
     *
     * Una RECTIFICACIÓN solo se puede rechazar desde acá: acogerla es aplicar
     * el cambio, y eso pasa por {@see self::accionRectificar()}. Si se pudiera
     * «acoger» sin más, la solicitud quedaría sellada como corregida con el
     * dato intacto y el maestro sin enterarse — el registro diría que el
     * municipio corrigió algo que sigue igual.
     *
     * Una SUPRESIÓN, por lo mismo y peor: acogerla es suprimir, y eso pasa por
     * {@see self::accionSuprimir()}. Acá se le quitan las DOS acogidas, no solo
     * la total: «acogida parcial» a mano sellaría un cese de tratamiento sin
     * dejar ni un bloqueo, o sea nada más que el papel.
     *
     * @return array<string, string>
     */
    public static function resultadosDisponibles(Solicitud $solicitud): array
    {
        $resultados = match ($solicitud->tipo) {
            TipoDeSolicitud::Rectificacion, TipoDeSolicitud::Supresion => [EstadoDeSolicitud::Rechazada],
            default => [EstadoDeSolicitud::Acogida, EstadoDeSolicitud::AcogidaParcial, EstadoDeSolicitud::Rechazada],
        };

        return collect($resultados)
            ->mapWithKeys(fn (EstadoDeSolicitud $estado): array => [$estado->value => self::etiquetaEstado($estado)])
            ->all();
    }

    private static function notaDeResultados(Solicitud $solicitud): ?string
    {
        return match ($solicitud->tipo) {
            TipoDeSolicitud::Rectificacion => 'Para acogerla, usa «Rectificar»: acoger sin corregir dejaría el dato como está.',
            TipoDeSolicitud::Supresion => 'Para acogerla, usa «Suprimir»: acoger sin suprimir sellaría un borrado que no ocurrió. '
                .'El rechazo sí se resuelve acá, con tu propio fundamento.',
            default => null,
        };
    }

    /**
     * El modal de rectificación: un campo por cada dato que el titular puede
     * corregir de su propio registro, precargado con lo que hay hoy.
     *
     * La lista la manda el titular (`camposRectificables()`), no este panel: es
     * el modelo el que sabe qué es rectificable y qué no (el RUT no lo es —eso
     * lo acredita el Registro Civil— ni los datos de discapacidad, que dependen
     * de un certificado médico).
     *
     * @return array<int, mixed>
     */
    private static function modalDeRectificacion(Solicitud $solicitud): array
    {
        $titular = $solicitud->titular;
        $campos = $titular instanceof TitularDeDatos ? $titular->camposRectificables() : [];

        return [
            Section::make('Datos que el titular puede corregir')
                ->description('Deja igual lo que esté bien: se envía al maestro únicamente lo que cambies.')
                ->columns(2)
                ->schema(array_map(
                    fn (string $campo): TextInput => TextInput::make("valores.{$campo}")
                        ->label(Str::headline($campo))
                        ->default(self::valorActual($titular, $campo))
                        ->maxLength(255),
                    $campos,
                )),
            Textarea::make('fundamento')
                ->label('Fundamento de la resolución')
                ->helperText('Es lo que se le responde al titular. El módulo no acoge una rectificación sin él.')
                ->rows(4)
                ->columnSpanFull(),
        ];
    }

    /**
     * Los cambios que el funcionario efectivamente pidió: lo que llegó del
     * modal y difiere de lo que hay guardado.
     *
     * Se recorre lo que trae el formulario y NO `camposRectificables()`: filtrar
     * acá contra la lista blanca la duplicaría en el panel y dejaría sin
     * ejercitar la del módulo, que es la que manda. Lo que este método garantiza
     * es que no se manden campos sin cambio (acoger una lista vacía certifica
     * una corrección que no se hizo, y por eso el módulo la rechaza); qué campos
     * son lícitos lo decide `Rectificaciones::aplicar()`.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function cambiosPedidos(Solicitud $solicitud, array $data): array
    {
        $titular = $solicitud->titular;

        if (! $titular instanceof TitularDeDatos) {
            return [];
        }

        /** @var array<string, mixed> $valores */
        $valores = (array) ($data['valores'] ?? []);
        $cambios = [];

        foreach ($valores as $campo => $nuevo) {
            $nuevo = is_string($nuevo) ? trim($nuevo) : $nuevo;

            if ((string) $nuevo === self::valorActual($titular, (string) $campo)) {
                continue;
            }

            $cambios[(string) $campo] = $nuevo === '' ? null : $nuevo;
        }

        return $cambios;
    }

    /** El valor guardado hoy, como texto, para precargar el modal y comparar. */
    private static function valorActual(mixed $titular, string $campo): string
    {
        if (! $titular instanceof Model) {
            return '';
        }

        $valor = $titular->getAttribute($campo);

        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d');
        }

        return is_scalar($valor) ? (string) $valor : '';
    }

    /**
     * Aviso —no prohibición— cuando quien va a resolver es quien recibió.
     *
     * El módulo permite que sean la misma persona y este panel tampoco lo
     * impide: en un municipio chico el mismo funcionario atiende el mesón y
     * resuelve, y eso lo decide el municipio, no este código. Lo que sí hace es
     * que la coincidencia se vea en el momento de resolver, y que los dos
     * permisos (`create_solicitud` y `resolver_solicitud_arcop`) sean separables
     * para el municipio que quiera separarlos.
     */
    public static function advertenciaSeparacionDeFunciones(Solicitud $solicitud): ?string
    {
        $registro = $solicitud->getAttribute('user_registro_id');

        if ($registro === null || $registro !== auth()->id()) {
            return null;
        }

        return 'Esta solicitud la recibiste tú. Resolverla tú mismo concentra la recepción y la resolución en '
            .'una sola persona: si hay alguien más que pueda resolverla, mejor que la resuelva esa persona.';
    }

    /**
     * Traduce una negativa del módulo en un aviso accionable y detiene la
     * acción.
     *
     * El cuerpo es el mensaje del módulo TAL CUAL cuando lo hay: en una
     * resolución ese texto es lo que se le responde al titular, y cambiarlo por
     * un «no se pudo» genérico dejaría al funcionario sin lo único que le sirve.
     *
     * @throws Halt
     */
    private static function avisarNegativa(string $titulo, string $mensaje): never
    {
        Notification::make()
            ->danger()
            ->title($titulo)
            ->body($mensaje)
            ->persistent()
            ->send();

        // Que no quede nada a medias NO lo sostiene este rollback: ninguna de
        // estas acciones llama a `Action::databaseTransaction()`, así que hoy
        // `rollBackDatabaseTransaction()` es literalmente un no-op (ver
        // `Filament\Actions\Concerns\CanUseDatabaseTransactions`). Lo sostienen
        // los servicios del módulo, que lanzan antes de escribir o revierten su
        // propia transacción. Los tests lo verifican leyendo las filas.
        throw (new Halt)->rollBackDatabaseTransaction();
    }

    public static function etiquetaEstado(EstadoDeSolicitud $estado): string
    {
        return match ($estado) {
            EstadoDeSolicitud::Recibida => 'Recibida',
            EstadoDeSolicitud::EnTramite => 'En trámite',
            EstadoDeSolicitud::Acogida => 'Acogida',
            EstadoDeSolicitud::AcogidaParcial => 'Acogida parcial',
            EstadoDeSolicitud::Rechazada => 'Rechazada',
        };
    }

    /**
     * El semáforo de plazo. Una solicitud ya resuelta no tiene "vencida" ni
     * "por vencer": el plazo dejó de importar el día que se cerró el caso.
     */
    public static function etiquetaPlazo(Solicitud $solicitud): string
    {
        if ($solicitud->estado->estaResuelta()) {
            return 'Resuelta';
        }

        $dias = $solicitud->diasRestantes();

        if ($dias < 0) {
            return 'Vencida';
        }

        // Mismo umbral que Solicitud::scopePorVencer().
        if ($dias <= 5) {
            return 'Por vencer';
        }

        return 'En plazo';
    }

    public static function colorPlazo(Solicitud $solicitud): string
    {
        return match (self::etiquetaPlazo($solicitud)) {
            'Resuelta' => 'gray',
            'Vencida' => 'danger',
            'Por vencer' => 'warning',
            default => 'success',
        };
    }

    /**
     * `titular_id` no está en el PHPDoc del modelo compartido (por eso
     * `getAttribute()` y no la propiedad mágica: PHPStan no puede ver una
     * columna que el paquete no declaró, y este código no puede tocar
     * vendor/ para agregarla).
     */
    public static function estaAnonimizada(Solicitud $solicitud): bool
    {
        return $solicitud->getAttribute('titular_id') === null;
    }

    /**
     * El titular es un morph —cada sistema adoptante declara el suyo— y puede
     * estar huérfano: la anonimización por
     * retención anula `titular_id` a propósito. Ese caso se muestra como lo
     * que es, no como una fila rota.
     */
    public static function etiquetaTitular(Solicitud $solicitud): string
    {
        if (self::estaAnonimizada($solicitud)) {
            return 'Caso anonimizado';
        }

        $titular = $solicitud->titular;

        if ($titular === null) {
            // titular_id existe pero el registro relacionado ya no: huérfano
            // sin haber pasado por la anonimización del módulo.
            return 'Titular no disponible';
        }

        if ($titular instanceof TitularDeDatos) {
            return $titular->titularNombre().' ('.$titular->titularDocumento().')';
        }

        return class_basename($titular).' #'.$titular->getKey();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSolicitudes::route('/'),
            'create' => CreateSolicitud::route('/create'),
        ];
    }
}
