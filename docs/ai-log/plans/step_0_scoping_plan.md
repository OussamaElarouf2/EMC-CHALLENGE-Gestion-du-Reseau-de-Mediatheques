# Implementation Plan - Step 0: Scoping & Alignment

Establish foundational alignment on the project scope, technical conventions, Git workflow, and initial documentation scaffolding before writing code.

## User Review Required

> [!IMPORTANT]
> - **Language Convention**: Confirm English for code, entities, database schema, tests, and commit messages; French for UI, `README.md`, `INSTALL.md`, `MODELE.md`, and `DECISIONS.md`.
> - **Domain Vocabulary**: Confirm core terms: Work, Edition, Copy, Member, Loan, Reservation, Penalty, Plan, Branch, Transfer.
> - **Remote Repository**: Confirm you have created the public GitHub repository on your personal account.

## Open Questions

> [!NOTE]
> 1. Do you want to fix `.gitignore` immediately to remove the self-exclusion (`/.gitignore`) and add standard ignore rules (`vendor/`, `var/`, `.env.local`, `traces-ia/`)?
> 2. Should we stage and commit the existing `docs/Project statement/` files and `plan.md` in Step 0?

## Proposed Changes

### Documentation & Repository Configuration

#### [MODIFY] .gitignore
- Remove self-ignoring rule `/.gitignore`.
- Add patterns for Symfony (`/vendor/`, `/var/`, `.env.local*`), OS artifacts (`.DS_Store`, `Thumbs.db`), and local AI traces (`traces-ia/`).

#### [NEW] docs/DECISIONS.md
- Create the ADR / decisions journal template with timestamp, context, decision, and consequences.
- Record Decision 001: Language conventions and core domain vocabulary.

#### [NEW] docs/MODELE.md
- Create initial model file with domain lexicon table (FR / EN).

#### [MODIFY] plan.md
- Synchronize paths and update Section 15 progress tracker for Step 0.

---

## Verification Plan

### Automated Tests
- `git status` to verify working tree cleanliness and proper tracking.

### Manual Verification
- Verify that `.gitignore` correctly ignores `traces-ia/` and local files without ignoring itself.
- Verify that `docs/DECISIONS.md` and `docs/MODELE.md` are correctly initialized.
