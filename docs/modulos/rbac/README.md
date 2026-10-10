# Módulo RBAC: roles, permisos y protección de rutas

Control de acceso basado en roles del Sistema Hospitalario Integrado: definición de roles y permisos, asignación a usuarios y protección de rutas de API y de interfaz.

**Estudiante:** Boris Alexander Quiroa Orellana

## Entregas por semana

| Semana | Entrega | Documento | Declaración IA | Defensa oral |
|---:|---|---|---|---|
| 1 | UML del proceso «asignación de rol, autorización de una operación y denegación trazable»: casos de uso, actividad, secuencia y narrativa de alcance | [semana-01-uml-proceso-rbac.md](semana-01/semana-01-uml-proceso-rbac.md) | [DECLARACION_IA.md](semana-01/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-01/GUIA_DEFENSA_ORAL.md) |
| 2 | RF/RNF, criterios de aceptación y aplicación de OCP con diseño antes/después y ejemplo PHP verificable (18 pruebas) | [semana-02-ocp-requerimientos-diseno.md](semana-02/semana-02-ocp-requerimientos-diseno.md) · [ejemplo-ocp](semana-02/ejemplo-ocp/README.md) | [DECLARACION_IA.md](semana-02/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-02/GUIA_DEFENSA_ORAL.md) |
| 3 | Arquitectura del Micro-HIS RBAC: vistas C4 (contexto, contenedores, componentes), paquetes y dependencias, capas Presentation/Application/Domain/Persistence con PDO y pruebas | [semana-03-arquitectura-micro-his.md](semana-03/semana-03-arquitectura-micro-his.md) · [micro-his-rbac](micro-his-rbac/README.md) | [DECLARACION_IA.md](semana-03/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-03/GUIA_DEFENSA_ORAL.md) |
| 4 | Arquitectura en capas y patrón Repository: entrada MVC (controladores, formularios, vistas), interfaces Repository en el Dominio con adaptadores PDO e InMemory, suite de contrato, objetos reutilizables y análisis del repositorio de datos compartido del HIS | [semana-04-capas-mvc-repositorio.md](semana-04/semana-04-capas-mvc-repositorio.md) · [micro-his-rbac](micro-his-rbac/README.md) | [DECLARACION_IA.md](semana-04/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-04/GUIA_DEFENSA_ORAL.md) |
| 5 | Cliente-servidor y frontera de microservicio: contrato OpenAPI 3.1, endpoint de decisión (PDP), cliente con resiliencia, observabilidad, evaluación medida de la frontera, migración por fases y plan de issue, rama, worktree y PR | [semana-05-cliente-servidor-microservicios.md](semana-05/semana-05-cliente-servidor-microservicios.md) · [openapi.yaml](micro-his-rbac/api/openapi.yaml) · [plan](semana-05/plan/issue-asii-02-rbac.md) | [DECLARACION_IA.md](semana-05/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-05/GUIA_DEFENSA_ORAL.md) |
| 6 | Primera evaluación parcial: defensa de la arquitectura (actores → UML → RF/RNF → OCP → capas → Repository → contrato API), presentación de 8 diapositivas, diagrama trazable, matriz decisión→evidencia y cambio práctico «revocar rol» (pruebas primero) | [semana-06-defensa-arquitectura.md](semana-06/semana-06-defensa-arquitectura.md) · [presentación](semana-06/presentacion/semana-06-defensa-rbac.pptx) · [matriz](semana-06/matriz-decision-evidencia.md) · [registro del cambio](semana-06/registro-cambio-practico.md) | [DECLARACION_IA.md](semana-06/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-06/GUIA_DEFENSA_ORAL.md) |

## Micro-HIS RBAC

El micro-proyecto en PHP 8.2+ vanilla que se inicia en la semana 3 y continúa en las semanas 4 y 5 vive en [`micro-his-rbac/`](micro-his-rbac/README.md). Está a nivel de módulo, y no dentro de una semana, porque cada semana lo hace evolucionar.

## Diagramas

Cada semana guarda sus fuentes editables (`.puml`) y las imágenes generadas (`.png`) en `semana-NN/diagramas/`. Para regenerar las imágenes:

```bash
java -jar plantuml.jar -charset UTF-8 -tpng docs/modulos/rbac/semana-01/diagramas/*.puml
```
