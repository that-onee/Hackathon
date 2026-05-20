# AI-Powered Cross-Disciplinary Project Generator
**Rotterdam TC · Hackathon 19–22 May 2026**

---

## Setup (5 steps)

### 1. Requirements
- PHP 8.1+ with cURL and PDO_MySQL enabled
- MySQL 5.7+ or MariaDB
- Apache or Nginx (XAMPP / WAMP / Laragon on Windows)

### 2. Database
```sql
-- In phpMyAdmin or MySQL CLI:
source /path/to/schema.sql
```
Or paste the contents of `schema.sql` into phpMyAdmin's SQL tab.

### 3. Config
Open `config.php` and fill in:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'ai_project_gen');
define('DB_USER', 'your_mysql_user');
define('DB_PASS', 'your_mysql_password');

define('ANTHROPIC_API_KEY', 'sk-ant-YOUR_KEY_HERE');
```
Get your API key at https://console.anthropic.com/

### 4. Deploy
Copy the entire folder to your web server root, e.g.:
- XAMPP: `C:/xampp/htdocs/ai-project-generator/`
- Linux: `/var/www/html/ai-project-generator/`

### 5. Open in browser
`http://localhost/ai-project-generator/`

---

## File Structure
```
ai-project-generator/
├── index.php           ← Login / Register
├── dashboard.php       ← Your projects list
├── generator.php       ← Project generator (all 4 steps)
├── ajax.php            ← AJAX API endpoint
├── logout.php
├── config.php          ← 🔑 Edit this first
├── db.php              ← Database helpers
├── auth.php            ← Login/register logic
├── api_functions.php   ← Anthropic API + feedback loop
├── courses_data.php    ← Subject data + keyword scoring
├── schema.sql          ← Run this to create the DB
└── assets/
    ├── style.css
    └── app.js
```

---

## Features
- **Login / Register** — secure PHP sessions, bcrypt passwords, MySQL storage
- **Course Selector** — pick grade 1–12 + up to 5 subjects
- **AI Generation** — calls Anthropic Claude API
- **Feedback Loop** — checks learning outcome keywords (target ≥50%), auto-retries up to 3×
- **Roadmap** — AI generates a 7–10 step personalised plan
- **Progress Tracker** — check off steps, live % counter
- **Completion Screen** — celebration + "want to start something new?"
- **Give Up** — abandon project at any time

---

## Hackathon Requirements Checklist
- ✅ Phase 1: Data structure (MySQL) + Input interface (HTML/PHP form)
- ✅ Phase 2: API connection + structured prompt engineering (JSON output)
- ✅ Phase 3: Feedback loop (keyword scoring, loop max 3 attempts, >50% threshold)
- ✅ Phase 4: Testing — works for 1–5 subject combinations across all grade levels
- ✅ Success criteria: handles unrelated subjects (AI bridges them creatively)

---

*Built with PHP + MySQL + Anthropic Claude API*
