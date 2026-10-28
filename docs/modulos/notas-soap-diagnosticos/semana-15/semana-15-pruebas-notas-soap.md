# Semana 15 — Pruebas unitarias, de caja blanca y de caja negra de Notas SOAP

**Estudiante:** Joshua Eduardo García Reyes (`jgarciar73@miumg.edu.gt`, `jgarciar73-svg`) · **Módulo:** ASII-11 Notas SOAP y diagnósticos · **Flujo:** «registro de nota SOAP y asociación de diagnóstico clínico»

| Pedido | Dónde | Resultado |
|---|---|---|
| Matriz RF/RNF → prueba | §1 | RF-01..RF-16 y RNF-01..RNF-07 |
| Mínimo 3 unitarias | **5**: UNI-01..05 en [`tests/Dominio/UnitariasNotasPrueba.php`](../micro-his-notas-soap/tests/Dominio/UnitariasNotasPrueba.php) | 5/5 PASA |
| Mínimo 2 de caja blanca | **2** con 13 casos: CB-01 (9 caminos), CB-02 (MC/DC) en [`tests/Aplicacion/CajaBlancaPrueba.php`](../micro-his-notas-soap/tests/Aplicacion/CajaBlancaPrueba.php) | 2/2 PASA |
| Mínimo 4 de caja negra | **5**: CN-01..05 en [`tests/Presentacion/CajaNegraPrueba.php`](../micro-his-notas-soap/tests/Presentacion/CajaNegraPrueba.php) | 5/5 PASA |
| Comandos, resultados y capturas | §5 y [capturas/](capturas/) | Suite: 136 pruebas, 0 fallan |
| Defectos registrados | §6 y [`tests/Defectos/DefectosPrueba.php`](../micro-his-notas-soap/tests/Defectos/DefectosPrueba.php) | 4 defectos (1 **nuevo**), cada uno con una prueba que hoy falla |

**Qué no se prueba:** los módulos de otros compañeros (pacientes, RBAC, autenticación). El expediente, el episodio y la asignación del médico se usan como datos de demostración del Micro-HIS, no como módulos ajenos.

## 1. Matriz RF/RNF → prueba

| Requisito (S2, S6, S12) | Pruebas nuevas (S15) | Pruebas que ya existían |
|---|---|---|
| RF-01 Registrar la nota con sus 4 secciones | UNI-01, CB-01 (C3, C6), CN-01 | DOM-02, APP-01 |
| RF-02 Solo médico, mismo hospital y asignado | CB-02 (MC/DC), CB-01 (C1), CN-03 | APP-03, APP-04, HTTP-13 · **DEF-01** en las lecturas |
| RF-03 Expediente existente, abierto y del paciente | UNI-05, CB-01 (C2), CN-01 | APP-05, COR-06 |
| RF-04 Diagnóstico CIE-10 válido, descripción y tipo | UNI-02, UNI-03, CB-01 (C4), CN-01, CN-02 | DOM-03, DOM-04 |
| RF-05 / RNF-01 Nota y diagnóstico en una transacción | CB-01 (C5: 500 y 0 notas) | PER-05, COR-07 |
| RF-06 / RNF-02 Autor del token, no del cliente | UNI-03 (autoría heredada) | HTTP-07 · **DEF-02** (tokens de demostración) |
| RF-07 / RF-08 Firmar una vez; la firmada no cambia | UNI-04, CN-03, CN-04 (`nota_firmada`) | DOM-06, DOM-07 |
| RF-09 Código de error estable en cada error | CN-01, CN-04 (6 códigos + 503 sin base) | API-04 |
| RF-10 Bitácora sin texto clínico | — | REF-01 |
| RF-11..RF-13 Corregir con versión nueva y motivo ≥ 10 | UNI-04, CN-02 (motivo 9 y 10), CN-03 | COR-01..05 |
| RF-16 Idempotencia | CB-01 (C7–C9), CN-02 (clave de 7, 8, 64 y 65) | HTTP-11, CID-01..05 · **DEF-04** (registro sin clave) |
| RNF-03 Errores sin datos internos | CN-04 (503 genérico) | PER-06 · **DEF-01** |
| RNF-06 Un error no deja efectos | CB-01 (C1–C5: 0 notas) | APP-16 |
| RNF-07 Registro en menos de 2 s (p95) | CN-05: **0.04 ms** en memoria | Pendiente con PostgreSQL |

## 2. Unitarias

| ID | Unidad | Técnica | Qué afirma |
|---|---|---|---|
| UNI-01 | `SeccionesSoap` | Límites | Vacía, espacios y salto de línea → `nota_incompleta`; 1 carácter basta; el contenido conserva tildes |
| UNI-02 | `Diagnostico` (código) | Límites | J18, J18.9, J18.99 y « j18.9 » válidos; J1, J18., J18.999, 18.9 y JJ8.9 no |
| UNI-03 | `Diagnostico` (tipo, autoría) | Particiones | Solo los 3 tipos del enum; descripción obligatoria; hereda nota, episodio y autor |
| UNI-04 | `NotaSoap` (versionado) | Límites | Firmar no muta ni se repite; solo se corrige una firmada; motivo 9 → no, 10 → sí (10 «ñ» cuentan como 10) |
| UNI-05 | Expediente disponible | Particiones | Abierto + episodio del paciente → sí; cerrado, otro paciente, inexistente → no |

## 3. Caja blanca

**CB-01 · Caminos básicos de `RegistrarNotaConDiagnostico::ejecutar()`.** Decisiones: D1 acceso, D2 ¿trae clave?, D3 ¿la clave ya existe?, D4 ¿misma huella?, D5 expediente disponible, D6 secciones válidas, D7 diagnóstico válido, D8 guardado sin falla. **V(G) = 8 + 1 = 9.**

| Camino | Decisión que lo define | Resultado esperado y obtenido |
|---|---|---|
| C1 | D1 falla | 403 `acceso_denegado`, 0 notas |
| C2 · C3 · C4 | D5 · D6 · D7 fallan | 409 `expediente_no_disponible` · 422 `nota_incompleta` · 422 `diagnostico_invalido`, 0 notas |
| C5 | D8 falla (doble que falla) | 500 `error_persistencia`, 0 notas |
| C6 · C7 | Sin clave · con clave nueva | 201, 1 nota |
| C8 · C9 | Clave repetida con la misma huella · con otra | 201 misma nota · 422 `idempotency_key_reutilizada`, 1 nota |

**CB-02 · MC/DC de `exigirPuedeEscribir()`.** Deniega si ¬A ∨ ¬(R ∧ S), con A = hospital igual, R = rol médico, S = asignado. Casos: (A R S) permite; ¬A, ¬R y ¬S deniegan, cada uno cambiando **una sola** condición.

**Cobertura por diseño:** CB-01 recorre las 8 decisiones del caso de uso por sus dos salidas (cobertura de ramas) y CB-02 demuestra el efecto independiente de cada condición (MC/DC). Se intentó además medir líneas con `phpdbg`, pero `phpdbg_get_executable()` falló de forma intermitente en PHP 8.3 (6 de cada 10 corridas terminaban en *segmentation fault*), así que no se entrega una cifra que no se pueda repetir.

## 4. Caja negra

Diseñadas desde el contrato, solo por HTTP y con un sistema nuevo en cada caso.

| ID | Técnica | Casos |
|---|---|---|
| CN-01 | Particiones de equivalencia | 1 válida (201) y 6 inválidas: sección vacía, CIE-10 sin formato, tipo fuera del enum (422), campo no texto (400), expediente cerrado y episodio de otro paciente (409) |
| CN-02 | Valores límite | CIE-10 con 2 decimales sí, 3 no; clave de 7 → 400, 8 y 64 → 201, 65 → 400; motivo de 9 → 422, 10 → 201 |
| CN-03 | Permisos (tabla de decisión) | 4 identidades × 5 operaciones = 20 celdas: solo la autora asignada escribe; médico sin asignación y enfermería leen; token inválido → 401 en todo |
| CN-04 | Errores | `tenant_requerido` 400, `token_invalido` 401, `ruta_no_encontrada` 404, `metodo_no_permitido` 405, `nota_no_encontrada` 404, `nota_firmada` 409 y `servicio_no_disponible` 503 en el punto de entrada real sin base. **Cierra M2 de la S14: 18/18 códigos afirmados.** |
| CN-05 | Rendimiento (RNF-07) | p95 de 200 registros por HTTP < 2 s |

## 5. Comandos, resultados y capturas

```bash
cd docs/modulos/notas-soap-diagnosticos/micro-his-notas-soap
php tests/ejecutar.php --solo=UNI,CB,CN   # 12 pruebas nuevas: Pasan 12, Fallan 0
php tests/ejecutar.php                    # Total: 136  Pasan: 135  Fallan: 0  Omitidas: 1 (PostgreSQL)
php tests/ejecutar.php --defectos         # Total: 4  Fallan: 4 (defectos abiertos, §6)
```

Capturas de la salida real: [nuevas pruebas](capturas/1-unitarias-caja-blanca-caja-negra.png) · [defectos abiertos](capturas/2-defectos-abiertos.png) · [suite completa](capturas/3-suite-completa.png).

## 6. Defectos registrados

Formato y severidad de la [revisión de la S13](../semana-13/formato-hallazgos.md). Cada defecto tiene una prueba que afirma lo **correcto** y hoy **falla**; al corregirse, pasa a la suite normal.

| ID | Severidad · tipo | Encontrado por | Defecto y evidencia | Propuesta | Estado |
|---|---|---|---|---|---|
| DEF-01 | **Crítico** · Defecto | Prueba de permisos (AR-01) | Un médico de otro hospital lee la nota y el listado: 200 y 200 (`ConsultarNota.php:25`, `ListarNotasExpediente.php:26`) | Guardar el hospital del expediente y filtrar por él | Abierto |
| DEF-02 | Mayor · Riesgo | Revisión del arranque (AR-04) | `crearKernel()` usa `AutenticadorTokenDemo` con tokens fijos en modo PDO (`contenedor.php:115`) | Autenticador por entorno; sin desarrollo → 401 | Abierto |
| DEF-03 | Menor · Riesgo | **Caja negra, nuevo:** límites sin máximo | Una sección de 1 MB y una descripción de 20 000 caracteres → 201 y 201; ni el código ni el contrato fijan un máximo | `maxLength` en el contrato y 422 al pasarlo | Abierto |
| DEF-04 | Menor · Riesgo | Caja negra (B-6, AR-03) | Dos registros idénticos sin clave dejan 2 notas | Exigir `Idempotency-Key` al registrar | Abierto |

**Observaciones** (no son defectos, se deciden con el grupo): un campo obligatorio **ausente** responde 422 `nota_incompleta` y no 400; firmar no exige expediente abierto (AR-02).

**Defensa en 1 minuto:** «Probé las reglas aisladas con valores límite, los 9 caminos del registro leyendo el código y el contrato desde afuera con particiones, límites, permisos y errores. Todo pasa. Las pruebas encontraron un defecto nuevo, entradas sin tamaño máximo, y confirman tres de la revisión. Cada uno tiene una prueba que falla hasta que se corrija.»
