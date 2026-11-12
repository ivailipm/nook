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
- Home "Today" card: tasks due today, open overdue tasks, and overdue tasks ticked today, which stay struck through until midnight so a mis-tap can be undone in place. Tasks completed on earlier days don't show. Confirmed on 2026-11-03.
- To-do "Done" group: tasks completed in the last 7 days.
- The add-promo form has an optional Note field (the promo sheet shows the note).
- The promo board's store filter includes an "Other" chip.
- You can add stores of your own in the "Your stores" sheet, opened from the dashed + after the store chips on the promo board and in the add-item and add-promo forms. They go between Colruyt and Other in every store list and in the grouping by store, and the quick add's `@store` finds them by prefix (a built-in store still wins a shared prefix: `@c` is Colruyt). Removing one moves its items and deals to Other. The built-in stores stay. Added on 2026-11-12.
- Home "Coming up" shows the real charge for yearly subscriptions, not price ÷ 12.
- Share target: the service worker catches the share POST and hands the photo to the add-promo form, so no route needs a CSRF exemption.
- The 08:00 push is skipped when nothing is due. "A deal on your list ends today" is a line in that same push, not a separate notification.
- Ticking a grocery item moves it to the end of its group, as the prototype does (FEATURES.md says it stays in place). Confirmed on 2026-10-31.
- Expired promos leave the board and show in the archive during the week they expired (Monday to Sunday). A daily job deletes each promo and its photo 30 days after its `valid_until`. Confirmed on 2026-10-31.
- When a subscription's next payment date passes, a daily job moves it on by one month or one year, following its billing cycle, so Home's "Coming up" always shows the next charge. Cancelled subscriptions don't move. Confirmed on 2026-11-03.
- A trial whose end date passes without Keep it or Cancel trial becomes a normal paid subscription at its price, and its date then rolls forward the same way. Confirmed on 2026-11-03.
- Every trial gets pushes 3 days and 1 day before it turns paid, by default. The trial card's button turns them off for that trial. Confirmed on 2026-11-03.
- A cancelled free trial doesn't count as money saved; only cancelled paid subscriptions do. Cancelling a trial must leave it recognisable as a trial. Confirmed on 2026-11-03.
- Offline in the shop: the PWA shows the last-loaded grocery list read-only. Ticking offline and syncing later is a separate, later job. Confirmed on 2026-11-03.
- Tasks and subscriptions can be edited and deleted: tapping one opens its sheet to change or delete it. Confirmed on 2026-11-03.
- The Home all-clear line changes with the time of day, on the greeting's boundaries (05:00, 12:00, 18:00). The evening wording stays `all clear today. go enjoy your evening`; from 05:00 to 11:59 it reads `all clear today. go enjoy your morning` and from 12:00 to 17:59 `all clear today. go enjoy your afternoon`. Confirmed on 2026-11-03.

## Still open

Nothing right now. New questions get decided before the milestone that needs them, and recorded above.
