# Decisions

Where these differ from `docs/nook-design/` (the prototype included), these win. Confirmed on 2026-10-30.

## Stack and infrastructure

- Laravel 13 and Livewire 4 (the design handoff says 12 and 3).
- Auth is Laravel Fortify, headless, with the login screen built in the nook look. Registration is off; the user is created with `php artisan nook:user`.
- Queue and cache use the database driver on Postgres. No Redis.
- Timezone Europe/Brussels; weeks start on Monday.
- Fonts are self-hosted and bundled by Vite instead of loaded from Google Fonts, so they work offline in the PWA and visitors' IPs aren't sent to Google.
- Tailwind v4: the design tokens live in the `@theme` block of `resources/css/app.css`.

## Behaviour

- The quick-add parsers match the prototype exactly, except for two prototype bugs that are fixed:
  - Weekday matching only takes real weekday words (`mon`, `monday`, `tue`, `tues`, `tuesday`, …), so "monitor" isn't Monday and "sunscreen" isn't Sunday.
  - `!medium` is read as a whole and leaves nothing behind (the prototype left "ium" in the title).
- Recurring tasks: completing one creates the next, due 7 days after the completed one's due date. If that date is still in the past, the next one is due on the next of that weekday from today. Unticking and re-ticking doesn't create a second copy.
- Home "Today" card: tasks due today plus open overdue tasks. Tasks completed on earlier days don't show.
- To-do "Done" group: tasks completed in the last 7 days.
- The add-promo form has an optional Note field (the promo sheet shows the note).
- The promo board's store filter includes an "Other" chip.
- Home "Coming up" shows the real charge for yearly subscriptions, not price ÷ 12.
- Share target: the service worker catches the share POST and hands the photo to the add-promo form, so no route needs a CSRF exemption.
- The 08:00 push is skipped when nothing is due. "A deal on your list ends today" is a line in that same push, not a separate notification.

## Still open

These get decided before the milestone that needs them, and recorded above.

- Trial reminders: FEATURES.md gives every trial pushes 3 days and 1 day before it converts, but the "Remind me" button toggles a day-before reminder. (M5)
- What happens when a subscription's next payment date passes, and when a trial ends without Keep it or Cancel trial. (M5)
- The archive's window ("N expired this week" or every expired promo), and when expired promos and their photos are deleted. (M3)
- Offline in the shop: the last-loaded list read-only, or ticking offline and syncing later. (M7)
- Editing and deleting tasks and subscriptions (the spec only has cancel and resume). (M4, M5)
