# Guía de defensa oral — Semana 11 (prototipo navegable)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Gobernanza y auditoría · **Documento:** [semana-11-prototipo-auditoria.md](semana-11-prototipo-auditoria.md)

## 1. Resumen en 60 segundos

El prototipo es una sola aplicación para escritorio y móvil, que se abre con doble clic. Su API simulada **se comporta igual que el Micro-HIS real**: lo comprueba un script que ejecuta las mismas 22 peticiones en los dos lados (0 diferencias), y que detecta un defecto cuando se lo inyecto.

- **Camino feliz:** el auditor encuentra una consulta atribuida al usuario equivocado y sigue su corrección.
- **Error crítico:** el super administrador descubre que a la cadena de Norte le borraron una fila directamente en la base. El prototipo dice «no modifique nada», da el contacto de seguridad y el informe. No ofrece «reparar».

Un recorrido automático verifica 48 cosas en las dos vistas, axe incluido.

## 2. Demostración en vivo (5 minutos)

1. Abrir `prototipo/index.html` → Auditor de Oriente → Más filtros: pacientes → Buscar.
2. «Cargar 50 más» → «Ver» en #55 → «Ver #56» → «‹».
3. En «Más», marcar «La sesión vence…» → Buscar → «Renovar sesión».
4. Cerrar sesión → Super administrador → marcar «Toda la red» con flechas (no pasa nada) → «Buscar en toda la red» → Esc.
5. Integridad → Norte → Verificar → ALTERADA → «Copiar informe».
6. F12 → modo móvil, 360 px: el mismo recorrido.

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Cómo sé que la simulación no miente? | `paridad.cjs` compara la simulada con el kernel real en 22 pasos más la semilla: 0 diferencias. Y con un defecto inyectado, encuentra 3. |
| ¿Por qué el error crítico es ALTERADA y no un 503? | Un 503 se recupera reintentando. Una bitácora manipulada es lo peor que le puede pasar a este módulo, y la interfaz debe guiar sin empeorar nada. |
| ¿Por qué no hay botón de reparar? | Reparar sería modificar la bitácora. El informe es la evidencia para investigar. |
| ¿Por qué «‹» no vuelve a consultar? | Cada consulta deja un registro (R-08). Volver a la lista no es una consulta nueva. |
| ¿axe en 0 significa accesible? | No: axe cubre lo automatizable. Por eso hay pruebas de teclado y foco, y la revisión de las capturas encontró 4 defectos que axe no ve. |
