# Guía de defensa oral — Semana 15

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** RBAC · **Documento:** [semana-15-pruebas-rbac.md](semana-15-pruebas-rbac.md)

## 1. Resumen en 60 segundos

> «Diseñé y ejecuté 19 pruebas nuevas, todas trazadas a RF y RNF.
>
> Hay 9 unitarias: cada regla de dominio aislada, con valores límite. Por ejemplo, una membresía que vence justo "ahora" ya no vale.
>
> Hay 4 de caja blanca, diseñadas leyendo el código: los 5 caminos básicos de la autorización, el MC/DC de la vigencia y el orden de decisiones del kernel. Medí con phpdbg que cubren el 98,7 % de las líneas de esas clases.
>
> Hay 6 de caja negra, diseñadas solo desde el contrato: particiones de equivalencia, límites, una matriz de 35 celdas de permisos y los errores.
>
> Encontré un defecto nuevo: el sistema acepta fechas imposibles como el 30 de febrero, y con "2026-00-10" el rol empieza un mes antes de lo pedido.
>
> En total hay 5 defectos registrados. Cada uno tiene una prueba que hoy falla y que pasará cuando se corrija.»

## 2. Orden sugerido de la exposición

1. **Alcance (§1):** qué se prueba y por qué no el Laravel principal (todavía sin RBAC; probarlo sería probar módulos ajenos).
2. **Unitarias:** UNI-02 (límites de vigencia) y UNI-07 (cerró el hueco M2 de la S14).
3. **Caja blanca:** el grafo, V(G) = 5 y los caminos C1–C5 (C5 revela DEF-04). Luego la cobertura de 98.7 %.
4. **Caja negra:** CN-04 (la matriz de 35 celdas) y CN-02 (límites 9/10/500/501).
5. **Defectos:** correr `--defectos` en vivo y explicar DEF-01.
6. **Matriz RF/RNF y limitaciones.**

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Probar el Micro-HIS y no el Laravel principal | El principal aún no tiene código de RBAC; lo que tiene (JWT, tenancy) es de otros módulos, y la tarea pide no probar módulos ajenos. |
| Caja negra solo por HTTP y desde el contrato | Si la prueba mira el código, deja de ser caja negra. Por eso CN-03 se rediseñó cuando dependía de un texto que el contrato no fija. |
| MC/DC en `estaVigenteEn` | Es la condición que decide si una membresía cuenta. MC/DC demuestra que cada subcondición, por sí sola, cambia el resultado. |
| Defectos como pruebas que fallan, en un grupo aparte | Reproducibles en cualquier momento, sin poner en rojo la suite ni bloquear los PR. Al corregir, la prueba pasa a regresión. |
| Medir la cobertura con phpdbg | No requiere instalar nada: viene con PHP. Da evidencia objetiva de la caja blanca. |

## 4. Preguntas probables y respuestas

**¿Cuál es la diferencia entre caja blanca y caja negra?**
- **Caja blanca:** se diseña mirando el código (caminos, ramas, condiciones) y se mide qué partes se ejecutaron.
- **Caja negra:** se diseña desde la especificación (entradas y salidas esperadas), sin saber cómo está hecho.

Encontraron cosas distintas: la caja blanca reveló el camino C5 (DEF-04); la caja negra y la exploración revelaron las fechas imposibles (DEF-01).

**¿Qué es V(G) y por qué 5?**
Es la complejidad ciclomática: el número de caminos independientes. `AutorizarOperacion` tiene 4 decisiones (lectura falla, motivo nulo, principal falla, respaldo falla), así que V(G) = 4 + 1 = 5. Una prueba por camino garantiza que cada decisión se tomó en ambos sentidos.

**¿Qué es MC/DC?**
Cobertura de condición/decisión modificada. Para cada condición hay un par de casos en el que **solo** esa condición cambia y el resultado también cambia. Demuestra que ninguna condición sobra ni está mal conectada.

**¿Por qué 9/10/500/501 en el motivo?**
Valores límite: los extremos del rango válido (10 y 500) y sus vecinos inválidos (9 y 501). La mayoría de los errores de rango están justo en el borde.

**¿Por qué DEF-01 es grave?**
Porque la vigencia decide el acceso. «2026-00-10» se guarda como 10 de diciembre de 2025: el rol queda vigente **antes** de lo autorizado y nadie se entera, porque la respuesta es 201.

**¿Por qué no corregiste los defectos?**
La tarea pide registrarlos. Además, tocan el código congelado para la revisión de la S13 y son parte de su retrabajo. Cada uno ya tiene la prueba que demostrará la corrección.

**¿Qué falta?**
Las pruebas con PostgreSQL (CON-PDO, INT-00..05) y la medición de rendimiento con base de datos. Siguen omitidas porque no hay una base de prueba configurada.

## 5. Demostración en vivo

```bash
cd docs/modulos/rbac/micro-his-rbac
php tests/ejecutar.php --solo=UNI,CB,CN        # 19 pasan
php tests/ejecutar.php --defectos --solo=DEF   # 5 fallan (defectos abiertos)
php tests/ejecutar.php                          # 99 · 97 · 0 · 2

cd ../semana-15
phpdbg -qrr herramientas/cobertura-caja-blanca.php   # 98.7 %
```

## 6. Cambios que podrían pedirte en vivo

| Pedido | Cómo hacerlo |
|---|---|
| «Agrega un caso límite» | Una línea en CN-02 o CN-03, por ejemplo un motivo de 499 caracteres → 200. |
| «Corrige DEF-01» | En `FormularioAsignacion::fecha()`, comparar `format('Y-m-d')` con el texto recibido. DEF-01 pasa: se mueve a `tests/Presentacion/` como regresión. |
| «Muestra que la caja blanca detecta un cambio» | Borrar el `catch` del respaldo en `AutorizarOperacion`: CB-01 (C4) falla. |
| «¿Qué prueba cubre RF-12?» | La matriz: CN-06 y REV-02. |
