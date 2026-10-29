# Guía de defensa oral — Semana 15 (pruebas)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA funcional, pruebas manuales y matriz de regresión · **Documento:** [semana-15-pruebas.md](semana-15-pruebas.md)

## 1. Resumen en 60 segundos

Escribí 12 pruebas trazadas a RF/RNF:
- **4 unitarias** de reglas de campaña, defecto y severidad.
- **3 de caja blanca:** cobertura de ramas del cierre, MC/DC de la regla de bloqueo y caminos de la trazabilidad.
- **5 de caja negra por HTTP:** equivalencia, límites, permisos, errores, y que la trazabilidad diga lo mismo que el cierre.

Las escribí antes de corregir los hallazgos de la revisión de la semana 13, y 8 fallaron por el defecto que buscaban. Después corregí cuatro defectos del producto (uno crítico) y uno de documentación, con 5 mutaciones nuevas que deshacen cada corrección.

Resultado: 112/112 con PostgreSQL, 40/40 mutaciones y la sonda pasa de 6 a 2 hallazgos (los dos agendados). Registré también cinco defectos de las pruebas: dos daban rojos falsos y uno tenía el oráculo equivocado.

## 2. Demostración sugerida (4 minutos)

1. `php tests/ejecutar.php --solo=UNI,CB,CN` → 12/12.
2. Abrir CB-02: la tabla MC/DC, y mostrar que cada par de filas cambia una sola condición y cambia el resultado.
3. `php ../semana-13/herramientas/sonda-revision.php` → solo RT-03 y RT-04.
4. Mostrar en `mutaciones.txt` la línea M36: si la propuesta deja de contar, fallan UNI-01, CB-02 y CN-05.

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Qué diferencia hay entre su caja blanca y su caja negra? | CB-01 y CB-02 se diseñaron leyendo el código: cada rama y cada condición. Las CN se diseñaron leyendo los requisitos y el contrato, y entran por HTTP sin saber cómo está hecho. |
| ¿Qué es MC/DC y por qué en `bloqueaGo`? | Que cada condición cambie el resultado por sí sola. `bloqueaGo` decide si se libera un build: es la expresión donde una condición mal escrita cuesta más. |
| ¿Por qué contar la severidad propuesta? | Ante la duda, mejor equivocarse hacia NO-GO. Si el tester exagera, el Líder QA la baja con un motivo que queda en el historial; si nadie la revisa, no se libera. |
| ¿No se rompió la prueba CON-06? | La prueba afirmaba el defecto como regla: era la decisión de la semana 4. La reescribí y dejé un comentario con el porqué. |
| ¿Qué son los «defectos de las pruebas»? | Cuatro errores míos en las pruebas nuevas, más CON-06. Dos daban rojo por la razón equivocada. Un rojo solo vale si falla por el defecto que busca. |
| ¿Cómo sabe que las pruebas nuevas no pasan por casualidad? | Cada corrección tiene una mutación que la deshace (M36–M40) y las 5 se detectan. |
| ¿Por qué quedan dos hallazgos abiertos? | RT-04 es una funcionalidad nueva (editar casos), no una corrección; RT-03 no puede ocurrir hasta que exista. Están registrados con su motivo. |
| ¿Probó módulos ajenos? | No. El HIS central y el expediente quedan fuera; el único contacto es `validar-integracion-central.php`, que no se tocó. |
