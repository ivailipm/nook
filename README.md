# nook

A personal household hub for one person, on a phone in the shop and on a laptop at a desk: a grocery list linked to a promo board, a to-do list, a subscription tracker, and a dashboard that ties them together.

- Design and behaviour: [docs/nook-design](docs/nook-design), plus [docs/decisions.md](docs/decisions.md)
- Repo instructions for people and agents: [CLAUDE.md](CLAUDE.md)

Laravel 13 · Livewire 4 · Alpine · Tailwind v4 · PostgreSQL 18 · Pest

## Run it locally

You need Laravel Herd (PHP 8.4), Node 24+ and Docker.

```bash
docker compose up -d            # Postgres on port 5434, Mailpit on http://localhost:8025
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
npm run build                   # or `composer dev` for Vite, the queue worker and the scheduler
herd link nook                  # serves http://nook.test
```

Log in as `ivayla@example.com` / `password`; the seeder creates that account everywhere except production.

`composer test` runs Pint, Larastan and the Pest suite (on the `nook_testing` database).

## CI and deploys

Every pull request runs the checks and builds the Docker image. Every merge to `main` pushes `ghcr.io/ivailipm/nook` and asks Coolify to deploy it.

One-time Coolify setup:

1. On the server, log in to GHCR with a token that can read packages: `docker login ghcr.io -u ivailipm`.
2. In Coolify, create a Docker Compose resource from this repo using `docker-compose.prod.yml`.
3. Set `APP_KEY` (from `php artisan key:generate --show`), `APP_URL` and `DB_PASSWORD`, and point the domain at the `app` service on port 8080.
4. Add the resource's deploy webhook URL and a Coolify API token to the GitHub repo as the secrets `COOLIFY_WEBHOOK` and `COOLIFY_TOKEN`. Until then, the deploy step is skipped.
5. After the first deploy, open a terminal on the `app` container in Coolify and run `php artisan nook:user` to create your login. There's no registration page.

## Software factory

Features are built with the [`/factory`](.claude/skills/factory/SKILL.md) skill: `/factory next` takes the first item in [factory/backlog.md](factory/backlog.md) through spec → build → review → approve, and opens a pull request for you to click through and merge.
