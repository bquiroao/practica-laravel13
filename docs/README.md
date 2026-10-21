# Documentación del proyecto

Toda la documentación del Sistema Hospitalario Integrado vive en esta carpeta, organizada por módulo.

## Estructura

```
docs/
├── README.md                        ← este índice
└── modulos/
    └── rbac/                        ← un directorio por módulo
        ├── README.md                ← índice del módulo y entregas por semana
        ├── micro-his-rbac/          ← código que evoluciona entre semanas (desde la semana 3)
        └── semana-01/               ← un directorio por entrega semanal
            ├── semana-01-<tema>.md  ← documento principal de la entrega
            ├── DECLARACION_IA.md    ← declaración de uso de IA
            ├── GUIA_DEFENSA_ORAL.md ← preparación para la defensa oral
            └── diagramas/           ← fuentes editables e imágenes
```

## Convenciones

- Cada módulo tiene su propia carpeta en `docs/modulos/<modulo>/`, con nombre en minúsculas y separado por guiones (por ejemplo `rbac`, `pacientes`, `citas-medicas`).
- El `README.md` de cada módulo funciona como índice: describe el módulo y enlaza las entregas de cada semana.
- Cada entrega semanal va en `semana-NN/` y **siempre** incluye `DECLARACION_IA.md` y `GUIA_DEFENSA_ORAL.md`.
- Los diagramas van en `semana-NN/diagramas/`, junto con su fuente editable (`.puml`, `.drawio`, `.excalidraw`).

## Módulos

| Módulo | Carpeta |
|---|---|
| RBAC: roles, permisos y protección de rutas | [`modulos/rbac`](modulos/rbac/README.md) |
| Notas SOAP y diagnósticos | [`modulos/notas-soap-diagnosticos`](modulos/notas-soap-diagnosticos/README.md) |
| Recepción de muestras y códigos identificadores | [`modulos/recepcion-muestras`](modulos/recepcion-muestras/README.md) |
| Pacientes: registro, edición, búsqueda y detalle | [`modulos/pacientes`](modulos/pacientes/README.md) |
