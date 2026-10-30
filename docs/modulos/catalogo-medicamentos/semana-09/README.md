# Semana 9 — Catálogo de medicamentos: evaluación de usabilidad y accesibilidad

| Entregable | Archivo |
|---|---|
| Checklist completado (10 heurísticas, 17 criterios WCAG 2.1 AA, 6 reglas del dominio) | `checklist.md` |
| Evidencia de 11 hallazgos (mínimo pedido: 6) | `hallazgos.md`, `capturas/` |
| Backlog priorizado con corrección y criterio verificable | `backlog.md` |
| Cálculo de contraste reproducible | `evidencia/calcular-contraste.php` |

## Método

1. Recorrido heurístico de la web del Micro-HIS (semana 4) y de los wireframes (semana 8), por rol.
2. Pruebas automáticas con Chromium (Playwright): orden de Tab, elemento con foco después de un 422,
   cantidad de campos con `aria-required`, enlaces en el resumen de errores y desplazamiento
   horizontal a 320, 375 y 430 px. Las capturas de `capturas/` salen de ese recorrido.
3. Contraste con la fórmula de luminancia relativa de WCAG 2.x sobre los colores de `layout.php`.

No hubo pruebas con usuarios reales; es una evaluación experta del autor.

## Cómo reproducir el contraste

```powershell
cd docs/modulos/catalogo-medicamentos/semana-09/evidencia
php calcular-contraste.php --evidencia=contraste.txt
cd ../../../../..
```

Resultado al preparar la entrega: todos los textos cumplen (mínimo 6,67:1); el indicador de foco
**no cumple** (1,82:1, mínimo 3:1); el foco propuesto `#1d4ed8` da 6,70:1.

## Resumen

| Impacto | Hallazgos |
|---|---|
| Alto | H-01 foco, H-02 errores sin foco ni enlaces, H-03 eliminar sin confirmación, H-05 error no informado |
| Medio | H-04, H-06, H-07, H-09, H-10 |
| Bajo | H-08, H-11 |

El más importante para el catálogo es **H-03**: un clic borra un registro (aunque RN-02 impide
borrar lo usado en prescripciones). **H-05** contradice RF-02 y pasa a la semana 15 como defecto.
