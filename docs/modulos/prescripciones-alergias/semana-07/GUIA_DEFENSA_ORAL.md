# Guía de defensa oral — Semana 7

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Prescripciones electrónicas con validación de alergias · **Documento:** [semana-07-componentes-refactor.md](semana-07-componentes-refactor.md)

## 1. Resumen en 60 segundos

> «Diseñé los componentes del módulo:
> - el backend, que ya está implementado en cuatro capas;
> - un frontend en Vue con la convención del HIS, con siete componentes de interfaz, un store, una ayuda de validación y un cliente de la API.
>
> Para elegir qué refactorizar, medí el acoplamiento de cada clase. El punto más acoplado era el caso de uso de emisión: además de orquestar, tomaba nueve decisiones clínicas que son del dominio, como cuánto debe medir una justificación o qué hacer si las alergias no están evaluadas. La verificación previa repetía una de ellas.
>
> Las moví a una política del dominio que se prueba con una tabla. Las decisiones clínicas fuera del dominio bajaron de diez a cero.
>
> Para probar que no cambié el comportamiento, grabé antes del refactor 32 escenarios con sus respuestas exactas, y después son idénticos.»

## 2. Orden sugerido de la exposición

1. **Diagrama de componentes:** el frontend y el backend unidos por el contrato; lo que el frontend no tiene a propósito.
2. **Medición:** la tabla de §3 y por qué no se eligió `EvaluacionClinica`.
3. **Antes:** el extracto de código y los dos lugares que interpretan «no evaluado».
4. **Después:** `PoliticaEmision` y la `Closure` del override.
5. **Prueba de que no se amplió:** caracterización, demo y contrato.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Elegir por medición y no por intuición | La clase con más preocupaciones (`EvaluacionClinica`) no era el problema: las reúne a propósito y no decide nada |
| Mover la decisión al Dominio | Son reglas clínicas (R-04, R-05, R-06): lo primero que pedirá cambiar farmacia |
| Override como `Closure` | El Dominio no conoce RBAC; un booleano obligaría a preguntar siempre. La `Closure` conserva el «solo si hace falta» (DOM-09, M29) |
| `Prescripcion::emitir(decisión)` | Construir desde la decisión hace imposible guardar una prescripción que el Dominio no permitió |
| Caracterización grabada antes | Es la única forma honesta de decir «sin ampliar el módulo» |
| Reportar que el Ce subió | El objetivo era la dirección de las dependencias, no su número |

## 4. Preguntas probables y respuestas

**¿Qué es una prueba de caracterización?**
Una prueba que no dice qué **debería** pasar, sino qué **pasa hoy**. Se graba antes del cambio, y después del cambio todo tiene que dar igual. Protege un refactor, no una regla.

**¿Por qué el Ce subió si el refactor era para desacoplar?**
Porque ahora el caso de uso depende de dos clases pequeñas del Dominio que deciden por él, en lugar de interpretar por su cuenta cuatro conceptos de otras. El acoplamiento que importa, el de decidir reglas ajenas, bajó de 9 a 0.

**¿El frontend también valida la justificación? ¿No es duplicar la regla?**
El frontend la usa como **ayuda** (deshabilitar el botón), pero el servidor siempre la vuelve a aplicar. Si se desincronizan, gana el servidor y el usuario ve un 409 explicado.

**¿Por qué no se mostró en la previa que el médico no tiene override?**
Porque cambiaría lo que responde la API, y la tarea pedía refactorizar sin ampliar el módulo. Queda anotado para el diseño UX de la semana 8.

## 5. Recorrido de demostración

1. `php bin/acoplamiento.php` y comparar con `evidencia/acoplamiento-1-antes.txt`.
2. `php tests/ejecutar.php --solo=CAR,DOM`.
3. Abrir `refactor/antes/EmitirPrescripcion.php` junto al actual.
