# Semana 2 (parte B) — Ejemplo ejecutable SRP

| Archivo | Qué es |
|---|---|
| `antes.php` | `GestorAlertas` con todas las responsabilidades. **No se ejecuta** (usaría una base real, `mail()` y `sleep()`); solo se analiza su código fuente. |
| `despues.php` | Versión mínima separada por responsabilidad: `Alerta`, `PoliticaEscalamiento`, `DespachadorNotificaciones`, `Reloj`/`RelojFijo` y `NotificadorSimulado`. |
| `verificar-srp.php` | Verificador estructural y de comportamiento. |

## Cómo ejecutarlo (desde la raíz del repo)

```powershell
php docs/modulos/alertas-criticas-notificaciones-internas/semana-02/srp/ejemplo/verificar-srp.php
```

Escribe la salida en pantalla y en `semana-02/evidencia/verificacion-srp.txt` (UTF-8). Devuelve código 0 si todo pasa.

## Qué comprueba

- **Estructural:** con el tokenizador de PHP lista por clase las dependencias externas (`PDO`, `mail`, `time`, `sleep` y el reloj real). Exige que el antes las concentre en `GestorAlertas` y que en el después las clases de reglas no usen ninguna.
- **Comportamiento:** CA-06 (3 intentos y reporte del fallido), CA-10 y CA-11 (vence a las 10:15, no a las 10:14), CA-08 (acuse idempotente) y CA-12 (una alerta acusada no se escala). Todo con reloj fijo: ninguna prueba espera minutos reales.

El notificador es **simulado**: no envía correos, SMS ni push reales.
Datos ficticios: `PAC-00451`, `PAC-00452`, `enf.turno01`, `med.tratante01`.
