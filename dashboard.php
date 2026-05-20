<?php
require_once __DIR__ . '/auth.php';
$auth = requireAuth();
require_once __DIR__ . '/db.php';

$projects = dbFetchAll('SELECT * FROM projects WHERE user_id=? ORDER BY updated_at DESC', [$auth['user_id']]);
$userXP    = (int)(dbFetch('SELECT total_xp FROM users WHERE id=?',[$auth['user_id']])['total_xp']??0);

$statusLabel = [
    'selecting'  => ['label'=>'Setting up',   'cls'=>'status-blue'],
    'generating' => ['label'=>'Generating…',  'cls'=>'status-yellow'],
    'active'     => ['label'=>'In Progress',  'cls'=>'status-teal'],
    'completed'  => ['label'=>'Completed ✓',  'cls'=>'status-green'],
    'abandoned'  => ['label'=>'Abandoned',    'cls'=>'status-red'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — AI Project Generator</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cabinet+Grotesk:wght@500;700;800&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<nav class="topnav">
  <div class="nav-logo"><div class="nav-logo-mark">AI</div> Project Generator</div>
  <div class="nav-right">
    <div class="xp-pill">⭐ <?= number_format($userXP) ?> XP</div>
    <span class="nav-user">👤 <?= htmlspecialchars($auth['username']) ?></span>
    <a href="logout.php" class="btn-ghost btn-sm">Logout</a>
  </div>
</nav>
<main class="container">
  <div class="page-header">
    <div>
      <h1 class="page-title">Your Projects</h1>
      <p class="page-sub">Pick up where you left off, or start something new.</p>
    </div>
    <a href="generator.php" class="btn-primary">+ New Project</a>
  </div>

  <?php if (empty($projects)): ?>
  <div class="empty-state">
    <div class="empty-icon">🚀</div>
    <h2>No projects yet</h2>
    <p>Generate your first AI-powered cross-disciplinary project!</p>
    <a href="generator.php" class="btn-primary">Start now →</a>
  </div>
  <?php else: ?>
  <div class="project-grid">
    <?php foreach ($projects as $p):
      $st  = $statusLabel[$p['status']] ?? ['label'=>$p['status'],'cls'=>'status-blue'];
      $gen = $p['generated_project'] ? json_decode($p['generated_project'],true) : null;
      $courses = json_decode($p['courses'],true) ?? [];
      $total = (int)(dbFetch('SELECT COUNT(*) as c FROM roadmap_steps WHERE project_id=?',[$p['id']])['c']??0);
      $done  = (int)(dbFetch('SELECT COUNT(*) as c FROM roadmap_steps WHERE project_id=? AND completed=1',[$p['id']])['c']??0);
      $pct   = $total>0?round(($done/$total)*100):0;
    ?>
    <a href="generator.php?id=<?= $p['id'] ?>" class="project-card <?= $p['status']==='completed'?'card-completed':'' ?>">
      <div class="card-top">
        <span class="status-badge <?= $st['cls'] ?>"><?= $st['label'] ?></span>
        <span class="card-score"><?php if($p['learning_outcomes_score']>0): ?>⭐ <?= $p['learning_outcomes_score'] ?>%<?php endif; ?></span>
      </div>
      <h3 class="card-title"><?= htmlspecialchars($p['title']??'Untitled') ?></h3>
      <?php if ($gen && isset($gen['tagline'])): ?><p class="card-tagline"><?= htmlspecialchars($gen['tagline']) ?></p><?php endif; ?>
      <div class="card-meta">
        <span>Grade <?= $p['grade_level'] ?></span>
        <span><?= count($courses) ?> subject<?= count($courses)!==1?'s':'' ?></span>
        <span><?= strtoupper($p['difficulty']??'medium') ?></span>
        <span><?= date('d M', strtotime($p['updated_at'])) ?></span>
      </div>
      <?php if ($total > 0): ?>
      <div class="card-progress">
        <div class="progress-bar-wrap"><div class="progress-bar-fill" style="width:<?= $pct ?>%"></div></div>
        <span><?= $pct ?>%</span>
      </div>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>
</body>
</html>
