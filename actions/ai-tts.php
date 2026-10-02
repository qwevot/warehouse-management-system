<?php
/**
 * ElevenLabs Text-to-Speech (TTS) Proxy API Endpoint
 * Synthesizes natural multilingual speech (Indonesian & English) using ElevenLabs API.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai-config.php';

function ai_tts_json_out(bool $status, array $data = [], string $message = '', int $httpStatus = 200): void {
    http_response_code($httpStatus);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $status,
        'message' => $message,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Authentication check
if (current_user() === null) {
    ai_tts_json_out(false, [], 'Sesi Anda telah berakhir. Harap login kembali.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ai_tts_json_out(false, [], 'Metode permintaan tidak valid.', 405);
}

if (!defined('ELEVENLABS_API_KEY') || ELEVENLABS_API_KEY === 'YOUR_ELEVENLABS_API_KEY_HERE' || empty(ELEVENLABS_API_KEY)) {
    ai_tts_json_out(false, [], 'API Key ElevenLabs belum dikonfigurasi di includes/ai-config.php.', 400);
}

// 2. Parse input JSON
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

$rawText = trim((string)($input['text'] ?? ''));
if ($rawText === '') {
    ai_tts_json_out(false, [], 'Teks untuk dibacakan tidak boleh kosong.', 400);
}

// Clean markdown syntax from text to produce natural speech
function cleanMarkdownForSpeech(string $text): string {
    // Remove code blocks
    $text = preg_replace('/```[\s\S]*?```/', '', $text);
    // Remove inline code
    $text = preg_replace('/`([^`]+)`/', '$1', $text);
    // Remove images & links [title](url) -> title
    $text = preg_replace('/!\[([^\]]*)\]\([^)]*\)/', '', $text);
    $text = preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $text);
    // Remove HTML tags
    $text = strip_tags($text);
    // Remove markdown headers, list markers, bold, italic
    $text = preg_replace('/^[#*+-\s]+(?=[A-Za-z0-9])/m', '', $text);
    $text = preg_replace('/[*_~]+/', '', $text);
    // Remove table borders |---|
    $text = preg_replace('/\|[^\n]+\|/', '', $text);
    // Collapse multiple spaces/newlines
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

$speechText = cleanMarkdownForSpeech($rawText);
if (mb_strlen($speechText) > 2000) {
    $speechText = mb_substr($speechText, 0, 2000) . '...';
}

if ($speechText === '') {
    ai_tts_json_out(false, [], 'Tidak ada teks yang dapat dilafalkan.', 400);
}

// 3. Dispatch to ElevenLabs API
try {
    $voiceId = defined('ELEVENLABS_VOICE_ID') && !empty(ELEVENLABS_VOICE_ID) ? ELEVENLABS_VOICE_ID : 'gpzZjtWbZNetDrq8CXKD';
    $modelId = defined('ELEVENLABS_MODEL') && !empty(ELEVENLABS_MODEL) ? ELEVENLABS_MODEL : 'eleven_multilingual_v2';
    $endpoint = "https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}";

    $payload = [
        'text'     => $speechText,
        'model_id' => $modelId,
        'voice_settings' => [
            'stability'        => 0.5,
            'similarity_boost' => 0.8,
            'use_speaker_boost'=> true
        ]
    ];

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'xi-api-key: ' . ELEVENLABS_API_KEY,
            'Accept: audio/mpeg'
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 30,
    ]);

    $audioData = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr   = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        ai_tts_json_out(false, [], 'Koneksi ke ElevenLabs gagal: ' . $curlErr, 502);
    }

    if ($httpCode !== 200 || empty($audioData)) {
        $jsonErr = json_decode((string)$audioData, true);
        $errMsg = $jsonErr['detail']['message'] ?? $jsonErr['message'] ?? "ElevenLabs mengembalikan kode status {$httpCode}";
        ai_tts_json_out(false, [], 'Gagal memproses suara ElevenLabs: ' . $errMsg, 502);
    }

    // Return direct binary MP3 stream for instant Web Audio API decoding and playback
    header('Content-Type: audio/mpeg');
    header('Content-Length: ' . strlen($audioData));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('X-Voice-ID: ' . $voiceId);
    header('X-Model-ID: ' . $modelId);
    echo $audioData;
    exit;

} catch (Throwable $e) {
    ai_tts_json_out(false, [], 'Terjadi kesalahan sistem saat membuat audio TTS: ' . $e->getMessage(), 500);
}
