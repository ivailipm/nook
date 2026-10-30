---
name: factory
description: Run the software factory. Use when the user types /factory <feature>, /factory next, /factory approve <job-id>, or /factory rework <job-id> <note>. The session that runs it becomes the orchestrator of the spec → build → review → approve loop.
---

# Factory

You are the orchestrator for one job. You run the loop below, spawn every agent,
and are the only writer of your job's `job.json`. You never write code, specs,
reviews or decisions yourself. Agents do the work and each writes one file; you
move the job along and keep `job.json` current.

## The loop

```
feature → spec-writer → builder → [security, ux, ui, code] → WAIT FOR ALL
                           ▲                                      │
                           └──── any CHANGES: resume builder ◄────┤
                                                                  ▼ all PASS
            you merge ◄─ needs-human ◄─ push + open PR ◄─ APPROVE | ESCALATE ─ approver
```

Every job ends as a pull request into `main`. The factory never merges on its
own: the user clicks through the PR and merges it, on GitHub or with
`/factory approve`. The approver's verdict decides what the PR says, not whether
it exists. Push only `factory/<job-id>` branches. Never commit to or push `main`,
never force-push, and never merge a PR unless the user runs `/factory approve`.

| Step         | Agent (`subagent_type`) | Writes                                      |
| ------------ | ----------------------- | ------------------------------------------- |
| spec         | `spec-writer`           | `spec.md`                                   |
| build        | `builder`               | `build.md` + commits on `factory/<job-id>`  |
| review       | `reviewer-security`     | `round-N/review-security.md`                |
|              | `reviewer-ux`           | `round-N/review-ux.md`                      |
|              | `reviewer-ui`           | `round-N/review-ui.md`                      |
|              | `reviewer-code`         | `round-N/review-code.md`                    |
| approve      | `approver`              | `decision.md`                               |

`MAX_RESUMES = 2`: after round 1 the builder can be resumed at most twice, so a
run has at most 3 review rounds. To change the factory, edit this file and the
agent files in `.claude/agents/`.

Every agent reads `AGENTS.md` (or `CLAUDE.md`) for the repo's setup, dev server,
validation commands and conventions. Nothing repo-specific lives in these files.

## Parallel jobs

Every job runs in its own git worktree on its own branch, so several jobs can run
at once, one per Claude session. To run three features, open three sessions in
this repo and start `/factory` in each. Your session orchestrates only the job it
started or was handed.

- Your session stays in the main checkout. Agents work in the job's worktree.
  The main checkout is the user's, and it may be dirty or on another branch.
  You only add and remove worktrees from it; merging happens on GitHub.
- Job state lives in `factory/jobs/<job-id>/` in the main checkout, never in a
  worktree. Each job has its own `job.json`, so sessions never write the same
  file.
- Each job gets its own dev server ports and agent-browser sessions (see
  **Prompts**), so reviewers of different jobs never see each other's pages.

## Files

`<repo>` is the main checkout (`git rev-parse --show-toplevel` from your
session) and `<name>` is its folder name. `factory/jobs/` is gitignored runtime
state that never lands in a commit.

```
<repo>/factory/backlog.md                      queue for /factory next
<repo>/factory/jobs/<job-id>/job.json          the job's state (you write it, the dashboard reads it)
<repo>/factory/jobs/<job-id>/spec.md
<repo>/factory/jobs/<job-id>/build.md
<repo>/factory/jobs/<job-id>/rework-<k>.md     written by you, from /factory rework
<repo>/factory/jobs/<job-id>/round-<N>/review-{security,ux,ui,code}.md
<repo>/factory/jobs/<job-id>/decision.md
<repo>/factory/jobs/<job-id>/pr-body.md        written by you, from decision.md and build.md
<repo>/../<name>-factory/<job-id>/             the job's worktree, on factory/<job-id>
```

### job.json

```json
{ "id": "003-rate-limiting", "feature": "Rate limit the signup form",
  "branch": "factory/003-rate-limiting",
  "worktree": "/Users/you/code/my-app-factory/003-rate-limiting",
  "stage": "review", "round": 2,
  "reviews": { "security": "CHANGES", "ux": "PASS", "ui": "pending", "code": "PASS" },
  "pr": null, "pr_url": null }
```

- `stage` is one of `spec`, `build`, `review`, `approve`, `merged`,
  `needs-human`. Worktree setup counts as `spec`.
- `round` is the current review round (0 until the first round starts).
- `reviews` holds the current round only. Each value is `pending`, `PASS` or
  `CHANGES`. Earlier rounds live in their `round-<N>/` folders, which the
  dashboard reads directly.
- `pr` and `pr_url` stay `null` until **Open PR** creates the pull request.
- Rewrite the whole file on every change. `worktree` is always an absolute path.

### Prompts

Every agent prompt starts with the job's absolute paths:
`Job folder: <repo>/factory/jobs/<job-id>/. Worktree: <worktree>. Branch: factory/<job-id>.`
Below, `<paths>` stands for that sentence.

Agents that open pages also get a port. With `n` the job number (`003` → 3),
the builder gets `5100 + 10n`, `reviewer-ux` gets `5101 + 10n` and `reviewer-ui`
gets `5102 + 10n`, appended as ` Port: <port>.`

## /factory <feature>

1. **Create the job.** `mkdir -p factory/jobs`, then pick the next 3-digit number
   after the highest numbered folder in `factory/jobs/` (`001` if there are
   none) and a 2–4 word kebab-case slug of the feature: `004-blog-reading-time`.
   Claim it with `mkdir factory/jobs/<job-id>` (no `-p`). If that fails because
   another session took the number, pick the next number and try again.
   The worktree path is `$(cd .. && pwd)/$(basename "$PWD")-factory/<job-id>`,
   run from `<repo>`. Write `job.json` with that `worktree`, `stage: "spec"`,
   `round: 0` and all reviews `pending`.
2. **Worktree.** Create the branch and its worktree from `origin/main`. PRs are
   merged on GitHub, so the local `main` may be behind:
   ```bash
   git fetch origin main
   git worktree add -b factory/<job-id> <worktree> origin/main
   ```
   Then, in the worktree, run the setup AGENTS.md gives for a fresh checkout
   (installing dependencies, for example). If any of it fails, stop and tell
   the user.
3. **Spec.** Spawn `spec-writer` with the prompt
   `<paths> Feature: <feature, verbatim>`.
   Wait for it to finish, then check that `spec.md` exists.
4. **Build.** Set `stage: "build"`. Spawn `builder` **with the name
   `builder-<job-id>`** and the prompt `<paths> Build round 1. Port: <port>.`
   Wait for it to finish, then go to **Build check**.

### Build check

Run **Clean up**, then read the first line of `build.md`.

- `STATUS: READY`: start the next review round.
- `STATUS: BLOCKED`: go to **Needs human**.

### Clean up

Agents sometimes leave a dev server running. For each of the job's three
ports (builder, `reviewer-ux`, `reviewer-ui`), if
`netstat -ano | grep -E "127\.0\.0\.1:<port> .*LISTENING"` prints a line, stop
it with the port-scoped command in AGENTS.md. Never stop processes by name.
If `<worktree>/public/hot` exists, an agent ran Vite: delete the file and tell
the user, since a Vite server may still be running from the worktree.

### Review round N

1. Set `stage: "review"`, `round: N` and all four reviews to `pending`. Create
   `factory/jobs/<job-id>/round-<N>/`.
2. Spawn all four reviewers **in one message**, so they run in parallel. Each
   gets the prompt `<paths> Review round N.`, plus its port for `reviewer-ux`
   and `reviewer-ui`.
3. **WAIT FOR ALL.** Each time a reviewer finishes, read the first line of its
   file (`VERDICT: PASS` or `VERDICT: CHANGES`) and write that verdict to
   `job.json` straight away, so the dashboard chips fill in as they land. The
   round is done only when all four `review-*.md` files exist in `round-<N>/`.
   Never resume the builder mid-round. If a reviewer finishes without writing
   its file, or writes a first line that isn't a verdict, stop and tell the user.
4. Run **Clean up**, then decide:
   - **All PASS**: go to **Approve**.
   - **Any CHANGES, resumes left**: set `stage: "build"`, then resume the same
     builder with `SendMessage` to `builder-<job-id>`:
     `Round N reviews are in factory/jobs/<job-id>/round-<N>/. Address every review with VERDICT: CHANGES, commit, and update build.md for round N+1.`
     Wait for it to finish, then go to **Build check**.
   - **Any CHANGES, no resumes left**: write `decision.md` yourself, reading
     `ESCALATE`, then a blank line, then
     `Review rounds exhausted. Outstanding CHANGES in round-<N>/ from: <reviewers>.`
     Go to **Needs human**.

### Approve

Set `stage: "approve"`. Spawn `approver` with the prompt `<paths> Final round: N.`
When it finishes, check that the first line of `decision.md` is `APPROVE` or
`ESCALATE` (if it's neither, stop and tell the user), then go to **Needs human**.
Both verdicts end as a PR for the user to merge.

### Open PR

Skip this step, and say so, when the branch has no commits on top of
`origin/main` (`git -C <worktree> rev-list --count origin/main..HEAD` prints `0`),
which can happen when the build is blocked.

1. Push the job branch: `git -C <worktree> push -u origin factory/<job-id>`.
2. Write `factory/jobs/<job-id>/pr-body.md`:
   ```
   **Factory verdict:** <first line of decision.md, or BLOCKED when there is no decision.md>

   <decision.md from its second line on, when it exists>

   ---

   <build.md from its second line on>
   ```
3. If `job.json` has no `pr` yet, open the PR, then record its number and URL in
   `job.json`:
   ```bash
   gh pr create --base main --head factory/<job-id> --title "<spec.md's # heading, without the #>" --body-file factory/jobs/<job-id>/pr-body.md
   gh pr view factory/<job-id> --json number,url
   ```
   If it already has one (after a rework), the push has updated it. Post the new
   verdict with `gh pr comment <pr> --body-file factory/jobs/<job-id>/pr-body.md`.
4. If `git push` or `gh` fails, the job still goes to needs-human. Tell the user
   what the command printed.

### Needs human

Run **Open PR**, then set `stage: "needs-human"`. The branch and its worktree
stay for the human to inspect. Tell the user which job is waiting, give the PR
link and the worktree path, and quote `decision.md` (or the BLOCKED reason from
`build.md`). Remind them that they approve by clicking through and merging the
PR, then running `/factory approve <job-id>`, or send it back with
`/factory rework <job-id> <note>`.

## /factory next

Read `factory/backlog.md` and take the first `- [ ]` item. Claim it with one
Edit that replaces its exact line with `- [x] <item> → claimed`. If the Edit
fails because the line changed, another session claimed it: re-read the backlog
and try the next `- [ ]` item. If none is left, say so and stop.

Run `/factory <item>`, and once step 1 has given it a job id, replace `claimed`
on its backlog line with the job id.

## /factory approve <job-id>

The job must be in `needs-human` with a `pr`. The user runs this once they have
clicked through the PR.

1. If `gh pr view <pr> --json state` says `MERGED` (the user merged it on
   GitHub), go to step 3.
2. Merge it: `gh pr merge <pr> --merge --delete-branch`. If that fails (checks
   still running or failing, conflicts, branch protection), leave the job in
   `needs-human` and tell the user what `gh` printed.
3. Clean up, then set `stage: "merged"`:
   ```bash
   git worktree remove <worktree>
   git branch -D factory/<job-id>
   git fetch origin main
   ```
   `-D` because the merge commit is on GitHub, not on the local branch. If
   removing the worktree fails, the job is still merged: tell the user what
   `git worktree remove` printed.

## /factory rework <job-id> <note>

The job must be in `needs-human`.

1. Write the note, verbatim, to `factory/jobs/<job-id>/rework-<k>.md`, where `k`
   is the next unused number.
2. Set `stage: "build"`.
3. Spawn a fresh `builder` named `builder-<job-id>` with the prompt
   `<paths> Rework: read rework-<k>.md and address it, then update build.md for round N+1. Port: <port>.`
   (`N` is the job's last round.) A rework run gets a fresh `MAX_RESUMES`.
4. Continue from **Build check**, exactly like a new job.

## Reporting

Keep the user posted with one short line per transition, for example
`003-rate-limiting: round 2, security CHANGES, ux/ui/code PASS → resuming builder`.
When the job reaches needs-human, give the PR link and the approver's verdict and
why. When `/factory approve` finishes, say that it merged.
