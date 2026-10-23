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
