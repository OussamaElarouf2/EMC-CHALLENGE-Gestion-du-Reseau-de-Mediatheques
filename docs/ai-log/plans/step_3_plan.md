# Implementation Plan - Step 3: Domain Data Model & Architectural Decisions

Formalize the complete relational data model in `docs/MODELE.md` with an explicit Mermaid ER diagram and document the 5 core decisions in `docs/DECISIONS.md` before generating entities or migrations.

## User Review Required

> [!IMPORTANT]
> Please review the proposed answers to the 5 "Questions to Settle" from the project statement:
> 1. **Work vs Edition**: Two editions of the same novel share the same `Work`, but have distinct `Edition` entities (distinct ISBN, publisher, publication year).
> 2. **Cross-Branch Reservation**: Allowed. A member can reserve an edition only available at the other site; when a copy is assigned, an automatic `Transfer` is triggered.
> 3. **Hold Duration**: 5 calendar days with status `held_for_pickup`. If uncollected, expires and moves to the next member in the FIFO queue.
> 4. **Multiple Contributors**: Handled via `Contributor` and a pivot `EditionContributor` with enum `role` (Author, Translator, Illustrator...) and `position` ordering.
> 5. **Open Library Conflict & Incompleteness**: Local catalogue data has absolute priority (never overwritten). Incomplete records imported from Open Library are flagged with status `to_complete` for librarian review.

## Proposed Changes

### Documentation

#### [MODIFY] docs/MODELE.md
- Add the full Mermaid `erDiagram` connecting all 12 entities:
  `Branch`, `Work`, `Edition`, `Contributor`, `EditionContributor`, `Copy`, `Plan`, `User`, `Member`, `Loan`, `Reservation`, `Penalty`, `Transfer`.
- Detail entity properties, types (integers, strings, DateTimeImmutable, decimals for money), and PHP 8.1+ Enums (`CopyStatus`, `ReservationStatus`, `LoanStatus`, `TransferStatus`, `MediaType`, `ContributorRole`, `PenaltyReason`).
- Document relationship cardinalities and data integrity constraints (indexes, foreign keys, unique constraints).

#### [MODIFY] docs/DECISIONS.md
- Record formal ADR entries:
  - `DEC-002`: Distinction between Work and Edition
  - `DEC-003`: Cross-branch reservations and transfer handling
  - `DEC-004`: Reservation hold duration (5 calendar days) and FIFO queue lifecycle
  - `DEC-005`: Modeling multiple contributors with roles and display order
  - `DEC-006`: Open Library data reconciliation and priority policy

#### [MODIFY] plan.md
- Update Section 15 progress tracker for Step 3 in a separate commit.

---

## Verification Plan

### Automated Tests
- Syntax validation of Mermaid `erDiagram` to ensure clean rendering.
- Cross-entity constraint verification against all Level 2 rules (no double loan, FIFO queue, penalties, transfers).

### Manual Verification
- Review Mermaid diagram visually in `docs/MODELE.md`.
- Confirm each of the 5 questions has a justified rationale in `docs/DECISIONS.md`.
