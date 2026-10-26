<?php

declare(strict_types=1);

// Semana 15 — Unitarias: cada regla aislada, sin caso de uso ni HTTP, con particiones y valores límite.

use NotasSoap\Dominio\Excepciones\{DiagnosticoInvalido, MotivoCorreccionInsuficiente, NotaFirmada, NotaIncompleta, NotaNoFirmada};
use NotasSoap\Dominio\Modelo\{Diagnostico, NotaSoap, SeccionesSoap};
use NotasSoap\Persistencia\EnMemoria\RepositorioExpedientesEnMemoria;

$ahora = new DateTimeImmutable('2026-10-25T08:00:00-06:00');
$secciones = static fn (string $s = 'Tos'): SeccionesSoap => new SeccionesSoap($s, 'T 38 °C', 'Bronquitis', 'Control');
$nota = static fn (): NotaSoap => NotaSoap::registrar('n-1', 'exp-carlos', 'epi-100', 'dra-mendez', $secciones(), $ahora);

prueba('UNI-01', 'Secciones SOAP: vacía, solo espacios o salto de línea → nota_incompleta; 1 carácter basta; el contenido guardado conserva tildes', function () use ($secciones): void {
    foreach (['', '   ', "\t\n"] as $vacia) {
        lanza(NotaIncompleta::class, fn () => $secciones($vacia), 'sección ' . json_encode($vacia));
    }
    $s = $secciones('ñ');
    igual('ñ', SeccionesSoap::desdeContenido($s->comoContenido())->subjetivo, 'ida y vuelta del contenido');
});

prueba('UNI-02', 'CIE-10 por límites: J18, J18.9, J18.99 y « j18.9 » válidos (se normaliza); J1, J18., J18.999, 18.9 y JJ8.9 inválidos', function () use ($nota): void {
    foreach (['J18' => 'J18', 'J18.9' => 'J18.9', 'J18.99' => 'J18.99', ' j18.9 ' => 'J18.9'] as $entrada => $guardado) {
        igual($guardado, Diagnostico::registrar('d', $nota(), $entrada, 'Neumonía', 'primary')->codigo, "código $entrada");
    }
    foreach (['J1', 'J18.', 'J18.999', '18.9', 'JJ8.9', ''] as $malo) {
        lanza(DiagnosticoInvalido::class, fn () => Diagnostico::registrar('d', $nota(), $malo, 'x', 'primary'), "código $malo");
    }
});

prueba('UNI-03', 'Diagnóstico: solo primary, secondary o differential; descripción obligatoria; hereda nota, episodio y autor (autoría clínica)', function () use ($nota): void {
    foreach (['primary', 'secondary', 'differential'] as $tipo) {
        igual($tipo, Diagnostico::registrar('d', $nota(), 'J18.9', 'x', $tipo)->tipo->value, "tipo $tipo");
    }
    foreach ([['PRIMARY', 'x'], ['principal', 'x'], ['primary', '   ']] as [$tipo, $desc]) {
        lanza(DiagnosticoInvalido::class, fn () => Diagnostico::registrar('d', $nota(), 'J18.9', $desc, $tipo), "tipo $tipo / descripción");
    }
    $d = Diagnostico::registrar('d', $nota(), 'J18.9', 'Neumonía', 'primary');
    igual(['n-1', 'epi-100', 'dra-mendez'], [$d->notaId, $d->episodioId, $d->autorId], 'herencia');
});

prueba('UNI-04', 'Versionado: firmar no muta la original y no se repite; corregir solo una firmada; la versión nueva hereda expediente, episodio y autor; motivo 9 → no, 10 → sí (caracteres, no bytes)', function () use ($nota, $secciones, $ahora): void {
    $borrador = $nota();
    $firmada = $borrador->firmar($ahora);
    igual(['draft', 'signed'], [$borrador->estado, $firmada->estado], 'firmar devuelve otra nota');
    lanza(NotaFirmada::class, fn () => $firmada->firmar($ahora), 'segunda firma');
    lanza(NotaNoFirmada::class, fn () => NotaSoap::corregir($borrador, 'n-2', $secciones(), 'Motivo suficiente', $ahora), 'corregir borrador');
    foreach (['123456789', '   123456789   '] as $corto) {
        lanza(MotivoCorreccionInsuficiente::class, fn () => NotaSoap::corregir($firmada, 'n-2', $secciones(), $corto, $ahora), "motivo «{$corto}»");
    }
    $v2 = NotaSoap::corregir($firmada, 'n-2', $secciones(), 'ññññññññññ', $ahora);
    igual(['exp-carlos', 'epi-100', 'dra-mendez', 2, 'draft', 'n-1'], [$v2->expedienteId, $v2->episodioId, $v2->autorId, $v2->version, $v2->estado, $v2->reemplazaA], 'versión 2');
});

prueba('UNI-05', 'Expediente disponible: abierto y episodio del mismo paciente → sí; cerrado, episodio de otro paciente, expediente o episodio inexistente → no', function (): void {
    $r = new RepositorioExpedientesEnMemoria(['exp-a' => ['paciente' => 'p-1', 'abierto' => true], 'exp-c' => ['paciente' => 'p-1', 'abierto' => false]],
        ['epi-1' => 'p-1', 'epi-2' => 'p-2']);
    igual([true, false, false, false, false],
        [$r->estaDisponible('exp-a', 'epi-1'), $r->estaDisponible('exp-c', 'epi-1'), $r->estaDisponible('exp-a', 'epi-2'), $r->estaDisponible('exp-x', 'epi-1'), $r->estaDisponible('exp-a', 'epi-x')], 'particiones');
});
