<?php
declare(strict_types=1);

namespace Alergias\Presentacion\Api;

use Alergias\Aplicacion\SolicitudRegistro;
use Alergias\Dominio\AlertaExpediente;
use Alergias\Presentacion\TraductorErrores;
use Alergias\Presentacion\Web\Respuesta;
use Closure;
use JsonException;
use Throwable;

/** API REST JSON v1 sobre los MISMOS casos de uso que la consola y el MVC. */
final class KernelApi
{
    private const RUTAS = [
        ['GET', '#^/api/v1/pacientes/(PAC-\d{5})/alerta$#', 'alerta.consultar', 'consultar'],
        ['POST', '#^/api/v1/pacientes/(PAC-\d{5})/alergias$#', 'alergias.registrar', 'registrar'],
        ['POST', '#^/api/v1/pacientes/(PAC-\d{5})/alerta/reconocimientos$#', 'alerta.reconocer', 'reconocer'],
    ];

    /** @param Closure(array): void $registrarAcceso recibe solo metadatos, nunca datos clínicos */
    public function __construct(
        private readonly array $casos,
        private readonly AutenticadorTokens $autenticador,
        private readonly string $hospital,
        private readonly Closure $registrarAcceso,
    ) {
    }

    /** @param array<string, string> $cabeceras nombres en minúscula */
    public function manejar(string $metodo, string $ruta, array $cabeceras, string $cuerpo): Respuesta
    {
        $inicio = hrtime(true);
        $idPeticion = preg_match('/^[A-Za-z0-9-]{8,64}$/', $cabeceras['x-request-id'] ?? '') ? $cabeceras['x-request-id'] : bin2hex(random_bytes(8));
        try {
            [$estado, $datos, $extra] = $this->resolver($metodo, $ruta, $cabeceras, $cuerpo);
        } catch (ErrorApi $e) {
            [$estado, $datos, $extra] = [$e->estado, self::error($e->codigo, $e->getMessage(), $idPeticion), []];
        } catch (Throwable $e) {
            [$estado, $codigo, $mensaje] = TraductorErrores::traducir($e);
            [$datos, $extra] = [self::error($codigo, $mensaje, $idPeticion), []];
        }
        ($this->registrarAcceso)(['request_id' => $idPeticion, 'metodo' => $metodo, 'ruta' => preg_replace('#PAC-\d{5}#', 'PAC-*', $ruta),
            'estado' => $estado, 'ms' => round((hrtime(true) - $inicio) / 1e6, 2)]);
        $cabecerasRespuesta = ['Content-Type' => 'application/json; charset=utf-8', 'X-Request-Id' => $idPeticion] + $extra;
        return new Respuesta($estado, json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $cabecerasRespuesta);
    }

    private function resolver(string $metodo, string $ruta, array $cabeceras, string $cuerpo): array
    {
        $yo = $this->autenticador->identificar($cabeceras['authorization'] ?? null)
            ?? throw new ErrorApi(401, 'NO_AUTENTICADO', 'Falta un token válido.');
        // El tenant sale del token; el encabezado, si viene, debe coincidir. Nunca al revés.
        if ($yo->hospital !== $this->hospital || (isset($cabeceras['x-tenant-id']) && $cabeceras['x-tenant-id'] !== $yo->hospital)) {
            throw new ErrorApi(403, 'TENANT_DISTINTO', 'El token no corresponde a este hospital.');
        }
        foreach (self::RUTAS as [$m, $patron, $permiso, $accion]) {
            if ($m === $metodo && preg_match($patron, $ruta, $partes)) {
                if (!Permisos::permite($yo->rol, $permiso)) {
                    throw new ErrorApi(403, 'PERMISO_DENEGADO', "Su rol no tiene el permiso {$permiso}.");
                }
                return $this->{$accion}($partes[1], $yo, $cuerpo);
            }
        }
        throw new ErrorApi(404, 'RUTA_NO_ENCONTRADA', 'La ruta no existe en la API v1.');
    }

    private function consultar(string $codigo, Identidad $yo): array
    {
        return [200, self::alerta($this->casos['consultar']->ejecutar($codigo, $yo->usuarioId)), []];
    }

    private function registrar(string $codigo, Identidad $yo, string $cuerpo): array
    {
        $j = self::json($cuerpo);
        $texto = static fn (string $k): ?string => isset($j[$k]) && is_string($j[$k]) && $j[$k] !== '' ? $j[$k] : null;
        $alerta = $this->casos['registrar']->ejecutar(new SolicitudRegistro(
            $codigo, $texto('alergeno_codigo'), $texto('alergeno_nombre'), $texto('tipo') ?? 'allergy',
            $texto('severidad') ?? '', $texto('reaccion'), $texto('fecha_inicio'), $yo->usuarioId,
        ));
        return [201, self::alerta($alerta), ['Location' => "/api/v1/pacientes/{$codigo}/alerta"]];
    }

    private function reconocer(string $codigo, Identidad $yo, string $cuerpo): array
    {
        $huella = self::json($cuerpo)['huella'] ?? null;
        if (!is_string($huella)) {
            throw new ErrorApi(400, 'FORMATO_INVALIDO', 'Falta "huella" (texto).');
        }
        return [201, self::alerta($this->casos['reconocer']->ejecutar($codigo, $yo->usuarioId, $huella)), []];
    }

    private static function json(string $cuerpo): array
    {
        try {
            $datos = json_decode($cuerpo, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ErrorApi(400, 'FORMATO_INVALIDO', 'El cuerpo debe ser JSON válido.');
        }
        return is_array($datos) ? $datos : throw new ErrorApi(400, 'FORMATO_INVALIDO', 'El cuerpo debe ser un objeto JSON.');
    }

    private static function alerta(AlertaExpediente $a): array
    {
        return [
            'paciente' => $a->codigoPaciente, 'nivel' => $a->nivel->value, 'huella' => $a->huella,
            'requiere_reconocimiento' => $a->requiereReconocimiento(), 'reconocida' => $a->reconocida,
            'alergias' => array_map(fn ($d) => [
                'alergeno' => $d['alergia']->alergeno->etiqueta(), 'tipo' => $d['alergia']->tipo->value,
                'severidad' => $d['alergia']->severidad->value, 'nivel' => $d['nivel']->value,
                'reaccion' => $d['alergia']->reaccion, 'fecha_inicio' => $d['alergia']->inicio?->format('Y-m-d'),
            ], $a->detalle),
        ];
    }

    /** Cuando ni siquiera se pudieron armar los casos de uso (por ejemplo, base caída al conectar). */
    public static function fueraDeServicio(Throwable $e): Respuesta
    {
        [$estado, $codigo, $mensaje] = TraductorErrores::traducir($e);
        $id = bin2hex(random_bytes(8));
        return new Respuesta($estado, json_encode(self::error($codigo, $mensaje, $id), JSON_UNESCAPED_UNICODE),
            ['Content-Type' => 'application/json; charset=utf-8', 'X-Request-Id' => $id]);
    }

    private static function error(string $codigo, string $mensaje, string $idPeticion): array
    {
        return ['error' => ['codigo' => $codigo, 'mensaje' => $mensaje, 'request_id' => $idPeticion]];
    }
}
