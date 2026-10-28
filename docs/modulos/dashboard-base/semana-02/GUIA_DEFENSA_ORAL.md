# Guía de defensa oral — Semana 2

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard base UI/UX · **Documento:** [semana-02-srp-requerimientos-diseno.md](semana-02-srp-requerimientos-diseno.md)

## 1. Resumen en 60 segundos

> «Antes, una sola función cargaba la tarjeta: llamaba a la API, decidía el estado, escribía el texto y cerraba la sesión. Eran cuatro razones para cambiar. Ahora el cliente HTTP corta a los 10 segundos, una función pura decide el estado, otra pura escribe el texto y la orquestadora solo las une. El script demuestra que en 7 escenarios el usuario ve exactamente lo mismo, que ningún texto muestra códigos técnicos y que "vacío no es error" se prueba sin red.»

## 2. Decisiones clave

| Decisión | Justificación |
|---|---|
| JavaScript y no PHP | El dashboard es frontend Vue; el ejemplo debe ser del mismo lenguaje que se implementará. |
| Funciones puras para estado y texto | Se prueban sin API ni sesión, y cualquier módulo las puede reutilizar. |
| Cliente y sesión inyectados | La prueba usa una API simulada y una sesión falsa. |

## 3. Preguntas probables

**Si UX decide que 403 muestre «sin permiso» en vez de ocultar, ¿qué cambia?** `estadoDeRespuesta()` y `textoDeEstado()`; el cliente HTTP y la orquestadora no se tocan (RNF-03).

**¿Cómo se prueba el límite de 10 s sin esperar 10 s?** El límite es un parámetro de `crearClienteTarjetas()`; la prueba lo pone en 30 ms con una API que nunca responde (CA-04).

**¿Cómo se corre la evidencia?** `node docs/modulos/dashboard-base/semana-02/ejemplo-srp/verificar.mjs`; debe terminar con «RESULTADO: todo correcto» y exit 0.
