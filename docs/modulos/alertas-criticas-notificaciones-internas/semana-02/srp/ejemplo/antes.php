<?php
// Semana 2 - SRP: diseño ANTES. Solo se analiza su código fuente; NO se ejecuta
// (usaría una base real, mail() y sleep()). Datos ficticios.
declare(strict_types=1);

namespace AlertasSrp\Antes;

final class GestorAlertas
{
    public function __construct(private \PDO $pdo) {}

    public function registrar(array $datos): array
    {
        foreach (['tenant', 'codigo_paciente', 'origen', 'id_origen', 'prioridad', 'descripcion'] as $campo) {
            if (empty($datos[$campo])) {
                throw new \InvalidArgumentException("Falta $campo");
            }
        }
        $st = $this->pdo->prepare('SELECT id FROM alerta WHERE tenant = :t AND origen = :o AND id_origen = :i');
        $st->execute([':t' => $datos['tenant'], ':o' => $datos['origen'], ':i' => $datos['id_origen']]);
        if ($fila = $st->fetch()) {
            return $fila;
        }
        $ahora = time();
        $st = $this->pdo->prepare('INSERT INTO alerta (tenant, codigo_paciente, origen, id_origen, prioridad, estado, enviada_en)
                                   VALUES (:t, :c, :o, :i, :p, \'ENVIADA\', to_timestamp(:a)) RETURNING id');
        $st->execute([':t' => $datos['tenant'], ':c' => $datos['codigo_paciente'], ':o' => $datos['origen'],
                      ':i' => $datos['id_origen'], ':p' => $datos['prioridad'], ':a' => $ahora]);
        $id = $st->fetchColumn();
        foreach ($this->destinatariosFijos(0) as $usuario) {
            $intentos = 0;
            while (!$this->enviarCorreo($usuario, "Alerta {$datos['codigo_paciente']}") && ++$intentos < 3) {
                sleep(60); // bloquea el registro mientras reintenta
            }
        }
        $this->guardarHistorial($id, 'creada', 'sistema');
        return ['id' => $id];
    }

    public function acusar(string $idAlerta, string $usuario): void
    {
        $st = $this->pdo->prepare('UPDATE alerta SET estado = \'ACUSADA\' WHERE id = :id');
        $st->execute([':id' => $idAlerta]);
        $this->guardarHistorial($idAlerta, 'acusada', $usuario); // acusar dos veces duplica el evento
    }

    public function revisarVencidas(): void
    {
        $limite = time() - 15 * 60; // plazo fijo, ignora la prioridad
        $st = $this->pdo->prepare('SELECT id, nivel FROM alerta WHERE estado = \'ENVIADA\' AND enviada_en <= to_timestamp(:l)');
        $st->execute([':l' => $limite]);
        foreach ($st->fetchAll() as $fila) {
            foreach ($this->destinatariosFijos($fila['nivel'] + 1) as $usuario) {
                $this->enviarCorreo($usuario, "Escalamiento {$fila['id']}");
            }
            $this->guardarHistorial($fila['id'], 'escalada', 'sistema');
        }
    }

    private function destinatariosFijos(int $nivel): array
    {
        return [0 => ['enf.turno01', 'med.tratante01'], 1 => ['jefe.servicio01'], 2 => ['coord.medico01']][$nivel] ?? [];
    }

    private function enviarCorreo(string $usuario, string $texto): bool
    {
        return mail("$usuario@hospital.example", 'Alerta crítica', $texto);
    }

    private function guardarHistorial(string $id, string $evento, string $actor): void
    {
        $st = $this->pdo->prepare('INSERT INTO alerta_evento (alerta_id, evento, actor, momento) VALUES (:a, :e, :r, now())');
        $st->execute([':a' => $id, ':e' => $evento, ':r' => $actor]);
    }
}
