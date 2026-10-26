<?php

declare(strict_types=1);

namespace Rbac\Aplicacion\CasosDeUso;

use Rbac\Dominio\Modelo\ContextoAutorizacion;
use Rbac\Dominio\Modelo\SolicitudAutorizacion;
use Rbac\Dominio\Puertos\RepositorioMembresias;
use Rbac\Dominio\Servicios\PoliticaAutorizacion;

/**
 * Semana 12 · cambio solicitado (brecha B-6): permisos efectivos de la sesión, para que la interfaz
 * decida qué MOSTRAR sin dejar eventos en la bitácora.
 *
 * Usa la MISMA PoliticaAutorizacion que AutorizarOperacion: lo que la interfaz muestra y lo que el
 * servidor decide no pueden divergir (prueba PEF-03). La diferencia es solo que aquí no se audita,
 * porque preguntar «¿qué puedo hacer?» no es un intento de acceso. Los intentos reales (ejecutar una
 * operación, entrar a una ruta protegida) siguen pasando por AutorizarOperacion y quedan auditados.
 */
final class ConsultarPermisosEfectivos
{
    private const RECURSO = 'consulta:permisos-efectivos';

    public function __construct(
        private readonly PoliticaAutorizacion $politica,
        private readonly RepositorioMembresias $membresias,
        private readonly \Closure $reloj,
    ) {
    }

    /**
     * @param list<string> $permisos los que la interfaz necesita conocer
     * @return array<string, bool> permiso → ¿lo tiene en este hospital, ahora?
     */
    public function ejecutar(string $usuarioId, string $tenantToken, string $tenantSolicitud, array $permisos): array
    {
        try {
            $membresias = $this->membresias->membresiasDe($usuarioId, $tenantSolicitud);
        } catch (\Throwable) {
            // R-05 fail-closed: sin poder leer las membresías, la interfaz no muestra ninguna acción.
            return array_fill_keys($permisos, false);
        }

        $ahora = ($this->reloj)();
        $resultado = [];
        foreach ($permisos as $permiso) {
            $solicitud = new SolicitudAutorizacion($usuarioId, $tenantToken, $tenantSolicitud, $permiso, self::RECURSO, '', $ahora);
            $resultado[$permiso] = $this->politica->evaluar(new ContextoAutorizacion($solicitud, $membresias)) === null;
        }

        return $resultado;
    }
}
