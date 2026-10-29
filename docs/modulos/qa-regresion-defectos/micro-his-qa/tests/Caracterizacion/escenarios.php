<?php

declare(strict_types=1);

/*
 * Semana 7 · Escenarios de caracterización de «registrar resultado», el punto más acoplado. Cada uno corre sobre un
 * Escenario nuevo y devuelve lo OBSERVABLE: tipo, código y datos de la salida, y los efectos (intentos, defectos,
 * evidencias en el almacén, trazas). bin/caracterizar.php los grabó en esperado.json ANTES del refactor.
 */

use Qa\Dominio\Modelo\EstadoDefecto;

if (!function_exists('caracterizar')) {
    /** @return array<string, array<string, mixed>> */
    function caracterizar(): array
    {
        $png = Escenario::PNG;
        $junit = '<testsuite name="H4" tests="3" failures="1" errors="0"></testsuite>';
        $defecto = ['titulo' => 'Nota editable', 'pasos' => ['Abrir', 'Editar'], 'severidad' => 'MAYOR'];
        $escenarios = [
            'pasa sin evidencia' => [['caso' => 'TC-107', 'resultado' => 'PASA']],
            'pasa con captura' => [['caso' => 'TC-107', 'resultado' => 'PASA', 'evidencias' => [['tipo' => 'captura', 'bytes' => $png]]]],
            'falla completa' => [['caso' => 'TC-107', 'resultado' => 'FALLA', 'evidencias' => [['tipo' => 'captura', 'bytes' => $png]], 'defecto' => $defecto]],
            'falla con junit y log' => [['caso' => 'TC-107', 'resultado' => 'FALLA', 'evidencias' => [['tipo' => 'junit', 'bytes' => $junit],
                ['tipo' => 'registro', 'bytes' => 'SQLSTATE[23505]', 'clasificacion' => 'DATOS_CLINICOS']], 'defecto' => $defecto]],
            'falla sin evidencia' => [['caso' => 'TC-107', 'resultado' => 'FALLA', 'defecto' => $defecto]],
            'falla sin defecto' => [['caso' => 'TC-107', 'resultado' => 'FALLA', 'evidencias' => [['tipo' => 'captura', 'bytes' => $png]]]],
            'falla con defecto sin título' => [['caso' => 'TC-107', 'resultado' => 'FALLA', 'evidencias' => [['tipo' => 'captura', 'bytes' => $png]],
                'defecto' => ['titulo' => ' ', 'pasos' => ['a', ''], 'severidad' => 'MAYOR']]],
            'falla con severidad inválida' => [['caso' => 'TC-107', 'resultado' => 'FALLA', 'evidencias' => [['tipo' => 'captura', 'bytes' => $png]],
                'defecto' => ['titulo' => 'X', 'pasos' => ['a'], 'severidad' => 'URGENTE']]],
            'bloqueado sin evidencia' => [['caso' => 'TC-107', 'resultado' => 'BLOQUEADO']],
            'bloqueado solo con referencia' => [['caso' => 'TC-107', 'resultado' => 'BLOQUEADO', 'referencias' => [['url' => 'https://ci.hospital.gt/88']]]],
            'bloqueado con captura y referencia' => [['caso' => 'TC-107', 'resultado' => 'BLOQUEADO', 'evidencias' => [['tipo' => 'captura', 'bytes' => $png]],
                'referencias' => [['url' => 'https://ci.hospital.gt/88', 'descripcion' => 'pipeline']]]],
            'referencia sin https' => [['caso' => 'TC-107', 'resultado' => 'PASA', 'referencias' => [['url' => 'http://ci/88']]]],
            'no aplica sin motivo' => [['caso' => 'TC-107', 'resultado' => 'NO_APLICA']],
            'no aplica con motivo' => [['caso' => 'TC-107', 'resultado' => 'NO_APLICA', 'motivo' => '  El build no toca notas  ']],
            'resultado desconocido' => [['caso' => 'TC-107', 'resultado' => 'APROBADO']],
            'caso fuera del alcance' => [['caso' => 'TC-101', 'resultado' => 'PASA']],
            'captura que no es imagen' => [['caso' => 'TC-107', 'resultado' => 'FALLA', 'evidencias' => [['tipo' => 'captura', 'bytes' => 'GIF89a']], 'defecto' => $defecto]],
            'evidencia vacía' => [['caso' => 'TC-107', 'resultado' => 'FALLA', 'evidencias' => [['tipo' => 'registro', 'bytes' => '']], 'defecto' => $defecto]],
            'tipo de evidencia desconocido' => [['caso' => 'TC-107', 'resultado' => 'FALLA', 'evidencias' => [['tipo' => 'video', 'bytes' => 'x']], 'defecto' => $defecto]],
            'clasificación desconocida' => [['caso' => 'TC-107', 'resultado' => 'FALLA', 'evidencias' => [['tipo' => 'captura', 'bytes' => $png, 'clasificacion' => 'SECRETO']], 'defecto' => $defecto]],
            'evidencia de más de 5 MB' => [['caso' => 'TC-107', 'resultado' => 'FALLA', 'evidencias' => [['tipo' => 'registro', 'bytes' => str_repeat('a', 5 * 1024 * 1024 + 1)]], 'defecto' => $defecto]],
            'grande después de una inválida' => [['caso' => 'TC-107', 'resultado' => 'FALLA', 'evidencias' => [['tipo' => 'captura', 'bytes' => 'GIF'],
                ['tipo' => 'registro', 'bytes' => str_repeat('a', 5 * 1024 * 1024 + 1)]], 'defecto' => $defecto]],
            'verifica corregido con PASA' => [['caso' => 'TC-107', 'resultado' => 'PASA', 'verifica_defecto' => 1], EstadoDefecto::CORREGIDO],
            'verifica corregido con FALLA' => [['caso' => 'TC-107', 'resultado' => 'FALLA', 'evidencias' => [['tipo' => 'captura', 'bytes' => $png]], 'verifica_defecto' => 1], EstadoDefecto::CORREGIDO],
            'verifica corregido con BLOQUEADO' => [['caso' => 'TC-107', 'resultado' => 'BLOQUEADO', 'evidencias' => [['tipo' => 'captura', 'bytes' => $png]], 'verifica_defecto' => 1], EstadoDefecto::CORREGIDO],
            'verifica un abierto' => [['caso' => 'TC-107', 'resultado' => 'PASA', 'verifica_defecto' => 1], EstadoDefecto::ABIERTO],
            'verifica inexistente' => [['caso' => 'TC-107', 'resultado' => 'PASA', 'verifica_defecto' => 99]],
            'verifica de otro caso' => [['caso' => 'TC-108', 'resultado' => 'PASA', 'verifica_defecto' => 1], EstadoDefecto::CORREGIDO],
            'verifica como texto' => [['caso' => 'TC-107', 'resultado' => 'PASA', 'verifica_defecto' => '1'], EstadoDefecto::CORREGIDO],
        ];
        $r = [];
        foreach ($escenarios as $nombre => $esc) {
            $e = new Escenario();
            $id = $e->campanaEnCurso();
            if (isset($esc[1])) {
                $e->sembrarDefecto($id, 'TC-107', $esc[1]);
            }
            $r[$nombre] = observar($e, $e->uc['registrar']->ejecutar('tito-tester', 'hosp-norte', $id, 'car-' . substr(md5($nombre), 0, 12), $esc[0]), $id);
        }
        // Secuencias: idempotencia y fallas de infraestructura.
        $e = new Escenario();
        $id = $e->campanaEnCurso();
        $datos = ['caso' => 'TC-107', 'resultado' => 'FALLA', 'evidencias' => [['tipo' => 'captura', 'bytes' => $png]], 'defecto' => $defecto];
        $e->uc['registrar']->ejecutar('tito-tester', 'hosp-norte', $id, 'car-repetida', $datos);
        $r['repetición idéntica'] = observar($e, $e->uc['registrar']->ejecutar('tito-tester', 'hosp-norte', $id, 'car-repetida', $datos), $id);
        $r['misma clave, otro contenido'] = observar($e, $e->uc['registrar']->ejecutar('tito-tester', 'hosp-norte', $id, 'car-repetida', ['caso' => 'TC-108', 'resultado' => 'PASA']), $id);
        $r['segundo intento del mismo caso'] = observar($e, $e->uc['registrar']->ejecutar('tito-tester', 'hosp-norte', $id, 'car-segundo', ['caso' => 'TC-107', 'resultado' => 'PASA']), $id);
        $e = new Escenario();
        $id = $e->campanaEnCurso();
        $e->almacen->caido = true;
        $r['almacén caído'] = observar($e, $e->uc['registrar']->ejecutar('tito-tester', 'hosp-norte', $id, 'car-almacen', $datos), $id);
        $e = new Escenario();
        $id = $e->campanaEnCurso();
        $e->auditoria->fallarEn = 'registrar';
        $r['traza que falla'] = observar($e, $e->uc['registrar']->ejecutar('tito-tester', 'hosp-norte', $id, 'car-traza', $datos), $id);
        $e = new Escenario();
        $id = $e->campanaEnCurso();
        $r['sin permiso'] = observar($e, $e->uc['registrar']->ejecutar('dev-luis', 'hosp-norte', $id, 'car-permiso', $datos), $id);
        $r['otro tenant'] = observar($e, $e->uc['registrar']->ejecutar('tester-sur', 'hosp-sur', $id, 'car-tenant', $datos), $id);
        $r['clave inválida'] = observar($e, $e->uc['registrar']->ejecutar('tito-tester', 'hosp-norte', $id, 'corta', $datos), $id);

        return $r;
    }

    /** @return array<string, mixed> */
    function observar(Escenario $e, \Qa\Aplicacion\Resultados\Salida $s, int $campana): array
    {
        $defectos = array_map(static fn ($d): array => [$d->id, $d->casoId, $d->estado()->value, $d->propuesta->value, $d->pasos, $d->conEvidencia],
            $e->defectos->real->deCampana('hosp-norte', $campana));

        return [
            'tipo' => $s->tipo,
            'codigo' => $s->codigo,
            'datos' => $s->datos,
            'intentos' => array_map(static fn ($i): array => [$i->casoId, $i->numero, $i->resultado->value, count($i->evidencias),
                array_map(static fn ($ev): array => [$ev->tipo, $ev->clasificacion, $ev->tamano], $i->evidencias), count($i->referencias),
                $i->defectoId, $i->verificaDefecto, $i->motivo], $e->intentos()),
            'defectos' => $defectos,
            'almacen' => $e->almacen->cantidad(),
            'trazas' => $e->memAuditoria()->acciones(),
        ];
    }
}
