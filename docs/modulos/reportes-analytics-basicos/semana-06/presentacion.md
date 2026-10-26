---
marp: true
paginate: true
title: Reportes y analytics básicos · Primera evaluación parcial
---

# Reportes y analytics básicos
## Arquitectura de «cálculo y visualización trazable de un indicador hospitalario»

Erick Rolando Ramazzini Muralles · ASII 2026 · Primera evaluación parcial

Pregunta guía: **¿de dónde sale este número y puedo comprobarlo?**

---

# 1. Actores y casos de uso (semana 1)

| Actor | Qué puede hacer |
|---|---|
| Administrador | Los 10 reportes, tres niveles |
| Jefe de área | Los 10 reportes, tres niveles |
| Médico | Solo Citas, y solo las suyas (RN-08) |

CU-01 Consultar · CU-02 Filtrar · CU-03 Calcular · CU-04 Ficha · CU-05 Permiso · CU-06 Anonimizar · CU-07 Desglosar · CU-08 Registros

Trazabilidad en 3 niveles: **78 %** → Medicina 18 · Cirugía 14 · Pediatría 7 → las 18 camas de Medicina

---

# 2. RF/RNF que mandan en el diseño (semana 2)

| Requerimiento | Decisión que provoca |
|---|---|
| RF-03 ficha de trazabilidad | `Indicador` no se crea sin fórmula, período, filtros, fuente y fecha |
| RF-04/05 desglose y registros | Contrato con 3 niveles y postcondición «la suma coincide» |
| RF-07 / RNF-02 permisos | `PoliticaPermisos` en Application; 403 |
| RNF-04 solo lectura | Repository solo con lecturas; rol `reportes_lectura` |
| RNF-05 mantenibilidad | Un reporte nuevo = una clase registrada |

---

# 3. SOLID: LSP (semana 2)

- **Antes:** Ocupación devolvía 0.78, Citas exigía `medico_id`, Mortalidad no desglosaba → cliente lleno de `instanceof`.
- **Después:** `ContratoReporte` con las mismas pre y postcondiciones para todos.
- Evidencia: **las mismas pruebas de contrato** pasan con cada reporte (16 de 16).

---

# 4. Capas y Repository (semanas 3 y 4)

`Presentation → Application → Domain ← Persistence`

- MVC en la entrada: el controlador **no tiene SQL ni reglas** (lo verifica una prueba).
- `OcupacionRepository` vive en Domain; adaptadores **PDO** (PostgreSQL 16) e **InMemory**.
- Una prueba compara los dos adaptadores: mismo desglose, mismos registros.
- 3 pruebas pedidas: camino feliz, regla de dominio y error de persistencia con doble.

---

# 5. Contrato API y frontera (semana 5)

| Ruta | Nivel |
|---|---|
| `GET /api/v1/reportes/{tipo}` | Indicador + ficha |
| `GET …/{tipo}/desglose` | Desglose (suma = numerador) |
| `GET …/{tipo}/registros?parte=` | Registros con datos mínimos |

Errores con forma común (403, 422, 503) y `X-Correlation-Id`.
Microservicio: **no dividir ahora**; 5 umbrales medibles y migración *strangler* en 4 fases.

---

# 6. Cambio práctico: estancia promedio (ALOS)

«Agregar la estancia promedio por sala, con un decimal, filtrable por sala y con su fuente.»

- Una clase nueva `ReporteEstanciaPromedio` + su Repository; **cero cambios** en controlador, vista y API.
- Precisión: se calcula exacto y se redondea **solo al mostrar**.
- RN-07 en un promedio: el desglose suma **días-cama** y **egresos**, no promedios.

---

# 7. Cierre: matriz decisión → evidencia

Cada decisión tiene una evidencia que se puede abrir en el repositorio (ver `matriz-decision-evidencia.md`).

**Respuesta a la pregunta guía:** el número se puede comprobar en 3 clics y en 29 pruebas automatizadas.
