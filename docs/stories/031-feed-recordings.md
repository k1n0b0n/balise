# Story 031: The feed recorded on the server, one public archive per service day

- **PRD:** E5.1
- **Status:** Done

## Acceptance criteria

See E5.1 in [prd.md](../prd.md); the decision is [ADR-0022](../adr/0022-feed-recordings.md).

## Tasks

- [x] `VehicleFeedSnapshot` carries the payload as received; `CatalogSnapshot::copyTo()` copies the downloaded catalogue
- [x] `FeedRecordings`: a payload kept in its service day, the day's end after 30 minutes without a fresh vehicle or at the network's cut (04:00 for TBM), the archive of every finished day not yet archived (`tar`, with the catalogue, a manifest and a README), the last 30 kept, `index.json`
- [x] The poll records after writing the positions, and logs a failed recording; the import keeps its catalogue, and downloads it again once when the recordings have none
- [x] `network:archive`, scheduled each night at `RECORDINGS_ARCHIVE_AT` in the network's time zone when the network records
- [x] nginx: `/recordings/` from a read-only volume, one connection per address (`429` beyond), 5 MB/s, hidden files refused; the visitor's address from `X-Forwarded-For` when a private address sends it; `robots.txt`
- [x] Server: the `balise-recordings` and `balise-recording-archives` volumes, `recordings` and `recordings_archive_at` in the inventory; the folders in the image belong to `balise`, so the volumes do too
- [x] Tests: the payload kept byte for byte, nothing on a `304` or an empty feed or with recordings off, a failed write, the day across midnight, a gap within a date, the fallback cut, the archive's content, a running day and a day dated today left alone, the catch-up, the retention, the catalogue kept and downloaded once

## Notes

- **The day is the network's, not the calendar's.** The prototype's recordings show TBM empty from about 01:30 to 04:30. A day ends once no fresh vehicle has been seen for 30 minutes, and is named after the date it began: the trams of 01:15 belong to the day before. A gap within one date (the feed down at noon) keeps the same day.
- **A day dated today is never archived**, even when it looks finished: archived at 05:15, a network that briefly ran at 04:00 would see that date's real day start after its archive. It waits for the next night.
- **`tar`, not `PharData`.** PHP keeps every archive it has opened in memory by path, so an archive written again under the same name read as the old one. The day's folder is laid out as the archive (`vehicles/`, `catalog.zip`, `manifest.json`, `README.md`) and `tar` takes it as it is; the manifest is also written beside the archive, so that the list does not open every archive.
- **Payloads gzipped one by one, the archive not compressed**: gzip's window is 32 KB, about one payload, so compressing the archive again would gain almost nothing; a replay reads one payload without the others.
- **The visitor's address.** Behind the host's nginx, every request came from Docker's gateway: one download at a time would have meant one for everybody. The web container now takes the address from `X-Forwarded-For`, only when a private address sends it; the access log shows visitors too.
- **Checked locally**: the index served as JSON, hidden archives and `..` refused (`403`), 5.2 MB/s for one download, `429` for a second one at the same time; the server's compose rendered and accepted by `docker compose config`; the production image's folders and a new volume writable by `balise`.
- **On the server**: the bootstrap must run before the next deploy, which then starts recording; the first archive comes the night after the first full service day.
