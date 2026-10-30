# Guía de defensa oral — Semana 10 (movilidad, 320–430 px)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA, pruebas E2E, CI y guía de despliegue final

## 1. Resumen en 60 segundos

Llevé al teléfono las cuatro pantallas que se usan lejos del escritorio:
- versiones;
- ejecución en curso;
- quality gate;
- despliegue.

**Cómo está hecho:** son HTML y CSS mobile-first, con la base en 320 px y cuatro breakpoints que agregan.

**Cómo se verifica:** las reglas de breakpoint no quedaron en el papel. Un script abre 43 vistas, toma la captura y mide 12 reglas, por ejemplo:
- objetivos de 44 px;
- la versión siempre arriba;
- el digest completo;
- el error a la vista.

Una contraprueba con defectos sembrados muestra que cada regla detecta algo.

**Lo más valioso fue lo que el script no veía.** Al revisar las capturas encontré que el bloque distinto del digest quedaba tapado por la barra de acción, y que a 1024 px el contenido salía duplicado. Las dos cosas pasaron a ser reglas medidas.

## 2. Orden sugerido (8 minutos)

| Paso | Qué mostrar | Minutos |
|---|---|---|
| 1 | Por qué en el teléfono: los dos roles y su situación | 1:00 |
| 2 | Breakpoints y jerarquía de contenido | 1:30 |
| 3 | Escenario 1 (lámina): digest distinto → smoke falla → REVERTIDO | 2:00 |
| 4 | Escenario 2 (lámina): sin señal → aviso de lo nuevo → gate | 1:30 |
| 5 | Correr el verificador; mostrar la contraprueba y R-F1 | 1:30 |
| 6 | Limitaciones: estático y emulado; 43 % de lo fijo a 320 px | 0:30 |

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué 44 px si WCAG pide 24? | Porque se usa de pie, con una mano, junto a un rack. 44 es la recomendación de las plataformas móviles; 24 es el mínimo legal. |
| ¿Por qué no registrar escenarios en el teléfono? | Son textos largos y listas de requisitos: tareas de escritorio. En el teléfono quedan en solo lectura. |
| ¿Cómo sabés que tus reglas sirven? | Sembré un defecto por regla en una copia y el script marcó 30 vistas en falla, al menos una por regla. |
| ¿Qué encontraste que el script no medía? | El bloque distinto del digest tapado por la barra a 320 px, y las tarjetas duplicadas con la tabla a 1024 px. Ahora son R-F1 y R-X1. |
| ¿Por qué el botón no se deshabilita si el digest no coincide? | Un botón deshabilitado no recibe foco ni explica nada. Activo, lleva el foco al campo y muestra qué bloque difiere (semana 9 B-03). |
| ¿El prototipo habla con la API? | No, todavía. Es estático, con estados por URL. La navegación completa y el escritorio son la semana 11. |
