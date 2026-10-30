# Guía de defensa oral — Semana 4 (capas, MVC y Repository)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA, pruebas E2E, CI y guía de despliegue final · **Documento:** [semana-04-capas-mvc-repositorio.md](semana-04-capas-mvc-repositorio.md)

## 1. Resumen en 60 segundos

Reorganicé la entrada en MVC:
- los formularios revisan solo la forma (400);
- los controladores llaman a un caso de uso y a una vista, sin SQL ni reglas;
- la vista es el único lugar con códigos HTTP.

Las 39 pruebas anteriores pasaron sin tocarlas.

El Repository son cuatro interfaces del dominio, con adaptadores PDO y en memoria que pasan la misma suite de contrato. Esa suite encontró que el adaptador en memoria aceptaba dos cierres de la misma ejecución.

Para el repositorio compartido elegí publicar hechos:
- cada evaluación del gate se copia en un esquema compartido, en la misma transacción;
- los otros módulos la leen con un rol de solo lectura que ve únicamente su hospital.

Resultado: 61 pruebas y 21 mutaciones. Además, una prueba de arquitectura era vacía y la descubrí sembrando una violación.

## 2. Demostración sugerida (3 minutos)

1. Abrir `VersionesControlador`: formulario → caso de uso → vista, sin nada más.
2. `php tests/ejecutar.php --solo=CON`: la misma suite, en memoria y PDO.
3. Mostrar INT-09: con el rol lector, `hosp-norte` ve 1 fila, `hosp-sur` 0, y el esquema `e2e` da *permission denied*.

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué el formulario deja pasar un digest mal formado? | Porque es una regla del dominio (422), no de forma. Si el formulario la repitiera, habría dos lugares que mantener. |
| ¿Para qué una suite de contrato? | Para que «en memoria pasa» signifique «en PostgreSQL también». Encontró una diferencia real (CON-05). |
| ¿Por qué no dejar que los otros módulos lean `e2e.*`? | Los acopla al esquema interno, ven datos que no necesitan y nadie controla el tenant. |
| ¿Por qué un trigger y no la aplicación? | Para que la publicación quede en la misma transacción que la evaluación: no hay una sin la otra. |
| ¿Qué es la seguridad por filas? | Una política de PostgreSQL: el rol lector solo ve las filas cuyo tenant coincide con el de su sesión. |
| ¿Cómo supo que ARQ-06 era vacía? | Sembré a mano un `use` del Dominio en un controlador y la prueba siguió en verde. Ahora tiene una guarda: si no lee ningún import, falla. |
