<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Proxy-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$BAKONG_TOKEN = getenv('BAKONG_TOKEN');
$PROXY_SECRET = getenv('PROXY_SECRET_KEY');

$incomingKey = $_SERVER['HTTP_X_PROXY_KEY'] ?? '';
if ($incomingKey !== $PROXY_SECRET) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$md5  = $body['md5'] ?? '';

if (!$md5) {
    http_response_code(422);
    echo json_encode(['error' => 'md5 is required']);
    exit;
}

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => 'https://api-bakong.nbc.gov.kh/v1/check_transaction_by_md5',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $BAKONG_TOKEN,
        'Accept: application/json',
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS     => json_encode(['md5' => $md5]),
    CURLOPT_TIMEOUT        => 15,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(503);
    echo json_encode([
        'error'  => 'Proxy could not reach Bakong API',
        'detail' => $curlError,
    ]);
    exit;
}

http_response_code($httpCode);
echo $response;
