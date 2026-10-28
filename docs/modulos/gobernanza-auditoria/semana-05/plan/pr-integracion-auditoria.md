# PR de integración — plantilla prellenada (tarea T2 del issue ASII-22)

Se abre desde el worktree, cuando la tarea esté lista:

```bash
git fetch origin
git worktree add ../shi-asii-22-auditoria -b feature/asii-22-auditoria-bquiroao origin/develop
cd ../shi-asii-22-auditoria
# … commits pequeños: puerto → adaptador PDO → casos de uso → pruebas …
git push -u origin feature/asii-22-auditoria-bquiroao
gh pr create --base develop --head feature/asii-22-auditoria-bquiroao \
  --title "ASII-22 T2: núcleo de auditoría (Dominio, Aplicación y PdoBitacora central)" --body-file pr.md
# después del merge
git worktree remove ../shi-asii-22-auditoria
```

---

# PR de módulo ASII

## Resumen

Núcleo del módulo de auditoría en la app Laravel: Dominio y Aplicación portados del micro-HIS y `PdoBitacora` sobre la conexión `central`. La suite de contrato del Repository pasa contra el esquema real.

## Módulo asignado

- Módulo ASII: `ASII-22 — Gobernanza y auditoría de movimientos del sistema`
- Estudiante: Boris Alexander Quiroa Orellana
- GitHub: @bquiroao
- Rama: `feature/asii-22-auditoria-bquiroao`
- Destino del PR: `develop`
- Issue relacionado: `Refs #<número del issue ASII-22>`

## Alcance

### Incluido

- `app/Auditoria/{Dominio,Aplicacion,Persistencia}` (mismas clases que el micro-HIS).
- Pruebas: contrato del Repository (InMemory y PDO central) y casos de uso.

### Fuera de alcance

- Rutas HTTP (T3), ingesta desde la bandeja (T4), roles y anclas publicadas (T5).

## Evidencia obligatoria

- [x] El PR viene desde un worktree creado desde `origin/develop`.
- [x] No trabajé sobre `main`.
- [x] No mezclé cambios de otro módulo.
- [x] El issue del módulo está vinculado.
- [x] Incluí RF/RNF y criterios de aceptación. *(Semana 2.)*
- [x] Incluí evidencia de diseño. *(Semanas 3 y 4.)*
- [ ] Documenté endpoints… *(T3.)*
- [ ] Adjunté capturas… *(Sin interfaz en esta tarea.)*
- [x] Ejecuté validaciones locales y pegué los resultados abajo.
- [x] Revisé roles, permisos, tenant y datos clínicos sensibles cuando aplica.
- [x] Dejé notas de integración con otros módulos o riesgos pendientes.

## Validación ejecutada

```bash
# composer test -- --filter=Auditoria
# resultado: <pegar>
```

## Riesgos y pendientes

- La conexión `central` debe tener el rol `app_auditoria` (T5) antes de producción.

## Checklist final del estudiante

- [x] El PR es pequeño y revisable.
- [x] El código compila o las limitaciones están explicadas.
- [x] La documentación del módulo está actualizada.
- [x] No hay credenciales, tokens ni datos sensibles reales en el PR.
- [x] Solicité revisión solo después de completar la evidencia mínima.
