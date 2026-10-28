# PR de integración — plantilla prellenada (tarea T1 del issue ASII-15)

Se abre desde el worktree, cuando la tarea esté lista:

```bash
git fetch origin
git worktree add ../shi-asii-15-prescripciones-esquema -b feature/asii-15-prescripciones-esquema-bquiroao origin/develop
cd ../shi-asii-15-prescripciones-esquema
# Commits pequeños, en orden RED → GREEN → REFACTOR, como pide la tarea 3.5 del plan del esquema:
#   1. tests/Database/PrescriptionIntegrityTest.php        (falla: no existen las tablas)
#   2. 2026_08_11_000500_create_medication_tables.php        (pasa)
#   3. down(): DROP TRIGGER antes que las tablas; prueba de rollback
git push -u origin feature/asii-15-prescripciones-esquema-bquiroao
gh pr create --base develop --head feature/asii-15-prescripciones-esquema-bquiroao \
  --title "ASII-15 T1: esquema H5 de medicamentos y prescripciones (hospital)" --body-file pr.md
# Después del merge:
cd ../sistema-hospitalario-integrado-SistenasII-2026-G1
git worktree remove ../shi-asii-15-prescripciones-esquema
git branch -d feature/asii-15-prescripciones-esquema-bquiroao
```

---

# PR de módulo ASII

## Resumen

Slice H5 del esquema de hospital: `medications`, `allergen_groups`, `prescriptions` y `prescription_items`, con:
- trigger de integridad: el episodio es del paciente, el prescriptor está asignado y la prescripción es inmutable;
- `UNIQUE` de idempotencia;
- la vista `prescripcion_alergias` para leer alergias sin privilegios sobre la tabla de ASII-12.

Es la tarea 3.5 pendiente de `openspec/changes/postgresql-federated-schema-rebuild/tasks.md`.

## Módulo asignado

- Módulo ASII: `ASII-15 — Prescripciones electrónicas con validación de alergias`
- Estudiante: Boris Alexander Quiroa Orellana
- GitHub: @bquiroao
- Rama: `feature/asii-15-prescripciones-esquema-bquiroao`
- Destino del PR: `develop`
- Issue relacionado: Refs #<número del issue ASII-15>

## Alcance

### Incluido

- `tests/Database/PrescriptionIntegrityTest.php`.
- `database/migrations/hospital/2026_08_11_000500_create_medication_tables.php` (sin editar migraciones existentes).

### Fuera de alcance

- Modelos Eloquent, factories y seeders (slices MH/SH del plan).
- Núcleo, API e integración (T2 a T4).

## Evidencia obligatoria

- [x] El PR viene desde un worktree creado desde `origin/develop`.
- [x] No trabajé sobre `main`.
- [x] No mezclé cambios de otro módulo.
- [x] El issue del módulo está vinculado.
- [x] Incluí RF/RNF y criterios de aceptación. *(RF-11, RF-12 de la semana 2.)*
- [x] Incluí evidencia de diseño: UML, C4, componentes, capas o flujo UX según corresponda. *(Semana 4, repositorio compartido.)*
- [ ] Documenté endpoints, payloads, respuestas, errores y permisos si el módulo expone API. *(No aplica: solo esquema.)*
- [ ] Adjunté capturas o demo si el módulo tiene UI. *(No aplica.)*
- [x] Ejecuté validaciones locales y pegué los resultados abajo.
- [x] Revisé roles, permisos, tenant y datos clínicos sensibles cuando aplica.
- [x] Dejé notas de integración con otros módulos o riesgos pendientes.

## Validación ejecutada

```bash
php artisan test tests/Database/PrescriptionIntegrityTest.php     # RED antes de la migración, GREEN después
php artisan migrate:rollback --step=1 && php artisan migrate        # down y up limpios
PRESCRIPCIONES_DB_DSN=… php docs/modulos/prescripciones-alergias/micro-his-prescripciones/bin/validar-integracion-hospital.php   # 13/13
```

## Contrato API si aplica

No aplica.

## Evidencia visual si aplica

No aplica.

## Riesgos y pendientes

- La vista `prescripcion_alergias` debe acordarse con ASII-12 (Alergias); si cambian las columnas de `allergies`, se ajusta la vista y no el módulo.
- `medications` es de ASII-14 (Catálogo): si ese módulo crea su propia migración, esta tarea solo agrega `prescriptions` y `prescription_items`.

## Checklist final del estudiante

- [x] El PR es pequeño y revisable (≤ 400 líneas, como exige el plan del esquema).
- [x] El código compila o las limitaciones están explicadas.
- [x] La documentación del módulo está actualizada.
- [x] No hay credenciales, tokens ni datos sensibles reales en el PR.
- [x] Solicité revisión solo después de completar la evidencia mínima.
