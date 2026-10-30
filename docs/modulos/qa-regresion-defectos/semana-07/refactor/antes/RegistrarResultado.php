<?php

declare(strict_types=1);

namespace Qa\Aplicacion\CasosDeUso;

use Qa\Aplicacion\Puertos\AlmacenEvidencias;
use Qa\Aplicacion\Puertos\AlmacenNoDisponible;
use Qa\Aplicacion\Puertos\PoliticaAcceso;
use Qa\Aplicacion\Puertos\RegistroAuditoria;
use Qa\Aplicacion\Puertos\Transaccion;
use Qa\Aplicacion\Resultados\Salida;
use Qa\Dominio\Evidencia\Clasificacion;
use Qa\Dominio\Evidencia\EvidenciaInvalida;
use Qa\Dominio\Evidencia\EvidenciaVerificable;
use Qa\Dominio\Evidencia\FabricaEvidencias;
use Qa\Dominio\Evidencia\ReferenciaExterna;
use Qa\Dominio\Excepciones\ClaveEnUso;
use Qa\Dominio\Excepciones\ErrorPersistencia;
use Qa\Dominio\Excepciones\ReglaRechazada;
use Qa\Dominio\Modelo\Defecto;
use Qa\Dominio\Modelo\EvidenciaRegistrada;
use Qa\Dominio\Modelo\Intento;
use Qa\Dominio\Modelo\Resultado;
use Qa\Dominio\Modelo\Severidad;
use Qa\Dominio\Puertos\Reloj;
use Qa\Dominio\Puertos\RepositorioCampanas;
use Qa\Dominio\Puertos\RepositorioDefectos;

/**
 * CU-05/06/07: el Tester registra el resultado de un caso, con su evidencia y, si falla, su defecto.
 * Orden (actividad de la semana 1): permiso → idempotencia → campaña admite → reglas R-05/R-06/R-10 → evidencias al
 * almacén (fail-closed, R-05) → intento + defecto + traza en UNA transacción. Una re-ejecución puede verificar un
 * defecto CORREGIDO: PASA lo cierra y FALLA lo reabre (R-08).
 */
final class RegistrarResultado
{
    public const PERMISO = 'qa.ejecutar';
    public const TAMANO_MAXIMO = 5 * 1024 * 1024;

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

    /**
     * @param array{caso?: mixed, resultado?: mixed, evidencias?: list<array{tipo: string, bytes: string, clasificacion?: string}>,
     *              referencias?: list<array{url: string, descripcion?: string}>, defecto?: array<string, mixed>|null,
     *              verifica_defecto?: mixed, motivo?: mixed} $datos
     */
    public function ejecutar(string $tester, string $tenant, int $campanaId, string $clave, array $datos): Salida
    {
        if (!$this->acceso->permite($tester, self::PERMISO, $tenant)) {
            return Salida::denegado();
        }
        if (!preg_match('/^[A-Za-z0-9-]{8,64}$/', $clave)) {
            return new Salida(Salida::INVALIDO, 'CLAVE_IDEMPOTENCIA_REQUERIDA');
        }
        try {
            $huella = self::huellaSolicitud($campanaId, $datos);
            $previo = $this->campanas->intentoPorClave($tenant, $clave);
            if ($previo !== null) {
                return $this->repetido($previo, $huella);
            }

            return $this->registrar($tester, $tenant, $campanaId, $clave, $huella, $datos);
        } catch (ReglaRechazada $e) {
            return Salida::rechazo($e);
        } catch (EvidenciaInvalida $e) {
            return new Salida(Salida::INVALIDO, $e->codigo);
        } catch (AlmacenNoDisponible) {
            return new Salida(Salida::NO_DISPONIBLE, 'EVIDENCIA_NO_DISPONIBLE');   // sin evidencia confirmada no hay resultado
        } catch (ClaveEnUso) {
            // Otra petición con la misma clave ganó la carrera: se responde como si fuera la repetición.
            $previo = $this->campanas->intentoPorClave($tenant, $clave);

            return $previo === null ? Salida::persistencia() : $this->repetido($previo, self::huellaSolicitud($campanaId, $datos));
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
        $resultado = Resultado::tryFrom((string) ($datos['resultado'] ?? '')) ?? throw new ReglaRechazada('DATOS_INVALIDOS', ['resultado']);
        $motivo = trim((string) ($datos['motivo'] ?? '')) === '' ? null : trim((string) $datos['motivo']);
        if ($resultado === Resultado::NO_APLICA && $motivo === null) {
            throw new ReglaRechazada('MOTIVO_REQUERIDO');
        }
        $evidencias = $this->evidencias($datos['evidencias'] ?? []);
        if ($evidencias === null) {
            return new Salida(Salida::DEMASIADO_GRANDE, 'EVIDENCIA_DEMASIADO_GRANDE');
        }
        if ($resultado->exigeEvidencia() && $evidencias === []) {
            throw new ReglaRechazada('EVIDENCIA_REQUERIDA');   // R-05: una referencia externa no cuenta
        }
        $referencias = array_map(static fn (array $r): ReferenciaExterna => new ReferenciaExterna((string) ($r['url'] ?? ''), (string) ($r['descripcion'] ?? '')),
            array_values(array_filter(is_array($datos['referencias'] ?? null) ? $datos['referencias'] : [], 'is_array')));

        $verificado = $this->defectoAVerificar($tenant, $casoId, $datos['verifica_defecto'] ?? null);
        $verificado?->verificar($resultado, $tester);
        $nuevo = null;
        if ($resultado === Resultado::FALLA && $verificado === null) {
            $d = is_array($datos['defecto'] ?? null) ? $datos['defecto'] : throw new ReglaRechazada('DEFECTO_REQUERIDO');   // R-06
            $propuesta = Severidad::tryFrom((string) ($d['severidad'] ?? '')) ?? throw new ReglaRechazada('DATOS_INVALIDOS', ['defecto.severidad']);
            $nuevo = Defecto::reportar($tenant, $campanaId, $casoId, (string) ($d['titulo'] ?? ''), is_array($d['pasos'] ?? null) ? $d['pasos'] : [],
                $propuesta, $evidencias !== [], $tester);
        }

        // Fuera de la transacción y ANTES de ella: si el almacén no confirma, no se escribe nada (fail-closed).
        $registradas = array_map(fn (EvidenciaVerificable $e): EvidenciaRegistrada => new EvidenciaRegistrada($e->tipo(), $e->huella(),
            $e->tamano(), $e->clasificacion()->value, $this->almacen->guardar($tenant, $e)), $evidencias);

        [$intento, $defecto] = $this->transaccion->ejecutar(function () use ($campana, $casoId, $resultado, $tester, $registradas, $referencias,
            $nuevo, $verificado, $motivo, $clave, $huella, $tenant, $campanaId): array {
            $defecto = $nuevo === null ? null : $this->defectos->crear($nuevo);
            $intento = $this->campanas->agregarIntento($tenant, new Intento(null, $campanaId, $casoId, $campana->siguienteNumero($casoId), $resultado,
                $tester, $registradas, $referencias, $defecto?->id, $verificado?->id, $motivo, $clave, $huella, $this->reloj->ahora()));
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

    /**
     * @param mixed $entrada
     * @return list<EvidenciaVerificable>|null null si alguna pasa del tamaño máximo
     */
    private function evidencias(mixed $entrada): ?array
    {
        $evidencias = [];
        foreach (is_array($entrada) ? $entrada : [] as $e) {
            $bytes = is_array($e) && is_string($e['bytes'] ?? null) ? $e['bytes'] : '';
            if (strlen($bytes) > self::TAMANO_MAXIMO) {
                return null;
            }
            $clasificacion = Clasificacion::tryFrom((string) ($e['clasificacion'] ?? 'GENERAL')) ?? throw new ReglaRechazada('DATOS_INVALIDOS', ['evidencias.clasificacion']);
            $evidencias[] = FabricaEvidencias::crear((string) ($e['tipo'] ?? ''), $bytes, $clasificacion);
        }

        return $evidencias;
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
