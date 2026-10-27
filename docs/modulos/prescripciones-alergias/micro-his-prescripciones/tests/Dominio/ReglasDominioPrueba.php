<?php

declare(strict_types=1);

use Prescripciones\Dominio\Modelo\Alergia;
use Prescripciones\Dominio\Modelo\ExpedienteAlergias;
use Prescripciones\Dominio\Modelo\Hallazgo;
use Prescripciones\Dominio\Modelo\Prescripcion;
use Prescripciones\Dominio\Modelo\ResultadoVerificacion;
use Prescripciones\Dominio\Reglas\ValidadorPrescripcion;
use Prescripciones\Dominio\Reglas\VerificadorAlergias;

$medicamentos = static function (string ...$codigos): array {
    $catalogo = Escenario::catalogo();

    return array_map(static fn (string $c) => $catalogo->buscar($c), $codigos);
};

prueba('DOM-01', 'R-01: el validador informa cada campo inválido y acepta un ítem completo', function (): void {
    $catalogo = ['PARA500' => Escenario::catalogo()->buscar('PARA500'), 'RANI150' => Escenario::catalogo()->buscar('RANI150'), 'X' => null];
    $v = new ValidadorPrescripcion();
    igual(['campos' => [], 'dosis' => []], $v->validar([Escenario::item('PARA500')], $catalogo), 'ítem completo');
    igual(['items[0].dosis', 'items[0].unidad', 'items[0].frecuencia_horas', 'items[0].via', 'items[0].dias', 'items[1].medicamento',
        'items[2].medicamento', 'items[3]'],
        $v->validar([Escenario::item('PARA500', -1, 5, 'iv', 91, 'g'), Escenario::item('RANI150'), Escenario::item('X'), 'texto'], $catalogo)['campos'],
        'campos');
    igual(['items'], $v->validar([], $catalogo)['campos'], 'sin ítems');
});

prueba('DOM-02', 'R-02: la dosis diaria máxima es inclusiva (4000 sí, 4000,5 no)', function (): void {
    $catalogo = ['PARA500' => Escenario::catalogo()->buscar('PARA500')];
    $v = new ValidadorPrescripcion();
    igual([], $v->validar([Escenario::item('PARA500', 1000, 6)], $catalogo)['dosis'], '1000 × 4 = 4000');
    igual(['items[0]'], $v->validar([Escenario::item('PARA500', 1000.125, 6)], $catalogo)['dosis'], '1000,125 × 4 = 4000,5');
    igual(['items[0]'], $v->validar([Escenario::item('PARA500', 1000, 4)], $catalogo)['dosis'], '1000 × 6 = 6000');
});

prueba('DOM-03', 'R-04: coincidencia directa por código y por nombre normalizado; bloquea', function () use ($medicamentos): void {
    $v = new VerificadorAlergias();
    $porCodigo = $v->verificar($medicamentos('AMOX500'), new ExpedienteAlergias(true, Escenario::alergias()['P-001']), []);
    $porNombre = $v->verificar($medicamentos('AMOX500'), new ExpedienteAlergias(true, Escenario::alergias()['P-007']), []);
    igual([ResultadoVerificacion::BLOQUEO, 'BLOQUEO_ALERGIA'], [$porCodigo->estado, $porCodigo->codigoBloqueo()], 'por código');
    igual([ResultadoVerificacion::BLOQUEO, Hallazgo::DIRECTA], [$porNombre->estado, $porNombre->hallazgos[0]->tipo], 'por nombre');
    igual('amoxicilina', VerificadorAlergias::normalizar('  AMOXICILÍNA '), 'normalizar');
});

prueba('DOM-04', 'R-05: cruzada no grave = advertencia; life_threatening o unknown = bloqueo', function () use ($medicamentos): void {
    $v = new VerificadorAlergias();
    $grupos = ['Penicilina' => 'betalactamicos', 'penicilina' => 'betalactamicos', 'N02BA01' => 'aines'];
    $moderada = $v->verificar($medicamentos('CEFA500'), new ExpedienteAlergias(true, Escenario::alergias()['P-002']), $grupos);
    $grave = $v->verificar($medicamentos('IBU400'), new ExpedienteAlergias(true, Escenario::alergias()['P-004']), $grupos);
    $desconocida = $v->verificar($medicamentos('CEFA500'), new ExpedienteAlergias(true, Escenario::alergias()['P-006']), $grupos);
    igual(ResultadoVerificacion::ADVERTENCIA, $moderada->estado, 'moderate');
    igual([ResultadoVerificacion::BLOQUEO, 'BLOQUEO_ALERGIA_CRUZADA'], [$grave->estado, $grave->codigoBloqueo()], 'life_threatening');
    igual(ResultadoVerificacion::BLOQUEO, $desconocida->estado, 'unknown');
});

prueba('DOM-05', 'Solo cuentan las alergias activas; sin grupo conocido no hay cruzada', function () use ($medicamentos): void {
    $v = new VerificadorAlergias();
    $resuelta = $v->verificar($medicamentos('AMOX500'), new ExpedienteAlergias(true, Escenario::alergias()['P-005']), []);
    $sinGrupo = $v->verificar($medicamentos('CEFA500'), new ExpedienteAlergias(true, Escenario::alergias()['P-002']), []);
    igual([ResultadoVerificacion::SIN_HALLAZGOS, ResultadoVerificacion::SIN_HALLAZGOS], [$resuelta->estado, $sinGrupo->estado], 'estados');
});

prueba('DOM-06', 'Una alergia sin código ni nombre, o con severidad fuera del esquema, no se puede construir', function (): void {
    lanza(\InvalidArgumentException::class, static fn () => new Alergia(' ', null, 'allergy', 'severe', 'active'), 'sin alérgeno');
    lanza(\InvalidArgumentException::class, static fn () => new Alergia('X', null, 'allergy', 'grave', 'active'), 'severidad inválida');
});

prueba('DOM-07', 'R-11: la huella distingue el contenido y no depende de la clave', function (): void {
    $a = Escenario::solicitud('P-005', [Escenario::item('PARA500')], clave: 'k1');
    $b = Escenario::solicitud('P-005', [Escenario::item('PARA500')], clave: 'k2');
    $c = Escenario::solicitud('P-005', [Escenario::item('PARA500', 750)], clave: 'k1');
    igual($a->huella(), $b->huella(), 'mismo contenido');
    afirmar($a->huella() !== $c->huella(), 'otro contenido, otra huella');
});

prueba('DOM-08', 'R-10: la prescripción es readonly y no tiene métodos que la cambien', function (): void {
    $clase = new \ReflectionClass(Prescripcion::class);
    afirmar($clase->isReadOnly(), 'debe ser readonly');
    $publicos = array_map(static fn (\ReflectionMethod $m): string => $m->getName(), $clase->getMethods(\ReflectionMethod::IS_PUBLIC));
    igual(['__construct', 'conId', 'aArreglo'], $publicos, 'métodos públicos');
});
