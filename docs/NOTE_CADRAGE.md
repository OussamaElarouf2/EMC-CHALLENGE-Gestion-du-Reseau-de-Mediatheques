# Note de Cadrage — Système de Gestion de Médiathèques
**Challenge PFE 2026 — Education Media Company (EMC)**  
**Candidat** : Oussama El Arouf | **Date** : Novembre 2026 | **Sujet 4** : Gestion d'un réseau de deux médiathèques

---

### 1. Choix du Sujet & Motivation
Parmi les sujets proposés, le **Sujet 4 (Réseau de médiathèques)** a été retenu pour son exigence technique et sa richesse fonctionnelle. Contrairement à un catalogue classique ou un CRUD élémentaire, la gestion d'un réseau multi-sites soulève des défis d'ingénierie logicielle concrets :
- La modélisation bibliographique fine conforme aux standards documentaires (FRBR).
- La gestion logistique inter-sites et la circulation physique des exemplaires.
- L'intégrité face aux accès concurrents (quotas d'emprunts simultanés, files d'attente FIFO).
- L'interopérabilité avec un service externe public (Open Library) soumis à des contraintes de débit.

### 2. Découpage du Besoin & Méthodologie de Réalisation
Le projet a été découpé selon les 5 niveaux progressifs de l'énoncé, gouvernés par un protocole rigoureux en 5 temps (*Planifier $\rightarrow$ Valider $\rightarrow$ Exécuter $\rightarrow$ Vérifier $\rightarrow$ Commiter*) :
1. **Niveau 1 (Fondations)** : Schéma relationnel complet (13 entités), migrations Doctrine, fixtures volumétriques réalistes (~300 exemplaires) et back-office d'administration EasyAdmin 4.
2. **Niveau 2 (Cœur Métier & Concurrence)** : Services de domaine purs (`LoanService`, `ReservationQueueService`, `TransferService`, `PenaltyCalculator`), verrous pessimistes et contraintes MySQL pour l'isolation transactionnelle, accompagnés de 29 tests automatisés.
3. **Niveau 3 (Expérience Utilisateur & Métriques)** : Authentification à 3 rôles hiérarchiques, catalogue public réactif, espace usager avec prolongations/annulations sécurisées par Voters Symfony, guichet express bibliothécaire et tableau de bord décisionnel (taux de rotation).
4. **Niveau 4 (Interopérabilité Externe)** : Intégration de l'API Open Library respectant les directives d'usage officielles (identification `User-Agent`, temporisation 3 req/s, préservation des données locales).
5. **Niveau 5 (Livraison Clé en Main)** : Conteneurisation Docker multi-services exécutable en une commande unique (`docker compose up --build -d`) avec amorçage automatique.

### 3. Choix d'Architecture Majeurs & Compromis Réalisés
- **Modélisation FRBR (`Work` $\rightarrow$ `Edition` $\rightarrow$ `Copy`)** : Une œuvre intellectuelle abstraite regroupe plusieurs éditions matérielles (formats `BOOK`, `COMIC`, `DVD`), chacune déclinée en exemplaires physiques identifiés par code-barres. Les contributeurs multiples sont qualifiés par rôle et rang d'affichage (`EditionContributor`).
- **Concurrence sous MySQL 8 (`REPEATABLE READ`)** : Pour pallier l'absence d'index uniques partiels sous MySQL, une double barrière a été conçue :
  - *Barrière applicative* : Verrouillage pessimiste ordonné déterministe (Member d'abord, Copy ensuite) pour éliminer tout interblocage (*deadlock*).
  - *Barrière physique SGBD* : Colonnes d'unicité nullables (`activeCopyId` et `activeMemberEditionKey`). InnoDB autorisant une infinité de valeurs `NULL`, les emprunts et réservations terminés coexistent sans conflit, tandis que toute tentative d'écriture concurrente illégitime est rejetée par le moteur.
- **Logique de Réservation Inter-sites & Statut Pivot `AWAITING_TRANSFER`** : Lorsqu'un usager réserve un document disponible sur l'autre site, la réservation prend immédiatement le statut `AWAITING_TRANSFER` et l'exemplaire est marqué `IN_TRANSIT`. Elle quitte ainsi la condition `WAITING` de la file FIFO, interdisant mathématiquement toute double allocation d'exemplaire lors d'un retour simultané. Le délai de retrait de 5 jours calendaires ne s'enclenche qu'à la réception physique sur le site de retrait (`READY`).
- **Durcissement de Sécurité PHP 8.3 (PHP2Shell)** : En réponse aux vulnérabilités critiques CVE-2026-109618/CVE-2026-109619 et à la fin de vie imminente de PHP 8.2, le runtime a été migré sur PHP 8.3.36 avec désactivation stricte de `session.upload_progress.enabled`.

### 4. Rôle et Supervision des Outils d'Intelligence Artificielle
Les outils d'IA générative ont été mobilisés comme un accélérateur d'ingénierie sous contrôle humain permanent :
- **Apports à forte valeur ajoutée** : Génération rapide des structures d'entités, mise en place des gabarits Twig / Bootstrap 5, élaboration de jeux de données réalistes via Faker, et rédaction des cas de tests nominaux et aux limites.
- **Supervision et arbitrage humain** : L'IA a parfois proposé des choix inadaptés ou commis des erreurs (ex: logique de réservation risquant d'allouer deux fois un livre en transit, décompte prématuré du délai de retrait de 5 jours avant l'arrivée physique du document, ou inversions de paramètres dans le code). La vigilance du développeur et le filet de sécurité de **85 tests automatisés (382 assertions passantes à 100%)** ont permis d'isoler, corriger et valider chaque règle métier.

### 5. Perspectives d'Évolution (Avec une Semaine Supplémentaire)
Avec un délai additionnel de 7 jours, les extensions suivantes seraient implémentées :
1. **Système de Notification Omnicanal (Email & SMS)** : Intégration de `symfony/mailer` et de webhooks SMS pour notifier immédiatement l'adhérent de l'arrivée de son document réservé et lui envoyer un rappel à J-1 avant l'expiration du délai de 5 jours.
2. **API pour Bornes Automatiques & Kiosques RFID** : Exposition d'une API RESTful (API Platform / OAuth2) permettant aux automates de prêt/retour en libre-service d'interagir directement avec `LoanService`.
3. **Gestion Logistique Avancée des Navettes** : Planification de tournées de navettes bihebdomadaires entre les deux sites, avec édition de bordereaux de colisage et signature électronique lors de la prise en charge par le coursier.
