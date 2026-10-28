# Guía de defensa oral — Semana 4

## Resumen en 60 segundos

> «Continué el caso de uso de revisión DIP de la Semana 2. La entrada ahora está organizada con router, controlador y vista MVC. El controlador recibe el contexto confiable y la solicitud, invoca el caso de uso y no contiene SQL ni reglas de validación bioquímica. `LabResultDecisionRepository` es el contrato de Aplicación y lo implementan dos adaptadores: el repositorio en memoria existente y uno PDO con parámetros preparados y transacción. La misma suite demuestra validación, rechazo, conflictos y rollback si falla la auditoría. Revisé las migraciones compartidas: hay almacenes central y hospital, pero no tablas de resultados en el esquema base. Por eso propongo conservar el resultado en el hospital de origen y publicar, si se autoriza, un evento mínimo hacia la bandeja central; no envío valores clínicos al centro. La integración compartida está analizada, no conectada.»

## Orden sugerido

1. Mostrar el flujo y las reglas existentes de Semana 2.
2. Recorrer el diagrama MVC/Repository y ubicar los límites del controlador.
3. Explicar el contrato `LabResultDecisionRepository` y comparar InMemory con PDO.
4. Ejecutar el runner y mostrar rollback de estado y auditoría.
5. Mostrar las migraciones centrales y hospitalarias consideradas.
6. Explicar propiedad del dato, aislamiento hospitalario e integración asíncrona propuesta.
7. Cerrar con límites y tareas previas a una integración productiva.

## Preguntas frecuentes

**¿Por qué el Repository está en Aplicación?**  
El caso de uso define la operación que necesita (`findById` y `decideAtomically`); persistencia implementa ese contrato. Así el caso de uso no depende de PDO ni de SQL.

**¿El controlador valida si el resultado se puede rechazar?**  
No. El controller valida la forma HTTP y convierte los datos a un comando. El caso de uso existente decide si el estado, la versión, autorización y motivo permiten la operación.

**¿Qué demuestra la atomicidad?**  
La prueba fuerza una falla al insertar la auditoría y confirma que la transacción revierte el cambio a `lab_result`; no queda decisión parcial.

**¿Se probó en PostgreSQL o contra el HIS?**  
No. Se probó el adaptador PDO con SQLite en memoria. No existe en esta base una tabla productiva de resultados y la integración central no se implementó.

**¿Por qué no se escribe el resultado en la base central compartida?**  
La decisión clínica pertenece al hospital que generó el resultado. El central existente proporciona metadatos de red y sincronización; una futura proyección central debe tener una finalidad y payload minimizado aprobados.

**¿Se define el rango normal del examen?**  
No. El bioquímico aplica el procedimiento clínico. El ejemplo registra su decisión y no automatiza interpretación bioquímica.
