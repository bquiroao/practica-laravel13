# Módulo Pacientes: registro, edición, búsqueda y detalle

Gestión de la ficha del paciente en cada hospital del Sistema Hospitalario Integrado: registro sin duplicados (con verificación en el índice maestro de pacientes, MPI), búsqueda acotada, consulta de detalle y actualización segura con control de versión y auditoría.

**Estudiante:** Boris Alexander Quiroa Orellana

## Entregas por semana

| Semana | Entrega | Documento | Declaración IA | Defensa oral |
|---:|---|---|---|---|
| 1 | UML del proceso «registro, búsqueda y actualización segura de un paciente»: casos de uso (CU-01..CU-08), actividad con decisiones, excepciones y resultados, secuencia con validaciones y respuestas, narrativa de alcance, 10 reglas de negocio y matriz de trazabilidad | [semana-01-uml-proceso-pacientes.md](semana-01/semana-01-uml-proceso-pacientes.md) | [DECLARACION_IA.md](semana-01/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-01/GUIA_DEFENSA_ORAL.md) |
| 2 | RF/RNF (11 + 9), 12 criterios de aceptación y aplicación de LSP con diseño antes/después: 3 violaciones reales (identificador temporal, réplica y caché por herencia) corregidas con contratos; ejemplo PHP con pruebas de contrato (25 pruebas) y comprobación por mutación | [semana-02-lsp-requerimientos-diseno.md](semana-02/semana-02-lsp-requerimientos-diseno.md) · [ejemplo-lsp](semana-02/ejemplo-lsp/README.md) | [DECLARACION_IA.md](semana-02/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-02/GUIA_DEFENSA_ORAL.md) |

## Diagramas

Cada semana guarda sus fuentes editables (`.puml`) y las imágenes generadas (`.png`) en `semana-NN/diagramas/`. Para regenerar las imágenes:

```bash
java -jar plantuml.jar -charset UTF-8 -tpng docs/modulos/pacientes/semana-01/diagramas/*.puml
```
