<?php

function ksers_paygate_script() {
    return 'https://api.paygate.net/ajax/common/OpenPayAPI.js';
}

function ksers_paygate_profile($method) {
    if ($method === 'card_kr') {
        return [
            'mid' => ksers_env('KSERS_PAYGATE_MID_KR'),
            'paymethod' => '103',
            'currency' => 'WON',
            'langcode' => 'KR',
        ];
    }
    if ($method === 'card_os') {
        return [
            'mid' => ksers_env('KSERS_PAYGATE_MID_OS'),
            'paymethod' => '104',
            'currency' => 'USD',
            'langcode' => 'US',
        ];
    }
    return null;
}

function ksers_paygate_ready($method) {
    $profile = ksers_paygate_profile($method);
    return $profile && $profile['mid'] !== '' && ksers_env('KSERS_PAYGATE_HASH') !== '';
}

function ksers_paygate_price($amount) {
    $amount = str_replace([',', ' ', '원'], '', trim((string)$amount));
    $amount = preg_replace('/(KRW|WON|USD)$/i', '', $amount);
    if ($amount === null) {
        return '';
    }
    if ($amount === '' || !is_numeric($amount)) {
        return '';
    }
    if (strpos($amount, '.') !== false) {
        $amount = rtrim(rtrim($amount, '0'), '.');
        if ($amount === '' || $amount === '-') {
            return '0';
        }
    }
    return $amount;
}

function ksers_paygate_hash($replycode, $tid, $serial, $unitprice, $currency) {
    $hashCurrency = strtoupper(trim((string)$currency));
    if ($hashCurrency === 'WON') {
        $hashCurrency = 'KRW';
    }
    $data = $replycode . $tid . $serial . ksers_paygate_price($unitprice) . $hashCurrency;
    return hash('sha256', ksers_env('KSERS_PAYGATE_HASH') . $data);
}

function ksers_paygate_hash_matches($hashresult, $replycode, $tid, $serial, $unitprice, $currency) {
    $hashresult = strtolower(trim((string)$hashresult));
    if (!preg_match('/^[a-f0-9]{40}$/', $hashresult) && !preg_match('/^[a-f0-9]{64}$/', $hashresult)) {
        return false;
    }
    $key = ksers_env('KSERS_PAYGATE_HASH');
    $price = ksers_paygate_price($unitprice);
    if ($key === '' || $price === '' || $replycode === '' || $tid === '') {
        return false;
    }
    $currencies = [strtoupper(trim((string)$currency))];
    if ($currencies[0] === 'KRW') {
        $currencies[] = 'WON';
    }
    foreach ($currencies as $hashCurrency) {
        $payload = $key . $replycode . $tid . $serial . $price . $hashCurrency;
        $expected = strlen($hashresult) === 40 ? sha1($payload) : hash('sha256', $payload);
        if (hash_equals($expected, $hashresult)) {
            return true;
        }
    }
    return false;
}

function ksers_pay_popup_done($location) {
    $next = json_encode((string)$location, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    echo '<!DOCTYPE html><html lang="ko"><head><meta charset="UTF-8"><title>Payment</title></head><body><script>';
    echo 'var next=' . $next . ';';
    echo 'if(window.opener&&!window.opener.closed){if(window.opener.ksersAllowLeave){window.opener.ksersAllowLeave();}window.opener.location.href=next;window.close();}else{location.replace(next);}';
    echo '</script></body></html>';
    exit;
}

function ksers_paygate_cancel_key($password) {
    $password = substr((string)$password, 0, 32);
    $password = str_pad($password, 32, "\0");
    $block = substr($password, 0, 16);
    $stretched = openssl_encrypt($block, 'aes-256-ecb', $password, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
    if ($stretched === false || strlen($stretched) < 16) {
        return '';
    }
    return $stretched . substr($stretched, 0, 16);
}

function ksers_paygate_encrypt_tid($tid) {
    $key = ksers_paygate_cancel_key(hash('sha256', ksers_env('KSERS_PAYGATE_HASH')));
    if ($key === '' || $tid === '') {
        return '';
    }
    $nonceMs = (int)floor(microtime(true) * 1000) % 1000;
    $nonceSec = (int)floor(microtime(true));
    $nonceRnd = random_int(0, 65535);
    $nonce = chr($nonceMs & 0xff) . chr(($nonceMs >> 8) & 0xff)
        . chr($nonceRnd & 0xff) . chr(($nonceRnd >> 8) & 0xff)
        . chr($nonceSec & 0xff) . chr(($nonceSec >> 8) & 0xff)
        . chr(($nonceSec >> 16) & 0xff) . chr(($nonceSec >> 24) & 0xff);
    $iv = $nonce . str_repeat("\0", 8);
    $cipher = openssl_encrypt($tid, 'aes-256-ctr', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);
    if ($cipher === false) {
        return '';
    }
    return 'AES256' . base64_encode($nonce . $cipher);
}

function ksers_paygate_http_post($url, $fields) {
    $payload = http_build_query($fields);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        return [$response, $error];
    }
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        $last = error_get_last();
        return [false, isset($last['message']) ? $last['message'] : ''];
    }
    return [$response, ''];
}

function ksers_paygate_cancel($method, $tid) {
    $profile = ksers_paygate_profile($method);
    $tid = trim((string)$tid);
    if (!$profile || $profile['mid'] === '' || ksers_env('KSERS_PAYGATE_HASH') === '' || $tid === '') {
        return [false, t('카드 거래번호가 없어 카드사에 취소를 보내지 못했습니다.', 'The card transaction number is missing, so the cancellation was not sent.')];
    }
    if (!function_exists('openssl_encrypt') || (!function_exists('curl_init') && !ini_get('allow_url_fopen'))) {
        return [false, t('이 서버에서 카드사 취소를 보낼 수 없습니다.', 'This server cannot send a card cancellation.')];
    }
    $encrypted = ksers_paygate_encrypt_tid($tid);
    if ($encrypted === '') {
        return [false, t('카드 거래번호를 암호화하지 못했습니다.', 'The card transaction number could not be encrypted.')];
    }
    list($body, $error) = ksers_paygate_http_post('https://service.paygate.net/service/cancelAPI.json?callback=callback', [
        'mid' => $profile['mid'],
        'tid' => $encrypted,
        'amount' => 'F',
    ]);
    if ($body === false || $body === '') {
        return [false, t('카드사 취소 요청이 전달되지 않았습니다.', 'The card cancellation request was not delivered.') . ($error !== '' ? ' ' . $error : '')];
    }
    $json = trim((string)$body);
    if (preg_match('/^[^(]*\((.*)\)\s*;?\s*$/s', $json, $match)) {
        $json = $match[1];
    }
    $parsed = json_decode($json, true);
    $code = '';
    if (is_array($parsed)) {
        foreach (['replyCode', 'replycode', 'ReplyCode', 'code'] as $key) {
            if (isset($parsed[$key])) {
                $code = (string)$parsed[$key];
                break;
            }
        }
    }
    if ($code === '0000') {
        return [true, ''];
    }
    $message = t('카드사 취소를 완료하지 못했습니다.', 'The card company did not complete the cancellation.');
    if ($code !== '') {
        $message .= ' (' . $code . ')';
    }
    if (is_array($parsed)) {
        foreach (['replyMsg', 'replyMessage', 'ReplyMsg', 'message'] as $key) {
            if (!empty($parsed[$key])) {
                $message .= ' ' . $parsed[$key];
                break;
            }
        }
    } elseif ($code === '') {
        $message .= ' ' . substr(preg_replace('/\s+/', ' ', $json), 0, 180);
    }
    return [false, $message];
}

function ksers_paygate_verify_received($tid) {
    $url = 'https://service.paygate.net/admin/settle/verifyReceived.jsp?tid=' . rawurlencode($tid) . '&verifyNum=100';
    if (!function_exists('curl_init')) {
        return;
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_exec($ch);
    curl_close($ch);
}
