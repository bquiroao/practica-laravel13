"""Mide el contraste WCAG 2.2 de los pares de color de los wireframes de la consola de release (semana 8).

Uso:
    python contraste.py                    # tabla de los colores actuales y de los propuestos
    python contraste.py --solo-propuestos

Termina con código 1 si algún color PROPUESTO no cumple su umbral: los criterios de aceptación del backlog
(B-02, B-05) se verifican con un comando.
"""
import sys

# Umbrales WCAG 2.2 nivel AA
TEXTO_NORMAL = 4.5   # 1.4.3, texto < 18 pt (o < 14 pt en negrita)
TEXTO_GRANDE = 3.0   # 1.4.3, texto >= 18 pt (o >= 14 pt en negrita)
NO_TEXTO = 3.0       # 1.4.11, bordes de campos, casillas e indicadores de estado
EXENTO = 0.0         # 1.4.3 exime los componentes deshabilitados


def luminancia(hex_color: str) -> float:
    canales = [int(hex_color[i:i + 2], 16) / 255 for i in (0, 2, 4)]
    lineal = [c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4 for c in canales]
    return 0.2126 * lineal[0] + 0.7152 * lineal[1] + 0.0722 * lineal[2]


def contraste(a: str, b: str) -> float:
    la, lb = sorted((luminancia(a), luminancia(b)), reverse=True)
    return (la + 0.05) / (lb + 0.05)


def umbral_texto(pt: float, negrita: bool) -> float:
    return TEXTO_GRANDE if pt >= 18 or (negrita and pt >= 14) else TEXTO_NORMAL


# (id, uso, pantallas, primer plano, fondo, umbral, propuesta o None)
# Los colores salen tal cual del generador de wireframes de la semana 8 (wf-e2e-base.js y wf-e2e-pantallas.js).
PARES = [
    ("C-01", "Etiquetas de campo: «Id», «Digest instalado», «Smoke posterior» (10 pt negrita)", "W1 W5", "8A8F96", "FFFFFF", umbral_texto(10, True), "5F6670"),
    ("C-02", "Encabezados de tabla (10 pt negrita)", "W1 W2 W3 W4", "8A8F96", "F2F3F5", umbral_texto(10, True), "5F6670"),
    ("C-03", "Datos de contexto: commit y digest, «4 de 6…», historial, detalle de la línea de tiempo (10 pt)", "W2 W3 W4 W6", "8A8F96", "FFFFFF", umbral_texto(10, False), "5F6670"),
    ("C-04", "Columnas «Por qué está» y «Umbral» (10.5 pt)", "W2 W4", "8A8F96", "FFFFFF", umbral_texto(10.5, False), "5F6670"),
    ("C-05", "Títulos de estado sobre gris: CARGA, SIN PERMISO (10 pt negrita)", "W1–W6", "8A8F96", "F2F3F5", umbral_texto(10, True), "5F6670"),
    ("C-06", "Títulos de estado sobre ámbar: CONGELADO, SIN CONEXIÓN, 409 (10 pt negrita)", "W1–W6", "8A8F96", "FFF4E5", umbral_texto(10, True), "5F6670"),
    ("C-07", "Títulos de estado sobre rosa: ABORTADA, NO DESPLEGABLE (10 pt negrita)", "W3 W5", "8A8F96", "FDECEC", umbral_texto(10, True), "5F6670"),
    ("C-08", "Títulos de estado sobre verde: ÉXITO (10 pt negrita)", "W1 W4 W5", "8A8F96", "E7F4EC", umbral_texto(10, True), "5F6670"),
    ("C-09", "Chip «pendiente» (9 pt negrita)", "W3", "8A8F96", "FFFFFF", umbral_texto(9, True), "5F6670"),
    ("C-10", "Placeholder «Buscar por id, título o RF…» (11 pt itálica)", "W1", "A0A4A8", "FFFFFF", umbral_texto(11, False), "6B7178"),
    ("C-11", "Chips PASA y «APROBADO · vence en 41 h» (9 pt negrita)", "W3 W5", "2E8B57", "E7F4EC", umbral_texto(9, True), "1F6B43"),
    ("C-12", "«✓» de las reglas que cumplen (10.5 pt negrita)", "W4", "2E8B57", "FFFFFF", umbral_texto(10.5, True), "1F6B43"),
    ("C-13", "Chips EN CURSO, SIN PLAN e INESTABLE (9 pt negrita)", "W2 W3", "B5651D", "FFF4E5", umbral_texto(9, True), "8A4A12"),
    ("C-14", "Borde de campo de texto, selector y casilla", "W1 W2 W5", "9AA0A6", "FFFFFF", NO_TEXTO, "767C82"),
    ("C-15", "Pista bajo el campo «Formato guia-AAAA.N» (10 pt itálica)", "W5", "8A8F96", "FFFFFF", umbral_texto(10, False), "5F6670"),
    ("C-16", "Texto general (10.5–11 pt)", "Todas", "2B2F33", "FFFFFF", umbral_texto(10.5, False), None),
    ("C-17", "Cabecera: módulo, hospital y usuario (12 pt negrita)", "Todas", "FFFFFF", "3D4F6B", umbral_texto(12, True), None),
    ("C-18", "Botón primario «Guardar plan» (11 pt negrita)", "W1 W2", "FFFFFF", "3D4F6B", umbral_texto(11, True), None),
    ("C-19", "Botón «Registrar RECHAZADO» (11 pt negrita)", "W4", "FFFFFF", "A33A3A", umbral_texto(11, True), None),
    ("C-20", "Error junto al campo (10 pt)", "W1 W5", "A33A3A", "FFFFFF", umbral_texto(10, False), None),
    ("C-21", "Borde de campo con error", "W1 W5", "A33A3A", "FFFFFF", NO_TEXTO, None),
    ("C-22", "Chip FALLA y RECHAZADO (9 pt negrita)", "W3 W6", "A33A3A", "FDECEC", umbral_texto(9, True), None),
    ("C-23", "Aviso de REVERTIDO sobre ámbar (10 pt)", "W5", "8A5A12", "FFF4E5", umbral_texto(10, False), None),
    ("C-24", "Enlaces: evidencia, «Política gate-2026.1» (10–10.5 pt)", "W3 W4", "3D4F6B", "FFFFFF", umbral_texto(10, False), None),
    ("C-25", "Texto de estado sobre ámbar / rosa / verde (10.5 pt)", "W1–W6", "2B2F33", "FFF4E5", umbral_texto(10.5, False), None),
    ("C-26", "Chips de módulos afectados (9 pt negrita)", "W2", "2B2F33", "E4E6EA", umbral_texto(9, True), None),
    ("C-27", "Barra de progreso: relleno contra el carril", "W3", "3D4F6B", "E4E6EA", NO_TEXTO, None),
    ("C-28", "Botón deshabilitado «Registrar escenario» / «Registrar despliegue»", "W1 W5", "9AA0A6", "E4E6EA", EXENTO, None),
]


def main() -> int:
    solo_propuestos = "--solo-propuestos" in sys.argv
    print(f"{'ID':5} {'Uso':92} {'Actual':>7} {'Mín.':>5} {'Estado':8} {'Propuesto':>10} {'Nuevo':>6} {'Estado':8}")
    print("-" * 148)
    fallan_hoy = 0
    fallan = 0
    for ident, uso, _pantallas, fg, bg, minimo, propuesta in PARES:
        if solo_propuestos and propuesta is None:
            continue
        actual = contraste(fg, bg)
        estado = "EXENTO" if minimo == EXENTO else ("CUMPLE" if actual >= minimo else "FALLA")
        fallan_hoy += estado == "FALLA"
        if propuesta:
            nuevo = contraste(propuesta, bg)
            nuevo_estado = "CUMPLE" if nuevo >= minimo else "FALLA"
            fallan += nuevo_estado == "FALLA"
            print(f"{ident:5} {uso:92} {actual:7.2f} {minimo:5.1f} {estado:8} {'#' + propuesta:>10} {nuevo:6.2f} {nuevo_estado:8}")
        else:
            print(f"{ident:5} {uso:92} {actual:7.2f} {minimo:5.1f} {estado:8} {'—':>10} {'':6} {'':8}")
    print("-" * 148)
    print(f"Pares que fallan con los colores actuales: {fallan_hoy} · Propuestas que no cumplen: {fallan}")
    return 1 if fallan else 0


if __name__ == "__main__":
    sys.exit(main())
