<?php

declare(strict_types=1);

/*
 * ANTES de aplicar DIP: la política de calidad (módulo de alto nivel) depende de los detalles (módulos de bajo nivel).
 * Es como se escribe un gate «rápido» dentro de un script de CI. Funciona en el runner de GitHub y en ningún otro lado:
 *   - crea ella misma el cliente de GitHub Actions, el lector de JUnit, el cliente de S3 y la conexión a la base;
 *   - lee variables de entorno y archivos de /tmp, y pregunta la hora al sistema;
 *   - para cambiar de proveedor de CI (GitLab) o de almacén (MinIO) hay que editar la REGLA de calidad.
 * No se ejecuta en este ejemplo: no hay red, ni runner, ni bucket. Es justamente el problema.
 */

namespace Antes;

final class EvaluadorGate
{
    public function evaluar(string $runId, string $digest): array
    {
        // Detalle 1: proveedor de CI concreto, con su token del entorno.
        $github = new GitHubActionsApi((string) getenv('GITHUB_TOKEN'), 'compilations-teams/sistema-hospitalario-integrado');
        $run = $github->obtenerRun($runId);
        if ($run['status'] !== 'completed') {
            return ['decision' => 'RECHAZADO', 'causas' => ['la ejecución no terminó']];
        }

        // Detalle 2: formato y ubicación del reporte, fijos.
        $xml = simplexml_load_string((string) file_get_contents("/tmp/e2e/$runId/junit.xml"));

        // Detalle 3: almacén de artefactos concreto.
        $s3 = new S3Cliente('us-east-1', (string) getenv('AWS_KEY'), (string) getenv('AWS_SECRET'));

        // Detalle 4: el reloj del sistema (la regla «ejecución de 24 h o menos» no se puede probar).
        $horas = (time() - strtotime($run['updated_at'])) / 3600;

        $criticosFallidos = [];
        $pasan = 0;
        $total = 0;
        $inestables = 0;
        foreach ($xml->testsuite->testcase as $caso) {
            $critico = str_contains((string) $caso['name'], '@critico');
            $fallo = isset($caso->failure);
            $reintentos = (int) ($caso->properties->property['value'] ?? 0);
            if ($fallo && !$s3->existe('his-e2e-artefactos', "$runId/" . $caso['name'] . '.zip')) {
                $criticosFallidos[] = (string) $caso['name'];             // sin evidencia: cuenta como falla
            }
            if ($critico && ($fallo || $reintentos > 0)) {
                $criticosFallidos[] = (string) $caso['name'];
            }
            $inestables += !$fallo && $reintentos > 0 ? 1 : 0;
            if (!$critico) {
                $total++;
                $pasan += $fallo ? 0 : 1;
            }
        }

        $causas = [];
        if ($criticosFallidos !== []) {
            $causas[] = 'críticos que no pasan: ' . implode(', ', array_unique($criticosFallidos));
        }
        if ($total > 0 && $pasan / $total < 0.95) {
            $causas[] = 'tasa menor que 95 %';
        }
        if ($inestables > 3) {
            $causas[] = 'más de 3 inestables';
        }
        if ($horas > 24) {
            $causas[] = 'ejecución de más de 24 h';
        }
        if ($run['head_sha'] === '' || $digest === '') {
            $causas[] = 'sin digest';                                      // y nunca compara el digest con el probado
        }

        // Detalle 5: persistencia concreta, con su DSN del entorno.
        $pdo = new \PDO((string) getenv('QA_DSN'));
        $pdo->prepare('INSERT INTO evaluaciones (run_id, digest, decision, creada) VALUES (?, ?, ?, ?)')
            ->execute([$runId, $digest, $causas === [] ? 'APROBADO' : 'RECHAZADO', date('c')]);

        return ['decision' => $causas === [] ? 'APROBADO' : 'RECHAZADO', 'causas' => $causas];
    }
}
