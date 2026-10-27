# Módulo Prescripciones electrónicas con validación de alergias

Emisión de prescripciones electrónicas del Sistema Hospitalario Integrado con **verificación previa de alergias** y **bloqueo seguro**.

| Parte | Qué cubre |
|---|---|
| **Verificación previa** | El médico ve, antes de firmar, si algún medicamento choca con una alergia activa del paciente (tabla `allergies` del expediente) |
| **Emisión con bloqueo seguro** | El servidor repite la verificación; bloquea la coincidencia directa, la reactividad cruzada grave, la dosis por encima del máximo y la fuente de alergias caída (*fail-closed*) |
| **Inmutabilidad y traza** | Una prescripción emitida no se edita: se anula con motivo. Cada emisión, bloqueo y anulación queda en Auditoría |

**Estudiante:** Boris Alexander Quiroa Orellana

## Entregas por semana

| Semana | Entrega | Documento | Declaración IA | Defensa oral |
|---:|---|---|---|---|
| 1 | UML del proceso «creación de prescripción con verificación previa de alergias». Incluye: casos de uso (CU-01..CU-09, sin editar ni omitir la verificación); actividad con 20 decisiones, 15 excepciones y 3 resultados; secuencia con 65 mensajes (verificación previa, emisión con 4 alternativas, anulación y consulta por farmacia); narrativa de alcance basada en la tabla `allergies` real; 12 reglas de negocio; matriz de trazabilidad. | [semana-01-uml-proceso-prescripciones.md](semana-01/semana-01-uml-proceso-prescripciones.md) | [DECLARACION_IA.md](semana-01/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-01/GUIA_DEFENSA_ORAL.md) |
| 2 | RF/RNF (14 + 8) y 17 criterios de aceptación ejecutables. Aplicación de DIP con diseño antes/después: 7 puertos propiedad de la capa de Aplicación, con el contrato de `FuenteAlergias` que prohíbe devolver vacío ante una falla; adaptador PDO sobre la tabla `allergies` real. Evidencia: el «antes» emite amoxicilina a un alérgico con el expediente inaccesible y el «después» responde 503; fuentes en memoria, PDO y FHIR intercambiables. 24/24 pruebas con PostgreSQL 18 (21 sin base) y 13/13 mutaciones. | [semana-02-dip-requerimientos-diseno.md](semana-02/semana-02-dip-requerimientos-diseno.md) · [ejemplo-dip](semana-02/ejemplo-dip/README.md) | [DECLARACION_IA.md](semana-02/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-02/GUIA_DEFENSA_ORAL.md) |
