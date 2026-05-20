<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/courses_data.php';

// ── MULTI-PROVIDER AI CALL — sırayla dener, çalışanı kullanır ──
function callAI(array $messages, string $systemPrompt = '', int $maxTokens = 1500): ?string {
    $providers = [];

    // Gemini
    if (defined('GEMINI_API_KEY') && strlen(GEMINI_API_KEY) > 10) {
        $providers[] = ['type' => 'gemini'];
    }
    // OpenRouter
    if (defined('OPENROUTER_API_KEY') && strlen(OPENROUTER_API_KEY) > 10) {
        $providers[] = ['type' => 'openai_compat',
            'url'   => 'https://openrouter.ai/api/v1',
            'key'   => OPENROUTER_API_KEY,
            'model' => OPENROUTER_MODEL,
            'extra_headers' => ['HTTP-Referer: http://localhost', 'X-Title: AI Project Generator'],
        ];
    }
    // Featherless
    if (defined('FEATHERLESS_API_KEY') && strlen(FEATHERLESS_API_KEY) > 10) {
        $providers[] = ['type' => 'openai_compat',
            'url'   => 'https://api.featherless.ai/v1',
            'key'   => FEATHERLESS_API_KEY,
            'model' => FEATHERLESS_MODEL,
        ];
    }
    // OpenAI
    if (defined('OPENAI_API_KEY') && strlen(OPENAI_API_KEY) > 10) {
        $providers[] = ['type' => 'openai_compat',
            'url'   => 'https://api.openai.com/v1',
            'key'   => OPENAI_API_KEY,
            'model' => OPENAI_MODEL,
        ];
    }

    foreach ($providers as $p) {
        $result = null;
        if ($p['type'] === 'gemini') {
            $result = callGeminiAPI($messages, $systemPrompt, $maxTokens);
        } elseif ($p['type'] === 'openai_compat') {
            $result = callOpenAICompatible($p, $messages, $systemPrompt, $maxTokens);
        }
        if ($result !== null && trim($result) !== '') {
            return $result;
        }
        // Bu provider çalışmadı, bir sonrakini dene
        error_log("AI provider failed: " . ($p['type'] === 'gemini' ? 'gemini' : $p['url']));
    }

    return null; // Hiçbiri çalışmadı
}

// ── GEMINI API ──
function callGeminiAPI(array $messages, string $systemPrompt = '', int $maxTokens = 1500): ?string {
    $contents = [];
    if ($systemPrompt) {
        $firstContent = $systemPrompt . "\n\n" . ($messages[0]['content'] ?? '');
        $contents[]   = ['role' => 'user', 'parts' => [['text' => $firstContent]]];
        $messages     = array_slice($messages, 1);
    }
    foreach ($messages as $msg) {
        $role = ($msg['role'] === 'assistant') ? 'model' : 'user';
        $contents[] = ['role' => $role, 'parts' => [['text' => (string)($msg['content'] ?? '')]]];
    }
    if (empty($contents)) return null;
    if (end($contents)['role'] === 'model') {
        $contents[] = ['role' => 'user', 'parts' => [['text' => 'Continue.']]];
    }

    $body = [
        'contents'         => $contents,
        'generationConfig' => ['maxOutputTokens' => $maxTokens, 'temperature' => 0.8, 'topP' => 0.95],
        'safetySettings'   => [
            ['category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
        ],
    ];

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $err || $code !== 200) {
        error_log("Gemini API HTTP $code: $err | " . substr($raw, 0, 200));
        return null;
    }
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) return null;
    $candidates = $data['candidates'] ?? [];
    if (empty($candidates)) return null;
    $text = $candidates[0]['content']['parts'][0]['text'] ?? '';
    return $text !== '' ? trim($text) : null;
}

// ── OPENAI-COMPATIBLE API (OpenRouter, Featherless, OpenAI) ──
function callOpenAICompatible(array $cfg, array $messages, string $systemPrompt, int $maxTokens): ?string {
    $msgs = [];
    if ($systemPrompt) {
        $msgs[] = ['role' => 'system', 'content' => $systemPrompt];
    }
    foreach ($messages as $m) {
        $msgs[] = ['role' => $m['role'], 'content' => (string)($m['content'] ?? '')];
    }

    $body = [
        'model'      => $cfg['model'],
        'messages'   => $msgs,
        'max_tokens' => $maxTokens,
        'temperature'=> 0.8,
    ];

    $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $cfg['key']];
    if (!empty($cfg['extra_headers'])) {
        $headers = array_merge($headers, $cfg['extra_headers']);
    }

    $ch = curl_init($cfg['url'] . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);

    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if (!$raw || $err || $code !== 200) {
        error_log("OpenAI-compat error HTTP $code url={$cfg['url']}: $err | " . substr($raw, 0, 200));
        return null;
    }

    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) return null;

    $text = $data['choices'][0]['message']['content'] ?? '';
    return $text !== '' ? trim($text) : null;
}


// ── JSON PARSER — handles markdown fences and extra text ──
function parseJSON(string $text): ?array {
    $text = preg_replace('/^```(?:json)?\s*/im', '', $text);
    $text = preg_replace('/\s*```\s*$/im', '', $text);
    $text = trim($text);

    $decoded = json_decode($text, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return $decoded;

    $start = strpos($text, '{');
    $end   = strrpos($text, '}');
    if ($start !== false && $end !== false && $end > $start) {
        $sub = substr($text, $start, $end - $start + 1);
        $decoded = json_decode($sub, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return $decoded;
    }

    $start = strpos($text, '[');
    $end   = strrpos($text, ']');
    if ($start !== false && $end !== false && $end > $start) {
        $sub = substr($text, $start, $end - $start + 1);
        $decoded = json_decode($sub, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return $decoded;
    }

    return null;
}

// ── LEARNING OUTCOMES CHECKER ──
function checkLearningOutcomes(string $generatedText, array $selectedCourses, array $coursesData): float {
    $generatedLower = strtolower($generatedText);
    $totalKeywords  = 0;
    $foundKeywords  = 0;
    foreach ($selectedCourses as $courseKey) {
        if (!isset($coursesData[$courseKey]['keywords'])) continue;
        foreach ($coursesData[$courseKey]['keywords'] as $kw) {
            $totalKeywords++;
            if (str_contains($generatedLower, strtolower($kw))) $foundKeywords++;
        }
    }
    return $totalKeywords > 0 ? round(($foundKeywords / $totalKeywords) * 100, 1) : 0;
}

function getDutchGradeLabel(int $grade): string {
    $map = [
        5  => 'Groep 5 (basisschool, ~8-9 jaar)',
        6  => 'Groep 6 (basisschool, ~9-10 jaar)',
        7  => 'Groep 7 (basisschool, ~10-11 jaar)',
        8  => 'Groep 8 (basisschool, ~11-12 jaar)',
        9  => 'Klas 1 voortgezet onderwijs (~12-13 jaar)',
        10 => 'Klas 2 voortgezet onderwijs (~13-14 jaar)',
        11 => 'Klas 3 voortgezet onderwijs (~14-15 jaar)',
        12 => 'Klas 4 voortgezet onderwijs (~15-16 jaar)',
        13 => 'Klas 5 voortgezet onderwijs (~16-17 jaar)',
        14 => 'Klas 6 voortgezet onderwijs (~17-18 jaar)',
    ];
    return $map[$grade] ?? "Klas {$grade}";
}

function getLangLabel(string $lang): string {
    return ['en' => 'English', 'nl' => 'Dutch', 'tr' => 'Turkish'][$lang] ?? 'English';
}
function getDifficultyLabel(string $diff): string {
    return ['easy' => 'easy (beginner-friendly)', 'medium' => 'intermediate', 'hard' => 'advanced/challenging', 'expert' => 'expert/research-level'][$diff] ?? 'intermediate';
}
function getDurationLabel(string $dur): string {
    return ['1week' => '1 week', '2weeks' => '2 weeks', '1month' => '1 month', 'custom' => 'flexible timeline'][$dur] ?? '2 weeks';
}
function getEQLevelLabel(string $eq): string {
    return ['low' => 'step-by-step with full explanations', 'medium' => 'some guidance, mostly independent', 'high' => 'minimal guidance, explore freely'][$eq] ?? 'some guidance, mostly independent';
}

// ── GENERATE PROJECT (feedback loop, max 3 attempts) ──
function generateProject(int $gradeLevel, array $selectedCourses, string $interests = '', string $difficulty = 'medium', string $duration = '2weeks', string $language = 'en', string $eqLevel = 'medium'): array {
    global $COURSES;

    $courseNames = [];
    $allOutcomes = [];
    foreach ($selectedCourses as $key) {
        if (isset($COURSES[$key])) {
            $courseNames[] = $COURSES[$key]['name'];
            $allOutcomes   = array_merge($allOutcomes, $COURSES[$key]['outcomes']);
        }
    }

    if (empty($courseNames)) {
        return ['project' => null, 'score' => 0, 'attempts' => 0, 'passed' => false];
    }

    $langLabel    = getLangLabel($language);
    $diffLabel    = getDifficultyLabel($difficulty);
    $durLabel     = getDurationLabel($duration);
    $eqLabel      = getEQLevelLabel($eqLevel);
    $interestNote = $interests ? "\nStudent's personal interests/hobbies: {$interests}" : '';

    $gradeLabel = getDutchGradeLabel($gradeLevel);

    $system = "You are an expert educational project designer for secondary schools. "
            . "Respond ONLY with a valid JSON object — no markdown, no explanation outside the JSON. "
            . "Write ALL project content in {$langLabel}. "
            . "Student autonomy level: {$eqLabel}.";

    $bestProject = null;
    $bestScore   = 0;
    $attempts    = 0;

    for ($i = 0; $i < 3; $i++) {
        $attempts++;

        // Collect keywords for feedback hint
        $allKeywords = [];
        foreach ($selectedCourses as $k) {
            if (isset($COURSES[$k]['keywords'])) {
                $allKeywords = array_merge($allKeywords, $COURSES[$k]['keywords']);
            }
        }
        $hint = $i > 0
            ? "\nIMPORTANT: Previous attempt scored {$bestScore}%. Explicitly include MORE of these subject keywords: " . implode(', ', array_slice($allKeywords, 0, 25))
            : '';

        $outcomesText = implode("\n", array_map(fn($o) => "- $o", $allOutcomes));
        $coursesText  = implode(', ', $courseNames);

        $prompt = <<<PROMPT
Create a cross-disciplinary school project for Dutch students in {$gradeLabel}.
Difficulty: {$diffLabel}
Duration: {$durLabel}
Autonomy level: {$eqLabel}
Subjects to combine: {$coursesText}{$interestNote}

Required learning outcomes:
{$outcomesText}{$hint}

Respond with this EXACT JSON (no extra text, no markdown):
{
  "title": "Creative project title",
  "tagline": "One exciting hook sentence",
  "description": "3-4 sentence project description",
  "steps": [{"step": 1, "title": "Step title", "detail": "What to do in detail"}],
  "materials": ["item1","item2"],
  "subjects_covered": {"SubjectName": "How this subject is applied"},
  "skills": ["skill1","skill2"],
  "eq_note": "One sentence about how this project fits the student's autonomy level",
  "fun_fact": "One surprising or inspiring fact related to the project topic"
}
PROMPT;

        $text    = callAI([["role" => "user", "content" => $prompt]], $system, 2400);
        if (!$text) continue;

        $project = parseJSON($text);
        if ($project && isset($project['title'])) {
            $score = checkLearningOutcomes(json_encode($project), $selectedCourses, $COURSES);
            if ($score > $bestScore) {
                $bestScore   = $score;
                $bestProject = $project;
            }
            if ($score >= 50) break;
        }
    }

    return ['project' => $bestProject, 'score' => $bestScore, 'attempts' => $attempts, 'passed' => $bestScore >= 50];
}

// ── GENERATE ROADMAP ──
function generateRoadmap(string $title, string $description, int $gradeLevel, string $difficulty = 'medium', string $duration = '2weeks', string $language = 'en', string $eqLevel = 'medium'): ?array {
    $langLabel = getLangLabel($language);
    $diffLabel = getDifficultyLabel($difficulty);
    $durLabel  = getDurationLabel($duration);
    $eqLabel   = getEQLevelLabel($eqLevel);
    $system    = "You are an educational coach. Respond ONLY with valid JSON in {$langLabel}. No markdown.";

    $gradeLabel = getDutchGradeLabel($gradeLevel);
    $prompt = <<<PROMPT
Create a step-by-step learning roadmap for this project:
Title: {$title}
Description: {$description}
Grade: {$gradeLabel}, Difficulty: {$diffLabel}, Duration: {$durLabel}, Autonomy: {$eqLabel}

Return JSON with 7-10 steps:
{"steps":[{"step_number":1,"title":"Short step title","description":"2-3 sentence guide for the student","resources":[{"type":"video","title":"Resource title","url":"https://example.com"}],"xp_reward":15}],"estimated_hours":12,"tip":"One motivating tip for the student"}
PROMPT;

    $text = callAI([['role' => 'user', 'content' => $prompt]], $system, 2200);
    return $text ? parseJSON($text) : null;
}

// ── GENERATE RESOURCE SUGGESTIONS ──
function generateResources(string $stepTitle, string $projectTitle, string $language = 'en'): ?array {
    $langLabel = getLangLabel($language);
    $system    = "You are a helpful teacher. Respond ONLY with valid JSON. No markdown.";
    $prompt    = "Suggest 3 real, helpful learning resources for:\nProject: {$projectTitle}\nStep: {$stepTitle}\nLanguage preference: {$langLabel}\n\nJSON: {\"resources\":[{\"type\":\"video|article|tool\",\"title\":\"Title\",\"description\":\"One sentence why it helps\",\"url\":\"https://\"}]}";
    $text      = callAI([['role' => 'user', 'content' => $prompt]], $system, 700);
    return $text ? parseJSON($text) : null;
}

// ── AI CHAT BUDDY ──  NEW FEATURE
function chatWithBuddy(string $message, array $history, ?array $project, string $language = 'en', int $grade = 8): string {
    $langLabel   = getLangLabel($language);
    $projectInfo = '';
    if ($project) {
        $projectInfo = "You are helping a student with their project:\n"
            . "Title: " . ($project['title']??'') . "\n"
            . "Description: " . ($project['description']??'') . "\n"
            . "Skills: " . implode(', ', $project['skills']??[]) . "\n\n";
    }

    $system = "{$projectInfo}You are a friendly, encouraging AI study buddy for a grade {$grade} student. "
            . "Speak in {$langLabel}. Be concise (2-4 sentences), supportive, and educational. "
            . "Give hints rather than full answers. Use 1 relevant emoji per message. "
            . "Never do the work for the student — guide them to discover it themselves.";

    // Build conversation history for context
    $messages = [];
    foreach (array_slice($history, -6) as $h) {  // last 6 turns for context
        $messages[] = ['role' => $h['role'], 'content' => $h['content']];
    }
    $messages[] = ['role' => 'user', 'content' => $message];

    $reply = callAI($messages, $system, 300);
    return $reply ?? "I'm having trouble connecting right now. Try rephrasing your question! 💡";
}

// ── GENERATE REFLECTION QUESTION ──  NEW FEATURE
function generateReflectionQuestion(string $stepTitle, string $language = 'en', int $grade = 8): string {
    $langLabel = getLangLabel($language);
    $system    = "You are an educational coach. Write in {$langLabel}. Respond with ONLY one reflection question — no preamble, no quotes around it.";
    $prompt    = "Write one thoughtful reflection question for a grade {$grade} student who just completed this learning step: \"{$stepTitle}\". Make it personal and encourage deep thinking about what they learned and how they can apply it.";
    $text      = callAI([['role' => 'user', 'content' => $prompt]], $system, 150);
    return $text ?? "What did you discover during this step that surprised you most?";
}
