# Report 003: Data model, part 1 (geography and content)

- PR: #5 (https://github.com/AbdulrahmanMasoud/awez-eh/pull/5). CI: green on 4ede9c3 (Pint, Rector, Larastan and Pest each passed); the commit with this report runs it again.
- Branch: task/003-content-data-model. Commit: 4ede9c3 (the last before this report).

## What changed

- **The database now holds the city and its content:** Sadat City and its districts; properties for sale and rent, with the brokerage agreements behind the platform's own deals; craftsmen with their trades and price lists; restaurants, shops and pharmacies with their menus or products, offers and opening hours; second-hand items; residents' requests and the replies to them; and reports.
- **New content waits for an admin.** A new property, craftsman profile, place or used item starts as "pending review", and only published content is public. Who reviewed it and when is recorded, and a rejection keeps its reason.
- **Each resident sees and changes only their own content**, apart from what is published. A reply to a request is seen only by the resident who asked and the one who replied, so competitors do not see each other's offers. A report is seen only by the resident who filed it.
- **"Near me" is computed in the database**, on the Earth's real shape, and every location is a precise point.
- **Arabic search has a column to search:** every listing keeps a normalized copy of its text, so `صيدليّة` saved is found when a resident types `صيدليه` or `صيدلة`. Search itself comes in step 2.6, and there are two findings for it below.
- **"Open now" works the way Egypt keeps time:** opening hours are Cairo wall-clock times, a restaurant open until 02:00 is open after midnight, a shop can close at noon and open again in the evening, a pharmacy can be open around the clock, and summer time is followed.
- **Every environment gets the reference data:** Sadat City, seven districts, 13 trades and 12 place categories, in Arabic and English. Running the seeder again changes nothing.
- **A local demo city:** 30 residents and 40 craftsmen; 30 properties (10 of them the platform's own deals, one with an agreement), 40 places, 60 used items, and 20 requests with replies. The texts read like real listings, the content is spread over the city, and most of it is published. Production never gets it.
- **Rules that keep the data honest are in the database itself:** a rent always has a period and a sale never does; a commission is a rate or a fixed amount, never both; a craftsman has one primary trade and one profile; a resident can have one open report per item; an offer ends after it starts.

## Decisions for the owner

1. **Search and short words** (DECISIONS 39). At the 0.25 threshold you accepted, a search term that begins like a two-letter word matches every text that contains that word. Arabic text is full of such words, so `منزل` would match every listing with `من` (scored 0.333), `فيللا` every listing with `في` (0.286), and `علاج` every text with `على` (0.286). Two ways out:
    - **leave words under three letters out** of the stored search text and of the search term, keeping the threshold. Typos are still forgiven; a search for a two-letter word finds nothing.
    - **raise the threshold to 0.34.** Short words stop matching, but so does the common typo with two letters swapped (0.27).

    Nothing changes until step 2.6, where search is built. The first is the one I would pick.
2. **Search speed** (DECISIONS 39). The GIN index makes a typo-tolerant search 16 times faster (187 ms against 2,932 ms for 20,030 listings), but PostgreSQL does not use it unless it is told that the comparison is expensive (`ALTER FUNCTION … COST 100`, one statement in a migration). That statement needs the database's owner. On our own server that is us; a managed database might refuse it, and then step 2.6 needs another way. This decision is needed before choosing where production runs (step 4.4).
3. **Deleting an account deletes the resident's content** (DECISIONS 41), as the brief says. That includes a brokered property and its agreement, and later the leads behind your commission. At step 1.4 the choice is:
    - refuse to delete an account while it owns a brokered listing (the resident is asked to contact you first);
    - or keep those rows and blank the owner's name.

    Today nothing deletes accounts, so nothing is lost meanwhile.
4. **Who sees a reply to a request** (DECISIONS 46). Done now: only the resident who asked and the one who replied. The other way, replies visible to everyone, lets residents compare offers in public, and lets competitors undercut each other. Say if you want that instead.
5. **Places' phone numbers** (DECISIONS 45). As the brief says, a place's phone and WhatsApp accept Egyptian mobile numbers only, so a pharmacy's landline (`048 …`) is refused. Accepting landlines is a small change to `PhoneNumber`, in its own step; say if places should be able to list one.
6. **Icons** (DECISIONS 44). Trades and place categories name their icon from Lucide (`wrench`, `pill`, `shopping-cart`, ...), the stroke set closest to the admin panel's. The mobile app will need to ship Lucide, or to map these names to its own set.
7. **The districts** (DECISIONS 43, OPEN_QUESTIONS 3). The seeder has the seven districts the brief names, without centres. Please send the full list, and a point for each district's centre if you can (a pin on a map is enough).

## The owner's steps

1. **After merging,** in the main checkout:
    - `git pull`;
    - `sail up -d --wait`, then `sail composer setup`. Your `.env` still has no `APP_KEY`; this sets it, migrates the dev and testing databases, and seeds reference data and the demo city into the dev database.
2. **If your dev database already has data you want to keep**, run `sail artisan migrate` and `sail artisan db:seed --class=ReferenceDataSeeder` instead of `composer setup`. Two things to know:
    - the demo seeder adds a new set every time it runs, so run it only once, or start from `sail artisan migrate:fresh --seed`;
    - a Sanctum token created before this change stores `App\Models\User`, which no longer exists, and stops working. Nothing is deployed, so only local test tokens are affected.

No DNS, Dokploy or production settings yet.

## Verification

**Tests first.** Each group of tests ran red before its code existed:

- `Point` and `PointCast`: the suite could not load the missing classes;
- the location, search-text and name traits: "Trait `HasLocation` not found";
- the schema tests: 2 of 26 passed before the migrations (task 001's foreign keys were already indexed, and `User` already had a UUID key), and two datasets were empty, waiting for the models;
- the relationship, factory and model-rule tests failed until the models existed.

**`sail composer check`:** Pint passed, Rector made 0 changes, and Larastan at max level found 0 errors, with no baseline and no ignores. Pest: **445 tests, 1,065 assertions**, in parallel (192 and 487 before, so 253 tests added).

Tests added, by file:

- **Unit:**
    - `Point`: 11;
    - `PointCast` (reading EWKB in both byte orders, refusing what is not a point in SRID 4326, writing longitude first, comparing): 28;
    - `OpeningHoursCalculator` (same day, across midnight, Saturday into Sunday, split shifts, closed days, 24 hours, exactly at closing, summer time): 25;
    - `ContentStatus`: 2.
- **Feature:**
    - locations (the Sadat City point stored with longitude as x and latitude as y, an exact round trip, no change on reading, three points at known distances, the SQL of both scopes): 7;
    - search text (filled, refreshed, `صيدليّة` found by `صيدليه` and `صيدلة`, not by unrelated words) and translated names: 8 and 3;
    - the schema: 56, of which 17 UUID keys, 7 enum checks, 7 money checks, 4 moderation column checks, 17 index lists, and one check each that every foreign key is indexed, has its delete rule, and every geography column and every `search_text` has its index;
    - relationships on both sides of every foreign key: 1;
    - every model's factory: 17;
    - constraints (14 rows refused, 2 allowed): 16;
    - search text in each kind of content: 5;
    - moderation (pending review first, the `published()` scope, review dates): 12;
    - reports and the morph map (short names stored and found back, a model without one refused, one open report per resident and item): 9;
    - **ownership at the policy level**: 25. For each of the seven user-owned tables, another resident cannot view an unpublished row, update it or delete it, and a guest cannot view it. The owner can do all three, and published rows are public but changed only by their owner. The API-level proof comes with each endpoint in phase 2.
    - `Place::isOpenAt()` from stored hours, and opening hours stored as times of day: 2;
    - seeders (reference data, idempotence, nothing in production, the guard, the demo counts, every demo point within 4.1 km of the centre): 7.
- **Arch:** models only in `App\Domains\*\Models`, final, keyed by UUID and explicit about what they fill (18); every model with a `user_id` has a policy (1).

**Arch rules proven with throwaways,** run against `tests/Arch` and removed afterwards:

- a model in `App\Models` failed "keeps every model in a domain's Models namespace";
- a model that was not final failed "makes every model final, …";
- a model on the `used_items` table without a policy failed "gives every model with an owner column a policy".

**Migrations on `testing`** (never on a dev database):

```text
$ sail artisan migrate:fresh --seed   (DB_DATABASE=testing)
  Dropping all tables  …  201.20ms DONE
  Creating migration table  …  28.17ms DONE
  0000_01_01_000000_enable_postgresql_extensions  …  3.03ms DONE
  0001_01_01_000000_create_users_table  …  38.12ms DONE
  0001_01_01_000001_create_cache_table  …  11.10ms DONE
  0001_01_01_000002_create_jobs_table  …  22.72ms DONE
  2026_11_24_133452_create_personal_access_tokens_table  …  9.50ms DONE
  2026_11_24_200000_create_geography_tables  …  60.61ms DONE
  2026_11_24_200100_create_real_estate_tables  …  65.48ms DONE
  2026_11_24_200200_create_services_tables  …  59.25ms DONE
  2026_11_24_200300_create_places_tables  …  69.57ms DONE
  2026_11_24_200400_create_marketplace_tables  …  34.77ms DONE
  2026_11_24_200500_create_requests_tables  …  50.39ms DONE
  2026_11_24_200600_create_moderation_tables  …  19.48ms DONE
  Database\Seeders\ReferenceDataSeeder  …  71 ms DONE  
  Database\Seeders\DemoContentSeeder  …  1,250 ms DONE  
wall time: 2.8 s

$ sail artisan migrate:rollback --step=7
  2026_11_24_200600_create_moderation_tables  …  22.67ms DONE
  2026_11_24_200500_create_requests_tables  …  22.48ms DONE
  2026_11_24_200400_create_marketplace_tables  …  15.63ms DONE
  2026_11_24_200300_create_places_tables  …  32.81ms DONE
  2026_11_24_200200_create_services_tables  …  27.40ms DONE
  2026_11_24_200100_create_real_estate_tables  …  22.43ms DONE
  2026_11_24_200000_create_geography_tables  …  9.62ms DONE

$ sail artisan migrate
  2026_11_24_200000_create_geography_tables  …  98.92ms DONE
  2026_11_24_200100_create_real_estate_tables  …  66.53ms DONE
  2026_11_24_200200_create_services_tables  …  74.11ms DONE
  2026_11_24_200300_create_places_tables  …  68.88ms DONE
  2026_11_24_200400_create_marketplace_tables  …  31.21ms DONE
  2026_11_24_200500_create_requests_tables  …  45.05ms DONE
  2026_11_24_200600_create_moderation_tables  …  17.25ms DONE

$ sail artisan db:seed
  Database\Seeders\ReferenceDataSeeder  …  186 ms DONE  
  Database\Seeders\DemoContentSeeder  …  1,273 ms DONE
```

`migrate:fresh --seed` takes **2.8 s** in all, against the brief's limit of a minute. The demo seeder alone takes 1.3 s.

One earlier run, made while I was writing the migrations, reached this worktree's own dev database, which is empty and has its own Docker volume. Your dev data was never touched. That database goes with the worktree (`sail down -v`).

**Real run.** `\d properties` on `testing`:

```text
                                           Table "public.properties"
      Column      |              Type              | Collation | Nullable |               Default               
------------------+--------------------------------+-----------+----------+-------------------------------------
 id               | uuid                           |           | not null | 
 user_id          | uuid                           |           | not null | 
 purpose          | character varying(255)         |           | not null | 
 type             | character varying(255)         |           | not null | 
 title            | character varying(255)         |           | not null | 
 description      | text                           |           | not null | 
 search_text      | text                           |           | not null | 
 price            | bigint                         |           | not null | 
 currency         | character(3)                   |           | not null | 
 rent_period      | character varying(255)         |           |          | 
 area_m2          | integer                        |           | not null | 
 bedrooms         | smallint                       |           |          | 
 bathrooms        | smallint                       |           |          | 
 floor            | smallint                       |           |          | 
 furnished        | boolean                        |           | not null | false
 finishing        | character varying(255)         |           |          | 
 city_id          | uuid                           |           | not null | 
 district_id      | uuid                           |           | not null | 
 address_note     | character varying(255)         |           |          | 
 location         | geography(Point,4326)          |           | not null | 
 brokered         | boolean                        |           | not null | false
 status           | character varying(255)         |           | not null | 'pending_review'::character varying
 rejection_reason | text                           |           |          | 
 reviewed_by      | uuid                           |           |          | 
 reviewed_at      | timestamp(0) without time zone |           |          | 
 published_at     | timestamp(0) without time zone |           |          | 
 views_count      | integer                        |           | not null | 0
 created_at       | timestamp(0) without time zone |           |          | 
 updated_at       | timestamp(0) without time zone |           |          | 
Indexes:
    "properties_pkey" PRIMARY KEY, btree (id)
    "properties_city_id_index" btree (city_id)
    "properties_district_id_index" btree (district_id)
    "properties_location_spatialindex" gist (location)
    "properties_price_index" btree (price)
    "properties_purpose_type_index" btree (purpose, type)
    "properties_reviewed_by_index" btree (reviewed_by)
    "properties_search_text_trgm_index" gin (search_text gin_trgm_ops)
    "properties_status_published_at_index" btree (status, published_at)
    "properties_user_id_index" btree (user_id)
Check constraints:
    "properties_finishing_check" CHECK (finishing::text = ANY (ARRAY['unfinished'::character varying, 'semi_finished'::character varying, 'finished'::character varying, 'luxury'::character varying]::text[]))
    "properties_purpose_check" CHECK (purpose::text = ANY (ARRAY['sale'::character varying, 'rent'::character varying]::text[]))
    "properties_rent_period_check" CHECK (rent_period::text = ANY (ARRAY['monthly'::character varying, 'yearly'::character varying]::text[]))
    "properties_rent_period_required_check" CHECK ((purpose::text = 'rent'::text) = (rent_period IS NOT NULL))
    "properties_status_check" CHECK (status::text = ANY (ARRAY['draft'::character varying, 'pending_review'::character varying, 'published'::character varying, 'paused'::character varying, 'sold'::character varying, 'rented'::character varying, 'rejected'::character varying, 'archived'::character varying]::text[]))
    "properties_type_check" CHECK (type::text = ANY (ARRAY['apartment'::character varying, 'villa'::character varying, 'duplex'::character varying, 'house'::character varying, 'land'::character varying, 'shop'::character varying, 'office'::character varying, 'warehouse'::character varying, 'factory'::character varying]::text[]))
Foreign-key constraints:
    "properties_city_id_foreign" FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE RESTRICT
    "properties_district_id_foreign" FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE RESTRICT
    "properties_reviewed_by_foreign" FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
    "properties_user_id_foreign" FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
Referenced by:
    TABLE "property_agreements" CONSTRAINT "property_agreements_property_id_foreign" FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
```

Rows per table after one `migrate:fresh --seed`:

```text
        table        | rows 
---------------------+------
 users               |   70
 cities              |    1
 districts           |    7
 properties          |   30
 property_agreements |    1
 trades              |   13
 providers           |   40
 provider_trades     |   49
 provider_prices     |   56
 place_categories    |   12
 places              |   40
 place_items         |  140
 place_offers        |   56
 opening_hours       |  331
 used_items          |   60
 requests            |   20
 request_replies     |   56
 reports             |    6
```

Moderation statuses of the demo content:

```text
     t      |     status     | count 
------------+----------------+-------
 places     | published      |    33
 places     | pending_review |     4
 places     | rejected       |     1
 places     | archived       |     1
 places     | paused         |     1
 properties | published      |    23
 properties | pending_review |     3
 properties | sold           |     1
 properties | rented         |     1
 properties | paused         |     1
 properties | rejected       |     1
 providers  | published      |    32
 providers  | pending_review |     4
 providers  | rejected       |     2
 providers  | paused         |     2
 used_items | published      |    48
 used_items | pending_review |     5
 used_items | sold           |     4
 used_items | rejected       |     2
 used_items | paused         |     1
```

**"Published properties within 2 km of the centre, nearest first"**, the SQL the scopes generate:

```sql
select "id", "title" from "properties" where "properties"."status" = 'published' and ST_DWithin(location, ST_SetSRID(ST_MakePoint(30.5266, 30.3811), 4326)::geography, 2000) order by ST_Distance(location, ST_SetSRID(ST_MakePoint(30.5266, 30.3811), 4326)::geography) limit 20
```

With the 30 seeded rows, the planner reads the status index and filters, which is right at that size:

```text
                                                                             QUERY PLAN                                                                              
---------------------------------------------------------------------------------------------------------------------------------------------------------------------
 Limit  (cost=33.16..33.17 rows=1 width=540) (actual time=92.885..92.957 rows=7.00 loops=1)
   ->  Sort  (cost=33.16..33.17 rows=1 width=540) (actual time=92.798..92.839 rows=7.00 loops=1)
         Sort Key: (st_distance(location, '0101000020E6100000D712F241CF863E406F8104C58F613E40'::geography, true))
         Sort Method: quicksort  Memory: 25kB
         ->  Index Scan using properties_status_published_at_index on properties  (cost=0.14..33.16 rows=1 width=540) (actual time=91.494..91.982 rows=7.00 loops=1)
               Index Cond: ((status)::text = 'published'::text)
               Filter: st_dwithin(location, '0101000020E6100000D712F241CF863E406F8104C58F613E40'::geography, '2000'::double precision, true)
               Rows Removed by Filter: 16
               Index Searches: 1
 Planning Time: 57.039 ms
 Execution Time: 94.506 ms
```

With 20,000 more listings over about 60 km (inserted in a transaction and rolled back), it uses the GiST index:

```text
                                                                                 QUERY PLAN                                                                                 
----------------------------------------------------------------------------------------------------------------------------------------------------------------------------
 Limit  (cost=1721.48..1721.49 rows=2 width=86) (actual time=91.212..91.278 rows=20.00 loops=1)
   ->  Sort  (cost=1721.48..1721.49 rows=2 width=86) (actual time=91.125..91.154 rows=20.00 loops=1)
         Sort Key: (st_distance(location, '0101000020E6100000D712F241CF863E406F8104C58F613E40'::geography, true))
         Sort Method: top-N heapsort  Memory: 29kB
         ->  Bitmap Heap Scan on properties  (cost=198.00..1721.47 rows=2 width=86) (actual time=88.569..90.416 rows=51.00 loops=1)
               Recheck Cond: ((status)::text = 'published'::text)
               Filter: st_dwithin(location, '0101000020E6100000D712F241CF863E406F8104C58F613E40'::geography, '2000'::double precision, true)
               Rows Removed by Filter: 50
               Heap Blocks: exact=90
               ->  BitmapAnd  (cost=198.00..198.00 rows=95 width=0) (actual time=2.986..3.005 rows=0.00 loops=1)
                     ->  Bitmap Index Scan on properties_location_spatialindex  (cost=0.00..5.29 rows=119 width=0) (actual time=0.937..0.943 rows=132.00 loops=1)
                           Index Cond: (location && _st_expand('0101000020E6100000D712F241CF863E406F8104C58F613E40'::geography, '2000'::double precision))
                           Index Searches: 1
                     ->  Bitmap Index Scan on properties_status_published_at_index  (cost=0.00..192.46 rows=16023 width=0) (actual time=1.818..1.818 rows=16023.00 loops=1)
                           Index Cond: ((status)::text = 'published'::text)
                           Index Searches: 1
 Planning Time: 7.056 ms
 Execution Time: 91.967 ms
```

**A trigram search** for `دوبليكس` (a typo of `دوبلكس`) at the configured threshold, on the same 20,030 rows (rolled back). PostgreSQL turns `'دوبليكس' <<% search_text` into this form itself:

```sql
select set_config('pg_trgm.strict_word_similarity_threshold', '0.25', true);
select "id", "title" from "properties" where search_text %>> 'دوبليكس' and status = 'published';
```

Left to choose, the planner scans the table, which takes 2.9 s:

```text
                                                     QUERY PLAN                                                     
--------------------------------------------------------------------------------------------------------------------
 Seq Scan on properties  (cost=0.00..2170.45 rows=1069 width=78) (actual time=0.410..2931.745 rows=1335.00 loops=1)
   Filter: ((search_text %>> 'دوبليكس'::text) AND ((status)::text = 'published'::text))
   Rows Removed by Filter: 18695
 Planning Time: 7.249 ms
 Execution Time: 2932.253 ms
```

With sequential and plain index scans disabled, and without the status filter, the GIN index answers in 187 ms:

```text
                                                                       QUERY PLAN                                                                       
--------------------------------------------------------------------------------------------------------------------------------------------------------
 Bitmap Heap Scan on properties  (cost=2372.27..5044.63 rows=1336 width=78) (actual time=13.873..185.841 rows=1336.00 loops=1)
   Recheck Cond: (search_text %>> 'دوبليكس'::text)
   Heap Blocks: exact=1454
   ->  Bitmap Index Scan on properties_search_text_trgm_index  (cost=0.00..2371.94 rows=1336 width=0) (actual time=12.912..12.920 rows=2670.00 loops=1)
         Index Cond: (search_text %>> 'دوبليكس'::text)
         Index Searches: 1
 Planning Time: 7.087 ms
 Execution Time: 186.600 ms
```

With the operator priced at cost 100 (`ALTER FUNCTION strict_word_similarity_commutator_op(text, text) COST 100`, rolled back too), the planner picks the GIN index by itself:

```text
                                                                           QUERY PLAN                                                                           
----------------------------------------------------------------------------------------------------------------------------------------------------------------
 Bitmap Heap Scan on properties  (cost=2036.18..4679.96 rows=1069 width=78) (actual time=11.979..176.171 rows=1335.00 loops=1)
   Recheck Cond: (((status)::text = 'published'::text) AND (search_text %>> 'دوبليكس'::text))
   Heap Blocks: exact=120
   ->  BitmapAnd  (cost=2036.18..2036.18 rows=1069 width=0) (actual time=11.361..11.382 rows=0.00 loops=1)
         ->  Bitmap Index Scan on properties_status_published_at_index  (cost=0.00..360.46 rows=16023 width=0) (actual time=2.354..2.360 rows=16023.00 loops=1)
               Index Cond: ((status)::text = 'published'::text)
               Index Searches: 1
         ->  Bitmap Index Scan on properties_search_text_trgm_index  (cost=0.00..1674.94 rows=1336 width=0) (actual time=8.670..8.670 rows=1336.00 loops=1)
               Index Cond: (search_text %>> 'دوبليكس'::text)
               Index Searches: 1
 Planning Time: 7.828 ms
 Execution Time: 176.942 ms
```

The scan and the last plan found the same 1,335 published rows: copies of the two duplex listings, at a score of 0.50. Without the status filter there are 1,336.

## Not done and follow-ups

- **Search (step 2.6):** the two findings above.
- **Not asked for, so left out:**
    - an index to sort craftsmen by rating: the average is computed from `rating_sum` and `rating_count`, and its expression index belongs with step 2.3's sort;
    - keeping `replies_count` and `views_count` up to date: that comes with the Actions of phase 2 (the demo sets `replies_count`).
- **Mutation testing** was not run: no money code changed. `PointCast` and `OpeningHoursCalculator` are fully covered by unit tests, but neither is money.
- **No endpoints, so no API docs (Scribe).**
- **Larastan** now reads `casts()` (`parseModelCastsMethod`). Without it, attributes had no cast types and `User`'s casts were unchecked too.

## Deploy notes

- **Seven new migrations**, all creating tables. Nothing existing changes.
- **Reference data:** on deploy, `php artisan db:seed --force`. In production it seeds only the reference data, and can run on every deploy. Never run `DemoContentSeeder` there; it refuses anyway.
- **The morph map:** Sanctum tokens now store `user` as their type. Nothing is deployed, so no stored row needs changing.
- **No new packages.**
