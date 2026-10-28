# Guía de defensa oral — Semana 9

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** RBAC · **Documento:** [semana-09-evaluacion-ux-rbac.md](semana-09-evaluacion-ux-rbac.md)

## 1. Resumen en 60 segundos

> «Evalué el diseño UX que hice la semana pasada con tres herramientas: las 10 heurísticas de Nielsen, 21 criterios de WCAG 2.2 AA y un checklist propio de RBAC. En total son 93 ítems.
>
> El contraste no lo estimé: escribí un script que calcula la razón WCAG con los colores exactos de los wireframes. Fallan 9 de 21 pares, sobre todo el gris de las etiquetas, que da 3.26 a 1 cuando el mínimo es 4.5.
>
> Encontré 16 hallazgos. Los prioricé multiplicando la severidad de Nielsen por el impacto en RBAC, porque no es lo mismo un problema que molesta que uno que da permisos a la persona equivocada.
>
> El más grave es que, al asignar un rol, el usuario se escribe a mano y la confirmación no dice quién es: una errata le da acceso clínico a otra persona. Los siguientes son que un usuario con lector de pantalla no se entera de que fue denegado, y que el diálogo de revocar deja cambiar de hospital.
>
> Cada ítem del backlog tiene una corrección y un criterio que se puede comprobar: un comando, una prueba e2e o un recorrido con teclado.»

## 2. Orden sugerido de la exposición

1. **Método (§2):** tres técnicas y la fórmula de prioridad.
2. **Contraste (§3):** correr el script en vivo.
3. **Tres hallazgos P1 con su recorte:**
   - H-01: usuario sin identificar;
   - H-04: denegación sin anuncio;
   - H-08: el diálogo deja la cabecera activa (se ve en el recorte).
4. **Checklist:** mostrar el resumen (93 ítems) y el recorrido de teclado de W3.
5. **Backlog (§6):** leer BL-01 y BL-03 con su criterio.
6. **Fortalezas y limitaciones (§5, §7).**

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Multiplicar severidad × impacto RBAC | La tarea pide priorizar «según impacto en roles, permisos, rutas, decisiones y denegaciones». La severidad sola trataría igual un problema de navegación que uno que otorga permisos indebidos. |
| Medir el contraste con un script | Es reproducible y sirve como criterio de aceptación: termina con código 1 si una propuesta no cumple. |
| Evaluar también lo que **no** se especificó | Un diseño que no dice dónde queda el foco deja la decisión al programador. Por eso H-04 y H-08 son hallazgos aunque nada esté «mal dibujado». |
| No corregir los wireframes esta semana | La tarea es evaluar y priorizar. Corregir sin priorizar mezcla dos entregas. |
| Mantener el 404 idéntico en H-10 | La corrección mejora el mensaje sin revelar si el evento existe en otro hospital (D-3). |
| BL-01 requiere backend | No se puede mostrar el nombre sin un endpoint que lo dé. Lo registré como brecha B-4 en vez de inventar datos en el frontend. |

## 4. Preguntas probables y respuestas

**¿Qué diferencia hay entre una heurística y un criterio WCAG?**
La heurística es un principio de diseño que se juzga, por ejemplo «prevenir errores». El criterio WCAG es una regla verificable con umbral, por ejemplo «texto con contraste de 4.5:1».

**¿Por qué 4.5:1 y no 3:1 para las etiquetas?**
WCAG llama «texto grande» al de 18 pt o más, o 14 pt o más en negrita. Las etiquetas son de 10 pt en negrita, así que necesitan 4.5:1. El script aplica esa regla por tamaño.

**Los botones deshabilitados tienen 1.53:1, ¿no es un fallo?**
WCAG exime a los componentes deshabilitados del 1.4.3. Pero el botón «Revocar rol» deshabilitado es un problema de usabilidad: no explica qué falta y no recibe foco (H-05).

**¿Cómo se verifica BL-03 si no hay frontend?**
El criterio queda escrito para cuando exista. Con NVDA, al llegar a W5 se anuncia «Acceso denegado», y una prueba e2e comprueba `document.title` y que el foco esté en el `h1`.

**¿Por qué H-01 es catastrófico (4)?**
Porque el error no se puede deshacer sin daño: el usuario equivocado ya pudo ver expedientes. Además, la confirmación existe pero no sirve para detectarlo.

**¿Cuántos evaluadores hacen falta?**
Nielsen recomienda de 3 a 5. Yo fui uno solo, y lo digo en las limitaciones. Un compañero podría repetir el checklist.

**¿Qué fortalezas tiene el diseño?**
- El 403 no revela nada.
- Siempre hay una referencia y un siguiente paso.
- Es fail-closed.
- Los estados se muestran con icono y texto.
- El diálogo de revocar tiene el foco inicial en «Cancelar».

## 5. Demostración en vivo

```bash
cd docs/modulos/rbac/semana-09
python herramientas/contraste.py                 # 9 fallas actuales, 0 en las propuestas
python herramientas/contraste.py --solo-propuestos
# Abrir evidencia/hallazgos/h-08-modal-sin-trampa-de-foco.png: la cabecera queda fuera del velo.
```

Si Windows muestra mal los caracteres, anteponer `PYTHONIOENCODING=utf-8` (o en PowerShell: `$env:PYTHONIOENCODING="utf-8"`).

## 6. Cambios que podrían pedirte en vivo

| Pedido | Cómo hacerlo |
|---|---|
| «Prueba otro gris para las etiquetas» | Cambiar la propuesta de C-01 en `contraste.py` y volver a correrlo. |
| «Sube la severidad de H-07 a 3» | 3 × 3 = 9 → pasa a P1. Mover BL-08 al bloque P1 y explicar por qué la confirmación no basta. |
| «Agrega un hallazgo» | Fila nueva en el checklist (P/N), sección H-17 con recorte, ítem de backlog con criterio. |
| «Mide el contraste de un color nuevo» | Agregar una tupla a `PARES` con su uso, tamaño y umbral. |
