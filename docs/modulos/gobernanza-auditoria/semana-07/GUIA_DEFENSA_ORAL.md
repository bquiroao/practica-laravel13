# Guía de defensa oral — Semana 7 (componentes y refactor)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Gobernanza y auditoría · **Documento:** [semana-07-componentes-refactor.md](semana-07-componentes-refactor.md)

## 1. Guion (≈ 8 minutos)

| Min | Qué mostrar | Mensaje |
|---|---|---|
| 0–2 | [Diagrama de componentes](diagramas/semana-07-componentes.png) | Frontend y backend con las mismas 4 capas, unidos por el contrato 1.1.0. El frontend no tiene registrar, editar ni borrar. |
| 2–3 | `php bin/acoplamiento.php` | No elegí el punto a ojo: la consulta tiene 23 ramas y 7 decisiones de alcance, y el detalle repite esas 7. |
| 3–5 | Diagramas [antes](diagramas/semana-07-refactor-antes.png) y [después](diagramas/semana-07-refactor-despues.png) | Seis razones para cambiar → cinco piezas, cada una en su capa. |
| 5–7 | Tabla de §6 y la demo en vivo | De 7 + 7 decisiones de alcance a 0; de 23 a 10 ramas; mismas respuestas. |
| 7–8 | DEF-07-01 y §9 | Lo que el refactor costó (Ce sube), lo que encontró y lo que queda. |

## 2. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Cómo sabe que no cambió el comportamiento? | REF-01..03 se escribieron y pasaron **antes** de tocar el código, y siguen pasando. Las demos dan la misma salida (`diff` vacío) y las 88 pruebas anteriores siguen verdes. |
| ¿Por qué el Ce subió si el objetivo era bajar el acoplamiento? | El acoplamiento oculto (un método con 6 razones para cambiar) pasó a ser explícito, con 5 dependencias pequeñas. Lo que bajó es donde estaba concentrado: decisiones de alcance, ramas y copias. |
| ¿Por qué `Alcance` en el Dominio y `AutorizacionLectura` en la Aplicación? | «Un hospital solo ve lo suyo» es una regla de negocio. El nombre del permiso es de RBAC y llega por un puerto. |
| ¿Por qué no un middleware que ponga el tenant? | El caso de uso puede llamarse desde otro canal; la regla de tenant no puede depender de la entrada HTTP. |
| ¿Por qué no corrigió DEF-07-01? | Corregirlo cambia una respuesta de 200 a 422: eso amplía el comportamiento, y la tarea dice «sin ampliar el módulo». Está registrado con evidencia y fecha de corrección. |
| ¿El frontend existe? | Está diseñado con contratos (props, eventos, acciones, errores). El prototipo es de la semana 11. |

## 3. Demostración en vivo

```bash
cd docs/modulos/gobernanza-auditoria/micro-his-auditoria
php bin/acoplamiento.php                 # ConsultarMovimientos: Alc 0, Ramas 10
php tests/ejecutar.php                   # 94 pruebas, 0 fallan
# Mutación M22: en src/Dominio/Modelo/Alcance.php, incluye() → «return true;»
#   → fallan REF-04, APP-07 y REF-02 (fuga entre hospitales en el detalle). Restaurar.
```
