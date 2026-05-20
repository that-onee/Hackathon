/* ============================================================
   AI Project Generator v2 — app.js
   Grade click FIX: using global selectGrade() / toggleCourse()
   ============================================================ */

// ── GRADE SELECTION (global function — called by inline onclick) ──
function selectGrade(g) {
  SEL.grade = g;
  document.querySelectorAll('.grade-btn').forEach(btn => {
    btn.classList.toggle('active', parseInt(btn.id.replace('grade-','')) === g);
  });
  updateGenerateBtn();
}

// ── COURSE TOGGLE (global) ──
function toggleCourse(key) {
  const chip = document.getElementById('chip-' + key);
  if (!chip) return;
  const idx = SEL.courses.indexOf(key);
  if (idx > -1) {
    SEL.courses.splice(idx, 1);
    chip.classList.remove('checked');
  } else {
    if (SEL.courses.length >= 5) { showToast('⚠️ Max 5 subjects allowed.', 'warn'); return; }
    SEL.courses.push(key);
    chip.classList.add('checked');
  }
  document.getElementById('selected-count').textContent = SEL.courses.length;
  updateGenerateBtn();
}

// ── PILL OPTION SELECTOR (difficulty / duration / language) ──
function selectOption(group, val) {
  SEL[group] = val;
  // Use onclick attribute to match — most reliable approach
  document.querySelectorAll(`#${group}-group .pill-btn`).forEach(btn => {
    const onclick = btn.getAttribute('onclick') || '';
    btn.classList.toggle('active', onclick.includes(`'${val}'`));
  });
  // Sync nav language switcher when language option changes
  if (group === 'language') {
    document.querySelectorAll('#nav-lang-switcher .lang-btn').forEach(btn => {
      const onclick = btn.getAttribute('onclick') || '';
      btn.classList.toggle('active', onclick.includes(`'${val}'`));
    });
  }
}

// Language switcher in nav (syncs with form language pill)
function setNavLanguage(code) {
  selectOption('language', code);
  // Also update nav buttons
  document.querySelectorAll('#nav-lang-switcher .lang-btn').forEach(btn => {
    const onclick = btn.getAttribute('onclick') || '';
    btn.classList.toggle('active', onclick.includes(`'${code}'`));
  });
  // Update form pill buttons
  document.querySelectorAll('#language-group .pill-btn').forEach(btn => {
    const onclick = btn.getAttribute('onclick') || '';
    btn.classList.toggle('active', onclick.includes(`'${code}'`));
  });
}

function updateGenerateBtn() {
  const btn = document.getElementById('btn-generate');
  if (btn) btn.disabled = !(SEL.grade && SEL.courses.length >= 1);
}

// ── INIT ──
document.addEventListener('DOMContentLoaded', () => {
  updateGenerateBtn();
  // If already has roadmap open both result and roadmap
  if (APP.hasRoadmap) {
    show('step-result'); show('step-roadmap');
    if (APP.status === 'completed') show('step-complete');
  } else if (APP.status === 'active') {
    show('step-result');
  }
});

// ── GENERATE ──
async function startGeneration() {
  if (!SEL.grade || SEL.courses.length < 1) { showToast('Kies een leerjaar en minimaal 1 vak.','warn'); return; }
  const interests = document.getElementById('interests-input')?.value?.trim() ?? '';

  show('step-generating');
  hide('step-select');
  startProgressAnim();

  try {
    let pid = APP.projectId;
    if (!pid) {
      const r = await ajax({ action:'create_project', grade_level:SEL.grade, courses:JSON.stringify(SEL.courses),
        interests, difficulty:SEL.difficulty, duration:SEL.duration, language:SEL.language, eq_level:SEL.eq||'medium' });
      if (r.error) throw new Error(r.error);
      pid = r.project_id;
      APP.projectId = pid;
      history.replaceState(null,'','?id='+pid);
    }

    setStatus('Connecting to AI…'); await delay(500);
    setStatus('Generating your cross-disciplinary project…'); await delay(400);

    const gen = await ajax({ action:'generate_project', project_id:pid });
    if (gen.error) throw new Error(gen.error);

    stopProgressAnim(100);
    await delay(400);

    populateResult(gen.result);
    hide('step-generating');
    show('step-result');

  } catch(err) {
    stopProgressAnim(0);
    hide('step-generating'); show('step-select');
    showToast('❌ ' + (err.message || 'Generation failed'), 'error');
  }
}

// ── REGENERATE ──
async function regenerateProject() {
  const btn = document.getElementById('btn-regen');
  if (btn) { btn.disabled = true; btn.textContent = '⏳ Regenerating…'; }
  try {
    const r = await ajax({ action:'regenerate_project', project_id:APP.projectId });
    if (r.error) throw new Error(r.error);
    populateResult(r.result);
    showToast('✅ New project generated!');
  } catch(err) {
    showToast('❌ ' + err.message, 'error');
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = '🔄 Regenerate'; }
  }
}

// ── POPULATE RESULT ──
function populateResult(result) {
  const p = result?.project;
  if (!p) return;

  setText('result-title',       p.title || '');
  setText('result-tagline',     p.tagline || '');
  setText('result-description', p.description || '');

  const scoreBadge = document.getElementById('result-score-badge');
  if (scoreBadge) scoreBadge.textContent = `⭐ ${result.score}% outcomes`;

  const attemptsBadge = document.getElementById('result-attempts-badge');
  if (attemptsBadge) attemptsBadge.textContent = `${result.attempts} attempt(s)`;

  const stepsList = document.getElementById('result-steps-list');
  if (stepsList && p.steps) {
    stepsList.innerHTML = p.steps.map(s => `<li><strong>${esc(s.title||s.step||'')}</strong><br>${esc(s.detail||'')}</li>`).join('');
  }
  const matList = document.getElementById('result-materials-list');
  if (matList && p.materials) {
    matList.innerHTML = p.materials.map(m => `<li class="tag">${esc(m)}</li>`).join('');
  }
  const subList = document.getElementById('result-subjects-list');
  if (subList && p.subjects_covered) {
    subList.innerHTML = Object.entries(p.subjects_covered).map(([s,h]) =>
      `<div class="subject-row"><strong>${esc(s)}</strong>: ${esc(h)}</div>`).join('');
  }

  APP.projectTitle = p.title || '';
  APP.projectDesc  = p.description || '';
}

// ── ROADMAP ──
async function requestRoadmap() {
  const prompt = document.getElementById('roadmap-prompt');
  if (prompt) prompt.innerHTML = `<div style="display:flex;align-items:center;gap:1rem;padding:.5rem"><div class="spinner-ring" style="width:30px;height:30px;flex-shrink:0"></div><span>Building your roadmap…</span></div>`;

  try {
    const r = await ajax({ action:'generate_roadmap', project_id:APP.projectId });
    if (r.error) throw new Error(r.error);
    buildRoadmapUI(r.steps, r.tip);
    show('step-roadmap');
    if (prompt) prompt.remove();
    if (r.tip) showToast('💡 ' + r.tip);
  } catch(err) {
    showToast('❌ ' + err.message, 'error');
    if (prompt) prompt.innerHTML = `<p style="color:var(--red);padding:1rem">Failed. <button onclick="requestRoadmap()" class="btn-ghost btn-sm">Retry</button></p>`;
  }
}

function buildRoadmapUI(steps, tip) {
  const c = document.getElementById('roadmap-steps-list');
  if (!c || !steps) return;
  c.innerHTML = steps.map(s => `
    <div class="roadmap-step" data-step-id="${s.id||''}">
      <div class="step-check" onclick="toggleStep('${s.id||''}',this)"></div>
      <div class="step-body">
        <div class="step-meta">
          <span class="step-num">Step ${s.step_number}</span>
          <span class="step-xp">+${s.xp_reward||10} XP</span>
        </div>
        <div class="step-title">${esc(s.title)}</div>
        <div class="step-desc">${esc(s.description||'')}</div>
        <div class="step-footer">
          <button class="step-link-btn" onclick="loadResources('${s.id||''}',this)">📚 Resources</button>
          <button class="step-link-btn" onclick="toggleNote('${s.id||''}')">📝 Note</button>
        </div>
        <div class="step-resources hidden" id="resources-${s.id||''}"></div>
        <div class="step-note-wrap hidden" id="note-wrap-${s.id||''}">
          <textarea class="note-textarea" id="note-${s.id||''}" placeholder="Write your notes…" onblur="saveNote('${s.id||''}')"></textarea>
        </div>
      </div>
    </div>`).join('');
  updateProgressUI(0, steps.length);
}

// ── TOGGLE STEP ──
async function toggleStep(stepId, checkEl) {
  if (!stepId) return;
  const stepDiv = checkEl.closest('.roadmap-step');
  const isDone  = !stepDiv.classList.contains('step-done');
  stepDiv.classList.toggle('step-done', isDone);
  checkEl.textContent = isDone ? '✓' : '';

  try {
    const r = await ajax({ action:'toggle_step', step_id:stepId, completed:isDone?1:0 });
    if (r.error) throw new Error(r.error);

    updateProgressUI(r.progress, r.total, r.done);

    if (isDone && r.xp_gained > 0) {
      showXPBanner(`+${r.xp_gained} XP earned! Total: ${r.total_xp} XP`);
      updateNavXP(r.total_xp);
    }
    if (r.completed) {
      setTimeout(() => { show('step-complete'); launchConfetti(); }, 800);
    }
  } catch(err) {
    stepDiv.classList.toggle('step-done', !isDone);
    checkEl.textContent = !isDone ? '✓' : '';
    showToast('Could not save.','error');
  }
}

function updateProgressUI(pct, total, done) {
  const bigPct = document.getElementById('big-pct');
  if (bigPct) bigPct.textContent = pct + '%';
  const bar = document.getElementById('main-progress-bar');
  if (bar) bar.style.width = pct + '%';
  const label = document.getElementById('progress-label');
  if (label) {
    const d = done ?? document.querySelectorAll('.roadmap-step.step-done').length;
    const t = total ?? document.querySelectorAll('.roadmap-step').length;
    label.textContent = `${d} of ${t} steps completed`;
  }
}

function updateNavXP(total) {
  const el = document.querySelector('.xp-pill');
  if (el) { el.textContent = `⭐ ${total.toLocaleString()} XP`; el.style.animation='xpPop .4s ease'; setTimeout(()=>el.style.animation='',500); }
}

function showXPBanner(text) {
  const b = document.getElementById('xp-banner');
  if (!b) return;
  document.getElementById('xp-banner-text').textContent = text;
  b.style.display = 'flex'; b.style.animation = 'fadeUp .3s ease';
  setTimeout(() => { b.style.display = 'none'; }, 2800);
}

// ── NOTES ──
function toggleNote(stepId) {
  const wrap = document.getElementById('note-wrap-' + stepId);
  if (!wrap) return;
  wrap.classList.toggle('hidden');
  if (!wrap.classList.contains('hidden')) document.getElementById('note-'+stepId)?.focus();
}

async function saveNote(stepId) {
  const note = document.getElementById('note-'+stepId)?.value ?? '';
  await ajax({ action:'save_note', step_id:stepId, note });
}

// ── RESOURCES ──
async function loadResources(stepId, btn) {
  const resDiv = document.getElementById('resources-' + stepId);
  if (!resDiv) return;

  if (!resDiv.classList.contains('hidden')) { resDiv.classList.add('hidden'); return; }

  btn.textContent = '⏳ Loading…'; btn.disabled = true;
  try {
    const r = await ajax({ action:'get_resources', step_id:stepId });
    if (r.error) throw new Error(r.error);

    resDiv.innerHTML = r.resources.map(res => `
      <a href="${esc(res.url||'#')}" target="_blank" rel="noopener" class="resource-card">
        <span class="res-type">${res.type==='video'?'▶️':res.type==='tool'?'🛠️':'📄'}</span>
        <div><strong>${esc(res.title)}</strong><br><span>${esc(res.description||'')}</span></div>
      </a>`).join('');
    resDiv.classList.remove('hidden');
  } catch(err) {
    resDiv.innerHTML = `<p class="res-error">Could not load resources.</p>`;
    resDiv.classList.remove('hidden');
  } finally {
    btn.textContent = '📚 Resources'; btn.disabled = false;
  }
}

// ── ABANDON ──
async function confirmAbandon() {
  if (!confirm('Give up on this project? You can still view it later.')) return;
  await ajax({ action:'abandon_project', project_id:APP.projectId });
  window.location.href = 'dashboard.php';
}

// ── PRINT ──
function printProject() {
  const overlay = document.getElementById('print-overlay');
  const content = document.getElementById('print-content');
  if (!overlay || !content) return;
  content.innerHTML = `
    <h1>${esc(APP.projectTitle)}</h1>
    <p>${esc(APP.projectDesc)}</p>
    <p><em>AI Project Generator — Rotterdam TC Hackathon 2026</em></p>
    <hr>
    ${document.getElementById('result-steps-list')?.outerHTML ?? ''}
    <hr>
    <strong>Materials:</strong> ${document.getElementById('result-materials-list')?.innerText ?? ''}
  `;
  overlay.classList.remove('hidden');
}

// ── CONFETTI ──
function launchConfetti() {
  const colors = ['#00c9ff','#7c3aed','#10b981','#f59e0b','#ec4899','#f97316'];
  for (let i = 0; i < 80; i++) {
    const d = document.createElement('div');
    Object.assign(d.style, {
      position:'fixed', top:'50%', left:Math.random()*100+'vw',
      width:'8px', height:'8px', borderRadius:Math.random()>.5?'50%':'2px',
      background:colors[i%colors.length], zIndex:9999, pointerEvents:'none',
      animation:`fall ${1.2+Math.random()*1.5}s ease-out forwards`,
      animationDelay:Math.random()*0.8+'s',
    });
    document.body.appendChild(d);
    setTimeout(() => d.remove(), 3200);
  }
  if (!document.querySelector('#confetti-style')) {
    const s = document.createElement('style');
    s.id = 'confetti-style';
    s.textContent = '@keyframes fall{from{transform:translateY(-10vh) rotate(0);opacity:1}to{transform:translateY(90vh) rotate(360deg);opacity:0}}';
    document.head.appendChild(s);
  }
}

// ── PROGRESS ANIMATION ──
let _genInterval = null;
function startProgressAnim() {
  const bar = document.getElementById('gen-progress-fill');
  let pct = 0;
  _genInterval = setInterval(() => {
    pct = Math.min(pct + Math.random()*3, 88);
    if (bar) bar.style.width = pct + '%';
  }, 350);
}
function stopProgressAnim(final=100) {
  clearInterval(_genInterval);
  const bar = document.getElementById('gen-progress-fill');
  if (bar) bar.style.width = final + '%';
}
function setStatus(text) {
  const el = document.getElementById('gen-status-text');
  if (el) el.textContent = text;
}

// ── HELPERS ──
function show(id) { document.getElementById(id)?.classList.remove('hidden'); }
function hide(id) { document.getElementById(id)?.classList.add('hidden'); }
function setText(id, val) { const e=document.getElementById(id); if(e) e.textContent=val; }
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function delay(ms) { return new Promise(r => setTimeout(r, ms)); }
async function ajax(data) {
  const fd = new FormData();
  for (const [k,v] of Object.entries(data)) fd.append(k, v);
  try {
    const r = await fetch('ajax.php', { method:'POST', body:fd });
    const text = await r.text();
    if (!text || text.trim() === '') {
      console.error('Empty response from server');
      return { error: 'Server returned an empty response. Please try again.' };
    }
    try {
      return JSON.parse(text);
    } catch(e) {
      console.error('JSON parse error:', e, '\nRaw response:', text.substring(0, 500));
      return { error: 'Server returned an invalid response. Please try again.' };
    }
  } catch(e) {
    console.error('Network error:', e);
    return { error: 'Network error. Check your connection and try again.' };
  }
}
function showToast(msg, type='info') {
  const t = document.createElement('div');
  t.textContent = msg;
  Object.assign(t.style, {
    position:'fixed', bottom:'1.5rem', left:'50%', transform:'translateX(-50%)',
    padding:'.75rem 1.5rem',
    background:type==='error'?'rgba(239,68,68,.92)':'rgba(7,9,15,.95)',
    border:`1px solid ${type==='error'?'rgba(239,68,68,.5)':'rgba(0,201,255,.3)'}`,
    borderRadius:'10px', color:'#fff', fontSize:'.875rem', fontWeight:'500',
    backdropFilter:'blur(10px)', zIndex:9999, whiteSpace:'nowrap',
    boxShadow:'0 4px 20px rgba(0,0,0,.4)',
  });
  document.body.appendChild(t);
  setTimeout(()=>t.remove(), 3200);
}

// ═══════════════════════════════════════════════════════════
// ✨ NEW FEATURES
// ═══════════════════════════════════════════════════════════

// ── CHAT BUDDY ──
let chatHistory = [];

function toggleChat() {
  const panel = document.getElementById('chat-buddy');
  if (!panel) return;
  panel.classList.toggle('hidden');
  if (!panel.classList.contains('hidden')) {
    document.getElementById('chat-input')?.focus();
  }
}

// Show the chat FAB once a project is active
function activateChatFab() {
  const fab = document.getElementById('chat-fab');
  if (fab && APP.projectId) fab.classList.remove('hidden');
}

async function sendChat() {
  const input = document.getElementById('chat-input');
  const msg   = input?.value?.trim();
  if (!msg || !APP.projectId) return;

  appendChatMsg(msg, 'user');
  input.value = '';

  const typingEl = appendChatMsg('…', 'buddy typing');
  try {
    const r = await ajax({
      action:     'chat_message',
      project_id: APP.projectId,
      message:    msg,
      history:    JSON.stringify(chatHistory.slice(-6)),
    });
    typingEl.remove();
    if (r.reply) {
      appendChatMsg(r.reply, 'buddy');
      chatHistory.push({ role:'user', content:msg });
      chatHistory.push({ role:'assistant', content:r.reply });
    }
  } catch {
    typingEl.remove();
    appendChatMsg('Connection lost. Try again! 🔄', 'buddy');
  }
}

function appendChatMsg(text, cls) {
  const c = document.getElementById('chat-messages');
  if (!c) return document.createElement('div');
  const d = document.createElement('div');
  d.className = 'chat-msg ' + cls;
  d.textContent = text;
  c.appendChild(d);
  c.scrollTop = c.scrollHeight;
  return d;
}

// ── REFLECTION JOURNAL ──
async function openReflection(stepId) {
  const wrap = document.getElementById('reflect-wrap-' + stepId);
  if (!wrap) return;
  if (!wrap.classList.contains('hidden')) { wrap.classList.add('hidden'); return; }

  const qEl = document.getElementById('reflect-q-' + stepId);
  if (qEl && qEl.dataset.loaded !== 'true') {
    qEl.textContent = '✨ Loading reflection question…';
    try {
      const r = await ajax({ action: 'get_reflection', step_id: stepId });
      if (r.question) { qEl.textContent = r.question; qEl.dataset.loaded = 'true'; }
    } catch { qEl.textContent = 'What did you discover during this step?'; }
  }
  wrap.classList.remove('hidden');
  document.getElementById('reflect-text-' + stepId)?.focus();
}

async function saveReflection(stepId) {
  const text = document.getElementById('reflect-text-' + stepId)?.value ?? '';
  if (!text.trim()) return;
  await ajax({ action: 'save_reflection', step_id: stepId, reflection: text });
  showToast('✅ Reflection saved!');
}

// ── EXTEND buildRoadmapUI to include reflection buttons ──
const _origBuildRoadmapUI = buildRoadmapUI;
// Patch: override buildRoadmapUI to add reflection + fix step IDs
function buildRoadmapUI(steps, tip) {
  const c = document.getElementById('roadmap-steps-list');
  if (!c || !steps) return;
  c.innerHTML = steps.map(s => `
    <div class="roadmap-step ${s.completed ? 'step-done' : ''}" data-step-id="${s.id||''}">
      <div class="step-check" onclick="toggleStep('${s.id||''}',this)">${s.completed ? '✓' : ''}</div>
      <div class="step-body">
        <div class="step-meta">
          <span class="step-num">Step ${s.step_number}</span>
          <span class="step-xp">+${s.xp_reward||10} XP</span>
        </div>
        <div class="step-title">${esc(s.title)}</div>
        <div class="step-desc">${esc(s.description||'')}</div>
        <div class="step-footer">
          <button class="step-link-btn" onclick="loadResources('${s.id||''}',this)">📚 Resources</button>
          <button class="step-link-btn" onclick="toggleNote('${s.id||''}')">📝 Notes</button>
          <button class="step-link-btn" onclick="openReflection('${s.id||''}')">💭 Reflect</button>
        </div>
        <div class="step-resources hidden" id="resources-${s.id||''}"></div>
        <div class="step-note-wrap hidden" id="note-wrap-${s.id||''}">
          <textarea class="note-textarea" id="note-${s.id||''}" placeholder="Write your notes…" onblur="saveNote('${s.id||''}')"></textarea>
        </div>
        <div class="step-note-wrap hidden" id="reflect-wrap-${s.id||''}">
          <div class="reflect-question" id="reflect-q-${s.id||''}">Loading…</div>
          <textarea class="note-textarea" id="reflect-text-${s.id||''}" placeholder="Write your reflection…" rows="3"></textarea>
          <button class="step-link-btn" style="margin-top:.5rem" onclick="saveReflection('${s.id||''}')">Save Reflection</button>
        </div>
      </div>
    </div>`).join('');
  updateProgressUI(0, steps.length);
  activateChatFab();
}

// Activate chat fab when result shows
const _origPopulate = populateResult;
function populateResult(result) {
  _origPopulate(result);
  activateChatFab();
}

// Show fun_fact if present
const __origPopulate = _origPopulate;
const ___origPopulateResult = populateResult;
// patch fun_fact display
document.addEventListener('DOMContentLoaded', () => {
  // If page loads with existing roadmap, activate fab
  if (APP.projectId && APP.status === 'active') activateChatFab();
});
