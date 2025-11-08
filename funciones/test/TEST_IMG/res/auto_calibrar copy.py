import cv2
import numpy as np
import pytesseract
import sys

def buscar_barra_escala_por_ocr(img, crop_w=100, pasos=10):
    height, width = img.shape[:2]
    for x in range(0, 400, pasos):
        crop = img[:, x:x+crop_w]
        gray = cv2.cvtColor(crop, cv2.COLOR_BGR2GRAY)
        inv = 255 - gray
        clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8,8))
        contrasted = clahe.apply(inv)
        _, binarizada = cv2.threshold(contrasted, 180, 255, cv2.THRESH_BINARY)
        config = '--psm 6 -c tessedit_char_whitelist=0123456789'
        data = pytesseract.image_to_data(binarizada, config=config, output_type=pytesseract.Output.DICT)
        textos = []
        for i in range(len(data['text'])):
            txt = data['text'][i].strip()
            if txt.isdigit():
                textos.append((int(txt), data['top'][i]))
        if len(textos) >= 2:
            tops = [y for v, y in textos]
            if max(tops) - min(tops) > height * 0.3:
                cv2.imwrite("recorte_barra_auto.png", crop)
                return crop, textos
    # Si no encuentra, retorna crop vacío y lista vacía
    cv2.imwrite("recorte_barra_debug.png", crop)  # así ves el último recorte aunque no encontró
    return np.zeros((height, crop_w, 3), dtype=np.uint8), []

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Uso: python3 auto_calibrar.py imagen.jpg")
        sys.exit(1)

    img_path = sys.argv[1]
    img = cv2.imread(img_path)

    scale_bar, textos = buscar_barra_escala_por_ocr(img)

    print(f"Valores OCR detectados (valor, Y): {textos}", file=sys.stderr)
    if len(textos) < 2:
        print("ERROR")
        sys.exit(1)

    textos_ordenados = sorted(textos, key=lambda x: x[1])
    distancias = []
    for i in range(len(textos_ordenados)-1):
        v0, y0 = textos_ordenados[i]
        v1, y1 = textos_ordenados[i+1]
        if v1 - v0 == 1:  # Solo pares consecutivos
            distancias.append(abs(y1 - y0))
    if distancias:
        px_por_cm = sum(distancias) / len(distancias)
    else:
        (v0, y0), (v1, y1) = textos_ordenados[0], textos_ordenados[-1]
        distancia_cm = abs(v1 - v0)
        if distancia_cm == 0:
            print("ERROR")
            sys.exit(1)
        px_por_cm = abs(y1 - y0) / distancia_cm

    print(f"{px_por_cm:.2f}")
