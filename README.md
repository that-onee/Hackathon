# AI Project Generator — Rotterdam TC Hackathon
## 19-22 May 2026

An AI-powered cross-disciplinary project generator built with Node.js + Gemini API.

---

## Quick Start

### 1. Install dependencies
```bash
npm install
```

### 2. Run
```bash
npm start
```

### 3. Open in browser
```
http://localhost:3000
```

---

## Project Structure

```
ai-project-gen/
├── server.js          ← Express server + Gemini API proxy
├── package.json
├── public/
│   └── index.html     ← Full frontend (EN + TR, all screens)
└── README.md
```

---

## How it works

1. Student selects grade level + 1–5 courses + optional interests
2. Frontend sends to `/api/generate` (Express proxy)
3. Server calls Gemini 2.0 Flash with a structured prompt
4. Response is validated: checks if ≥50% of learning outcomes are covered
5. If coverage is too low → automatic retry with feedback (max 3 attempts)
6. Project is displayed with learning outcomes, steps, and coverage %
7. Student can generate a phase-based learning path with checkable tasks
8. Progress bar updates live; at 100% → "Learn something new" or "I'm done"

---

## Configuration

The Gemini API key is already set in `server.js`.
To use a different key, set the environment variable:

```bash
GEMINI_API_KEY=your_key_here npm start
```

Or change the default in `server.js`:
```js
const GEMINI_KEY = process.env.GEMINI_API_KEY || 'YOUR_KEY_HERE';
```

---

## Requirements

- Node.js 16+
- Internet connection (for Gemini API calls)

---

## Success Criteria (per hackathon spec)

- ✅ Works with 1–5 course selections
- ✅ Generates creative projects even for unrelated subjects (e.g. PE + Math)
- ✅ Feedback loop: detects missing learning outcomes and auto-retries
- ✅ Bilingual: English & Turkish
- ✅ Learning path with progress tracking
- ✅ Completion flow: learn more or quit
