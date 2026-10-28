# Reportes y analytics básicos — Semana 8

**Tema:** diseño de experiencia de usuario.
**Tarea individual:** diseñar el flujo UX de «cálculo y visualización trazable de un indicador hospitalario» por sus roles autorizados: inicio, decisión, confirmación, estados vacío/carga/éxito, errores recuperables, ayuda contextual y protección de datos vinculados con indicadores, agregaciones, filtros, fuentes, precisión y visualización.
**Entregable:** user flow por rol y 4–6 wireframes anotados con estados, mensajes, validaciones y reglas de interacción.

| Archivo | Contenido |
|---|---|
| `diagramas/flujo-administrador-jefe.puml` | User flow del Administrador y del Jefe de área (mismo acceso: 10 reportes, 3 niveles) |
| `diagramas/flujo-medico.puml` | User flow del Médico (solo sus citas) |
| `wireframes.md` y `wireframes/*.puml` | 6 wireframes anotados con estados, mensajes, validaciones y reglas de interacción |

## Roles y lo que cambia en su flujo

| Rol | Inicio | Reportes visibles | Diferencia en el flujo |
|---|---|---|---|
| Administrador | Menú → Reportes | Los 10 | Ninguna: flujo completo |
| Jefe de área | Menú → Reportes | Los 10 | Igual al Administrador (RN-01); su sala aparece preseleccionada en el desglose |
| Médico | Menú → Mis citas en reportes | Solo Citas | El filtro de médico está fijo en él mismo y no se puede cambiar (RN-08) |

## Pasos del flujo y sus estados

| Paso | Qué ve el usuario | Estados y mensajes |
|---|---|---|
| Inicio | Selector de reporte con una línea de ayuda por indicador | Si el rol no tiene reportes: «No tiene reportes asignados. Consulte al administrador» |
| Decisión | Período (desde/hasta), dimensión y, si aplica, granularidad | Validación en el momento: «La fecha inicial no puede ser mayor que la final» (RN-02) |
| Confirmación | Botón «Calcular» con resumen: «Ocupación · 01/10/2026 a 31/10/2026» | El botón queda deshabilitado mientras hay errores |
| Carga | Esqueleto de la tarjeta y texto «Calculando…» anunciado al lector de pantalla | Si tarda más de 2 s (RNF-01): «Sigue calculando, puede tardar unos segundos más» |
| Éxito | Valor con su unidad y precisión, comparación y botón «¿De dónde sale?» | — |
| Vacío | «Sin datos para este período» con sugerencia de ampliar el período (RN-06) | Nunca se muestra 0 |
| Error recuperable | «No se pudieron obtener los datos. Intente de nuevo» + botón «Reintentar» + código de seguimiento | Se conservan los filtros elegidos |
| Sin permiso | «No tiene permiso para ver este reporte» (403) | Sin botón de reintento |
| Trazabilidad | Ficha → Desglose → Registros | Grupos con menos de 3 casos aparecen como «Otro» (RN-04) |

## Ayuda contextual

| Dónde | Ayuda |
|---|---|
| Junto a cada indicador | Ícono «i» con la fórmula en lenguaje simple: «Camas ocupadas entre camas habilitadas» |
| En el desglose | «La suma de las partes es igual al total» con una marca de verificación (RN-07) |
| En los registros | «Solo se muestran datos mínimos; el paciente aparece con su código» (RN-09) |
| En la precisión | «Valor redondeado a un decimal; el cálculo usa los valores completos» |

## Protección de datos en la experiencia

- Los registros nunca muestran nombre ni diagnóstico; el código de paciente no es un enlace al expediente.
- Al cambiar de rol o cerrar sesión se limpian los resultados en pantalla.
- Copiar o imprimir no está en este módulo (es de Reportes exportables).
