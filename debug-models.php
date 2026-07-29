<?php
// Debug script to list available models

require_once __DIR__ . '/includes/env.php';

$apiKey = getenv('GEMINI_API_KEY');

// Try to list models
$url = 'https://generativelanguage.googleapis.com/v1/models?key=' . $apiKey;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "<pre>";
echo "HTTP Code: " . $httpCode . "\n\n";

if ($curlError) {
    echo "CURL Error: " . $curlError . "\n";
} else {
    $data = json_decode($response, true);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
echo "</pre>";
?>
