"""Recorta y marca en rojo cada hallazgo de la semana 9 sobre el PNG de su wireframe (semana 8).

Uso:  python recortes.py <carpeta-con-w1..w5.png> <carpeta-de-salida>
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
    ("H-01", "boton-clasificar-sin-motivo", 4, [(7.2, 3.95, 1.5, 0.34)], "«Clasificar» deshabilitado: el motivo solo al pasar el cursor"),
    ("H-02", "contraste-etiquetas", 2, [(0.6, 4.0, 4.2, 0.26), (0.6, 5.02, 1.3, 0.24), (0.6, 1.92, 6.5, 0.26)], "Etiquetas y datos en #8A8F96: 3,26:1"),
    ("H-03", "contraste-bordes", 2, [(0.6, 3.55, 5.1, 0.34)], "Bordes de opciones y campos en #9AA0A6: 2,64:1"),
    ("H-04", "contraste-go-chips", 1, [(7.3, 2.45, 1.4, 0.32), (4.75, 3.29, 1.05, 0.24)], "«✓ GO» 4,25:1 y chip PLANIFICADA 3,99:1"),
    ("H-05", "resultado-sin-semantica", 2, [(0.6, 3.25, 5.1, 0.68)], "Cuatro botones sin semántica de grupo: ● es solo un dibujo"),
    ("H-06", "criticos-bloqueados-mudos", 1, [(0.75, 4.68, 4.1, 0.34)], "Críticos con candado: deshabilitados y sin explicación accesible"),
    ("H-07", "bandeja-sin-aviso", 3, [(0.6, 4.45, 8.1, 1.45)], "La bandeja reenvía sola sin anunciar el resultado"),
    ("H-08", "bandeja-contradice-proteccion", 3, [(0.75, 5.5, 7.8, 0.3)], "«Nada se pierde» vs «se perderán» y la evidencia clínica local"),
    ("H-09", "dialogo-cierre-foco", 5, [(0.6, 4.4, 4.0, 1.5)], "Diálogo irreversible sin foco inicial, Esc ni retorno definidos"),
    ("H-10", "decision-numero-ambiguo", 1, [(7.3, 2.85, 1.4, 0.32)], "«NO_GO · 2»: ¿2 qué?"),
    ("H-11", "error-antes-de-escribir", 4, [(4.75, 3.05, 3.95, 0.9)], "Error del motivo mostrado antes de que el usuario escriba"),
    ("H-12", "codigos-tecnicos", 5, [(0.6, 2.02, 8.1, 0.36), (2.6, 5.45, 1.85, 0.34)], "Códigos de sistema a la vista: NO_GO, NO APLICA"),
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
        lienzo = Image.new("RGB", (max(recorte.width, 900), recorte.height + 50), "white")
        lienzo.paste(recorte, (0, 50))
        ImageDraw.Draw(lienzo).text((10, 10), f"{ident} · W{w} · {titulo}", fill=(160, 20, 20), font=FUENTE)
        salida = destino / f"{ident.lower()}-{nombre}.png"
        lienzo.save(salida, optimize=True)
        print(salida.name, lienzo.size)
    return 0


if __name__ == "__main__":
    sys.exit(main())
