# Guía de defensa oral — Semana 12 (segunda evaluación parcial)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** ASII-03 Pacientes · **Presentación:** [semana-12-defensa-pacientes.pptx](presentacion/semana-12-defensa-pacientes.pptx) · **Guion de demostración:** [recorrido-navegable.md](recorrido-navegable.md)

## 1. Resumen en 60 segundos

> «Defiendo las decisiones de componentes, UX, accesibilidad y movilidad del flujo de pacientes. En componentes, las reglas de la ficha tienen un solo dueño, RevisionDeFicha, y la interfaz no decide nada: muestra lo que el servidor manda. En UX, buscar antes de registrar y nada personal en la URL ni en el teléfono. La accesibilidad la medí: 93 ítems de checklist, contraste con script, y axe en cada pantalla del prototipo, sin infracciones. En movilidad, cinco reglas verificadas en 39 vistas y un reintento que no duplica. El prototipo responde igual que el backend real en 37 de 37 situaciones. Y respondo un cambio solicitado: corregir un teléfono sin tener el DPI del paciente. Escribí las pruebas primero, cambié 17 líneas, tres de ellas en RevisionDeFicha, y el contrato pasó a 1.2.0 sin tocar casos de uso ni pantallas de servidor.»

## 2. Tiempos sugeridos (≈ 15 minutos con la demostración)

| Parte | Tiempo | Qué decir sí o sí |
|---|:-:|---|
| Diapositivas 1–5 | 5:00 | Una decisión por tema y su cifra verificable |
| **Demostración** (recorrido A y B) | 6:00 | Roles distintos sobre la misma ficha; el error crítico sin revelar |
| Diapositiva 7: cambio solicitado | 2:00 | Rojo 3 → verde 0; 17 líneas; por qué `null` sigue quitando el documento |
| Diapositiva 8 + preguntas | 2:00 | Las limitaciones, dichas antes de que las pregunten |

## 3. Cómo responder si piden OTRO cambio en vivo

El mismo método del registro del cambio:

1. **¿Qué aspecto toca?** Identidad, demografía, búsqueda, edición, duplicados o confidencialidad.
2. **Criterio de aceptación** antes del código (R-12…, RF-20…).
3. **Impacto por capa:** si es una regla de la ficha, va a `RevisionDeFicha`; si es un dato, al modelo, al puerto y a los dos adaptadores; si es un permiso, a `Capacidades`; si es solo de pantalla, al prototipo.
4. **Prueba en rojo → verde**, más una mutación.
5. **Contrato:** versión menor si es compatible.
6. **Prototipo:** actualizar la API simulada y correr la paridad.

| Cambio posible | Dónde va |
|---|---|
| «Que el clínico pueda corregir el teléfono» | Permiso: `config.php` + una prueba. `PoliticaEdicion` no cambia. |
| «Marcar a un paciente como fallecido» | Es la brecha B-1: el `PUT` recibe `estado` (solo supervisión, con motivo), con una regla nueva en `RevisionDeFicha`. |
| «Buscar por teléfono» | `CriterioBusqueda` + los dos adaptadores + un CON-* nuevo; los protegidos se excluyen como en la búsqueda por nombre. |

## 4. Preguntas probables y respuestas

**¿Por qué ese cambio y no otro?**
Porque salió del uso real del prototipo: al construir la edición en el teléfono vi que había que pedir el DPI completo para corregir un teléfono. Es un cambio pequeño, pero toca identidad, edición y confidencialidad.

**¿No es peligroso que «ausente» signifique «sin cambio»?**
Es más seguro que antes. Antes, olvidar un campo **borraba** el documento. Ahora, para quitarlo hay que decirlo explícitamente (`null`) y dar un motivo. Es el mismo patrón que ya usaba la identidad protegida desde la semana 6.

**¿Por qué no devolver el documento completo a admisión?**
Porque R-04 y RNF-03 dicen que nunca viaja completo en una respuesta. El problema no era que faltara el documento, sino que el `PUT` lo exigía sin necesitarlo.

**¿Cómo sabes que el prototipo no miente?**
Por la paridad: 37 peticiones idénticas al prototipo y al kernel PHP real, con la misma respuesta en todas. Cuando cambié el backend, la paridad me obligó a cambiar también la API simulada.

**¿Qué RNF formalizaste y por qué ahora?**
Accesibilidad (RNF-12), movilidad (RNF-13), conexión limitada (RNF-14) y privacidad en el cliente (RNF-15). Los veníamos aplicando desde la semana 8 sin escribirlos; para trazarlos en la matriz tenían que tener un número y un criterio.

## 5. Plan B si falla la demostración

- Las capturas de cada paso están en `capturas/escritorio` y `capturas/movil`, con la misma numeración que el recorrido.
- La presentación tiene las capturas del error crítico (diapositiva 6) y del cambio (diapositiva 7).
- `evidencia/recorrido.txt` y `evidencia/paridad.txt` son la salida de los scripts.
