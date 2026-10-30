# Guía de defensa oral — Semana 12

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard de ocupación hospitalaria · **Documentos:** [presentación](semana-12-presentacion.md) y [recorrido, matriz y cambio](semana-12-defensa-prototipo.md)

> «Con el prototipo muestro el tablero en 9 pasos: ocupación por sala con nivel en texto, hora y fuente del corte, aviso de datos viejos, filtro anunciado y un corte fallido que no borra el anterior. El cambio solicitado fue mostrar las camas que no cuadran: cada sala lista las camas cuyo estado no coincide con sus estancias abiertas y dice dónde se corrigen. El tablero marca, pero no corrige.»

**¿Por qué mostrar las inconsistencias si la ocupación no cambia?** Porque la cifra puede estar bien y aun así esconder un problema: dos pacientes en una cama. La procedencia del dato importa tanto como el número para decidir un ingreso.

**¿Por qué «Dónde se corrige» y no un botón «Corregir»?** Porque el tablero es de solo lectura (R-09). Corregir una estancia es trabajo de admisión, con su auditoría. El tablero solo dice qué pasa, qué hacer y a quién acudir.

**¿Qué ajustaste al revisar las capturas?** El `<h2>` nuevo se veía más grande que el `<h1>` de la sala. Un encabezado de sección no debe parecer más importante que el título de la página (WCAG 1.3.1), y lo bajé de tamaño.

**¿Qué decisión de accesibilidad defiendes?** El nivel de ocupación siempre en texto («baja», «media», «alta») además del color, porque el color solo no llega a todos (WCAG 1.4.1).

**¿De dónde salen los datos en producción?** Del campo `inconsistentes` que calcula el backend al consolidar (semana 6). El prototipo usa datos ficticios con la misma forma.
