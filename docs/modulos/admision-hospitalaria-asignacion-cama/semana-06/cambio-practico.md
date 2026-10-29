# Registro del cambio práctico (primer parcial)

El cambio lo pide el ingeniero en la defensa. Este archivo trae **casos preparados**, que no son el cambio real. La sección 4 se llena **después** de la defensa con lo que realmente se pidió y se respondió.

## 1. Caso preparado A — reserva temporal de cama (RN-07)

**Enunciado supuesto:** «Mientras el admisionista confirma, la cama elegida no debe aparecer libre para otros durante 3 minutos.»

**Regla nueva propuesta, RN-07:** al elegir una cama se crea una **reserva** de 3 minutos a nombre del usuario. Las demás consultas no muestran esa cama. Al confirmar, la reserva se convierte en asignación; si vence, la cama vuelve a estar libre.

### Análisis de impacto por capa

| Capa | Cambio | Archivos | ¿Se modifica algo existente? |
|---|---|---|---|
| Domain | Criterio nuevo `CriterioCamaNoReservada` (OCP) e interfaz `ReservaRepository` | 2 archivos nuevos | No: `EvaluadorElegibilidad` no cambia |
| Application | Caso de uso nuevo `ReservarCama`; `AdmitirPacienteYAsignarCama` verifica que la reserva sea del mismo usuario | 1 nuevo y 1 modificado | Sí, una verificación más |
| Persistence | Tabla `reserva_cama` con un índice único propio por `(tenant_id, cama_id)`, `expira_en` y limpieza de reservas vencidas | Esquema y adaptadores PDO / InMemory | Se agrega; RN-01 sigue en `ux_asignacion_cama_activa` |
| Presentation / API | `POST /api/v1/reservas-cama`; en la UI, aviso «reservada para usted hasta hh:mm» | Kernel y vistas | Sí, rutas nuevas |
| Composition Root | Agregar `CriterioCamaNoReservada` al evaluador | `bootstrap.php` | Una línea |

### Concurrencia y trazabilidad
- Dos usuarios que reservan la misma cama al mismo tiempo: el índice único de `reserva_cama` deja pasar solo una reserva, igual que con RN-01.
- Una reserva vencida **no** basta para asignar: al confirmar, RN-01 se vuelve a verificar en la misma transacción.
- Eventos nuevos `CAMA_RESERVADA` y `RESERVA_VENCIDA` con usuario, rol e id de solicitud (RN-05).

### Pruebas que se agregarían
1. Una cama reservada no aparece en las camas disponibles de otro usuario (unitaria, criterio nuevo).
2. Dos reservas simultáneas de la misma cama: solo una pasa (integración, dos conexiones).
3. Confirmar con la reserva vencida y la cama ya tomada responde `CAMA_YA_ASIGNADA` (integración).
4. La prueba de contrato se extiende a `ReservaRepository` (PDO e InMemory).

**Riesgo:** reservas que no se liberan si falla la limpieza. Mitigación: la consulta filtra por `expira_en > now()` y la limpieza es solo mantenimiento.

## 2. Caso preparado B — el médico admite en emergencia

**Enunciado supuesto:** «En emergencia, el médico también puede admitir.»

- Cambia **RN-06** y nada más. La autorización está centralizada en `ContextoUsuario::exigirRol`, así que basta con agregar `Rol::MEDICO` cuando `origen = EMERGENCIA` dentro de `AdmitirPacienteYAsignarCama`.
- Pruebas: «MEDICO admite con origen EMERGENCIA» y «MEDICO no admite con CONSULTA_EXTERNA».
- Trazabilidad: el evento ya guarda el rol, así que la auditoría distingue las admisiones hechas por médicos.
- Riesgo: un permiso más amplio. Se discute con el líder antes de aplicarlo.

## 3. Caso preparado C — dar de alta libera la cama

Fuera de este módulo: Altas y traslados emitiría `AltaRegistrada` y Admisión llenaría `liberada_en`. El índice parcial ya permite reutilizar la cama en cuanto `liberada_en` deja de ser nulo. Pendiente de definir con ese módulo.

## 4. Registro de la defensa (llenar después de presentar)

| Campo | Valor |
|---|---|
| Fecha | `<por llenar>` |
| Cambio pedido por el ingeniero (textual) | `<por llenar>` |
| Caso preparado más cercano | `<A / B / C / ninguno>` |
| Respuesta dada: capas, archivos, reglas y pruebas | `<por llenar>` |
| Observaciones del ingeniero | `<por llenar>` |
| Acción posterior (issue o semana) | `<por llenar>` |
