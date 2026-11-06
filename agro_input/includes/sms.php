<?php
function send_sms(string $phone, string $message): bool {
    $username = trim((string)getenv('AT_USERNAME'));
    $apiKey = trim((string)getenv('AT_API_KEY'));
    $senderId = trim((string)getenv('AT_SENDER_ID'));

    if ($username === '' || $apiKey === '') {
        error_log('SMS not sent: Africa\'s Talking credentials are not configured.');
        return false;
    }
    if (!function_exists('curl_init')) {
        error_log('SMS not sent: PHP cURL is not enabled.');
        return false;
    }

    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (str_starts_with($digits, '00')) {
        $digits = substr($digits, 2);
    }
    if (str_starts_with($digits, '0')) {
        $digits = '256' . substr($digits, 1);
    } elseif (!str_starts_with($digits, '256') && strlen($digits) === 9) {
        $digits = '256' . $digits;
    }
    if (!preg_match('/^[1-9]\d{7,14}$/', $digits)) {
        error_log('SMS not sent: customer phone number is not a valid international number.');
        return false;
    }

    $payload = [
        'username' => $username,
        'to' => '+' . $digits,
        'message' => $message,
    ];
    if ($senderId !== '') {
        $payload['from'] = $senderId;
    }

    $curl = curl_init('https://api.africastalking.com/version1/messaging');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => [
            'apiKey: ' . $apiKey,
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ],
    ]);
    $response = curl_exec($curl);
    $httpStatus = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);

    if ($response === false || $httpStatus < 200 || $httpStatus >= 300) {
        error_log('SMS delivery failed (HTTP ' . $httpStatus . '): ' . ($curlError ?: 'provider request failed.'));
        return false;
    }

    $result = json_decode($response, true);
    $recipient = $result['SMSMessageData']['Recipients'][0] ?? null;
    if (!is_array($recipient) || (int)($recipient['statusCode'] ?? 0) !== 101) {
        $status = is_array($recipient) ? (string)($recipient['status'] ?? 'unknown') : 'unknown';
        error_log('SMS delivery failed: provider recipient status ' . $status . '.');
        return false;
    }

    return true;
}