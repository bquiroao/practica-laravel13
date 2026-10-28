# Semana 12 — Segunda evaluación parcial: defensa de componentes, UX, accesibilidad y movilidad

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** ASII-03 · Pacientes: registro, edición, búsqueda y detalle

**Proceso:** «registro, búsqueda y actualización segura de un paciente»

## Entregables

| Entregable pedido | Archivo |
|---|---|
| Presentación breve | [presentacion/semana-12-defensa-pacientes.pptx](presentacion/semana-12-defensa-pacientes.pptx) (8 diapositivas con notas del orador) · [PDF](presentacion/semana-12-defensa-pacientes.pdf) |
| Recorrido navegable | [recorrido-navegable.md](recorrido-navegable.md): guion de unos 6 minutos sobre [prototipo/index.html](prototipo/index.html) |
| Matriz decisión → RF/RNF → evidencia | [matriz-decision-rf-rnf-evidencia.md](matriz-decision-rf-rnf-evidencia.md): D-22..D-37 y los RNF-12..15 formalizados |
| Respuesta documentada a un cambio solicitado | [registro-cambio-solicitado.md](registro-cambio-solicitado.md): B-4, «corregir un teléfono sin el documento a mano», contrato 1.2.0 |

## 1. Qué se defiende, en una frase por tema

| Tema | Decisión central | Semana |
|---|---|:-:|
| **Componentes** | Las reglas de la ficha tienen un solo dueño en el Dominio (`RevisionDeFicha`); la interfaz no decide reglas, solo muestra lo que el servidor manda. | 7 |
| **UX** | Buscar antes de registrar; un flujo por rol; cada mensaje sale de un código real del backend; nada personal en la URL ni en el dispositivo. | 8 |
| **Accesibilidad** | Se mide, no se supone: checklist de 93 ítems, contraste con script, axe-core en cada pantalla del prototipo y teclado comprobado en el diálogo. | 9·11 |
| **Movilidad** | Mobile-first de 320 px con 5 reglas verificadas; ante un corte de red, el registro se reintenta sin duplicar y la edición se recarga. | 10 |
| **Prototipo** | Una sola app para escritorio y móvil, con una API simulada que responde igual que el kernel real en 37 de 37 situaciones. | 11·12 |
| **Roles, estados, errores y riesgos** | Admisión, clínico y supervisión ven y pueden hacer cosas distintas sobre la misma ficha. El error crítico (un duplicado de una paciente protegida) se bloquea sin revelarla. | 8–12 |

Cómo trata el flujo cada aspecto de la tarea:

| Aspecto | Rol / estado / error / riesgo que se demuestra | Dónde |
|---|---|---|
| Identidad | Un corte de red no duplica (misma `Idempotency-Key`); el documento no se pierde al editar (1.2.0) | Recorrido A-4..A-6, A-8 |
| Datos demográficos | «Desconocido» para emergencias; fecha nativa; nombres y apellidos separados | A-3, A-6 |
| Búsqueda | Un campo; los protegidos no aparecen por nombre; los criterios no van a la URL | A-2, B-9 |
| Edición | Motivo solo si cambia la identidad; confirmación antes → después; nunca sobrescribe ante un 409 | A-7, A-8 |
| Duplicados | `PACIENTE_EXISTENTE` y `POSIBLE_DUPLICADO` con una salida segura por rol | B-10 |
| Confidencialidad | «Reservado» para el clínico; el aviso del duplicado no revela a la paciente; supervisión sí la ve | B-10..B-12 |

## 2. Cifras verificables

| Verificación | Comando (desde la carpeta indicada) | Resultado |
|---|---|---|
| Pruebas del Micro-HIS | `micro-his-pacientes/`: `php tests/ejecutar.php` | 87: **85 pasan, 0 fallan**, 2 omitidas (PostgreSQL) |
| Mutaciones | `micro-his-pacientes/`: `php tests/mutaciones.php` | **26/26** detectadas |
| Contrato | `micro-his-pacientes/`: `npx @redocly/cli lint api/openapi.yaml` | 1.2.0 válido |
| Paridad prototipo ↔ kernel | `semana-12/`: `node herramientas/paridad.cjs` | **37/37** iguales |
| Recorrido con axe | `semana-12/`: `node herramientas/recorrido.cjs` | **36** comprobaciones, 0 fallas, axe 0 |
| Breakpoints | `semana-10/`: `node herramientas/verificar-responsive.cjs prototipo capturas` | 39 vistas, 0 fallas |
| Contraste | `semana-09/`: `python herramientas/contraste.py` | Propuestas: 0 fallas |

## 3. Estructura de la carpeta

```
semana-12/
├── presentacion/                     ← 8 diapositivas (.pptx y .pdf)
├── recorrido-navegable.md            ← guion de la demostración en vivo
├── matriz-decision-rf-rnf-evidencia.md
├── registro-cambio-solicitado.md     ← B-4 → contrato 1.2.0
├── prototipo/                        ← el prototipo de la semana 11 con el contrato 1.2.0 (sin pedir el documento al editar)
├── herramientas/                     ← paridad (37 pasos) y recorrido (escritorio + móvil, axe)
├── capturas/escritorio, capturas/movil
└── evidencia/                        ← rojo → impacto → verde, mutaciones, lint, paridad y recorrido
```

El código del cambio vive donde corresponde, en [`micro-his-pacientes/`](../micro-his-pacientes/README.md): `src/`, `api/openapi.yaml` y `tests/Aplicacion/CambioSolicitadoPrueba.php`.

## 4. Limitaciones que se declaran en la defensa

- **El prototipo usa una API simulada.** Su fidelidad está probada en 37 situaciones, no en todas.
- **Accesibilidad:** axe-core y el recorrido de teclado no reemplazan una prueba con lector de pantalla ni con usuarios reales de admisión y enfermería.
- **El frontend no está implementado en `resources/js`**: componentes y prototipo son diseño, listos para la implementación coordinada con el equipo de UI base.
- **PostgreSQL:** 2 pruebas de integración siguen sin ejecutarse por falta de credenciales.
- **Brechas abiertas del contrato:** B-1 (cambiar el estado del paciente), B-2 (pedir revisión de una coincidencia) y B-3 (historial de la ficha). B-4 se cerró esta semana.
