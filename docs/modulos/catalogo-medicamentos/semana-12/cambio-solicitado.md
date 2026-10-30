# Respuesta documentada a un cambio solicitado — segundo parcial

> **Caso preparado.** El cambio real lo pide el ingeniero en la defensa; si es otro, se responde con
> el formato de la sección 6.

## 1. Cambio (supuesto)

«El químico farmacéutico necesita ver, en una sola pantalla, qué medicamentos **cambian de
vigencia en los próximos 30 días**: versiones que empiezan y versiones que se cierran.»

## 2. Por qué cabe en el módulo

Es control de vigencia (alcance de la semana 1). No toca existencias, precios ni prescripciones,
y no agrega reglas: es una consulta sobre datos que ya existen (`vigente_desde`, `vigente_hasta`).

## 3. Respuesta por capa

| Capa | Cambio | Decisión |
|---|---|---|
| Componentes | `CatalogoCambiosProximosView` + reutiliza `TablaMedicamentos`/tarjetas | Sin componentes nuevos de formulario |
| UX | Pestaña «Cambios próximos» en Buscar (QF y auditor); dos grupos: «Entran en vigencia» y «Se cierran», con la fecha en negrita | El estado vacío dice «No hay cambios entre … y …» |
| Accesibilidad | Encabezados `h2` por grupo; fechas como texto, no solo color; tabla en desktop y tarjetas en móvil | Mismos criterios de la semana 9 |
| Movilidad | Rango por defecto «hoy + 30 días»; selector de rango plegado | Prioridad: código, nombre y fecha del cambio |
| API v1 (compatible) | `GET /api/v1/medicamentos/cambios?desde=AAAA-MM-DD&hasta=AAAA-MM-DD`, roles QF_CATALOGO y AUDITOR_CALIDAD, rango máximo 90 días (422 si se excede) | Recurso nuevo; nada existente cambia |
| Aplicación | `ConsultarCambiosDeVigencia(BusquedaCatalogo, Reloj)` | Un caso de uso de solo lectura |
| Dominio / Repository | `BusquedaCatalogo::cambiosEntre(hospitalId, desde, hasta)`: versiones con `vigente_desde` o `vigente_hasta` dentro del rango | ISP: solo cambia la interfaz de lectura |
| Persistencia | Índices `(hospital_id, vigente_desde)` y `(hospital_id, vigente_hasta)` | Medir con RNF-03 |

## 4. Riesgos

- Confundir «se cierra» con «se borra»: el texto dice «deja de estar vigente el …».
- Rangos grandes: límite de 90 días y paginación de 50, igual que la búsqueda.

## 5. Pruebas que se agregarían

| ID | Tipo | Caso |
|---|---|---|
| T-CS-01 | Contrato (PDO = InMemory) | Con los datos ficticios, rango 2026-06-15 a 2026-07-15 devuelve MED-000104 v1 (se cierra) y v2 (entra). |
| T-CS-02 | Caja negra (API) | Rango de 91 días → 422 en `hasta`; CONSULTA_CLINICA → 403. |
| T-CS-03 | UI (Playwright) | Estado vacío con el rango en el texto; sin desplazamiento horizontal a 375 px. |

## 6. Formato para el cambio real

| Campo | Contenido |
|---|---|
| Cambio pedido (textual) | *(completar en la defensa)* |
| Pantallas y componentes afectados | |
| Estados y errores nuevos | |
| Accesibilidad y móvil | |
| API / dominio | |
| Pruebas | |
