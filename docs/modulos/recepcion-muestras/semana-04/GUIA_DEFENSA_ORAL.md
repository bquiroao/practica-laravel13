# Guía de defensa oral — Semana 4

## 1. Objetivo de la defensa

Defender la estructura de la solución del módulo de recepción de muestras y mostrar por qué la separación entre entrada, negocio y persistencia es crítica para la trazabilidad, la seguridad y la mantenibilidad.

## 2. Estructura sugerida

1. Presentación del problema del módulo.
2. Qué cambió respecto de la semana 3.
3. Arquitectura en capas del módulo.
4. Explicación de MVC en la entrada.
5. Justificación del patrón Repository.
6. Riesgos y próximos pasos.

## 3. Puntos clave a explicar

- La lógica de negocio no vive en el controlador.
- El dominio define identidades, reglas y puertos de acceso.
- La persistencia se adapta al dominio, no al revés.
- La recepción y el rechazo deben quedar trazados con actor, motivo y momento.
- La arquitectura debe permitir cambiar la tecnología sin cambiar la decisión del negocio.

## 4. Preguntas probables del docente

### ¿Por qué la separación por capas es importante en un hospital?

Porque permite reducir errores de integración y mejorar la trazabilidad de decisiones críticas como aceptar o rechazar una muestra.

### ¿Qué diferencia hay entre validación de entrada y validación de negocio?

La validación de entrada comprueba formato y estructura. La validación de negocio comprueba que la orden, la identidad y las condiciones de la muestra son correctas.

### ¿Qué ventaja tiene el Repository en este caso?

Permite probar el mismo flujo con una implementación en memoria y una persistencia real, sin mezclar la lógica de la muestra con SQL.

### ¿Qué harías después de esta etapa?

Definir mejor el contrato API, los permisos por rol y la trazabilidad en la base de datos del HIS.

## 5. Criterios de una buena defensa

- Hablar en términos de responsabilidades y trazabilidad, no solo de estructura.
- Mostrar que la lógica clínica y administrativa no se mezcla con la capa web.
- Explicar por qué el Repository ayuda a probar y a evolutivamente integrar con la base del HIS.
- Reconocer limitaciones del diseño y proponer trabajo siguiente.
