<?php
/**
 * Daily Motivation - external REST API integration.
 *
 * Reads a motivational quote from a free public API and returns it to the
 * dashboards as JSON. No API key is needed for either service.
 *
 *   Primary  : https://zenquotes.io/api/random
 *   Fallback : https://dummyjson.com/quotes/random
 *
 * The page calls this file instead of calling the API from the browser, which
 * keeps the request free of cross-origin problems and lets the answer be
 * cached. The result is cached for an hour so a busy dashboard does not send
 * one request per page view.
 *
 * Response shape:
 *   {"status":"success","quote":"...","author":"...","source":"ZenQuotes","cached":false}
 *   {"status":"error","message":"..."}
 */

header('Content-Type: application/json');
header('Cache-Control: no-store');

$cacheFile = sys_get_temp_dir() . '/edulearn_quote_cache.json';
$cacheSeconds = 3600;

// Serve the cached quote while it is still fresh.
if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $cacheSeconds) {
    $cached = json_decode((string)file_get_contents($cacheFile), true);

    if (is_array($cached) && !empty($cached['quote'])) {
        $cached['cached'] = true;
        echo json_encode($cached);
        exit;
    }
}

/**
 * Small helper: fetch a URL and return the body, or null on failure.
 * Uses cURL when available and falls back to file_get_contents, so the
 * feature also works on a XAMPP install where cURL is switched off.
 */
function fetchUrl(string $url): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'EduLearn LMS'
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // No curl_close(): the handle closes itself, and calling it is
        // deprecated on newer PHP versions, which would print a notice into
        // the middle of this JSON response.
        unset($ch);

        return ($body !== false && $status === 200) ? (string)$body : null;
    }

    $context = stream_context_create(['http' => ['timeout' => 6, 'user_agent' => 'EduLearn LMS']]);
    $body = @file_get_contents($url, false, $context);

    return $body !== false ? (string)$body : null;
}

$quote = null;

// Primary service: ZenQuotes returns [{"q":"...","a":"..."}]
$body = fetchUrl('https://zenquotes.io/api/random');

if ($body !== null) {
    $data = json_decode($body, true);

    if (isset($data[0]['q'], $data[0]['a'])) {
        $quote = [
            'status' => 'success',
            'quote' => trim($data[0]['q']),
            'author' => trim($data[0]['a']),
            'source' => 'ZenQuotes'
        ];
    }
}

// Fallback service: DummyJSON returns {"quote":"...","author":"..."}
if ($quote === null) {
    $body = fetchUrl('https://dummyjson.com/quotes/random');

    if ($body !== null) {
        $data = json_decode($body, true);

        if (isset($data['quote'], $data['author'])) {
            $quote = [
                'status' => 'success',
                'quote' => trim($data['quote']),
                'author' => trim($data['author']),
                'source' => 'DummyJSON'
            ];
        }
    }
}

// Both services unreachable: say so plainly instead of breaking the page.
if ($quote === null) {
    echo json_encode([
        'status' => 'error',
        'message' => 'The quote service is unavailable right now.'
    ]);
    exit;
}

@file_put_contents($cacheFile, json_encode($quote));

$quote['cached'] = false;
echo json_encode($quote);
