# Implementation Plan - Revised Step 4: Entities, Enums, Constraints & Migrations

Bootstrap the complete database schema with 7 PHP 8.1+ Enums, 13 Doctrine Entities (table `app_user`, constructor-injected dates, `RESTRICT` foreign keys, `enumType` mappings), 5 dedicated repositories, and a fully reversible database migration.

## User Review Required

> [!IMPORTANT]
> - **Environment & DATABASE_URL**:
>   - `.env` & `.env.example`: point to `db:3306` (`DATABASE_URL="mysql://app_user:app_password@db:3306/mediatheque?serverVersion=8.0&charset=utf8mb4"`).
>   - `.env.local` (git-ignored): points to `127.0.0.1:3307` for local execution against Docker MySQL.
>   - `compose.override.yaml` (git-ignored): exposes `127.0.0.1:3307:3306`.
> - **Flex Recipe Protection**: After `composer require`, we will reject any Flex changes that inject PostgreSQL or alter Docker files, showing `git diff` of `.env`, `compose.yaml`, and `composer.json` before proceeding.
> - **No `new \DateTimeImmutable()` in Entities**: All creation dates are supplied via constructors (ClockInterface injected in services/fixtures). No lifecycle callbacks.
> - **Foreign Key Constraints**: All relations on `Loan`, `Penalty`, `Reservation`, and `Transfer` use `onDelete: 'RESTRICT'` (no cascade deletion of financial or circulation history).
> - **Table Name for User**: Mapped as `app_user`.
> - **Repositories**: Strictly 5 custom repositories (`LoanRepository`, `ReservationRepository`, `CopyRepository`, `EditionRepository`, `MemberRepository`). The remaining 8 entities use standard Doctrine repository.

## Proposed Changes

### Dependencies & Configuration

#### [MODIFY] composer.json & composer.lock
- Require `symfony/orm-pack` and `symfony/validator`.

#### [MODIFY] .env & .env.example
- Ensure `DATABASE_URL` uses host `db` and `serverVersion=8.0`.

### Domain Enums

#### [NEW] src/Enum/
- `MediaType.php` (`BOOK`, `COMIC`, `DVD`)
- `CopyStatus.php` (`AVAILABLE`, `ON_LOAN`, `HELD_FOR_PICKUP`, `IN_TRANSIT`, `LOST`, `DAMAGED`)
- `ReservationStatus.php` (`WAITING`, `AWAITING_TRANSFER`, `READY`, `FULFILLED`, `EXPIRED`, `CANCELLED`)
- `TransferStatus.php` (`REQUESTED`, `IN_TRANSIT`, `RECEIVED`, `CANCELLED`)
- `ContributorRole.php` (`AUTHOR`, `ILLUSTRATOR`, `TRANSLATOR`, `DIRECTOR`, `OTHER`)
- `PenaltyReason.php` (`LATE`, `LOST`, `DAMAGED`)
- `CompletenessStatus.php` (`COMPLETE`, `TO_COMPLETE`)

### Domain Entities & Repositories

#### [NEW] src/Entity/ & src/Repository/
- **Reference & Catalogue**:
  - `Branch.php`, `Plan.php` (MAD currency, decimal 10,2), `User.php` (`#[ORM\Table(name: 'app_user')]`).
  - `Work.php`, `Contributor.php`, `Edition.php` + `EditionRepository.php`, `EditionContributor.php`, `Copy.php` + `CopyRepository.php`.
- **Members & Circulation**:
  - `Member.php` + `MemberRepository.php` (`subscriptionEndsAt`, OneToOne unique `app_user`).
  - `Loan.php` + `LoanRepository.php` (RESTRICT, nullable unique `activeCopyId`, index `member_id, returned_at`).
  - `Reservation.php` + `ReservationRepository.php` (RESTRICT, nullable unique `activeMemberEditionKey`, index `edition_id, status, created_at`).
  - `Penalty.php` (RESTRICT on Loan, decimal 10,2 amount).
  - `Transfer.php` (RESTRICT on Copy and Reservation, nullable reservation).

### Database Migration

#### [NEW] migrations/Version2026XXXXXXXXXX.php
- Generated via `doctrine:migrations:diff`.
- Full forward and backward (`down()`) schema migration.

---

## Verification Plan

### Automated Tests
1. `docker compose up -d db` to ensure MySQL container is listening on `127.0.0.1:3307`.
2. Inspect `git diff` on `.env`, `compose.yaml`, and `composer.json` post-composer install.
3. Apply migration: `php bin/console doctrine:migrations:migrate --no-interaction`.
4. Schema validation: `php bin/console doctrine:schema:validate`.
5. Rollback & re-apply test:
   - `php bin/console doctrine:migrations:migrate prev --no-interaction` (confirm rollback).
   - `php bin/console doctrine:migrations:migrate --no-interaction` (confirm re-application).
   - `php bin/console doctrine:schema:validate` (confirm database is in perfect sync).

### Manual Verification
- Check that all enum columns use `enumType`.
- Confirm no `new \DateTimeImmutable()` or `@ORM\HasLifecycleCallbacks` exists in entities.
- Confirm table `app_user` and `RESTRICT` foreign keys.
