# Módulo RBAC: roles, permisos y protección de rutas

Control de acceso basado en roles del Sistema Hospitalario Integrado: definición de roles y permisos, asignación a usuarios y protección de rutas de API y de interfaz.

**Estudiante:** Boris Alexander Quiroa Orellana

## Entregas por semana

| Semana | Entrega | Documento | Declaración IA | Defensa oral |
|---:|---|---|---|---|
| 1 | UML del proceso «asignación de rol, autorización de una operación y denegación trazable»: casos de uso, actividad, secuencia y narrativa de alcance | [semana-01-uml-proceso-rbac.md](semana-01/semana-01-uml-proceso-rbac.md) | [DECLARACION_IA.md](semana-01/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-01/GUIA_DEFENSA_ORAL.md) |

## Diagramas

Cada semana guarda sus fuentes editables (`.puml`) y las imágenes generadas (`.png`) en `semana-NN/diagramas/`. Para regenerar las imágenes:

```bash
java -jar plantuml.jar -charset UTF-8 -tpng docs/modulos/rbac/semana-01/diagramas/*.puml
```
