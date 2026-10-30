<?php
declare(strict_types=1);

namespace MicroHis\DashboardBase\Dominio;

/** Violación de una regla de negocio. El código (E1, E4, E6, NF) lo traduce a HTTP la capa de presentación. */
final class ReglaDeDominio extends \DomainException
{
    public function __construct(public readonly string $codigo, string $mensaje) { parent::__construct($mensaje); }
}

/** Estados de una tarjeta (R-04, R-05): «vacío» es una respuesta correcta sin datos, no un error. */
enum EstadoTarjeta: string
{
    case Cargando = 'cargando'; case Resumen = 'resumen'; case Vacio = 'vacio'; case Error = 'error';
    public static function deConteo(int $total): self { return $total === 0 ? self::Vacio : self::Resumen; }
}

/** Tarjeta del resumen inicial: solo un conteo (R-06), el permiso que la muestra (R-02) y textos sin detalles técnicos (R-09). */
final class Tarjeta
{
    public function __construct(public readonly string $id, public readonly string $titulo, public readonly array $unidad,
                                public readonly string $sugerencia, public readonly string $permiso) {}

    public function texto(EstadoTarjeta $estado, ?int $total): string
    {
        return match ($estado) {
            EstadoTarjeta::Cargando => "Cargando {$this->titulo}…",
            EstadoTarjeta::Resumen => "$total " . $this->unidad[$total === 1 ? 0 : 1],   // [singular, plural]: «1 cita hoy», «2 citas hoy»
            EstadoTarjeta::Vacio => $this->sugerencia,
            EstadoTarjeta::Error => "No pudimos cargar {$this->titulo}. Intenta de nuevo.",
        };
    }
}

/** Menú y tarjetas del dashboard, filtrados por los permisos del usuario (R-02): nunca un acceso que el backend rechazaría. */
final class Catalogo
{
    private const MENU = ['/pacientes' => ['Pacientes', 'pacientes.ver'], '/citas' => ['Citas', 'citas.ver'], '/camas' => ['Camas', 'camas.ver']];

    public static function menu(array $permisos): array
    {
        $r = [];
        foreach (self::MENU as $ruta => [$titulo, $permiso]) if (in_array($permiso, $permisos, true)) $r[] = ['ruta' => $ruta, 'titulo' => $titulo];
        return $r;
    }

    /** CU-03: un módulo del menú, solo si el rol lo permite (R-02). Escribir la ruta a mano no salta el permiso (E6). */
    public static function destino(string $ruta, array $permisos): array
    {
        [$titulo, $permiso] = self::MENU[$ruta] ?? throw new ReglaDeDominio('NF', 'Esa sección no existe.');
        if (!in_array($permiso, $permisos, true)) throw new ReglaDeDominio('E6', 'Esa sección no está disponible para tu rol.');
        return ['ruta' => $ruta, 'titulo' => $titulo];
    }

    /** @return array<string, Tarjeta> */
    public static function tarjetas(array $permisos): array
    {
        $todas = [new Tarjeta('pacientes', 'pacientes', ['paciente activo', 'pacientes activos'], 'Aún no hay pacientes activos: registra el primero.', 'pacientes.ver'),
                  new Tarjeta('citas_hoy', 'las citas de hoy', ['cita hoy', 'citas hoy'], 'No hay citas hoy: agenda una.', 'citas.ver'),
                  new Tarjeta('camas_disponibles', 'las camas disponibles', ['cama disponible', 'camas disponibles'], 'No hay camas disponibles ahora.', 'camas.ver')];
        $r = [];
        foreach ($todas as $t) if (in_array($t->permiso, $permisos, true)) $r[$t->id] = $t;
        return $r;
    }
}
