"""Recorta y marca en rojo cada hallazgo de la semana 9 sobre el PNG de su wireframe (semana 8).

Uso:  python recortes.py <carpeta-con-w1..w6.png> <carpeta-de-salida>
Los PNG son las diapositivas exportadas a 1600 x 900 px (13,333 x 7,5 pulgadas → 120 px por pulgada); las cajas se
dan en pulgadas, las mismas coordenadas del generador de wireframes.
"""
import sys
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

PX = 120
FUENTE = ImageFont.truetype("C:/Windows/Fonts/calibrib.ttf", 26)

# (id, nombre de archivo, wireframe, cajas en pulgadas (x, y, w, h), título)
HALLAZGOS = [
    ("H-01", "boton-registrar-deshabilitado", 5, [(6.45, 5.5, 2.1, 0.34), (0.75, 4.11, 5.2, 0.34)], "«Registrar despliegue» deshabilitado: el teclado no llega y no dice por qué"),
    ("H-02", "contraste-texto-secundario", 2, [(0.6, 1.98, 7.5, 0.28), (0.6, 2.72, 8.1, 0.32), (5.1, 3.07, 1.5, 1.8)], "Texto secundario en #8A8F96: 2,93–3,26:1"),
    ("H-03", "contraste-placeholder-bordes", 1, [(3.2, 1.64, 2.6, 0.34), (2.45, 3.98, 3.1, 0.34)], "Placeholder 2,51:1 y bordes de campo 2,64:1"),
    ("H-04", "contraste-chips-estado", 3, [(5.9, 1.67, 1.0, 0.24), (4.25, 3.04, 0.75, 0.24), (4.25, 3.76, 0.95, 0.24)], "Chips EN CURSO, PASA e INESTABLE: 3,75–3,99:1"),
    ("H-05", "refresco-automatico", 3, [(0.6, 2.08, 8.1, 0.48), (0.6, 2.65, 8.1, 2.5)], "Se refresca cada 15 s: sin pausa y sin anunciar qué cambió"),
    ("H-06", "iconos-copiar", 5, [(3.38, 2.48, 0.25, 0.25)], "Icono de copiar: sin nombre accesible y de ~14 px (< 24 px)"),
    ("H-07", "digest-abreviado", 5, [(0.75, 2.44, 4.0, 0.3), (0.75, 4.11, 5.2, 0.34)], "Digest abreviado con «…»: no se puede comparar a ojo"),
    ("H-08", "vencimiento-durante-instalacion", 5, [(2.65, 1.67, 2.0, 0.24), (6.1, 6.1, 2.6, 0.85)], "La aprobación vence sin aviso previo; ¿se conserva lo cargado?"),
    ("H-09", "vista-previa-vs-decision", 4, [(0.6, 4.35, 8.1, 0.72), (6.45, 5.2, 2.1, 0.34)], "El botón nombra una decisión que no elige el usuario ni garantiza el servidor"),
    ("H-10", "datos-invalidos-sin-mensaje", 1, [(0.75, 3.98, 1.5, 0.34), (0.75, 4.32, 1.95, 0.48)], "El servidor responde 422 DATOS_INVALIDOS, que el catálogo no tiene"),
    ("H-11", "iconos-sin-texto", 4, [(6.8, 2.45, 1.9, 1.8)], "La marca de «cumple» no tiene texto; la de falla dice «no cumple»"),
    ("H-12", "revertido-sin-pantalla", 6, [(0.6, 4.63, 8.1, 0.62)], "El aviso de REVERTIDO al responsable de release no tiene pantalla"),
    ("H-13", "codigos-tecnicos", 6, [(3.35, 6.1, 2.6, 0.85)], "Códigos de sistema a la vista: TENANT_AJENO, EN_CURSO, e2e.consultar"),
    ("H-14", "enlaces-ambiguos", 3, [(6.3, 3.0, 2.4, 1.45), (0.75, 5.27, 7.8, 0.32)], "Enlaces «log» y «[Descargar]» repetidos, sin decir de qué escenario"),
]


def main() -> int:
    origen, destino = Path(sys.argv[1]), Path(sys.argv[2])
    destino.mkdir(parents=True, exist_ok=True)
    for ident, nombre, w, cajas, titulo in HALLAZGOS:
        im = Image.open(origen / f"w{w}.png").convert("RGB")
        d = ImageDraw.Draw(im)
        for x, y, ancho, alto in cajas:
            d.rectangle([x * PX - 6, y * PX - 6, (x + ancho) * PX + 6, (y + alto) * PX + 6], outline=(220, 30, 30), width=5)
        x0 = max(0, min(c[0] for c in cajas) * PX - 60)
        y0 = max(0, min(c[1] for c in cajas) * PX - 90)
        x1 = min(im.width, max((c[0] + c[2]) for c in cajas) * PX + 60)
        y1 = min(im.height, max((c[1] + c[3]) for c in cajas) * PX + 60)
        recorte = im.crop((int(x0), int(y0), int(x1), int(y1)))
        lienzo = Image.new("RGB", (max(recorte.width, 1100), recorte.height + 50), "white")
        lienzo.paste(recorte, (0, 50))
        ImageDraw.Draw(lienzo).text((10, 10), f"{ident} · W{w} · {titulo}", fill=(160, 20, 20), font=FUENTE)
        salida = destino / f"{ident.lower()}-{nombre}.png"
        lienzo.save(salida, optimize=True)
        print(salida.name, lienzo.size)
    return 0


if __name__ == "__main__":
    sys.exit(main())
