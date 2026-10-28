"""Mide el contraste WCAG 2.2 de los pares de color de los wireframes de Prescripciones (semana 8).

Uso:
    python contraste.py                    # tabla de los colores actuales y de los propuestos
    python contraste.py --solo-propuestos

Termina con código 1 si algún color PROPUESTO no cumple su umbral: los criterios de
aceptación del backlog (B-04, B-05, B-06) se verifican con un comando.
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
# Los colores salen tal cual del generador de wireframes de la semana 8 (wf-presc-base.js, G.*).
PARES = [
    ("C-01", "Etiquetas de campo y columnas (10 pt negrita)", "W1 W3 W6", "8A8F96", "FFFFFF", umbral_texto(10, True), "5F6670"),
    ("C-02", "Encabezados de tabla (10 pt negrita)", "W5", "8A8F96", "F2F3F5", umbral_texto(10, True), "5F6670"),
    ("C-03", "Pistas en cursiva (9.5 pt): quién verá, quién confirmó", "W3", "8A8F96", "FFFFFF", umbral_texto(9.5, False), "5F6670"),
    ("C-04", "Títulos de estado sobre gris (10 pt negrita)", "W1 W2 W5", "8A8F96", "F2F3F5", umbral_texto(10, True), "5F6670"),
    ("C-05", "Títulos de estado sobre ámbar (10 pt negrita)", "W1 W2 W3 W4 W6", "8A8F96", "FFF4E5", umbral_texto(10, True), "5F6670"),
    ("C-06", "Títulos de estado sobre rosa: BLOQUEO, 403 (10 pt negrita)", "W2 W3 W4 W5 W6", "8A8F96", "FDECEC", umbral_texto(10, True), "5F6670"),
    ("C-07", "Título de estado sobre verde: SIN HALLAZGOS (10 pt negrita)", "W2 W4", "8A8F96", "E7F4EC", umbral_texto(10, True), "5F6670"),
    ("C-08", "Íconos «?» y «✕ quitar ítem» (11 pt negrita)", "W1", "8A8F96", "FFFFFF", umbral_texto(11, True), "5F6670"),
    ("C-09", "Datos de cabecera de la prescripción (10.5 pt)", "W5", "8A8F96", "FFFFFF", umbral_texto(10.5, False), "5F6670"),
    ("C-10", "Chip «EMITIDA» (9 pt negrita)", "W5", "2E8B57", "E7F4EC", umbral_texto(9, True), "1F6B43"),
    ("C-11", "Contador válido «29 / mínimo 20 ✓» (9.5 pt)", "W3", "2E8B57", "FFFFFF", umbral_texto(9.5, False), "1F6B43"),
    ("C-12", "Borde de campo de texto, selector y área de texto", "W1 W3", "9AA0A6", "FFFFFF", NO_TEXTO, "7D838A"),
    ("C-13", "Borde de la casilla «sin alergias conocidas»", "W3", "9AA0A6", "FFFFFF", NO_TEXTO, "7D838A"),
    ("C-14", "Texto general (10.5–11 pt)", "Todas", "2B2F33", "FFFFFF", umbral_texto(10.5, False), None),
    ("C-15", "Cabecera: hospital y usuario (12 pt negrita)", "Todas", "FFFFFF", "3D4F6B", umbral_texto(12, True), None),
    ("C-16", "Botón primario «Verificar alergias» (11 pt negrita)", "W1 W3", "FFFFFF", "3D4F6B", umbral_texto(11, True), None),
    ("C-17", "Alergias activas en la cabecera del paciente (9.5 pt negrita)", "W1", "A33A3A", "FDECEC", umbral_texto(9.5, True), None),
    ("C-18", "Mensaje M-03 de dosis (10 pt)", "W1", "A33A3A", "FDECEC", umbral_texto(10, False), None),
    ("C-19", "Error junto al campo (9.5 pt)", "W1 W6", "A33A3A", "FFFFFF", umbral_texto(9.5, False), None),
    ("C-20", "Borde de campo con error", "W1 W6", "A33A3A", "FFFFFF", NO_TEXTO, None),
    ("C-21", "Hallazgo en el diálogo de advertencia (10 pt)", "W3 W5", "7A4B00", "FFF4E5", umbral_texto(10, False), None),
    ("C-22", "Chip «CON ADVERTENCIA JUSTIFICADA» (9 pt negrita)", "W5", "7A4B00", "FFF4E5", umbral_texto(9, True), None),
    ("C-23", "Texto de estado sobre verde / ámbar / rosa (10.5 pt)", "W2 W4 W5 W6", "2B2F33", "FDECEC", umbral_texto(10.5, False), None),
    ("C-24", "Título «Anular prescripción #123» (14 pt negrita)", "W6", "A33A3A", "FFFFFF", umbral_texto(14, True), None),
    ("C-25", "Enlace «+ Agregar medicamento» (10.5 pt negrita)", "W1", "3D4F6B", "FFFFFF", umbral_texto(10.5, True), None),
    ("C-26", "Botón deshabilitado «Emitir»", "W1 W3", "9AA0A6", "E4E6EA", EXENTO, None),
    ("C-27", "Botón deshabilitado «Anular»", "W6", "FFFFFF", "EBC9C9", EXENTO, None),
]


def main() -> int:
    solo_propuestos = "--solo-propuestos" in sys.argv
    print(f"{'ID':5} {'Uso':60} {'Actual':>7} {'Mín.':>5} {'Estado':8} {'Propuesto':>10} {'Nuevo':>6} {'Estado':8}")
    print("-" * 116)
    fallan = 0
    for ident, uso, _pantallas, fg, bg, minimo, propuesta in PARES:
        if solo_propuestos and propuesta is None:
            continue
        actual = contraste(fg, bg)
        estado = "EXENTO" if minimo == EXENTO else ("CUMPLE" if actual >= minimo else "FALLA")
        if propuesta:
            nuevo = contraste(propuesta, bg)
            nuevo_estado = "CUMPLE" if nuevo >= minimo else "FALLA"
            fallan += nuevo_estado == "FALLA"
            print(f"{ident:5} {uso:60} {actual:7.2f} {minimo:5.1f} {estado:8} {'#' + propuesta:>10} {nuevo:6.2f} {nuevo_estado:8}")
        else:
            print(f"{ident:5} {uso:60} {actual:7.2f} {minimo:5.1f} {estado:8} {'—':>10} {'':6} {'':8}")
    print("-" * 116)
    print(f"Propuestas que no cumplen: {fallan}")
    return 1 if fallan else 0


if __name__ == "__main__":
    sys.exit(main())
