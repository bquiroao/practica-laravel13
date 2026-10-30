<?php

declare(strict_types=1);

namespace Qa\Aplicacion\CasosDeUso;

use Qa\Aplicacion\Puertos\AlmacenEvidencias;
use Qa\Aplicacion\Puertos\AlmacenNoDisponible;
use Qa\Aplicacion\Puertos\PoliticaAcceso;
use Qa\Aplicacion\Puertos\RegistroAuditoria;
use Qa\Aplicacion\Puertos\Transaccion;
use Qa\Aplicacion\Resultados\Salida;
use Qa\Dominio\Evidencia\EvidenciaVerificable;
use Qa\Dominio\Excepciones\ClaveEnUso;
use Qa\Dominio\Excepciones\ErrorPersistencia;
use Qa\Dominio\Excepciones\ReglaRechazada;
use Qa\Dominio\Modelo\Defecto;
use Qa\Dominio\Modelo\EvidenciaRegistrada;
use Qa\Dominio\Modelo\Intento;
use Qa\Dominio\Modelo\SolicitudResultado;
use Qa\Dominio\Puertos\Reloj;
use Qa\Dominio\Puertos\RepositorioCampanas;
use Qa\Dominio\Puertos\RepositorioDefectos;

/**
 * CU-05/06/07: el Tester registra el resultado de un caso, con su evidencia y, si falla, su defecto.
 * Semana 7: este caso de uso solo ORQUESTA. Las reglas del resultado (R-05, R-06, R-08, R-10) las decide el Dominio
 * (SolicitudResultado); aquí queda el orden: permiso → idempotencia → campaña admite → reglas → evidencias al almacén
 * (fail-closed) → intento + defecto + traza en UNA transacción.
 */
final class RegistrarResultado
{
    public const PERMISO = 'qa.ejecutar';

    public function __construct(
        private readonly PoliticaAcceso $acceso,
        private readonly RepositorioCampanas $campanas,
        private readonly RepositorioDefectos $defectos,
        private readonly AlmacenEvidencias $almacen,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
        private readonly Reloj $reloj,
    ) {
    }

    /** @param array<string, mixed> $datos caso, resultado, evidencias[{tipo, bytes, clasificacion?}], referencias, defecto, verifica_defecto, motivo */
    public function ejecutar(string $tester, string $tenant, int $campanaId, string $clave, array $datos): Salida
    {
        if (!$this->acceso->permite($tester, self::PERMISO, $tenant)) {
            return Salida::denegado();
        }
        if (!preg_match('/^[A-Za-z0-9-]{8,64}$/', $clave)) {
            return new Salida(Salida::INVALIDO, 'CLAVE_IDEMPOTENCIA_REQUERIDA');
        }
        $huella = self::huellaSolicitud($campanaId, $datos);
        try {
            $previo = $this->campanas->intentoPorClave($tenant, $clave);

            return $previo !== null ? $this->repetido($previo, $huella) : $this->registrar($tester, $tenant, $campanaId, $clave, $huella, $datos);
        } catch (ReglaRechazada $e) {
            return Salida::rechazo($e);
        } catch (AlmacenNoDisponible) {
            return new Salida(Salida::NO_DISPONIBLE, 'EVIDENCIA_NO_DISPONIBLE');   // sin evidencia confirmada no hay resultado
        } catch (ClaveEnUso) {
            // Otra petición con la misma clave ganó la carrera: se responde como si fuera la repetición.
            $previo = $this->campanas->intentoPorClave($tenant, $clave);

            return $previo === null ? Salida::persistencia() : $this->repetido($previo, $huella);
        } catch (ErrorPersistencia) {
            return Salida::persistencia();
        }
    }

    /** @param array<string, mixed> $datos */
    private function registrar(string $tester, string $tenant, int $campanaId, string $clave, string $huella, array $datos): Salida
    {
        $campana = $this->campanas->porId($tenant, $campanaId);
        if ($campana === null) {
            return Salida::noEncontrado();
        }
        $casoId = (string) ($datos['caso'] ?? '');
        if (!$campana->admite($casoId)) {
            throw new ReglaRechazada('CAMPANA_NO_ADMITE_RESULTADOS');
        }
        $solicitud = SolicitudResultado::desde($datos);
        $verificado = $this->defectoAVerificar($tenant, $casoId, $datos['verifica_defecto'] ?? null);
        $nuevo = $solicitud->resolverDefecto($verificado, $tenant, $campanaId, $casoId, $tester);

        // Fuera de la transacción y ANTES de ella: si el almacén no confirma, no se escribe nada (fail-closed).
        $registradas = array_map(fn (EvidenciaVerificable $e): EvidenciaRegistrada => new EvidenciaRegistrada($e->tipo(), $e->huella(),
            $e->tamano(), $e->clasificacion()->value, $this->almacen->guardar($tenant, $e)), $solicitud->evidencias);

        [$intento, $defecto] = $this->transaccion->ejecutar(function () use ($campana, $casoId, $solicitud, $tester, $registradas, $nuevo, $verificado,
            $clave, $huella, $tenant, $campanaId): array {
            $defecto = $nuevo === null ? null : $this->defectos->crear($nuevo);
            $intento = $this->campanas->agregarIntento($tenant, new Intento(null, $campanaId, $casoId, $campana->siguienteNumero($casoId),
                $solicitud->resultado, $tester, $registradas, $solicitud->referencias, $defecto?->id, $verificado?->id, $solicitud->motivo, $clave,
                $huella, $this->reloj->ahora()));
            if ($verificado !== null) {
                $this->defectos->actualizar($verificado);
            }
            $this->auditoria->registrar('RESULTADO_REGISTRADO', $tenant, $tester, "campana:{$campanaId}/caso:{$casoId}#{$intento->numero}");

            return [$intento, $defecto];
        });

        return new Salida(Salida::CREADO, 'RESULTADO_REGISTRADO', self::datos($intento) + [
            'defecto_verificado' => $verificado === null ? null : ['id' => $verificado->id, 'estado' => $verificado->estado()->value],
        ] + ($defecto === null ? [] : ['defecto_estado' => $defecto->estado()->value]));
    }

    private function defectoAVerificar(string $tenant, string $casoId, mixed $id): ?Defecto
    {
        if ($id === null) {
            return null;
        }
        $defecto = is_int($id) ? $this->defectos->porId($tenant, $id) : null;
        if ($defecto === null || $defecto->casoId !== $casoId) {
            throw new ReglaRechazada('DATOS_INVALIDOS', ['verifica_defecto']);
        }

        return $defecto;
    }

    private function repetido(Intento $previo, string $huella): Salida
    {
        return hash_equals($previo->huellaSolicitud, $huella)
            ? new Salida(Salida::OK, 'RESULTADO_YA_REGISTRADO', self::datos($previo))
            : new Salida(Salida::CONFLICTO, 'CLAVE_REUTILIZADA');
    }

    /** @return array<string, mixed> */
    private static function datos(Intento $i): array
    {
        return ['intento' => $i->id, 'caso' => $i->casoId, 'numero' => $i->numero, 'resultado' => $i->resultado->value,
            'evidencias' => count($i->evidencias), 'defecto' => $i->defectoId];
    }

    /** Huella de lo que se pidió (sin la clave): la misma clave con otro contenido es un error, no una repetición. */
    private static function huellaSolicitud(int $campanaId, array $datos): string
    {
        $evidencias = array_map(static fn ($e): array => is_array($e)
            ? [(string) ($e['tipo'] ?? ''), hash('sha256', is_string($e['bytes'] ?? null) ? $e['bytes'] : ''), (string) ($e['clasificacion'] ?? 'GENERAL')]
            : [], is_array($datos['evidencias'] ?? null) ? $datos['evidencias'] : []);

        return hash('sha256', json_encode([$campanaId, $datos['caso'] ?? null, $datos['resultado'] ?? null, $evidencias, $datos['referencias'] ?? [],
            $datos['defecto'] ?? null, $datos['verifica_defecto'] ?? null, $datos['motivo'] ?? null], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
    }
}
