# Recorrido navegable — demostración con el prototipo

**Prototipo:** [`prototipo/index.html`](prototipo/index.html) (se abre con doble clic). **Duración:** unos 7 minutos. Cada paso enlaza a su captura (la misma que produce `herramientas/recorrido.cjs`).

## Parte A · Roles, estados y una corrección (≈ 3 min, auditor)

| # | Hacer | Señalar | Captura |
|---|---|---|---|
| 1 | Elegir «Auditor de Oriente» | El rol y el hospital, siempre en la barra | [01](capturas/escritorio/01-ingresar.png) |
| 2 | Mirar los filtros sin tocar nada | «Hoy» ya elegido; aviso M-06; **no se cargó nada** (cada consulta queda registrada); no hay «Toda la red» | [02](capturas/escritorio/02-filtros.png) |
| 3 | «Más filtros» → módulo pacientes → Buscar | Carga → 50 filas, «hay más»; `#55 Corregido por #56` | [04](capturas/escritorio/04-resultados.png) |
| 4 | «Cargar 50 más» | 58, «fin de la lista»; el lector de pantalla lo anuncia | [05](capturas/escritorio/05-cargar-mas.png) |
| 5 | «Ver» en #55 → «Ver #56» | El original no cambia; el motivo está en la corrección | [06](capturas/escritorio/06-detalle-original.png) · [07](capturas/escritorio/07-detalle-correccion.png) |
| 6 | «‹» | La **misma** lista: no hubo otra consulta | [08](capturas/escritorio/08-vuelve-a-la-lista.png) |

## Parte B · El cambio solicitado (≈ 1,5 min)

| # | Hacer | Señalar | Captura |
|---|---|---|---|
| 7 | «Ver» en #55 → «¿Quién más accedió a esta ficha?» | La pregunta nace donde surge | [08a](capturas/movil/08a-detalle-con-recurso.png) |
| 8 | — | Filtros con el recurso puesto y el foco en él; **todavía no se consultó** | [08b](capturas/movil/08b-filtros-con-recurso.png) |
| 9 | Buscar | 3 accesos a P-000001 | [08c](capturas/movil/08c-quien-vio-la-ficha.png) |

## Parte C · Error crítico y riesgos (≈ 2,5 min, super administrador)

| # | Hacer | Señalar | Captura |
|---|---|---|---|
| 10 | Más → marcar «La sesión vence…» → Filtros → Buscar | La sesión se renueva **sobre la página**: nada se pierde ni se guarda en el navegador | [09](capturas/escritorio/09-sesion-vencida.png) · [10](capturas/escritorio/10-sesion-renovada.png) |
| 11 | Cerrar sesión → «Super administrador» | Aparece «Alcance» | [11](capturas/escritorio/11-superadmin-filtros.png) |
| 12 | Recorrer el alcance con ↓, luego «Buscar en toda la red» | Mover las flechas no abre nada; el diálogo aparece al buscar, con el foco en «Cancelar»; Esc | [12](capturas/escritorio/12-confirmar-red.png) |
| 13 | Integridad → pegar un sello incompleto → Verificar | El resumen de errores recibe el foco; el botón nunca se deshabilitó | [14](capturas/escritorio/14-ancla-invalida.png) |
| 14 | Norte → Verificar → confirmar | **«Cadena alterada desde la secuencia 2»**: no modifique nada, contacto, informe; sin reparar | [16](capturas/escritorio/16-cadena-alterada.png) |
| 15 | F12 → modo móvil, 360 px → repetir el paso 14 | El mismo veredicto, legible en el teléfono | [16 móvil](capturas/movil/16-cadena-alterada.png) |

## Si algo falla en vivo

- **El prototipo no abre:** mostrar las capturas de [`capturas/`](capturas/). Son la salida del recorrido automático, no montajes.
- **Piden probar que la simulación no miente:** `node herramientas/paridad.cjs` (0 diferencias contra el kernel real).
