# Módulo Gobernanza y auditoría de movimientos del sistema

Bitácora de movimientos de los usuarios del Sistema Hospitalario Integrado, pensada para la gobernanza del super administrador y de los auditores de cada hospital.

Tiene dos partes:

| Parte | Qué cubre |
|---|---|
| **Registro inmutable** | Tabla `audit_logs` de solo inserción, sellos encadenados para detectar alteraciones e idempotencia por `event_id` |
| **Consulta filtrada** | Filtro obligatorio por rango libre de fechas (`desde` / `hasta`) y filtros por usuario, módulo y acción, siempre dentro del hospital (tenant) que corresponde |

**Estudiante:** Boris Alexander Quiroa Orellana

## Entregas por semana

| Semana | Entrega | Documento | Declaración IA | Defensa oral |
|---:|---|---|---|---|
| 1 | UML del proceso «registro inmutable y consulta filtrada de movimientos por usuario, módulo y fechas». Incluye: casos de uso (CU-01..CU-07, sin editar ni borrar); actividad con 14 decisiones, 10 excepciones y 4 resultados; secuencia con 53 mensajes; narrativa de alcance; 10 reglas de negocio; matriz de trazabilidad. | [semana-01-uml-proceso-auditoria.md](semana-01/semana-01-uml-proceso-auditoria.md) | [DECLARACION_IA.md](semana-01/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-01/GUIA_DEFENSA_ORAL.md) |
| 2 | RF/RNF (13 + 9) y 17 criterios de aceptación. Aplicación de OCP con diseño antes/después: filtros de consulta y reglas de registro como puntos de extensión; alcance, sello, idempotencia y meta-auditoría fijos. Dos extensiones sin tocar los servicios. 21 pruebas (paridad de sellos byte a byte) y 8/8 mutaciones detectadas. | [semana-02-ocp-requerimientos-diseno.md](semana-02/semana-02-ocp-requerimientos-diseno.md) · [ejemplo-ocp](semana-02/ejemplo-ocp/README.md) | [DECLARACION_IA.md](semana-02/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-02/GUIA_DEFENSA_ORAL.md) |
| 3 | Inicio del Micro-HIS en PHP vanilla: cuatro capas, PDO con sentencias preparadas, `audit_logs` de solo inserción (trigger), sello encadenado con candado por cadena, idempotencia, consulta paginada y meta-auditoría. Vista C4 (contexto, contenedores, componentes), paquetes y vista dinámica. 39 pruebas (39/39 con PostgreSQL 18 real), 12/12 mutaciones y recorrido HTTP; 2 defectos que solo aparecieron con la base y el servidor reales. | [semana-03-arquitectura-micro-his.md](semana-03/semana-03-arquitectura-micro-his.md) · [micro-his-auditoria](micro-his-auditoria/README.md) | [DECLARACION_IA.md](semana-03/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-03/GUIA_DEFENSA_ORAL.md) |
