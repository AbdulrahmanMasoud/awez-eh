# 0007. Photos are cleaned before they are stored, and only their sizes are public

## Context

Residents upload photos of their homes, their work and the things they sell. A phone's photo carries its GPS position, the camera, often the owner's name, and an orientation flag instead of upright pixels. Photos are needed before the listing that holds them exists (the app uploads while the resident writes), and storage moves to Cloudflare R2 later without code changes.

## Decision

- Every image is decoded and written again before it is stored: upright, with no metadata but its colour profile, at most 2560 px on its long side, as JPEG. The uploaded bytes and the client's file name are never kept. Only JPEG, PNG and WebP are accepted, by content, and ImageMagick is always told which decoder to use.
- A photo's cleaned original is on the private disk. Its two WebP sizes (480 and 1600 px wide) are public, and they are the only URLs that exist for it.
- Private documents (brokerage agreements' scans) have no public form at all: an allowed admin gets a signed URL for minutes, and each one is logged.
- Disks come from configuration only (`MEDIA_DISK`, `MEDIA_PRIVATE_DISK`); R2 is two disks on the S3 driver.
- Photos uploaded ahead of their listing are pending uploads, their uploader's alone for 24 hours. Attaching them hands the media rows to the listing; the files never move.

## Consequences

- No location or name leaves the platform inside a photo, whoever uploads it (residents through the API, admins through the panel).
- Reading the original takes the private disk (the conversions job, an admin tool later); the app only ever shows the sizes.
- HEIC needs a decoder the container lacks; the app sends JPEG.
- Each upload costs a decode in the request (about a second for a large photo) and two conversions on the `media` queue, which production's worker must listen to.
