<?php
declare(strict_types=1);

// ANTES: una sola clase decide si una cama es compatible.
// Cada criterio nuevo obliga a ABRIR y MODIFICAR este método (viola OCP).

namespace OcpAntes;

final class AsignadorCamas
{
    /**
     * @param array{codigo:string, servicio:string, tipo:string, aislamiento:bool} $cama
     * @param array{servicio:string, tipo:string, requiereAislamiento:bool} $req
     */
    public function esElegible(array $cama, array $req): bool
    {
        if ($cama['servicio'] !== $req['servicio']) {
            return false;
        }

        switch ($req['tipo']) {
            case 'GENERAL':
                if ($cama['tipo'] !== 'GENERAL') {
                    return false;
                }
                break;
            case 'UCI':
                if ($cama['tipo'] !== 'UCI') {
                    return false;
                }
                break;
            case 'PEDIATRICA':
                if ($cama['tipo'] !== 'PEDIATRICA') {
                    return false;
                }
                break;
            default:
                return false;
        }

        // Agregado cuando llegó el requisito de aislamiento:
        // hubo que modificar esta clase, volver a probar todo el método
        // y arriesgar los criterios que ya funcionaban.
        if ($req['requiereAislamiento'] !== $cama['aislamiento']) {
            return false;
        }

        return true;
    }
}
