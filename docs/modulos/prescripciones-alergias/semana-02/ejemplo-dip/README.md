# Ejemplo DIP — Prescripciones, semana 2

Ejemplo ejecutable en PHP 8.2+ vanilla, sin Composer ni framework, del flujo «creación de prescripción con verificación previa de alergias».

```
ejemplo-dip/
├── antes/ServicioPrescripcion.php      ← sin DIP: crea su PDO, escribe el SQL, constantes, archivo y date()
├── despues/
│   ├── autoload.php                    ← autoload PSR-4 mínimo
│   ├── composicion.php                 ← raíz de composición: único lugar con clases concretas
│   ├── src/Dominio/                    ← reglas puras: VerificadorAlergias, ValidadorPrescripcion, Alergia, Prescripcion…
│   ├── src/Aplicacion/                 ← EmitirPrescripcion, VerificarAlergiasPrevia, EvaluacionClinica
│   ├── src/Aplicacion/Puertos/         ← las 7 abstracciones, propiedad del alto nivel
│   └── src/Adaptadores/                ← EnMemoria (8) y Pdo/FuenteAlergiasPdo (tabla allergies)
├── verificar.php                       ← 24 pruebas: CA-01..17, DIP-01..05, ANT-01, PAR-01
└── mutaciones.php                      ← 13 defectos inyectados en copias temporales: todos deben detectarse
```

## Ejecutar

Desde la raíz del repositorio:

```bash
php docs/modulos/prescripciones-alergias/semana-02/ejemplo-dip/verificar.php
```

Sin base, DIP-04, DIP-05 y PAR-01 se **omiten** (no fallan). Para ejecutarlas, con un PostgreSQL vacío:

```bash
export PRESCRIPCIONES_DB_DSN="pgsql:host=127.0.0.1;port=55432;dbname=micro_his_prescripciones"
export PRESCRIPCIONES_DB_USUARIO=postgres
php docs/modulos/prescripciones-alergias/semana-02/ejemplo-dip/verificar.php
php docs/modulos/prescripciones-alergias/semana-02/ejemplo-dip/mutaciones.php
```

Las pruebas crean y recrean los esquemas `s2_sano` y `s2_roto` en esa base. Los dos scripts devuelven 0 si todo está bien y 1 si algo falla.
