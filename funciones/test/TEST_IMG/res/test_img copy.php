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
        .badge-cm { font-size: 1.1rem; }
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
    <!-- <div class="barra-container">
        <?php if ($nombreArchivo): ?>
            <div class="mb-3">
                <label>Imagen original:</label><br>
                <img src="uploads/<?= htmlspecialchars($nombreArchivo) ?>" style="max-width:400px;">
            </div>
        <?php endif; ?>

        <?php if (file_exists('recorte_barra_auto.png')): ?>
            <div class="mb-3">
                <label>Barra detectada para calibración:</label><br>
                <img src="recorte_barra_auto.png?<?= time() ?>" class="img-thumbnail" style="max-width:60px; max-height:400px;">
                <?php if (!empty($ocrTextos)): ?>
                    <br><small class="text-muted">OCR: <?= htmlspecialchars($ocrTextos) ?></small>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div> -->
    <?php if ($nombreArchivo && $pxPorCm): ?>
        <div class="card p-4 shadow-sm mt-4">
            <h5 class="mb-3">Imagen de ecografía:</h5>
            <div class="canvas-container mb-3">
                <canvas id="canvasMedicion" style="border:1px solid #333; max-width: 100%; background: #fff;" width="1200" height="1300"></canvas>
            </div>
            <div class="alert alert-success">
                <b>Calibración automática:</b><br>
                <b>Relación:</b> <?= round($pxPorCm,2) ?> px por cm<br>
                Ahora dibuja sobre la imagen y obtendrás la distancia en <b>cm</b>.
            </div>
            <span class="badge bg-info text-dark badge-cm" id="distanciaCm">Distancia: 0 cm</span>
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

img.onload = function() {
    canvas.width = img.width;
    canvas.height = img.height;
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
};
img.src = imgUrl;

function redraw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
    if (start.x !== end.x || start.y !== end.y) {
        ctx.strokeStyle = "#FF0000";
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(start.x, start.y);
        ctx.lineTo(end.x, end.y);
        ctx.stroke();
        ctx.fillStyle = "#FF0000";
        ctx.beginPath();
        ctx.arc(start.x, start.y, 4, 0, 2*Math.PI);
        ctx.arc(end.x, end.y, 4, 0, 2*Math.PI);
        ctx.fill();
    }
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

canvas.addEventListener('mousedown', function(e) {
    drawing = true;
    const pos = getMousePos(canvas, e);
    start = { x: pos.x, y: pos.y };
    end = { ...start };
    redraw();
    document.getElementById('distanciaCm').textContent = "Distancia: 0 cm";
});

canvas.addEventListener('mousemove', function(e) {
    if (!drawing) return;
    const pos = getMousePos(canvas, e);
    end = { x: pos.x, y: pos.y };
    redraw();
    const dx = end.x - start.x;
    const dy = end.y - start.y;
    const distancia = Math.sqrt(dx*dx + dy*dy);
    let distCm = pxPorCm ? (distancia / pxPorCm) : 0;
    document.getElementById('distanciaCm').textContent = "Distancia: " + distCm.toFixed(2) + " cm";
});

canvas.addEventListener('mouseup', function(e) {
    drawing = false;
    redraw();
    const dx = end.x - start.x;
    const dy = end.y - start.y;
    const distancia = Math.sqrt(dx*dx + dy*dy);
    let distCm = pxPorCm ? (distancia / pxPorCm) : 0;
    document.getElementById('distanciaCm').textContent = "Distancia: " + distCm.toFixed(2) + " cm";
});

</script>
<?php endif; ?>
</body>
</html>
