# Implementation Plan - Revised Step 1: Local Development & Symfony 7.4 Skeleton

Configure the hybrid local-development / Docker-delivery workflow, enforce LF line endings, update `plan.md` policies, and initialize Symfony 7.4 with PHP 8.2 platform pinning.

## User Review Required

> [!IMPORTANT]
> - **Host Machine Setup**: You will enable `intl` and `zip` in `C:\xampp\php\php.ini` and install Composer on your Windows PATH (instructions detailed in response).
> - **Environment Policy**: Daily development runs locally (PHP 8.2 + Composer), MySQL runs in Docker with port 3306 exposed via dev-only `compose.override.yaml`. Full Docker stack is tested for Level 5 delivery.
> - **Platform Pinning**: `composer.json` will enforce `"config": {"platform": {"php": "8.2.0"}}`.
> - **CRLF Prevention**: `.gitattributes` will enforce `* text=auto eol=lf`.
> - **Git Discipline**: No `git add .`. Every commit will have an explicit file list, and `plan.md` tracker updates will have separate `docs:` commits. No co-author trailers.

## Proposed Changes

### Documentation & Repository Configuration

#### [MODIFY] plan.md
- **Section 0**: Add "Local vs Docker Environment Policy".
- **Section 3**: Add explicit staging rule (no `git add .`), separate tracker commits, `.env` conventions.
- **Section 5.3**: Clarify `.env` vs `.env.local` vs `.env.example`.
- **Section 5.4**: Add PHP 8.2 platform pinning in `composer.json`, identical PHP extensions requirement for Docker, and dev-only `compose.override.yaml`.
- **Section 6**: Update technical decisions table with local dev PHP 8.2 + Composer and Docker for Level 5 delivery.

#### [NEW] .gitattributes
- Set `* text=auto eol=lf` to prevent Windows CRLF issues across git and Docker entrypoints.

### Application Bootstrap

#### [NEW] Symfony 7.4 Skeleton
- Initialize clean Symfony 7.4 files:
  - `composer.json` (with `"config": {"platform": {"php": "8.2.0"}}`)
  - `composer.lock`, `symfony.lock`
  - `bin/console`
  - `config/` (`bundles.php`, `packages/`, `routes.yaml`)
  - `public/index.php`
  - `src/Kernel.php`

#### [NEW] .env and .env.example
- `.env`: committed with non-secret local placeholders (`DATABASE_URL="mysql://app_user:app_password@127.0.0.1:3306/mediatheque?serverVersion=8.0&charset=utf8mb4"`).
- `.env.example`: documented copy of all required environment variables.

#### [MODIFY] .gitignore
- Retain existing rules (`/md files`, `/traces-ia/`, `.DS_Store`, `Thumbs.db`) and incorporate Symfony Flex ignore rules (`/vendor/`, `/var/`, `/.env.local*`).

---

## Verification Plan

### Automated Tests
- `php -v`, `php -m | findstr -i "intl zip"`, `composer -V` to confirm host readiness.
- `php bin/console about` to confirm Symfony 7.4 boot on PHP 8.2.12.
- `git status` and `git diff --stat` to verify no `vendor/`, `var/`, or `.env.local` are staged.

### Manual Verification
- Verify that `composer.json` contains `"platform": {"php": "8.2.0"}`.
- Inspect `.gitattributes` line ending setting.
