# Guía de defensa oral — Semana 12 (segunda evaluación parcial)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Gobernanza y auditoría · **Presentación:** [semana-12-defensa-auditoria.pptx](presentacion/semana-12-defensa-auditoria.pptx)

## 1. Resumen en 60 segundos

De la semana 7 a la 12 diseñé los componentes, la experiencia de usuario, la accesibilidad y la versión móvil de la bitácora, y lo dejé todo **ejecutable**:
- un prototipo que responde igual que el backend real (26 pasos, 0 diferencias);
- un recorrido que comprueba teclado, foco y axe en escritorio y en móvil (58, 0 fallas).

El cambio solicitado, «¿quién vio la ficha de este paciente?», se resolvió con pruebas primero y una extensión OCP que no tocó el caso de uso.

## 2. Tiempos sugeridos (≈ 15 minutos con la demostración)

| Min | Diapositiva / acción |
|---|---|
| 0–1 | 1 · Portada y cifras |
| 1–3 | 2 · Componentes y refactor medido |
| 3–5 | 3 · UX por rol y los hallazgos de mi propio diseño |
| 5–7 | 4 · Movilidad y reintentos medidos |
| 7–12 | 5–6 · Prototipo en vivo ([recorrido](recorrido-navegable.md)) y cambio solicitado |
| 12–13 | 7 · Matriz y riesgos |
| 13–15 | Preguntas |

## 3. Cómo responder si piden OTRO cambio en vivo

| Pedido | Dónde se hace | Cómo se prueba |
|---|---|---|
| «Buscar por recurso parcial (prefijo)» | Otro filtro, por prefijo, con su índice; antes hay que decidir si expone más pacientes de lo necesario | Una prueba REC nueva más una fila de paridad |
| «Exportar el resultado» | Amplía el módulo: la exportación es otra lectura que debe auditarse | Meta-auditoría con `formato`; prueba como REF-02 |
| «Que el auditor vea dos hospitales» | Permisos en RBAC; `Alcance` admitiría un conjunto | REF-04 ampliada |
| «Más de 50 por página» | Constante `PAGINA`; afecta el rendimiento y el cursor | REF-03 |

## 4. Preguntas probables y respuestas

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué el botón «¿Quién más accedió?» no busca directamente? | Porque cada consulta queda registrada: la persona debe ver qué va a buscar y en qué período antes de dejar ese rastro. |
| ¿Por qué coincidencia exacta? | Una parcial trae fichas de otros pacientes: más datos de los necesarios. |
| ¿Por qué cambió una prueba de la semana 6? | Exigía el texto literal de la versión 1.1.0. Lo que protege es que la corrección esté documentada desde 1.1.0, y subir a 1.2.0 no rompe eso. |
| ¿Cómo sabe que el prototipo no miente? | Paridad contra el kernel real: 0 diferencias. Y con un defecto inyectado, la herramienta encuentra 3. |
| ¿Qué riesgo le preocupa más? | Que el recurso sea texto libre del emisor: si un módulo lo escribe distinto, la búsqueda no lo encuentra. Lo llevo a la revisión técnica. |

## 5. Plan B si falla la demostración

- Mostrar las capturas de `capturas/`: son la salida real del recorrido.
- Ejecutar `php micro-his-auditoria/tests/ejecutar.php`: no depende del navegador.
