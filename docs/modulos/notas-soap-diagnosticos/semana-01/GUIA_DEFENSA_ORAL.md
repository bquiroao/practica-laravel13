# Guía de defensa oral — Semana 1

**Estudiante:** Joshua Eduardo García Reyes · **Módulo:** ASII-11 Notas SOAP y diagnósticos · **Documento:** [semana-01-uml-proceso-notas-soap.md](semana-01-uml-proceso-notas-soap.md)

## 1. Resumen en 60 segundos

> «Modelé cómo un médico registra una nota SOAP y le asocia un diagnóstico. Primero el sistema revisa que la solicitud venga del hospital correcto, con un token válido, y que el médico esté asignado al paciente. Después el médico llena Subjetivo, Objetivo, Análisis y Plan y escoge el diagnóstico CIE-10. El sistema valida todo antes de guardar y guarda la nota y el diagnóstico en una sola transacción, para que nunca quede una nota sin diagnóstico. Al final el médico puede firmar, y una nota firmada ya no se modifica: si hay que corregir, se crea una versión nueva. Los tres diagramas usan los mismos códigos, así que cualquier paso se puede seguir de uno a otro.»

## 2. Orden sugerido de la exposición

1. **Narrativa de alcance:** el caso de la Dra. Méndez y el paciente con neumonía.
2. **Casos de uso:** actores, CU-01 a CU-05, y la diferencia entre `include` y `extend`.
3. **Actividad:** las tres fases; señalar D-02, D-04 y E-06.
4. **Secuencia:** seguir el camino feliz de S-01 a S-30 y mostrar dónde se revierte la transacción (S-25).
5. **Matriz de trazabilidad:** escoger una regla, por ejemplo R-06, y ubicarla en los tres diagramas.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| CU-01 **incluye** a CU-02 | En este proceso la nota siempre lleva diagnóstico. `include` es para comportamiento obligatorio. |
| CU-03 **extiende** a CU-01 | Firmar es opcional en ese momento: el médico puede firmar después. `extend` es para comportamiento que se activa bajo una condición. La flecha va de CU-03 hacia CU-01. |
| CU-05 aparte | Lo usan CU-01 y CU-04. Sacarlo evita repetir la verificación de acceso en cada caso. |
| Validar todo antes de guardar y usar una transacción (R-06) | Si la nota se guarda primero y el diagnóstico falla, queda una nota huérfana. Con la transacción se guardan los dos o ninguno. |
| Nota firmada inmutable (R-07) | Es un documento clínico y legal. Cambiarlo borraría lo que el médico afirmó en ese momento. Las correcciones crean otra versión con motivo. |
| El paciente no es actor | Un actor interactúa con el sistema. El paciente no usa pantallas de este módulo; es el sujeto de la nota. |
| Los middleware no son actores | Son parte del sistema. Por eso salen en la secuencia y no en el diagrama de casos de uso. |
| Reglas repetidas en código y en la base de datos | La base HOSPITAL ya tiene triggers que revisan la asignación del médico, el paciente del episodio y la inmutabilidad. Si alguien se salta la aplicación, la base igual rechaza el registro. |

## 4. Preguntas probables y respuestas

**¿Qué significa SOAP?**
Subjetivo (lo que cuenta el paciente), Objetivo (lo que encuentra el médico), Análisis (su interpretación) y Plan (lo que va a hacer). Lo propuso Lawrence Weed en 1968.

**¿Por qué tres diagramas?**
Cada uno responde una pregunta distinta. Casos de uso: qué hace el sistema y para quién. Actividad: cómo fluye el proceso y dónde se decide. Secuencia: quién le habla a quién, en qué orden y qué se responde.

**¿Qué es la trazabilidad entre diagramas?**
Que cada elemento se puede seguir de uno a otro. Ejemplo: R-06 está en A-07 a A-10 y E-06 del diagrama de actividad, y en S-21 a S-28 de la secuencia.

**¿Diferencia entre decisión (D) y excepción (E)?**
Una decisión es una pregunta normal del proceso: ¿el médico está asignado?, ¿falta una sección? Una excepción es el camino de error que sale de una decisión negativa: 403, 422, 500.

**¿Por qué E-04 y E-05 regresan al formulario y E-02 termina el proceso?**
Porque un campo vacío lo arregla el médico en ese momento. Una falta de permiso no se arregla reintentando.

**¿Qué pasa si se cae la base de datos entre el INSERT de la nota y el del diagnóstico?**
La transacción se revierte (S-25) y no queda nada guardado. El médico ve el error y puede intentar de nuevo con lo que ya escribió.

**¿Diferencia entre 401 y 403?**
401: no se sabe quién es el usuario (token ausente o inválido). 403: se sabe quién es, pero no tiene permiso o no está asignado al paciente.

**¿Dónde se guarda esto en la base de datos real?**
En la base HOSPITAL: la nota en `clinical_notes` (con `note_type`, `content`, `status`, `version_no`, `supersedes_id`) y el diagnóstico en `diagnoses` (con `code`, `description`, `type` y `clinical_note_id`).

**¿Qué concepto de orientación a objetos se ve aquí?**
Encapsulamiento: `SoapNote` y `Diagnosis` validan sus propias reglas y no dejan crear un objeto inválido. Y separación de responsabilidades: el controlador no valida reglas de negocio ni escribe SQL.

## 5. Recorrido de demostración

1. Abrir el diagrama de casos de uso y explicar los actores y las relaciones.
2. En el de actividad, seguir el camino feliz hasta A-13.
3. Volver a D-04 y mostrar el regreso al formulario de E-04.
4. En la secuencia, mostrar S-21 a S-28 y explicar la reversión.
5. Terminar con la matriz de reglas.

## 6. Cambios que podrían pedirte en vivo

| Cambio pedido | Qué modificar |
|---|---|
| «Que enfermería también pueda escribir notas de enfermería» | Agregar a Enfermería como actor de un CU nuevo «Registrar nota de enfermería» que incluya CU-05, y ajustar R-02. |
| «Que la nota pueda guardarse sin diagnóstico» | Cambiar el `include` de CU-01 a CU-02 por un `extend`, quitar D-05 del bucle obligatorio y ajustar R-05. |
| «Agregar la corrección de una nota firmada» | CU nuevo «Corregir nota» que extienda a CU-04, con las reglas de R-07 (`version_no`, `supersedes_id`, `correction_reason`). |
| «Mostrar qué pasa si el código CIE-10 no existe en el catálogo» | En D-05, agregar la consulta al catálogo y una excepción nueva con 422. |
| «Agregar un mensaje de auditoría» | En la secuencia, después de S-28, un mensaje al registro de auditoría con autor, nota y fecha. |
