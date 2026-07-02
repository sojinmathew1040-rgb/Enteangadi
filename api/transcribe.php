<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!defined('GROQ_API_KEY') || empty(GROQ_API_KEY)) {
    echo json_encode(['success' => false, 'error' => 'API_KEY_NOT_CONFIGURED', 'message' => 'Please configure GROQ_API_KEY inside config.php']);
    exit;
}

if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'NO_AUDIO_FILE', 'message' => 'No valid audio file uploaded.']);
    exit;
}

$audioPath = $_FILES['audio']['tmp_name'];
$mimeType = $_FILES['audio']['type'] ?: 'audio/wav';

// Groq Whisper API endpoint
$url = 'https://api.groq.com/openai/v1/audio/transcriptions';

// Prepare CURL file
$cfile = new CURLFile($audioPath, $mimeType, basename($_FILES['audio']['name']));

$fields = [
    'file' => $cfile,
    'model' => 'whisper-large-v3-turbo'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . GROQ_API_KEY
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Bypass local certificate validation errors

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    $error_msg = curl_error($ch);
    echo json_encode(['success' => false, 'error' => 'CURL_ERROR', 'message' => $error_msg]);
    curl_close($ch);
    exit;
}

curl_close($ch);

$resData = json_decode($response, true);

if ($httpCode === 200 && isset($resData['text'])) {
    echo json_encode(['success' => true, 'transcript' => $resData['text']]);
} else {
    echo json_encode([
        'success' => false, 
        'error' => 'TRANSCRIPTION_FAILED', 
        'message' => $resData['error']['message'] ?? 'Unable to transcribe audio.'
    ]);
}
?>
