# Guía de defensa oral — Semana 15 (pruebas y defectos)

**Estudiante:** Boris Alexander Quiroa Orellana

## 1. Resumen en 60 segundos

La matriz une cada RF/RNF con sus pruebas y con la mutación que lo rompe. Dos requisitos quedan sin prueba porque no están implementados (cuarentena y excepción), y eso está dicho, no escondido.

Escribí 9 pruebas nuevas por técnica, **antes** de corregir:
- **3 unitarias** del dominio;
- **2 de caja blanca**, que cubren las dos ramas nuevas;
- **4 de caja negra** por HTTP, con particiones y valores límite tomados del contrato.

Las 5 que tocaban el defecto fallaron primero; las otras 4 fijan comportamiento que ya era correcto.

El defecto principal, **DEF-01**, salió de la revisión técnica: reintentar un despliegue lo duplicaba. Ahora un reintento idéntico dentro de 15 minutos devuelve el mismo despliegue. Resultados:
- 98/98 pruebas contra PostgreSQL;
- el script de la semana 13 pasó de «201, 201, 2» a «201, 200, 1»;
- una mutación nueva, M35, detectada.

## 2. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué 15 minutos? | Un reintento del operador es inmediato, y un redespliegue legítimo de la misma versión con la misma guía no ocurre en 15 minutos. Es una constante del dominio (`VENTANA_REINTENTO_MIN`), con su límite probado (UNI-03). |
| ¿Por qué no un `Idempotency-Key` como en los resultados? | El operador no es un programa: no guarda claves entre intentos. La identidad del despliegue son sus datos. |
| ¿Qué diferencia hay entre caja blanca y caja negra aquí? | Las de caja blanca se diseñaron mirando las ramas de `RegistrarDespliegue`. Las de caja negra, solo desde el contrato: formatos, rangos y roles. |
| ¿Qué queda abierto? | DEF-04 (vigencia duplicada en la consola) y DEF-05 (sin listado de versiones), los dos menores. |
