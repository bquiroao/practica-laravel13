# Guía de defensa oral — Semana 10 (móvil 320–430 px)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA funcional, pruebas manuales y matriz de regresión · **Documento:** [semana-10-movil-qa.md](semana-10-movil-qa.md)

## 1. Resumen en 60 segundos

Construí 4 pantallas responsive en HTML y CSS, mobile-first desde 320 px, con 5 breakpoints. Verifiqué 8 reglas medibles en 32 vistas con un navegador real: sin desplazamiento horizontal, objetivos de 44 px, fuente de 16 px en los campos, el caso siempre arriba, sin controles deshabilitados y chips con texto. Las 32 pasan.

Para no confiar a ciegas, sembré 4 defectos en una copia y el instrumento los detectó. Esa contraprueba encontró un hueco del propio instrumento, que corregí.

Dos escenarios reales:
- el tester registra una falla con foto y sin señal;
- el Líder QA decide la liberación desde el pasillo.

Apliqué 11 de los 12 ítems del backlog de la semana 9; el 12.º (todos los colores como tokens) queda pendiente y está medido.

## 2. Orden sugerido

1. Por qué QA en el móvil (§1) y la jerarquía (§2).
2. Las reglas de breakpoint y el comando que las verifica (§3).
3. **La contraprueba y el hueco de R-P1** (§3.1).
4. Escenario 1: la evidencia clínica sin red (B-01).
5. Escenario 2: `bloquea_go` en tarjetas.

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué 44 px si WCAG pide 24? | Se usa de pie y en movimiento; 44 es la guía de las plataformas móviles. |
| ¿Cómo sabe que las reglas funcionan? | Sembré 4 defectos en una copia y fallaron 16 vistas. Así descubrí que R-P1 no medía con la pantalla desplazada. |
| ¿Y si el tester pierde la evidencia clínica? | Es el costo elegido: protegerla en un equipo compartido vale más. Se le avisa «No cierre esta pantalla»; si la cierra, la vuelve a tomar. |
| ¿Por qué tarjetas y no la tabla? | Cinco columnas no caben en 320 px sin desplazamiento horizontal; la tarjeta además dice por qué bloquea. |
| ¿Qué falta? | B-12 (11 colores fuera de los tokens) y axe-core, en la semana 11. |
