"""Mide el contraste WCAG 2.2 de los pares de color de los wireframes RBAC (semana 8).

Uso:
    python contraste.py            # tabla de los colores actuales y de los propuestos
    python contraste.py --solo-propuestos

Termina con código 1 si algún color PROPUESTO no cumple su umbral.
Así el criterio de aceptación del backlog se puede verificar con un comando.
"""
import sys

# Umbrales WCAG 2.2 nivel AA
TEXTO_NORMAL = 4.5   # 1.4.3, texto < 18 pt (o < 14 pt en negrita)
TEXTO_GRANDE = 3.0   # 1.4.3, texto >= 18 pt (o >= 14 pt en negrita)
NO_TEXTO = 3.0       # 1.4.11, bordes de campos e indicadores de estado
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
# Los colores salen tal cual del generador de wireframes de la semana 8.
PARES = [
    ("C-01", "Etiquetas de campo (10 pt negrita)", "W1 W2 W3 W6", "8A8F96", "FFFFFF", umbral_texto(10, True), "5F6670"),
    ("C-02", "Encabezados de tabla (10.5 pt negrita)", "W1", "8A8F96", "F2F3F5", umbral_texto(10.5, True), "5F6670"),
    ("C-03", "Pistas y texto secundario (10-10.5 pt)", "W2 W5 W6", "8A8F96", "FFFFFF", umbral_texto(10, False), "5F6670"),
    ("C-04", "Títulos de estado VACÍO/CARGA (10 pt negrita)", "W1 W4 W5 W6", "8A8F96", "F2F3F5", umbral_texto(10, True), "5F6670"),
    ("C-05", "Ícono de ayuda «?» (11 pt negrita)", "W1", "8A8F96", "FFFFFF", umbral_texto(11, True), "5F6670"),
    ("C-06", "Borde de campo de texto", "W1 W2 W6", "9AA0A6", "FFFFFF", NO_TEXTO, "7D838A"),
    ("C-07", "Borde de casilla de verificación", "W3", "9AA0A6", "FFFFFF", NO_TEXTO, "7D838A"),
    ("C-08", "«Copiado ✓» (9.5 pt)", "W5", "2E8B57", "FFFFFF", umbral_texto(9.5, False), "1F6B3A"),
    ("C-09", "Indicador de pestaña activa (hoy: relleno)", "W4", "FFFFFF", "F2F3F5", NO_TEXTO, "3D4F6B"),
    ("C-10", "Texto general (11 pt)", "Todas", "2B2F33", "FFFFFF", umbral_texto(11, False), None),
    ("C-11", "Cabecera: hospital activo (12 pt negrita)", "Todas", "FFFFFF", "3D4F6B", umbral_texto(12, True), None),
    ("C-12", "Botón primario (11 pt negrita)", "W1 W2 W4 W5 W6", "FFFFFF", "3D4F6B", umbral_texto(11, True), None),
    ("C-13", "Mensaje de error junto al campo (10 pt)", "W2 W3", "A33A3A", "FFFFFF", umbral_texto(10, False), None),
    ("C-14", "Borde de campo con error", "W2 W3", "A33A3A", "FFFFFF", NO_TEXTO, None),
    ("C-15", "Título del aviso 403 (13 pt negrita)", "W4", "A33A3A", "FDECEC", umbral_texto(13, True), None),
    ("C-16", "Ayuda del aviso 403 (11 pt)", "W4", "6B2B2B", "FDECEC", umbral_texto(11, False), None),
    ("C-17", "Etiqueta ACCESO_DENEGADO (9.5 pt negrita)", "W6", "A33A3A", "FDECEC", umbral_texto(9.5, True), None),
    ("C-18", "Aviso de éxito (11.5 pt negrita)", "W2", "1F6B3A", "E7F4EC", umbral_texto(11.5, True), None),
    ("C-19", "Anillo de foco (2 pt)", "W3", "3D4F6B", "FFFFFF", NO_TEXTO, None),
    ("C-20", "Botón «Revocar rol» deshabilitado", "W3", "FFFFFF", "EBC9C9", EXENTO, None),
    ("C-21", "Botón deshabilitado genérico", "W2", "9AA0A6", "E4E6EA", EXENTO, None),
]


def main() -> int:
    solo_propuestos = "--solo-propuestos" in sys.argv
    fallas_propuestas = 0
    print(f"{'ID':5} {'Uso':46} {'Actual':>7} {'Mín.':>5} {'Estado':8} {'Propuesto':>9} {'Nuevo':>6} {'Estado':8}")
    print("-" * 104)
    for ident, uso, _pantallas, fg, bg, minimo, propuesta in PARES:
        if solo_propuestos and propuesta is None:
            continue
        actual = contraste(fg, bg)
        if minimo == EXENTO:
            estado = "EXENTO"
        else:
            estado = "CUMPLE" if actual >= minimo else "FALLA"
        if propuesta:
            nuevo = contraste(propuesta, bg)
            estado_nuevo = "CUMPLE" if nuevo >= minimo else "FALLA"
            fallas_propuestas += estado_nuevo == "FALLA"
            columnas = f"{'#' + propuesta:>9} {nuevo:6.2f} {estado_nuevo:8}"
        else:
            columnas = f"{'—':>9} {'':6} {'':8}"
        print(f"{ident:5} {uso:46} {actual:7.2f} {minimo:5.1f} {estado:8} {columnas}")
    print("-" * 104)
    print("Propuestas que no cumplen:", fallas_propuestas)
    return 1 if fallas_propuestas else 0


if __name__ == "__main__":
    sys.exit(main())
