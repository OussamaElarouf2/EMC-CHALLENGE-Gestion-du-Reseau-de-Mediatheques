# Implementation Plan - Step 2: Docker Environment & Local MySQL Setup

Configure the Docker container stack for Level 5 delivery (PHP 8.2-FPM, Nginx, MySQL 8) and a dev-only override for local development.

## User Review Required

> [!IMPORTANT]
> - **Port 3306 on host**: For local development, Docker MySQL will bind to `127.0.0.1:3306`. If you have MySQL running in XAMPP on your Windows host, please stop XAMPP MySQL before starting the Docker MySQL container to prevent port conflicts.
> - **HTTP Port**: We recommend binding Nginx to `8080:80` (accessible at `http://localhost:8080`) to avoid conflicts with Windows services (like IIS or Apache) on port 80.
> - **`compose.override.yaml` Policy**: To keep the delivery `compose.yaml` secure and clean for evaluators, `compose.override.yaml` (which exposes port 3306 to `127.0.0.1`) will be ignored in `.gitignore`, while `compose.override.yaml.dist` is tracked in Git.

## Proposed Changes

### Docker Configuration & Infrastructure

#### [NEW] compose.yaml
- Delivery stack definition:
  - `app`: PHP 8.2-FPM Alpine container, volume mounts, internal network.
  - `web`: Nginx Alpine reverse proxy on port 8080 (or 80).
  - `db`: MySQL 8.0 with healthcheck, internal network, persistent data volume.

#### [NEW] compose.override.yaml & compose.override.yaml.dist
- Dev-only override exposing `127.0.0.1:3306:3306` on `db` so your local PHP 8.2 can connect directly to Docker MySQL.

#### [NEW] docker/php/Dockerfile
- Multi-stage Alpine PHP 8.2-FPM build installing:
  - `intl`, `pdo_mysql`, `mbstring`, `xml`, `ctype`, `iconv`, `zip`, `opcache`.
  - Composer 2.
  - Execution non-root user / standard permissions.

#### [NEW] docker/php/docker-entrypoint.sh
- Enforces LF endings via `.gitattributes`.
- Waits for MySQL database readiness via healthcheck before booting.
- Prepared for running `doctrine:migrations:migrate` and `doctrine:fixtures:load` in subsequent steps.

#### [NEW] docker/nginx/default.conf
- Nginx server block routing requests to `app:9000` via Symfony front-controller pattern (`try_files $uri /index.php$is_args$args;`).

#### [NEW] INSTALL.md
- Initial guide explaining:
  1. Prerequisites (PHP 8.2, Composer, Docker).
  2. Local development workflow (running `docker compose up -d db`).
  3. Delivery execution (`docker compose up --build`).
  4. Default demo credentials.

#### [MODIFY] .gitignore
- Add `compose.override.yaml` (keeping `compose.override.yaml.dist` tracked).

---

## Verification Plan

### Automated Tests
- `docker compose up -d db` (with override) to confirm MySQL 8 boots and binds to `127.0.0.1:3306`.
- `docker compose up -d --build` to confirm full delivery stack builds and runs cleanly.
- `curl -I http://localhost:8080` to verify Nginx -> PHP-FPM communication.
- `docker compose down` to verify clean teardown.

### Manual Verification
- Check that port 3306 is not exposed in the delivery `compose.yaml`.
- Inspect `INSTALL.md` for clarity and completeness.
