<?php
$nombreArchivo = null;
$pxPorCm = null;
$ocrTextos = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['imagen'])) {
    $archivo = $_FILES['imagen'];
    $nombreTemp = $archivo['tmp_name'];
    $nombreArchivo = 'ecografia_' . time() . '_' . rand(1000,9999) . '.jpg';
    $rutaDestino = __DIR__ . '/uploads/' . $nombreArchivo;

    if (!is_dir(__DIR__ . '/uploads')) {
        mkdir(__DIR__ . '/uploads', 0777, true);
    }

    if (move_uploaded_file($nombreTemp, $rutaDestino)) {
        // Ejecuta el script Python y recibe el resultado (px por cm)
        $cmd = "python3 auto_calibrar.py " . escapeshellarg($rutaDestino) . " 2>&1";
        $output = shell_exec($cmd);
        // Busca los valores OCR en la salida del script
        if (preg_match('/Valores OCR detectados.*?\[(.*?)\]/s', $output, $match)) {
            $ocrTextos = '[' . $match[1] . ']';
        }
        if ($output !== null && trim($output) !== "" && trim($output) !== "ERROR") {
            // Busca el último número con decimales en la salida (el pxPorCm)
            if (preg_match('/(\d+\.\d+)\s*$/', $output, $match)) {
                $pxPorCm = floatval($match[1]);
            }
        } else {
            $resultado = "No se pudo calibrar automáticamente.";
        }
    } else {
        $resultado = "Error al subir la imagen.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Medición Automática PHP+Python</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .canvas-container { max-width: 1200px; }
        /* .badge-cm { font-size: 1.1rem; } */
        .barra-container { display: flex; align-items: flex-start; gap: 20px; }
    </style>
</head>
<body class="bg-light">
<div class="container py-5">
    <h2 class="mb-4">Medición automática (barra de escala detectada)</h2>
    <form action="" method="post" enctype="multipart/form-data" class="card p-4 shadow-sm mb-4">
        <div class="mb-3">
            <label for="imagen" class="form-label">Selecciona la imagen:</label>
            <input class="form-control" type="file" name="imagen" id="imagen" accept="image/*" required>
        </div>
        <button class="btn btn-primary" type="submit">Subir imagen</button>
    </form>
    <?php if ($nombreArchivo && $pxPorCm): ?>
        <div class="card p-4 shadow-sm mt-4">
            <h5 class="mb-3">Imagen de ecografía:</h5>
            <br><small class="text-muted">OCR: <?= htmlspecialchars($ocrTextos) ?></small>
            <div class="canvas-container mb-3">
                <canvas id="canvasMedicion" style="border:1px solid #333; max-width: 100%; background: #fff;" width="1200" height="1300"></canvas>
            </div>
            <button class="btn btn-success mt-3" id="btnGuardarImagen">Guardar imagen</button>
        </div>
    <?php elseif(!empty($resultado)): ?>
        <div class="alert alert-danger mt-4"><?= $resultado ?></div>
    <?php endif; ?>
</div>
<?php if ($nombreArchivo && $pxPorCm): ?>
<script>
const imgUrl = "uploads/<?= htmlspecialchars($nombreArchivo) ?>";
const canvas = document.getElementById('canvasMedicion');
const ctx = canvas.getContext('2d');
const img = new Image();

let drawing = false;
let start = {x:0, y:0};
let end = {x:0, y:0};

const pxPorCm = <?= floatval($pxPorCm) ?>;
let mediciones = []; // Array para almacenar todas las mediciones

img.onload = function() {
    canvas.width = img.width;
    canvas.height = img.height;
    redraw();
};
img.src = imgUrl;

// Dibuja la imagen y todas las líneas
function redraw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

    // Dibuja todas las mediciones guardadas
    mediciones.forEach((m, idx) => {
        dibujarLinea(m.start, m.end, idx+1, m.distanciaCm);
    });

    // Si el usuario está midiendo, dibujar la línea temporal
    if (drawing && (start.x !== end.x || start.y !== end.y)) {
        dibujarLinea(start, end, mediciones.length+1, calcularDistanciaCm(start, end));
    }
}

function dibujarCruz(ctx, x, y, color = "#FFD600", size = 16, lineW = 4) {
    ctx.save();
    ctx.strokeStyle = color;
    ctx.lineWidth = lineW;
    ctx.beginPath();
    ctx.moveTo(x - size/2, y);
    ctx.lineTo(x + size/2, y);
    ctx.moveTo(x, y - size/2);
    ctx.lineTo(x, y + size/2);
    ctx.stroke();
    ctx.restore();
}

function dibujarLinea(p1, p2, numero, distCm) {
    ctx.strokeStyle = "#FFD600";
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(p1.x, p1.y);
    ctx.lineTo(p2.x, p2.y);
    ctx.stroke();

    // Marca con cruz en vez de círculo
    dibujarCruz(ctx, p1.x, p1.y);
    dibujarCruz(ctx, p2.x, p2.y);

    // Escribir solo el número
    ctx.font = "bold 22px Arial";
    ctx.fillStyle = "#FFD600";
    ctx.fillText(numero, (p1.x + p2.x) / 2 + 8, (p1.y + p2.y) / 2 - 8);
}

function calcularDistanciaCm(a, b) {
    const dx = b.x - a.x;
    const dy = b.y - a.y;
    const distancia = Math.sqrt(dx*dx + dy*dy);
    return pxPorCm ? (distancia / pxPorCm) : 0;
}

function getMousePos(canvas, evt) {
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    return {
        x: (evt.clientX - rect.left) * scaleX,
        y: (evt.clientY - rect.top) * scaleY
    };
}

// Eventos para medir múltiples segmentos
canvas.addEventListener('mousedown', function(e) {
    drawing = true;
    const pos = getMousePos(canvas, e);
    start = { x: pos.x, y: pos.y };
    end = { ...start };
    redraw();
});

canvas.addEventListener('mousemove', function(e) {
    if (!drawing) return;
    end = getMousePos(canvas, e);
    redraw();
});

canvas.addEventListener('mouseup', function(e) {
    if (!drawing) return;
    drawing = false;
    end = getMousePos(canvas, e);

    // Guardar la medición
    if (start.x !== end.x || start.y !== end.y) {
        mediciones.push({
            start: {...start},
            end: {...end},
            distanciaCm: calcularDistanciaCm(start, end)
        });
    }

    redraw();
});

// Limpia las mediciones
function limpiarMediciones() {
    mediciones = [];
    redraw();
}

// Variable global para guardar el área del botón limpiar
let botonLimpiarRect = null;

function drawMedicionesTable() {
    const padding = 10;
    const rowHeight = 26;
    const totalRows = mediciones.length + 1;
    const col1w = 38;
    const col2w = 110;
    const tablaWidth = col1w + col2w + padding * 2;
    const tablaHeight = rowHeight * totalRows + padding * 2;
    const btnH = 32;
    const margin = 18;

    // Calcula espacio para tabla + botón
    const tablaY = canvas.height - tablaHeight - btnH - margin;
    const x = canvas.width - tablaWidth - margin;
    const y = tablaY;

    ctx.save();
    ctx.globalAlpha = 0.85;
    ctx.fillStyle = "#fff";
    ctx.fillRect(x, y, tablaWidth, tablaHeight);
    ctx.globalAlpha = 1;

    // Títulos
    ctx.font = "bold 16px Arial";
    ctx.fillStyle = "#222";
    ctx.fillText("#", x + padding, y + padding + 16);
    ctx.fillText("Distancia (cm)", x + padding + col1w, y + padding + 16);

    // Filas de mediciones
    ctx.font = "15px Arial";
    mediciones.forEach((m, idx) => {
        ctx.fillText((idx + 1), x + padding, y + padding + 16 + rowHeight * (idx + 1));
        ctx.fillText(m.distanciaCm.toFixed(2), x + padding + col1w, y + padding + 16 + rowHeight * (idx + 1));
    });

    // Botón limpiar debajo de la tabla
    const btnW = tablaWidth - 2 * padding;
    const btnX = x + padding;
    const btnY = y + tablaHeight + 6;

    ctx.fillStyle = "#ea5050";
    ctx.strokeStyle = "#fff";
    ctx.lineWidth = 2;
    ctx.globalAlpha = 0.95;
    ctx.fillRect(btnX, btnY, btnW, btnH);
    ctx.globalAlpha = 1;
    ctx.strokeRect(btnX, btnY, btnW, btnH);

    ctx.font = "bold 17px Arial";
    ctx.fillStyle = "#fff";
    ctx.textAlign = "center";
    ctx.fillText("Limpiar", btnX + btnW / 2, btnY + btnH / 2 + 7);

    ctx.textAlign = "start"; // Devuelve alineación a por defecto

    ctx.restore();

    // Guarda el área del botón para detectar clicks
    botonLimpiarRect = { x: btnX, y: btnY, w: btnW, h: btnH };
}



// Y al final de redraw():
function redraw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

    // Dibuja todas las mediciones guardadas
    mediciones.forEach((m, idx) => {
        dibujarLinea(m.start, m.end, idx+1, m.distanciaCm);
    });

    // Si el usuario está midiendo, dibujar la línea temporal
    if (drawing && (start.x !== end.x || start.y !== end.y)) {
        dibujarLinea(start, end, mediciones.length+1, calcularDistanciaCm(start, end));
    }

    // --- Aquí dibuja la tabla sobre la imagen ---
    drawMedicionesTable();
}

canvas.addEventListener('click', function(e) {
    if (!botonLimpiarRect) return;
    const rect = canvas.getBoundingClientRect();
    const mouseX = (e.clientX - rect.left) * (canvas.width / rect.width);
    const mouseY = (e.clientY - rect.top) * (canvas.height / rect.height);

    if (
        mouseX >= botonLimpiarRect.x && mouseX <= botonLimpiarRect.x + botonLimpiarRect.w &&
        mouseY >= botonLimpiarRect.y && mouseY <= botonLimpiarRect.y + botonLimpiarRect.h
    ) {
        // Llama a tu función de limpiar
        limpiarMediciones();
    }
});

document.getElementById('btnGuardarImagen').addEventListener('click', function() {
    // Convierte el canvas a una imagen PNG y la descarga
    const link = document.createElement('a');
    link.download = 'ecografia_medida.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
});

</script>

<?php endif; ?>
</body>
</html>
