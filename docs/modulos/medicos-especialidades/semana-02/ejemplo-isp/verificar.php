<?php
declare(strict_types=1);

// Evidencia de la semana 2. Uso: php verificar.php   (sale con 1 si algo no da lo esperado)
require __DIR__ . '/despues.php';
require __DIR__ . '/antes.php';

use Medicos\Antes as A;
use Medicos\Despues as D;

$fallas = 0;
function ver(string $id, bool $ok, string $detalle): void {
    $GLOBALS['fallas'] += $ok ? 0 : 1;
    printf("%-7s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $detalle);
}
function error(callable $f): string { try { $f(); return 'sin error'; } catch (\DomainException $e) { return $e->getMessage(); } }

// Métodos que un cliente VE (los de las interfaces de su constructor) y los que USA (llamados en su código).
function veYUsa(string $clase): array {
    $tipo = (new ReflectionClass($clase))->getConstructor()->getParameters()[0]->getType();
    $interfaces = $tipo instanceof ReflectionIntersectionType ? $tipo->getTypes() : [$tipo];
    $ve = array_unique(array_merge(...array_map(fn ($t) => array_map(fn ($m) => $m->name, (new ReflectionClass($t->getName()))->getMethods()), $interfaces)));
    $r = new ReflectionClass($clase);
    $codigo = implode('', array_slice(file($r->getFileName()), $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1));
    preg_match_all('/->(\w+)\(/', $codigo, $m);
    return [count($ve), count(array_intersect($ve, array_unique($m[1])))];
}

echo "== ISP: ¿cada cliente depende solo de lo que usa?\n";
[$sobraAntes, $sobraDespues] = [0, 0];
foreach (['PantallaRecepcion', 'IntegracionCitas', 'PortalMedico'] as $c) {
    [$va, $ua] = veYUsa("Medicos\\Antes\\$c");
    [$vd, $ud] = veYUsa("Medicos\\Despues\\$c");
    [$sobraAntes, $sobraDespues] = [$sobraAntes + $va - $ua, $sobraDespues + $vd - $ud];
    printf("        %-17s antes: ve %d, usa %d · después: ve %d, usa %d\n", $c, $va, $ua, $vd, $ud);
}
ver('ISP-01', $sobraAntes === 17 && $sobraDespues === 0, "Métodos que los clientes ven y no usan: antes $sobraAntes · después $sobraDespues");
$stubs = substr_count((string) file_get_contents(__DIR__ . '/antes.php'), "throw new \\LogicException('no_soportado')");
$metodosCache = count(array_filter((new ReflectionClass(D\DisponibilidadEnCache::class))->getMethods(), fn ($m) => $m->name !== '__construct'));
ver('ISP-02', $stubs === 6 && $metodosCache === 1, "Réplica de solo lectura para Citas: antes $stubs métodos de relleno que lanzan · después implementa $metodosCache método y ninguno de relleno");
$doble = new class implements D\ConsultaDisponibilidad {
    public function consultarDisponibilidad(string $especialidad, string $desde, string $hasta): array { return [['medico' => 'med-x', 'inicio' => "$desde 07:00"]]; }
};
$metodosGorda = count((new ReflectionClass(A\ServicioMedicos::class))->getMethods());
ver('ISP-03', (new D\PantallaRecepcion($doble))->franjas('pediatria', '2026-10-28', '2026-10-28')[0]['medico'] === 'med-x' && $metodosGorda === 7,
    "Doble de prueba de recepción: después basta 1 método · antes había que escribir $metodosGorda");

// Datos de ejemplo: hoy lunes 26; el miércoles 28 med-1 atiende de 08:00 a 10:00 y ya tiene una cita a las 08:00.
$hoy = '2026-10-26';
$citas = fn () => [['medico' => 'med-1', 'inicio' => '2026-10-28 08:00']];
$catalogo = ['medicina_interna' => true, 'pediatria' => true, 'cardiologia' => false];
$preparar = function (D\ServicioMedicos $s): D\ServicioMedicos {
    $s->registrarMedico('12345', '1111222233334', 'Dra. Ana Gómez');
    $s->asociarEspecialidad('med-1', 'medicina_interna', '2026-01-01', null, true);
    $s->definirHorario('med-1', 3, '08:00', '10:00');
    return $s;
};
$viejo = $preparar(new A\ServicioCompleto($catalogo, $citas, $hoy));
$nuevo = $preparar(new D\ServicioMedicos($catalogo, $citas, $hoy));

echo "== Seguridad por diseño\n";
$recepcionAntes = new A\PantallaRecepcion($viejo);
(fn () => $this->medicos)->call($recepcionAntes)->desactivarMedico('med-1');   // posible porque la interfaz lo ofrece
$puedeDespues = (new ReflectionClass(D\ConsultaDisponibilidad::class))->hasMethod('desactivarMedico');
ver('SEG-01', $recepcionAntes->franjas('medicina_interna', '2026-10-28', '2026-10-28') === [] && !$puedeDespues,
    'Antes, el código de recepción puede desactivar a un médico (y su agenda desaparece) · después su interfaz no tiene ese método');

echo "== Criterios de aceptación\n";
$s = fn () => $preparar(new D\ServicioMedicos($catalogo, $citas, $hoy));
ver('CA-02', error(fn () => $s()->registrarMedico('12345', '9999', 'Otro')) === 'medico_duplicado', 'Colegiado repetido → medico_duplicado');
ver('CA-04', error(fn () => $s()->asociarEspecialidad('med-1', 'cardiologia', '2026-01-01', null, false)) === 'especialidad_invalida', 'Especialidad inactiva en el catálogo → especialidad_invalida');
ver('CA-05', error(fn () => $s()->asociarEspecialidad('med-1', 'medicina_interna', '2026-06-01', null, false)) === 'especialidad_duplicada'
    && error(fn () => $s()->asociarEspecialidad('med-1', 'pediatria', '2026-06-01', null, true)) === 'especialidad_duplicada', 'Vigencia cruzada o segunda principal → especialidad_duplicada');
$franjas = (new D\PantallaRecepcion($nuevo))->franjas('medicina_interna', '2026-10-28', '2026-10-28');
ver('CA-07', array_column($franjas, 'inicio') === ['2026-10-28 08:30', '2026-10-28 09:00', '2026-10-28 09:30'], 'Disponibilidad = horario − citas: 08:30, 09:00 y 09:30 (08:00 ya tiene cita)');
$citasM05 = new D\IntegracionCitas($nuevo);
ver('CA-08', $citasM05->hayEspacio('med-1', 'medicina_interna', '2026-10-28 08:30') && !$citasM05->hayEspacio('med-1', 'medicina_interna', '2026-10-28 08:00'), 'Citas (M05) pregunta antes de agendar: 08:30 sí, 08:00 no');
$portal = new D\PortalMedico($s(), 'med-1');
$portal->avisarAusencia('2026-10-28');
ver('CA-09', $portal->misFranjas('medicina_interna', '2026-10-28') === [], 'El médico registra una ausencia desde su portal → ese día no tiene franjas');
ver('CA-10', error(fn () => $s()->consultarDisponibilidad('medicina_interna', '2026-10-26', '2026-11-20')) === 'rango_invalido'
    && error(fn () => $s()->consultarDisponibilidad('medicina_interna', '2026-10-20', '2026-10-22')) === 'rango_invalido', 'Rango de más de 14 días o en el pasado → rango_invalido');
$cache = new D\DisponibilidadEnCache($franjas);
ver('CA-11', (new D\IntegracionCitas($cache))->hayEspacio('med-1', 'medicina_interna', '2026-10-28 09:00'), 'Citas funciona igual con la réplica de solo lectura (sustituible por la interfaz)');

echo $fallas === 0 ? "\nResultado: todo lo esperado se cumple.\n" : "\nResultado: $fallas verificaciones fallaron.\n";
exit($fallas === 0 ? 0 : 1);
