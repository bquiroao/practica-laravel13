# Módulo Pacientes: registro, edición, búsqueda y detalle

Gestión de la ficha del paciente en cada hospital del Sistema Hospitalario Integrado: registro sin duplicados (con verificación en el índice maestro de pacientes, MPI), búsqueda acotada, consulta de detalle y actualización segura con control de versión y auditoría.

**Estudiante:** Boris Alexander Quiroa Orellana

## Entregas por semana

| Semana | Entrega | Documento | Declaración IA | Defensa oral |
|---:|---|---|---|---|
| 1 | UML del proceso «registro, búsqueda y actualización segura de un paciente»: casos de uso (CU-01..CU-08), actividad con decisiones, excepciones y resultados, secuencia con validaciones y respuestas, narrativa de alcance, 10 reglas de negocio y matriz de trazabilidad | [semana-01-uml-proceso-pacientes.md](semana-01/semana-01-uml-proceso-pacientes.md) | [DECLARACION_IA.md](semana-01/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-01/GUIA_DEFENSA_ORAL.md) |
| 2 | RF/RNF (11 + 9), 12 criterios de aceptación y aplicación de LSP con diseño antes/después: 3 violaciones reales (identificador temporal, réplica y caché por herencia) corregidas con contratos; ejemplo PHP con pruebas de contrato (25 pruebas) y comprobación por mutación | [semana-02-lsp-requerimientos-diseno.md](semana-02/semana-02-lsp-requerimientos-diseno.md) · [ejemplo-lsp](semana-02/ejemplo-lsp/README.md) | [DECLARACION_IA.md](semana-02/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-02/GUIA_DEFENSA_ORAL.md) |
| 3 | Micro-HIS Pacientes en PHP 8.2+ vanilla con cuatro capas (Presentación, Aplicación, Dominio, Persistencia), PDO con sentencias preparadas, documento cifrado y versión; vistas C4 (contexto, contenedores, componentes), paquetes y dependencias, vista dinámica; 38 pruebas (camino feliz, reglas, errores de persistencia, arquitectura) y 8/8 mutaciones detectadas | [semana-03-arquitectura-micro-his.md](semana-03/semana-03-arquitectura-micro-his.md) · [micro-his-pacientes](micro-his-pacientes/README.md) | [DECLARACION_IA.md](semana-03/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-03/GUIA_DEFENSA_ORAL.md) |
| 4 | Capas, MVC y patrón Repository en el mismo Micro-HIS: controlador sin SQL ni reglas (verificado), formulario de formato y vistas; interfaces Repository en el Dominio con adaptadores PDO e InMemory y suite de contrato común; objetos reutilizables; integración con el repositorio de datos compartido del HIS (PostgreSQL federado, MPI y outbox); 52 pruebas y 12/12 mutaciones detectadas | [semana-04-capas-mvc-repositorio.md](semana-04/semana-04-capas-mvc-repositorio.md) · [micro-his-pacientes](micro-his-pacientes/README.md) | [DECLARACION_IA.md](semana-04/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-04/GUIA_DEFENSA_ORAL.md) |

## Micro-HIS Pacientes

El micro-proyecto en PHP 8.2+ vanilla que se inicia en la semana 3 vive en [`micro-his-pacientes/`](micro-his-pacientes/README.md). Está a nivel de módulo, y no dentro de una semana, porque cada semana lo hace evolucionar.

## Diagramas

Cada semana guarda sus fuentes editables (`.puml`) y las imágenes generadas (`.png`) en `semana-NN/diagramas/`. Para regenerar las imágenes:

```bash
java -jar plantuml.jar -charset UTF-8 -tpng docs/modulos/pacientes/semana-01/diagramas/*.puml
```
