<?php

declare(strict_types=1);

use Auditoria\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Auditoria\Presentacion\Http\Observabilidad\RegistroAcceso;
use Auditoria\Presentacion\Http\Solicitud;
use Auditoria\Pruebas\Dobles\Escenario;

/** Registro de acceso en memoria para inspeccionar las líneas. */
final class RegistroAccesoEnMemoria implements RegistroAcceso
{
    /** @var list<array<string, mixed>> */
    public array $lineas = [];
    public bool $fallar = false;

    public function registrar(array $linea): void
    {
        if ($this->fallar) {
            throw new RuntimeException('el agregador de logs no responde');
        }
        $this->lineas[] = $linea;
    }
}

$obsKernel = static function (RegistroAccesoEnMemoria $registro): object {
    $e = Escenario::crear();
    $config = Escenario::config();

    return (object) ['e' => $e, 'kernel' => construirKernel($e->casos, new AutenticadorTokenDemo($config['tokens_demo']), $registro)];
};

prueba('OBS-01', 'X-Request-ID: se respeta el válido, se genera si falta y se reemplaza si es peligroso', function () use ($obsKernel): void {
    $k = $obsKernel(new RegistroAccesoEnMemoria())->kernel;
    $con = static function (string $id) use ($k): string {
        return $k->manejar(new Solicitud('GET', '/api/v1/auditoria/anclas',
            ['Authorization' => 'Bearer token-superadmin', 'X-Tenant-ID' => '*'] + ($id === '' ? [] : ['X-Request-ID' => $id])))->cabeceras['X-Request-ID'];
    };
    igual('req-cliente-0001', $con('req-cliente-0001'), 'válido');
    afirmar(preg_match('/^[0-9a-f]{32}$/', $con('')) === 1, 'no se generó');
    $peligroso = $con("x\r\nSet-Cookie: a=b");
    afirmar($peligroso !== "x\r\nSet-Cookie: a=b" && preg_match('/^[0-9a-f]{32}$/', $peligroso) === 1, 'se aceptó un id peligroso');
});

prueba('OBS-02', 'Registro de acceso: una línea por petición con request_id, ruta, estado, duración, actor y tenant; SIN token, consulta ni cuerpo', function () use ($obsKernel): void {
    $registro = new RegistroAccesoEnMemoria();
    $o = $obsKernel($registro);
    $o->kernel->manejar(Escenario::http('POST', '/api/v1/auditoria/movimientos', 'token-svc-pacientes-oriente', Escenario::H_ORIENTE, Escenario::mov()));
    $o->kernel->manejar(Escenario::http('GET', '/api/v1/auditoria/movimientos', 'token-auditor-oriente', Escenario::H_ORIENTE, null,
        ['desde' => '2026-10-21', 'hasta' => '2026-10-21', 'usuario' => 'u-ana']));
    igual(2, count($registro->lineas), 'líneas');
    igual(['request_id', 'metodo', 'ruta', 'estado', 'duracion_ms', 'actor', 'tenant'], array_keys($registro->lineas[1]), 'campos');
    igual(['/api/v1/auditoria/movimientos', 200, 'auditor-oriente'], [$registro->lineas[1]['ruta'], $registro->lineas[1]['estado'],
        $registro->lineas[1]['actor']], 'contenido');
    $todo = json_encode($registro->lineas);
    foreach (['token-', 'u-ana', 'P-000001', 'desde'] as $prohibido) {
        afirmar(!str_contains($todo, $prohibido), "el registro de acceso contiene «{$prohibido}»");
    }
});

prueba('OBS-03', 'Si el registro de acceso falla, la respuesta al usuario no cambia (y el movimiento sí se registró)', function () use ($obsKernel): void {
    $registro = new RegistroAccesoEnMemoria();
    $registro->fallar = true;
    $o = $obsKernel($registro);
    $r = $o->kernel->manejar(Escenario::http('POST', '/api/v1/auditoria/movimientos', 'token-svc-pacientes-oriente', Escenario::H_ORIENTE, Escenario::mov()));
    igual([201, 1], [$r->estado, count($o->e->bitacora->todos())], 'respuesta y bitácora');
});
