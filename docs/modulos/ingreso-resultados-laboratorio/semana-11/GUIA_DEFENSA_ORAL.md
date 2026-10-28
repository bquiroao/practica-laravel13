# Guía de defensa oral — Semana 11

## 1. Objetivo de la defensa

Defender el prototipo navegable del módulo de ingreso de resultados de laboratorio y explicar cómo el diseño reduce errores críticos, mejora la accesibilidad y mantiene la trazabilidad del proceso.

## 2. Estructura sugerida

1. Presentar el flujo principal del módulo.
2. Explicar la lógica del prototipo desktop/móvil.
3. Mostrar el manejo de errores críticos.
4. Describir la estrategia de accesibilidad.
5. Concluir con la ventaja del diseño para la operación real.

## 3. Puntos clave a explicar

- El diseño debe proteger la integridad del dato y la claridad del estado.
- La corrección requiere más contexto que la captura inicial.
- La validación temprana de valores y unidades reduce errores clínicos y operativos.
- Los errores críticos necesitan mensajes asociados al campo, no solo cambios de color.
- La consistencia entre desktop y móvil ayuda a mantener el mismo nivel de seguridad.

## 4. Preguntas probables del docente

### ¿Qué fue lo más importante del prototipo?

La prevención de errores críticos y la claridad del estado. Si el sistema no diferencia bien un resultado pendiente de uno corregido, la operación puede volverse insegura.

### ¿Por qué la accesibilidad es central en este módulo?

Porque la entrada de resultados se realiza sobre información de diagnóstico y su error puede afectar la toma de decisiones. La persona operaria necesita lectura clara, mensajes asociados a campos y navegación fiable.

### ¿Qué harías en la siguiente iteración?

Refinar las reglas de validación por tipo de prueba, mejorar la diferenciación entre estados y definir la interacción con el módulo de validación bioquímica.

## 5. Criterios de una buena defensa

- Hablar en términos de seguridad operativa, no solo de estética.
- Mostrar que el flujo prioriza la prevención de errores.
- Definir claramente la diferencia entre resultado, corrección y validación.
- Asegurar que el diseño se entiende en mobile y desktop.
- Reconocer que la validación definitiva depende del catálogo y del módulo de validación clínico.
