# Reportes y analytics básicos — Semana 6

**Tema:** primera evaluación parcial.
**Tarea individual:** defender la arquitectura propuesta para «cálculo y visualización trazable de un indicador hospitalario»: conectar actores, UML, RF/RNF, principio SOLID, capas, Repository y contrato API, y responder un cambio práctico sobre indicadores hospitalarios, agregaciones, filtros, fuentes, precisión y visualización.
**Entregable:** presentación de 6–8 diapositivas, diagrama trazable, matriz decisión→evidencia y registro del cambio práctico defendido.

| Archivo | Contenido |
|---|---|
| `presentacion.md` | 7 diapositivas más la portada, en formato Marp (se exportan a PowerPoint o PDF con la extensión «Marp for VS Code») |
| `diagramas/trazabilidad.puml` | Del actor al caso de uso, al requerimiento, a la decisión de diseño y a su evidencia |
| `matriz-decision-evidencia.md` | Cada decisión con su razón, la alternativa descartada y dónde se comprueba |
| `cambio-practico.md` | Registro del cambio defendido: estancia promedio (ALOS) por sala con un decimal |

## Guion de la defensa (8 a 10 minutos)

| Minuto | Diapositiva | Qué mostrar en el repositorio |
|---|---|---|
| 0–1 | Portada y pregunta guía | — |
| 1–2 | Actores y casos de uso | `semana-01/01_casos_de_uso.png` |
| 2–3 | RF/RNF | Tabla de `semana-02/README.md` |
| 3–4 | LSP | `semana-02/ejemplo-lsp/verificar.php` ejecutándose |
| 4–6 | Capas y Repository | `semana-04/diagramas/capas-mvc.png` y `php pruebas/ejecutar.php` |
| 6–7 | API y frontera | `semana-05/contrato-api.md` y `cliente.html` en el navegador |
| 7–9 | Cambio práctico | `cambio-practico.md` |
| 9–10 | Matriz y cierre | `matriz-decision-evidencia.md` |

## Cómo exportar la presentación

1. Instalar en VS Code la extensión **Marp for VS Code**.
2. Abrir `presentacion.md` y usar el comando **Marp: Export Slide Deck** → PowerPoint (`.pptx`) o PDF.
