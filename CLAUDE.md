# nook

A personal household hub for one person, used on a phone in the shop and on a laptop at a desk: a grocery list linked to a promo board, a to-do list, a subscription tracker, and a Home dashboard that ties them together. Single user, but behind normal auth so it can be deployed.

## Read before you build

- `docs/nook-design/DESIGN.md`: the visual spec (fonts, colours per section, components, layout, breakpoints). Follow it exactly.
- `docs/nook-design/FEATURES.md`: what every screen does, the data model, quick-add parsing, auto-cleanup, notifications, Share Target.
- `docs/nook-design/prototype/Main.dc.html`: the click-through prototype. Read it, don't run it. Its inline styles are the exact visual values, and the JS class at the bottom (`renderVals()` and the helper methods) is the exact behaviour, including the parsers and edge cases. Where DESIGN.md and the prototype disagree, the prototype wins.
- `docs/nook-design/reference/look-and-feel.png`: the mood reference for the mobile look.
- `docs/decisions.md`: decisions that change or fill gaps in the design docs. They win over everything above.

## Stack

Laravel 13 on PHP 8.4, Livewire 4 + Alpine, Tailwind v4 (design tokens in the `@theme` block of `resources/css/app.css`; there is no `tailwind.config.js`), PostgreSQL 18, Pest 5, Larastan (level 6), Pint. Queue, cache and sessions use the database driver (no Redis). The app timezone is Europe/Brussels.

## Local setup (main checkout)

- `docker compose up -d` starts Postgres 18 on host port 5434 (databases `nook`, `nook_testing`, `nook_factory`) and Mailpit (http://localhost:8025).
- Laravel Herd serves the main checkout at http://nook.test.
- First run: `composer install && npm install && cp .env.example .env && php artisan key:generate && php artisan migrate --seed && npm run build`.
- `composer dev` runs the queue worker, the scheduler and Vite together.

## Set up a fresh checkout (factory worktrees)

With Docker running, in the worktree:

```bash
composer install --no-interaction
npm ci --no-audit --no-fund
cp .env.example .env
sed -i 's/^DB_DATABASE=nook$/DB_DATABASE=nook_factory/' .env
php artisan key:generate --no-interaction
php artisan migrate:fresh --seed --force
npm run build
```

Worktrees use the `nook_factory` database, so they never touch the main checkout's data. Factory jobs share `nook_factory` and `nook_testing`, so run one job at a time.

## Dev server on a given port

`php artisan serve --host=127.0.0.1 --port=<port>`, in the background, then open `http://127.0.0.1:<port>`. It serves the built assets in `public/build`, so run `npm run build` again after changing CSS, JS or classes in Blade.

Never run `npm run dev` or `composer dev` in a factory worktree. Vite writes `public/hot`, and once Vite stops, every page asks the dead Vite server for its CSS and JS. If you find `public/hot` in a worktree, delete it.

Stop the server when you're done, by its port. Never stop processes by name: Herd runs on the same `php.exe`.

```bash
taskkill //F //PID $(netstat -ano | grep -E "127\.0\.0\.1:<port> .*LISTENING" | awk '{print $5}' | head -1)
```

That ends the listener on the port, and `artisan serve` exits with it.

Every page except login is behind auth. The seeder creates the local account `ivayla@example.com` / `password` (local and factory databases only, never production).

## Validation

- `composer test` runs Pint (`pint --test`), Larastan (`phpstan analyse`) and the Pest suite on Postgres (`nook_testing`; Docker must be running). All three must pass.
- `npm run build` must succeed.
- One test file: `php artisan test tests/Feature/SomeTest.php`. Format only the files you changed: `vendor/bin/pint --dirty`.
- CI (`.github/workflows/ci.yml`) runs the same checks plus a Docker image build on every pull request.

## Conventions

- Conventional commits with a scope and a one-line subject: `feat(promos): add polaroid board`, `fix(todo): keep focus after quick add`, `test(groceries): cover the quick-add parser`, `chore(ci): cache composer`.
- Logic that tests must cover (quick-add parsers, auto-linking, promo expiry, recurring rollover, monthly and yearly totals, reminder scheduling) lives in plain PHP classes under `app/`, not inside Livewire components or Blade views, and has Pest tests. Use the examples in FEATURES.md as test cases.
- Pages are class-based full-page Livewire components in `app/Livewire/Pages`, created with `php artisan make:livewire Pages/Name --class` (no single-file components), and render in the one layout, `resources/views/layouts/app.blade.php`, with `#[Title]` setting the page title. Routes are added with `Route::livewire()` and named after their `App\Enums\Section` value; the enum also holds each section's labels, icon, ground and FAB label.
- Reusable Blade components in `resources/views/components/`: polaroid, receipt, chip, checkbox, sheet, card, toast, nav, and anything else that repeats.
- Design tokens: `text-accent`, `bg-accent` and `text-on-accent` follow the section's ground, so don't hard-code gold, apricot or peach for the accent. Fonts are `font-display` (Fraunces), `font-sans` (DM Sans, the default), `font-script` (Caveat) and `font-mono` (IBM Plex Mono).
- Icons are Lucide, rendered as `<x-lucide-…>` components with `aria-hidden="true"` (24px grid, round caps). The SVGs say `stroke-width="2"`, so give each icon the 1.75 stroke with `stroke-[1.75]`. No emoji anywhere in the UI.
- Accessibility: real `<button>`s and `<label>`s, `aria-pressed` on toggles and chips, `aria-label` on icon-only buttons, touch targets of at least 44px, the peach focus ring (2px solid `#f0b48a`, offset 2px) on everything interactive, and state never shown by colour alone.
- One codebase for a 390px phone and a laptop. The breakpoint is 900px, and `wide:` is the only responsive variant (Tailwind's default breakpoints are removed).
- Money is `€3.49`. Dates are relative when near ("today", "tomorrow", "Sat"), otherwise "10 Nov"; the long form is "Sun 1 Nov". Weeks start on Monday.
- Seeders reproduce the prototype's sample data, with dates relative to the day you seed.

## Deploy

Every merge to `main` builds `ghcr.io/ivailipm/nook` and calls Coolify's deploy webhook (skipped until the `COOLIFY_WEBHOOK` and `COOLIFY_TOKEN` repository secrets exist). `docker-compose.prod.yml` runs that image as `app` (web, migrations on start), `queue` and `scheduler`, plus Postgres. Don't change CI, Docker or deploy config unless the task asks for it.

## This machine

Windows. Bash is Git Bash: `php` and `composer` come from wrappers in `~/bin` that call Herd's PHP 8.4, and Python is `python` (`python3` is the Microsoft Store stub). Other projects' containers use host ports 5432, 5433, 3000, 6379, 8080 and 9200.

## Software factory

`/factory next` builds the first unchecked item in `factory/backlog.md` through spec → build → review → approve, and every job ends as a pull request into `main` that a human merges (see `.claude/skills/factory/SKILL.md`). Job state lives in `factory/jobs/` (gitignored). Dashboard: `cd factory && python -m http.server`, then open http://localhost:8000/dashboard.html.
