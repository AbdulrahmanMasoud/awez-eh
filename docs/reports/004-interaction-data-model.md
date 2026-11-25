# Report 004: Data model, part 2 (interaction and business)

- PR: #6 (https://github.com/AbdulrahmanMasoud/awez-eh/pull/6). CI: see the PR.
- Branch: task/004-interaction-data-model. Commit: 359a136 (the last before this report).

## What changed

- **The data model is complete,** apart from sign-in's two tables (step 1.4) and media (step 2.1). The database now holds residents' chats, bookings and their history, reviews, favorites, saved searches, notifications, and the business side: leads, commission records, featured listings, and the city guide with tonight's duty pharmacy and city news.
- **The platform's deals are protected in the data itself.** A chat or a viewing on a brokered property has no counterpart: the platform's staff answer, the owner's id is stored nowhere in it, and the owner cannot read it. Every such chat and viewing has its lead in the demo.
- **History cannot be rewritten.** Leads, a booking's timeline and commission records are refused any change or deletion by the database itself, whoever asks, psql included. A mistaken lead is corrected by a new row that voids it, and both stay.
- **Commission is computed, never stored:** expected, invoiced, collected and outstanding are added up from a property's records each time.
- **Bookings follow written rules:** which status can follow which, for a viewing or a craftsman's visit, and who may make each move (the resident, the owner or craftsman, the platform's staff, or the system). Step 3.1's screens will use them. The rules are proposed below for you to confirm.
- **Who sees what:** a chat only by its two participants; a booking by its resident and its counterpart; a review by everyone unless an admin hid it; favorites, saved searches and featured placements by their owner. No resident sees a lead, not even their own.
- **Every environment gets Sadat City's emergency numbers** (police, ambulance, fire, and the electricity, gas and water lines).
- **The demo city now feels used:**
    - 31 conversations, 26 bookings covering every status, reviews, 48 favorites, 6 saved searches, and 17 leads, one of them corrected;
    - the commission on a 1.8 million pound sale of the property with an agreement, half collected;
    - two featured packages (one placement active, one ended, one cancelled, one granted by an admin), a duty pharmacy for each of the next 14 nights, and 5 news items.

## Decisions for the owner

All answered (see the update at the end):

1. **The booking rules** are approved as proposed (DECISIONS 60). Rescheduling is not a status: it edits `scheduled_at` with its own booking event, in step 3.1.
2. **An account with history is never hard-deleted;** its personal data is blanked in step 1.4 (DECISIONS 56).
3. **Emergency numbers:** the national ones stay. The city's hospitals and transport lines are still open (OPEN_QUESTIONS 5).
4. **Housekeeping:** I do it after the merge, with your approval where the permission check asks.

## The owner's steps

1. **Remove task 003's leftovers,** from the main checkout:
    - `cd .worktrees/003-content-data-model && vendor/bin/sail down -v && cd ../..` (its containers are stopped; this removes them and their volumes);
    - `git worktree remove .worktrees/003-content-data-model`;
    - `git branch -D task/003-content-data-model` and `git push origin --delete task/003-content-data-model` (`-D` because squash merges leave the branch unmerged by ancestry; #5 shows as merged);
    - `rm docs/tasks/003-content-data-model.md`: an untracked copy, identical to the one on `main`, which would block `git pull`.
2. **After merging,** in the main checkout: `git pull`, then `sail artisan migrate` and `sail artisan db:seed --class=ReferenceDataSeeder` (it adds the emergency numbers). Or start over with `sail composer setup`, which seeds the demo too.

No DNS, Dokploy or production settings yet.

## Verification

**Tests first.** Each group ran red before its code existed:

- the state machine and balance tests could not load the missing classes;
- the schema tests failed on the missing tables, indexes and rules;
- once the models existed, 3 of 809 tests failed, all of them seeder tests waiting for the seeders.

**`sail composer check`:** Pint passed, Rector made 0 changes, and Larastan at max level found 0 errors, with no baseline and no ignores. Pest: **809 tests, 1,864 assertions**, in parallel (481 and 1,131 before, so 328 tests added).

Tests added, by file:

- **Unit:**
    - `BookingStateMachine`: 172. All 162 pairs of kind, from and to are each checked against the exact set of actors allowed; also the 9 terminal and non-terminal statuses, and the names;
    - `CommissionBalance`: 6 (empty, each total, outstanding, cancellations, another currency, mixed currencies refused).
- **Feature:**
    - the schema: 42 more (14 UUID keys, the new enum and money columns, 16 index lists, the delete rule of every new foreign key, and the `search_text` and geography indexes);
    - append-only, for each of the three tables: refused in PHP, refused in the database through Eloquent's query builder and `DB::table()` (update, delete, truncate), new rows still accepted, `created_at` only, and triggers on exactly those tables: 13;
    - constraints (21 rows refused, 2 allowed): 23;
    - ownership at the policy level: 27. Another resident and a guest cannot view, update or delete any of the 9 private rows, and their owners can. Public reviews, the message sender, and leads hidden from everyone are covered too;
    - a brokered conversation stores no trace of the owner, who cannot read it: 1;
    - leads and their corrections: 2; featured placements at their boundaries: 6; a database notification: 1; search alerts: 3;
    - every new model's factory: 14; the morph map and reports on messages and reviews: 2; seeders (emergency numbers, demo interactions that keep every rule): 2.
- **Arch:** 14 more model checks, and the policy rule widened to `resident_id`.

**Mutation testing** (`sail composer test:mutate`), every mutation killed:

| Class | Mutations | Score |
| --- | --- | --- |
| `MoneyCast` | 37 | 100% |
| `BookingStateMachine` | 33 | 100% |
| `BookingTransition` | 5 | 100% |
| `CommissionBalance` | 2 | 100% |

The first run scored 31%. 116 mutations of the state machine were "uncovered": Pest runs a mutation only against the tests that cover its line, and line coverage never reaches inside a constant. The transitions are now built in a method from `BookingTransition` objects, which coverage sees. `CommissionBalance` gets only two mutations because Pest does not swap Money's `plus()` and `minus()`; its six unit tests check every total.

**The widened arch rule proven with a throwaway:** a model on `bookings` (a `resident_id`, no `user_id`) without a policy failed it. The old rule would have missed it. The throwaway was removed.

**Migrations on `testing`** (never on a dev database):

```text
$ sail artisan migrate:fresh --seed   (DB_DATABASE=testing)
  Dropping all tables  …  311.13ms DONE
  2026_11_24_221056_create_notifications_table  …  6.46ms DONE
  2026_11_25_100100_create_conversations_tables  …  32.45ms DONE
  2026_11_25_100200_create_bookings_tables  …  51.48ms DONE
  2026_11_25_100300_create_reviews_table  …  25.76ms DONE
  2026_11_25_100400_create_favorites_and_alerts_tables  …  29.53ms DONE
  2026_11_25_100500_create_commissions_tables  …  50.64ms DONE
  2026_11_25_100600_create_promotions_tables  …  29.73ms DONE
  2026_11_25_100700_create_city_guide_tables  …  39.02ms DONE
  Database\Seeders\ReferenceDataSeeder  …  89 ms DONE  
  Database\Seeders\DemoContentSeeder  …  1,272 ms DONE  
  Database\Seeders\DemoInteractionSeeder  …  506 ms DONE  
wall time: 3.9 s

$ sail artisan migrate:rollback --step=8
  2026_11_25_100700_create_city_guide_tables  …  45.45ms DONE
  2026_11_25_100600_create_promotions_tables  …  17.39ms DONE
  2026_11_25_100500_create_commissions_tables  …  49.97ms DONE
  2026_11_25_100400_create_favorites_and_alerts_tables  …  13.61ms DONE
  2026_11_25_100300_create_reviews_table  …  10.36ms DONE
  2026_11_25_100200_create_bookings_tables  …  20.99ms DONE
  2026_11_25_100100_create_conversations_tables  …  14.56ms DONE
  2026_11_24_221056_create_notifications_table  …  3.56ms DONE

$ sail artisan migrate
  2026_11_24_221056_create_notifications_table  …  31.55ms DONE
  2026_11_25_100100_create_conversations_tables  …  97.84ms DONE
  2026_11_25_100200_create_bookings_tables  …  53.57ms DONE
  2026_11_25_100300_create_reviews_table  …  25.10ms DONE
  2026_11_25_100400_create_favorites_and_alerts_tables  …  32.17ms DONE
  2026_11_25_100500_create_commissions_tables  …  60.96ms DONE
  2026_11_25_100600_create_promotions_tables  …  29.85ms DONE
  2026_11_25_100700_create_city_guide_tables  …  38.57ms DONE
```

`migrate:fresh --seed` takes **3.9 s** in all; the new demo seeder alone takes 0.5 s.

**Real run.** `\d leads` on `testing`:

```text
                                   Table "public.leads"
    Column     |              Type              | Collation | Nullable |      Default      
---------------+--------------------------------+-----------+----------+-------------------
 id            | uuid                           |           | not null | 
 property_id   | uuid                           |           | not null | 
 resident_id   | uuid                           |           | not null | 
 channel       | character varying(255)         |           | not null | 
 source_type   | character varying(255)         |           | not null | 
 source_id     | uuid                           |           | not null | 
 voids_lead_id | uuid                           |           |          | 
 void_reason   | text                           |           |          | 
 created_at    | timestamp(0) without time zone |           | not null | CURRENT_TIMESTAMP
Indexes:
    "leads_pkey" PRIMARY KEY, btree (id)
    "leads_one_per_source_index" UNIQUE, btree (source_type, source_id) WHERE voids_lead_id IS NULL
    "leads_property_id_created_at_index" btree (property_id, created_at)
    "leads_resident_id_index" btree (resident_id)
    "leads_voids_lead_id_unique" UNIQUE CONSTRAINT, btree (voids_lead_id)
Check constraints:
    "leads_channel_check" CHECK (channel::text = ANY (ARRAY['chat'::character varying, 'viewing'::character varying]::text[]))
    "leads_source_check" CHECK (channel::text = 'chat'::text AND source_type::text = 'conversation'::text OR channel::text = 'viewing'::text AND source_type::text = 'booking'::text)
    "leads_void_check" CHECK ((voids_lead_id IS NULL) = (void_reason IS NULL) AND voids_lead_id <> id)
Foreign-key constraints:
    "leads_property_id_foreign" FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE RESTRICT
    "leads_resident_id_foreign" FOREIGN KEY (resident_id) REFERENCES users(id) ON DELETE RESTRICT
    "leads_voids_lead_id_foreign" FOREIGN KEY (voids_lead_id) REFERENCES leads(id) ON DELETE RESTRICT
Referenced by:
    TABLE "commission_entries" CONSTRAINT "commission_entries_lead_id_foreign" FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE RESTRICT
    TABLE "leads" CONSTRAINT "leads_voids_lead_id_foreign" FOREIGN KEY (voids_lead_id) REFERENCES leads(id) ON DELETE RESTRICT
Triggers:
    leads_append_only BEFORE DELETE OR UPDATE ON leads FOR EACH ROW EXECUTE FUNCTION append_only_refuse()
    leads_append_only_truncate BEFORE TRUNCATE ON leads FOR EACH STATEMENT EXECUTE FUNCTION append_only_refuse()
```

An `UPDATE` and a `DELETE` on `leads`, in psql:

```text
ERROR:  leads is append-only: UPDATE is refused. Record a correcting row instead.
CONTEXT:  PL/pgSQL function append_only_refuse() line 3 at RAISE
ERROR:  leads is append-only: DELETE is refused. Record a correcting row instead.
CONTEXT:  PL/pgSQL function append_only_refuse() line 3 at RAISE
 leads_still_there 
-------------------
                18
(1 row)
```

Rows per new table after `migrate:fresh --seed`:

```text
        table        | rows 
---------------------+------
 conversations       |   31
 messages            |   84
 bookings            |   26
 booking_events      |   69
 reviews             |    6
 favorites           |   48
 search_alerts       |    6
 notifications       |    0
 leads               |   18
 commission_entries  |    3
 featured_packages   |    2
 featured_placements |    4
 guide_entries       |    6
 duty_pharmacies     |   14
 news_items          |    5
```

`notifications` is empty on purpose: nothing sends any before step 3.4.

The demo bookings by status:

```text
          status          | service_visits | viewings | total 
--------------------------+----------------+----------+-------
 requested                |              2 |        2 |     4
 confirmed                |              2 |        3 |     5
 declined                 |              2 |        1 |     3
 on_the_way               |              1 |        0 |     1
 in_progress              |              1 |        0 |     1
 completed                |              3 |        3 |     6
 cancelled_by_resident    |              1 |        1 |     2
 cancelled_by_counterpart |              1 |        1 |     2
 no_show                  |              1 |        1 |     2
```

Leads, and who answers on brokered listings:

```text
 channel | leads | corrections 
---------+-------+-------------
 chat    |    10 |           1
 viewing |     7 |           0

what      | on_brokered | with_counterpart 
---------------+-------------+------------------
 conversations |          10 |                0
 viewings      |           7 |                0
(2 rows)
```

## Not done and follow-ups

- **Housekeeping:** the permission check refused removing task 003's worktree and branches (decision 4).
    - A second refusal stopped a command of mine that would have copied `.env.example` over the main checkout's `.env`. That was my mistake: the shell was still in the main checkout. Nothing changed.
- **Where I went another way than the brief, and why:**
    - **every parent of an append-only row uses `restrictOnDelete`,** as the brief prefers. None uses `nullOnDelete`, so the triggers have no exception to allow;
    - **"one lead per source" is a partial unique index,** for live leads only: a correction carries the source of the lead it voids;
    - **`news_items.created_by` is nullable with `nullOnDelete`,** so an admin account can go, as `property_agreements.created_by` already does;
    - **a conversation goes with either participant's account** (`cascadeOnDelete`). A null counterpart means the platform, so setting a deleted one to null would misread the chat as the platform's.
- **For later steps:** a conversation whose subject is deleted keeps pointing at it (morphs have no foreign key); step 2.8's Actions decide what happens to it.
- **Rector:**
    - one of its rules is now skipped for policies (DECISIONS 70);
    - a booking's events are `timeline()`, because Rector renames `->events`.
- **No endpoints, so no API docs (Scribe).**

## Deploy notes

- **Eight new migrations,** all creating tables. Nothing existing changes.
- **Three PostgreSQL functions** (`append_only_refuse`, `append_only_attach`, `append_only_detach`) come with the append-only tables. Creating them needs the `CREATE` privilege on the schema, which our own server's database user has.
- **Reference data:** `php artisan db:seed --force` adds the six emergency numbers, and can run on every deploy.
- **No new packages.**

## Update (2026-11-25)

The owner answered the decisions; nothing in the code changed.

- **Recorded:**
    - DECISIONS 60: the booking rules are approved, and rescheduling is an edit of `scheduled_at` with its own event, also written into step 3.1 of `docs/BUILD_PLAN.md`;
    - DECISIONS 56: an account with history is never hard-deleted;
    - DECISIONS 67: the national emergency numbers stay.
- **`OPEN_QUESTIONS.md`:** the booking rules and account deletion are settled and removed; hospitals and transport lines stay open.
- **Housekeeping** of tasks 003 and 004 follows the merge.
