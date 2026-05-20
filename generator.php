<?php
require_once __DIR__ . '/auth.php';
$auth = requireAuth();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/courses_data.php';

$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$project   = null;
$gen       = null;
$steps     = [];

if ($projectId > 0) {
    $project = dbFetch('SELECT * FROM projects WHERE id=? AND user_id=?', [$projectId, $auth['user_id']]);
    if ($project) {
        $gen   = $project['generated_project'] ? json_decode($project['generated_project'], true) : null;
        $steps = dbFetchAll('SELECT * FROM roadmap_steps WHERE project_id=? ORDER BY step_number', [$projectId]);
    }
}

$totalSteps = count($steps);
$doneSteps  = count(array_filter($steps, fn($s) => $s['completed']));
$pct        = $totalSteps > 0 ? round(($doneSteps/$totalSteps)*100) : 0;

$selGrade      = $project ? (int)$project['grade_level'] : 0;
$selCourses    = $project ? (json_decode($project['courses'], true) ?? []) : [];
$selDifficulty = $project ? $project['difficulty'] : 'medium';
$selDuration   = $project ? $project['duration'] : '2weeks';
$selLanguage   = $project ? $project['language'] : 'en';
$selInterests  = $project ? ($project['interests']??'') : '';
$selEQ         = $project ? ($project['eq_level']??'medium') : 'medium';

$userXP = (int)(dbFetch('SELECT total_xp FROM users WHERE id=?',[$auth['user_id']])['total_xp']??0);

$showSelect    = !$project || $project['status'] === 'selecting';
$showResult    = $gen && in_array($project['status']??'', ['active','completed']);
$showRoadmap   = !empty($steps);
$showComplete  = $project && $project['status'] === 'completed';

$langLabels = ['en' => 'EN', 'nl' => 'NL', 'tr' => 'TR'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Project Generator — AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cabinet+Grotesk:wght@500;700;800&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<nav class="topnav">
  <div class="nav-logo">
    <div class="nav-logo-mark">AI</div>
    Project Generator
  </div>
  <div class="nav-right">
    <!-- Language switcher — top right, independent -->
    <div class="lang-switcher" id="nav-lang-switcher">
      <?php foreach ($langLabels as $code => $label): ?>
      <button type="button" class="lang-btn <?= $selLanguage===$code?'active':'' ?>"
        onclick="setNavLanguage('<?= $code ?>')"><?= $label ?></button>
      <?php endforeach; ?>
    </div>
    <div class="xp-pill">+<?= number_format($userXP) ?> XP</div>
    <a href="dashboard.php" class="btn-ghost btn-sm">Dashboard</a>
    <a href="logout.php" class="btn-ghost btn-sm">Logout</a>
  </div>
</nav>

<main class="container container-narrow">

<!-- STEP 1: SELECTOR -->
<div id="step-select" class="step-section <?= !$showSelect ? 'hidden' : '' ?>">
  <div class="step-header">
    <div class="step-badge">Step 1 — Design Your Project</div>
    <h1 class="page-title">What do you want to build?</h1>
    <p class="page-sub">Choose your grade, subjects, and some details. The AI handles the rest.</p>
  </div>

  <div class="select-card">

    <!-- GRADE LEVEL — Dutch school system -->
    <div class="field-section">
      <div class="field-label">Leerjaar <span class="muted">(Schoolniveau)</span></div>
      <div class="grade-grid" id="grade-grid">
        <?php
        // Basisschool: Groep 5-8 (internal values 5-8)
        $basisgroepen = [5=>'Groep 5', 6=>'Groep 6', 7=>'Groep 7', 8=>'Groep 8'];
        // Voortgezet onderwijs: Klas 1-6 (internal values 1-6 but labelled Klas 1-6)
        // We store VO klas as 9-14 internally (9=Klas1, 10=Klas2, ..., 14=Klas6)
        $voKlassen = [9=>'Klas 1', 10=>'Klas 2', 11=>'Klas 3', 12=>'Klas 4', 13=>'Klas 5', 14=>'Klas 6'];
        $allGrades = $basisgroepen + $voKlassen;
        foreach ($allGrades as $val => $label):
        ?>
        <button type="button" id="grade-<?= $val ?>"
          class="grade-btn <?= $selGrade===$val?'active':'' ?>"
          onclick="selectGrade(<?= $val ?>)"><?= $label ?></button>
        <?php endforeach; ?>
      </div>
      <div class="grade-group-labels">
        <span class="grade-group-tag">Basisschool →</span>
        <span class="grade-group-tag vo-tag">Voortgezet Onderwijs →</span>
      </div>
    </div>

    <!-- SUBJECTS -->
    <div class="field-section">
      <div class="field-label">Subjects <span class="muted">(1–5)</span></div>
      <div class="course-grid" id="course-grid">
        <?php foreach ($COURSES as $key => $c):
          $isChecked = in_array($key, $selCourses);
        ?>
        <div class="course-chip <?= $isChecked?'checked':'' ?>"
             id="chip-<?= $key ?>"
             style="--chip-color:<?= $c['color'] ?>"
             onclick="toggleCourse('<?= $key ?>')">
          <span class="chip-icon"><?= $c['icon'] ?></span>
          <span class="chip-name"><?= $c['name'] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="selection-summary">
        <span id="selected-count"><?= count($selCourses) ?></span> subject(s) selected
      </div>
    </div>

    <!-- INTERESTS -->
    <div class="field-section">
      <div class="field-label">Your Interests / Hobbies <span class="muted">(optional)</span></div>
      <input type="text" id="interests-input" class="text-input"
        placeholder="e.g. gaming, nature, music, robotics, cooking…"
        value="<?= htmlspecialchars($selInterests) ?>" maxlength="200">
      <div class="field-hint">AI will personalise the project around what you love.</div>
    </div>

    <!-- OPTIONS ROW: Difficulty + Duration + EQ Level + Language -->
    <div class="options-row">
      <div class="field-section">
        <div class="field-label">Difficulty</div>
        <div class="pill-group" id="difficulty-group">
          <?php foreach (['easy'=>'Easy','medium'=>'Medium','hard'=>'Hard','expert'=>'Expert'] as $val=>$lbl): ?>
          <button type="button" class="pill-btn <?= $selDifficulty===$val?'active':'' ?>"
            onclick="selectOption('difficulty','<?= $val ?>')"><?= $lbl ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="field-section">
        <div class="field-label">Duration</div>
        <div class="pill-group" id="duration-group">
          <?php foreach (['1week'=>'1 Week','2weeks'=>'2 Weeks','1month'=>'1 Month','custom'=>'Custom'] as $val=>$lbl): ?>
          <button type="button" class="pill-btn <?= $selDuration===$val?'active':'' ?>"
            onclick="selectOption('duration','<?= $val ?>')"><?= $lbl ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="field-section">
        <div class="field-label">Autonomy Level</div>
        <div class="pill-group eq-group" id="eq-group">
          <?php foreach (['low'=>'Guided','medium'=>'Mixed','high'=>'Independent'] as $val=>$lbl): ?>
          <button type="button" class="pill-btn <?= $selEQ===$val?'active':'' ?>"
            onclick="selectOption('eq','<?= $val ?>')"><?= $lbl ?></button>
          <?php endforeach; ?>
        </div>
        <div class="field-hint">How much guidance does the student need?</div>
      </div>
      <div class="field-section">
        <div class="field-label">Language</div>
        <div class="pill-group" id="language-group">
          <?php foreach (['en'=>'English','nl'=>'Dutch','tr'=>'Turkish'] as $val=>$lbl): ?>
          <button type="button" class="pill-btn <?= $selLanguage===$val?'active':'' ?>"
            onclick="selectOption('language','<?= $val ?>')"><?= $lbl ?></button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <button id="btn-generate" class="btn-primary btn-full btn-lg"
      onclick="startGeneration()"
      <?= (count($selCourses)<1||$selGrade<1)?'disabled':'' ?>>
      Generate My Project
    </button>
  </div>
</div>

<!-- STEP 2: GENERATING -->
<div id="step-generating" class="step-section hidden">
  <div class="generating-wrap">
    <div class="ai-spinner">
      <div class="spinner-ring"></div>
      <div class="spinner-core">AI</div>
    </div>
    <h2 id="gen-headline">Building your project…</h2>
    <p id="gen-status-text" class="page-sub">Crafting cross-disciplinary connections…</p>
    <div class="gen-attempts-wrap" id="gen-attempts-wrap"></div>
    <div class="gen-progress-bar"><div class="gen-progress-fill" id="gen-progress-fill"></div></div>
  </div>
</div>

<!-- STEP 3: RESULT -->
<div id="step-result" class="step-section <?= !$showResult ? 'hidden' : '' ?>">
  <?php if ($gen): ?>
  <div class="result-hero">
    <div class="result-badges">
      <span class="score-badge" id="result-score-badge"><?= $project['learning_outcomes_score'] ?>% outcomes</span>
      <span class="attempts-badge" id="result-attempts-badge"><?= $project['attempts'] ?> attempt(s)</span>
      <?php if ($project['learning_outcomes_score'] >= 50): ?>
      <span class="pass-badge">Passed</span>
      <?php else: ?>
      <span class="fail-badge">Below 50%</span>
      <?php endif; ?>
    </div>
    <h1 class="result-title" id="result-title"><?= htmlspecialchars($gen['title']??'') ?></h1>
    <p class="result-tagline" id="result-tagline"><?= htmlspecialchars($gen['tagline']??'') ?></p>
    <div class="result-actions">
      <button class="btn-ghost btn-sm" onclick="regenerateProject()" id="btn-regen">Regenerate</button>
      <button class="btn-ghost btn-sm" onclick="printProject()">Print</button>
    </div>
  </div>

  <div class="result-grid">
    <div class="result-block full-width">
      <h3>Project Description</h3>
      <p id="result-description"><?= htmlspecialchars($gen['description']??'') ?></p>
      <?php if (!empty($gen['eq_note'])): ?>
      <div class="eq-note">
        <span>i</span>
        <span id="result-eq-note"><?= htmlspecialchars($gen['eq_note']) ?></span>
      </div>
      <?php endif; ?>
    </div>
    <div class="result-block">
      <h3>Steps</h3>
      <ol class="result-steps" id="result-steps-list">
        <?php foreach ($gen['steps']??[] as $s): ?>
        <li><strong><?= htmlspecialchars($s['title']??$s['step']??'') ?></strong><?= htmlspecialchars($s['detail']??'') ?></li>
        <?php endforeach; ?>
      </ol>
    </div>
    <div class="result-block">
      <h3>Materials</h3>
      <ul class="tag-list" id="result-materials-list">
        <?php foreach ($gen['materials']??[] as $m): ?><li class="tag"><?= htmlspecialchars($m) ?></li><?php endforeach; ?>
      </ul>
      <h3 style="margin-top:1.25rem">Skills Gained</h3>
      <ul class="tag-list">
        <?php foreach ($gen['skills']??[] as $s): ?><li class="tag tag-accent"><?= htmlspecialchars($s) ?></li><?php endforeach; ?>
      </ul>
    </div>
    <div class="result-block full-width">
      <h3>Subjects Covered</h3>
      <div id="result-subjects-list">
        <?php foreach ($gen['subjects_covered']??[] as $sub=>$how): ?>
        <div class="subject-row"><strong><?= htmlspecialchars($sub) ?></strong>: <?= htmlspecialchars($how) ?></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <?php if (!$project['roadmap_requested'] && $project['status']!=='completed'): ?>
  <div class="action-card" id="roadmap-prompt">
    <div>
      <h3>Want a step-by-step roadmap?</h3>
      <p>AI will create a personalised plan to guide you. Earn <strong>+15 XP</strong>!</p>
    </div>
    <div class="action-btns">
      <button class="btn-primary" onclick="requestRoadmap()">Build my roadmap</button>
      <button class="btn-ghost" onclick="document.getElementById('roadmap-prompt').remove()">Skip</button>
    </div>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<!-- STEP 4: ROADMAP -->
<div id="step-roadmap" class="step-section <?= !$showRoadmap ? 'hidden' : '' ?>">
  <div class="progress-header">
    <div>
      <h2>Your Roadmap</h2>
      <p class="page-sub" id="roadmap-title-sub"><?= htmlspecialchars($gen['title']??'') ?></p>
    </div>
    <div class="big-pct" id="big-pct"><?= $pct ?>%</div>
  </div>

  <div class="progress-bar-wrap progress-big">
    <div class="progress-bar-fill" id="main-progress-bar" style="width:<?= $pct ?>%"></div>
  </div>
  <p class="progress-label" id="progress-label"><?= $doneSteps ?> of <?= $totalSteps ?> steps completed</p>

  <div class="xp-banner" id="xp-banner" style="display:none">
    <span id="xp-banner-text">XP earned!</span>
  </div>

  <div class="roadmap-steps" id="roadmap-steps-list">
    <?php foreach ($steps as $s): ?>
    <div class="roadmap-step <?= $s['completed']?'step-done':'' ?>" data-step-id="<?= $s['id'] ?>">
      <div class="step-check" onclick="toggleStep(<?= $s['id'] ?>, this)">
        <?= $s['completed']?'✓':'' ?>
      </div>
      <div class="step-body">
        <div class="step-meta">
          <span class="step-num">Step <?= $s['step_number'] ?></span>
          <span class="step-xp">+<?= $s['xp_reward']??10 ?> XP</span>
        </div>
        <div class="step-title"><?= htmlspecialchars($s['title']) ?></div>
        <div class="step-desc"><?= htmlspecialchars($s['description']??'') ?></div>
        <div class="step-footer">
          <button class="step-link-btn" onclick="loadResources(<?= $s['id'] ?>, this)">Resources</button>
          <button class="step-link-btn" onclick="toggleNote(<?= $s['id'] ?>)">Note</button>
        </div>
        <div class="step-resources hidden" id="resources-<?= $s['id'] ?>"></div>
        <div class="step-note-wrap hidden" id="note-wrap-<?= $s['id'] ?>">
          <textarea class="note-textarea" id="note-<?= $s['id'] ?>"
            placeholder="Write your notes here…"
            onblur="saveNote(<?= $s['id'] ?>)"><?= htmlspecialchars($s['personal_note']??'') ?></textarea>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($project && !in_array($project['status']??'', ['completed','abandoned'])): ?>
  <div class="abandon-wrap">
    <button class="btn-danger btn-sm" onclick="confirmAbandon()">Give Up</button>
  </div>
  <?php endif; ?>
</div>

<!-- STEP 5: COMPLETE -->
<div id="step-complete" class="step-section <?= !$showComplete ? 'hidden' : '' ?>">
  <div class="complete-wrap">
    <div class="complete-icon">🎉</div>
    <h1>Project Complete!</h1>
    <p>You finished <strong><?= htmlspecialchars($gen['title']??'your project') ?></strong>. Incredible work!</p>
    <div class="complete-stats">
      <div class="stat-pill"><?= $project?$project['learning_outcomes_score']:0 ?>% outcomes</div>
      <div class="stat-pill"><?= $totalSteps ?> steps done</div>
      <div class="stat-pill">+50 XP bonus!</div>
    </div>
    <h3 style="margin-top:2rem;margin-bottom:1rem">Want to keep learning?</h3>
    <div class="complete-btns">
      <a href="generator.php" class="btn-primary">Start New Project</a>
      <a href="dashboard.php" class="btn-ghost">View All Projects</a>
    </div>
  </div>
</div>

</main>

<!-- PRINT OVERLAY -->
<div id="print-overlay" class="hidden print-overlay">
  <div class="print-close">
    <button onclick="document.getElementById('print-overlay').classList.add('hidden')">Close</button>
  </div>
  <div id="print-content" class="print-content"></div>
  <button onclick="window.print()" class="btn-primary" style="margin:1rem auto;display:block">Print Now</button>
</div>

<script>
const APP = {
  projectId: <?= $projectId ?: 'null' ?>,
  status: '<?= $project ? $project['status'] : 'new' ?>',
  hasRoadmap: <?= !empty($steps)?'true':'false' ?>,
  projectTitle: <?= json_encode($gen['title']??'') ?>,
  projectDesc: <?= json_encode($gen['description']??'') ?>,
};
let SEL = {
  grade: <?= $selGrade ?: 'null' ?>,
  courses: <?= json_encode($selCourses) ?>,
  difficulty: '<?= $selDifficulty ?>',
  duration: '<?= $selDuration ?>',
  language: '<?= $selLanguage ?>',
  eq: '<?= $selEQ ?>',
};
</script>
<script src="assets/app.js"></script>

<!-- ✨ AI CHAT BUDDY -->
<div id="chat-buddy" class="chat-buddy hidden">
  <div class="chat-header">
    <span>🤖 Study Buddy</span>
    <button onclick="toggleChat()" class="chat-close-btn">✕</button>
  </div>
  <div class="chat-messages" id="chat-messages">
    <div class="chat-msg buddy">Hey! I know your project inside-out. Ask me anything — I'll guide you, not give it away 😄</div>
  </div>
  <div class="chat-input-row">
    <input type="text" id="chat-input" placeholder="Ask your study buddy…" onkeydown="if(event.key==='Enter')sendChat()">
    <button onclick="sendChat()" class="chat-send-btn">➤</button>
  </div>
</div>
<button id="chat-fab" class="chat-fab hidden" onclick="toggleChat()" title="Ask your Study Buddy">🤖</button>

</body>
</html>
