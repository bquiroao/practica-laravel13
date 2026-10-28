# 1. Flujo y alcance de la defensa

**Mercedes Azucena Lopez Perez — Primera evaluación parcial**

Consulta de orden ficticia y evaluación de capacidad local. Evidencia de contrato no significa soporte NativePHP. No hay pacientes, resultados ni escritura clínica.

---

# 2. Actores y RF/RNF

Evaluador configura experimento, operador lo ejecuta, revisor consulta evidencia. Roles propuestos, no permisos ya implementados.

RF01/02 consulta por gateway; RF03/04 estado reconocido y falla explícita; RF05 capacidad separada; RF06 exportación independiente; RF07 privacidad del mensaje. RNF01 ISP, RNF02 portabilidad, RNF03 privacidad, RNF04 resultados explícitos, RNF05 testabilidad, RNF06 límites honestos (semana 2).

---

# 3. UML y cuatro capas

Mostrar diagrama del README y fuente UML. Presentation CLI → Application Evaluate → Domain/puertos. Persistence implementa Repository y guarda evidencia con PDO preparado y rollback.

Base ejecutable: semana 3. DB temporal, HIS y notificador dobles. El dominio no depende del runtime nativo ni del motor SQL.

---

# 4. ISP, DIP y Repository

Puertos separados: consulta HIS, notificador y evidencia. Exportar/escaneo no son obligaciones de cada adaptador. Repository escribe evaluación y evento como unidad.

Transacción real SQLite; efecto SO posterior no pertenece a esa transacción. Registro técnico no certifica notificación enviada. Cambiar adaptador no modifica reglas del dominio.

---

# 5. API, propiedad y sincronización

Contrato semana 5: GET autorizado por tenant con estado/fecha; 401/403 deniegan, 429 espera, 503 no inventa éxito. Ver OpenAPI local.

HIS es fuente del estado. Refrescar lectura no es sincronizar escrituras clínicas. Snapshot obsoleto no notifica. Adaptador HTTP aún no implementado; CLI usa doble.

---

# 6. Cambio práctico C06-01

Petición del ejercicio: si permiso de notificación está denegado, conservar consulta registrada sin invocar adaptador SO.

ConsentNotifier decora el puerto; consulta permiso en cada ejecución. Domain, Evaluate y PDO no cambian. No pedir permiso en bucle; concedido no garantiza entrega si SO cambia después.

---

# 7. Demostración y evidencia

Ejecutar cambio/verificar.php: 12 aserciones, antes/después, denegado/concedido/retiro de permiso, privacidad, error de persistencia sin efecto y rollback.

Regresión semana 3: 16 aserciones. Semana 2: 14. Abrir salidas en evidencia/. Permiso y notificador dobles; ninguna prueba demuestra runtime nativo.

---

# 8. Viabilidad y respuesta a preguntas

¿Por qué ISP? Permiso y notificación no obligan al gateway a conocer SO ni exportación.

¿Por qué no afirmar soporte móvil? Falta elegir distribución/runtime/versión y medir en destino real.

¿Qué pasa sin red o con resultado desconocido? Sin lectura actual ni notificación del snapshot; UNKNOWN no se reintenta a ciegas. Integración HTTP y auditoría completa de efecto quedan pendientes.