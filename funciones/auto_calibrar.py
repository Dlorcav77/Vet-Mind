import cv2
import numpy as np
import pytesseract
import sys
import os

def debug(msg):
    print(f"[auto_calibrar.py DEBUG] {msg}", file=sys.stderr)

def escala_ocr_valida(textos, height):
    if len(textos) < 2:
        return False

    ordenados = sorted(textos, key=lambda x: x[1])
    valores = [v for v, _ in ordenados]
    span = abs(ordenados[-1][1] - ordenados[0][1])

    if len(ordenados) == 2:
        diferencia_valor = abs(valores[1] - valores[0])
        return 0 in valores and 1 <= diferencia_valor <= 10 and span > height * 0.3

    if min(valores) > 1:
        return False

    px_por_unidad = []

    for i in range(len(ordenados) - 1):
        v0, y0 = ordenados[i]
        v1, y1 = ordenados[i + 1]
        diferencia_valor = v1 - v0
        diferencia_px = abs(y1 - y0)

        if diferencia_valor <= 0 or diferencia_px == 0:
            return False

        px_por_unidad.append(diferencia_px / diferencia_valor)

    mediana = float(np.median(px_por_unidad))
    if mediana <= 0:
        return False

    desviacion_maxima = max(abs(valor - mediana) / mediana for valor in px_por_unidad)
    return span > height * 0.25 and desviacion_maxima <= 0.20

def extraer_mejor_escala(textos, height):
    if len(textos) < 2:
        return []

    ordenados = sorted(textos, key=lambda x: x[1])
    mejor = []

    for inicio in range(len(ordenados)):
        for fin in range(inicio + 2, len(ordenados) + 1):
            candidato = ordenados[inicio:fin]

            if not escala_ocr_valida(candidato, height):
                continue

            if not mejor:
                mejor = candidato
                continue

            score_actual = (
                len(candidato),
                abs(candidato[-1][0] - candidato[0][0]),
                abs(candidato[-1][1] - candidato[0][1])
            )
            score_mejor = (
                len(mejor),
                abs(mejor[-1][0] - mejor[0][0]),
                abs(mejor[-1][1] - mejor[0][1])
            )

            if score_actual > score_mejor:
                mejor = candidato

    return mejor

def buscar_barra_escala_por_ocr(img, crop_w=90):
    height, width = img.shape[:2]
    mejor_escala = []
    mejor_score = None
    mejor_x = None

    debug(f"Tamaño imagen: {width}x{height}")

    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    _, binaria = cv2.threshold(gray, 160, 255, cv2.THRESH_BINARY_INV)

    def crear_posiciones(inicio, fin, paso=45):
        limite = max(0, width - crop_w)
        inicio = max(0, min(int(inicio), limite))
        fin = max(0, min(int(fin), limite))

        if fin < inicio:
            return []

        posiciones = list(range(inicio, fin + 1, paso))
        if not posiciones or posiciones[-1] != fin:
            posiciones.append(fin)

        return posiciones

    def evaluar_posiciones(posiciones):
        nonlocal mejor_escala, mejor_score, mejor_x

        for x in dict.fromkeys(posiciones):
            bin_crop = binaria[:, x:x+crop_w]

            if bin_crop.shape[1] < crop_w:
                continue

            data = pytesseract.image_to_data(
                bin_crop,
                config='--psm 6 -c tessedit_char_whitelist=0123456789',
                output_type=pytesseract.Output.DICT
            )

            textos = []
            for i, txt in enumerate(data['text']):
                txt = txt.strip()
                if txt.isdigit():
                    textos.append((int(txt), data['top'][i]))

            candidato = extraer_mejor_escala(textos, height)
            if not candidato:
                continue

            score = (
                len(candidato),
                abs(candidato[-1][0] - candidato[0][0]),
                abs(candidato[-1][1] - candidato[0][1])
            )

            debug(f"Candidato x={x}: {candidato}")

            if mejor_score is None or score > mejor_score:
                mejor_score = score
                mejor_escala = candidato
                mejor_x = x

    limite_lateral = int(width * 0.35)

    posiciones_laterales = crear_posiciones(0, limite_lateral - crop_w)
    posiciones_laterales += crear_posiciones(width - limite_lateral, width - crop_w)
    evaluar_posiciones(posiciones_laterales)

    if mejor_x is None:
        evaluar_posiciones(crear_posiciones(limite_lateral - crop_w, width - limite_lateral))

    if mejor_x is not None:
        evaluar_posiciones(crear_posiciones(mejor_x - 45, mejor_x + 45, 10))

    if mejor_escala:
        debug(f"Escala seleccionada en x={mejor_x}: {mejor_escala}")
        return mejor_escala

    debug("No se encontró una escala confiable.")
    return []

def calcular_px_por_cm(textos):
    ordenados = sorted(textos, key=lambda x: x[1])
    distancias = []

    for i in range(len(ordenados) - 1):
        v0, y0 = ordenados[i]
        v1, y1 = ordenados[i + 1]

        if v1 - v0 == 1:
            distancias.append(abs(y1 - y0))

    if distancias:
        return sum(distancias) / len(distancias)

    v0, y0 = ordenados[0]
    v1, y1 = ordenados[-1]
    distancia_cm = abs(v1 - v0)

    if distancia_cm == 0:
        return 0

    return abs(y1 - y0) / distancia_cm

if __name__ == "__main__":
    if len(sys.argv) < 2:
        debug("Uso: python3 auto_calibrar.py imagen.jpg")
        sys.exit(1)

    img_path = sys.argv[1]
    debug(f"Ruta de imagen recibida: {img_path}")

    if not os.path.isfile(img_path):
        debug(f"Archivo no existe: {img_path}")
        print("ERROR")
        sys.exit(1)

    img = cv2.imread(img_path)
    if img is None:
        debug(f"No se pudo cargar la imagen: {img_path}")
        print("ERROR")
        sys.exit(1)

    textos = buscar_barra_escala_por_ocr(img)
    print(f"Valores OCR detectados (valor, Y): {textos}", file=sys.stderr)

    if len(textos) < 2:
        debug("No se encontraron suficientes textos OCR")
        print("ERROR")
        sys.exit(1)

    px_por_cm = calcular_px_por_cm(textos)

    if px_por_cm <= 0 or not np.isfinite(px_por_cm):
        debug("No se pudo calcular una escala válida")
        print("ERROR")
        sys.exit(1)

    debug(f"pxPorCm calculado: {px_por_cm:.2f}")
    print(f"{px_por_cm:.2f}")