# Semana 2 — Requisitos de datos con criterios de aceptación verificables

**Estudiante:** Joshua Eduardo García Reyes (`jgarciar73@miumg.edu.gt`, `jgarciar73-svg`) · **Módulo:** Verificación de base de datos PostgreSQL (asignado en el tablero del docente) · **Base revisada:** HOSPITAL (`database/migrations/hospital/` de `develop`), sobre las entidades de la [semana 1](../semana-01/semana-01-clases-his-postgresql.md)

**Entregable:** 26 requisitos de datos (§2), cada uno con el mecanismo de PostgreSQL que lo cumple y un criterio de aceptación que se puede ejecutar. [`verificar-requisitos.php`](verificar-requisitos.php) comprueba la lista de dos formas (§3): contra el texto de las migraciones y, si hay un PostgreSQL disponible, intentando romper cada regla en una base de prueba.

## 1. Cómo se escribió cada requisito

- **RF de datos:** regla que los datos deben cumplir siempre (integridad, unicidad, obligatoriedad).
- **RNF de datos:** cualidad del dato en el tiempo (trazabilidad y retención).
- **Criterio de aceptación:** una operación concreta que **intenta romper** la regla y el resultado esperado (el código SQLSTATE de PostgreSQL y el nombre de la restricción o del trigger). Si la operación pasa, el requisito no se cumple.
- **Estado:** *Cubierto* si la base ya lo impone; *Brecha* si hoy no lo impone. Para las brechas, el criterio demuestra que el problema existe.

## 2. Requisitos de datos

| ID | Tipo | Categoría | Requisito | Mecanismo en la BD | Criterio de aceptación (intento → resultado esperado) | Estado |
|---|---|---|---|---|---|---|
| RD-01 | RF | Integridad | Toda cita apunta a un paciente que existe | `appointments.local_patient_id → local_patients` | Cita con un paciente inexistente → 23503 | Cubierto |
| RD-02 | RF | Integridad | El estado de una cama es uno de los cuatro permitidos | `beds_status_check` | Cama con estado «sucia» → 23514 | Cubierto |
| RD-03 | RF | Integridad | Un episodio cerrado tiene fecha de fin posterior al inicio | `care_episodes_status_period_check` (no basta) | Cerrar un episodio sin `ended_at` → hoy se acepta: el CHECK da NULL y PostgreSQL lo deja pasar | Brecha |
| RD-04 | RF | Integridad | Solo hay cita con una especialidad vigente del médico | `appointments_specialty_trigger` | Cita en pediatría con un médico de medicina interna → P0001 | Cubierto |
| RD-05 | RF | Integridad | Nota, expediente y episodio son del mismo paciente | `clinical_notes_record_episode_patient_trigger` | Nota en el expediente de P-002 con el episodio de P-001 → P0001 | Cubierto |
| RD-06 | RF | Unicidad | El código del paciente es único | `local_patients_code_unique` | Segundo paciente con código P-001 → 23505 | Cubierto |
| RD-07 | RF | Unicidad | Un paciente tiene un solo expediente | `medical_records_local_patient_id_unique` | Segundo expediente para P-001 → 23505 | Cubierto |
| RD-08 | RF | Unicidad | El número de colegiado es único | `doctor_profiles_license_number_unique` | Otro perfil con colegiado 12345 → 23505 | Cubierto |
| RD-09 | RF | Unicidad | Un médico no tiene dos citas activas que se crucen | `appointments_doctor_active_period_exclusion` | Cita de 08:15 a 08:45 si ya hay una de 08:00 a 08:30 → 23P01 | Cubierto |
| RD-10 | RF | Unicidad | Una cama no tiene dos estancias que se crucen | `bed_stays_bed_active_period_exclusion` | Abrir una estancia en A1 mientras otro paciente sigue en ella → 23P01 | Cubierto |
| RD-11 | RF | Unicidad | Un médico tiene una sola especialidad principal a la vez | `doctor_specialties_primary_period_exclusion` | Segunda especialidad principal con vigencia cruzada → 23P01 | Cubierto |
| RD-12 | RF | Obligatoriedad | El paciente tiene nombre, apellido, fecha de nacimiento y sexo | `local_patients.given_name NOT NULL` | Paciente sin nombre → 23502 | Cubierto |
| RD-13 | RF | Obligatoriedad | Toda cita tiene un motivo no vacío | `appointments_reason_check` | Cita con motivo de solo espacios → 23514 | Cubierto |
| RD-14 | RF | Obligatoriedad | Una nota que corrige a otra explica el motivo | `clinical_notes_correction_check` | Versión 2 que reemplaza a la 1 sin motivo → 23514 | Cubierto |
| RD-15 | RF | Obligatoriedad | La fecha de nacimiento no es futura | `birth_date <= current_date` | Paciente nacido mañana → 23514 | Cubierto |
| RD-16 | RNF | Trazabilidad | Toda estancia en cama guarda quién la asignó | `bed_stays.assigned_by_user_ref_id NOT NULL` | Estancia sin usuario que asigna → 23502 | Cubierto |
| RD-17 | RNF | Trazabilidad | Solo escribe notas un médico asignado al episodio | `clinical_notes_author_assignment_trigger` | Nota escrita por enfermería no asignada → P0001 | Cubierto |
| RD-18 | RNF | Trazabilidad | Las versiones de una nota son consecutivas y enlazadas | `clinical_notes_version_coherence_trigger` | Versión 3 sin indicar a cuál reemplaza → P0001 | Cubierto |
| RD-19 | RNF | Trazabilidad | Toda tabla registra cuándo se creó y se modificó cada fila | `created_at timestamptz NOT NULL DEFAULT now()` | Buscar tablas sin `created_at` → ninguna | Cubierto |
| RD-20 | RNF | Retención | Las citas no se borran (se cancelan) | `appointments_delete_protection` | Borrar una cita → P0001 | Cubierto |
| RD-21 | RNF | Retención | Expedientes, notas y diagnósticos no se borran | `clinical_notes_delete_protection` | Borrar un expediente → P0001 | Cubierto |
| RD-22 | RNF | Retención | Una nota firmada no se modifica | `clinical_notes_signed_immutable` | Firmar la nota y cambiar su contenido → P0001 | Cubierto |
| RD-23 | RNF | Retención | Una estancia cerrada no se modifica | `bed_stays_finalized_immutable` | Cerrar la estancia y cambiar su motivo → P0001 | Cubierto |
| RD-24 | RNF | Retención | No se borra un paciente con historia clínica | `care_episodes.local_patient_id → local_patients` | Borrar a P-001, que tiene episodio → 23503 | Cubierto |
| RD-25 | RF | Integridad | Una cama con estancia abierta está «occupied» | — | Abrir estancia en A2 y revisar el estado de A2 → hoy sigue «available» | Brecha |
| RD-26 | RNF | Trazabilidad | Cada cambio en tablas clínicas guarda quién, cuándo y qué cambió | — | Buscar una tabla de auditoría en HOSPITAL → hoy no hay | Brecha |

## 3. Verificación

```bash
# 1) Siempre: cada mecanismo «Cubierto» existe en las migraciones
php docs/modulos/verificacion-bd-postgresql/semana-02/verificar-requisitos.php

# 2) Con PostgreSQL 15+ y una base vacía de prueba: además ejecuta los 26 criterios
#    (crea un esquema temporal, carga las migraciones HOSPITAL, intenta romper cada regla y borra el esquema)
SHI_PG_DSN="pgsql:host=127.0.0.1;port=5432;dbname=shi_prueba" SHI_PG_USER=postgres php docs/modulos/verificacion-bd-postgresql/semana-02/verificar-requisitos.php
```

En PowerShell las variables se ponen antes con `$env:SHI_PG_DSN = "pgsql:host=127.0.0.1;port=5432;dbname=shi_prueba"`, `$env:SHI_PG_USER = "postgres"` y, si hace falta, `$env:SHI_PG_PASS`. La contraseña nunca se escribe en el repositorio.

**Resultado** con PostgreSQL 16 ([captura](evidencia/verificar-requisitos.png)): estático 23/23 mecanismos encontrados; ejecución 23/23 criterios cumplidos y 3 brechas confirmadas. Si se borra el trigger de retención de citas, RD-20 falla: el criterio sí detecta cuando una regla desaparece.

**Hallazgo de la ejecución (RD-03):** la restricción `care_episodes_status_period_check` existe, pero con `status = 'closed'` y `ended_at` vacío la condición vale NULL, y PostgreSQL acepta un CHECK que da NULL. Solo leyendo la migración parecía cubierto; al ejecutarlo apareció la brecha. `care_assignments_period_check` tiene el mismo patrón.

## 4. Brechas y propuesta

| ID | Riesgo | Propuesta para el grupo |
|---|---|---|
| RD-03 | Episodios «cerrados» sin fecha de fin rompen reportes de estancia y egresos | Agregar `AND ended_at IS NOT NULL` a la condición de cierre (y `valid_to IS NOT NULL` en `care_assignments_period_check`) |
| RD-25 | El tablero de ocupación y Traslados pueden ver una cama «libre» con un paciente adentro | Trigger en `bed_stays` que ponga la cama en `occupied` al abrir y en `maintenance` al cerrar, o vista que calcule el estado desde `bed_stays` |
| RD-26 | No se puede responder «quién cambió este dato» en la base HOSPITAL (el README lo marca como pendiente: auditoría local) | Tabla `audit_events` de solo inserción con trigger genérico en las tablas clínicas |

**Defensa en 1 minuto:** «Escribí 26 requisitos de datos en cinco categorías. Cada uno dice qué restricción o trigger de PostgreSQL lo cumple y cómo se prueba: intento romper la regla y espero un código de error exacto. Un script lo comprueba en dos niveles: que el mecanismo exista en las migraciones y, con PostgreSQL, que de verdad rechace el dato. Pasan 23 y quedan 3 brechas demostradas: un episodio puede cerrarse sin fecha de fin porque el CHECK da NULL, la cama no se marca ocupada sola y no hay auditoría de cambios en HOSPITAL.»
