import cv2
import numpy as np
import pytesseract
import sys
import os

def debug(msg):
    print(f"[auto_calibrar.py DEBUG] {msg}", file=sys.stderr)

def buscar_barra_escala_por_ocr(img, crop_w=40, pasos=2):
    height, width = img.shape[:2]
    mejores_textos = []
    mejores_crop = None

    debug(f"Tamaño imagen: {width}x{height}")

    for x in range(190, 250, pasos):
        crop = img[:, x:x+crop_w]
        debug(f"Revisando crop en x={x}")

        # Recorta solo la franja inferior donde debería estar el 5
        low_crop = crop[int(height*0.75):, :]  # solo 25% inferior
        gray = cv2.cvtColor(low_crop, cv2.COLOR_BGR2GRAY)
        _, binarizada = cv2.threshold(gray, 160, 255, cv2.THRESH_BINARY_INV)
        kernel = np.ones((2,2), np.uint8)
        clean = cv2.morphologyEx(binarizada, cv2.MORPH_OPEN, kernel)

        config = '--psm 7 -c tessedit_char_whitelist=5'
        text5 = pytesseract.image_to_string(clean, config=config)
        debug(f"OCR bajo (x={x}): {text5.strip()}")

        # Ahora intentar todo el crop normal
        gray_full = cv2.cvtColor(crop, cv2.COLOR_BGR2GRAY)
        _, bin_full = cv2.threshold(gray_full, 160, 255, cv2.THRESH_BINARY_INV)
        config_full = '--psm 6 -c tessedit_char_whitelist=0123456789'
        data = pytesseract.image_to_data(bin_full, config=config_full, output_type=pytesseract.Output.DICT)
        textos = []
        for i in range(len(data['text'])):
            txt = data['text'][i].strip()
            if txt.isdigit():
                textos.append((int(txt), data['top'][i]))
        debug(f"Texto OCR completo (x={x}): {textos}")

        if len(textos) > len(mejores_textos):
            mejores_textos = textos
            mejores_crop = bin_full.copy()
        if len(textos) >= 2:
            tops = [y for v, y in textos]
            if max(tops) - min(tops) > height * 0.3:
                cv2.imwrite("recorte_barra_auto.png", bin_full)
                debug(f"Encontrado barra de escala en x={x}: {textos}")
                return crop, textos

    if mejores_crop is not None:
        cv2.imwrite("recorte_barra_debug.png", mejores_crop)
        debug("Guardado mejores_crop como recorte_barra_debug.png")
    debug(f"Mejores textos encontrados: {mejores_textos}")
    return crop, mejores_textos

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

    scale_bar, textos = buscar_barra_escala_por_ocr(img)

    print(f"Valores OCR detectados (valor, Y): {textos}", file=sys.stderr)
    if len(textos) < 2:
        debug("No se encontraron suficientes textos OCR")
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
            debug("Distancia cm entre textos es 0")
            print("ERROR")
            sys.exit(1)
        px_por_cm = abs(y1 - y0) / distancia_cm

    debug(f"pxPorCm calculado: {px_por_cm:.2f}")
    print(f"{px_por_cm:.2f}")
