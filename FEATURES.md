# 20+ Feature Suggestions & Status

## ✅ Implemented (v2)

| # | Feature | Description |
|---|---------|-------------|
| 1 | **Grade level click fix** | Inline `onclick="selectGrade(N)"` — no more JS listener issues |
| 2 | **Student interests input** | Free text: "gaming, nature, robotics…" → AI personalises the project |
| 3 | **Difficulty selector** | 🌱 Easy / ⚡ Medium / 🔥 Hard / 🚀 Expert |
| 4 | **Project duration** | 1 Week / 2 Weeks / 1 Month / Custom |
| 5 | **Language selector** | 🇬🇧 English / 🇳🇱 Dutch / 🇹🇷 Turkish — output in chosen language |
| 6 | **Regenerate button** | Click "🔄 Regenerate" to get a new project without changing settings |
| 7 | **XP/Points system** | Earn XP for generating (+5), creating roadmap (+15), completing steps (+10), finishing project (+50) |
| 8 | **XP display in nav** | Live XP counter in the top navigation bar |
| 9 | **Step notes** | Click 📝 Note on any step to add personal notes (auto-saved) |
| 10 | **AI Resource suggestions** | Click 📚 Resources on any step — AI suggests videos, articles, tools |
| 11 | **Print/Export** | 🖨️ Print button generates a clean printable view |

---

## 🔜 Not Yet Implemented (Ready to Build)

| # | Feature | Difficulty | What it does |
|---|---------|-----------|--------------|
| 12 | **Teacher dashboard** | Medium | Separate login for teachers; see all student projects, add feedback comments |
| 13 | **Thumbs up/down rating** | Easy | Rate the generated project quality; data feeds back into prompt tuning |
| 14 | **Project gallery** | Medium | Browse completed projects from all students (anonymised) for inspiration |
| 15 | **AI chat assistant** | Medium | Chat window on the project page: "How do I do step 3?" — Claude answers in context |
| 16 | **Time tracker** | Easy | Stopwatch per step — track how long you actually spend |
| 17 | **Badges/Achievements** | Medium | Unlock badges: "First Project", "5 Steps in a Day", "Expert Mode" etc. |
| 18 | **Email reminders** | Medium | Daily email "Don't forget your project!" via PHP mail() or Mailgun |
| 19 | **Peer collaboration** | Hard | Invite a classmate by email to work on the same project together |
| 20 | **Image/sketch upload** | Easy | Upload a photo of your work-in-progress for each step |
| 21 | **Project sharing link** | Easy | Copy a public URL to show off your completed project |
| 22 | **Similar projects** | Medium | "Students who chose Physics + Music also built…" recommendations |
| 23 | **AI difficulty check** | Easy | Second API call validates the project is appropriate for the grade level |
| 24 | **Leaderboard** | Easy | School-wide XP leaderboard (opt-in) |
| 25 | **Project templates** | Easy | Start from a blank, saved, or popular template instead of generating |
| 26 | **Progress photos** | Easy | Upload a photo at completion — creates a visual portfolio |
| 27 | **Subject interest profile** | Medium | Track which subjects a student picks most; adapt suggestions over time |
| 28 | **Parent view** | Easy | Read-only link parents can use to follow progress without logging in |

---

## How to implement any suggestion

Pick a number from the list above and say "implement feature 15 (AI chat)" — Claude will add it to the codebase.
