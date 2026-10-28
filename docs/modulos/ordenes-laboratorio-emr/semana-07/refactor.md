# Refactor concreto R07-01 — Separar ClinicalRules por dependencias del consumidor

## Punto de acoplamiento observado

En semana-03/micro-his/Domain/Model.php, ClinicalRules reúne authorized, contextValid, testsAvailable e initialStatus. Application/Workflow.php inyecta ese contrato tanto en Issue como en Find. Presentation/DemoRules.php implementa todo en una clase de fixture. Find solo necesita authorized, pero su dependencia conoce catálogo, contexto y ciclo de vida. Es un punto real inspeccionado, no una clase monolítica ficticia atribuida al repositorio.

La semana 3 ya tiene SQL aislado y dominio separado; no se presenta como si el controlador contuviera SQL. El refactor actúa sobre la agregación restante de políticas y sobre el futuro borde UI/API. Mayor acoplamiento identificado dentro de este Micro-HIS, sin afirmar un análisis global cuantitativo del sistema hospitalario.

## Antes

- Issue y Find dependen de ClinicalRules agregado.
- Una clase demo cambia por reglas de EMR, catálogo, permiso y estado.
- Resultado bool de validación no distingue decisión negativa de dependencia caída; aplicación solo distingue excepción genérica.

## Después propuesto

- Issue recibe ClinicalAuthorization, ClinicalContextValidator, LabCatalog, OrderLifecyclePolicy y Repository.
- Find recibe ClinicalAuthorization y Repository.
- Order sigue validando orden/pruebas no vacías; Repository sigue persistiendo orden/partidas.
- Adaptador por dueño integra decisión externa; resultados explícitos de permitido/denegado/indisponible, como diseño de semana 2.
- Presentación solo traduce DTO y contexto confiable; UI solo coordina formulario/revisión/listado. No replica reglas clínicas.

La aplicación se vuelve más explícita y Find no necesita un catálogo ficticio para construirse. Coste: más objetos de composición y manejo de errores; se acepta porque las cuatro políticas cambian por dueños distintos. Si un despliegue usa un único adaptador que implementa varios puertos, es posible sin volver a obligar a cada consumidor a depender de todos.

## Comparación

| Aspecto | Antes real | Después de diseño |
|---|---|---|
| Find | 1 dependencia de reglas con 4 operaciones; usa 1 | Puerto de autorización con 1 operación + Repository. |
| Cambiar catálogo | Tocar implementación ClinicalRules agregada | Cambiar adaptador LabCatalog; Find no cambia. |
| Indisponibilidad | bool o excepción genérica | Resultado tipado por frontera, sin denegación o aceptación falsa. |
| Datos de orden | Entidad y SQL ya separados | Se preservan; no traslado de SQL a controller. |
| Seguimiento de laboratorio | Ejemplo separado semana 2 | Permanece separado, origen/ciclo/compare-and-set; integración PDO pendiente. |

## Migración conceptual sin ampliar alcance

1. Definir puertos y resultados; mapear cada regla/criterio de semana 2.
2. Crear adaptadores demo que conserven permisos/códigos/estado sintéticos actuales.
3. Cambiar composición y constructor de Issue/Find; mantener llamadas externas del caso de uso.
4. Ejecutar regresiones 19+34 y añadir fallos por dependencia individual/contratos HTTP cuando se implemente esa frontera.
5. Sustituir dobles por adaptadores aprobados de dueños EMR/catálogo; verificar identidad/tenant y aislamiento antes de datos reales.

No hay migración de tablas por dividir puertos. Rollback de implementación futura: conservar versión previa de composición/consumidores como conjunto; no mezclar firmas anteriores con nuevas. No se extrae microservicio ni se replica catálogo/expediente.

## Criterios verificables de aceptación futura

Find se construye sin catálogo/context-validator/lifecycle; deniega antes de leer Repository. Emisión rechaza contexto/pruebas inválidos sin escritura; cada dependencia caída da indisponibilidad explícita. Camino feliz conserva actor/paciente/episodio/pruebas/estado. Error PDO revierte todo. UI no muestra éxito antes de commit ni lista vacía al fallar lectura. Cambio de contexto descarta respuesta anterior y no filtra órdenes ajenas.

Estado: **refactor conceptual documentado, no aplicado al código previo**. Las salidas de regresión son comportamiento de referencia, no resultados de una implementación posterior inexistente. No se cierran defectos o revisiones de terceros por este diseño.