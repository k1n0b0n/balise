# Story 022: Report a problem

- **PRD:** E3.9
- **Status:** Done

## Acceptance criteria

See E3.9 in [prd.md](../prd.md).

## Tasks

- [x] `app.repository_url` (`APP_REPOSITORY_URL`, this repository by default) and a small link to its issue chooser under the header's subtitle
- [x] Issue forms in `.github/ISSUE_TEMPLATE`: bug, feature request, wrong data; blank issues disabled
- [x] Test: the link points to the configured repository, its name says it opens GitHub, and it opens in the same tab
- [x] Once merged: the issue chooser lists the three forms and no blank issue, checked by the maintainer (2026-10-24)

## Notes

- **Small, in the header.** Beside the Pause button it pushed "Liste des véhicules" onto two lines on a phone; at the end of the vehicle list it was out of sight. It sits under the subtitle in small grey text, "Signaler un problème", the header's buttons on one line at 390 px.
- **Same tab, and GitHub in its name.** The link leaves the site: its accessible name ends with "sur GitHub", visually hidden, after the visible text (WCAG 2.5.3, the visible label starts the name). A new tab would need a warning too (RGAA 13.2), for no gain on a page people come back to with the back button.
- **The forms are in French**, like the site that links to them, although the repository is in English: the people who report are the site's users. Their file names are numbered to set their order in the chooser.
- **Labels.** The bug and feature forms use GitHub's default `bug` and `enhancement` labels; wrong data has none and is told apart by its `[Donnée]` title, since a new label is a repository setting.
- **Wrong data asks for what the site shows**: the line as written in the selector, the vehicle number of the popup and the text view, the time. Filling them from the popup goes to the PRD's *Later*.
- **Only the default branch counts.** GitHub reads the forms from `main`, so the chooser can be checked only after the merge.
