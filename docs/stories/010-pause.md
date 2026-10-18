# Story 010: Pause and resume live updates

- **PRD:** E3.6
- **Status:** Review

## Acceptance criteria

See E3.6 in [prd.md](../prd.md).

## Tasks

- [x] `resources/js/map/live.ts`: `followVehicles` returns a control with `pause()` and `resume()`; while paused no timer runs and a visible tab does not restart polling; resuming sends one request for the changes since the last version, then the normal interval resumes
- [x] Pause button in the header, rendered hidden and shown by the map module (it does nothing without JavaScript); `aria-pressed` follows the state, the label stays the same
- [x] The status line says the updates are paused, with the time of the last one; resuming writes the normal status again at once, since the catch-up request may get a `304`
- [x] Browser test: no request while paused, one request on resume, `aria-pressed`, status line, axe

## Notes

- A response already on its way when the user pauses is dropped, version included: nothing moves after the click, and resuming asks again from the same version.
- The button keeps its label ("Pause des mises à jour" for assistive technologies) and only `aria-pressed` changes, as a toggle button should; pressed, it is dark.
- The line catalogue pauses too: an answer arriving during the pause is kept but drawn only on resume, and a retry cancelled by the pause, or a load failing during it, runs again on resume. Not paused: the ages ("il y a 25 s") keep counting, since they stay true.
- The browser test counts requests with the Resource Timing API, the refresh interval set to 1 s.
- The popup test failed in the full suite (twice), never with the browser file alone, nor on `main`: `data-vehicle-count` was written when the data was handed to MapLibre, whose worker draws it later, so a test could click before the vehicle was there. The attribute is now written when the map is next idle.
- JavaScript budget: 501.3 KB compressed (+0.3 KB).
- CodeRabbit reviewed the pull request on its own when it was marked ready (the explicit request then hit the hourly limit): four findings. Fixed: a catalogue answer redrawing during the pause, a missing-line retry lost on resume, the test counting requests by end rather than start. Not changed: a request started before a pause and ending after a quick resume is applied, since its data is no older than the catch-up request would bring; the interval then resumes.
