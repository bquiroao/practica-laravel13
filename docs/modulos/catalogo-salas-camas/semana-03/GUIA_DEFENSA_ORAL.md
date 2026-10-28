# Guía de defensa oral — Semana 3

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Catálogo de salas, wards y camas · **Documento:** [semana-03-arquitectura-micro-his.md](semana-03-arquitectura-micro-his.md)

## 1. Resumen en 60 segundos

> «El Micro-HIS tiene cuatro capas. El controlador recibe la petición y traduce los errores a HTTP; el servicio ordena los casos de uso; el dominio decide qué transiciones de estado son válidas; el repositorio PDO guarda con sentencias preparadas y deja el historial. Las dependencias apuntan hacia el dominio: el servicio define un puerto y la persistencia lo implementa. Las pruebas cubren el camino feliz, las reglas, la base caída y la regla de dependencias.»

## 2. Preguntas probables

**¿Qué diferencia hay entre un 409 E5 y un 503 E8?** E5 es una regla de negocio (la cama está ocupada); E8 es un fallo técnico de la base. `RepositorioPdo` convierte la `PDOException` en `ErrorPersistencia` para no mezclarlos ni mostrar detalles de SQL.

**¿Por qué el puerto está en Aplicación y no en Persistencia?** Porque lo define quien lo necesita: así Persistencia depende de Aplicación y no al revés (DIP).

**¿Cómo se evita que dos usuarios pisen el cambio del otro?** El `UPDATE` exige que el estado siga siendo el leído; si ya cambió, afecta 0 filas y se responde 409 E7. La evidencia se corre con `php …/micro-his-catalogo-camas/pruebas/pruebas.php` (16/16, exit 0).
