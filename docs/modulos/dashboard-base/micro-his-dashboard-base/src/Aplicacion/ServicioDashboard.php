<?php
declare(strict_types=1);

namespace MicroHis\DashboardBase\Aplicacion;

use MicroHis\DashboardBase\Dominio\{Catalogo, EstadoTarjeta, ReglaDeDominio, Tarjeta};

/** Puertos: los define la aplicación y los implementan las capas externas. */
interface FuenteConteos { public function conteo(string $tarjeta, string $dia): int; }
interface Reloj { public function hoy(): string; }

final class ErrorPersistencia extends \RuntimeException {}   // fallo técnico de la base: no es una regla de negocio

/** CU-01, CU-02 y CU-04 de la semana 1, con CU-05 (validar sesión, hospital y permisos) incluido. */
final class ServicioDashboard
{
    public function __construct(private FuenteConteos $conteos, private Reloj $reloj) {}

    /** CU-01: el menú del rol y el esqueleto de sus tarjetas, todas «cargando» (R-02, R-04). */
    public function inicio(string $hospital, array $permisos): array
    {
        $this->exigirSesion($hospital);
        return ['menu' => Catalogo::menu($permisos),
                'tarjetas' => array_values(array_map(fn (Tarjeta $t) => $this->vista($t, EstadoTarjeta::Cargando, null), Catalogo::tarjetas($permisos)))];
    }

    /** CU-02 y CU-04: cada tarjeta carga y falla por su cuenta (R-03); reintentar es volver a pedirla (R-08). */
    public function tarjeta(string $id, string $hospital, array $permisos): array
    {
        $this->exigirSesion($hospital);
        $t = Catalogo::tarjetas($permisos)[$id] ?? throw new ReglaDeDominio('E4', 'Esta tarjeta no está disponible para tu rol.');
        try { $total = $this->conteos->conteo($id, $this->reloj->hoy()); }
        catch (ErrorPersistencia) { return $this->vista($t, EstadoTarjeta::Error, null); }
        return $this->vista($t, EstadoTarjeta::deConteo($total), $total);
    }

    private function vista(Tarjeta $t, EstadoTarjeta $e, ?int $total): array { return ['id' => $t->id, 'estado' => $e->value, 'texto' => $t->texto($e, $total)]; }

    /** R-01: sin hospital seleccionado (tenant) no se entra. */
    private function exigirSesion(string $hospital): void { if (trim($hospital) === '') throw new ReglaDeDominio('E1', 'Tu sesión terminó. Vuelve a iniciar sesión.'); }
}
