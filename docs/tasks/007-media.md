# Task 007: Media

- **Branch:** `task/007-media`, in `.worktrees/007-media` (`docs/tasks/README.md`).
- **Depends on:** task 006 (merged as #11).
- **Build plan step:** 2.1. Before coding, expand it in `docs/BUILD_PLAN.md` into **Acceptance** and **Verify** lines from this brief.

## Why

Every listing of phase 2 has photos, and the agreements behind brokered deals have scanned documents. This task builds the upload, the two sizes, privacy (no location left in a photo) and the storage switch, once, so steps 2.2 to 2.5 only attach photos to their models.

## Packages (the owner approves all three)

- **`spatie/laravel-medialibrary`** (named in `CLAUDE.md` and ARCHITECTURE §3).
- **`league/flysystem-aws-s3-v3`**: Laravel's S3 driver, which Cloudflare R2 uses.
- **`spatie/laravel-settings`** (named in `CLAUDE.md`): the first values an admin tunes (section 6). Its first use sets the pattern for later steps.

## 1. Storage: the local disk now, R2 by configuration

- **No MinIO and no R2 account yet** (owner's decision). Locally the public files go to the `public` disk (`sail artisan storage:link`) and private ones to `local` (`storage/app/private`). Tests use `Storage::fake()`.
- `config/filesystems.php` gets two R2 disks, `r2` (public bucket, served from a public URL) and `r2_private` (no public URL), on the `s3` driver with R2's endpoint, region `auto` and the keys from `.env`. Nothing uses them until `.env` says so.
- `config('awez.media.disk')` and `config('awez.media.private_disk')`, from `MEDIA_DISK` (default `public`) and `MEDIA_PRIVATE_DISK` (default `local`). Medialibrary reads them; no code names a disk.
- R2 itself (CORS, the public domain, a real upload) is checked once in step 4.4; record that as a follow-up.

## 2. Images

- Formats in: JPEG, PNG, WebP, and HEIC when the PHP image library in the container can read it (check, and say in the report; the mobile app can always send JPEG). Validated by content, not by the extension or the client's MIME type. Up to 10 MB a file by default (section 6).
- **Privacy first:** before a file is stored, it is decoded and written again as JPEG with every piece of metadata removed (EXIF, GPS, the camera, the owner's name), auto-rotated from its EXIF orientation first, and scaled down to at most 2560 px on its long side. The stored original is that clean copy; the uploaded bytes are never kept.
- **Two sizes**, as conversions in WebP: `thumb` (480 px wide, for lists) and `large` (1600 px wide, for the detail page), never enlarged. Conversions run on a queue named `media`; the job is Medialibrary's own, and running it twice gives the same files (invariant 7).
- Each image stores its width and height, so the app reserves space before it loads.
- Public URLs only for the two conversions. The cleaned original is not exposed by the API.

## 3. Collections

Each model declares its collections (and its maximum count from the settings), with an order the owner can change:

| Model | Collection | Count | Disk |
| --- | --- | --- | --- |
| `Property` | `photos` | up to 20 | public |
| `PropertyAgreement` | `documents` (PDF or image) | up to 5 | **private** |
| `Provider` | `avatar` (single), `work` | 1, up to 30 | public |
| `Place` | `logo` (single), `cover` (single), `photos` | 1, 1, up to 20 | public |
| `PlaceItem` | `photo` (single) | 1 | public |
| `UsedItem` | `photos` | up to 10 | public |
| `ServiceRequest` | `photos` | up to 3 | public |
| `User` | `avatar` (single) | 1 | public |
| `GuideEntry`, `NewsItem` | `image` (single) | 1 | public |

- A **private** file is never given a public URL: admins get a temporary signed URL (5 minutes) through the panel only, and each view writes an activity entry.
- Messages with images (`messages.kind = image`) come with chat in step 2.8; leave messages out here.
- `Media` lives in `App\Domains\Media`; add `media` to the morph map.

## 4. Uploading from the app

The app uploads photos while the resident is still writing the listing, then the listing is created with them. So:

- `POST /api/v1/uploads` (multipart `file`): stores the image on a **pending upload** owned by the user (`pending_uploads`: `user_id` `cascadeOnDelete`, `expires_at` 24 hours later), and answers with `MediaResource` (`id`, `thumb_url`, `large_url`, `width`, `height`; the URLs are `null` until the conversions exist, with `processing: true`).
- `GET /api/v1/uploads/{id}` (to poll for the conversions) and `DELETE /api/v1/uploads/{id}`. Only the owner (policy, ownership test).
- **`App\Domains\Media\Actions\AttachUploads`**: `handle(HasMedia $model, string $collection, User $user, list<string> $uploadIds): void`. It checks every upload belongs to the user and has not expired, that the collection's count is not exceeded, moves the files to the model in the given order, and deletes the pending uploads, all in one transaction. Phase 2's create and edit Actions call it; this task tests it with a property and a used item.
- `PUT /api/v1/me/avatar` (multipart) and `DELETE /api/v1/me/avatar`. `UserResource`'s avatar comes from the uploaded one, else from `avatar_url` (Google's or Apple's picture). Deleting an account (task 005) deletes its avatar and its pending uploads.
- Rate limit on uploads: 60 an hour per user (section 6).
- A scheduled job, every hour, deletes expired pending uploads with their files (`chunkById`). Idempotent; test it.
- Scribe documents the endpoints with a multipart example.

## 5. The admin panel

- In the users resource, the account page shows the uploaded avatar.
- A small reusable piece for later resources: a Filament field and a table column for a collection's images (thumbnails, reorder by dragging, remove), built on Filament's own spatie media plugin if it exists for the installed Filament major (check), else on Filament's file upload.
- Agreements' private documents: their screen comes with the brokered properties' resource in step 2.2; here only the signed URL action, tested.

## 6. Settings (`spatie/laravel-settings`)

- `App\Domains\Media\Settings\MediaSettings`: maximum file size, maximum per collection (the table's numbers as defaults), uploads per hour. Defaults in a settings migration in `database/settings`.
- A settings page **إعدادات الصور** in the panel under النظام, for `super_admin`, its changes in the activity log.
- Tests change settings with the package's fake, never by writing the table.
- Write the pattern into `CLAUDE.md`'s Configuration paragraph if it needs more than it says now (registration, the migration folder, who may edit).

## 7. Tests

- Each accepted format stored and converted; a renamed text file, an executable, an SVG and a file over the limit refused with `validation_failed` and Arabic messages.
- **A JPEG with GPS and camera EXIF comes out with none** (read back with `exif_read_data` on the stored original and the conversions), rotated upright.
- Conversion sizes: a wide, a tall and a small image (never enlarged).
- The pending-upload flow: owner only, expiry, deleting, polling until processed; `AttachUploads` with too many files, someone else's upload, an expired one, the order kept, all or nothing.
- The avatar, set, replaced and removed; the resource's fallback to the provider's picture.
- Private documents: no public URL exists for them; a signed URL works for an admin and expires.
- The disk switch: with `MEDIA_DISK` set to a faked `r2`, files go there (the S3 driver itself is not called; `Storage::fake('r2')`).
- The cleanup job, twice.
- Arch: no code outside `config/` names a disk for media.

## Out of scope

Photos in each listing's own create and edit endpoints (2.2 to 2.5), chat images (2.8), video, the real R2 bucket (4.4), image moderation by machine.

## Acceptance

- `sail composer check` green, test counts in the report.
- Migrations (`media`, `pending_uploads`, `settings`) verified on `testing` as the definition of done says.
- Real run on the dev stack, outputs in the report: upload a real phone photo that carries GPS through `curl` with a token from the fake sign-in; poll until processed; open both URLs (sizes reported); show its EXIF before and after (`exif_read_data` output, or `exiftool` if it is in the container); attach it to a demo property through `AttachUploads` in `tinker`; let a pending upload expire and run the cleanup.
