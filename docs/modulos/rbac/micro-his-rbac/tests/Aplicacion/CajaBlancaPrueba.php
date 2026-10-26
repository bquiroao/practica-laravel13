<?php

declare(strict_types=1);

/*
 * Semana 15 · Pruebas de CAJA BLANCA: diseñadas leyendo el código del flujo «asignación de rol, autorización
 * de una operación y denegación trazable». Cubren caminos básicos (McCabe), ramas y condiciones (MC/DC).
 * La cobertura de líneas que logran se mide con phpdbg: docs/modulos/rbac/semana-15/herramientas/cobertura-caja-blanca.php
 */

use Rbac\Dominio\Modelo\ContextoAutorizacion;
use Rbac\Dominio\Modelo\Membresia;
use Rbac\Dominio\Modelo\SolicitudAutorizacion;
use Rbac\Dominio\Reglas\ReglaAutorizacion;
use Rbac\Dominio\Servicios\PoliticaAutorizacion;
use Rbac\Persistencia\ConjuntoAdaptadores;
use Rbac\Persistencia\EnMemoria\FabricaEnMemoria;
use Rbac\Persistencia\EnMemoria\RegistroAuditoriaEnMemoria;
use Rbac\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Rbac\Presentacion\Http\Kernel;
use Rbac\Presentacion\Http\MiddlewareAutorizacion;
use Rbac\Presentacion\Http\Respuesta;
use Rbac\Presentacion\Http\Ruta;
use Rbac\Pruebas\Dobles\Escenario;
use Rbac\Pruebas\Dobles\RegistroAuditoriaConFallas;
use Rbac\Pruebas\Dobles\RepositorioMembresiasConFallas;

/**
 * Arma AutorizarOperacion con interruptores para cada rama de AutorizarOperacion::ejecutar()/denegar().
 * @return array{0: \Rbac\Aplicacion\CasosDeUso\AutorizarOperacion, 1: RegistroAuditoriaConFallas, 2: RegistroAuditoriaConFallas}
 */
function autorizadorCajaBlanca(bool $lecturaFalla = false, bool $principalFalla = false, bool $respaldoFalla = false): array
{
    $base = FabricaEnMemoria::crear(Escenario::datosDemo());
    $membresias = new RepositorioMembresiasConFallas($base->membresias);
    $membresias->fallarLectura = $lecturaFalla;
    $principal = new RegistroAuditoriaConFallas($base->auditoria);
    $principal->fallar = $principalFalla;
    $respaldo = new RegistroAuditoriaConFallas(new RegistroAuditoriaEnMemoria());
    $respaldo->fallar = $respaldoFalla;
    $casos = construirCasosDeUso(
        new ConjuntoAdaptadores($membresias, $base->catalogo, $base->usuarios, $principal, $base->transaccion),
        $respaldo,
        static fn (): string => 'corr-cb',
        static fn (): DateTimeImmutable => new DateTimeImmutable(Escenario::AHORA),
    );

    return [$casos['autorizar'], $principal, $respaldo];
}

prueba('CB-01', 'AutorizarOperacion: los 5 caminos básicos de ejecutar()/denegar() (V(G) = 5)', function (): void {
    // Grafo: docs/modulos/rbac/semana-15/diagramas/semana-15-grafo-autorizar.png
    // D1 ¿membresiasDe() lanza? · D2 ¿motivo === null? · D3 ¿la bitácora principal lanza? · D4 ¿el respaldo lanza?
    // C1: D1 no, D2 sí → PERMITIDO sin eventos.
    [$aut, $pri, $res] = autorizadorCajaBlanca();
    $r = $aut->ejecutar(Escenario::comandoAutorizar('ana', 'signos_vitales.registrar'));
    igual([true, 0, 0], [$r->permitido, count($pri->eventos()), count($res->eventos())], 'C1 permitido');

    // C2: D1 sí → ERROR_EVALUACION (fail-closed), evento en la principal.
    [$aut, $pri, $res] = autorizadorCajaBlanca(lecturaFalla: true);
    $r = $aut->ejecutar(Escenario::comandoAutorizar('admin-01', 'roles.asignar'));
    igual([false, 403, 'ERROR_EVALUACION'], [$r->permitido, $r->codigoHttp, $pri->eventos()[0]->datos['motivo'] ?? null], 'C2 lectura caída');

    // C3: D1 no, D2 no, D3 no → evento en la principal, respaldo vacío.
    [$aut, $pri, $res] = autorizadorCajaBlanca();
    $r = $aut->ejecutar(Escenario::comandoAutorizar('ana', 'prescripciones.crear'));
    igual([false, 'corr-cb', 1, 0], [$r->permitido, $r->correlationId, count($pri->eventos()), count($res->eventos())], 'C3 denegado con evidencia');

    // C4: D3 sí, D4 no → el evento va al respaldo.
    [$aut, $pri, $res] = autorizadorCajaBlanca(principalFalla: true);
    $r = $aut->ejecutar(Escenario::comandoAutorizar('ana', 'prescripciones.crear'));
    igual([false, 0, 'PERMISO_NO_ASIGNADO'], [$r->permitido, count($pri->eventos()), $res->eventos()[0]->datos['motivo'] ?? null], 'C4 respaldo');

    // C5: D3 sí, D4 sí → sigue DENEGADO y no lanza (fail-closed), pero sin evidencia: comportamiento actual, defecto DEF-04.
    [$aut, $pri, $res] = autorizadorCajaBlanca(principalFalla: true, respaldoFalla: true);
    $r = $aut->ejecutar(Escenario::comandoAutorizar('ana', 'prescripciones.crear'));
    igual([false, 0, 0], [$r->permitido, count($pri->eventos()), count($res->eventos())], 'C5 ambas bitácoras caídas');
});

prueba('CB-02', 'PoliticaAutorizacion::evaluar(): rama sin reglas, rama «todas pasan», rama «regla lanza» y rama «regla da motivo»', function (): void {
    $ahora = new DateTimeImmutable(Escenario::AHORA);
    $contexto = new ContextoAutorizacion(new SolicitudAutorizacion('ana', 'h', 'h', 'x.y', 'r', '', $ahora), []);
    $regla = static fn (callable $f): ReglaAutorizacion => new class ($f) implements ReglaAutorizacion {
        public function __construct(private $f)
        {
        }

        public function evaluar(ContextoAutorizacion $contexto): ?string
        {
            return ($this->f)();
        }
    };
    igual('SIN_REGLAS_CONFIGURADAS', (new PoliticaAutorizacion([]))->evaluar($contexto), 'sin reglas');
    igual(null, (new PoliticaAutorizacion([$regla(static fn () => null), $regla(static fn () => null)]))->evaluar($contexto), 'todas pasan');
    igual('ERROR_EVALUACION', (new PoliticaAutorizacion([$regla(static fn () => throw new LogicException('x'))]))->evaluar($contexto), 'regla lanza');
    igual('X', (new PoliticaAutorizacion([$regla(static fn () => null), $regla(static fn () => 'X')]))->evaluar($contexto), 'segunda da motivo');
});

prueba('CB-03', 'Membresia::estaVigenteEn(): MC/DC de A = activa, B = desde ≤ ahora, C1 = sin fin, C2 = ahora < hasta', function (): void {
    $ahora = new DateTimeImmutable(Escenario::AHORA);
    $m = static fn (string $estado, string $desde, ?string $hasta): bool
        => (new Membresia('ana', 'h', 'enfermeria', $estado, new DateTimeImmutable($desde), $hasta ? new DateTimeImmutable($hasta) : null))->estaVigenteEn($ahora);
    $antes = '2026-01-01T00:00:00-06:00';
    $despues = '2026-12-31T00:00:00-06:00';
    // Cada par cambia UNA condición y cambia el resultado: así se demuestra su efecto independiente (MC/DC).
    $casos = [
        'A=V B=V C1=V            → V' => [$m('active', $antes, null), true],
        'A=F B=V C1=V            → F (efecto de A)' => [$m('revoked', $antes, null), false],
        'A=V B=F C1=V            → F (efecto de B)' => [$m('active', $despues, null), false],
        'A=V B=V C1=F C2=V       → V' => [$m('active', $antes, $despues), true],
        'A=V B=V C1=F C2=F       → F (efecto de C2)' => [$m('active', $antes, '2026-06-30T00:00:00-06:00'), false],
    ];
    foreach ($casos as $nombre => [$real, $esperado]) {
        igual($esperado, $real, $nombre);
    }
});

prueba('CB-04', 'Kernel::procesar()/manejar(): cada decisión en su orden (ruta → método → tenant → token → permiso → controlador → errores)', function (): void {
    $e = new Escenario();
    $rutas = [
        new Ruta('GET', '#^/x/ok$#', null, static fn (): Respuesta => Respuesta::json(200, ['ok' => true])),
        new Ruta('GET', '#^/x/protegida$#', 'roles.asignar', static fn (): Respuesta => Respuesta::json(200, ['ok' => true])),
        new Ruta('GET', '#^/x/lanza$#', null, static fn (): Respuesta => throw new RuntimeException('falla del controlador')),
        new Ruta('GET', '#^/x/no-respuesta$#', null, static fn () => 'no soy una Respuesta'),
    ];
    $logRoto = static function (array $registro): void {
        throw new RuntimeException('el registro de acceso falló');
    };
    $kernel = new Kernel($rutas, new AutenticadorTokenDemo(['token-ana' => ['ana', 'hosp-oriente'], 'token-admin' => ['admin-01', 'hosp-oriente']]),
        new MiddlewareAutorizacion($e->autorizar), $logRoto, static fn (): string => 'req-cb');
    $estado = static fn (string $metodo, string $ruta, ?string $token = 'token-ana', ?string $tenant = 'hosp-oriente'): int
        => $kernel->manejar(Escenario::http($metodo, $ruta, $token, $tenant))->estado;
    igual(404, $estado('GET', '/x/no-existe'), 'ruta inexistente (antes que todo)');
    igual(405, $estado('POST', '/x/ok'), 'método no permitido');
    igual(400, $estado('GET', '/x/ok', 'token-ana', ''), 'sin tenant (antes que el token)');
    igual(401, $estado('GET', '/x/ok', 'token-falso'), 'token inválido');
    igual(401, $estado('GET', '/x/ok', null), 'sin token');
    igual(200, $estado('GET', '/x/ok'), 'ruta sin permiso: no pasa por el autorizador');
    igual(403, $estado('GET', '/x/protegida'), 'permiso denegado (antes del controlador)');
    igual(200, $estado('GET', '/x/protegida', 'token-admin'), 'permiso concedido');
    igual(500, $estado('GET', '/x/lanza'), 'controlador lanza → 500 sin detalles');
    igual(500, $estado('GET', '/x/no-respuesta'), 'controlador devuelve algo que no es Respuesta → 500');
    $r = $kernel->manejar(Escenario::http('GET', '/x/lanza', 'token-ana'));
    igual(['mensaje' => 'Error interno.'], $r->cuerpo, 'el 500 no filtra el mensaje de la excepción');
    igual('req-cb', $r->cabeceras['X-Request-ID'] ?? null, 'el registro de acceso roto no tumba la respuesta');
});
