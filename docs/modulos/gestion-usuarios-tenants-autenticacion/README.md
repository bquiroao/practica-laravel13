# Módulo: gestión de usuarios, tenants y endurecimiento de autenticación

Modela el alta autorizada de una identidad con acceso a un tenant, la autenticación segura en ese contexto y la revocación efectiva de la sesión.

**Estudiante según la captura de la tarea:** Mercedes Azucena Lopez Perez

> La asignación general del repositorio actualmente atribuye este módulo a Brandon José Aquino Arrivillaga. Esta entrega sigue la captura recibida y no modifica la asignación global.

## Entregas por semana

| Semana | Entrega | Documento | Declaración IA | Defensa oral |
|---:|---|---|---|---|
| 1 | UML del proceso «alta de usuario por tenant, autenticación segura y revocación de sesión»: casos de uso, actividad, secuencia y narrativa de alcance | [semana-01-uml-usuarios-tenants-autenticacion.md](semana-01/semana-01-uml-usuarios-tenants-autenticacion.md) | [DECLARACION_IA.md](semana-01/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-01/GUIA_DEFENSA_ORAL.md) |
| 2 | RF/RNF, criterios de aceptación y aplicación de SRP al mismo flujo, con ejemplo PHP verificable | [semana-02-srp-usuarios-tenants.md](semana-02/semana-02-srp-usuarios-tenants.md) | [DECLARACION_IA.md](semana-02/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-02/GUIA_DEFENSA_ORAL.md) |
| 7 | Diseño de componentes backend/frontend y refactorización medida del inicio de sesión por tenant | [semana-07-componentes-refactorizacion.md](semana-07/semana-07-componentes-refactorizacion.md) | [DECLARACION_IA.md](semana-07/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-07/GUIA_DEFENSA_ORAL.md) |

## Diagramas

Las fuentes PlantUML editables y sus imágenes se guardan en `semana-NN/diagramas/`. Para regenerar las imágenes desde la raíz del repositorio:

```bash
java -jar plantuml.jar -charset UTF-8 -tpng docs/modulos/gestion-usuarios-tenants-autenticacion/semana-01/diagramas/*.puml
```

## Semana 13

[Plan de revisión técnica, checklist, roles y formatos](semana-13/semana-13-revision-tecnica-usuarios-tenants.md).
