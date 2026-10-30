<?php

declare(strict_types=1);

use Pacientes\Aplicacion\Comandos\ComandoRegistrar;
use Pacientes\Aplicacion\Resultados\Resultado;
use Pacientes\Dominio\Excepciones\ErrorDominio;
use Pacientes\Presentacion\Http\Controladores\PacientesControlador;
use Pacientes\Presentacion\Http\Formularios\EntradaInvalida;
use Pacientes\Presentacion\Http\Formularios\FormularioPaciente;
use Pacientes\Presentacion\Http\Identidad;
use Pacientes\Presentacion\Http\Solicitud;
use Pacientes\Presentacion\Http\Vistas\ErrorVista;
use Pacientes\Presentacion\Http\Vistas\PacienteVista;
use Pacientes\Pruebas\Dobles\Escenario;

$identidad = new Identidad(Escenario::ADMISION, Escenario::H_ORIENTE);
$solicitud = static fn (?array $cuerpo, array $cabeceras = []): Solicitud => new Solicitud('POST', '/api/v1/pacientes', $cabeceras, [], $cuerpo);

prueba('MVC-01', 'Formulario: valida solo el FORMATO (JSON, tipos, If-Match) y toma actor y hospital del token, no del cuerpo', function () use ($identidad, $solicitud): void {
    $tipo = static fn (callable $f): string => lanza(EntradaInvalida::class, $f, 'formato')->tipo;
    igual(EntradaInvalida::CUERPO, $tipo(fn () => FormularioPaciente::registro($solicitud(null), $identidad)), 'sin cuerpo');
    $e = lanza(EntradaInvalida::class, fn () => FormularioPaciente::registro($solicitud(['nombres' => 7, 'documento' => 'DPI']), $identidad), 'tipos');
    igual(['nombres', 'documento'], array_keys($e->campos), 'campos con tipo incorrecto');
    igual(EntradaInvalida::FORMATO, $tipo(fn () => FormularioPaciente::registro($solicitud(['confirmar_coincidencia' => 'sí']), $identidad)), 'booleano');
    igual(EntradaInvalida::VERSION_REQUERIDA, $tipo(fn () => FormularioPaciente::actualizacion($solicitud([]), $identidad, 'P-000001')), 'sin If-Match');
    igual(EntradaInvalida::VERSION_INVALIDA, $tipo(fn () => FormularioPaciente::actualizacion($solicitud([], ['If-Match' => 'abc']), $identidad, 'P-000001')), 'If-Match inválido');
    igual(3, FormularioPaciente::actualizacion($solicitud([], ['If-Match' => 'W/"3"']), $identidad, 'P-000001')->versionLeida, 'ETag débil');

    $comando = FormularioPaciente::registro($solicitud(Escenario::json(Escenario::datosAna(), ['hospital' => 'hosp-norte', 'actor' => 'otro'])), $identidad);
    afirmar($comando instanceof ComandoRegistrar, 'comando');
    igual([Escenario::ADMISION, Escenario::H_ORIENTE, 'DPI', Escenario::DPI_ANA],
        [$comando->actor, $comando->hospital, $comando->datos['tipo_documento'], $comando->datos['numero_documento']], 'datos del comando');
});

prueba('MVC-02', 'Vistas: ÚNICO lugar con códigos HTTP; ETag/Location en el éxito y categoría → código en el error', function (): void {
    $r = PacienteVista::registrado(Resultado::creado(['codigo' => 'P-000009', 'version' => 1, 'identidad_provisional' => false]));
    igual([201, '/api/v1/pacientes/P-000009', '"1"'], [$r->estado, $r->cabeceras['Location'], $r->cabeceras['ETag']], 'registrado');
    $esperado = [ErrorDominio::INVALIDO => 422, ErrorDominio::CONFLICTO => 409, ErrorDominio::NO_ENCONTRADO => 404, ErrorDominio::PROHIBIDO => 403];
    foreach ($esperado as $categoria => $http) {
        $e = ErrorVista::deResultado(Resultado::desdeError(new ErrorDominio($categoria, 'X', ['dato' => 1])));
        igual([$http, ['error' => 'X', 'dato' => 1]], [$e->estado, $e->cuerpo], $categoria);
    }
    igual(503, PacienteVista::lista(Resultado::noDisponible())->estado, 'no disponible');
    igual([400, 428], [ErrorVista::entradaInvalida(new EntradaInvalida(EntradaInvalida::CUERPO))->estado,
        ErrorVista::entradaInvalida(new EntradaInvalida(EntradaInvalida::VERSION_REQUERIDA))->estado], 'entrada');
});

prueba('MVC-03', 'El controlador funciona sin Kernel ni rutas: se prueba solo, con el Modelo real en memoria', function () use ($identidad, $solicitud): void {
    $e = Escenario::crear();
    $c = new PacientesControlador($e->casos['registrar'], $e->casos['buscar'], $e->casos['consultar'], $e->casos['actualizar']);
    igual(201, $c->registrar($solicitud(Escenario::json(Escenario::datosAna())), $identidad, [])->estado, 'registrar');
    igual('"1"', $c->consultar(new Solicitud('GET', '/'), $identidad, ['codigo' => 'P-000001'])->cabeceras['ETag'] ?? null, 'consultar');
    igual(428, $c->actualizar($solicitud(Escenario::json(Escenario::datosAna())), $identidad, ['codigo' => 'P-000001'])->estado, 'actualizar sin If-Match');
    igual(1, $c->buscar(new Solicitud('GET', '/', [], ['nombre' => 'lopez']), $identidad, [])->cuerpo['total'], 'buscar');
});

prueba('MVC-04', 'Formato válido pero regla rota llega al Dominio: 422 DATOS_INVALIDOS (no 400 del formulario)', function () use ($identidad, $solicitud): void {
    $e = Escenario::crear();
    $c = new PacientesControlador($e->casos['registrar'], $e->casos['buscar'], $e->casos['consultar'], $e->casos['actualizar']);
    $r = $c->registrar($solicitud(Escenario::json(Escenario::datosAna(['fecha_nacimiento' => '2030-01-01', 'nombres' => '']))), $identidad, []);
    igual([422, 'DATOS_INVALIDOS', ['nombres', 'nacimiento']], [$r->estado, $r->cuerpo['error'], array_keys($r->cuerpo['campos'])], 'regla del dominio');
});
