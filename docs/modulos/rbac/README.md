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
| 7 | Diseño de componentes (backend implementado + frontend Vue diseñado), contratos de entrada/salida y refactor medido del punto de mayor acoplamiento (AsignarRol/RevocarRol: Ce 13→8, 5→3 preocupaciones) sin ampliar el módulo | [semana-07-componentes-refactor.md](semana-07/semana-07-componentes-refactor.md) · [contratos](semana-07/contratos-entrada-salida.md) | [DECLARACION_IA.md](semana-07/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-07/GUIA_DEFENSA_ORAL.md) |
| 8 | Diseño de experiencia de usuario: user flow por rol (administrador asignar/revocar, clínico, auditor) y 6 wireframes anotados con estados, mensajes, validaciones, reglas de interacción, ayuda contextual y protección de datos | [semana-08-ux-rbac.md](semana-08/semana-08-ux-rbac.md) · [wireframes](semana-08/wireframes/semana-08-wireframes-rbac.pptx) | [DECLARACION_IA.md](semana-08/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-08/GUIA_DEFENSA_ORAL.md) |
| 9 | Evaluación del diseño UX con heurísticas de Nielsen, WCAG 2.2 AA y checklist RBAC (93 ítems): contraste medido con script, recorrido de teclado, 16 hallazgos con evidencia y backlog priorizado (severidad × impacto RBAC) con corrección y criterio verificable | [semana-09-evaluacion-ux-rbac.md](semana-09/semana-09-evaluacion-ux-rbac.md) · [checklist](semana-09/checklist-evaluacion.md) · [contraste.py](semana-09/herramientas/contraste.py) | [DECLARACION_IA.md](semana-09/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-09/GUIA_DEFENSA_ORAL.md) |
| 10 | Diseño para movilidad (320–430 px): prototipo responsive de 5 pantallas (M1–M5), jerarquía de contenido, reglas de breakpoint verificadas en 31 vistas, política de conexión limitada con reintento medido sobre el Micro-HIS y dos escenarios móviles | [semana-10-movil-rbac.md](semana-10/semana-10-movil-rbac.md) · [prototipo](semana-10/prototipo/m1-roles.html) · [escenarios](semana-10/escenarios/) | [DECLARACION_IA.md](semana-10/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-10/GUIA_DEFENSA_ORAL.md) |

## Micro-HIS RBAC

El micro-proyecto en PHP 8.2+ vanilla que se inicia en la semana 3 y continúa en las semanas 4 y 5 vive en [`micro-his-rbac/`](micro-his-rbac/README.md). Está a nivel de módulo, y no dentro de una semana, porque cada semana lo hace evolucionar.

## Diagramas

Cada semana guarda sus fuentes editables (`.puml`) y las imágenes generadas (`.png`) en `semana-NN/diagramas/`. Para regenerar las imágenes:

```bash
java -jar plantuml.jar -charset UTF-8 -tpng docs/modulos/rbac/semana-01/diagramas/*.puml
```
