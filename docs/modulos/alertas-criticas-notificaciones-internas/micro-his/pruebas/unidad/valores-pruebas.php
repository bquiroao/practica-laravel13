<?php
/** Objetos de valor y enumeraciones del dominio. @var Pruebas $t */
declare(strict_types=1);

use AlertasCriticas\Domain\{CodigoPaciente, EstadoAlerta, IdAlerta, Prioridad, ReglaDominioException};

$t->prueba('CodigoPaciente normaliza espacios y minúsculas', function () use ($t) {
    $t->igual('PAC-00451', (new CodigoPaciente('  pac-00451 '))->valor);
});

$t->prueba('CodigoPaciente rechaza nombres y formatos incompletos', function () use ($t) {
    foreach (['Juan Pérez', 'PAC-451', 'PAC-004510', ''] as $malo) {
        $t->lanza(ReglaDominioException::class, fn () => new CodigoPaciente($malo));
    }
});

$t->prueba('Prioridad acepta minúsculas y rechaza valores desconocidos', function () use ($t) {
    $t->igual(Prioridad::CRITICA, Prioridad::desde(' critica '));
    $e = $t->lanza(ReglaDominioException::class, fn () => Prioridad::desde('BAJA'));
    $t->verdadero(str_contains($e->getMessage(), '«BAJA»'), 'El mensaje debe mostrar el valor recibido');
});

$t->prueba('EstadoAlerta: solo ENVIADA y ESCALADA esperan acuse', function () use ($t) {
    $esperan = array_map(fn ($e) => $e->value, array_filter(EstadoAlerta::cases(), fn ($e) => $e->esperaAcuse()));
    $t->igual(['ENVIADA', 'ESCALADA'], array_values($esperan));
});

$t->prueba('IdAlerta genera UUID v4 distintos', function () use ($t) {
    $a = IdAlerta::nuevo();
    $t->verdadero((bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $a), "UUID inválido: $a");
    $t->verdadero($a !== IdAlerta::nuevo(), 'Dos ids no deben repetirse');
});
