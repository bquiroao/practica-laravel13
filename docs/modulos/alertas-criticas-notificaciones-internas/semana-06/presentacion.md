---
marp: true
paginate: true
title: Alertas críticas y notificaciones internas — Primer parcial
---

# Alertas críticas y notificaciones internas
## Primer parcial · ASII 2026
Erick Rolando Ramazzini Muralles · @ErickRamazzini
Flujo: detección, envío, acuse y escalamiento de una alerta crítica

---

## 1. Problema, actores y alcance
- Un valor crítico (p. ej. potasio 6.8 mmol/L de `PAC-00451`) debe llegar a alguien que **acuse**.
- Actores: sistema origen, enfermería de turno, médico tratante, jefe de servicio, coordinación, planificador.
- Fuera de alcance: calcular resultados, escribir en el expediente, canales reales.

---

## 2. UML trazable
- Casos de uso CU-01 a CU-09 (semana 1).
- Actividad con decisiones: datos válidos, duplicado, sin destinatarios, 3 intentos, plazo vencido.
- Secuencia con los mismos nombres de clase que el código.
- Diagrama trazable: actor → CU → RF → regla → clase → endpoint → prueba.

---

## 3. RF/RNF y reglas que se defienden
- RF-01 a RF-10 y CA-01 a CA-16 (Dado/Cuando/Entonces).
- RN-04 plazo por prioridad · RN-05 escalamiento hasta nivel 2.
- RN-06 acuse idempotente · RN-08 hasta 3 intentos · RN-10 historial que solo crece.
- RNF-04: el tiempo viene de un reloj inyectable.

---

## 4. Principio SOLID: SRP
- Antes: `GestorAlertas` con 7 razones de cambio (`mail()`, `sleep()`, `time()` y SQL juntos).
- Después: `Alerta`, `PoliticaEscalamiento`, `DespachadorNotificaciones` y casos de uso.
- Evidencia: el verificador mide las dependencias externas por clase y prueba CA-06, CA-08, CA-10 a CA-12.

---

## 5. Capas y Repository
- Presentation (consola, MVC, API) → Application → Domain ← Persistence.
- `AlertaRepository` en Domain; adaptadores PDO e InMemory.
- La misma prueba de contrato (8 casos) corre contra los dos.
- El controlador no tiene SQL ni reglas (prueba estática).

---

## 6. Contrato API y seguridad
- `POST /api/v1/alertas` (201/200 idempotente), acuse, cierre, escalamientos.
- Token con rol y tenant: el tenant **nunca** viene en el cuerpo.
- Error único `{codigo, mensaje, regla, request_id}` y `X-Request-Id`.
- RF-08: solo médico o jefe de servicio cierran.

---

## 7. Frontera de microservicio
- Hoy **no** se separa: ningún gatillo medible se cumple.
- Gatillos: revisión de plazos > 20 s, > 5 000 alertas/día, despliegues que cortan el escalamiento.
- Migración por etapas: monolito → worker con bandeja → BD propia → cola (opcional).

---

## 8. Cambio práctico preparado y evidencia
- Caso: «CRITICA a 10 minutos y 5 reintentos». Cambian el Composition Root y 2 pruebas; el dominio no.
- 90 pruebas automáticas: unidad, aplicación, integración PDO, contrato, MVC y API.
- Matriz decisión → evidencia en `matriz-decision-evidencia.md`.
