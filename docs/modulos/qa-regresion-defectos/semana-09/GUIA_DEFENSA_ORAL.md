# Guía de defensa oral — Semana 9 (evaluación heurística y WCAG)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA funcional, pruebas manuales y matriz de regresión · **Documento:** [semana-09-evaluacion-ux-qa.md](semana-09-evaluacion-ux-qa.md)

## 1. Resumen en 60 segundos

Evalué mi propio diseño de la semana 8 con las diez heurísticas de Nielsen, 22 criterios de WCAG 2.2 AA y seis criterios del módulo. Resultado: 21 cumplen, 12 en parte y 5 no.

Encontré 12 hallazgos, cada uno con su recorte. El contraste lo **medí**: 12 de 22 pares de color fallan.

El hallazgo más importante es una contradicción mía. La bandeja de pendientes promete que «nada se pierde», pero deja evidencia clínica en un equipo compartido si se cierra el navegador.

El backlog tiene 12 ítems priorizados, cada uno con un criterio que se comprueba con un comando o una inspección concreta.

## 2. Orden sugerido

1. El método y la escala de severidad (§2).
2. H-08 (datos clínicos), H-01 (teclado) y H-02 (contraste), con sus recortes.
3. Correr `python herramientas/contraste.py` en vivo: 12 fallan hoy, 0 con la paleta.
4. El backlog: por qué B-03 propone **no** deshabilitar botones.

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué evaluar wireframes y no la app? | La tarea evalúa el diseño antes de construirlo: es más barato corregir aquí. El prototipo de las semanas 10–11 se mide de nuevo con axe-core. |
| ¿Cómo midió el contraste? | Con la fórmula de WCAG 2.2, sobre los colores exactos del generador de los wireframes, no a ojo. |
| ¿Por qué no deshabilitar el botón? | Un botón deshabilitado no recibe el foco: el teclado no llega a saber qué falta. Habilitado, lleva el foco al error. |
| ¿Qué es lo más grave? | H-08: evidencia clínica guardada en un equipo compartido. B-01 la deja solo en memoria. |
| ¿Cómo sabré que B-02 quedó hecho? | `contraste.py` termina con código 0. |
