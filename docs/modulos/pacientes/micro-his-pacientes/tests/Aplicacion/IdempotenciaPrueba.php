<?php

declare(strict_types=1);

use Pacientes\Aplicacion\Comandos\ComandoRegistrar;
use Pacientes\Aplicacion\Excepciones\ClaveIdempotenciaEnUso;
use Pacientes\Aplicacion\Puertos\AlmacenIdempotencia;
use Pacientes\Dominio\Modelo\EventoAuditoria;
use Pacientes\Persistencia\ConjuntoAdaptadores;
use Pacientes\Persistencia\Configuracion\PoliticaAccesoEstatica;
use Pacientes\Persistencia\FabricaEnMemoria;
use Pacientes\Pruebas\Dobles\Escenario;
use Pacientes\Pruebas\Dobles\RelojFijo;

$desconocido = ['nombres' => 'Juan', 'apellidos' => 'Desconocido', 'fecha_nacimiento' => '2000-01-01', 'sexo' => 'male'];
$post = static fn (object $e, array $datos, array $cabeceras = [], string $token = 'token-admision-oriente') =>
    $e->kernel->manejar(Escenario::http('POST', '/api/v1/pacientes', $token, Escenario::json($datos), cabeceras: $cabeceras));

prueba('IDEM-01', 'Misma Idempotency-Key y mismo cuerpo: misma respuesta 201, Idempotent-Replayed, un paciente y un evento', function () use ($post): void {
    $e = Escenario::crear();
    $primera = $post($e, Escenario::datosAna(), ['Idempotency-Key' => 'registro-ana-0001']);
    $segunda = $post($e, Escenario::datosAna(), ['Idempotency-Key' => 'registro-ana-0001']);
    igual([201, 201], [$primera->estado, $segunda->estado], 'estados');
    igual($primera->cuerpo, $segunda->cuerpo, 'mismo cuerpo');
    igual([null, 'true'], [$primera->cabeceras['Idempotent-Replayed'] ?? null, $segunda->cabeceras['Idempotent-Replayed'] ?? null], 'marca de repetición');
    igual(1, count($e->repositorio->todos()), 'pacientes');
    igual(1, count($e->bitacora->eventos(EventoAuditoria::REGISTRADO)), 'eventos');
});

prueba('IDEM-02', 'Misma clave con otro cuerpo → 422 IDEMPOTENCY_KEY_REUTILIZADA; clave mal formada → 400', function () use ($post, $desconocido): void {
    $e = Escenario::crear();
    $post($e, Escenario::datosAna(), ['Idempotency-Key' => 'registro-ana-0001']);
    $otra = $post($e, $desconocido, ['Idempotency-Key' => 'registro-ana-0001']);
    igual([422, 'IDEMPOTENCY_KEY_REUTILIZADA'], [$otra->estado, $otra->cuerpo['error'] ?? null], 'reutilizada');
    igual(1, count($e->repositorio->todos()), 'no se registró el segundo');
    igual(400, $post($e, $desconocido, ['Idempotency-Key' => 'corta'])->estado, 'clave inválida');
});

prueba('IDEM-03', 'El problema que resuelve: sin clave, el reintento de un registro que SÍ se hizo responde 409; con clave, 201 con el código', function () use ($post, $desconocido): void {
    $e = Escenario::crear();
    $post($e, $desconocido);
    $reintento = $post($e, $desconocido); // la respuesta de la primera se perdió
    // R-03 evita el duplicado, pero el cliente recibe un error por algo que funcionó y no sabe el código.
    igual([409, 'POSIBLE_DUPLICADO', 1], [$reintento->estado, $reintento->cuerpo['error'] ?? null, count($e->repositorio->todos())], 'sin clave');
    // Si un supervisor «confirma que es otra persona» ante ese 409, el duplicado sí se crea.
    $confirmado = $post($e, $desconocido, [], 'token-supervisor-oriente');
    igual(409, $confirmado->estado, 'sin confirmación explícita sigue bloqueado');

    $e = Escenario::crear();
    $primera = $post($e, $desconocido, ['Idempotency-Key' => 'urgencias-0000001']);
    $reintento = $post($e, $desconocido, ['Idempotency-Key' => 'urgencias-0000001']);
    igual([201, $primera->cuerpo['codigo'], 1], [$reintento->estado, $reintento->cuerpo['codigo'] ?? null, count($e->repositorio->todos())], 'con clave');
});

prueba('IDEM-04', 'Dos peticiones simultáneas con la misma clave: la segunda recibe 409 SOLICITUD_EN_CURSO y no deja paciente', function () use ($desconocido): void {
    $base = FabricaEnMemoria::crear();
    $carrera = new class implements AlmacenIdempotencia {
        public function buscar(string $hospital, string $actor, string $clave): ?array
        {
            return null; // la otra petición aún no había confirmado
        }

        public function guardar(string $hospital, string $actor, string $clave, string $huella, array $datos): void
        {
            throw new ClaveIdempotenciaEnUso(); // y confirmó primero
        }
    };
    $a = new ConjuntoAdaptadores($base->consulta, $base->repositorio, $base->indice, $base->auditoria, $base->transaccion, $carrera);
    $casos = construirCasosDeUso($a, new PoliticaAccesoEstatica(Escenario::config()['permisos']), new RelojFijo());
    $r = $casos['registrar']->ejecutar(new ComandoRegistrar(Escenario::ADMISION, Escenario::H_ORIENTE, $desconocido, claveIdempotencia: 'urgencias-0000001'));
    igual(['conflicto', 'SOLICITUD_EN_CURSO'], [$r->estado, $r->error], 'resultado');
    igual([], $base->repositorio->todos(), 'el paciente se revirtió');
});
