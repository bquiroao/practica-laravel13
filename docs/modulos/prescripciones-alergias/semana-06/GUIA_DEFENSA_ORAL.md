# Guía de defensa oral — Semana 6 (primera evaluación parcial)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Prescripciones electrónicas con validación de alergias · **Documento:** [semana-06-defensa-arquitectura.md](semana-06-defensa-arquitectura.md)

## 1. Guion por diapositiva (≈ 1,5 min cada una)

| # | Diapositiva | Mensaje |
|---|---|---|
| 1 | Portada | 107 pruebas, 33 defectos inyectados detectados, 23 decisiones con evidencia: todo se ejecuta |
| 2 | Actores y UML | Lo importante es lo que **no** está: ni «editar» ni «omitir verificación» |
| 3 | Requisitos y DIP | El «antes» emitía amoxicilina a un alérgico con el expediente caído; el contrato del puerto lo impide |
| 4 | Capas, MVC y Repository | Dos adaptadores, un contrato; la suite de contrato encontró una diferencia real (la dosis) |
| 5 | Contrato API y frontera | No se separa: con el Expediente remoto caído, 0 de 200 emisiones |
| 6 | Diagrama trazable | De actor a prueba; en verde, el cambio de hoy |
| 7 | Cambio práctico | La anulación es otro hecho; el bloqueo seguro es asimétrico; 7 → 0 |
| 8 | Cierre | Lo que no cambió y los comandos para verificarlo |

Las notas del orador de cada diapositiva tienen el texto completo.

## 2. Decisiones que más preguntan y cómo defenderlas

| Pregunta probable | Respuesta |
|---|---|
| ¿Por qué no un `UPDATE status = 'ANULADA'`? | Porque lo firmado no cambia (R-10), y el trigger lo rechazaría. La anulación es otro registro, también inmutable; el estado se deriva |
| ¿No es incoherente fallar cerrado al emitir y abierto al anular? | No es «abierto»: anular no **consulta** el Expediente porque no hay nada que verificar. Bloquear una acción que reduce el riesgo por una falla ajena sería proteger en la dirección equivocada |
| ¿Por qué 200 y no 201? | La anulación no es un recurso que se consulte por separado; se ve dentro de la prescripción |
| ¿Cómo saben que el cambio no rompió nada? | Los 32 escenarios grabados en la semana 7 dan lo mismo byte a byte (CAR-01), y el contrato es compatible (la clave nueva solo aparece si hay anulación) |
| ¿Qué pasa si dos médicos anulan a la vez? | La política responde 409 en el caso normal; si llegan juntos, la clave primaria de `prescription_voids` impide la segunda y el caso de uso responde 409 igual (`YaAnulada`) |
| ¿Por qué DIP y no otro principio? | Porque el riesgo principal del módulo es una dependencia que falla (el Expediente). DIP puso la regla de qué hacer ante esa falla en la abstracción, donde ningún adaptador la puede saltar |

## 3. Demostración en vivo (2 minutos)

```bash
cd docs/modulos/prescripciones-alergias/micro-his-prescripciones
php bin/demo-anulacion.php              # pasos 9 y 10: emitir 503, anular 200
php tests/ejecutar.php --solo=ANU,CAR
# Mostrar que una prueba no pasa por casualidad: en PoliticaAnulacion.php cambiar
#   mb_strlen(trim($motivo)) < self::MIN_MOTIVO ? 'MOTIVO_REQUERIDO' : null   por   null
# → falla ANU-02 (es la mutación M31). Restaurar.
```

## 4. Si piden un cambio en vivo

- **«Que no se pueda anular una prescripción ya dispensada.»** Hay que agregar el estado «dispensada» (otro hecho, de farmacia) y una línea en `PoliticaAnulacion` antes del motivo. La prueba va primero.
- **«Que el motivo salga de un catálogo.»** Es un cambio en el formulario (formato) y en la política (valores admitidos). El contrato sube a 1.2.0 con un `enum`.
- **«Que se notifique a farmacia.»** Va por la traza `PRESCRIPCION_ANULADA` en la bandeja, que ya existe: el consumidor la reenvía a Alertas (ASII-21).
