# Story 022: Report a problem

- **PRD:** E3.9
- **Status:** Done

## Acceptance criteria

See E3.9 in [prd.md](../prd.md).

## Tasks

- [x] `app.repository_url` (`APP_REPOSITORY_URL`, this repository by default) and a link to its issue chooser at the end of the text view (the list of vehicles)
- [x] Issue forms in `.github/ISSUE_TEMPLATE`: bug, feature request, wrong data; blank issues disabled
- [x] Test: the link points to the configured repository, says it opens GitHub, and opens in the same tab
- [ ] Once merged: the issue chooser lists the three forms and no blank issue (*Current state*)

## Notes

- **Out of the way.** First in the header, then under its subtitle; at the maintainer's request it ends the text view instead, under a rule: beside the map in landscape, at the end of the side panel on a phone, under the map without JavaScript. The map and its controls keep the top of the page.
- **Same tab, said in the text.** "Signaler un problème sur GitHub": the link leaves the site, and its text says where it leads; a new tab would need a warning too (RGAA 13.2), for no gain on a page people come back to with the back button.
- **The forms are in French**, like the site that links to them, although the repository is in English: the people who report are the site's users. Their file names are numbered to set their order in the chooser.
- **Labels.** The bug and feature forms use GitHub's default `bug` and `enhancement` labels; wrong data has none and is told apart by its `[Donnée]` title, since a new label is a repository setting.
- **Wrong data asks for what the site shows**: the line as written in the selector, the vehicle number of the popup and the text view, the time. Filling them from the popup goes to the PRD's *Later*.
- **Only the default branch counts.** GitHub reads the forms from `main`, so the chooser can be checked only after the merge.
