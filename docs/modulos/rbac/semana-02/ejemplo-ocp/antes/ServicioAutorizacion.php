<?php

declare(strict_types=1);

namespace Rbac\Antes;

/**
 * Diseño ANTES de aplicar OCP.
 *
 * Una sola clase obtiene las membresías, evalúa todas las reglas con una cadena
 * de if/else, arma el motivo y escribe la bitácora en un archivo concreto.
 * Agregar una regla nueva (por ejemplo, restringir por red hospitalaria) o un
 * destino de auditoría nuevo obliga a MODIFICAR el método autorizar().
 */
final class ServicioAutorizacion
{
    /**
     * @param array<string, list<array{rol: string, estado: string, desde: string, hasta: ?string}>> $membresias
     *        Clave "usuario|hospital".
     * @param array<string, list<string>> $permisosPorRol
     */
    public function __construct(
        private array $membresias,
        private array $permisosPorRol,
        private string $archivoBitacora,
    ) {
    }

    /**
     * @return array{permitido: bool, http: int, motivo?: string, correlation_id?: string}
     */
    public function autorizar(
        string $usuarioId,
        string $tenantToken,
        string $tenantSolicitud,
        string $permiso,
        string $recurso,
        string $ip,
        \DateTimeImmutable $ahora,
    ): array {
        $motivo = null;

        if ($tenantToken !== $tenantSolicitud) {
            $motivo = 'TENANT_NO_COINCIDE';
        } else {
            $vigentes = [];
            foreach ($this->membresias[$usuarioId . '|' . $tenantSolicitud] ?? [] as $m) {
                $desde = new \DateTimeImmutable($m['desde']);
                $hasta = $m['hasta'] === null ? null : new \DateTimeImmutable($m['hasta']);
                if ($m['estado'] === 'active' && $desde <= $ahora && ($hasta === null || $ahora < $hasta)) {
                    $vigentes[] = $m;
                }
            }

            if ($vigentes === []) {
                $motivo = 'MEMBRESIA_NO_VIGENTE';
            } else {
                $concede = false;
                foreach ($vigentes as $m) {
                    if (in_array($permiso, $this->permisosPorRol[$m['rol']] ?? [], true)) {
                        $concede = true;
                    }
                }
                if (!$concede) {
                    $motivo = 'PERMISO_NO_ASIGNADO';
                }
            }
        }
        // Una regla nueva sería otro elseif aquí: la clase no está cerrada a modificación.

        if ($motivo === null) {
            return ['permitido' => true, 'http' => 200];
        }

        $correlationId = bin2hex(random_bytes(16));

        // Dependencia concreta: solo sabe escribir en un archivo de texto.
        @file_put_contents($this->archivoBitacora, json_encode([
            'tipo' => 'ACCESO_DENEGADO',
            'correlation_id' => $correlationId,
            'actor' => $usuarioId,
            'hospital' => $tenantSolicitud,
            'permiso' => $permiso,
            'recurso' => $recurso,
            'motivo' => $motivo,
            'ip' => $ip,
            'fecha' => $ahora->format(DATE_ATOM),
        ]) . PHP_EOL, FILE_APPEND);

        return ['permitido' => false, 'http' => 403, 'motivo' => $motivo, 'correlation_id' => $correlationId];
    }
}
