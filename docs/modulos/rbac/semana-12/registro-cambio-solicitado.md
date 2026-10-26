# Registro del cambio solicitado — «La bitácora tiene denegaciones que nadie intentó» (B-6)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** RBAC · **Semana:** 12 (segunda evaluación parcial)

## 1. Solicitud

> «Al revisar la bitácora, el auditor encuentra muchos `ACCESO_DENEGADO` de personas que dicen no haber intentado nada. Haga que la interfaz deje de generar ese ruido **sin perder la trazabilidad de los intentos reales**.»

El problema se encontró y midió en la semana 11 (brecha B-6). Para mostrar qué secciones y botones le corresponden a cada persona, la interfaz pregunta al PDP permiso por permiso, y el PDP audita cada «no». Medido en el prototipo de la semana 11, solo navegando, sin intentar nada prohibido:

| Persona | Denegaciones «fantasma» |
|---|---:|
| Carlos | 1 |
| Luis | 3 |
| Ana | 3 |
| Marta | 2 |
| **Total** | **9** |

## 2. Requisitos nuevos

| ID | Requisito | Criterio de aceptación |
|---|---|---|
| **RF-15** | La interfaz puede saber qué permisos tiene la sesión, en el hospital activo, con una sola consulta. | `POST /api/v1/autorizaciones/permisos-efectivos` con `{"permisos": [...]}` responde `{"permisos": {"x.y": true\|false}}` (PEF-01, PEF-07). |
| **RNF-14** | La bitácora registra **intentos** de acceso, no las consultas que la interfaz hace para decidir qué mostrar. | 0 eventos por consultar (PEF-02); los intentos siguen auditados (PEF-10); 0 denegaciones «fantasma» en el prototipo. |

**Restricciones que el cambio no puede romper:**

| Regla | Qué exige | Prueba |
|---|---|---|
| R-06 | Toda denegación de un intento real queda auditada | PEF-10 y recorrido EC-1/EC-4 |
| R-08 | La respuesta no explica por qué | El mapa no trae motivos |
| R-01 | Aislamiento por hospital | PEF-05 |
| R-05 | Fail-closed | PEF-06 |
| RF-05 / RF-07 | La interfaz no puede mostrar algo distinto de lo que el servidor decidiría | PEF-03 |

## 3. Análisis de impacto (antes de programar)

| Opción | Ventaja | Por qué no / por qué sí |
|---|---|---|
| A. Dejar de auditar los DENEGADO del PDP | Simple | ✗ Rompe R-06: otros módulos del HIS usan el PDP para intentos reales. |
| B. Agregar `permisosDe(rol)` al puerto `CatalogoPermisos` y calcular en la UI | Una consulta | ✗ Toca el puerto y los **dos** adaptadores (PDO e InMemory) y su suite de contrato. Además duplicaría la lógica de autorización en otro lugar, con riesgo de divergir del PDP. |
| C. Parámetro `"proposito": "ui"` en `/decisiones` que no audite | Sin ruta nueva | ✗ Mezcla dos responsabilidades en un endpoint. Cualquier cliente podría desactivar la auditoría enviando un campo. |
| **D. Endpoint nuevo de permisos efectivos que reutiliza la `PoliticaAutorizacion`, sin auditar** | Misma política que el PDP; no toca puertos ni adaptadores; extensión, no modificación (OCP) | ✓ **Elegida** |

**Piezas afectadas:**

| Pieza | Cambio | Capa |
|---|---|---|
| `ConsultarPermisosEfectivos` | **Nueva** | Aplicación |
| `FormularioPermisosEfectivos`, `PermisosEfectivosVista` | **Nuevas** | Presentación |
| `AutorizacionControlador` | + método `permisosEfectivos()` | Presentación |
| `Rutas` | + 1 ruta, sin permiso propio | Presentación |
| `bootstrap/contenedor.php` | + 1 caso de uso en la composición | Arranque |
| `api/openapi.yaml` | v1.1.0 → **v1.2.0** (compatible: solo agrega) | Contrato |
| Dominio, puertos, adaptadores PDO/InMemory | **sin cambios** | — |
| Prototipo: `api-simulada.js`, `app.js` | permisos efectivos para mostrar; `intentar()` para los intentos | UI |

**La regla de diseño que sale del análisis:**

| Tipo de acción | Ejemplo | Qué consulta la UI |
|---|---|---|
| **Pasiva** (decidir qué mostrar) | Menú, tarjetas de Inicio, botones de una pantalla | Permisos efectivos: sin evento |
| **Activa** (intentar algo) | Entrar por URL a una ruta prohibida, ejecutar una operación | PDP o la ruta protegida del servidor: con evento y referencia |

## 4. Implementación

**Backend (Micro-HIS):**

```php
// src/Aplicacion/CasosDeUso/ConsultarPermisosEfectivos.php (extracto)
foreach ($permisos as $permiso) {
    $solicitud = new SolicitudAutorizacion($usuarioId, $tenantToken, $tenantSolicitud, $permiso, self::RECURSO, '', $ahora);
    $resultado[$permiso] = $this->politica->evaluar(new ContextoAutorizacion($solicitud, $membresias)) === null;
}
// Si no se pueden leer las membresías: todos en false (fail-closed). No se registra ningún evento.
```

- **Composición.** `'permisosEfectivos' => new ConsultarPermisosEfectivos($politicaAutorizacion, …)` usa la **misma instancia** de la política que `AutorizarOperacion`.
- **Ruta.** `POST /api/v1/autorizaciones/permisos-efectivos`, sin permiso propio. Como `/decisiones`, forma parte de la autorización y solo responde por la sesión que pregunta: el usuario sale del token.
- **Formato.** Lista de 1 a 20 permisos `modulo.accion`; si no, `422 DATOS_INVALIDOS ["permisos"]`.

**Prototipo** (`semana-12/prototipo/app.js`):

```js
async function puede(permiso) { return { permitido: (await permisosEfectivos())[permiso] === true }; } // pasiva: sin evento
async function intentar(permiso, recurso) { /* POST /autorizaciones/decisiones */ }                     // activa: auditada
// guard: if (!(await puede(p)).permitido) d = await intentar(p, `ruta:#${ruta}`);  → referencia + evento
```

## 5. Pruebas: rojo → verde

Las pruebas PEF-01..PEF-10 se escribieron **antes** de implementar:

| Momento | Resultado | Evidencia |
|---|---|---|
| 1. Pruebas escritas, sin implementación | **80 · 69 pasan · 9 fallan · 2 omitidas**. Pasa solo PEF-10, la de regresión: los intentos ya se auditaban. | [cambio-1-antes-rojo.txt](evidencia/cambio-1-antes-rojo.txt) |
| 2. Implementación, sin tocar contrato ni reglas | **78 pasan · 2 fallan**: **API-01** (el contrato no tiene la ruta) y **ARQ-08** (solo `/decisiones` puede no tener permiso). Las guardas detectaron el cambio, como deben. | [cambio-2-guardas-contrato-y-arquitectura.txt](evidencia/cambio-2-guardas-contrato-y-arquitectura.txt) |
| 3. Contrato v1.2.0 + ARQ-08 ampliada **por nombre** con justificación + 2 escenarios en API-03 | **80 · 78 pasan · 0 fallan · 2 omitidas** · Redocly: válido | [cambio-3-despues-verde.txt](evidencia/cambio-3-despues-verde.txt), [lint](evidencia/salida-lint-openapi.txt) |

**Pruebas nuevas:**

| ID | Qué comprueba |
|---|---|
| PEF-01 | Mapa correcto para Ana |
| PEF-02 | **0 eventos** al consultar a 6 personas |
| PEF-03 | **Concordancia**: para 7 usuarios × 6 permisos, la respuesta coincide con la decisión del PDP |
| PEF-04 | Cambia en cuanto se revoca un rol |
| PEF-05 | Aislamiento por tenant |
| PEF-06 | Fail-closed |
| PEF-07 | HTTP 200, sin evento |
| PEF-08 | 422 con lista vacía, permiso mal escrito, valor que no es lista o más de 20 permisos |
| PEF-09 | Orden del kernel: 400 sin hospital, 401 sin token |
| PEF-10 | Los intentos siguen auditados |

## 6. Demostración y medición de punta a punta

| Medición | Antes (S11) | Después (S12) | Evidencia |
|---|---:|---:|---|
| Denegaciones «fantasma» solo navegando (4 personas) | **9** | **0** | [cambio-5-ruido-antes-despues.txt](evidencia/cambio-5-ruido-antes-despues.txt) |
| Llamadas al servidor para armar el menú | 3 por persona | **1** | `iniciarSesion()` |
| Denegaciones de Ana en el error crítico | (ruido + 2 reales) | **exactamente 2**: `ruta:#/roles` y `POST /api/v1/signos-vitales` | [recorrido.txt](evidencia/recorrido.txt) |
| Paridad API simulada ↔ kernel real | 33/33 | **38/38**, ahora comparando **también** el número de eventos de la bitácora tras cada paso | [paridad.txt](evidencia/paridad.txt) |
| Recorrido escritorio + móvil | 38/38 | **42/42** (4 comprobaciones nuevas de bitácora) | [recorrido.txt](evidencia/recorrido.txt) |

## 7. Costo del cambio, medido

| Métrica (`bin/acoplamiento.php`) | Antes | Después |
|---|---:|---:|
| Clases en `src/` | 96 | 99 |
| Ce de `AutorizacionControlador` | 7 | **10** |
| Ce de `ConsultarPermisosEfectivos` (nueva) | — | 4 |
| Ce medio | 2.47 | 2.49 |
| Líneas duplicadas en Aplicación | 29 | 30 |
| Clases que mezclan ≥ 4 preocupaciones | 1 | 1 |

Evidencia: [cambio-4-acoplamiento-antes-despues.txt](evidencia/cambio-4-acoplamiento-antes-despues.txt).

**Decisión consciente.** El controlador de autorización sube de 7 a 10 dependencias porque ahora atiende las dos consultas de autorización, con su formulario y su vista. Separarlo en un `PermisosEfectivosControlador` dejaría el Ce en 7, a cambio de una clase más. Lo mantuve junto porque ambas operaciones son del mismo recurso (`/autorizaciones`) y el número de preocupaciones no cambió (2: rutas · decisión). Si crece una tercera consulta, se separa.

## 8. Decisiones tomadas durante el cambio

| Decisión | Razón |
|---|---|
| Reutilizar la **misma** `PoliticaAutorizacion` | Un solo lugar decide. PEF-03 lo verifica en 42 combinaciones. |
| Sin permiso propio para la ruta | Exigir permiso para preguntar «qué permisos tengo» sería circular, como en `/decisiones`. Solo responde por el usuario del token. |
| Ampliar ARQ-08 **por nombre** y no con un comodín | Cualquier otra ruta sin permiso sigue rompiendo la prueba. |
| La UI marca en «no» solo el permiso que el servidor negó (EC-4) | Recargar todo haría que el guard sacara a la persona antes de leer la referencia (defecto encontrado en la S11). |
| Contrato v1.2.0 (menor) | Solo agrega una operación: compatible. |

## 9. Riesgos y pendientes

| Riesgo | Mitigación / estado |
|---|---|
| Un cliente podría usar permisos efectivos para «tantear» sin dejar rastro | Solo responde por la **propia** sesión y no revela motivos. Saber los propios permisos no es información sensible. Los intentos reales siguen auditados. |
| La caché de 30 s de la UI puede mostrar un botón que ya no aplica | El servidor vuelve a autorizar (EC-4). Es el comportamiento esperado y está demostrado. |
| CON-PDO e INT-00 siguen omitidas | Falta PostgreSQL configurado. El cambio no tocó la persistencia. |
| B-1 y B-4 siguen solo en la simulación | Pendientes del contrato |
