# Guide d'Installation et de Déploiement — Réseau de Médiathèques

Ce guide présente la procédure clé en main pour installer, démarrer et évaluer l'application, via l'environnement Docker automatisé (exigence Niveau 5) ou en environnement local de développement.

---

## 1. Déploiement en Une Commande via Docker (Livrable Niveau 5)

L'environnement Docker est conçu pour démarrer l'application immédiatement sur une machine propre sans aucune configuration manuelle de PHP ou MySQL.

### Prérequis
- [Docker](https://docs.docker.com/get-docker/) et Docker Compose (version v2+).

### Lancement clé en main
À la racine du projet, lancez :

```bash
docker compose up --build -d
```

### Ce que fait cette commande automatiquement :
1. **Construction de l'image PHP 8.3-FPM** (`php:8.3.36-fpm-alpine`), durcie sans `session.upload_progress` (protection contre la vulnérabilité PHP2Shell CVE-2026-109618 / CVE-2026-109619).
2. **Installation des extensions PHP requises** : `intl`, `pdo_mysql`, `mbstring`, `xml`, `ctype`, `iconv`, `zip`, `opcache`.
3. **Démarrage de MySQL 8** avec healthcheck automatique.
4. **Démarrage du serveur web Nginx** sur le port `8080`.
5. **Script d'Entrypoint automatisé (`docker-entrypoint.sh`)** :
   - Attend la disponibilité effective du serveur de base de données.
   - Exécute automatiquement les **migrations Doctrine** (`doctrine:migrations:migrate`).
   - Détecte si la base est vierge et charge automatiquement le jeu complet de **fixtures réalistes** (2 médiathèques, 3 abonnements, 52 comptes utilisateurs, ~300 exemplaires, prêts, réservations, transferts).
   - Préchauffe le cache applicatif (`cache:warmup`).

### Accès direct aux interfaces
Une fois les conteneurs démarrés, toutes les fonctionnalités sont immédiatement opérationnelles :

| Service | URL | Profil recommandé |
|---|---|---|
| **Catalogue public** | [http://localhost:8080/catalogue](http://localhost:8080/catalogue) | Accès libre (ou connecté pour réserver) |
| **Espace Adhérent** | [http://localhost:8080/espace-adherent](http://localhost:8080/espace-adherent) | `member@mediatheque.local` / `Member123!` |
| **Guichet de circulation** | [http://localhost:8080/admin/guichet](http://localhost:8080/admin/guichet) | `librarian@mediatheque.local` / `Librarian123!` |
| **Statistiques & Rotation** | [http://localhost:8080/admin/statistiques](http://localhost:8080/admin/statistiques) | `librarian@mediatheque.local` / `Librarian123!` |
| **Back-Office EasyAdmin** | [http://localhost:8080/admin](http://localhost:8080/admin) | `admin@mediatheque.local` / `Admin123!` |
| **Import unitaire Open Library** | [http://localhost:8080/admin/import-isbn](http://localhost:8080/admin/import-isbn) | `librarian@mediatheque.local` / `Librarian123!` |

Pour éteindre les conteneurs :
```bash
docker compose down
```

---

## 2. Comptes de Démonstration (Fixtures)

Quatre comptes de démonstration prédéfinis permettent d'évaluer l'ensemble des règles métier et des niveaux d'habilitation :

| Rôle | Email | Mot de passe | Description & Usages d'évaluation |
|---|---|---|---|
| **Administrateur** | `admin@mediatheque.local` | `Admin123!` | Accès complet au back-office, gestion des sites, plans et utilisateurs |
| **Bibliothécaire** | `librarian@mediatheque.local` | `Librarian123!` | Accès au guichet express de prêt/retour, import Open Library, statistiques et catalogue |
| **Adhérent actif** | `member@mediatheque.local` | `Member123!` | Consultation du compte, suivi et prolongation des prêts en cours, réservations |
| **Adhérent expiré** | `expired.member@mediatheque.local` | `Expired123!` | Test de blocage des emprunts et réservations pour cotisation expirée |

---

## 3. Test de la Commande par Lot Open Library (CLI)

Pour tester l'import en lot de notices bibliographiques depuis l'API publique Open Library :

```bash
# Dans le conteneur Docker :
docker compose exec app php bin/console app:import-isbn data/isbns_sample.txt

# Ou en local :
php bin/console app:import-isbn data/isbns_sample.txt
```

La commande applique la régulation de débit (rate limiting à 3 req/sec), respecte la priorité du catalogue local (DEC-005 : pas d'écrasement des doublons), et affiche un rapport récapitulatif formaté.

---

## 4. Exécution de la Suite de Tests Automatisés

L'application intègre une suite complète de **85 tests automatisés (382 assertions)** couvrant l'intégrité du modèle, les règles métier (Niveau 2), la sécurité/voters (Niveau 3) et l'API externe (Niveau 4) :

```bash
# Exécution dans le conteneur Docker :
docker compose exec app php bin/phpunit

# Ou en local :
php bin/phpunit
```

---

## 5. Développement Local (Optionnel)

Si vous souhaitez exécuter le serveur PHP directement sur votre machine hôte (sans passer par les conteneurs PHP/Nginx) :

### Prérequis
- **PHP >= 8.3.36** avec `session.upload_progress.enabled = Off` dans `php.ini`.
- Extensions : `intl`, `pdo_mysql`, `mbstring`, `xml`, `ctype`, `iconv`, `zip`, `curl`, `openssl`.
- Composer 2+.

### Démarrage de MySQL uniquement
```bash
# Démarre le conteneur MySQL dédié
docker compose up -d db

# Installation des dépendances et base locale
composer install
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction

# Lancement du serveur local
symfony server:start --port=8080
# ou
php -S 127.0.0.1:8080 -t public
```

---

## 6. Dépannage & Commandes Utiles

- **Vérifier l'état des conteneurs** :
  ```bash
  docker compose ps
  ```
- **Consulter les journaux d'exécution en direct** :
  ```bash
  docker compose logs -f app
  docker compose logs -f web
  docker compose logs -f db
  ```
- **Réinitialiser complètement la base de données** :
  ```bash
  docker compose exec app php bin/console doctrine:fixtures:load --no-interaction
  ```
