# Respuesta documentada a un cambio solicitado — CASO PREPARADO

> Es una propuesta preparada. Si el ingeniero pide otro cambio, se anota al final con el mismo formato.

## Cambio: enfermería registra una alergia **referida** por el paciente

**Motivo.** En la noche no siempre hay un médico, y el paciente dice «soy alérgico al látex». Hoy enfermería no puede registrarlo (RN-07) y el dato se pierde.

| Aspecto | Impacto |
|---|---|
| Roles | Permiso nuevo `alergias.referir` para enfermería. El médico conserva `alergias.registrar` y gana `alergias.verificar` |
| Datos | `allergies.verified_by_user_ref_id` es **NOT NULL**: una alergia referida no tiene verificador. **Propuesta:** tabla `allergy_reports` (paciente, alérgeno, severidad declarada, reportado_por, estado `pendiente/verificada/descartada`). Al verificar, se crea la fila en `allergies`. No se toca la tabla real |
| Alerta | Una alergia referida **cuenta** para la alerta con un nivel mínimo de ALTA, aunque se declare leve: no está verificada. En la tarjeta aparece «REFERIDA · sin verificar» |
| Estados de UX | Tarjeta con borde discontinuo y texto; acción «Verificar» o «Descartar» (con motivo) solo para el médico |
| Accesibilidad | El estado se dice con texto («referida, sin verificar») y no solo con el borde |
| Móvil | La tarjeta referida va primero en su nivel; «Verificar» es un botón de ancho completo |
| Riesgo | Una alergia falsa referida eleva la alerta. Se acepta, porque es preferible a perder una verdadera |

### Componentes afectados

- **Backend:** `ReferirAlergia` (CU-08), `VerificarAlergiaReferida` (CU-09), `RepositorioReportes` (PDO e InMemory con contrato), y una regla nueva `ReglaAlergiaReferida`, que solo agrega una clase gracias a OCP: el calculador no cambia.
- **Frontend:** `TarjetaAlergiaReferida`, y `FormularioAlergia` con modo «referir».

### Pruebas primero

| ID | Dado / Cuando / Entonces |
|---|---|
| T-CS1 | Enfermería refiere Látex leve → la alerta pasa a ALTA como mínimo |
| T-CS2 | Médico verifica → aparece en `allergies` con su `verified_by` y desaparece de pendientes |
| T-CS3 | Médico descarta sin motivo → 422 |
| T-CS4 | Enfermería intenta verificar → 403 |
| T-CS5 | Referir algo ya activo y equivalente → 409 `ALERGIA_DUPLICADA` |
| T-CS6 | El calculador conserva su SHA-256 después de agregar `ReglaAlergiaReferida` (OCP-03) |

## Cambio pedido en la evaluación

- **Cambio solicitado:** `<anotar>`
- **Respuesta (roles, estados, errores, riesgos, componentes y pruebas):** `<anotar>`
