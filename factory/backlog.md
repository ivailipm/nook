# Backlog

`/factory next` builds the first unchecked item and checks it off with its job id.

- [ ] M1 app shell: Livewire 4 + Alpine, the design tokens from DESIGN.md §2–3 in Tailwind's @theme, self-hosted Fraunces / DM Sans / Caveat / IBM Plex Mono, Lucide icons, one layout with the per-section gradient, the sidebar (≥900px) and the floating nav with the centre FAB (<900px), and placeholder pages for Home, Groceries, Promo board, To-do and Subscriptions
- [ ] M1 auth: Fortify login (no registration) in the nook look, every page behind auth, logout, `php artisan nook:user` to create the user, and the seeded local account from CLAUDE.md
- [ ] M1 shared components: card, chip, checkbox (grocery and task variants), sheet (bottom sheet below 900px, centred dialog above), and toast; the FAB and "Add something" open the "Add something" chooser sheet
- [ ] M2 groceries data and parser: GroceryItem model, migration, factory and seeder with the prototype's items, and the quick-add parser from FEATURES.md §2 and the prototype's parseItem, with Pest tests for every example
- [ ] M2 groceries list: quick add (Enter adds and keeps focus), group by Store or Category with "N left" cards, tick and untick, remove, "Clear N ticked", the List | Promo board switch, and the full add-item sheet from the FAB
- [ ] M3 promos data: Promo model with active and expired scopes and tones, a seeder with the prototype's promos, auto-link when an item is added (FEATURES.md §2), and the daily expiry job with the archive rules in docs/decisions.md, with Pest tests for auto-link and expiry
- [ ] M3 promo board: polaroid component, the board grid sorted by soonest end with store filter chips (including Other), "until" labels and the "on list" pill, the archive toggle, the snap card, and promo badges on list items
- [ ] M3 promo sheet and add promo: the promo sheet (got it, unlink, add to my list, delete), and the add-promo sheet with photo upload (downscaled, EXIF stripped), store, valid from and until, product, deal, note and link chips
- [ ] M4 tasks data and parser: Task model, migration, factory and seeder with the prototype's tasks, the quick-add parser from FEATURES.md §4 with the fixes in docs/decisions.md, and recurring rollover, with Pest tests for both
- [ ] M4 to-do screen: quick add, tag filter chips, Overdue / Today / Upcoming / Done groups, task rows with priority, due, repeat and tag meta, the full add-task sheet, and the morning-nudge card toggle (saving the setting only)
- [ ] M5 subscriptions data: Subscription model, migration, factory and seeder with the prototype's subscriptions, monthly and yearly totals, the category breakdown and the savings line, with Pest tests for the totals and savings
- [ ] M5 subscriptions screen: receipt component with the zig-zag tear, the trial card (remind, keep, cancel), the by-category bar and legend, the all-subscriptions list with cancel and resume, and the add-subscription sheet
- [ ] M6 home dashboard: greeting by the hour, today's open tasks in words, the three chips, the Today and Groceries cards with inline ticking, Deals ending soon, Coming up, the bell toggle, and live counts in the sidebar
- [ ] M7 PWA: web app manifest (theme #190a0d, icons), a service worker that caches the app shell and the last-loaded grocery list, and the Android share_target that opens the add-promo form with the shared photo
- [ ] M7 web push: VAPID keys, the push subscription flow, the 08:00 morning nudge (FEATURES.md §6 and docs/decisions.md) and trial reminders, with Pest tests for what is sent when
