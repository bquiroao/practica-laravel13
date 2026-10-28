# Guía de defensa oral — Semana 15

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** ASII-03 Pacientes · **Documento:** [semana-15-pruebas-pacientes.md](semana-15-pruebas-pacientes.md) · **Matriz:** [matriz-rf-rnf-pruebas.md](matriz-rf-rnf-pruebas.md) · **Defectos:** [registro-defectos.md](registro-defectos.md)

## 1. Resumen en 60 segundos

> «Diseñé y ejecuté 21 pruebas nuevas de Pacientes, trazadas a los 19 RF y a los RNF del backend. Hice diez unitarias, una por regla: DPI, pasaporte, datos demográficos, cambio de identidad, búsqueda, confidencialidad, duplicados y edición, casi todas con valores límite. Hice cuatro de caja blanca, leyendo el código. Registrar tiene complejidad ciclomática 7 y fuerzo los siete caminos. En la edición pruebo el orden de las reglas con un índice espía, y hago MC/DC de dos condiciones compuestas. Solo con esas cuatro llego a 98 % de cobertura de líneas, medida con phpdbg. Hice siete de caja negra, solo por HTTP: equivalencia, límites, una tabla de 41 celdas de permisos, errores y un escenario de confidencialidad de punta a punta. La suite pasa de 87 a 108 pruebas, todas en verde. Registré seis defectos, cada uno con una prueba que hoy falla y que dirá cuándo quedó corregido. Uno es nuevo: el teléfono cuenta espacios como dígitos. También fui honesto con el plan SQA: el retrabajo no se hizo esta semana, así que lo registré como no conformidad y se escala.»

## 2. Orden sugerido de la exposición

1. **Alcance** (§1): qué es de Pacientes y qué es de RBAC o de otros módulos.
2. **Ejecutar** `php tests/ejecutar.php --solo=UNI` y explicar UNI-04 y UNI-09.
3. **Caja blanca**: mostrar el grafo de CB-01, la tabla de C1–C7 y la cobertura con `phpdbg`.
4. **Caja negra**: abrir CN-05, la tabla de permisos, y explicar por qué hay una celda fuera.
5. **Defectos**: correr `--defectos --solo=DEF` y contar cómo se encontró DEF-06.
6. **Matriz**: elegir un RF y seguirlo hasta sus pruebas.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Las pruebas de defectos **fallan** y van aparte (`--defectos`) | Una prueba que afirma lo correcto y hoy falla es la mejor descripción de un defecto. Va aparte para no poner en rojo la suite (M1); cuando se corrija el defecto, pasa a regresión. |
| Caja negra **solo por HTTP**, con un sistema nuevo por caso | Así no depende del código ni del orden de ejecución, y prueba lo que ve un cliente del contrato. |
| Una celda fuera de la tabla de permisos | Su valor correcto depende de un defecto abierto (DEF-02). Afirmar el valor de hoy sería consagrar el defecto. |
| Índice espía en CB-02 | Algunas ramas no cambian el resultado, solo lo que se consulta (R4, R9). Sin espía no se pueden distinguir. |
| Cobertura con `phpdbg` y no con Xdebug | Viene con PHP: no hay que instalar nada para reproducirla. |
| DEF-04 como «requiere decisión» | El código hace lo que dice RF-13. El problema está en el requisito, que choca con RNF-10, y eso lo decide el negocio, no el programador. |

## 4. Preguntas probables y respuestas

**¿Qué diferencia hay entre caja blanca y caja negra?**
La caja blanca se diseña mirando el código: caminos, ramas y condiciones. La caja negra se diseña mirando la especificación (contrato y reglas) y solo usa la interfaz. Encuentran cosas distintas: la blanca encuentra ramas sin probar; la negra encuentra requisitos mal implementados o ausentes.

**¿Cómo se calcula V(G) de `RegistrarPaciente`?**
Decisiones + 1. Tiene 4 `if` (clave, previa, huella, clave al guardar) y 2 `catch`: 6 + 1 = 7. Siete caminos independientes, y CB-01 fuerza los siete.

**¿Qué es MC/DC y por qué no basta la cobertura de ramas?**
Cada condición debe demostrar que, sola, cambia el resultado. En `(P ∨ ¬Q) ∧ R ∧ (S ∨ T)`, la cobertura de ramas se logra con dos casos (V y F), pero no prueba que la condición Q, el protegido, importe. MC/DC lo exige con un par de casos que solo difieren en Q.

**¿Cómo eligieron los valores límite?**
De las reglas: 100 caracteres en nombres, de 8 a 15 dígitos en el teléfono, 20 resultados, 3 letras, de 8 a 64 caracteres en la clave. Para cada límite se prueba el valor justo dentro y el justo fuera. Con texto multibyte («ñ») se comprueba que se cuentan caracteres y no bytes.

**¿Por qué DEF-06 no lo encontraron las pruebas de límites?**
Porque los límites se probaron con solo dígitos, que es la entrada que uno imagina. La regla mide caracteres, no dígitos. Lo encontró la exploración con espacios, que es justamente el formato del ejemplo `+502 5555 1234`.

**¿No se debían corregir los defectos esta semana?**
El plan SQA lo programaba para S15, pero la tarea de la semana era diseñar y ejecutar las pruebas. No lo escondo: es la no conformidad NC-01, la segunda medición en rojo, y según el plan se escala y se congela la liberación. Lo que se ganó es que ahora cada defecto tiene una prueba que dirá objetivamente cuándo quedó cerrado.

**¿Por qué no se prueba RBAC aquí?**
Porque la tarea prohíbe probar módulos ajenos. Pacientes solo pregunta «¿este actor tiene este permiso?». Lo que se prueba es que pregunte por el permiso correcto y respete la respuesta.

## 5. Recorrido de demostración

Desde `docs/modulos/pacientes/micro-his-pacientes/`:

1. `php tests/ejecutar.php --solo=UNI` → 10/10.
2. `cd ../semana-15 && phpdbg -qrr herramientas/cobertura-caja-blanca.php` → 98.2 %.
3. `php tests/ejecutar.php --solo=CN` → 7/7.
4. `php tests/ejecutar.php --defectos --solo=DEF` → 6 fallan (a propósito).
5. `php ../semana-15/herramientas/exploratorias.php` → la fila del teléfono `«1      2»`.
6. `php tests/ejecutar.php` → 108 · 106 · 0 · 2.
