# TASK-015 — Remove local access and agent files from repository tracking

## Status

COMPLETED

## Goal

Keep local server-access instructions and agent guidance out of the repository's current tracked files.

## Source requirements / Traceability

- SOW.md → N/A
- architecture.md → N/A
- DB.md → N/A
- backlog.md → TASK-015
- AGENTS.md → Documentation policy, Git policy / local repository governance

## Scope

- Remove root `versions.md` from the repository.
- Ignore root `AGENTS.md` and stop tracking it while preserving the local copy.
- Confirm `.server-access/` remains ignored and contains no tracked files.

## Non-goals

- Rewriting Git history.
- Removing local `AGENTS.md` from the user's working copy.
- Changing server credentials or access configuration.

## Invariants / Constraints

- Local `AGENTS.md` remains available on disk.
- No contents of `.server-access/` are added to Git.
- Existing unrelated working changes are preserved.

## Acceptance criteria

- [x] AC-1 `versions.md` is absent from the working tree and Git index.
- [x] AC-2 `AGENTS.md` remains available locally but is ignored and no longer tracked.
- [x] AC-3 `.server-access/` is ignored and no files beneath it are tracked.
- [x] AC-4 Changes and history limitation are documented.

## Implementation plan

- [x] Remove `versions.md` from the working tree and Git index.
- [x] Add `/AGENTS.md` to `.gitignore` and remove it from the Git index while preserving the local file.
- [x] Verify ignore rules, tracked-file state, and diff formatting.
- [x] Update backlog and implementation log; finalize after owner approval.

## Verification plan

| Acceptance criterion | Verification |
|---|---|
| AC-1 | `test ! -e versions.md`; `git ls-files versions.md` returns no paths |
| AC-2 | `test -f AGENTS.md`; `git check-ignore -v AGENTS.md`; `git ls-files AGENTS.md` returns no paths |
| AC-3 | `git check-ignore -v .server-access/example`; `git ls-files .server-access` returns no paths |
| AC-4 | Review backlog and implementation-log entries |

## Documentation impact

- README: no
- OpenAPI: no
- Database diagram: no
- Other: `.gitignore`, backlog, implementation log

## Implementation notes

- `AGENTS.md` was tracked, so a `.gitignore` rule alone would not have excluded it. It was removed from the index with `git rm --cached`, preserving the local file.
- `.server-access/` was already covered by the root ignore rule `/.server-access/`.

## Findings / Risks

- Removing `versions.md` from the current tree does not erase its content from earlier Git commits.
- After the owner commits, `AGENTS.md` will no longer be present in the shared repository; the local copy remains available in this checkout.

## Decisions created

- None.

## Review feedback

- Одобрено владельцем 2026-10-23.

## Completion summary

Удалён `versions.md`; `AGENTS.md` сохранён локально, но исключён из Git tracking; проверено, что `.server-access/` игнорируется и не содержит tracked файлов. `git diff --check` прошёл.

## Proposed commit

`chore(TASK-014, TASK-015): fix CI and exclude local files`
