<?php
function callExternalAuthApi(string $url, array $credentials): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($credentials),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);
    $result = curl_exec($ch);
    if ($result === false) {
        curl_close($ch);
        return ['status' => 'error', 'message' => 'Service unavailable, try again.'];
    }
    curl_close($ch);
    return json_decode($result, true) ?? ['status' => 'error', 'message' => 'Invalid API response.'];
}
