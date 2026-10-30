I want to build **nook**, a personal household hub I'll use on my phone in the shop and on my laptop. It has four modules: a grocery list linked to a promo board, a to-do list, a subscription tracker, and a dashboard that ties them together. The design and the behaviour are already decided. Everything you need is in the `nook-design/` folder:

- `DESIGN.md`: the visual spec (fonts, colours per section, components, layout, breakpoints). Follow it exactly.
- `FEATURES.md`: what every screen does, the data model, the quick-add parsing rules, auto-cleanup, notifications, Share Target.
- `prototype/Main.dc.html`: a working click-through prototype. It is in a canvas format that won't run outside the design tool, so don't try to run it. Read it as a reference: the inline styles are the exact visual values, and the JS class at the bottom (`renderVals()` and helper methods) is the exact behaviour, including the parsers and edge cases. `Mobile.dc.html` just mounts the same app at 390×844.
- `reference/look-and-feel.png`: the mood reference for the mobile look.

**Stack:** Laravel 12 + Livewire 3 + Alpine + Tailwind, PostgreSQL, Pest for tests. I run the app with Laravel Herd on Windows; Postgres (and Redis if needed for queues) run in Docker via a docker-compose file. Use Laravel's scheduler and queued notifications for the daily jobs, and web push (VAPID, e.g. `laravel-notification-channels/webpush`) for notifications. It's a single-user app for me, but use normal auth (Breeze or Fortify) so it can be deployed safely later on my VPS with Coolify.

**How I want to work:**
1. Start by reading all four files, then give me a short build plan split into milestones. Each milestone should end in something I can click through. Wait for my OK before writing code.
2. Suggested order:
   - **M1**: project setup, auth, layout shell (sidebar ≥900px / floating nav with centre FAB below), section gradients, fonts, design tokens in Tailwind config.
   - **M2**: Groceries list with quick add + grouping.
   - **M3**: Promo board, photo upload, polaroids, the promo sheet, linking, auto-link, expiry + archive (scheduled job).
   - **M4**: To-do with quick-add parsing and recurring tasks.
   - **M5**: Subscriptions: receipt, trial card, category chart, cancel/resume, savings.
   - **M6**: Home dashboard.
   - **M7**: PWA (manifest, service worker, Android `share_target` → add-promo form) and web push (morning nudge, trial reminders).
3. Work on a feature branch per milestone and open a PR into `main`. Use conventional-commit messages with a scope, e.g. `feat(promos): add polaroid board`.
4. Write Pest tests for the logic that matters: the grocery and task quick-add parsers (use the examples in FEATURES.md), auto-linking, promo expiry, recurring rollover, monthly/yearly totals, and trial reminder scheduling.
5. Seed the database with the same sample data as the prototype, so the screens look like the design straight away.

**Design rules that matter most:**
- Reusable Blade components: polaroid, receipt, chip, checkbox, sheet, card, toast, nav.
- No emoji in the UI; icons are Lucide.
- Touch targets ≥44px, real buttons and labels, a visible peach focus ring.
- The same app must work at 390px wide and on a laptop.

Ask me if anything in the spec is unclear or contradicts itself rather than guessing.
