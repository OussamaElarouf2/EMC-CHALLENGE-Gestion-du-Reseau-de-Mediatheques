# Guide d'Installation et de Déploiement — Médiathèques

Ce guide détaille les prérequis et les étapes pour installer, lancer et tester l'application, que ce soit via l'environnement conteneurisé Docker (livrable Niveau 5) ou en développement local.

---

## 1. Déploiement via Docker (Environnement de Référence & Évaluation)

L'environnement Docker est conçu pour être déployé en **une seule commande** sur une machine propre sans nécessiter l'installation de PHP ou MySQL sur l'hôte.

### Prérequis
- [Docker](https://docs.docker.com/get-docker/) et Docker Compose (version v2+).

### Lancement de l'application
À la racine du projet, lancez :

```bash
docker compose up --build
```

Cette commande :
1. Construit l'image PHP 8.2-FPM avec toutes les extensions nécessaires (`intl`, `pdo_mysql`, `mbstring`, `xml`, `ctype`, `iconv`, `zip`, `opcache`).
2. Démarre le serveur web Nginx sur le port `8080`.
3. Démarre le serveur de base de données MySQL 8 (avec healthcheck).
4. Exécute le script d'entrée (`docker-entrypoint.sh`) qui attend la disponibilité effective de MySQL avant de chauffer le cache (et appliquera automatiquement les migrations et les données de démonstration dans les niveaux suivants).

### Accès
- **Application Web** : [http://localhost:8080](http://localhost:8080)
- Pour arrêter les conteneurs : `docker compose down`

---

## 2. Développement Local (Hybride)

Pour le travail quotidien, l'application Symfony et les tests s'exécutent directement sur l'hôte avec PHP 8.2 et Composer, tandis que MySQL tourne dans un conteneur isolé.

### Prérequis locaux
- PHP 8.2+ avec les extensions actives : `intl`, `pdo_mysql`, `mbstring`, `xml`, `ctype`, `iconv`, `zip`.
- Composer 2+.

### Démarrage de la base de données
1. Si ce n'est pas déjà fait, copiez le template d'override :
   ```bash
   cp compose.override.yaml.dist compose.override.yaml
   ```
   *(Ce fichier lie le port MySQL 3306 sur `127.0.0.1` pour votre machine hôte).*
2. Démarrez le service de base de données :
   ```bash
   docker compose up -d db
   ```
3. L'application locale communique avec MySQL sur `127.0.0.1:3306` avec les identifiants configurés dans `.env`.

---

## 3. Identifiants de Démonstration (Développement / Évaluation)

> [!NOTE]
> Ces identifiants sont strictement réservés aux environnements de démonstration et de développement.

- **Base de données MySQL** :
  - Hôte Docker interne : `db:3306`
  - Hôte local (via override) : `127.0.0.1:3306`
  - Nom de la base : `mediatheque`
  - Utilisateur : `app_user`
  - Mot de passe : `app_password`
  - Utilisateur root : `root` / `root_password`

---

## 4. Dépannage Courant

- **Conflit sur le port 3306** : Si MySQL ou un serveur XAMPP/WAMP est déjà actif sur votre machine hôte, arrêtez-le avant de démarrer `docker compose up -d db`.
- **Vérification de l'état des conteneurs** :
  ```bash
  docker compose ps
  ```
- **Consultation des journaux d'exécution** :
  ```bash
  docker compose logs -f
  ```
