<?php
// Test file — DELETE after fixing
require_once __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== AI PROVIDER TEST ===\n\n";

function testProvider(string $name, callable $fn): void {
    echo "[$name]\n";
    $start = microtime(true);
    $result = $fn();
    $ms = round((microtime(true) - $start) * 1000);
    if ($result) {
        echo "✅ ÇALIŞIYOR ({$ms}ms) → " . substr(trim($result), 0, 80) . "\n\n";
    } else {
        echo "❌ ÇALIŞMIYOR\n\n";
    }
}

function curlPost(string $url, array $body, array $headers): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200) { echo "  HTTP $code: " . substr($raw, 0, 150) . "\n"; return null; }
    return $raw;
}

// 1. GEMINI
testProvider('Gemini (' . GEMINI_MODEL . ')', function() {
    $url  = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY;
    $body = ['contents' => [['role'=>'user','parts'=>[['text'=>'Reply with just: WORKS']]]],'generationConfig'=>['maxOutputTokens'=>10]];
    $raw  = curlPost($url, $body, ['Content-Type: application/json']);
    if (!$raw) return null;
    $d = json_decode($raw, true);
    return $d['candidates'][0]['content']['parts'][0]['text'] ?? null;
});

// 2. OPENROUTER
testProvider('OpenRouter (' . OPENROUTER_MODEL . ')', function() {
    if (!strlen(OPENROUTER_API_KEY)) { echo "  (key boş, atlandı)\n"; return 'SKIP'; }
    $body = ['model'=>OPENROUTER_MODEL,'messages'=>[['role'=>'user','content'=>'Reply with just: WORKS']],'max_tokens'=>10];
    $raw  = curlPost('https://openrouter.ai/api/v1/chat/completions', $body, ['Content-Type: application/json','Authorization: Bearer '.OPENROUTER_API_KEY,'HTTP-Referer: http://localhost']);
    if (!$raw) return null;
    $d = json_decode($raw, true);
    return $d['choices'][0]['message']['content'] ?? null;
});

// 3. FEATHERLESS
testProvider('Featherless (' . FEATHERLESS_MODEL . ')', function() {
    if (!strlen(FEATHERLESS_API_KEY)) { echo "  (key boş, atlandı)\n"; return 'SKIP'; }
    $body = ['model'=>FEATHERLESS_MODEL,'messages'=>[['role'=>'user','content'=>'Reply with just: WORKS']],'max_tokens'=>10];
    $raw  = curlPost('https://api.featherless.ai/v1/chat/completions', $body, ['Content-Type: application/json','Authorization: Bearer '.FEATHERLESS_API_KEY]);
    if (!$raw) return null;
    $d = json_decode($raw, true);
    return $d['choices'][0]['message']['content'] ?? null;
});

// 4. OPENAI
testProvider('OpenAI (' . OPENAI_MODEL . ')', function() {
    if (!strlen(OPENAI_API_KEY)) { echo "  (key boş, atlandı)\n"; return 'SKIP'; }
    $body = ['model'=>OPENAI_MODEL,'messages'=>[['role'=>'user','content'=>'Reply with just: WORKS']],'max_tokens'=>10];
    $raw  = curlPost('https://api.openai.com/v1/chat/completions', $body, ['Content-Type: application/json','Authorization: Bearer '.OPENAI_API_KEY]);
    if (!$raw) return null;
    $d = json_decode($raw, true);
    return $d['choices'][0]['message']['content'] ?? null;
});
