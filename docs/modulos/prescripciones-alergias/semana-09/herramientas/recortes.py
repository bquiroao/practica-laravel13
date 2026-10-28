"""Recortes marcados de los hallazgos de la semana 9 (Prescripciones) sobre los wireframes de la semana 8.

Uso (desde semana-09/):
    python herramientas/recortes.py ../semana-08/wireframes evidencia/hallazgos

Las coordenadas están en pulgadas del lienzo de 13,33 × 7,5 (las mismas del generador de
wireframes), así que cada marca apunta al elemento exacto que se evalúa.
"""
import sys
from pathlib import Path
from PIL import Image, ImageDraw

WF = Path(sys.argv[1])      # carpeta semana-08/wireframes
SALIDA = Path(sys.argv[2])  # carpeta semana-09/evidencia/hallazgos
PX = 144                    # 1920 px / 13,333 in
NOMBRES = {1: "preparar", 2: "verificacion-previa", 3: "justificar-confirmar", 4: "resultado-emision", 5: "consultar", 6: "anular-errores"}

# id, archivo de salida, pantalla, recorte (x, y, x2, y2 en pulgadas), marcas [(x, y, w, h)]
HALLAZGOS = [
    ("h-01", "emitir-deshabilitado-sin-motivo", 1, (0.35, 4.0, 8.95, 5.2), [(7.5, 4.7, 1.2, 0.34)]),
    ("h-02", "sesion-promete-borrador", 6, (4.6, 1.5, 8.95, 3.8), [(4.75, 2.7, 3.95, 0.95)]),
    ("h-03", "casilla-sin-alergias-pequena", 3, (4.6, 1.5, 8.95, 4.8), [(4.88, 2.88, 0.26, 0.26), (5.2, 2.84, 3.4, 0.6)]),
    ("h-04", "contraste-texto-secundario", 1, (0.35, 2.2, 8.95, 4.6), [(0.6, 2.73, 6.6, 0.28), (2.98, 2.34, 0.3, 0.3), (7.3, 3.0, 0.3, 0.79)]),
    ("h-05", "contraste-verdes", 5, (0.35, 1.5, 8.95, 2.45), [(3.0, 1.66, 1.0, 0.24)]),
    ("h-06", "contraste-bordes", 1, (0.35, 2.7, 8.95, 3.9), [(0.6, 3.0, 6.6, 0.34)]),
    ("h-07", "items-sin-etiqueta", 1, (0.35, 2.7, 8.95, 3.9), [(0.6, 2.73, 6.6, 0.28), (0.6, 3.45, 6.6, 0.34)]),
    ("h-08", "quitar-item-sin-nombre", 1, (0.35, 2.7, 8.95, 3.9), [(7.3, 3.0, 0.3, 0.34), (7.3, 3.45, 0.3, 0.34)]),
    ("h-09", "severidad-inconsistente", 2, (0.35, 1.95, 8.95, 6.4), [(6.1, 2.05, 2.6, 1.45), (0.75, 5.95, 7.8, 0.3)]),
    ("h-10", "incierto-sin-endpoint", 4, (0.35, 3.3, 8.95, 6.85), [(4.75, 3.35, 3.95, 1.55), (0.72, 5.55, 7.9, 0.2)]),
    ("h-11", "override-despues-de-escribir", 3, (0.35, 1.5, 4.7, 6.85), [(2.05, 5.1, 2.4, 0.34), (0.75, 5.6, 3.7, 1.1)]),
    ("h-12", "resultado-sin-anuncio", 2, (0.35, 1.5, 8.95, 5.2), [(0.6, 2.05, 8.1, 3.05)]),
    ("h-13", "error-mientras-escribe", 6, (0.35, 1.5, 4.7, 4.8), [(0.75, 3.7, 3.7, 0.28), (3.25, 4.15, 1.2, 0.34)]),
    ("h-14", "asterisco-sin-leyenda", 3, (0.35, 2.85, 4.7, 3.3), [(0.75, 2.95, 1.8, 0.26)]),
    ("h-15", "ayuda-solo-raton", 1, (0.35, 2.2, 4.8, 2.75), [(2.98, 2.34, 0.3, 0.3)]),
]

SALIDA.mkdir(parents=True, exist_ok=True)
for ident, nombre, w, recorte, marcas in HALLAZGOS:
    img = Image.open(WF / f"semana-08-w{w}-{NOMBRES[w]}.png").convert("RGB")
    dibujo = ImageDraw.Draw(img)
    for x, y, ancho, alto in marcas:
        dibujo.rectangle([x * PX, y * PX, (x + ancho) * PX, (y + alto) * PX], outline=(220, 30, 30), width=4)
    x1, y1, x2, y2 = (round(v * PX) for v in recorte)
    destino = SALIDA / f"{ident}-{nombre}.png"
    img.crop((x1, y1, x2, y2)).save(destino)
    print(destino.name, img.crop((x1, y1, x2, y2)).size)
