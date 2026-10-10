<?php

declare(strict_types=1);

use NotasSoap\Dominio\Excepciones\DiagnosticoInvalido;
use NotasSoap\Dominio\Excepciones\NotaFirmada;
use NotasSoap\Dominio\Excepciones\NotaIncompleta;
use NotasSoap\Dominio\Modelo\Diagnostico;
use NotasSoap\Dominio\Modelo\NotaSoap;
use NotasSoap\Dominio\Modelo\SeccionesSoap;
use NotasSoap\Dominio\Modelo\TipoDiagnostico;

$secciones = static fn (array $c = []): SeccionesSoap => new SeccionesSoap(
    $c['S'] ?? 'Tos y fiebre', $c['O'] ?? 'T 38.6 °C', $c['A'] ?? 'Probable neumonía', $c['P'] ?? 'Radiografía',
);
$nota = static fn (): NotaSoap => NotaSoap::registrar('n-1', 'exp-carlos', 'epi-100', 'dra-mendez', $secciones(), new DateTimeImmutable('2026-10-12T09:30:00-06:00'));

prueba('DOM-01', 'Una nota nueva nace en borrador, versión 1 y sin fecha de firma (R-07)', function () use ($nota): void {
    $n = $nota();
    igual(NotaSoap::BORRADOR, $n->estado, 'estado');
    igual(1, $n->version, 'versión');
    igual(null, $n->firmadaEn, 'firmada en');
});

prueba('DOM-02', 'Las cuatro secciones S, O, A y P son obligatorias (R-04, CA-07)', function () use ($secciones): void {
    foreach (['S' => 'subjetivo', 'O' => 'objetivo', 'A' => 'analisis', 'P' => 'plan'] as $clave => $nombre) {
        $e = lanza(NotaIncompleta::class, fn () => $secciones([$clave => '   ']), "sección $clave vacía");
        afirmar(str_contains($e->getMessage(), $nombre), "el mensaje debe nombrar la sección $nombre");
    }
});

prueba('DOM-03', 'El código CIE-10 debe tener formato válido (R-05, CA-08)', function () use ($nota): void {
    foreach (['J189', 'neumonia', '18.9', 'J18.999', ''] as $malo) {
        lanza(DiagnosticoInvalido::class, fn () => Diagnostico::registrar('d-1', $nota(), $malo, 'Neumonía', 'primary'), "código '$malo'");
    }
    igual('J18.9', Diagnostico::registrar('d-1', $nota(), ' j18.9 ', 'Neumonía', 'primary')->codigo, 'el código se normaliza');
});

prueba('DOM-04', 'El tipo de diagnóstico solo puede ser primary, secondary o differential (R-05, CA-09)', function () use ($nota): void {
    lanza(DiagnosticoInvalido::class, fn () => Diagnostico::registrar('d-1', $nota(), 'J18.9', 'Neumonía', 'principal'), 'tipo inválido');
    lanza(DiagnosticoInvalido::class, fn () => Diagnostico::registrar('d-1', $nota(), 'J18.9', '  ', 'primary'), 'descripción vacía');
    igual(TipoDiagnostico::Diferencial, Diagnostico::registrar('d-1', $nota(), 'J18.9', 'Neumonía', 'differential')->tipo, 'tipo');
});

prueba('DOM-05', 'El diagnóstico hereda nota, episodio y autor de la nota (R-05)', function () use ($nota): void {
    $d = Diagnostico::registrar('d-1', $nota(), 'J18.9', 'Neumonía', 'primary');
    igual(['n-1', 'epi-100', 'dra-mendez'], [$d->notaId, $d->episodioId, $d->autorId], 'nota, episodio y autor');
});

prueba('DOM-06', 'Firmar cambia el estado y guarda la fecha sin mutar el original (R-07)', function () use ($nota): void {
    $original = $nota();
    $firmada = $original->firmar(new DateTimeImmutable('2026-10-12T10:00:00-06:00'));
    igual(NotaSoap::FIRMADA, $firmada->estado, 'estado firmado');
    igual('2026-10-12T10:00:00-06:00', $firmada->firmadaEn?->format(DATE_ATOM), 'fecha de firma');
    igual(NotaSoap::BORRADOR, $original->estado, 'el objeto original no cambia');
});

prueba('DOM-07', 'Una nota firmada no se puede volver a firmar ni modificar (R-07, CA-11)', function () use ($nota): void {
    $firmada = $nota()->firmar(new DateTimeImmutable());
    lanza(NotaFirmada::class, fn () => $firmada->firmar(new DateTimeImmutable()), 'segunda firma');
});

prueba('DOM-08', 'Las secciones se guardan en un solo campo content y se reconstruyen igual', function () use ($secciones): void {
    $s = $secciones(['O' => 'T 38.6 °C, estertores']);
    $r = SeccionesSoap::desdeContenido($s->comoContenido());
    igual([$s->subjetivo, $s->objetivo, $s->analisis, $s->plan], [$r->subjetivo, $r->objetivo, $r->analisis, $r->plan], 'secciones');
    afirmar(str_contains($s->comoContenido(), '°C'), 'conserva acentos y símbolos sin escapar');
});
