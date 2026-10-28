# Guía de defensa oral — Semana 1

**Estudiante:** Mercedes Azucena Lopez Perez · **Módulo:** Investigación y prototipo NativePHP para extensión móvil/escritorio · **Documento:** [semana-01-uml-investigacion-prototipo-nativephp.md](semana-01-uml-investigacion-prototipo-nativephp.md)

## 1. Resumen en 60 segundos

> «Modelé la evaluación técnica de NativePHP como una extensión local del HIS. El objetivo no es construir el sistema completo, sino elegir un flujo acotado y comprobar si puede ejecutarse con una app nativa usando Laravel como backend. El proceso incluye definir el caso de uso, validar la integración con la API, distinguir entre éxito, error técnico y rechazo de la idea, y registrar una decisión de viabilidad con evidencia. Los tres diagramas mantienen una relación directa entre objetivos, decisión y mensajes entre los participantes.»

## 2. Orden sugerido para exponer

1. Presentar el objetivo y explicar por qué NativePHP se evalúa como extensión nativa y no como reemplazo del sistema principal.
2. Mostrar al operador, el administrador y a Laravel como actores/sistemas del modelo de evaluación.
3. Recorrer la actividad para distinguir flujo válido, fallo técnico y decisión de viabilidad.
4. Revisar la secuencia para localizar las llamadas entre la app nativa y el backend.
5. Elegir una regla de trazabilidad y demostrar que se conserva en los tres diagramas.

## 3. Decisiones clave

| Decisión | Cómo explicarla |
|---|---|
| El prototipo se define sobre un flujo acotado | Evita convertir la evaluación en un proyecto de reemplazo del HIS completo. |
| Laravel sigue siendo la fuente de verdad | La lógica de negocio y los permisos no se duplican en la app nativa. |
| NativePHP se evalúa como cliente local | Sirve para experiencia nativa y acceso a un flujo concreto del sistema, no para reemplazar la arquitectura central. |
| Se separan éxito, error técnico y decisión de viabilidad | Una falla de conexión no es lo mismo que una aplicación inviable. |
| La decisión se documenta con evidencia | El modelo de evaluación exige registrar criterio, resultado y continuidad o rechazo. |
| El flujo no se presenta como una implementación productiva | La documentación es orientada a la viabilidad y la prueba de concepto, no a producción. |

## 4. Preguntas probables

**¿Por qué no se modela una solución completa del HIS?**

Porque esta entrega es de investigación y prototipo; la consigna pide evaluar un flujo acotado y su viabilidad técnica, no construir una app clínica completa.

**¿Qué diferencia hay entre una app nativa y un uso web convencional?**

La diferencia es el entorno de ejecución y la forma de interaction con el sistema, especialmente para casos de acceso local, offline parcial o experiencia más cercana a una aplicación de escritorio/móvil.

**¿Qué ocurre si falla la autenticación o la conexión?**

Se registra una condición técnica y el flujo no puede considerarse exitoso. Eso se modela como excepción, no como decisión de negocio.

**¿Por qué el backend sigue siendo Laravel?**

Porque el backend central conserva reglas de negocio, seguridad y persistencia; la app nativa solo entrega la experiencia local y consume la lógica existente.

**¿Cómo se comprueba la trazabilidad?**

Cada regla de decisión se identifica con un código y se relaciona con casos de uso, la actividad y la secuencia. La matriz de la sección 8 lo hace explícito.

## 5. Recorrido de demostración

1. Abrir [semana-01-uml-investigacion-prototipo-nativephp.md](semana-01-uml-investigacion-prototipo-nativephp.md) y leer la narrativa y las reglas.
2. Mostrar [el diagrama de casos de uso](diagramas/semana-01-casos-de-uso.puml) para explicar qué busca resolver la evaluación.
3. En [el diagrama de actividad](diagramas/semana-01-actividad.puml), seguir el camino de ejecución y luego el de decisión de viabilidad.
4. En [el diagrama de secuencia](diagramas/semana-01-secuencia.puml), mostrar la interacción entre operador, app nativa y Laravel.
5. Cerrar con una regla de la matriz y explicar cómo se conserva en cada vista.

## 6. Cómo modificar los diagramas

| Cambio solicitado | Archivos que deben mantenerse sincronizados |
|---|---|
| Añadir un flujo hospitalario concreto | Alcance, caso CU-01, actividad, secuencia y matriz. |
| Cambiar la decisión de viabilidad | Caso CU-03, decisiones del diagrama de actividad y la secuencia asociada. |
| Incluir un requisito de seguridad | Reglas R-02, R-04 y validaciones del diagrama de secuencia. |
| Redefinir la app como otra plataforma | Casos de uso, actores y mensajes de interacción entre capas. |

Para regenerar las imágenes desde la raíz del repositorio:

```bash
java -jar plantuml.jar -charset UTF-8 -tpng docs/modulos/investigacion-prototipo-nativephp/semana-01/diagramas/*.puml
```

## 7. Referencia técnica del proyecto

La arquitectura principal del HIS ya está orientada a Laravel 12, Vue y bases federadas. NativePHP puede evaluarse como una extensión del mismo ecosistema, pero debe demostrar que agrega valor sin duplicar la lógica crítica ni complicar la mantenibilidad. Este módulo sirve como análisis de viabilidad técnica, no como sustitución del diseño base del sistema.
