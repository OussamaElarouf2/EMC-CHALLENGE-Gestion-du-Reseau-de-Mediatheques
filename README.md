# Gestion d'un Réseau de Médiathèques

> **Challenge PFE — Education Media Company (EMC)**  
> **Sujet 4** : Système de gestion pour un réseau de deux médiathèques de quartier (~8 000 documents, ~1 500 usagers).

---

## 1. Stack Technique & Prérequis

- **Framework** : Symfony 7.4 LTS
- **Langage** : PHP 8.3 (version minimale requise : **PHP >= 8.3.36**)
- **Base de données** : MySQL 8 (InnoDB, transactions `REPEATABLE READ`, contraintes de concurrence strictes)
- **ORM & Migrations** : Doctrine ORM 3, Doctrine Migrations 3
- **Front-end** : Twig + Bootstrap 5 CDN (zéro pipeline Node.js / NPM)
- **Environnement de livraison** : Docker Compose (PHP 8.3.36-FPM Alpine + Nginx + MySQL 8)

### Sécurité & Durcissement (PHP2Shell)
Suite à la publication des vulnérabilités critiques **PHP2Shell** (CVE-2026-109618 et CVE-2026-109619, exécution de code à distance non authentifiée) et à la fin de vie imminente de PHP 8.2 (31/12/2026) :
- Le projet est strictement ciblé sur **PHP 8.3.36+**.
- La directive vulnérable `session.upload_progress.enabled` est **désactivée** (`Off` / `0`) en local comme dans l'image Docker (`docker/php/conf.d/security.ini`).
- L'image Docker de livraison est épinglée de façon immuable sur `php:8.3.36-fpm-alpine` (aucun tag flottant).
- La plateforme Composer est verrouillée sur `8.3.0` (`config.platform.php`).

Consultez l'ADR complet : [docs/DECISIONS.md](docs/DECISIONS.md) (DEC-008).

---

## 2. Démarrage Rapide

### Option A : Déploiement Docker (Recommandé pour l'évaluation)
En une seule commande à la racine du projet :
```bash
docker compose up --build
```
- **Application web** : [http://localhost:8080](http://localhost:8080)
- Les migrations et les données de test sont chargées automatiquement.

### Option B : Développement Local
Consultez le guide détaillé : [INSTALL.md](INSTALL.md).

---

## 3. Comptes de Démonstration

| Rôle | Email | Mot de passe | Description |
|---|---|---|---|
| **Administrateur** | `admin@mediatheque.local` | `Admin123!` | Accès complet back-office et configuration réseau |
| **Bibliothécaire** | `librarian@mediatheque.local` | `Librarian123!` | Gestion des prêts, retours, transferts et catalogue |
| **Adhérent** | `member@mediatheque.local` | `Member123!` | Adhérent standard avec 1 prêt en cours et 1 prêt en retard |
| **Adhérent échu** | `expired.member@mediatheque.local` | `Expired123!` | Adhérent avec abonnement expiré (test de blocage) |

---

## 4. Tableau d'Avancement des Niveaux (55 / 55 pts)

| Niveau | Intitulé | Statut | Points |
|---|---|---|---|
| **Niveau 1** | Modèle de données (13 entités), migrations, fixtures réalistes, back-office | **Validé** (13 entités, fixtures complètes, EasyAdmin 4) | 15 / 15 pts |
| **Niveau 2** | Règles métier avancées (quotas, files FIFO, transferts, retards) + Tests automatisés | **Validé** (29 tests unitaires, intégration et concurrence) | 15 / 15 pts |
| **Niveau 3** | Espace adhérent, authentification, catalogue public, statistiques | **Validé** (Auth 3 rôles, catalogue public, espace adhérent, guichet express, statistiques & rotation, 71 tests) | 10 / 10 pts |
| **Niveau 4** | Import enrichi Open Library (unitaire et par lot) | **Validé** (Client API avec rate limiting, import web unitaire, commande CLI par lot `app:import-isbn`, tests MockHttpClient, 85 tests) | 7 / 7 pts |
| **Niveau 5** | Environnement Docker clé en main, `INSTALL.md`, reproductibilité | **Validé** (Déploiement en 1 commande `docker compose up --build -d`, migrations & fixtures automatiques, `INSTALL.md`) | 8 / 8 pts |
| **Total Résultats** | **Tous les niveaux opérationnels et vérifiés** | **100% Validé** | **55 / 55 pts** |

---

## 5. Exécution de la Suite de Tests

Le projet dispose d'une couverture de tests automatisés exhaustive via **PHPUnit 12** :
```bash
php bin/phpunit
```
**Résultat : 85 tests, 382 assertions (100% passants)** sans dépendance externe ni appel réseau (mocks `MockHttpClient` et temps simulé `MockClock`).

---

## 6. Documentation de Référence

- [Note de Cadrage Conceptuelle (PDF)](note-de-cadrage.pdf) : Note de synthèse d'une page (A4) sur les choix d'architecture, les arbitrages de concurrence, le rôle de l'IA et les perspectives (10 pts). Également consultable en [Markdown](docs/NOTE_CADRAGE.md).
- [Guide d'Installation](INSTALL.md) : Prérequis, déploiement Docker clé en main en 1 commande et dépannage.
- [Modèle de Données](docs/MODELE.md) : Schéma entité-association Mermaid, dictionnaire de données, contraintes d'intégrité, verrous de concurrence et matrice complète des 85 tests.
- [Journal des Décisions d'Architecture](docs/DECISIONS.md) : Réponses argumentées aux 5 questions du sujet et arbitrages techniques formels (**DEC-001 à DEC-014**).
- [Traces d'Usage de l'IA & Rétrospective](docs/ai-log/LISEZMOI.md) : Guide explicatif des outils utilisés, structure des logs et 3 retours d'expérience concrets (+15 pts bonus). Archive complète : [traces-ia.zip](traces-ia.zip).
- [Plan de Développement](plan.md) : Cadre méthodologique, protocole en 5 temps et journal de suivi pas à pas.
