# Semana 8 — Catálogo de medicamentos: diseño de experiencia de usuario

Flujo «alta, búsqueda y control de vigencia» para los roles autorizados.

| Entregable | Archivo |
|---|---|
| User flow QF_CATALOGO | `diagramas/flujo-qf-catalogo.png` |
| User flow CONSULTA_CLINICA | `diagramas/flujo-consulta-clinica.png` |
| User flow AUDITOR_CALIDAD | `diagramas/flujo-auditor.png` |
| 6 wireframes anotados (Salt) | `wireframes/w1-buscar.png` … `w6-baja-eliminar.png` |

`SISTEMA_PRESCRIPCIONES` no tiene interfaz: usa la API (semana 5).

## 1. Qué ve y hace cada rol

| Acción | QF_CATALOGO | CONSULTA_CLINICA | AUDITOR_CALIDAD |
|---|---|---|---|
| Buscar vigentes (hoy o a una fecha) | sí | sí | sí |
| Ver detalle | sí | sí (solo lectura) | sí |
| Ver historial de versiones | sí | no | sí |
| Registrar, nueva versión, baja, eliminar | sí | no (los botones no aparecen) | no |

Ocultar un botón es ayuda visual; el servidor responde 403 de todas formas (RNF-01).

## 2. Wireframes anotados

### W1 — Buscar (inicio)
- **Estados:** inicial sin búsqueda (texto de ayuda), escribiendo.
- **Validaciones:** `q` de 2 a 60 caracteres **o** una forma; si no, mensaje bajo el campo (no ventana emergente).
- **Ayuda contextual:** «% y _ se buscan como texto»; «vacío = hoy» junto a la fecha.
- **Interacción:** Enter busca; «Limpiar» vuelve al estado inicial y deja el foco en el campo de texto.

### W2 — Resultados
- **Carga:** indicador «Buscando…» con `aria-live="polite"`; el botón se deshabilita para evitar doble envío.
- **Éxito:** «2 resultados vigentes al 2026-10-15» (siempre se dice la fecha consultada).
- **Vacío:** explica la causa probable (dado de baja) y ofrece «Registrar medicamento» (solo QF) y «Buscar en otra fecha».
- **Error 503:** «El catálogo no responde. Reintentar», conservando el criterio escrito.

### W3 — Alta con errores (422)
- **Validación:** todos los errores llegan juntos (RF-02); resumen arriba con enlaces a cada campo y mensaje bajo cada campo (`aria-describedby`).
- **Mensajes:** dicen qué hacer, con el límite exacto («Use entre 3 y 150 caracteres (tiene 1)»).
- **Regla:** los datos escritos se conservan; el foco va al resumen de errores.

### W4 — Duplicado (409, error recuperable)
- **Mensaje:** «Ya existe un medicamento equivalente vigente» con el código y la ficha del existente.
- **Recuperación:** «Ver MED-000102», «Corregir datos» o «Cancelar»; sugiere «Nueva versión» si es otra presentación.
- **Soporte:** muestra el `request_id` para reportar el caso.

### W5 — Detalle, historial y confirmación de nueva versión
- **Historial:** versión vigente resaltada («sí» en negrita); fechas `[desde, hasta)`.
- **Confirmación:** antes de guardar se dice qué se cerrará y cuándo; «Volver» no cambia nada.
- **Error 409 `SOLAPE_VIGENCIA`:** se muestra junto a la fecha, con la fecha mínima permitida.

### W6 — Baja y eliminación protegida
- **Baja:** motivo obligatorio (5–200 caracteres); se informa que el historial se conserva.
- **Eliminar usado (409 `EN_USO`):** bloqueado con explicación y acción alternativa «Ir a dar de baja» (RN-02).
- **Eliminar sin uso:** se confirma escribiendo el código (evita borrados por clic accidental).

## 3. Reglas de interacción comunes

1. Un mensaje de éxito usa `role="status"`; un error usa `role="alert"` y lleva una acción para salir de él.
2. Ninguna acción destructiva se ejecuta con un solo clic.
3. Las fechas se muestran `AAAA-MM-DD` y siempre se dice «vigente al …».
4. Los códigos (`MED-000102`) son enlaces al detalle y se pueden copiar.
5. Tras guardar, se usa PRG (redirección) para que recargar la página no repita la operación.

## 4. Protección de datos

- El catálogo **no** muestra pacientes ni prescripciones; solo datos del producto.
- El hospital y el usuario se muestran en la cabecera para que nadie trabaje en otro tenant por error.
- No se muestran SQL ni trazas en ningún error; solo el `request_id`.
- Registros sanitarios, códigos y fechas de los wireframes son **ficticios**.

## 5. Pendientes que pasan a la semana 9

- Una baja el mismo día del alta no se permite (vigencia vacía); la UI debe explicarlo y ofrecer «Eliminar registro erróneo».
- La búsqueda distingue acentos.
- El auditor no ve quién registró cada versión: la API v1 no expone `creado_por` (cambio compatible propuesto).
