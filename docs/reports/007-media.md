# Report 007: Media

- PR: #13 (https://github.com/AbdulrahmanMasoud/awez-eh/pull/13). CI: see the PR.
- Branch: task/007-media. Commit: 2713333 (the last before this report).

## What changed

- **A photo never carries its owner's location.** Before anything is stored, the photo is decoded and written again as a JPEG. The new copy is upright (from the phone's orientation flag), at most 2560 px on its long side, and has no metadata: no GPS, no camera, no owner's name, no comments. The uploaded file and its name (for example `ahmed-house.jpg`) are never kept. This holds for residents uploading through the app and for admins uploading in the panel.
- **Every photo has two sizes**, made in the background in WebP: a small one, 480 px wide, for lists; a large one, 1600 px wide, for a listing's own page. A small photo is never enlarged. Each photo stores its width and height, so the app can reserve the space before the picture loads. These two sizes are the only links that exist for a photo: its original is kept on the private disk.
- **Formats:**
    - accepted: JPEG, PNG and WebP, recognized by the file's content, not its name;
    - refused, with an Arabic message: a text file or a program renamed `.jpg`, an SVG, a HEIC, a file over 10 MB, a damaged file, and a picture larger than any phone takes (over 120 megapixels).

  HEIC (an iPhone's own format) cannot be read in our container; the app will send JPEG (OPEN_QUESTIONS 8).
- **The app uploads photos while the resident writes the listing.**
    - `POST /api/v1/uploads` keeps the photo for that resident for 24 hours and answers at once with its id and shape (`processing: true`, the links null).
    - `GET /api/v1/uploads/{id}` gives the links once both sizes are ready.
    - `DELETE` removes the photo.

  Nobody else can see or delete someone's upload: it answers "not found", as an expired one does. Each account may upload 60 photos an hour (avatars included).
- **Listings take their photos in the order the resident chose.** Phase 2's create and edit steps call `AttachUploads`. It moves the resident's photos to the listing within the listing's limit (20 for a property, 10 for a used item, and so on). It takes all of them or none, and copies no file. An hourly job deletes uploads never attached, with their files.
- **The account's picture.**
    - `PUT /api/v1/me/avatar` sets it.
    - `DELETE /api/v1/me/avatar` removes it.
    - The app shows the uploaded picture, else the Google or Apple one.
    - Deleting an account deletes its picture and its unattached uploads.
- **Every kind of content has its photo albums**, with their limits. The owner can change the limits:

| Content | Albums |
| --- | --- |
| Property | photos (20) |
| Brokerage agreement | documents (5, PDF or photos, private) |
| Craftsman | picture (1), work (30) |
| Place | logo (1), cover (1), photos (20) |
| Place item | photo (1) |
| Used item | photos (10) |
| Request | photos (3) |
| Account | picture (1) |
| City guide entry, news item | image (1) |
- **Agreements' scanned documents are private.**
    - They have no public link at all.
    - An admin allowed to see them opens one from the panel in a new tab, through a link that works for 5 minutes. Each opening is written in سجل النشاط.
    - The permission is `ViewDocuments:PropertyAgreement`: super admins hold it, and a super admin can give it to another role on the roles screen.
    - The agreements' own screen comes with step 2.2.
- **إعدادات الصور** (under النظام, super admin only) sets:
    - the largest photo (10 MB);
    - uploads per hour per account (60);
    - each album's limit.

  Each change is written in سجل النشاط with before and after.
- **In the panel:**
    - an account's page shows its uploaded picture;
    - steps 2.2 to 2.5 get a ready field for an album (thumbnails, drag to reorder, remove), a table column and the private-document button. The field cleans admin uploads like residents'.
- **Storage moves to Cloudflare R2 by configuration only.** Today the files are on the server's disks. Setting `MEDIA_DISK=r2` and `MEDIA_PRIVATE_DISK=r2_private`, with the R2 keys, moves them; no code changes.

## Decisions for the owner

1. **The originals of photos are kept private** (DECISIONS 99, ADR 0007).
    - The brief put photos on the public disk. In the real run, the original's address could be guessed from a size's address (`…/conversions/photo-thumb.webp` → `…/photo.jpg`), and it opened.
    - Done meanwhile: originals go to the private disk; only the two sizes are public. The guessed address now answers 403.
    - Costs:
        - as done: two disks per photo, and R2 needs both buckets;
        - the other way: the 2560 px original (clean, but full size) is reachable by anyone who edits a link.
2. **HEIC is refused** (DECISIONS 100, OPEN_QUESTIONS 8).
    - Our image library lists HEIC but has no decoder for it.
    - Done meanwhile: the app must send JPEG, which Expo's picker and manipulator do in one line.
    - Costs:
        - as done: nothing on the server; the app converts;
        - the other way: the production image gets libheif's HEVC plugin (step 4.4) and HEIC joins the accepted list.
3. **Who opens agreements' documents** (DECISIONS 99).
    - Done meanwhile: super admins only by default, through a new permission a super admin can give to sales on the roles screen.
    - Costs:
        - as done: sales asks a super admin until given the permission;
        - the other way: seeding it for sales by default, a one-line change.
4. **Limits.** The brief's numbers are the defaults, plus a 120-megapixel cap that a 108 MP phone photo passes. All of them except the cap can be changed on إعدادات الصور.
5. **Packages.** The owner approved four packages for this task: the brief's three and Filament's media plugin.

## The owner's steps

None now; R2 waits for step 4.4. When it comes, in this order:

1. In Cloudflare R2, create two buckets: a public one with a custom domain (for example `media.awezeh.com`, a CNAME the dashboard creates) and CORS allowing GET from anywhere; and a private one with no public access.
2. Create an R2 API token with read and write on both buckets.
3. In Dokploy, set the environment names (values only there):
    - `MEDIA_DISK=r2`
    - `MEDIA_PRIVATE_DISK=r2_private`
    - `R2_ACCESS_KEY_ID`
    - `R2_SECRET_ACCESS_KEY`
    - `R2_ENDPOINT` (`https://<account id>.r2.cloudflarestorage.com`)
    - `R2_BUCKET`
    - `R2_PRIVATE_BUCKET`
    - `R2_PUBLIC_URL` (the custom domain)
4. Make the queue worker listen to `default,media`.
5. After deploying:
    - upload one photo from the app and open both sizes;
    - open an agreement's document from the panel and check that its link stops working after 5 minutes.

## Verification

**`sail composer check`:** Pint, Rector and Larastan (max, no baseline, no ignores) pass. Pest passes 1,147 tests with 3,211 assertions. `main` had 1,065; this task adds 82 (datasets counted):

- `Media/ImageUploadTest` (21):
    - each format stored as a clean JPEG with two WebP sizes;
    - GPS, camera and owner gone from the original and both sizes, read back with `exif_read_data`, and the photo turned upright (checked by its pixels);
    - the uploaded bytes and name not kept;
    - the original private and its guessed URL refused;
    - sizes for a wide, a tall and a small image, and for two far over the cap (decoded at a reduced scale first);
    - transparency turned white;
    - refusals (renamed text, executable, SVG, HEIC, no file, over the size, damaged, huge canvas) with Arabic messages;
    - English messages on request.
- `Media/PendingUploadsTest` (12):
    - the response while processing, the `media` queue, the conversion job run twice with the same files, then the URLs;
    - no URL for the original;
    - owner only (404 for anyone else);
    - deleting with files, expiry at 24 hours to the second, unknown ids;
    - signed-in only, the hourly budget shared with avatars;
    - the cleanup job twice, scheduled hourly on `media`.
- `Media/AttachUploadsTest` (10): the order kept with the same files, appending after existing photos, too many, someone else's, expired, bad ids, a failure rolling everything back, duplicates, and the limits from the settings.
- `Media/AvatarTest` (6): fallback, set, replace (old files gone), remove, refuse, and account deletion.
- `Media/PrivateDocumentsTest` (6):
    - documents on the private disk with no sizes;
    - no file served without a valid signature;
    - a 5-minute signed URL for a super admin, logged, refused after it expires;
    - refused for a moderator and outside the panel;
    - the panel button opens it in a new tab and logs only the click, hidden without the permission.
- `Media/StorageDiskTest` (3): files on faked `r2` and `r2_private` disks when `.env` names them, and the R2 disk settings.
- `Admin/ImageSettingsTest` (11): access per role, show and save with only the changes logged, nothing logged when nothing changed, four invalid values, and a new limit applied.
- `Admin/MediaFieldsTest` (3): an admin's upload cleaned through the field, the field's limits, the column.
- `Arch/MediaTest` (4):
    - no disk named in `app/` (a throwaway `Storage::disk('public')` made it fail, then it was removed);
    - every model with media on the shared contract and trait (11 models);
    - the contract is an interface;
    - only the image cleaner uses Imagick.
- Adjusted tests: the morph map, the delete rules (`pending_uploads.user_id` cascades) and the account routes. The only route id is an upload's, which its policy guards.

**Coverage:** 95.8% of `app` (`--min=85` passes); every class in `app/Domains/Media` is covered at 83% or more.

**Migrations on `testing`:** `migrate:fresh --seed` succeeded, then `migrate:rollback --step=4` rolled back the media settings, `pending_uploads`, `settings` and `media`, and `migrate` ran them again; 8 settings rows were back.

**API docs:** `sail composer docs` lists the Uploads group (POST with a multipart `file`; GET and DELETE with their 404) and the avatar under Account (multipart, documented as POST with `_method=PUT`, which Laravel accepts too).

**Real run on the dev stack** (this worktree's, `localhost:8031`, fake sign-in on):

- No real phone photo exists on this machine, so the test helper wrote one the way a phone does: 4032×3024 with the "turn 90°" orientation, GPS for Sadat City, a camera make and model, and an owner's name. Before upload, `exif_read_data`:

  ```
  {"size":"4032x3024","IFD0":{"Make":"Awez Test Phones","Model":"Pixel Sadat 9","Orientation":6,"Artist":"Ahmed El Sayed","GPS_IFD_Pointer":74},
   "GPS":{"GPSLatitudeRef":"N","GPSLatitude":["30/1","21/1","39/1"],"GPSLongitudeRef":"E","GPSLongitude":["30/1","31/1","21/1"]}}
  ```
- `curl -F file=@phone-photo.jpg POST /api/v1/uploads` with a fake sign-in token:

  ```
  {"data":{"id":"01a21709-b12f-…","thumb_url":null,"large_url":null,"width":1920,"height":2560,"processing":true,"expires_at":"2026-11-27T08:47:20Z"}}
  ```

  Turned upright (3024×4032), then capped (1920×2560).
- `queue:work --queue=media` ran `PerformConversionsJob` in 314 ms. The poll then answered `processing: false` with both URLs:
    - `thumb`: HTTP 200, `image/webp`, 480×640, 758 bytes;
    - `large`: HTTP 200, `image/webp`, 1600×2133, 6,658 bytes;
    - the original's guessed URL `…/storage/01a21709-b282-…/photo.jpg`: HTTP 403.
- After, with `exif_read_data`:

  ```
  original (private disk): 1920x2560, 59973 bytes, exif_read_data sections: ["FILE","COMPUTED"], GPS: none, IFD0 (camera, owner): none, camera/owner text in bytes: no
  thumb: 480x640, 758 bytes, exif_read_data sections: [], GPS: none, IFD0 (camera, owner): none, camera/owner text in bytes: no
  large: 1600x2133, 6658 bytes, exif_read_data sections: [], GPS: none, IFD0 (camera, owner): none, camera/owner text in bytes: no
  ```
- `AttachUploads` in `tinker` on the demo property "مخزن للإيجار مساحة 320 متر على شارع رئيسي":
    - photos went from 0 to 1, as `model_type=property`, `collection=photos`, `order=1`, with the same URLs;
    - the pending upload was gone, and `GET` on it then answered 404.
- `curl -X PUT -F file=@… /api/v1/me/avatar` (a real multipart PUT) answered 200 with `avatar_url: null` while processing (this account has no Google picture), then the small size's URL.
- An upload left to expire:
    - before: 1 private file and 2 public;
    - once past its time: `GET` answered 404;
    - `schedule:list` shows `0 * * * * App\Domains\Media\Jobs\DeleteExpiredUploads`;
    - `schedule:test` then the worker, twice: `pending uploads: 0; media row: 0; files: 0 private, 0 public`.

**Measurements:**

- An upload of the 4032×3024 JPEG, cleaning included, answered in 0.44 to 0.66 s (three runs, Sail on Apple silicon).
- Conversions took about 300 ms.
- The 339 KB upload became a 60 KB original, a 758-byte thumb and a 6.7 KB large. The demo picture is two flat colours; a real photo is larger.

## Not done and follow-ups

- **R2 itself** (buckets, CORS, the public domain, a real upload) waits for step 4.4, as the brief says; it is written into BUILD_PLAN 4.4 with the worker's `media` queue and the image's Imagick.
- **The real-run photo was synthetic**, written with a phone's EXIF by the test helper; the brief asked for a real phone photo. Uploading one from a phone to the same endpoint would repeat the check on a real camera's EXIF (MakerNotes, XMP), which the cleaner strips the same way.
- **The admin panel was checked by tests, not by hand:** the settings page, the field and the document button render and work in Livewire tests. A look at إعدادات الصور and an account's picture in the browser is worth a minute after the merge.
- **Found on the way, not from this task:** `composer audit` reports five advisories in Symfony 8.1.10 and 8.1.11 (`http-kernel`, `mailer`, `routing`), fixed in 8.1.12. Raising them is a small `composer update symfony/http-kernel symfony/mailer symfony/routing` in its own PR.
- **Found by CI:** libjpeg builds round a reduced-scale decode differently. CI's decoded a 4000×2000 photo at 2500×1250, just under the cap, and skipped the resize, while Sail's rounds up. The decoder is now asked for twice the target, so the resize always sets the exact size (DECISIONS 100's hint), and two tests cover it.
- File deletions inside a transaction (account deletion, the cleanup) are not undone if the transaction rolls back; the rows would remain without files. This is rare and harmless for uploads, and is noted for when it matters.

## Deploy notes

- **Migrations:**
    - `2026_11_26_100500_create_media_table`
    - `…100600_create_settings_table`
    - `…100700_create_pending_uploads_table`
    - the settings defaults `database/settings/2026_11_26_100800_create_media_settings`, run by `migrate`
- **New packages:**
    - spatie/laravel-medialibrary 11.23.14, with spatie/image 3.9.10 and spatie/image-optimizer 1.10.1
    - league/flysystem-aws-s3-v3 3.35.3, with aws/aws-sdk-php 3.408.0
    - spatie/laravel-settings 3.10.2
    - filament/spatie-laravel-media-library-plugin 5.12.0
- **The queue worker must listen to `media`** (for example `--queue=default,media`); `composer dev` already does.
- **The scheduler must run** (`schedule:run` every minute) for the hourly cleanup.
- **`php artisan storage:link`** once, while files are on the local public disk.
- **The image needs PHP's `imagick` and `exif` extensions.** PHP's `upload_max_filesize` and `post_max_size` must be above the settings' 10 MB; Sail has 100M.
- **New environment names**, unset until R2: `MEDIA_DISK`, `MEDIA_PRIVATE_DISK`, `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_ENDPOINT`, `R2_BUCKET`, `R2_PRIVATE_BUCKET`, `R2_PUBLIC_URL`.
- **New permissions:** `View:ImageSettings` and `ViewDocuments:PropertyAgreement`. Super admins receive them when the roles are seeded: `db:seed --class=ReferenceDataSeeder` runs `SyncAdminRoles`.
