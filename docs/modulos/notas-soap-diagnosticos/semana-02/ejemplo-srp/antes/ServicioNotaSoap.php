<?php

declare(strict_types=1);

namespace NotasSoap\Antes;

/**
 * Datos simulados del diseño ANTES. Representa la base de datos que la clase
 * usa directamente (en el sistema real serían consultas SQL dentro del servicio).
 */
final class BaseDatosFalsa
{
    /** @var array<string, array{id: string, tenant: string, roles: list<string>}> token => usuario */
    public array $tokens = [];
    /** @var array<string, list<string>> episodio => médicos asignados */
    public array $asignaciones = [];
    /** @var array<string, array{paciente: string, abierto: bool}> */
    public array $expedientes = [];
    /** @var array<string, string> episodio => paciente */
    public array $episodios = [];
    /** @var array<string, array<string, mixed>> */
    public array $notas = [];
    /** @var list<array<string, mixed>> */
    public array $diagnosticos = [];
    /** @var list<string> */
    public array $bitacora = [];
    public bool $fallarInsertDiagnostico = false;
}

/**
 * Diseño ANTES de aplicar SRP: una sola clase hace todo el flujo
 * «registro de nota SOAP y asociación de diagnóstico clínico».
 *
 * Razones para cambiar (cada una es una responsabilidad distinta):
 *  1. cómo se lee el token y el tenant (HTTP / autenticación)
 *  2. quién puede escribir (rol y asignación al episodio)
 *  3. cuándo un expediente está disponible
 *  4. qué es una nota SOAP completa
 *  5. qué es un diagnóstico CIE-10 válido
 *  6. cómo se guarda en la base de datos
 *  7. qué se escribe en la bitácora
 *  8. cómo se arma la respuesta HTTP
 */
final class ServicioNotaSoap
{
    public function __construct(private readonly BaseDatosFalsa $bd)
    {
    }

    /** @param array{headers: array<string, string>, body: array<string, mixed>} $peticion */
    public function registrar(array $peticion): array
    {
        // 1. HTTP y autenticación
        $tenant = $peticion['headers']['X-Tenant-ID'] ?? '';
        if ($tenant === '') {
            return ['status' => 400, 'body' => ['error' => ['code' => 'tenant_requerido']]];
        }
        $token = preg_replace('/^Bearer\s+/', '', $peticion['headers']['Authorization'] ?? '');
        $usuario = $this->bd->tokens[$token] ?? null;
        if ($usuario === null) {
            return ['status' => 401, 'body' => ['error' => ['code' => 'token_invalido']]];
        }
        $b = $peticion['body'];
        // Defecto: el autor se toma del cuerpo si viene, y no del token.
        $autor = (string) ($b['author_user_ref_id'] ?? $usuario['id']);

        // 2. Permisos
        $episodio = (string) ($b['care_episode_id'] ?? '');
        if ($usuario['tenant'] !== $tenant || !in_array('medico', $usuario['roles'], true)
            || !in_array($usuario['id'], $this->bd->asignaciones[$episodio] ?? [], true)) {
            return ['status' => 403, 'body' => ['error' => ['code' => 'acceso_denegado']]];
        }

        // 3. Expediente
        $expediente = $this->bd->expedientes[(string) ($b['medical_record_id'] ?? '')] ?? null;
        if ($expediente === null || !$expediente['abierto'] || ($this->bd->episodios[$episodio] ?? null) !== $expediente['paciente']) {
            return ['status' => 409, 'body' => ['error' => ['code' => 'expediente_no_disponible']]];
        }

        // 4. Reglas de la nota
        foreach (['subjetivo', 'objetivo', 'analisis', 'plan'] as $seccion) {
            if (trim((string) ($b[$seccion] ?? '')) === '') {
                return ['status' => 422, 'body' => ['error' => ['code' => 'nota_incompleta']]];
            }
        }

        // 6. Persistencia de la nota (antes de validar el diagnóstico)
        $notaId = sprintf('nota-%03d', count($this->bd->notas) + 1);
        $this->bd->notas[$notaId] = ['id' => $notaId, 'autor' => $autor, 'estado' => 'borrador', 'version' => 1];

        // 5. Reglas del diagnóstico
        $dx = $b['diagnostico'] ?? [];
        $codigo = strtoupper(trim((string) ($dx['code'] ?? '')));
        if (preg_match('/^[A-Z][0-9]{2}(\.[0-9]{1,2})?$/', $codigo) !== 1 || trim((string) ($dx['description'] ?? '')) === ''
            || !in_array($dx['type'] ?? '', ['primary', 'secondary', 'differential'], true)) {
            // Defecto: la nota ya quedó guardada sin diagnóstico (nota huérfana).
            return ['status' => 422, 'body' => ['error' => ['code' => 'diagnostico_invalido']]];
        }

        // 6. Persistencia del diagnóstico
        if ($this->bd->fallarInsertDiagnostico) {
            // Defecto: no hay transacción, la nota se queda guardada.
            return ['status' => 500, 'body' => ['error' => ['code' => 'error_persistencia']]];
        }
        $this->bd->diagnosticos[] = ['nota' => $notaId, 'codigo' => $codigo];

        // 7. Bitácora
        $this->bd->bitacora[] = "NOTA_REGISTRADA {$notaId} {$autor}";

        // 8. Respuesta HTTP
        return ['status' => 201, 'body' => ['clinical_note_id' => $notaId, 'author_user_ref_id' => $autor, 'status' => 'borrador']];
    }
}
