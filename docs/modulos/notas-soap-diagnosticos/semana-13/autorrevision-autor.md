# Autorrevisión del autor

**Autor:** Joshua Eduardo García Reyes · **Paquete:** el de la [revisión](plan-revision-tecnica.md#2-alcance-y-paquete) (S1 + S2, 2883 líneas efectivas) · **Método:** el mismo [checklist](checklist-revision.md), antes de convocar.

Encontré **6 hallazgos**. Tres se reproducen con [herramientas/verificar-autorrevision.php](herramientas/verificar-autorrevision.php):

```text
AR-01 REPRODUCIDO  otro hospital: GET nota → 200 (con texto clínico) · GET listado → 200 con 1 notas
AR-02 REPRODUCIDO  expediente cerrado: firmar → 200 · registrar → 409 expediente_no_disponible
AR-04 REPRODUCIDO  crearKernel() (public/index.php) usa AutenticadorTokenDemo con 3 tokens fijos de config.php
```

| ID | Ítem | Severidad | Tipo | Hallazgo | Ubicación |
|---|---|---|---|---|---|
| AR-01 | RT-10 | **Crítico** | Defecto | Un médico de **otro hospital** lee las notas y el listado: los casos de uso de lectura solo comparan el hospital del token con la cabecera, y ni la nota ni el expediente guardan su hospital | `ConsultarNota.php:25`, `ListarNotasExpediente.php:26`, `schema.sql` |
| AR-04 | RT-15 | Mayor | Riesgo | El punto de entrada real (modo PDO) usa siempre la autenticación de demostración: con `Bearer token-mendez` cualquiera escribe como la Dra. Méndez | `contenedor.php:115`, `config.php:17` |
| AR-02 | RT-11 | Menor | Pregunta | Firmar no exige asignación al episodio ni expediente abierto, a diferencia de registrar y corregir. ¿Se puede firmar un borrador después del cierre? | `FirmarNota.php:33` |
| AR-03 | RT-12 | Menor | Riesgo | `Idempotency-Key` sigue opcional al registrar (B-6): sin clave, un reintento duplica la nota | `FormularioRegistroNota.php:31` |
| AR-05 | RT-12 | Observación | Mejora | Las claves de idempotencia no vencen: la tabla crece sin límite | `schema.sql:83` |
| AR-06 | RT-10 | Observación | Pregunta | Enfermería puede leer borradores sin firmar. ¿Debe ver solo notas firmadas? | `ConsultarNota.php` |

Los 6 están en el [registro de hallazgos](plantillas/registro-hallazgos.csv) con el formato completo.

**Por qué ninguna prueba los detectó.** APP-04 prueba que el token de un hospital no sirva **con la cabecera de otro**, pero no que el dato pertenezca al hospital del token (AR-01). Las pruebas usan siempre el autenticador de demostración, así que nunca comprueban el punto de entrada real (AR-04). Son exactamente los huecos que el checklist obliga a mirar.

**No se corrigieron esta semana.** Llegan **abiertos** a la reunión para que el equipo los confirme o los ajuste. Corregirlos antes vaciaría la revisión y rompería E-1 (paquete congelado). AR-01 es crítico: según el plan, si se confirma, la decisión será **re-inspección** del arreglo.
