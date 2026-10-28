# Guía de defensa oral — Semana 15 (pruebas)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Prescripciones electrónicas con validación de alergias · **Documento:** [semana-15-pruebas-prescripciones.md](semana-15-pruebas-prescripciones.md)

## 1. Resumen en 60 segundos

Agregué 18 pruebas trazadas a los requisitos:
- **8 unitarias**, una regla del dominio por prueba.
- **3 de caja blanca:** caminos básicos de la emisión (V(G) = 14), MC/DC de sus tres condiciones compuestas y las ramas de la anulación. Juntas recorren el **100 %** de las líneas de las cuatro clases del flujo.
- **7 de caja negra**, diseñadas solo desde el contrato: equivalencia, límites, una tabla de decisión de permisos, tenant, errores e idempotencia.

**Dos resultados:**
- **Corregí DEF-02** con la prueba primero: otro médico con la misma clave recibía «ya emitida» una prescripción ajena. CB-01 falló justo en ese camino y ahora pasa; lo vigila la mutación M36.
- **La caja negra encontró un defecto nuevo, DEF-08:** la dosis máxima se mide por ítem. Dos ítems de Paracetamol suman 8000 mg/día y pasan sin hallazgos.

Suite: 129/129 con PostgreSQL y 36/36 mutaciones. Los 7 defectos abiertos tienen su prueba, que falla.

## 2. Orden sugerido

1. La matriz RF/RNF → prueba (`matriz-rf-rnf-pruebas.md`).
2. Unitarias: correr `--solo=UNI`; mostrar UNI-07 (orden de la política).
3. Caja blanca: el grafo, los 14 caminos y la cobertura con phpdbg (96,8 % → 100 %).
4. DEF-02: la evidencia antes/después y M36.
5. Caja negra: CN-04 (tabla de decisión) y CN-02 → DEF-08.
6. `--defectos`: los 7 que fallan y por qué no se corrigieron todos.

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Cómo calculó V(G) = 14? | 13 decisiones en el flujo de emisión, contando por separado las dos condiciones del reintento (huella y prescriptor), más 1. El grafo lo muestra, y hay un camino por cada una. |
| ¿Por qué no corrigió DEF-08 si es grave? | Porque es una regla clínica: hay que decidir con farmacia si se suma por medicamento o por principio activo. La prueba ya describe lo correcto y falla. |
| ¿Por qué DEF-02 sí? | Es un defecto del propio módulo, con una solución sin ambigüedad (mismo prescriptor) y sin efectos clínicos. |
| ¿Las pruebas de caja negra miraron el código? | No: salen del contrato y de los requisitos. Por eso encontraron DEF-08, que el código, leído ítem por ítem, no sugiere. |
| ¿100 % de líneas significa sin defectos? | No: DEF-08 está en una clase con 100 % de cobertura por las unitarias. La cobertura dice qué se ejecutó, no qué se preguntó. |

## 4. Cambios que podrían pedir en vivo

| Pedido | Respuesta |
|---|---|
| «Agregar una clase de equivalencia a CN-01» | Una línea en la lista de inválidas; corre por HTTP. |
| «Mostrar que M36 detecta el defecto» | `php tests/mutaciones.php` → M36 falla CB-01 y CB-02 ([evidencia](evidencia/mutaciones.txt)). |
| «Corregir DEF-04 ahora» | Aceptar en el validador un decimal sin parte fraccionaria; DEF-04 pasa y se mueve a UNI-03. Se puede hacer en vivo con su mutación. |
