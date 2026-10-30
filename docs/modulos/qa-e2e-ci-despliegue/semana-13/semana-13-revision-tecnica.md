# Semana 13 — Revisión técnica formal del módulo QA E2E, CI y despliegue

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana (@bquiroao) |
| **Objeto revisado** | `micro-his-e2e` en `develop` (contrato 1.2.0) y la consola de release (semanas 11 y 12) |
| **Tipo** | Inspección con checklist (revisión formal), una sesión de 90 minutos |

## 1. Roles

| Rol | Quién | Responsabilidad |
|---|---|---|
| Moderador | Líder técnico del equipo (o el docente) | Verifica los criterios de entrada, conduce la sesión y decide la salida |
| Autor | Boris Quiroa | Presenta el objeto, responde y corrige; **no** clasifica la severidad de sus propios hallazgos |
| Revisor de dominio | Responsable de release | Reglas R-01…R-14: gate, vigencia, despliegue |
| Revisor técnico | Un par del equipo (otro módulo) | Capas, contrato, pruebas, seguridad |
| Escriba | Rotativo | Registra cada hallazgo en el formato de la sección 4 |

> En esta entrega la preparación individual (paso 2) la hizo el autor con el checklist y herramientas. Los roles de la sesión quedan definidos para la revisión con el equipo.

## 2. Criterios de entrada y salida

| Entrada (sin esto no se revisa) | Estado |
|---|---|
| E-1 Suite completa en verde contra PostgreSQL | ✅ 89/89 ([semana 12](../semana-12/evidencia/cambio-2-suite-postgresql.txt)) |
| E-2 Mutaciones: ninguna sobrevive | ✅ 34/34 ([semana 12](../semana-12/evidencia/mutaciones.txt)) |
| E-3 Contrato verificado contra el código (API-01…04) | ✅ |
| E-4 Objeto congelado en una rama y los revisores con el material 48 h antes | ✅ `develop` en el commit del PR #544 |

| Salida | Regla |
|---|---|
| **Aprobado** | 0 hallazgos críticos o mayores abiertos |
| **Aprobado con condiciones** | Hallazgos mayores con dueño, fecha y una prueba que los reproduzca |
| **Rechazado** | Algún hallazgo crítico, o la suite en rojo |

**Decisión de esta revisión: aprobado con condiciones.** RT-01 es mayor, y su prueba de reproducción ya existe.

## 3. Checklist (14 ítems), aplicado

| # | Ítem | Cómo se verifica | Resultado |
|---|---|---|---|
| C-01 | Cada regla R-01…R-14 implementada tiene una prueba **y** una mutación que la rompe | `mutaciones.php` | ✅ 34/34 |
| C-02 | Las capas respetan la dirección de dependencias | ARQ-01…06 | ✅ |
| C-03 | El contrato coincide con el código (rutas, permisos, códigos) | API-01…04 | ✅ |
| C-04 | Toda operación exige su permiso en el tenant (R-13) | APP-06, CAM-06 | ✅ |
| C-05 | Las escrituras que el cliente puede reintentar son idempotentes | Lectura + prueba manual | ❌ **RT-01** |
| C-06 | Las reglas críticas también se exigen en la base | INT-05, INT-10 (triggers) | ✅ |
| C-07 | Ningún secreto en el repositorio | `config.php`, búsqueda | ⚠️ **RT-04** |
| C-08 | Cada requisito de la semana 2 está implementado o declarado fuera de alcance | RF-01…14 contra el código | ⚠️ **RT-02** |
| C-09 | Los errores de persistencia salen como 503, nunca como `PDOException` | INT-05, APP-07 | ✅ |
| C-10 | Una regla de negocio vive en un solo lugar (no se duplica en el cliente) | Lectura de `app.js` | ⚠️ **RT-03** |
| C-11 | Las clases mantienen una sola responsabilidad razonable | `bin/acoplamiento.php` | ⚠️ **RT-05** |
| C-12 | La evidencia se identifica por SHA-256 y se repara si se corrompe | PER-04, PER-05 | ✅ |
| C-13 | La consola cumple WCAG 2.2 AA en sus recorridos | axe-core en el recorrido (88/88) | ✅ |
| C-14 | La documentación del módulo refleja el estado actual (contrato, conteos) | README del micro y del módulo | ✅ (actualizada en la semana 12) |

**Resultado:** 9 ✅, 4 ⚠️, 1 ❌.

## 4. Formato de hallazgos

Cada hallazgo se registra con estos campos:
- **Id:** RT-nn.
- **Ítem del checklist:** el C-nn que lo detectó.
- **Ubicación:** archivo y método, o pantalla.
- **Descripción:** qué pasa, en una oración.
- **Evidencia:** comando, prueba o captura que lo reproduce.
- **Severidad:**
  - **Crítica:** pierde o corrompe datos, o salta una regla de seguridad.
  - **Mayor:** resultado incorrecto en un caso real.
  - **Menor:** deuda o riesgo sin efecto hoy.
  - **Sugerencia.**
- **Acción propuesta y dueño.**
- **Estado:** abierto, corregido o aceptado como riesgo.

## 5. Hallazgos

| Id | Ítem | Ubicación | Descripción | Evidencia | Sev. | Acción | Estado |
|---|---|---|---|---|---|---|---|
| RT-01 | C-05 | `RegistrarDespliegue` · `POST …/despliegues` | Un reintento idéntico del registro de despliegue **crea un segundo despliegue**. Es justo lo que haría un operador al que se le cortó la red (escenarios de las semanas 10 y 11) | [reproducir-rt01.sh](herramientas/reproducir-rt01.sh) → [salida](evidencia/rt01-reproduccion.txt): 201, 201, 2 despliegues | **Mayor** | Rechazar con 409 un segundo DESPLEGADO de la misma versión y el mismo digest en el mismo entorno, si es el vigente. Prueba primero. Dueño: autor, semana 15 | Abierto |
| RT-02 | C-08 | RF-08 (cuarentena) y RF-11 (excepción) | No están implementados. Están declarados fuera de alcance desde la semana 6, pero el gate de la semana 2 los nombra | Búsqueda en `src/`: sin `Cuarentena` ni `Excepcion` de negocio | Menor | Dejarlos explícitos en el backlog del producto | Aceptado como riesgo |
| RT-03 | C-10 | `semana-11/prototipo/app.js` (`VIGENCIA_H = 72`) | La vigencia de la aprobación está duplicada en el cliente; el contrato no la expone | Lectura | Menor | Exponer `vence_en` en la trazabilidad (contrato 1.3.0, aditivo) | Abierto |
| RT-04 | C-07 | `config/config.php` | Tokens de demostración fijos en el repositorio. La clave de la base sale de una variable de entorno; los tokens, no | Lectura | Menor | En producción la identidad la da el JWT del HIS; restringir `tokens_demo` a `E2E_ENTORNO=demo` | Abierto |
| RT-05 | C-11 | `Dominio/Modelo/Ejecucion` | Mezcla 4 preocupaciones (133 líneas) desde el refactor de la semana 7 | `acoplamiento-despues.txt` (semana 7) | Sugerencia | Revisar al agregar la cuarentena (RT-02) | Aceptado |
| RT-06 | C-08 | Contrato 1.2.0 | No hay un listado de versiones (brecha B-1 de la semana 8); la consola usa `?versiones=` | Semana 11 §5 | Menor | `GET /versiones` paginado | Abierto |

## 6. Métricas de la revisión

| Métrica | Valor |
|---|---|
| Ítems del checklist | 14 |
| Hallazgos | 6 (1 mayor, 4 menores, 1 sugerencia) |
| Encontrados por herramienta / por lectura / por prueba manual | 1 / 4 / 1 |
| Densidad | 6 hallazgos / ≈ 2 800 líneas de `src/` ≈ 2,1 por KLOC |
