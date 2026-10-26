# Guía de defensa oral — Semana 9

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** ASII-03 Pacientes · **Documento:** [semana-09-evaluacion-ux-pacientes.md](semana-09-evaluacion-ux-pacientes.md) · **Checklist:** [checklist-evaluacion.md](checklist-evaluacion.md)

## 1. Resumen en 60 segundos

> «Evalué el diseño UX de la semana 8 con cuatro técnicas: las diez heurísticas de Nielsen en las seis pantallas, 21 criterios de WCAG 2.2 AA, un script que mide el contraste con los colores exactos de los wireframes, y una comparación de cada regla de la interfaz con el código del backend. La checklist tiene 93 ítems: 64 cumplen, 21 en parte y 8 no. Encontré 14 hallazgos. Los más graves salieron de comparar con el backend: la pantalla de duplicados le muestra al supervisor "Reservado" cuando el servidor le da el código real, y la de edición pide motivo por cambiar una tilde, que para el backend no es un cambio de identidad. Además, admisión no tiene salida ante una coincidencia, y "Copiar mis cambios" saca datos del paciente al portapapeles. Prioricé multiplicando la severidad de Nielsen por el impacto en los seis aspectos de la tarea: 4 hallazgos P1, 6 P2 y 4 P3. Cada uno tiene una corrección y un criterio verificable.»

## 2. Orden sugerido de la exposición

1. **Método** (§2) y la fórmula de prioridad (§2.1).
2. **Checklist**: el resumen de 93 ítems y un ejemplo por bloque.
3. **Contraste**: ejecutar `python herramientas/contraste.py`.
4. **Los 4 P1** (H-01..H-04), con su recorte.
5. **Backlog**: leer el criterio verificable de BL-01 y BL-03.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Comparar el diseño con el backend | Una UI que aplica otra regla que el servidor confunde al usuario o lo lleva a errores. Así salieron los dos hallazgos más concretos (H-01, H-02). |
| Severidad × impacto | Un problema «menor» que puede crear un duplicado o exponer a una paciente protegida pesa más que uno «mayor» sin esas consecuencias. |
| El contraste quedó en P2, no en P1 | Afecta a todas las pantallas, pero no produce por sí solo una identidad equivocada ni una fuga. RBAC lo puso en P1 porque ahí la escala de impacto era otra. |
| Criterios «Dado… cuando… entonces» | Hacen que cada corrección se pueda comprobar con una prueba, no con una opinión. |
| No corregir los wireframes esta semana | La tarea es evaluar y priorizar. Las correcciones quedan en el backlog con su criterio. |

## 4. Preguntas probables y respuestas

**¿Qué diferencia hay entre una heurística y un criterio WCAG?**
Una heurística es un principio general de usabilidad, aplicado con juicio (por ejemplo, «prevención de errores»). Un criterio WCAG es una regla verificable de accesibilidad, a veces con un umbral numérico (por ejemplo, contraste ≥ 4.5:1).

**¿Cómo se calcula el contraste?**
Con la luminancia relativa de cada color: (L1 + 0,05) / (L2 + 0,05), con L1 la más clara. El texto normal necesita 4,5:1; el texto grande y los bordes, 3:1. El script lo hace con los colores exactos del generador.

**¿Por qué H-02 es P1 si solo pide un motivo de más?**
Porque demuestra que la UI tiene una regla de identidad distinta de la del servidor. Hoy pide de más; mañana, con otro cambio, podría pedir de menos. La corrección (BL-01) obliga a usar la misma normalización en los dos lados, con una tabla de casos compartida.

**¿Por qué el portapapeles es un problema de confidencialidad?**
Porque sale del control de la aplicación: otras aplicaciones lo leen, los historiales de portapapeles lo guardan, y no queda registro en la bitácora del HIS.

**¿Cuántos evaluadores hacen falta?**
Nielsen recomienda de 3 a 5. Aquí hubo uno, y está declarado como limitación.

## 5. Recorrido de demostración

Desde `docs/modulos/pacientes/semana-09/`:

1. `python herramientas/contraste.py` → 9 FALLA, propuestas 0 fallas, código de salida 0.
2. Abrir el recorte de H-02 y ejecutar desde `micro-his-pacientes/`:
   `php -r 'require "bootstrap/autoload.php"; var_dump(Pacientes\Dominio\Modelo\Texto::normalizar("Ana Lucía") === Pacientes\Dominio\Modelo\Texto::normalizar("Ana Lucia"));'` → `bool(true)`.
3. Abrir `PoliticaDuplicados::visibles()` para mostrar que con `leer_protegido` se devuelve el código real (H-01).
