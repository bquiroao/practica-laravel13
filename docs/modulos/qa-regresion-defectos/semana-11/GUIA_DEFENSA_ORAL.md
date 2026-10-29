# Guía de defensa oral — Semana 11 (prototipo navegable)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA funcional, pruebas manuales y matriz de regresión · **Documento:** [semana-11-prototipo-qa.md](semana-11-prototipo-qa.md)

## 1. Resumen en 60 segundos

El prototipo se navega en escritorio y en móvil, con una API simulada que responde lo mismo que el contrato.

- **Camino feliz:** el tester registra dos casos y el Líder QA cierra con GO.
- **Error crítico:** una falla con foto clínica justo cuando se cae la red. Queda pendiente sin guardarse en el equipo, se reenvía sin duplicar y el Líder QA ve por qué no se puede liberar.

Un recorrido automático hace todo eso en los dos anchos y en cada paso comprueba textos, foco y axe-core: 46/46 y 0 violaciones.

Encontré tres problemas:
- axe vio controles activos detrás del diálogo (faltaba `inert`);
- los radios medían 22 px;
- la previsión del GO contaba una causa menos que el cierre. Este último lo encontré mirando las capturas, no con una prueba.

## 2. Demostración sugerida (5 minutos)

1. Abrir `prototipo/index.html` en un ancho de 360 px.
2. Tester → Ejecutar → Falla → Registrar: el error lleva el foco al botón de la foto.
3. Abrir «Demostración: estado de la red» → Cortar la red → registrar → Pendiente y «No cierre esta pestaña».
4. Restablecer la red → Pendientes → Reenviar: se anuncia «TC-107 registrado».
5. Líder QA → Trazabilidad: «2 causas» y ⛔ Bloquea → Cerrar… → Esc → Cerrar con No liberar.

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué ese error crítico? | Cruza R-05, R-12 y la protección de datos clínicos: es donde más cuesta equivocarse. |
| ¿Qué es `inert` y por qué hacía falta? | Hace que lo que está detrás del diálogo no se pueda tocar ni tabular. `aria-modal` solo informa al lector, no lo impide; axe lo detectó. |
| Si el recorrido pasaba, ¿cómo encontró H-3? | Mirando la captura: decía «1 causa» y el cierre daba dos. Reforcé la prueba y unifiqué el cálculo en una sola función. |
| ¿La API simulada no esconde problemas reales? | Usa los mismos códigos del contrato; la integración HTTP real ya la probó el cliente PHP en la semana 5. |
