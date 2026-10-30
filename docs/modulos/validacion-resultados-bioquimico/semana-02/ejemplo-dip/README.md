# Ejemplo DIP — Validación de resultados bioquímicos

Demostración ejecutable en PHP 8.2+ vanilla, sin framework ni Composer, del proceso «revisión, validación o rechazo de un resultado por bioquímico».

```text
ejemplo-dip/
├── antes/
│   └── CoupledLabResultReview.php        ← servicio acoplado a PDO, SQL, reloj y rol concreto
├── despues/
│   ├── ReviewWorkflow.php                ← dominio, contratos y caso de uso
│   └── InMemoryLabResultDecisionRepository.php
└── verificar.php                         ← criterios CA-01 a CA-13
```

## Ejecutar las pruebas

Desde la raíz del repositorio:

```bash
php docs/modulos/validacion-resultados-bioquimico/semana-02/ejemplo-dip/verificar.php
```

El proceso termina con código `0` cuando pasan las pruebas y con código distinto de cero si una aserción falla. El diseño «antes» es una muestra conceptual acoplada a PDO; no requiere ni modifica una base real. El diseño «después» se prueba con un repositorio en memoria que implementa el mismo contrato que implementarían los adaptadores de persistencia. La persistencia de producción y su esquema se dejan para la Semana 3.

La salida ejecutada se conserva en [`../evidencia/salida-verificar.txt`](../evidencia/salida-verificar.txt).

## Alcance del ejemplo

El valor y la unidad sirven únicamente para demostrar que la decisión no altera el dato. No hay rangos bioquímicos ni reglas clínicas codificadas: la interpretación pertenece al bioquímico y a los procedimientos vigentes para cada prueba.
