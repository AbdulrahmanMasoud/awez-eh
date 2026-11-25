# Tasks

The owner and Claude (on claude.ai) plan the work here. Claude Code carries it out.

- **A task** is `docs/tasks/NNN-slug.md`: the brief, written by Claude.
- **A report** is `docs/reports/NNN-slug.md`: what Claude Code did, written by Claude Code at the end.

## Index

| #   | Task                                                                   | Status | PR  |
| --- | ---------------------------------------------------------------------- | ------ | --- |
| 001 | [Project skeleton and quality gate (BUILD_PLAN 1.1)](001-project-skeleton.md) | done   | #1  |
| 002 | [Core support (BUILD_PLAN 1.2)](002-core-support.md)                          | done   | #3  |
| 003 | [Data model: geography and content (BUILD_PLAN 1.3a)](003-content-data-model.md) | done   | #5  |
| 004 | [Data model: interaction and business (BUILD_PLAN 1.3b)](004-interaction-data-model.md) | done   | #6  |
| 005 | [Sign-in (BUILD_PLAN 1.4)](005-sign-in.md)                                     | in progress |     |

The statuses are `planned` (no brief yet, do not start), `ready`, `in progress`, `review` (the PR is open and the report is written), `done` (merged) and `dropped`.

## "start task NNN"

1. **Read the brief.**
    - It is `docs/tasks/NNN-*.md` in the main checkout. Claude writes it there, so it may not be committed yet.
    - Read this file too.
    - If the brief depends on a task that is not merged, stop and say so.
2. **Make a worktree.**
    - Run `git fetch origin`, then `git worktree add .worktrees/NNN-slug -b task/NNN-slug origin/main`.
    - Work only inside that worktree. Other tasks may be running in the main checkout or in other worktrees at the same time.
    - If `.worktrees/` is not in `.gitignore`, add it in this task's PR.
3. **Bring the brief in.**
    - Copy the brief, and this README if `origin/main` lacks it, into the worktree's `docs/tasks/`.
    - Set the task's row in the index to `in progress`.
    - Commit them with the work.
4. **Do the work** as the brief says, under CLAUDE.md's rules.
5. **Open the PR.**
    - Its title ends with `(task NNN)`.
    - Wait for CI and fix what fails. A failure that is not from this task is fixed too, or reported.
    - Never merge. The owner does.
6. **Write the report** at `docs/reports/NNN-slug.md` (template below).
    - Commit it in the same PR.
    - Set the task's row to `review`, with the PR number.
7. **End the session with three lines:**
    - the PR link;
    - CI's state;
    - the report's path.

    Everything else belongs in the report.

**Housekeeping.** When a task starts:

- set to `done` the rows of earlier tasks whose PRs are merged (`gh pr view`), and remove their worktrees;
- in the main checkout, remove any untracked file in `docs/tasks/` that is now identical on `origin/main`, so pulling `main` is not blocked. A brief not yet on `main` stays.

## "continue task NNN: …"

- Work in the same worktree and branch.
- Add a dated `## Update` section at the end of the report, saying what changed and why.
- Push, wait for CI, and end with the same three lines.

## Report template

```markdown
# Report NNN: <title>

- PR: #<n> (<link>). CI: green / red (why).
- Branch: task/NNN-slug. Commit: <sha>.

## What changed

Short bullets, in the owner's terms. Say what a visitor, a business or the owner will notice.

## Decisions for the owner

Numbered. Each one gives the choice, what was done meanwhile, and the cost of each way.
Write "None" if there are none.

## The owner's steps

In order, exactly: DNS records, Dokploy domains and env names (never values), settings, what to check after the deploy.
Write "None" if there are none.

## Verification

The tests added, the suites run with their counts, and the measurements (sizes, timings, memory).

## Not done and follow-ups

What the brief asked for and was not done, and why. Also anything found on the way that is out of scope.

## Deploy notes

Migrations, new services, new packages with versions, and anything else merging changes in production.
```
