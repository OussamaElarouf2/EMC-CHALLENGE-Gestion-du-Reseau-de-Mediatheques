# Journal des Décisions d'Architecture (ADR)

Ce document consigne de manière datée l'ensemble des décisions d'architecture, des arbitrages techniques et des réponses argumentées aux « Questions à trancher » du sujet.

---

## [2026-11-16] DEC-001 : Conventions linguistiques et vocabulaire du domaine

### Contexte
Le projet est développé dans le cadre du Challenge PFE EMC évalué par un jury francophone. Il est nécessaire d'établir dès le départ des conventions claires pour la langue du code, de la base de données, des interfaces et des documents de restitution, ainsi qu'un lexique commun.

### Options considérées
1. **Tout en français** : Code, base de données et documentation en français. (Inconvénient : syntaxe peu naturelle avec les composants Symfony et les standards PSR).
2. **Tout en anglais** : Code, base de données, documentation et UI en anglais. (Inconvénient : décalage avec le jury et l'énoncé exprimé en français).
3. **Hybride (Code en anglais / Documentation et UI en français)** : Respecte les conventions industrielles pour le code et offre un confort de lecture optimal pour les évaluateurs.

### Choix retenu
Option 3 :
- **Anglais** pour le code source (classes, méthodes, attributs), la base de données (tables, colonnes, migrations), les tests automatisés et les messages de commits.
- **Français** pour l'interface utilisateur (Twig), le back-office, et les documents de cadrage (`README.md`, `INSTALL.md`, `docs/MODELE.md`, `docs/DECISIONS.md`, note conceptuelle).
- Vocabulaire métier : *Work, Edition, Copy, Member, Plan, Loan, Reservation, Penalty, Branch, Transfer*.

### Conséquences
- Code standardisé conforme aux recommandations Symfony.
- Documentation d'évaluation limpide et immédiatement compréhensible.

---

## [2026-11-17] DEC-002 : Distinction conceptuelle entre Œuvre et Édition (Question 1)

### Contexte
L'énoncé pose la question : *« Deux éditions d'un même roman sont-elles la même œuvre ? »*. Il s'agit de déterminer le niveau de granularité pour le catalogue, l'inventaire physique et les réservations.

### Options considérées
1. **Modèle plat monocouche (Livre unique)** : Fusionner œuvre et édition dans une même entité `Book`. Chaque réédition est un livre distinct. (Inconvénient : duplication massive des titres originaux, impossibilité de relier les traductions ou rééditions).
2. **Modèle hiérarchique FRBR à deux niveaux (`Work` → `Edition`)** : Une entité abstraite `Work` (l'œuvre intellectuelle) décline une ou plusieurs entités concrètes `Edition` (publications avec ISBN, éditeur, année, format physique).
3. **Modèle complet FRBR à quatre niveaux (Work, Expression, Manifestation, Item)** : Norme bibliothécaire internationale complète. (Inconvénient : complexité excessive et sur-ingénierie par rapport au budget temps de 2 jours).

### Choix retenu
Option 2 :
- **Oui, deux éditions d'un même roman constituent la même œuvre.**
- L'entité `Work` porte le titre original de création.
- Chaque `Edition` porte l'ISBN propre, le format (`BOOK`, `COMIC`, `DVD`), l'éditeur, l'année et la jaquette.
- Les exemplaires physiques (`Copy`) sont rattachés directement à l'entité `Edition`.

### Conséquences
- Évite toute duplication d'information bibliographique générale.
- Prépare le catalogue public à présenter une œuvre avec le choix de ses éditions/formats disponibles.

---

## [2026-11-17] DEC-003 : Réservation inter-sites et transferts logistiques (Question 2)

### Contexte
L'énoncé demande : *« Un adhérent peut-il réserver un document disponible uniquement dans l'autre site ? »*. Le réseau comprend deux médiathèques de quartier distinctes.

### Options considérées
1. **Interdiction stricte des réservations inter-sites** : L'adhérent ne peut réserver que si un exemplaire est physiquement rattaché à son site ou doit se déplacer lui-même. (Inconvénient : mauvaise expérience utilisateur, ne valorise pas la notion de « réseau » de médiathèques).
2. **Autorisation universelle avec création automatique d'un transfert (`Transfer`) et statut dédié `AWAITING_TRANSFER`** : L'adhérent choisit son site de retrait. Le système prend en charge la logistique inter-sites que l'exemplaire soit déjà disponible ou actuellement emprunté sur l'autre site.

### Choix retenu
Option 2 :
- **Oui, un adhérent peut réserver une édition disponible uniquement sur l'autre site.**
- **Cas 1 (Exemplaire disponible au site de retrait)** : La réservation passe immédiatement au statut `READY`, l'exemplaire passe au statut `HELD_FOR_PICKUP`, et le délai de 5 jours commence immédiatement sans transfert.
- **Cas 2 (Exemplaire disponible uniquement sur l'autre site)** : Un exemplaire disponible du site distant est assigné et retiré de la circulation (`Copy.status = IN_TRANSIT`, son `currentBranchId` restant le site source tant que le transit n'est pas achevé). Un ordre de transfert `Transfer` (statut `REQUESTED`, signifiant « en attente d'expédition ») est créé. **La réservation prend immédiatement le statut `AWAITING_TRANSFER`**. Cela garantit qu'elle quitte la file d'attente FIFO et ne pourra jamais se voir allouer un second exemplaire par erreur.
- **Cas 3 (Tous les exemplaires du réseau sont actuellement prêtés)** : La réservation entre dans la file d'attente FIFO (`status = WAITING`). Lors du retour d'un exemplaire sur n'importe quel site, si un transfert est nécessaire vers le site de retrait, la réservation passe à `AWAITING_TRANSFER` et le transfert démarre ; si la restitution a lieu directement sur le site de retrait, elle passe directement à `READY`.
- **Invariant structurel** : Une file d'attente active (réservations au statut `WAITING`) n'existe que si **aucun exemplaire de l'édition n'est disponible dans tout le réseau**.
- **Gestion des annulations en cours de transit** : Si l'adhérent annule sa réservation alors que le transfert physique est en cours (`IN_TRANSIT`), le colis poursuit son acheminement jusqu'au site destinataire. À la réception (`Transfer.status = RECEIVED`), le système constate l'annulation : s'il existe une réservation en attente sur ce site pour la même édition, elle lui est affectée (`READY`) ; sinon, l'exemplaire devient `AVAILABLE` dans le stock du site de destination.

### Conséquences
- Circulation fluide et intelligente des documents entre les deux médiathèques.
- Zéro risque de double affectation d'exemplaires grâce à l'état pivot `AWAITING_TRANSFER`.
- Zéro blocage logistique en cas d'annulation pendant le transit.

---

## [2026-11-17] DEC-004 : Durée de mise de côté et point de départ du délai de retrait (Question 3)

### Contexte
L'énoncé interroge : *« Combien de temps un document réservé reste-t-il mis de côté une fois revenu ? »*. Il convient de définir une durée raisonnable sans bloquer indéfiniment les exemplaires non réclamés.

### Options considérées
1. **Délai calculé dès le retour initial en médiathèque d'origine** : Le compte à rebours démarre au moment où l'exemplaire est scanné en retour, même s'il doit transiter vers l'autre site. (Inconvénient : injuste pour l'adhérent si le transfert met 2 jours à être acheminé).
2. **Délai fixe de 5 jours calendaires débutant à la mise à disposition effective sur le site de retrait** : Le délai ne démarre que lorsque l'exemplaire est physiquement disponible sur le site choisi par l'adhérent.

### Choix retenu
Option 2 :
- Le document réservé reste mis de côté pendant **5 jours calendaires**.
- **Point de départ strict** : Le délai de 5 jours commence **uniquement lorsque l'exemplaire est physiquement disponible au site de retrait (PICKUP branch)** :
  - Si aucun transfert n'est requis : dès la restitution au comptoir, l'exemplaire passe au statut `HELD_FOR_PICKUP`, la réservation passe au statut `READY`, et `pickupDeadlineAt = now + 5 jours`.
  - Si un transfert est nécessaire : l'exemplaire voyage sous statut `IN_TRANSIT`. Le compte à rebours de 5 jours ne démarre qu'au moment où le bibliothécaire du site de retrait réceptionne le transfert (`Transfer.status = RECEIVED`). À cet instant précis, la réservation passe de `AWAITING_TRANSFER` à `READY` et `pickupDeadlineAt = date_reception + 5 jours`.
- **Expiration** : Une commande planifiée (`app:reservations:expire`) recherche les réservations dont `status = 'READY'` et `pickupDeadlineAt < now`. Elles passent au statut `EXPIRED`, et l'exemplaire est automatiquement proposé au réservant suivant dans la file FIFO (ou remis en rayon `AVAILABLE`).

### Conséquences
- Équité totale pour les usagers quel que soit le site de retour physique.
- Décongestion automatique des files d'attente grâce à la commande de purge.

---

## [2026-11-17] DEC-005 : Représentation des contributeurs multiples (Question 4)

### Contexte
L'énoncé demande : *« Comment représenter les auteurs multiples, les traducteurs, les illustrateurs ? »*. Les œuvres peuvent impliquer des scénaristes, dessinateurs (bandes dessinées), traducteurs ou réalisateurs (DVD).

### Options considérées
1. **Champ texte libre dénormalisé (`authors: string`)** : Simple chaîne de caractères sur l'édition. (Inconvénient : impossible de rechercher les ouvrages d'un auteur de manière fiable, aucun filtrage par rôle).
2. **Relation ManyToMany directe sans table de liaison explicite** : `Edition` liée à `Contributor`. (Inconvénient : impossible de qualifier le rôle joué ni l'ordre d'affichage sur la couverture).
3. **Entité pivot dédiée `EditionContributor`** : Relie `Edition` et `Contributor` avec un rôle typé (`ContributorRole`) et un entier de position (`position`).

### Choix retenu
Option 3 :
- Entité `Contributor` : nom unique ou raison sociale.
- Entité de liaison `EditionContributor` comportant :
  - `role` (enum PHP `ContributorRole` : `AUTHOR`, `ILLUSTRATOR`, `TRANSLATOR`, `DIRECTOR`, `OTHER`).
  - `position` (entier définissant l'ordre officiel d'apparition sur la notice bibliographique).

### Conséquences
- Modélisation fine et robuste adaptée à tous les types de médias (romans, BD, DVD).
- Précision des filtres de recherche dans le catalogue (ex: chercher par traducteur ou illustrateur).

---

## [2026-11-17] DEC-006 : Réconciliation des données de l'API Open Library (Question 5)

### Contexte
L'énoncé demande : *« Que faire quand Open Library renvoie des données incomplètes ou différentes de celles du catalogue ? »*. L'API externe Open Library est libre et participative, ce qui implique de potentielles données manquantes ou divergentes.

### Options considérées
1. **Écrasement automatique par les données externes** : Considérer l'API externe comme la vérité absolue. (Inconvénient : risque de perdre des corrections saisies manuellement par les bibliothécaires locaux).
2. **Rejet strict des notices incomplètes** : Refuser l'import si un champ obligatoire (ex: jaquette ou éditeur) manque. (Inconvénient : frustrant lors des imports par lot de listes d'ISBN).
3. **Priorité absolue au catalogue local et marquage des imports incomplets** : Ne jamais écraser une notice existante ; importer les données manquantes avec un statut de complétude `to_complete`.

### Choix retenu
Option 3 :
- **Priorité absolue au catalogue local** : Si un ISBN existe déjà en base, l'import est ignoré (doublon comptabilisé dans le rapport d'importation). Les données locales ne sont jamais écrasées par l'API externe.
- **Données incomplètes** : Si Open Library renvoie une notice sans couverture, sans éditeur ou avec des données partielles, la notice est tout de même créée pour faire gagner du temps au bibliothécaire, mais son champ `completenessStatus` est défini à `TO_COMPLETE`.
- **Visibilité bibliothécaire** : Un filtre dédié dans le back-office permettra au bibliothécaire de lister les notices `TO_COMPLETE` pour enrichissement manuel ultérieur.

### Conséquences
- Garantie d'intégrité du catalogue local sans risque de régression de données.
- Traitement par lot robuste et résilient (rapport détaillé : importés, incomplets, doublons ignorés, erreurs API).

---

## [2026-11-17] DEC-007 : Intégrité concurrente sous MySQL 8 (Index partiels simulés, Quotas et REPEATABLE READ)

### Contexte
MySQL 8 ne supportant pas les index uniques partiels avec clause conditionnelle (`WHERE`), et fonctionnant par défaut sous le niveau d'isolation `REPEATABLE READ`, il est nécessaire de se prémunir contre les écritures concurrentes illégitimes (dépassement de quota de prêt, double prêt d'un même exemplaire, double réservation active pour un adhérent, double affectation d'une réservation FIFO).

### Options considérées
1. **Validation applicative seule (SELECT puis INSERT sans verrou)** : Vulnérable aux race conditions sous `REPEATABLE READ` (lectures non bloquantes, insertion de doublons concurrents).
2. **Verrous pessimistes seuls (`PESSIMISTIC_WRITE`)** : Bloque les conflits applicatifs mais ne constitue pas une contrainte d'intégrité physique au niveau du SGBD si une écriture directe survient.
3. **Défense en profondeur : Verrouillage ordonné + Colonnes uniques nullables** :
   - Pour les prêts : ordre fixe des verrous (**`Member` d'abord, `Copy` ensuite**) pour sérialiser le contrôle de quota sans deadlock + colonne `Loan.activeCopyId` (`UNIQUE`, `NULL` après retour).
   - Pour les réservations : verrouillage de la ligne `Member` + colonne `Reservation.activeMemberEditionKey` (`UNIQUE`, `NULL` après fin de réservation, active sous `WAITING`, `AWAITING_TRANSFER` et `READY`).
   - Pour le dépouillement FIFO : verrouillage exclusif `FOR UPDATE` sur la ligne de réservation cible lors des retours, avec bascule immédiate vers `AWAITING_TRANSFER` ou `READY`.

### Choix retenu
Option 3 :
- **Comportement MySQL/InnoDB** : InnoDB autorise une infinité de valeurs `NULL` dans une colonne protégée par une contrainte `UNIQUE`. Le moteur garantit ainsi physiquement qu'il ne peut exister qu'une seule valeur non nulle pour un exemplaire ou un adhérent/édition actif.
- **Ordre strict des verrous anti-interblocage (Anti-Deadlock)** : Dans `LoanService::borrow()`, l'acquisition des verrous se fait toujours dans l'ordre déterministe `Member` puis `Copy`.
- **Protection de la file FIFO** : Lors d'un retour ou d'une expiration, la réservation à allouer est verrouillée en écriture (`setLockMode(LockMode::PESSIMISTIC_WRITE)`). En basculant immédiatement à l'état `AWAITING_TRANSFER` (ou `READY`), elle quitte instantanément la condition `status = 'WAITING'`, interdisant formellement que deux retours simultanés n'allouent deux exemplaires à la même réservation.

### Conséquences
- Forte garantie d'intégrité des données et haute résilience face aux écritures concurrentes, sans dépendre d'un SGBD spécifique comme PostgreSQL.
- Comportement prévisible et exempt de deadlocks sur l'ensemble des scénarios de prêt et de réservation.


---

## [2026-11-17] DEC-008 : Migration de sécurité vers PHP 8.3 (PHP2Shell CVE-2026-109618 / CVE-2026-109619) et durcissement

### Contexte
La publication des vulnérabilités critiques PHP2Shell (CVE-2026-109618 et CVE-2026-109619, exécution de code à distance non authentifiée via `session.upload_progress`) affecte toutes les versions de PHP inférieures à 8.2.35 / 8.3.36 / 8.4.27. De plus, la branche PHP 8.2 atteint sa fin de vie (End Of Life) le 31 décembre 2026. L'environnement initial du projet (PHP 8.2.12 sur l'hôte et image Docker `php:8.2-fpm-alpine`) présentait un risque direct d'exploitation et de péremption imminente.

### Options considérées
1. **Rester sur PHP 8.2 en appliquant le patch 8.2.35** : Corrige temporairement les failles CVE mais conserve une branche en fin de vie sous quelques semaines, obligeant à une migration future.
2. **Migrer vers PHP 8.3 avec un patch minimal 8.3.36 (Retenu)** : Branche activement supportée jusqu'à fin 2027, mature, stable, pleinement supportée par Symfony 7.4 et Doctrine ORM 3, corrigeant définitivement PHP2Shell.
3. **Migrer immédiatement vers PHP 8.4** : Version très récente (8.4.27+), risquant d'introduire des dépréciations ou incompatibilités avec certains bundles communautaires.

### Choix retenu
Option 2 (PHP 8.3 avec version minimale 8.3.36) :
- **Environnement local hôte** : Exigence d'un runtime local `PHP >= 8.3.36` avec `session.upload_progress.enabled = Off`.
- **Épinglage Docker strict** : Utilisation d'un tag d'image immuable et vérifié sur Docker Hub : `php:8.3.36-fpm-alpine` (aucun tag flottant `php:8.3-fpm` ou `latest`).
- **Durcissement INI versionné** : Injection dans l'image Docker du fichier `docker/php/conf.d/security.ini` contenant `session.upload_progress.enabled = 0` pour neutraliser préventivement tout vecteur d'attaque.
- **Verrouillage Composer** : Mise à jour de `composer.json` avec `"php": ">=8.3"` et verrouillage de la plateforme cible `"config": {"platform": {"php": "8.3.0"}}` pour garantir que le graphe de dépendances et `composer.lock` restent strictement reproductibles sur toute machine.
- **Remplacement de polyfill** : Déclaration de `"symfony/polyfill-php83": "*"` dans la section `replace` de `composer.json` pour supprimer le polyfill devenu inutile sous PHP 8.3 natif.

### Conséquences
- Résilience et immunité totales face aux failles PHP2Shell.
- Pérennité du socle technique au-delà de l'échéance EOL de PHP 8.2.
- Parfaite cohérence entre le runtime hôte (8.3.36), le runtime conteneurisé (8.3.36) et le verrouillage de plateforme Composer (8.3.0).

---

## [2026-11-17] DEC-009 : Dépendances de développement et chargement des fixtures sous Docker (Étape 14)

### Contexte
L'évaluation finale (Niveau 5) exige une livraison Docker clé en main exécutable en une seule commande (`docker compose up --build`), amorçant automatiquement la base de données, les migrations et un jeu de données réaliste (Niveau 1) avec comptes de démonstration pour le jury. Or, le paquet `doctrine/doctrine-fixtures-bundle` est une dépendance de développement (`--dev`).

### Options considérées
1. **Environnement de production strict (`APP_ENV=prod`) sans dépendances dev** : Nécessite d'injecter un dump SQL pré-généré dans le conteneur ou d'installer des fixtures dans le code de production. (Inconvénient : maintenance lourde de scripts SQL désynchronisés des entités Doctrine, perte de flexibilité pour le jury).
2. **Environnement de démonstration/évaluation (`APP_ENV=dev`) avec dépendances dev dans l'image Docker** : L'image Docker de livraison installe l'ensemble des dépendances (y compris `--dev`) et s'exécute sous `APP_ENV=dev`.

### Choix retenu
Option 2 :
- L'image Docker de livraison exécutera `composer install` avec les dépendances de développement et fonctionnera sous `APP_ENV=dev`.
- L'entrypoint Docker pourra ainsi exécuter automatiquement `php bin/console doctrine:migrations:migrate --no-interaction` puis `php bin/console doctrine:fixtures:load --no-interaction`.
- Le jury bénéficiera d'un environnement complet prêt à tester dès l'ouverture du navigateur, avec les profils de démonstration, la barre de débogage Symfony et les outils d'inspection.

### Conséquences
- Déploiement automatisé et infaillible en une seule commande (`docker compose up --build`).
- Données de démonstration toujours synchronisées avec les entités et migrations Doctrine.

---

## [2026-11-17] DEC-010 : Architecture des services métier du domaine et stratégie de tests automatisés (Niveau 2)

### Contexte
Le Niveau 2 impose la mise en œuvre de règles de gestion strictes (quotas d'emprunts simultanés, durées selon les formules, calcul des pénalités de retard, file d'attente FIFO des réservations, transferts logistiques inter-sites, déclaration de documents perdus ou détériorés). Ces règles impliquent des opérations transactionnelles multi-entités et des calculs temporels sensibles (retards, délais de retrait de 5 jours).

### Options considérées
1. **Logique métier éclatée dans les contrôleurs et formulaires** : Simple à court terme pour un prototype, mais impossible à tester unitairement sans instancier tout le noyau HTTP, couplée au framework et sujette à des régressions lors du développement des futures interfaces publiques (Niveau 3).
2. **Architecture pilotée par événements (Messenger / Event Dispatcher)** : Découplage maximal, mais complexité inutile et asynchronisme inadapté à la validation immédiate des quotas lors d'un emprunt au comptoir.
3. **Services du Domaine purs avec injection de `ClockInterface`, transactions explicites et exceptions typées (Retenu)** :
   - Services métiers spécialisés (`LoanService`, `ReservationQueueService`, `TransferService`, `PenaltyCalculator`).
   - Exceptions de domaine dédiées (`LoanQuotaExceededException`, `SubscriptionExpiredException`, `UnpaidPenaltyException`, etc.) héritant de `DomainException`.
   - Contrôle temporel déterministe via `Symfony\Component\Clock\ClockInterface` (permettant d'utiliser `MockClock` dans les tests sans toucher à l'horloge système).
   - Encapsulation des mutations critiques dans `$em->wrapInTransaction()`.

### Choix retenu
Option 3 :
- **Découplage et réutilisabilité** : Les mêmes services gouvernent à la fois le back-office EasyAdmin, les commandes console (`app:reservations:expire`) et les futures interfaces adhérent/bibliothécaire de la Journée 2.
- **Testabilité totale** : Les 29 tests automatisés (PHPUnit 12) s'exécutent en moins de 20 secondes sur une base MySQL dédiée (`mediatheque_test`) en manipulant `MockClock` pour voyager dans le temps (+14 jours, +6 jours) sans aucun artifice (`sleep()`).
- **Filet de sécurité de concurrence** : Les verrous applicatifs sont doublés par les index d'unicité physiques en base (`UNIQ_LOAN_ACTIVE_COPY` et `UNIQ_RES_ACTIVE_MEMBER_EDITION`).

### Conséquences
- Architecture robuste, maintenable et 100% conforme aux exigences de robustesse du sujet.
- Élimination des effets de bord temporels dans les tests automatisés.
- Couverture complète de toutes les règles métier du Niveau 2 validée à 100%.

---

## [2026-11-17] DEC-011 : Modèle de sécurité, hiérarchie de rôles et Voters d'autorisation métier (Niveau 3)

### Contexte
Le système s'adresse à trois profils distincts avec des privilèges et des responsabilités différenciés :
1. **Adhérents (`ROLE_MEMBER`)** : consultation de l'état de leurs emprunts, prolongation de leurs propres prêts, annulation de leurs réservations actives, recherche catalogue.
2. **Bibliothécaires (`ROLE_LIBRARIAN`)** : gestion des prêts/retours au guichet express, import unitaire ou par lot de notices bibliographiques, réception des transferts inter-sites.
3. **Responsables de réseau / Administrateurs (`ROLE_ADMIN`)** : accès complet au back-office EasyAdmin, configuration des formules d'abonnement, consultation des tableaux de bord statistiques et de rotation de fonds.

Il est impératif d'éviter que des contrôles d'autorisation complexes ne soient disséminés dans les gabarits Twig ou dupliqués dans les contrôleurs.

### Options considérées
1. **Contrôles simples basés sur les rôles (`is_granted('ROLE_MEMBER')`)** : Facile à configurer mais incapable de vérifier qu'un adhérent n'annule que *sa propre* réservation ou ne prolonge que *son propre* prêt.
2. **Logique d'autorisation intégrée directement dans les contrôleurs** : Rend la logique difficilement testable unitairement et risque d'omettre des contrôles lors de l'ajout de nouvelles routes.
3. **Hiérarchie de rôles Symfony + Voters de sécurité dédiés (`LoanVoter`, `ReservationVoter`)** :
   - Hiérarchie explicite : `ROLE_ADMIN` hérite de `ROLE_LIBRARIAN`, qui hérite de `ROLE_MEMBER`.
   - Voters Symfony encapsulant les règles d'habilitation contextuelles :
     - `LoanVoter` : autorise l'attribut `EXTEND` si l'utilisateur est le titulaire du prêt (`loan.member.user === user`) ou possède `ROLE_LIBRARIAN`.
     - `ReservationVoter` : autorise l'attribut `CANCEL` si l'utilisateur est le titulaire de la réservation (`reservation.member.user === user`) ou possède `ROLE_LIBRARIAN`.

### Choix retenu
Option 3 :
- Hiérarchie déclarée dans `security.yaml` :
  ```yaml
  role_hierarchy:
      ROLE_LIBRARIAN: ROLE_MEMBER
      ROLE_ADMIN: [ROLE_LIBRARIAN]
  ```
- Mise en œuvre de `App\Security\Voter\LoanVoter` et `App\Security\Voter\ReservationVoter`.
- Redirection post-authentification intelligente : les bibliothécaires et administrateurs sont dirigés vers le guichet express ou le tableau de bord, tandis que les adhérents sont dirigés vers leur espace personnel.

### Conséquences
- Sécurité hermétique : un usager authentifié ne peut en aucun cas interagir avec les prêts ou réservations d'un tiers.
- Code propre, réutilisable dans Twig (`is_granted('EXTEND', loan)`) et dans les contrôleurs (`$this->denyAccessUnlessGranted('CANCEL', $reservation)`).
- Couverture complète validée par des tests unitaires (`LoanVoterTest`, `ReservationVoterTest`) et fonctionnels (`SecurityAccessTest`).

---

## [2026-11-17] DEC-012 : Métriques de rotation des fonds et tableaux de bord statistiques (Niveau 3)

### Contexte
La gestion d'un réseau de médiathèques requiert des indicateurs d'aide à la décision pour évaluer l'attractivité des collections, identifier les fonds sous-utilisés et optimiser les acquisitions futures. Le sujet demande en particulier de mesurer le **taux de rotation** des documents, le classement des œuvres les plus empruntées et la liste des exemplaires jamais empruntés.

### Options considérées
1. **Calcul dynamique par itération sur les collections Doctrine (PHP)** : Charger l'ensemble des entités en mémoire et calculer les statistiques en PHP. (Inconvénient : effondrement des performances avec la croissance du catalogue).
2. **Tables d'agrégation pré-calculées avec déclencheurs (triggers)** : Rapide à la lecture mais alourdit considérablement le modèle relationnel et la maintenance des migrations.
3. **Requêtes d'agrégation DQL / SQL dédiées optimisées par indexation (`StatisticsService`)** :
   - **Taux de rotation global et par site** : formule bibliothécaire standard $Taux = \frac{Nombre\ total\ d'emprunts}{Nombre\ d'exemplaires\ physiques}$.
   - **Palmarès des éditions les plus empruntées** : requête avec `GROUP BY` et `COUNT(l.id) DESC` sur les prêts historiques.
   - **Fonds dormant (exemplaires jamais empruntés)** : requête `LEFT JOIN loan` avec clause `WHERE l.id IS NULL`.

### Choix retenu
Option 3 :
- Centralisation dans `App\Service\StatisticsService` injecté dans `App\Controller\StatisticsController`.
- Utilisation de requêtes DQL optimisées tirant parti des index composites sur `loan(copy_id)` et `copy(current_branch_id)`.
- Restitution sous forme de tableau de bord moderne Bootstrap avec cartes synthétiques (total emprunts, prêts actifs, taux de rotation par site et global) et tableaux détaillés.

### Conséquences
- Exécution instantanée des requêtes même sur un catalogue de plusieurs milliers d'exemplaires.
- Indicateurs pertinents immédiatement exploitables par la direction du réseau.
- Validation rigoureuse par tests d'intégration et fonctionnels (`StatisticsServiceTest`).

---

## [2026-11-17] DEC-013 : Intégration Open Library, respect des directives d'usage officielles et résilience (Niveau 4)

### Contexte
Le Niveau 4 requiert l'enrichissement du catalogue via l'API publique Open Library. La documentation officielle d'Open Library stipule des directives strictes :
1. Les requêtes doivent identifier l'application avec un en-tête `User-Agent` comportant un contact pour bénéficier d'une limite de débit triplée (3 req/s au lieu de 1 req/s).
2. Les requêtes ne doivent pas servir de backend massif sans mise en cache.
3. Les données participatives d'Open Library peuvent comporter des lacunes (titres tronqués, éditeurs manquants, jaquettes absentes).

### Options considérées
1. **Requêtes anonymes synchrones sans régulation de flux** : Risque immédiat de blocage HTTP 429 (Too Many Requests) ou de bannissement IP par Internet Archive lors des imports par lot.
2. **Client avec identification officielle, temporisation active et évaluation de complétude (Retenu)** :
   - En-tête personnalisé `User-Agent: MediathequePFE/1.0 (pfe@educationmedia.ma)` conformément aux exigences d'Open Library.
   - Régulation de flux automatique : temporisation de **0,35 seconde** (3 requêtes par seconde) dans la commande console par lot `app:import-isbn`.
   - Découplage via l'interface `BookMetadataProviderInterface` et `OpenLibraryClient`.
   - Évaluation heuristique de complétude : statut `CompletenessStatus::COMPLETE` si titre, auteur, éditeur et jaquette sont présents ; sinon `CompletenessStatus::TO_COMPLETE` (DEC-006) pour signalement au bibliothécaire.
   - Priorité absolue au catalogue local : les ISBNs déjà existants sont comptabilisés comme `SKIPPED` et ne sont jamais écrasés.

### Choix retenu
Option 2 :
- `Symfony\Contracts\HttpClient\HttpClientInterface` utilisé avec timeout de 5 secondes.
- DTO immuable `BookMetadata` pour isoler le modèle de domaine des structures JSON externes (`/isbn/{isbn}.json` et `/works/{id}.json`).
- Commande CLI robuste `ImportIsbnCommand` acceptant une liste d'ISBNs (séparés par retours à la ligne ou virgules), avec barre de progression console et tableau récapitulatif détaillé.
- Interface Web dédiée réservée aux bibliothécaires (`/librarian/import`) pour la saisie unitaire avec prévisualisation immédiate de la couverture.

### Conséquences
- Conformité absolue avec les directives éthiques et techniques de la fondation Internet Archive.
- Zéro dégradation des données existantes en base locale.
- Testabilité totale sans dépendance réseau grâce au bouchonnage via `MockHttpClient` et `MockResponse`.

---

## [2026-11-17] DEC-014 : Déploiement conteneurisé clé en main en une commande (Niveau 5)

### Contexte
Le Niveau 5 évalue la reproductibilité et la facilité d'installation par le jury. L'énoncé demande une mise en route rapide, idéalement en une seule commande Docker, sans exiger de configuration manuelle préalable.

### Options considérées
1. **Guide pas à pas demandant à l'évaluateur d'exécuter manuellement 5 commandes dans les conteneurs** (`docker compose up`, puis `exec app migrations:migrate`, puis `exec app fixtures:load`). (Inconvénient : risque d'erreur humaine, expérience d'évaluation dégradée).
2. **Script d'entrée Docker automatisé et idempotent (`docker-entrypoint.sh`)** :
   - Attente active de la disponibilité physique du port MySQL 3306.
   - Exécution automatique des migrations Doctrine (`doctrine:migrations:migrate --no-interaction`).
   - Amorçage automatique des fixtures réalistes (`doctrine:fixtures:load --no-interaction`) avec comptes de test prêts à l'emploi.
   - Lancement transparent du serveur PHP-FPM.

### Choix retenu
Option 2 :
- Configuration d'un point d'entrée `docker/php/docker-entrypoint.sh` robuste.
- Exposition claire des services sur les ports standards (8000 pour le Web, 3306 pour MySQL).
- Guide d'installation exhaustif `INSTALL.md` documentant le lancement Docker en 1 ligne, les identifiants de démonstration, et les procédures alternatives en cas de besoin.

### Conséquences
- Expérience d'évaluation optimale : le jury clone le dépôt, saisit `docker compose up --build -d` et dispose immédiatement d'une application complète et fonctionnelle sur `http://localhost:8000`.

