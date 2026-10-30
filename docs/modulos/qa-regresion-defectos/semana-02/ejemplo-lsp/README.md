# Ejemplo LSP — QA y regresión, semana 2

Ejemplo ejecutable en PHP 8.2+ vanilla, sin Composer ni framework, de la evidencia de las campañas de regresión.

```
ejemplo-lsp/
├── antes/Evidencias.php        ← una jerarquía «Evidencia» con 2 subtipos que rompen el contrato
├── despues/
│   ├── autoload.php
│   ├── src/Dominio/Evidencia/  ← EvidenciaVerificable (contrato), EvidenciaInmutable (métodos final),
│   │                              Captura, RegistroTexto, ResultadoJUnit; ReferenciaExterna fuera de la jerarquía
│   ├── src/Dominio/Modelo.php  ← CasoPrueba, Campana, Intento, Defecto, CriterioSalida
│   ├── src/Aplicacion/         ← RegistrarResultado, VerificarIntegridad, GenerarInforme, ConsultarEvidencia,
│   │                              ImportarLogRemoto y sus puertos
│   └── src/Adaptadores/        ← almacén, política y pipeline en memoria
├── verificar.php               ← 21 pruebas: CA-01..CA-15 y LSP-01..LSP-06
└── mutaciones.php              ← 15 defectos inyectados en copias temporales: todos deben detectarse
```

## Ejecutar

Desde la raíz del repositorio (no necesita base de datos):

```bash
php docs/modulos/qa-regresion-defectos/semana-02/ejemplo-lsp/verificar.php
php docs/modulos/qa-regresion-defectos/semana-02/ejemplo-lsp/mutaciones.php
```

Los dos devuelven 0 si todo está bien y 1 si algo falla. Requieren las extensiones `mbstring` y `SimpleXML`.
