# Guía de defensa oral — Semana 15

## Presentación breve

«Preparé pruebas unitarias, de caja blanca y caja negra para el flujo de revisión de resultados por bioquímico. La matriz relaciona cada requisito con casos identificables. Uso dobles de reloj, autorización y repositorio para probar decisiones, conflictos y errores técnicos sin acceder a módulos ajenos.»

## Preguntas para explicar la estrategia

1. **¿Qué se considera firma en esta entrega?** La identidad del bioquímico enlazada con hospital, versión, decisión y momento. No es una firma criptográfica.
2. **¿Por qué no se prueba un rango bioquímico?** El catálogo y los intervalos por examen no están definidos en este flujo; no se deben inventar reglas clínicas ni sustituir el criterio profesional.
3. **¿Qué cubre caja blanca?** Las ramas de precondiciones del caso de uso y cada estado que devuelve el puerto de persistencia.
4. **¿Qué particiones de caja negra se probaron?** Decisiones válidas, límite de versión, campos/contexto omitidos, cambio concurrente, hospital distinto y error de persistencia.
5. **¿Por qué usar dobles?** Hacen repetibles la autorización, el reloj y los fallos de repositorio sin base de datos ni servicios externos.

## Comandos de evidencia

```powershell
php docs/modulos/validacion-resultados-bioquimico/semana-15/pruebas/run.php
php docs/modulos/validacion-resultados-bioquimico/semana-02/ejemplo-dip/verificar.php
```

La salida íntegra, capturas y registro de defectos se conservan en `evidencia/`.
