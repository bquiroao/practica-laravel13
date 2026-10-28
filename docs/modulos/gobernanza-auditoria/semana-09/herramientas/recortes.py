"""Recortes marcados de los hallazgos de la semana 9 (Auditoría) sobre los wireframes de la semana 8.

Uso (desde semana-09/):
    python herramientas/recortes.py ../semana-08/wireframes evidencia/hallazgos
"""
import sys
from pathlib import Path
from PIL import Image, ImageDraw

WF = Path(sys.argv[1])      # carpeta semana-08/wireframes
SALIDA = Path(sys.argv[2])  # carpeta semana-09/evidencia/hallazgos
PX = 150                    # 2000 px / 13.333 in
NOMBRES = {1: "buscar-filtros", 2: "resultados", 3: "detalle", 4: "consulta-red", 5: "verificar-integridad", 6: "errores-proteccion"}

# id, archivo de salida, pantalla, recorte (x, y, x2, y2 en pulgadas), marcas [(x, y, w, h)]
HALLAZGOS = [
    ("h-01", "radio-abre-dialogo", 4, (0.35, 1.5, 8.95, 7.0), [(4.75, 2.32, 2.4, 0.36), (1.6, 4.05, 6.1, 2.6)]),
    ("h-02", "sesion-promete-filtros", 6, (0.35, 1.5, 8.95, 3.3), [(0.6, 1.65, 2.6, 1.55)]),
    ("h-03", "ids-invisibles", 2, (0.35, 2.4, 8.95, 4.85), [(0.6, 2.5, 8.1, 0.32), (3.9, 3.09, 1.35, 0.24), (3.9, 3.59, 1.35, 0.24)]),
    ("h-04", "fila-clicable", 2, (0.35, 2.4, 8.95, 4.85), [(0.6, 2.82, 8.1, 0.5)]),
    ("h-05", "contraste-texto-secundario", 1, (0.35, 1.5, 8.95, 3.9), [(0.6, 2.35, 8.1, 0.26), (2.35, 3.2, 4.5, 0.26)]),
    ("h-06", "contraste-bordes", 5, (0.35, 1.95, 8.95, 3.5), [(0.58, 2.29, 3.44, 0.38), (4.18, 2.29, 1.84, 0.38)]),
    ("h-07", "verificar-deshabilitado", 5, (0.35, 1.95, 8.95, 3.5), [(7.08, 2.96, 1.64, 0.38), (6.13, 2.29, 2.59, 0.62)]),
    ("h-08", "alterada-sin-destino", 5, (0.35, 3.6, 8.95, 5.35), [(3.35, 3.7, 2.6, 1.55)]),
    ("h-09", "formato-de-fecha", 1, (0.35, 2.2, 8.95, 3.9), [(0.58, 2.59, 3.39, 0.38)]),
    ("h-10", "hora-sin-fecha", 2, (0.35, 1.95, 8.95, 4.85), [(0.6, 2.02, 6.3, 0.32), (0.6, 2.5, 1.05, 2.32)]),
    ("h-11", "codigos-tecnicos", 2, (0.35, 2.4, 8.95, 4.85), [(3.85, 2.82, 2.05, 2.0)]),
    ("h-12", "resultados-sin-anuncio", 2, (0.35, 4.7, 8.95, 5.3), [(0.58, 4.84, 5.17, 0.4)]),
    ("h-13", "limpiar-borra-rango", 1, (0.35, 2.2, 8.95, 3.95), [(5.73, 3.44, 1.24, 0.38), (0.58, 2.59, 3.39, 0.38)]),
    ("h-14", "asterisco-sin-leyenda", 1, (0.35, 2.2, 8.95, 3.0), [(0.58, 2.33, 3.39, 0.28)]),
    ("h-15", "ayuda-solo-raton", 1, (0.35, 1.5, 4.8, 2.0), [(3.81, 1.62, 0.34, 0.34)]),
]

SALIDA.mkdir(parents=True, exist_ok=True)
for ident, nombre, w, recorte, marcas in HALLAZGOS:
    img = Image.open(WF / f"semana-08-w{w}-{NOMBRES[w]}.png").convert("RGB")
    dibujo = ImageDraw.Draw(img)
    for x, y, ancho, alto in marcas:
        dibujo.rectangle([x * PX, y * PX, (x + ancho) * PX, (y + alto) * PX], outline=(220, 30, 30), width=5)
    x1, y1, x2, y2 = (round(v * PX) for v in recorte)
    destino = SALIDA / f"{ident}-{nombre}.png"
    img.crop((x1, y1, x2, y2)).save(destino)
    print(destino.name, img.crop((x1, y1, x2, y2)).size)
