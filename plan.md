# plan.md — EMC PFE Challenge · Subject 4: Library Network (Médiathèques)

> **This file is your permanent reference.** Re-read it in full at the start of every session and before every new step. Update the "Progress tracker" (section 15) after each validated step.

---

## 0. Roles and the golden rule

- **Me (the candidate)**: I own the design and every decision. I validate, understand, defend each line in front of a jury, **and I make all commits myself**.
- **You (the coding agent)**: you propose, explain, and write code **from my specification, only after my explicit approval**. You never commit and never push.

### Golden rule: the 5-beat protocol

For **every step** of the plan (section 9), follow this cycle without exception:

1. **PLAN**: tell me what you are going to do (files created/modified, entities, services, commands, tests), the technical options and their trade-offs, and the business assumptions I need to settle.
2. **WAIT**: stop and wait for my "OK" or my corrections. **Do not create, modify or delete any file, and do not run any command that changes the project, before my OK.**
3. **EXECUTE**: apply exactly what was validated, nothing more (no bonus features, no unrequested refactoring).
4. **VERIFY**: run the relevant tests / linters / check commands and show me the output, failures included.
5. **PROPOSE THE COMMIT**: give me the list of files to stage and a ready-to-use commit message (with the AI trailer when the code is yours, see section 3). **I run `git add`, `git commit` and `git push` myself.**

If something is ambiguous, **ask, do not guess.** If you notice a mistake in one of your earlier answers, tell me immediately.

### Never do any of this without my explicit confirmation

- Write or change code, migrations, configuration or Docker files.
- Install a dependency (propose it first with the reason and its footprint).
- Run a destructive command (`doctrine:schema:drop`, `git reset --hard`, `rm -rf`, etc.).
- Run any Git write command (`add`, `commit`, `push`, `rebase`, `merge`, `reset`, `checkout` that discards changes). Read-only commands (`status`, `diff`, `log`) are fine.
- Put a secret, a real password or an API key in a versioned file.

### Environment policy: Local development vs Docker delivery
- **Daily development**: runs **locally** on Windows with PHP 8.3 (>= 8.3.36) and Composer in the PATH. The candidate installs/verifies them personally; the agent never installs host software.
- **Docker**: the **delivery** environment (Level 5) and the benchmark for "works on a clean machine". The candidate tests `docker compose up --build` after each major milestone and from a fresh clone before final submission.
- **Local services**: only MySQL 8 runs in Docker for local development. A dev-only `compose.override.yaml` exposes MySQL on 127.0.0.1; the delivered `compose.yaml` must not expose the database port needlessly.
- **Reproducibility**: `composer.json` pins `"config": {"platform": {"php": "8.3.0"}}` so `composer.lock` is strictly bound to PHP 8.3. The Docker image installs the exact same PHP extensions as the local setup (`intl`, `pdo_mysql`, `mbstring`, `xml`, `ctype`, `iconv`, `zip`). List documented in `INSTALL.md`.

---

## 1. Project context

### 1.1 The challenge

Pre-selection for a PFE (end-of-studies internship) at **Education Media Company** (Technopark Souss Massa, Agadir). Successful candidates are offered a PFE with a prospect of being hired afterwards.

- **Chosen subject: Subject 4 — Library network management.**
- **Imposed stack**: Symfony 7.4, PHP 8.3+, MySQL 8, Doctrine ORM and migrations. Free choice for the front end.
- **Individual work, AI allowed** (and encouraged: my AI usage traces earn up to +15 bonus points).
- **Real time budget: 2 days.** Every level must be a **minimal but clean** version. The brief itself says that three solid levels beat five sloppy ones. The goal is all 5 levels, but **quality comes before quantity**.

### 1.2 Reference documents

The official briefs are in the repository under `docs/Project statement/`: `README.md`, `00-demarche.md`, `sujet-4-mediatheque.md` (the other three subjects are out of scope). **When in doubt about a requirement, re-read these files.**

### 1.3 The subject in short

Two neighbourhood libraries, about 8,000 documents (books, comics, DVDs), about 1,500 members. A tool for librarians **and** members.

Needs: catalogue (works, editions, copies spread over 2 sites) · members and subscription plans · loans, returns, extensions, reservations · late penalties · copies moving between sites.

| Level | Required content |
|---|---|
| 1 Model | Data model, MySQL migrations, realistic demo data (a few hundred documents, members, ongoing and past loans), back-office for the main objects |
| 2 Rules | Simultaneous-loan quota and loan duration per plan · reservations with a waiting queue (first in line is served when the copy comes back) · extension only when nobody is waiting · late penalties · lost or damaged copies · **automated tests** of these rules |
| 3 Product | Accounts and roles (member, librarian, administrator) · public catalogue with search · member area (current loans, reservations, history) · statistics (turnover rate, most borrowed, never borrowed) |
| 4 External API | **Open Library**: create a record from an ISBN (title, authors, publisher, cover), one at a time **and in batch** |
| 5 Deliver | Ready-to-use Docker environment (one command), automatic migrations + fixtures, `INSTALL.md`, tested on a clean machine |

---

## 2. Grading: where the points are

| Block | Criterion | Points |
|---|---|---|
| Method | Git history | 15 |
| | Design note (1-page PDF) | 10 |
| | Data model and documented decisions | 10 |
| | Automated tests | 10 |
| Result | Level 1 | 15 |
| | Level 2 | 15 |
| | Level 3 | 10 |
| | Level 4 | 7 |
| | Level 5 | 8 |
| Bonus | AI usage traces | +15 |

**45 points come from method** (Git, note, model/decisions, tests). They are built continuously, not at the end. Absolute priorities: clean Git history, level 2 tests, `DECISIONS.md`, a working Docker setup.

**Evaluation condition**: a complete, intact Git history is required for the work to be graded.

---

## 3. Git rules (mandatory)

- **Public** repository on my own GitHub account, created on day 1, first commit as soon as the Symfony skeleton exists.
- **I make every commit myself.** You supply the proposed message and the explicit file list. **Never use `git add .`**. Run `git status` and `git diff --stat` before proposing the commit.
- **Progress tracker updates**: keep `plan.md` progress tracker checkmarks in a separate `docs:` commit.
- **Small, frequent, focused commits** (every 30 to 45 minutes at most), one concern per commit.
- **Conventional Commits** in English: `feat:`, `fix:`, `test:`, `docs:`, `refactor:`, `chore:`, `build:` (Docker), `style:`. Example: `feat: refuse loan extension when a reservation is waiting`.
- **AI trailer**: omitted per user instruction.
- **Intact history**: never `rebase`, never squash, never amend a pushed commit, never `push --force`. If a branch is used, merge it with `--no-ff`.
- **Push often**; every evening the repo must be in a working state, tests green, pushed.
- **.env policy**: `.env` is committed with non-secret development placeholders only (Symfony standard). `.env.local` and `.env.*.local` are git-ignored. `.env.example` is a documented copy of all variables. The MySQL password in `DATABASE_URL` is a dev-only placeholder and must be overridden by environment variables in Docker. Never commit: `.env.local`, keys, real passwords, `vendor/`, `var/`, raw AI traces.

### Typical commit breakdown for one feature

1. `docs:` decision or assumption in `DECISIONS.md`
2. `feat:` entity + migration (one migration per logical change)
3. `feat:` business service
4. `test:` tests for that service
5. `feat:` controller / view
6. `docs:` model diagram / README levels table update

---

## 4. Document as you go

Documentation is part of the grade and must never be postponed to the end. After each step, check this list and tell me which items apply:

- **Entity or schema change**: update the Mermaid diagram and the rationale in `docs/MODELE.md`; add **one migration per logical change** (never edit an already applied migration); update the fixtures.
- **Assumption or ambiguity settled**: add a **dated** entry to `docs/DECISIONS.md` (context, options, choice, consequence). The five "Questions to settle" from the subject must each get an entry.
- **Business rule**: describe it in plain words in `docs/DECISIONS.md` **and** cover it with an automated test. Rules that involve concurrency (no double loan of the same copy, no two members taking the same reserved copy) need dedicated concurrency tests.
- **Feature finished or changed**: update the levels table in `README.md` (works / remaining).
- **End of every work session**: add a short note in `docs/ai-log/` named `YYYY-MM-DD-session-N.md` with three parts: what I asked, what the AI got wrong, how it was caught. You draft it, I review and edit it. This feeds both the AI-traces bonus and the one-page design note.

---

## 5. Code quality principles (non-negotiable)

### 5.1 Clean code

- **Explicit, consistent names**, using the domain vocabulary (see 7.1).
- **Short functions**, one responsibility, few parameters, no hidden side effects.
- **SOLID**, especially single responsibility, open/closed and dependency inversion.
- **DRY without over-abstraction**: no abstraction before a second real use (YAGNI, KISS).
- **No dead code, no useless comments.** A comment explains *why*, never *what*.
- **No magic numbers or strings**: constants and PHP 8.1+ enums (`LoanStatus`, `CopyStatus`, `ReservationStatus`, `ContributorRole`, `MediaType`).
- **Strict typing everywhere**: `declare(strict_types=1);`, parameter/return/property types, `readonly` when relevant.
- **Dedicated domain exceptions** (e.g. `LoanQuotaExceededException`, `ExtensionNotAllowedException`) instead of generic exceptions or ambiguous boolean returns.
- **Standards**: PSR-12 (PHP-CS-Fixer or equivalent) and PHPStan at a reasonable level (e.g. 6) if time allows. Propose them at step 0.

### 5.2 Architecture and loose coupling

- **Thin controllers**: validate input, call **one** application service, return a response. **No business rule in controllers, templates or EasyAdmin configuration.**
- **Business logic in dedicated services** (`src/Service/` or `src/Domain/...`), one responsibility each: `LoanService`, `ReservationQueueService`, `PenaltyCalculator`, `TransferService`, `OpenLibraryClient`, `IsbnImporter`, `StatisticsService`.
- **Constructor dependency injection** only: no `new` on services, no service locator, no static access.
- **Depend on abstractions** for anything external (e.g. `BookMetadataProviderInterface` implemented by `OpenLibraryClient`) so it can be mocked in tests and replaced later.
- **Time is injected**: use the Symfony/PSR `ClockInterface`. **Never call `new \DateTime()` or `date()` in business logic.** Tests use `MockClock`.
- **Doctrine repositories** hold all queries: no DQL/SQL in controllers or business services (they call clearly named repository methods).
- **Entities carry simple invariants** (e.g. `Copy::markAsLost()`); cross-entity rules live in services.
- **Symfony events** to decouple side effects (e.g. "copy returned" → reservation queue processing), only if it really simplifies things.
- **DTOs / value objects** for complex inputs (forms, imports) rather than associative arrays.

### 5.3 Security

- **Secrets & .env**: environment variables only. `.env` is committed with non-secret development placeholders (Symfony convention). `.env.local` and credentials are in `.gitignore`; `.env.example` is kept current as a documented copy of all variables without secrets. The MySQL password in `DATABASE_URL` is a dev-only placeholder and must be overridden by environment variables in Docker (Step 2). Demo account passwords are documented as demo-only in `INSTALL.md`.
- **Authentication**: Symfony Security, hashed passwords (`auto` hasher), never plain text.
- **Authorization**: `access_control` in `security.yaml` **and** fine-grained checks via **Voters** (e.g. a member only sees their own loans). Least privilege. Role hierarchy: `ROLE_ADMIN` > `ROLE_LIBRARIAN` > `ROLE_MEMBER`.
- **Anti-IDOR**: always verify that the requested resource belongs to the logged-in user.
- **CSRF** on every form and every state-changing action. No state change through `GET`.
- **SQL injection**: parameterized queries only (QueryBuilder/DQL with parameters), never string concatenation.
- **XSS**: keep Twig auto-escaping; `|raw` is forbidden unless justified in `DECISIONS.md`.
- **Validation**: Symfony Validator on entities/DTOs, always server-side.
- **External calls**: timeouts, ISBN format validation before any call, no URL built from raw user input (SSRF prevention), clean error handling.
- **Cookies/headers**: `secure`, `httponly`, `samesite` session cookies in production; no detailed errors outside `dev`.
- **Login throttling** (`login_throttling`) if time allows.
- **Logs**: no personal data and no secrets.
- **Docker**: non-root containers if possible, MySQL port not exposed needlessly, passwords passed via environment variables.
- **Before the final submission**: secret audit of the repository and of the AI-traces archive (`git grep`, manual review).

### 5.4 Data integrity, concurrency and reproducibility

- **Database constraints** in addition to application validation: foreign keys, `UNIQUE` (ISBN, copy barcode, email), `NOT NULL`, indexes on search and join columns.
- **Transactions** (`EntityManager::wrapInTransaction()`) for every multi-write operation (borrow, return, queue processing, transfer).
- **No double loan**: pessimistic lock (`LockMode::PESSIMISTIC_WRITE`) on the copy while borrowing, or optimistic locking (`#[ORM\Version]`) with proper exception handling. I validate the choice at the relevant step.
- **Money**: never `float`. Use `DECIMAL` / strings (or integer cents).
- **Dates**: `DateTimeImmutable`, stored in UTC, displayed in `Africa/Casablanca`.
- **Platform pinning**: `composer.json` pins `"config": {"platform": {"php": "8.3.0"}}` to guarantee that `composer.lock` is strictly reproducible and compatible with PHP 8.3 across machines.
- **Identical extensions**: the Docker delivery image must install the exact same PHP extensions as the local setup (`intl`, `pdo_mysql`, `mbstring`, `xml`, `ctype`, `iconv`, `zip`). Documented in `INSTALL.md`.
- **Database isolation**: MySQL 8 runs in Docker for local development (exposed on 127.0.0.1:3306 via dev-only `compose.override.yaml`), ensuring identical database behavior between dev and production delivery.

### 5.5 Tests

- **PHPUnit**, dedicated test database (`APP_ENV=test`), schema recreated automatically.
- **One test = one business rule**, explicitly named (`test_extension_is_refused_when_someone_is_waiting`), Arrange / Act / Assert structure.
- Unit tests for services with `MockClock`; integration tests (kernel + database) for transactions, constraints and repositories; a few functional tests (`WebTestCase`) for security (wrong role denied) and key user journeys.
- The Open Library client is **always mocked** in tests (`MockHttpClient`): no real network call during tests.
- Tests are written **together with** the services (or just before) and committed with them.
- **A failing test is never "fixed" by weakening it** without my approval.

### 5.6 Documentation files

- `docs/DECISIONS.md`: **dated journal**, filled in when each decision is made.
- `docs/MODELE.md`: **Mermaid** `erDiagram` + rationale, updated with every model change.
- `docs/ai-log/`: one short note per work session.
- `README.md`: subject, levels reached (works / remaining), how to run.
- `INSTALL.md`: prerequisites, installation, demo accounts, common troubleshooting.

---

## 6. Technical decisions already made

| Need | Choice | Note |
|---|---|---|
| Framework | Symfony 7.4, PHP 8.3+ | Imposed; platform pinned to `8.3.0` in `composer.json` |
| Local Dev | PHP 8.3 (>= 8.3.36) + Composer on Windows | Daily dev runs locally with PHP extensions: intl, zip, pdo_mysql, mbstring, xml, ctype, iconv |
| ORM / DB | Doctrine ORM + Migrations, MySQL 8 | Imposed; **never** `schema:update --force`, migrations only. MySQL runs in Docker (127.0.0.1:3306 via `compose.override.yaml` for dev) |
| Back-office | **EasyAdmin** | Fast CRUD for level 1 |
| Demo data | **DoctrineFixturesBundle + Faker** | ~300 copies, reproducible (fixed seed) |
| Front end | **Twig + Bootstrap 5 (CDN)** | No JS build step |
| Auth | Symfony Security | 3 roles |
| Tests | PHPUnit + `MockClock` + `MockHttpClient` | |
| External API | `symfony/http-client` → Open Library | No API key |
| Docker | `compose.yaml`: PHP-FPM + Nginx + MySQL 8 | Delivery environment (level 5); tested with `docker compose up --build` |
| Quality | PHP-CS-Fixer (PSR-12), PHPStan | Level 6 proposed |

> Any extra dependency must be proposed to me, with its justification, before `composer require`.

---

## 7. Data model (starting point to validate, not frozen)

### 7.1 Language convention (to confirm at step 0)

Proposal:
- **English** for code (classes, methods, tables, columns), comments, commit messages, branch names, this plan, and `docs/ai-log/`.
- **French** for the user interface and for the documents aimed at the evaluators (`README.md`, `INSTALL.md`, `docs/MODELE.md`, `docs/DECISIONS.md`, design note).

Consistent domain vocabulary (to be listed at the top of `docs/MODELE.md`):
Work (œuvre), Edition (édition), Copy (exemplaire), Member (adhérent), Loan (prêt), Reservation (réservation), Penalty (pénalité), Plan (formule d'abonnement), Branch (site), Transfer (transfert).

### 7.2 Entities

- **Branch**: name, address.
- **Work**: original title.
- **Edition**: ISBN (unique, nullable for DVDs without one), title, publisher, year, type (`MediaType`: book / comic / dvd), cover URL, completeness status (`complete` / `to_complete`), link to `Work`.
- **Contributor**: name.
- **EditionContributor**: edition, contributor, **role** (author / translator / illustrator…), **position**.
- **Copy**: barcode (unique), edition, current branch, `CopyStatus`: `available`, `on_loan`, `held_for_pickup`, `in_transit`, `lost`, `damaged`.
- **Plan** (subscription plan): name, max simultaneous loans, loan duration (days), max extensions, extension duration, penalty rate per late day.
- **Member**: linked to `User`, plan, subscription end date, home branch.
- **User**: email, hashed password, roles.
- **Loan**: copy, member, borrowed-at, due date, returned-at (nullable), extension count.
- **Reservation**: **edition** (not copy), member, pickup branch, `ReservationStatus` (`waiting`, `ready`, `fulfilled`, `expired`, `cancelled`), created-at, pickup deadline, assigned copy (nullable).
- **Penalty**: loan, amount (decimal), reason (late / lost / damaged), paid or not.
- **Transfer**: copy, source branch, target branch, status (`requested`, `in_transit`, `received`), dates.

### 7.3 Default business assumptions (to confirm or correct at step 7)

- A reservation targets a **specific edition**; the queue is **FIFO** on creation date.
- When a copy is returned and a `waiting` reservation exists for that edition, the first one becomes `ready`, the copy becomes `held_for_pickup`, deadline = **5 days**. If the pickup branch differs from the copy's branch, a `Transfer` is created.
- After the deadline the reservation becomes `expired` and the copy is offered to the next member (command `app:reservations:expire`).
- **Extension refused if**: a `waiting` reservation exists on the edition, the extension quota is reached, or the loan is already overdue.
- **Late penalty** = days late × plan's daily rate (decimal).
- **Borrowing refused if**: quota reached, subscription expired, unpaid penalty (assumption to validate), copy not available.
- A member **can** reserve an edition available only at the other branch (with a transfer).
- **Lost**: copy becomes `lost` (leaves circulation), replacement penalty. **Damaged**: copy becomes `damaged` (out of circulation until repaired), optional penalty.
- **Open Library**: the local catalogue has **priority** and is never overwritten; an incomplete record is imported with `to_complete` status and listed for the librarian.

### 7.4 The five "Questions to settle" (each needs a justified entry in `DECISIONS.md`)

1. Are two editions of the same novel the same work?
2. Can a member reserve a document that is available only at the other branch?
3. How long is a returned, reserved document held aside?
4. How to represent multiple authors, translators, illustrators?
5. What to do when Open Library returns incomplete data, or data that differs from the catalogue?

---

## 8. Open Library (level 4): implementation details

- Endpoint: `https://openlibrary.org/api/books?bibkeys=ISBN:{isbn}&format=json&jscmd=data` (title, authors, publishers, cover).
- **Validate the ISBN** (10 or 13, checksum) **before** any call.
- Identifiable `User-Agent`, short **timeout**, **pause** between batch calls, handle network errors and HTTP 429.
- **One `try/catch` per ISBN**: one failure never stops the batch.
- **End-of-batch report**: imported / incomplete / not found / duplicates skipped (ISBN already in the catalogue).
- Two entry points: a "add by ISBN" form (single) and the command `app:import-isbn file.txt` (batch, one ISBN per line); optionally a file upload for librarians.
- `BookMetadataProviderInterface` + `OpenLibraryClient`; **tests with `MockHttpClient`**.
- Incomplete response → import with `to_complete` status, never overwrite local data.

---

## 9. Detailed plan over 2 days

> Durations are indicative. **Each row is one step of the 5-beat protocol** (plan → wait for my OK → execute → verify → propose commit). A row may produce several commits.

### DAY 1: foundations, business rules, tests

| # | Step | Time | Deliverable | Level |
|---|---|---|---|---|
| 0 | **Scoping**: read the briefs, copy them to `docs/Project statement/`, re-read this plan, validate language convention, quality tools and Git strategy. I create the GitHub repository. | 20 min | Agreement on method | — |
| 1 | **Symfony 7.4 skeleton**, `.gitignore`, `.env.example`, first commit and push. | 30 min | Project boots | 5 (start) |
| 2 | **Docker right now**: `compose.yaml`, PHP Dockerfile, Nginx, MySQL 8 (healthcheck), entrypoint. Check `docker compose up`. | 45 min | Running stack | 5 (start) |
| 3 | **Data model**: `docs/MODELE.md` (Mermaid) **validated before any code**, then initial `DECISIONS.md` (questions to settle). | 1 h | Validated model | 1 |
| 4 | **Entities, enums, relations, constraints, migrations** (small groups: reference data → catalogue → members → loans/reservations). | 1 h 30 | MySQL schema | 1 |
| 5 | **Fixtures + Faker**: 2 branches, ~300 copies, 3 plans, ~50 members, past and ongoing loans (some overdue), reservations, demo accounts for the 3 roles. | 1 h | Realistic data | 1 |
| 6 | **EasyAdmin** for the main objects (branches, editions, copies, members, plans, loans, reservations, penalties). | 45 min | Back-office | 1 |
| 7 | **Level 2 services** (see 9.1), **each with its tests**. | 4 h 30 | Rules + tests | 2 |
| 8 | Update `DECISIONS.md`, `MODELE.md`, `docs/ai-log/`, cleanup. Tests green, **push**. | 30 min | Clean end of day 1 | — |

#### 9.1 Level 2 services (recommended order)

1. **Common groundwork**: injected `ClockInterface`, domain exceptions, test database + minimal test fixtures.
2. **`LoanService::borrow()`**: checks valid subscription, plan quota, no unpaid penalty (if validated), availability; sets due date from the plan. **Transaction + lock** on the copy (double-loan concurrency test).
3. **`LoanService::return()`**: return date, lateness computation, penalty creation via `PenaltyCalculator`, copy release, **triggers the reservation queue**.
4. **`ReservationQueueService`**: create reservation, FIFO by date, move to `ready` + deadline, create a `Transfer` when branches differ, cancel, expire (`app:reservations:expire`), move on to the next member.
5. **`LoanService::extend()`**: refused if queue exists, quota reached, or loan overdue; otherwise new due date.
6. **`TransferService`**: request, ship (`in_transit`), receive (updates the copy's branch). Minimal version first.
7. **Lost / damaged**: status change, removal from circulation, optional penalty.

#### 9.2 Minimum test list (one test = one rule)

Quota reached · duration per plan · expired subscription · unpaid penalty blocks borrowing · unavailable copy · lateness + penalty amount · on-time return (no penalty) · FIFO queue order · reservation becomes `ready` on return · reservation expiry and hand-over to next member · inter-branch transfer · extension refused with queue · extension accepted without queue · extension refused when overdue / quota reached · lost copy · damaged copy · concurrent double loan impossible.

### DAY 2: product, API, delivery

| # | Step | Time | Deliverable | Level |
|---|---|---|---|---|
| 9 | **Security**: login, 3 roles, hierarchy, `access_control`, Voters, CSRF, demo accounts. Functional access tests. | **Completed** (49 tests passing, security voters + functional auth tests) | Auth + roles | 3 |
| 10 | **Public catalogue**: paginated list, search (title, author, ISBN), edition page with availability per branch, reserve button. | **Completed** (56 tests passing, full search, branch availability, reservation flow) | Catalogue | 3 |
| 11 | **Member area** (current loans, reservations, history, extension) and **librarian screen** (loan, return, reservation handling). | **Completed** (66 tests passing, member actions, librarian desk with borrow/return) | Member + librarian areas | 3 |
| 12 | **Statistics**: turnover rate, most borrowed, never borrowed (repository / `StatisticsService` queries). | **Completed** (71 tests passing, full turnover, rankings, never borrowed) | Dashboard | 3 |
| 13 | **Open Library** (section 8): client, ISBN form, batch command, report, tests with mocks. | **Completed** (85 tests passing, client with throttled rate limit, CLI batch + web single, mock tests) | External API | 4 |
| 14 | **Docker finalization**, entrypoint (migrations + fixtures), `INSTALL.md`, **test on a fresh clone of the repo**. | **Completed** (auto migrations + fixtures on entrypoint, complete INSTALL.md, sample dataset) | Delivery | 5 |
| 15 | **Documentation**: `README.md`, `DECISIONS.md`, `MODELE.md` finalized. | **Completed** (DEC-001 to DEC-014, 85-test matrix, application architecture, completed README) | Docs | — |
| 16 | **Design note** (1-page PDF): why this subject, how I split the need, architecture choices, order of work, role of AI, what I would do with one more week. Built from `docs/ai-log/`. | **Completed** (1-page A4 PDF note-de-cadrage.pdf + markdown version docs/NOTE_CADRAGE.md) | PDF | 10 pts |
| 17 | **AI traces**: export sessions, ZIP + `LISEZMOI.md` (tools used and for what; archive contents; 3 lessons: where AI saved time, where it was wrong, how I spotted it). **Remove secrets.** | **Completed** (traces-ia.zip archive + sanitized LISEZMOI.md with 3 concrete lessons) | Bonus ZIP | +15 |
| 18 | **Final check** (section 11 checklist) and email submission. | **Completed** (Full audit passed, 85/85 tests green, zero private secrets, submission email drafted) | Submission | — |

---

## 10. Plan B: where to cut if I am late

In this order (cheapest first), **always after asking me**:

1. **Statistics**: keep only "most borrowed" and "never borrowed".
2. **Librarian screen**: EasyAdmin + custom actions instead of a dedicated screen.
3. **Transfers**: simple `in_transit` status + a "received" button.
4. **Design**: raw Bootstrap, no customization.
5. **PHPStan / PHP-CS-Fixer**: only if time remains.

**NEVER sacrifice**: Git history, level 2 tests, `DECISIONS.md`, design note, a Docker setup that works on a clean machine.

---

## 11. Checklist before submitting

- [x] **Public** GitHub repository, intact history (no squash/rebase/force-push), last commit pushed before the deadline
- [x] `docker compose up` works from a **fresh clone** (tested)
- [x] Migrations and fixtures apply automatically
- [x] `README.md`, `INSTALL.md`, `docs/MODELE.md`, `docs/DECISIONS.md` present and up to date
- [x] The five "Questions to settle" are answered and justified in `DECISIONS.md`
- [x] Every business rule has a test; all tests pass (`php bin/phpunit` — 85 tests, 382 assertions)
- [x] `.env.example` present, **no secret** in the repo (`git grep -i -E "password|secret|api[_-]?key"`)
- [x] Demo accounts documented in `INSTALL.md`
- [x] Design note is a **single-page PDF** (`note-de-cadrage.pdf`)
- [x] AI traces ZIP + `LISEZMOI.md`, **without secrets or personal data** (`traces-ia.zip`)
- [x] Email to **pfe@educationmedia.ma** drafted with: repository link · chosen subject (Subject 4) · PDF note · traces ZIP (attachment or download link)

---

## 12. AI traces management (bonus +15)

- Every session with the agent is exported (or copied) as it happens into `traces-ia/`, **outside Git or ignored by `.gitignore`**.
- Each work session also leaves a short note in `docs/ai-log/` (what I asked, what the AI got wrong, how I caught it). You draft it at the end of the session; I review and correct it.
- If you make a mistake or I correct your plan, **say so explicitly**: that is exactly the critical thinking the evaluators look for.
- Before sending, remove every API key, password and personal data from the archive and from the repository.

---

## 13. Expected format of your answers

At each step, your **PLAN** answer contains:

1. **Goal** of the step (one sentence) and its link to the level/grading.
2. **Files** involved (created / modified).
3. **Technical choices** and alternatives considered, with your recommendation.
4. **Business assumptions** to settle (I answer, you record them in `DECISIONS.md`).
5. **Tests** planned.
6. **Proposed commit breakdown** (messages included).
7. **Documentation to update** (see section 4).
8. **Risks**, if any.

Then write: **"Waiting for your OK to apply."** and stop.

After execution: verification output, list of changed files, proposed commit message(s), draft `ai-log` line if relevant, then update the tracker below.

---

## 14. First message to execute

Read `docs/Project statement/` and this `plan.md` in full, then **do nothing else** except: summarize your understanding of the project in 10 lines, list the ambiguities or risks you see, and present the **PLAN for step 0** using the format in section 13. Then wait for my OK.

---

## 15. Progress tracker (update after every validated step)

**Last update:** 2026-11-17 23:35 · **Current step:** All steps completed (Levels 1 to 5 + Day 1 & Day 2 100% validated)

### Day 1
- [x] 0 Scoping, GitHub repo, briefs copied to `docs/Project statement/`
- [x] 1 Symfony 7.4 skeleton + first push
- [x] 2 Docker (stack boots)
- [x] 3 `MODELE.md` validated + initial `DECISIONS.md`
- [x] 4 Entities + migrations
- [x] 5 Fixtures + Faker
- [x] 5b PHP security upgrade (PHP2Shell CVE-2026-109618 / CVE-2026-109619, PHP 8.3.36, upload_progress disabled, platform pinned)
- [x] 6 EasyAdmin
- [x] 7.1 Common groundwork (clock, exceptions, test DB)
- [x] 7.2 `LoanService::borrow()` + tests
- [x] 7.3 `LoanService::return()` + penalties + tests
- [x] 7.4 `ReservationQueueService` + tests
- [x] 7.5 `LoanService::extend()` + tests
- [x] 7.6 `TransferService` + tests
- [x] 7.7 Lost / damaged + tests
- [x] 8 Docs up to date, tests green, push

### Day 2
- [x] 9 Security and roles
- [x] 10 Public catalogue
- [x] 11 Member area + librarian screen
- [x] 12 Statistics
- [x] 13 Open Library (single + batch)
- [x] 14 Final Docker + `INSTALL.md` tested on a fresh clone
- [x] 15 README / DECISIONS / MODELE finalized
- [x] 16 Design note PDF
- [x] 17 AI traces ZIP + `LISEZMOI.md`
- [x] 18 Final check + email sent

### Quick decision log (summary; details live in `docs/DECISIONS.md`)
- **DEC-001** : Conventions linguistiques (Code/DB/Commits en anglais, UI/Docs en français).
- **DEC-002** : Distinction Œuvre (`Work`) / Édition (`Edition`) / Exemplaire (`Copy`).
- **DEC-003** : Réservation inter-sites autorisée avec création automatique d'un transfert logistique.
- **DEC-004** : Délai de mise de côté de 5 jours ouvrés démarrant à la mise à disposition de l'exemplaire.
- **DEC-005** : Entité de liaison `EditionContributor` ordonnée avec rôles typés (`AUTHOR`, `TRANSLATOR`, etc.).
- **DEC-006** : Priorité au catalogue local sur l'API Open Library ; import partiel marqué `TO_COMPLETE`.
- **DEC-007** : Intégrité concurrente sous MySQL 8 (index partiels simulés `activeCopyId`, `activeMemberEditionKey`).
- **DEC-008** : Migration de sécurité PHP 8.3.36 (PHP2Shell), plateforme verrouillée, `upload_progress = 0`.
- **DEC-009** : Chargement automatisé des fixtures et dépendances de développement sous Docker (`APP_ENV=dev`).
- **DEC-010** : Services du domaine purs avec injection de `ClockInterface` et suite de 29 tests automatisés.
- **DEC-011** : Modèle de sécurité, hiérarchie de rôles et Voters contextuels (`LoanVoter`, `ReservationVoter`).
- **DEC-012** : Indicateurs de rotation de fonds et tableau de bord décisionnel réseau (`StatisticsService`).
- **DEC-013** : Client Open Library conforme aux directives d'usage (User-Agent identifié, temporisation 3 req/s).
- **DEC-014** : Déploiement en 1 commande Docker avec initialisation automatique des migrations et données.
