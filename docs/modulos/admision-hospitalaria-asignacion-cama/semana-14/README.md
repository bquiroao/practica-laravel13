# Semana 14 — Confiabilidad y aseguramiento de calidad

Módulo: **Admisión hospitalaria y asignación de cama** · Flujo: **admisión de paciente y asignación de una cama disponible**

Plan SQA del módulo: qué se mide, con qué umbral, quién responde y qué se hace si no se cumple. Las métricas M-01, M-03, M-04, M-05 y M-07 se pueden **medir** con `metricas-sqa.php` (sección 5).

## 1. Objetivos de calidad
- **Integridad:** nunca dos admisiones con la misma cama (RN-01) ni dos admisiones activas por paciente (RN-02).
- **Confiabilidad:** una falla técnica no deja datos a medias y el usuario sabe si debe reintentar (RNF-08).
- **Seguridad:** rol y hospital correctos en cada operación (RN-06, RNF-02); sin datos clínicos expuestos (RNF-03).
- **Trazabilidad clínica:** cada admisión con sus eventos inmutables (RN-05).
- **Usabilidad y accesibilidad:** WCAG 2.1 AA en el flujo (RNF-07).

## 2. Métricas

| ID | Métrica | Umbral | Fuente | Responsable | Acción si no se cumple |
|---|---|---|---|---|---|
| M-01 | Camas con más de una asignación activa | **= 0** | Consulta SQL (`metricas-sqa.php`) | Erick (autor del módulo) | Incidente **crítico**: bloquear las asignaciones de esa cama, avisar al líder y a supervisión de camas, y revisar `ux_asignacion_cama_activa` |
| M-02 | Pruebas en verde por PR | **Fallas: 0** (100 %) | `php pruebas/ejecutar.php` (última línea, pegada en el PR) | Autor del PR; verifica Boris | No se mergea el PR |
| M-03 | p95 de `POST /api/v1/admisiones` | **≤ 800 ms** (y RNF-04: p95 ≤ 500 ms en `GET /camas-disponibles`) | Registro JSON de `ApiKernel` (`duracion_ms`) | Erick | Analizar las consultas; si se repite 2 semanas, evaluar el gatillo G-1 (semana 5) |
| M-04 | Proporción de `POST` con 409 | **≤ 5 %** por semana | Registro JSON (`estado`) | Erick y supervisión de camas | Revisar la antigüedad de la lista («Actualizado hace N s») y la reserva temporal (RN-07, semana 6) |
| M-05 | Líneas del registro con código de paciente o nombres | **= 0** | Búsqueda en el registro (`metricas-sqa.php`) | Erick | Incidente de privacidad: corregir el registro y avisar al líder |
| M-06 | Hallazgos de accesibilidad de severidad alta abiertos | **= 0** antes de cada parcial | `semana-09/README.md` + `evidencia/auditoria-navegador.py` | Erick | Priorizar en el backlog (B-01 a B-04) |
| M-07 | Admisiones con sus 2 eventos (`ADMISION_CREADA`, `CAMA_ASIGNADA`) | **= 100 %** | Consulta SQL (`metricas-sqa.php`) | Erick | Incidente mayor: revisar la transacción de `registrar()` |

## 3. Matriz de riesgos

Probabilidad (P) e impacto (I) en escala de 1 a 3; nivel = P × I.

| ID | Riesgo | P | I | Nivel | Control preventivo | Control detectivo |
|---|---|---|---|---|---|---|
| R-01 | Dos admisiones con la misma cama | 2 | 3 | **6** | Índice único parcial + transacción + `lock_timeout` | M-01; pruebas de concurrencia con dos conexiones |
| R-02 | Admisión sin cama o cama sin eventos (datos a medias) | 1 | 3 | 3 | Una sola transacción con reversión | M-07; prueba «Error de persistencia a mitad de la transacción revierte todo» |
| R-03 | Acceso a datos de otro hospital | 1 | 3 | 3 | Tenant del token; `WHERE tenant_id` en cada consulta | Pruebas de tenant (API y caso de uso); C-06 de la RTF |
| R-04 | Exposición de datos del paciente | 2 | 3 | **6** | Solo código; salida escapada; registro sin datos clínicos | M-05; C-10 de la RTF |
| R-05 | Contrato con el catálogo de Josué sin acordar | 3 | 2 | **6** | Puerto `CatalogoCamas` de solo lectura; propuesta de vistas o eventos | Revisión con Josué en el calendario; prueba de contrato |
| R-06 | Error de UX que asigna la cama equivocada | 2 | 2 | 4 | Paso de confirmación (B-01); etiquetas legibles (B-07) | M-06; M-04 |
| R-07 | Renombrar un índice rompe la traducción de errores (RT-01) | 1 | 2 | 2 | Constantes compartidas (acción de RT-01) | Pruebas de integración en el criterio de entrada (M-02) |

## 4. Controles

| Tipo | Control | Dónde |
|---|---|---|
| Preventivo | PR menores de 400 líneas, una rama por semana desde `develop` | Reglas del repositorio |
| Preventivo | `prepare()` + `execute()` siempre; salida HTML escapada | Persistence y `Vista` |
| Preventivo | Autorización en el caso de uso (`exigirRol`) | Application |
| Preventivo | Revisión técnica formal antes de cerrar el módulo | Semana 13 |
| Detectivo | Ejecutor de pruebas con unitarias, contrato e integración | `micro-his/pruebas/` |
| Detectivo | Prueba de arquitectura (dependencias, SQL fuera de Persistence) | `prueba-dependencias.php` |
| Detectivo | Métricas M-01, M-03, M-04, M-05 y M-07 | `metricas-sqa.php` |
| Detectivo | Auditoría automática de accesibilidad | `semana-09/evidencia/auditoria-navegador.py` |

## 5. Cómo medir

Desde `semana-14/`, con la base arriba y el registro guardado de la consola del servidor:

```powershell
# Se usa cmd para la redirección: el 2> de PowerShell 5 guardaría el archivo en UTF-16
cmd /c "php -S 127.0.0.1:8085 ..\micro-his\public\index.php 2> registro-servidor.log"
# (en otra terminal, después de usar la API, y con el servidor detenido con Ctrl+C)
php metricas-sqa.php --base=micro_admision --registro=registro-servidor.log
```

Esto lo probé en mi entorno: tres `POST` por la API, el tercero a propósito sobre una cama ya ocupada. El resultado fue M-01 = 0, M-07 = 2 de 2, M-03 = 10 ms, M-05 = 0 y **M-04 = 33.3 % (NO CUMPLE)**. Lo esperado: forcé el conflicto en 1 de 3 solicitudes, y sirve para mostrar que la métrica detecta el problema. **No es una medición de uso real.**

## 6. Calendario de seguimiento

| Cuándo | Qué | Responsable |
|---|---|---|
| Cada PR | M-02 (última línea de las pruebas en la descripción) | Autor; verifica Boris |
| Cada semana | M-01, M-04, M-05 y M-07 con `metricas-sqa.php` | Erick |
| Antes de cada parcial | M-06 (auditoría de accesibilidad) y revisión de la matriz de riesgos | Erick |
| Semana 13 | Revisión técnica formal (checklist C-01 a C-14) | Moderador: Boris (propuesta) |
| Al acordar con Josué | Cerrar R-05 (contrato de ocupación) | Erick y Josué |
| Semana 15 | M-02 con el conjunto final de pruebas y el registro de defectos | Erick |
