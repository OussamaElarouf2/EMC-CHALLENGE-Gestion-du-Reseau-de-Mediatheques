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

## 4. Tableau d'Avancement des Niveaux

| Niveau | Intitulé | Statut | Points |
|---|---|---|---|
| **Niveau 1** | Modèle de données (13 entités), migrations, fixtures réalistes, back-office | **Validé** (13 entités, fixtures, EasyAdmin) | 15 / 15 pts |
| **Niveau 2** | Règles métier avancées (quotas, files FIFO, transferts, retards) + Tests automatisés | **Validé** (29 tests unitaires, intégration et concurrence) | 15 / 15 pts |
| **Niveau 3** | Espace adhérent, authentification, catalogue public, statistiques | Prévu (Étapes 9 à 12) | 10 pts |
| **Niveau 4** | Import enrichi Open Library (unitaire et par lot) | Prévu (Étape 13) | 7 pts |
| **Niveau 5** | Environnement Docker clé en main, `INSTALL.md`, reproductibilité | En cours (Socle Docker actif) | 8 pts |

---

## 5. Documentation de Référence

- [Guide d'Installation](INSTALL.md) : Prérequis, déploiement Docker et dépannage.
- [Modèle de Données](docs/MODELE.md) : Diagramme entité-association Mermaid, dictionnaire de données, contraintes d'intégrité et de concurrence.
- [Journal des Décisions d'Architecture](docs/DECISIONS.md) : Réponses argumentées aux 5 questions du sujet et arbitrages techniques (DEC-001 à DEC-009).
- [Plan de Développement](plan.md) : Cadre méthodologique, protocole en 5 temps et suivi pas à pas.
