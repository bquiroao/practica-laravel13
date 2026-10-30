# Registro del cambio práctico — CASO PREPARADO

> Este cambio es una **propuesta preparada** para la defensa. Si el ingeniero pide otro cambio, se registra abajo, en «Cambio pedido en la evaluación», con el mismo formato.

## Cambio: cambiar la severidad de una alergia ya registrada

**Situación.** PAC-00451 tiene Ibuprofeno `moderate`. En una nueva exposición presenta edema de glotis, y el médico necesita subir la severidad a `life_threatening`. Hoy la única opción sería inactivar la alergia y registrarla de nuevo, lo que deja un hueco en el que la alerta baja de nivel.

### Análisis por aspecto

| Aspecto | Efecto del cambio |
|---|---|
| Alergias | Nueva operación sobre una alergia **activa**; una inactiva o resuelta no se modifica (RN-10) |
| Severidad | Subir es libre. Bajar exige motivo (≥ 10 caracteres) porque reduce la alerta |
| Paciente | Debe seguir activo (RN-06) y ser del mismo hospital (RN-11) |
| Alertas visibles | El nivel se recalcula y se devuelve, como en el registro (DEC-05) |
| Reconocimiento | **Sin código nuevo:** la huella ya incluye `severidad`, así que todos los reconocimientos anteriores dejan de valer |
| Seguridad clínica | Solo el médico (`alergias.registrar`). La nueva severidad queda con `verified_by_user_ref_id` del médico que la cambió y `updated_at` |

### Cambios por capa

| Capa | Cambio |
|---|---|
| Dominio | `Alergia::cambiarSeveridad(Severidad $nueva, ?string $motivo, string $medico): Alergia`, que lanza `MOTIVO_REQUERIDO` si baja sin motivo y `ALERGIA_NO_ACTIVA` si no está activa |
| Repository | `RepositorioAlergias::porId(string $id)` y `actualizarSeveridad(Alergia $a)`, implementados en PDO (`UPDATE allergies SET severity = :s, verified_by_user_ref_id = :v, updated_at = now() WHERE allergy_id = :id AND status = 'active'`) y en InMemory, más los casos nuevos de la suite de contrato |
| Aplicación | `CambiarSeveridadAlergia` (CU-07) |
| API | `PATCH /api/v1/pacientes/{codigo}/alergias/{id}` con `{"severidad": "...", "motivo": "..."}` → 200 `Alerta`; 404, 409 `ALERGIA_NO_ACTIVA`, 422 `MOTIVO_REQUERIDO`. Contrato **v1.1**, compatible: no cambia ninguna operación existente |

### Pruebas primero (deben fallar antes del cambio)

| ID | Dado / Cuando / Entonces |
|---|---|
| T-CP1 | Ibuprofeno `moderate`; se sube a `life_threatening` → alerta CRÍTICA |
| T-CP2 | Alerta reconocida por la enfermera; se sube la severidad → vuelve a pedir reconocimiento |
| T-CP3 | Se baja de `severe` a `mild` sin motivo → 422 `MOTIVO_REQUERIDO` |
| T-CP4 | Enfermería intenta el PATCH → 403 |
| T-CP5 | Alergia `resolved` → 409 `ALERGIA_NO_ACTIVA` |
| T-CP6 | Contrato PDO = InMemory en `actualizarSeveridad` |

Estado: **diseño defendido**; no se implementa en el Micro-HIS, porque la semana 7 pide no ampliar el módulo.

## Cambio pedido en la evaluación

- **Cambio solicitado por el ingeniero:** `<anotar aquí durante la defensa>`
- **Respuesta dada (aspectos, capas y pruebas):** `<anotar>`
