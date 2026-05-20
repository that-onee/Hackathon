<?php
// ── OUTPUT BUFFER: prevents PHP warnings/notices from corrupting JSON ──
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api_functions.php';

// Ensure clean JSON output even if something leaked
ob_clean();
header('Content-Type: application/json; charset=utf-8');

// Catch any PHP fatal/exception and return as JSON instead of empty response
set_exception_handler(function(Throwable $e) {
    ob_clean();
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    exit;
});
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

if (!isset($_SESSION['user_id'])) { echo json_encode(['error' => 'Not authenticated']); exit; }
$userId = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── CREATE PROJECT ──
if ($action === 'create_project') {
    $gradeLevel = (int)($_POST['grade_level'] ?? 0);
    $courses    = json_decode($_POST['courses'] ?? '[]', true);
    $interests  = trim($_POST['interests'] ?? '');
    $difficulty = in_array($_POST['difficulty']??'', ['easy','medium','hard','expert']) ? $_POST['difficulty'] : 'medium';
    $duration   = in_array($_POST['duration']??'', ['1week','2weeks','1month','custom']) ? $_POST['duration'] : '2weeks';
    $language   = in_array($_POST['language']??'', ['en','nl','tr']) ? $_POST['language'] : 'en';
    $eqLevel    = in_array($_POST['eq_level']??'', ['low','medium','high']) ? $_POST['eq_level'] : 'medium';

    // Dutch system: 5-8 = Basisschool Groep 5-8, 9-14 = VO Klas 1-6
    if ($gradeLevel < 1 || $gradeLevel > 14 || count($courses) < 1 || count($courses) > 5) {
        echo json_encode(['error' => 'Select grade (1-12) and 1-5 subjects.']); exit;
    }
    $id = dbInsert(
        'INSERT INTO projects (user_id,grade_level,courses,interests,difficulty,duration,language,eq_level,status) VALUES (?,?,?,?,?,?,?,?,?)',
        [$userId,$gradeLevel,json_encode($courses),$interests,$difficulty,$duration,$language,$eqLevel,'selecting']
    );
    echo json_encode(['success'=>true,'project_id'=>$id]); exit;
}

// ── GENERATE PROJECT ──
if ($action === 'generate_project') {
    $projectId = (int)($_POST['project_id'] ?? 0);
    $project   = dbFetch('SELECT * FROM projects WHERE id=? AND user_id=?', [$projectId,$userId]);
    if (!$project) { echo json_encode(['error'=>'Project not found']); exit; }

    $courses = json_decode($project['courses'], true);
    $result  = generateProject(
        $project['grade_level'], $courses,
        $project['interests']??'',
        $project['difficulty'],
        $project['duration'],
        $project['language'],
        $project['eq_level']??'medium'
    );

    if (!$result['project']) {
        echo json_encode(['error'=>'AI generation failed. Please try again. If the problem persists, check your Gemini API key in config.php']);
        exit;
    }

    dbQuery(
        'UPDATE projects SET title=?,generated_project=?,learning_outcomes_score=?,attempts=?,status=? WHERE id=?',
        [$result['project']['title'], json_encode($result['project']), $result['score'], $result['attempts'], 'active', $projectId]
    );

    awardXP($userId, $projectId, 'generate_project', 5);
    echo json_encode(['success'=>true,'result'=>$result]); exit;
}

// ── REGENERATE PROJECT ──
if ($action === 'regenerate_project') {
    $projectId = (int)($_POST['project_id'] ?? 0);
    $project   = dbFetch('SELECT * FROM projects WHERE id=? AND user_id=?', [$projectId,$userId]);
    if (!$project) { echo json_encode(['error'=>'Not found']); exit; }

    $courses = json_decode($project['courses'], true);
    $result  = generateProject(
        $project['grade_level'], $courses,
        $project['interests']??'',
        $project['difficulty'],
        $project['duration'],
        $project['language'],
        $project['eq_level']??'medium'
    );
    if (!$result['project']) { echo json_encode(['error'=>'Regeneration failed. Please try again.']); exit; }

    dbQuery(
        'UPDATE projects SET title=?,generated_project=?,learning_outcomes_score=?,attempts=attempts+?,status=? WHERE id=?',
        [$result['project']['title'], json_encode($result['project']), $result['score'], $result['attempts'], 'active', $projectId]
    );

    echo json_encode(['success'=>true,'result'=>$result]); exit;
}

// ── GENERATE ROADMAP ──
if ($action === 'generate_roadmap') {
    $projectId = (int)($_POST['project_id'] ?? 0);
    $project   = dbFetch('SELECT * FROM projects WHERE id=? AND user_id=?', [$projectId,$userId]);
    if (!$project || !$project['generated_project']) { echo json_encode(['error'=>'Project not found']); exit; }

    $gen  = json_decode($project['generated_project'], true);
    $road = generateRoadmap(
        $gen['title']??'Project',
        $gen['description']??'',
        $project['grade_level'],
        $project['difficulty'],
        $project['duration'],
        $project['language'],
        $project['eq_level']??'medium'
    );
    if (!$road || empty($road['steps'])) { echo json_encode(['error'=>'Roadmap generation failed. Please try again.']); exit; }

    // Clear old steps
    dbQuery('DELETE FROM roadmap_steps WHERE project_id=?', [$projectId]);

    // Insert new steps
    foreach ($road['steps'] as $step) {
        dbQuery(
            'INSERT INTO roadmap_steps (project_id,step_number,title,description,resources,xp_reward) VALUES (?,?,?,?,?,?)',
            [
                $projectId,
                (int)($step['step_number']??1),
                $step['title']??'Step',
                $step['description']??'',
                json_encode($step['resources']??[]),
                (int)($step['xp_reward']??10)
            ]
        );
    }
    dbQuery('UPDATE projects SET roadmap_requested=1 WHERE id=?', [$projectId]);
    awardXP($userId, $projectId, 'create_roadmap', 15);

    // ✅ FIX: Return DB rows with real IDs (not AI output without IDs)
    $savedSteps = dbFetchAll(
        'SELECT * FROM roadmap_steps WHERE project_id=? ORDER BY step_number',
        [$projectId]
    );

    echo json_encode([
        'success' => true,
        'roadmap' => $road,
        'steps'   => $savedSteps,        // ← real DB IDs now included
        'tip'     => $road['tip']??'',
        'estimated_hours' => $road['estimated_hours']??null,
    ]);
    exit;
}

// ── TOGGLE STEP ──
if ($action === 'toggle_step') {
    $stepId    = (int)($_POST['step_id'] ?? 0);
    $completed = (int)($_POST['completed'] ?? 0);
    $step = dbFetch(
        'SELECT rs.*,p.user_id FROM roadmap_steps rs JOIN projects p ON p.id=rs.project_id WHERE rs.id=? AND p.user_id=?',
        [$stepId,$userId]
    );
    if (!$step) { echo json_encode(['error'=>'Step not found']); exit; }

    dbQuery(
        'UPDATE roadmap_steps SET completed=?,completed_at=? WHERE id=?',
        [$completed, $completed ? date('Y-m-d H:i:s') : null, $stepId]
    );

    $xpGained = 0;
    if ($completed) {
        awardXP($userId, $step['project_id'], 'complete_step', $step['xp_reward']??10);
        $xpGained = (int)($step['xp_reward']??10);
    }

    $total = (int)dbFetch('SELECT COUNT(*) as c FROM roadmap_steps WHERE project_id=?', [$step['project_id']])['c'];
    $done  = (int)dbFetch('SELECT COUNT(*) as c FROM roadmap_steps WHERE project_id=? AND completed=1', [$step['project_id']])['c'];
    $pct   = $total > 0 ? round(($done / $total) * 100) : 0;
    $isCompleted = ($pct === 100);

    if ($isCompleted) {
        dbQuery('UPDATE projects SET status=? WHERE id=?', ['completed', $step['project_id']]);
        awardXP($userId, $step['project_id'], 'complete_project', 50);
    }

    $totalXP = (int)(dbFetch('SELECT total_xp FROM users WHERE id=?', [$userId])['total_xp']??0);
    echo json_encode([
        'success'    => true,
        'progress'   => $pct,
        'completed'  => $isCompleted,
        'xp_gained'  => $xpGained,
        'total_xp'   => $totalXP,
        'done'       => $done,
        'total'      => $total,
    ]);
    exit;
}

// ── SAVE NOTE ──
if ($action === 'save_note') {
    $stepId = (int)($_POST['step_id'] ?? 0);
    $note   = trim($_POST['note'] ?? '');
    $step   = dbFetch(
        'SELECT rs.id FROM roadmap_steps rs JOIN projects p ON p.id=rs.project_id WHERE rs.id=? AND p.user_id=?',
        [$stepId,$userId]
    );
    if (!$step) { echo json_encode(['error'=>'Not found']); exit; }
    dbQuery('UPDATE roadmap_steps SET personal_note=? WHERE id=?', [$note,$stepId]);
    echo json_encode(['success'=>true]); exit;
}

// ── GET RESOURCES ──
if ($action === 'get_resources') {
    $stepId = (int)($_POST['step_id'] ?? 0);
    $step   = dbFetch(
        'SELECT rs.*,p.title as proj_title,p.language,p.user_id FROM roadmap_steps rs JOIN projects p ON p.id=rs.project_id WHERE rs.id=? AND p.user_id=?',
        [$stepId,$userId]
    );
    if (!$step) { echo json_encode(['error'=>'Not found']); exit; }

    $existing = $step['resources'] ?? null;
    if ($existing && $existing !== 'null' && $existing !== '[]') {
        $decoded = json_decode($existing, true);
        if (!empty($decoded)) {
            echo json_encode(['success'=>true,'resources'=>$decoded]); exit;
        }
    }

    $res = generateResources($step['title'], $step['proj_title'], $step['language']??'en');
    if ($res && !empty($res['resources'])) {
        dbQuery('UPDATE roadmap_steps SET resources=? WHERE id=?', [json_encode($res['resources']), $stepId]);
        echo json_encode(['success'=>true,'resources'=>$res['resources']]); exit;
    }
    echo json_encode(['error'=>'Could not generate resources at this time.']); exit;
}

// ── AI CHAT BUDDY ──
if ($action === 'chat_message') {
    $projectId = (int)($_POST['project_id'] ?? 0);
    $message   = trim($_POST['message'] ?? '');
    $history   = json_decode($_POST['history'] ?? '[]', true);

    if (!$message) { echo json_encode(['error'=>'Empty message']); exit; }

    $project = dbFetch('SELECT * FROM projects WHERE id=? AND user_id=?', [$projectId,$userId]);
    if (!$project) { echo json_encode(['error'=>'Not found']); exit; }

    $gen = $project['generated_project'] ? json_decode($project['generated_project'], true) : null;
    $reply = chatWithBuddy($message, $history, $gen, $project['language']??'en', $project['grade_level']);

    echo json_encode(['success'=>true,'reply'=>$reply]); exit;
}

// ── SAVE REFLECTION ──
if ($action === 'save_reflection') {
    $stepId     = (int)($_POST['step_id'] ?? 0);
    $reflection = trim($_POST['reflection'] ?? '');
    $step = dbFetch(
        'SELECT rs.id FROM roadmap_steps rs JOIN projects p ON p.id=rs.project_id WHERE rs.id=? AND p.user_id=?',
        [$stepId,$userId]
    );
    if (!$step) { echo json_encode(['error'=>'Not found']); exit; }
    // Store reflection in personal_note with prefix
    $existing = dbFetch('SELECT personal_note FROM roadmap_steps WHERE id=?', [$stepId])['personal_note']??'';
    $combined = "💭 REFLECTION: $reflection\n\n📝 NOTES: " . preg_replace('/^💭 REFLECTION:.*?📝 NOTES: /s', '', $existing);
    dbQuery('UPDATE roadmap_steps SET personal_note=? WHERE id=?', [trim($combined), $stepId]);
    echo json_encode(['success'=>true]); exit;
}

// ── GET REFLECTION QUESTION ──
if ($action === 'get_reflection') {
    $stepId = (int)($_POST['step_id'] ?? 0);
    $step   = dbFetch(
        'SELECT rs.*,p.language,p.grade_level FROM roadmap_steps rs JOIN projects p ON p.id=rs.project_id WHERE rs.id=? AND p.user_id=?',
        [$stepId,$userId]
    );
    if (!$step) { echo json_encode(['error'=>'Not found']); exit; }
    $question = generateReflectionQuestion($step['title'], $step['language']??'en', $step['grade_level']??8);
    echo json_encode(['success'=>true,'question'=>$question]); exit;
}

// ── ABANDON PROJECT ──
if ($action === 'abandon_project') {
    $projectId = (int)($_POST['project_id'] ?? 0);
    dbQuery('UPDATE projects SET status=? WHERE id=? AND user_id=?', ['abandoned',$projectId,$userId]);
    echo json_encode(['success'=>true]); exit;
}

// ── XP HELPER ──
function awardXP(int $userId, ?int $projectId, string $action, int $xp): void {
    dbQuery('INSERT INTO xp_log (user_id,project_id,action,xp_gained) VALUES (?,?,?,?)', [$userId,$projectId,$action,$xp]);
    dbQuery('UPDATE users SET total_xp=total_xp+? WHERE id=?', [$xp,$userId]);
}

echo json_encode(['error'=>'Unknown action']);
