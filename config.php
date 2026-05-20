<?php
// =====================================================
// CONFIG — Edit API keys below
// =====================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'ai_project_gen');
define('DB_USER', 'root');
define('DB_PASS', '');

// =====================================================
// AI PROVIDERS — Sistem sırayla dener, çalışanı kullanır
// =====================================================

// 1) GEMINI
define('GEMINI_API_KEY', 'AIzaSyD2CGsJkkeMOr4Ka9lHNRXPkEgU1WnCXqc');
define('GEMINI_MODEL',   'gemini-1.5-flash');

// 2) OPENROUTER
define('OPENROUTER_API_KEY', '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');
define('OPENROUTER_MODEL',   'google/gemini-flash-1.5-8b');

// 3) FEATHERLESS
define('FEATHERLESS_API_KEY', 'rc_1ef45d18e2d00dd21948c2cfdc7700114d90a4f538e95097e018f9008e13f288');
define('FEATHERLESS_MODEL',   'Qwen/Qwen2.5-72B-Instruct');

// 4) OPENAI (sonra ekle)
define('OPENAI_API_KEY', '');
define('OPENAI_MODEL',   'gpt-4o-mini');

define('APP_NAME', 'AI Project Generator');
define('APP_URL',  'http://localhost/ai-project-generator');
define('SESSION_LIFETIME', 86400);
