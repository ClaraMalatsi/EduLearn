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

    // The handle closes itself when it goes out of scope. curl_close() is
    // deprecated on newer PHP versions and would print a notice mid-page.
    if ($result === false) {
        unset($ch);
        return ['status' => 'error', 'message' => 'Service unavailable, try again.'];
    }

    unset($ch);
    return json_decode($result, true) ?? ['status' => 'error', 'message' => 'Invalid API response.'];
}
