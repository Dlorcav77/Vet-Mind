<?php
header('Content-Type: application/json');
require_once(dirname(__DIR__) . "../configP.php");

// 🔒 API Key AssemblyAI
$assemblyApiKey = $ASSEM_API_KEY ?? '';
if (!$assemblyApiKey) {
    echo json_encode(['status' => 'error', 'message' => 'API Key de AssemblyAI no configurada.']);
    exit;
}

$audioPath = '';

// 🔒 Sanitizar nombre de archivo
function sanitizeFilename($filename) {
    return preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $filename);
}

if (isset($_FILES['audio'])) {
    $tmpFile = $_FILES['audio']['tmp_name'];
    $audioName = sanitizeFilename(uniqid('uploaded_', true)) . '.webm';
    $audioPath = dirname(__DIR__) . '/uploads/grabaciones/' . $audioName;

    if (!move_uploaded_file($tmpFile, $audioPath)) {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar el archivo de audio subido']);
        exit;
    }
} elseif (isset($_POST['audio_filename'])) {
    $audioName = sanitizeFilename(basename($_POST['audio_filename']));
    $audioPath = dirname(__DIR__) . "/uploads/grabaciones/$audioName";
}

if (!file_exists($audioPath)) {
    echo json_encode(['status' => 'error', 'message' => 'Archivo de audio no encontrado']);
    exit;
}

// 📤 Subir audio a AssemblyAI
$uploadResponse = shell_exec("curl -s --request POST --url https://api.assemblyai.com/v2/upload --header 'authorization: $assemblyApiKey' --data-binary '@$audioPath'");

$uploadData = json_decode($uploadResponse, true);
if (!isset($uploadData['upload_url'])) {
    echo json_encode(['status' => 'error', 'message' => 'Error al subir audio a AssemblyAI.']);
    exit;
}
$uploadUrl = $uploadData['upload_url'];

// 🔥 Iniciar transcripción
$transcriptionRequest = [
    'audio_url' => $uploadUrl,
    'language_code' => 'es',
    'format_text' => true,
    'disfluencies' => false
];

$ch = curl_init('https://api.assemblyai.com/v2/transcript');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'authorization: ' . $assemblyApiKey,
    'content-type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($transcriptionRequest));
$transcriptionResponse = curl_exec($ch);
curl_close($ch);

$transcriptionData = json_decode($transcriptionResponse, true);
if (!isset($transcriptionData['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Error al iniciar transcripción en AssemblyAI.']);
    exit;
}
$transcriptionId = $transcriptionData['id'];

// 🕒 Polling para obtener resultado
$status = '';
$text = '';
for ($i = 0; $i < 20; $i++) { // Esperar hasta 20 segundos
    sleep(3);
    $ch = curl_init("https://api.assemblyai.com/v2/transcript/$transcriptionId");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'authorization: ' . $assemblyApiKey
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $statusResponse = curl_exec($ch);
    curl_close($ch);

    $statusData = json_decode($statusResponse, true);
    if ($statusData['status'] === 'completed') {
        $text = trim($statusData['text']);
        $text = quitarTildes($text);
        break;
    } elseif ($statusData['status'] === 'failed') {
        echo json_encode(['status' => 'error', 'message' => 'La transcripción falló en AssemblyAI.']);
        exit;
    }
}

if (!$text || strlen($text) < 5) {
    echo json_encode(['status' => 'error', 'message' => 'Texto transcrito vacío o inválido.']);
    exit;
}

// 🤖 Pasar texto a proceso_gpt.php
$ch = curl_init('http://localhost/funciones/proceso_gpt.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'texto'         => $text,
    'plantilla_base'=> $_POST['plantilla_base'] ?? '',
    'plantilla_id'  => $_POST['plantilla_id'] ?? '',
    'paciente'      => $_POST['paciente'] ?? '',
    'especie'       => $_POST['especie'] ?? '',
    'raza'          => $_POST['raza'] ?? '',
    'edad'          => $_POST['edad'] ?? '',
    'sexo'          => $_POST['sexo'] ?? '',
    'tipo_estudio'  => $_POST['tipo_estudio'] ?? '',
    'motivo'        => $_POST['motivo_examen'] ?? ''
]));
$responseGPT = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode(['status' => 'error', 'message' => 'Error cURL al llamar proceso_gpt: ' . curl_error($ch)]);
    exit;
}
curl_close($ch);

// ✅ Devolver respuesta de GPT
echo $responseGPT;


function quitarTildes($string) {
    $originales = ['á','é','í','ó','ú','Á','É','Í','Ó','Ú'];
    $sinTildes  = ['a','e','i','o','u','A','E','I','O','U'];
    return str_replace($originales, $sinTildes, $string);
}
