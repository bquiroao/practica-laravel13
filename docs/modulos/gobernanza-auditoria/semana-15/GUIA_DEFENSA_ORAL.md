# Guía de defensa oral — Semana 15

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Gobernanza y auditoría · **Documento:** [semana-15-pruebas-auditoria.md](semana-15-pruebas-auditoria.md)

## 1. Resumen en 60 segundos

Escribí 18 pruebas nuevas, trazadas a los requisitos, y las ejecuté:
- **8 unitarias:** una por tema de la tarea, con sus casos límite;
- **3 de caja blanca:** caminos básicos y MC/DC, con el 100 % de las líneas de las clases objetivo;
- **7 de caja negra:** diseñadas solo desde el contrato.

La suite queda en 116/116 con PostgreSQL y 27/27 mutaciones. Un defecto de la semana 7 se cerró con su prueba en rojo primero. La caja negra encontró **un defecto nuevo**: la cabecera del tenant no limita la verificación. Los defectos abiertos tienen pruebas que fallan a propósito, separadas de la suite.

## 2. Orden sugerido de la exposición

1. La matriz RF/RNF → prueba (qué requisito cubre cada prueba).
2. Unitarias: UNI-07 (límites del día en hora local) y UNI-08 (rojo → verde de DEF-07-01).
3. Caja blanca: el grafo de flujo, V(G) = 12, los 11 caminos y el MC/DC de CB-02.
4. Caja negra: la tabla de decisión de CN-04 y el defecto nuevo.
5. Defectos: el registro y `--defectos`.
6. Las cifras: 116/116, 27/27 y 100 %.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Unitarias por tema (inmutable, usuario, módulo, acción, tenant, fechas) | La tarea los nombra; cada uno tiene su regla y su prueba aislada |
| Caminos básicos **y** MC/DC | Los caminos recorren el flujo; MC/DC demuestra que cada condición de un `&&` o un `\|\|` importa por sí sola |
| Caja negra desde el contrato, sin mirar el código | Así encontró DEF-04: el código ignora una cabecera que el contrato define |
| Defectos abiertos como pruebas que fallan, fuera de la suite | La suite principal sigue siendo una señal limpia (verde = sin regresiones), y cada defecto tiene su reproducción ejecutable |
| No corregir DEF-01 ni DEF-02 esta semana | DEF-01 depende del autenticador de otro módulo; DEF-02 cambia lo que aceptan los emisores y hay que acordarlo con ellos |

## 4. Preguntas probables y respuestas

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué V(G) = 12 y solo 11 caminos? | El camino «ninguna regla configurada» no es factible: el contenedor siempre arma las 9 reglas. |
| ¿Qué es MC/DC en CB-02? | Para «A y B», mostrar un par de casos donde solo cambia A y cambia la salida, y otro donde solo cambia B. |
| ¿100 % de líneas es 100 % de ramas? | No. phpdbg mide líneas; las ramas se cubrieron por diseño (caminos y MC/DC). |
| ¿Por qué el defecto nuevo es menor? | Solo el super administrador tiene el permiso, que ya es de red: no hay escalada. Es una inconsistencia del contrato. |
| ¿No es trampa sacar los defectos de la suite? | No: cada defecto está registrado, tiene su prueba ejecutable y se ve con `--defectos`. La suite principal mide regresiones. |
| ¿Probó RBAC? | No, a propósito: la tarea pide no probar módulos ajenos. RBAC se simula con la política estática. |

## 5. Recorrido de demostración

```bash
cd docs/modulos/gobernanza-auditoria/micro-his-auditoria
php tests/ejecutar.php --solo=UNI,CB,CN            # 18/18
php tests/ejecutar.php --defectos --solo=DEF       # 4 fallan: los defectos abiertos
cd ../semana-15 && phpdbg -qrr herramientas/cobertura-caja-blanca.php
```
