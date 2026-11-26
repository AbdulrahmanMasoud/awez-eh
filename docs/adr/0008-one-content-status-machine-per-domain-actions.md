# 0008. One status machine for moderated content, with each domain's own Actions and one review queue

## Context

Properties, craftsmen's profiles, places and used items share a lifecycle (ARCHITECTURE §6): an owner drafts, submits, pauses and closes; a moderator approves, rejects and takes down. Each domain writes only its own tables (CLAUDE.md), and steps 2.3 to 2.5 will add three more moderated kinds after real estate. The admin panel's design has one review queue for all of them, with a tab per kind.

## Decision

- **One pure machine** (`App\Domains\Moderation\Support\ContentStatusMachine`) says, for a row's state (its status, its deal: sale, rent or none, and whether its owner changed it since its last review), an action and an actor (owner or moderator), where the row goes, or that it may not. It also says which actions are allowed now (the API's `allowed_actions`), which statuses are terminal, which need a reason, and what an edit does to the status. It knows nothing of properties. It is mutation tested at 100.
- **No shared transition Action.** Each domain has its own small Actions (`SubmitProperty`, `ApproveProperty`, …) that ask the machine, lock the row, write the review columns, log the move (moderators in the `admin` log, owners in `content`) and dispatch an event after commit. Steps 2.3 to 2.5 copy `ChangePropertyStatus` for their own models.
- **One review queue in the panel** (المراجعة and the dashboard's محتاج قرارك), built on `ReviewableKind`: each kind says how to list what waits, how to show a row, and which of its domain's Actions approve and reject. Kinds are registered in `ReviewKinds` by the panel provider; the page never names one.
- A `changed_since_review` flag on every moderated table carries what the machine needs between an edit and the next move.

## Consequences

- The rules of moderation live in one place, tested once, and stay the same for every kind.
- Each new kind writes its Actions (a dozen lines each) and one `ReviewableKind`, and touches neither the machine nor the page.
- The queue shows one kind at a time: one table cannot page across four tables. The sidebar badge adds the kinds up.
- A kind that cannot be sold or rented passes no deal and can only be archived at the end.
