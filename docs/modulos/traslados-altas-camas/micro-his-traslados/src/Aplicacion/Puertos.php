<?php
declare(strict_types=1);

// Límite de la Aplicación (semana 4): lo que los casos de uso necesitan, sin saber qué almacén hay detrás.
namespace Traslados\Aplicacion;

use Traslados\Dominio\Resultado;

/** Repository de lo que este módulo escribe: órdenes y movimiento aplicado. Si falla lanza ErrorPersistencia. */
interface RepositorioMovimientos {
    /** @return array{orden_id: string, requisito_cama: ?string, tipo_alta: ?string, motivo: ?string}|null */
    public function ordenVigente(string $episodio, string $tipo): ?array;
    /** Cierra la estancia de origen, deja libre para limpieza esa cama, abre la nueva y ocupa el destino, marca la orden y, si es alta, cierra el episodio: todo o nada. */
    public function aplicar(string $episodio, string $orden, string $tipo, Resultado $r, string $estanciaOrigen, string $estanciaNueva, \DateTimeImmutable $en, ?string $clave = null): void;
    /** Semana 5: movimiento ya aplicado con esa clave de idempotencia: care_episode_id, tipo, origen_id, destino_id (o null). La clave se guarda en la misma transacción. */
    public function ordenPorClave(string $clave): ?array;
}

/** Datos que otros módulos poseen y este solo lee: episodio, estancia abierta y estado de la cama. */
interface FuenteClinica {
    /** @return array{care_episode_id: string}|null null si no existe o ya está cerrado */
    public function episodioAbierto(string $id): ?array;
    /** @return array{bed_stay_id: string, bed_id: string}|null */
    public function estanciaAbierta(string $episodio): ?array;
    /** @return array{bed_id: string, status: string, type: string}|null */
    public function cama(string $id): ?array;
}
