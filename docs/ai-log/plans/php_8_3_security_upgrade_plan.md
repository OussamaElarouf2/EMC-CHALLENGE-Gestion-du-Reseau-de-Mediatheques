# Plan d'Implémentation — Migration de Sécurité PHP 8.3 (PHP2Shell & Durcissement)

## Description de l'Objectif
La découverte récente de la vulnérabilité critique **PHP2Shell** (CVE-2026-109618 et CVE-2026-109619, RCE non authentifiée exploitant `session.upload_progress`) affecte l'ensemble des versions PHP inférieures à 8.2.35 / 8.3.36 / 8.4.27. De plus, la branche PHP 8.2 atteint sa fin de vie officielle (EOL) le 31 décembre 2026.

L'objectif de cette intervention est de migrer le projet vers **PHP 8.3** (avec un patch minimal et réel **PHP 8.3.36**), de durcir la configuration en désactivant `upload_progress`, d'épingler l'image Docker de livraison sur une étiquette exacte et vérifiée (`php:8.3.36-fpm-alpine`), d'actualiser la configuration Composer (`require.php: >=8.3`, `platform.php: 8.3.0`), et de consigner formellement ces arbitrages dans le journal d'architecture (`DECISIONS.md`), le guide d'installation (`INSTALL.md`) et le journal d'IA (`ai-log`).

Cette intervention est exécutée sur la branche Git isolée `updated-php-version` créée depuis `main`.

---

## Revue Utilisateur Requise
> [!IMPORTANT]
> **Diagnostic de la machine hôte : VALIDÉ**
> Vous avez mis à niveau PHP avec succès sur votre machine hôte vers **PHP 8.3.36** (ZTS Visual C++ 2019 x64).
> - `php -v` : `PHP 8.3.36` (conforme >= 8.3.36).
> - `session.upload_progress.enabled` : `Off` (correctif actif).
> - Toutes les extensions Symfony indispensables (`intl`, `pdo_mysql`, `mbstring`, `xml`, `zip`, etc.) sont activées et opérationnelles.

> [!WARNING]
> **Épinglage strict Docker (zéro tag flottant)**
> L'image de base Docker sera explicitement épinglée sur l'image multi-plateforme officielle vérifiée sur Docker Hub : `php:8.3.36-fpm-alpine`. Les tags flottants `php:8.3-fpm` ou `latest` sont formellement exclus pour garantir la reproductibilité absolue de l'évaluation PFE.

---

## Questions Ouvertes
Aucune question bloquante : la version hôte locale (8.3.36) et la version Docker cible (8.3.36) sont parfaitement alignées.

---

## Modifications Proposées

### 1. Configuration des dépendances (Composer)

#### [MODIFY] [composer.json](file:///C:/Users/EL%20AROUF%20OUSSAMA/Desktop/DLA%203/PFE%202027/A%20Rendre%20urgent/projet-4-mediatheque/composer.json)
- Passer `"php": ">=8.2"` à `"php": ">=8.3"` dans `require`.
- Passer `"php": "8.2.0"` à `"php": "8.3.0"` dans `config.platform`.
- Ajouter `"symfony/polyfill-php83": "*"` dans `replace` pour éviter le téléchargement inutile d'un polyfill désormais natif.

```diff
--- a/composer.json
+++ b/composer.json
@@ -4,7 +4,7 @@
     "minimum-stability": "stable",
     "prefer-stable": true,
     "require": {
-        "php": ">=8.2",
+        "php": ">=8.3",
         "ext-ctype": "*",
         "ext-iconv": "*",
         "doctrine/doctrine-bundle": "^2.19",
@@ -31,7 +31,7 @@
         "allow-plugins": { ... },
         "bump-after-update": true,
         "platform": {
-            "php": "8.2.0"
+            "php": "8.3.0"
         },
         "sort-packages": true
     },
@@ -53,7 +53,8 @@
         "symfony/polyfill-php74": "*",
         "symfony/polyfill-php80": "*",
         "symfony/polyfill-php81": "*",
-        "symfony/polyfill-php82": "*"
+        "symfony/polyfill-php82": "*",
+        "symfony/polyfill-php83": "*"
     },
```

#### [MODIFY] [composer.lock](file:///C:/Users/EL%20AROUF%20OUSSAMA/Desktop/DLA%203/PFE%202027/A%20Rendre urgent/projet-4-mediatheque/composer.lock)
- Mise à jour minimale de la section `platform` (8.3.0) et du hash de contenu sans mise à niveau des paquets non liés.

---

### 2. Environnement Conteneurisé Docker

#### [NEW] [docker/php/conf.d/security.ini](file:///C:/Users/EL%20AROUF%20OUSSAMA/Desktop/DLA%203/PFE%202027/A%20Rendre%20urgent/projet-4-mediatheque/docker/php/conf.d/security.ini)
Fichier ini versionné désactivant l'upload progress pour neutraliser PHP2Shell en conteneur :
```ini
; Security hardening against PHP2Shell (CVE-2026-109618 / CVE-2026-109619)
session.upload_progress.enabled = 0
```

#### [MODIFY] [docker/php/Dockerfile](file:///C:/Users/EL%20AROUF%20OUSSAMA/Desktop/DLA%203/PFE%202027/A%20Rendre%20urgent/projet-4-mediatheque/docker/php/Dockerfile)
- Épingler sur `php:8.3.36-fpm-alpine`.
- Copier `docker/php/conf.d/security.ini` dans `/usr/local/etc/php/conf.d/security.ini`.
- Assurer le chargement des fixtures sous Docker en mode dev (`composer install` complet).

```diff
--- a/docker/php/Dockerfile
+++ b/docker/php/Dockerfile
@@ -1,4 +1,4 @@
-FROM php:8.2-fpm-alpine
+FROM php:8.3.36-fpm-alpine
 
 # Install php extension installer
 COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
@@ -19,6 +19,9 @@ COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
 # Install utilities
 RUN apk add --no-cache bash netcat-openbsd
 
+# Security hardening: disable upload progress (PHP2Shell fix)
+COPY docker/php/conf.d/security.ini /usr/local/etc/php/conf.d/security.ini
+
 WORKDIR /var/www/html
 
 # Copy app files
```

---

### 3. Documentation & Traçabilité (en Français)

#### [MODIFY] [docs/DECISIONS.md](file:///C:/Users/EL%20AROUF%20OUSSAMA/Desktop/DLA%203/PFE%202027/A%20Rendre%20urgent/projet-4-mediatheque/docs/DECISIONS.md)
- Ajouter l'entrée datée **DEC-008 : Migration de sécurité vers PHP 8.3 (PHP2Shell) et durcissement**.
- Détailler le contexte (PHP2Shell, CVE-2026-109618/109619, EOL PHP 8.2 fin 2026), les options examinées (rester sur 8.2.35, migrer vers 8.3, migrer vers 8.4), le choix de PHP 8.3.36 et les conséquences (hôte, Docker, INI, platform pinning).
- Re-numéroter l'ADR sur les dépendances Docker en **DEC-009**.

#### [MODIFY] [INSTALL.md](file:///C:/Users/EL%20AROUF%20OUSSAMA/Desktop/DLA%203/PFE%202027/A%20Rendre%20urgent/projet-4-mediatheque/INSTALL.md)
- Mettre à jour les prérequis hôte : `PHP >= 8.3.36` avec `session.upload_progress.enabled = Off`.
- Mettre à jour la description de l'image Docker PHP 8.3-FPM.

#### [MODIFY] [plan.md](file:///C:/Users/EL%20AROUF%20OUSSAMA/Desktop/DLA%203/PFE%202027/A%20Rendre%20urgent/projet-4-mediatheque/plan.md)
- Remplacer toutes les occurrences de PHP 8.2 par PHP 8.3 dans les sections 1.1, 5.4 et 6.
- Ajouter la ligne de suivi `5b PHP security upgrade (PHP2Shell, PHP 8.3.36)` dans le tracker.

#### [NEW] [docs/ai-log/2026-11-17-session-1.md](file:///C:/Users/EL%20AROUF%20OUSSAMA/Desktop/DLA%203/PFE%202027/A%20Rendre%20urgent/projet-4-mediatheque/docs/ai-log/2026-11-17-session-1.md)
- Rédiger la note de session du jour (ce qui a été demandé, les anomalies détectées et corrigées, la veille de sécurité active).

---

## Plan de Vérification

### Tests Automatisés et Commandes de Vérification

#### 1. Validation Composer sur l'hôte
```powershell
$env:Path = [System.Environment]::GetEnvironmentVariable("Path","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path","User")
composer validate
composer audit
```

#### 2. Vérification de l'application Symfony sur l'hôte
```powershell
php bin/console about
php bin/console doctrine:schema:validate
php bin/console doctrine:fixtures:load --no-interaction
php bin/console doctrine:fixtures:load --no-interaction
```

#### 3. Construction et Vérification Conteneurisée Docker
```powershell
docker compose build --no-cache --pull php
docker compose run --rm php php -v
docker compose run --rm php php -m
docker compose run --rm php php -i | grep upload_progress
docker compose run --rm php php bin/console doctrine:schema:validate
```

#### 4. Audit "Leave nothing behind"
```powershell
git grep -n -i -E "8\.2|php ?8|php:8|php-8" -- ":!vendor" ":!docs/Project statement"
```
Vérifier que chaque occurrence restante est justifiée ou mise à jour.

---

## Découpage Proposé des Commits
1. **Commit 1 (`fix`)** : `security: upgrade target to PHP 8.3.36, pin platform and harden upload_progress`
   - `composer.json`, `composer.lock`, `docker/php/Dockerfile`, `docker/php/conf.d/security.ini`
2. **Commit 2 (`docs`)** : `docs: record DEC-008 on PHP2Shell mitigation and update references to PHP 8.3`
   - `docs/DECISIONS.md`, `INSTALL.md`, `plan.md`, `docs/ai-log/2026-11-17-session-1.md`
