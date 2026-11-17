# Modèle de Données — Réseau de Médiathèques

Ce document présente l'architecture des données, le schéma relationnel complet (13 entités), les contraintes d'intégrité, les enums du domaine, les verrous de concurrence et les justifications conceptuelles.

---

## 1. Vocabulaire du Domaine (Lexique Bilingue)

| Concept Métier (FR) | Entité / Table (EN) | Définition & Rôle |
|---|---|---|
| **Site / Médiathèque** | `Branch` | L'un des deux établissements physiques du réseau. |
| **Œuvre** | `Work` | Création intellectuelle abstraite (ex: *Les Misérables*). |
| **Édition** | `Edition` | Publication concrète d'une œuvre (ISBN, éditeur, année, format). |
| **Contributeur** | `Contributor` | Personne physique ou morale (auteur, traducteur, illustrateur...). |
| **Contribution** | `EditionContributor` | Relation liant un contributeur à une édition avec un rôle et un ordre. |
| **Exemplaire** | `Copy` | Support physique identifié par un code-barres unique, rattaché à un site. |
| **Formule d'abonnement** | `Plan` | Règles de prêt (durée, quota, extensions autorisées, taux de pénalité). |
| **Compte Utilisateur** | `User` | Authentification (email, mot de passe hashé, rôles de sécurité). |
| **Adhérent** | `Member` | Usager de la médiathèque lié à un compte `User`, avec un abonnement et un site de rattachement. |
| **Prêt** | `Loan` | Emprunt d'un exemplaire par un adhérent avec dates d'échéance et de retour. |
| **Réservation** | `Reservation` | Réservation d'une édition avec file d'attente FIFO et site de retrait. |
| **Pénalité** | `Penalty` | Montant dû suite à un retard, une détérioration ou la perte d'un document. |
| **Transfert** | `Transfer` | Acheminement d'un exemplaire entre deux médiathèques. |

---

## 2. Diagramme Conceptuel Complet (13 Entités — Mermaid ERD)

```mermaid
erDiagram
    Branch ||--o{ Copy : "abrite"
    Branch ||--o{ Member : "rattache"
    Branch ||--o{ Reservation : "recoit_retrait"
    Branch ||--o{ Transfer : "source"
    Branch ||--o{ Transfer : "destination"
    Work ||--o{ Edition : "decline"
    Edition ||--o{ Copy : "possede"
    Edition ||--o{ EditionContributor : "associe"
    Contributor ||--o{ EditionContributor : "participe"
    Edition ||--o{ Reservation : "concerne"
    User ||--o| Member : "possede_profil"
    Plan ||--o{ Member : "regit"
    Member ||--o{ Loan : "emprunte"
    Copy ||--o{ Loan : "est_prete"
    Member ||--o{ Reservation : "reserve"
    Copy |o--o{ Reservation : "est_affecte_a"
    Loan ||--o{ Penalty : "genere"
    Copy ||--o{ Transfer : "est_transfere"
    Reservation |o--o{ Transfer : "declenche"

    Branch {
        int id PK
        string name
        string address
    }

    Work {
        int id PK
        string originalTitle
    }

    Edition {
        int id PK
        int workId FK
        string isbn UK "nullable pour DVD"
        string title
        string publisher
        int publicationYear
        string mediaType "BOOK, COMIC, DVD"
        string coverUrl "nullable"
        string completenessStatus "COMPLETE, TO_COMPLETE"
        datetime createdAt
    }

    Contributor {
        int id PK
        string name
    }

    EditionContributor {
        int id PK
        int editionId FK
        int contributorId FK
        string role "AUTHOR, ILLUSTRATOR, TRANSLATOR, DIRECTOR, OTHER"
        int position
    }

    Copy {
        int id PK
        int editionId FK
        int currentBranchId FK "source branch pendant transit"
        string barcode UK
        string status "AVAILABLE, ON_LOAN, HELD_FOR_PICKUP, IN_TRANSIT, LOST, DAMAGED"
        datetime createdAt
    }

    Plan {
        int id PK
        string name
        int maxSimultaneousLoans
        int loanDurationDays
        int maxExtensions
        int extensionDurationDays
        decimal penaltyRatePerDay "MAD"
    }

    User {
        int id PK
        string email UK
        string password
        json roles
    }

    Member {
        int id PK
        int userId FK "UNIQUE (1:0..1)"
        int planId FK
        int homeBranchId FK
        string membershipNumber UK
        datetime subscriptionEndsAt
        datetime createdAt
    }

    Loan {
        int id PK
        int copyId FK
        int memberId FK
        int activeCopyId UK "nullable: copyId si actif, NULL si retourne"
        datetime borrowedAt
        datetime dueDate
        datetime returnedAt "nullable"
        int extensionCount
    }

    Reservation {
        int id PK
        int editionId FK
        int memberId FK
        int pickupBranchId FK
        int assignedCopyId FK "nullable"
        string activeMemberEditionKey UK "nullable: memberId_editionId si active, NULL sinon"
        string status "WAITING, AWAITING_TRANSFER, READY, FULFILLED, EXPIRED, CANCELLED"
        datetime createdAt
        datetime pickupDeadlineAt "nullable"
        datetime fulfilledAt "nullable"
    }

    Penalty {
        int id PK
        int loanId FK
        decimal amount "MAD"
        string reason "LATE, LOST, DAMAGED"
        boolean isPaid
        datetime paidAt "nullable"
        datetime createdAt
    }

    Transfer {
        int id PK
        int copyId FK
        int sourceBranchId FK
        int targetBranchId FK
        int reservationId FK "nullable"
        string status "REQUESTED, IN_TRANSIT, RECEIVED, CANCELLED"
        datetime requestedAt
        datetime shippedAt "nullable"
        datetime receivedAt "nullable"
    }
```

---

## 3. Choix de Conception et Justifications Métier

1. **Séparation Œuvre (`Work`) / Édition (`Edition`)** :
   - Conforme à la norme FRBR (Functional Requirements for Bibliographic Records).
   - Une *Œuvre* matérialise la création intellectuelle abstraite (ex: *Le Petit Prince* d'Antoine de Saint-Exupéry).
   - Une *Édition* porte les attributs physiques et bibliographiques spécifiques (ISBN, format livre/BD/DVD, éditeur Gallimard/Folio, année de publication).
   - Cette distinction permet de rattacher plusieurs formats ou rééditions à une même création sans dupliquer l'identité fondamentale de l'œuvre.
2. **Réservation ciblée sur l'Édition (`Edition`)** :
   - L'usager réserve une édition précise (format, langue, traducteur ou jaquette recherchée) plutôt qu'une œuvre globale ou un exemplaire physique unique.
   - Dès qu'un exemplaire quelconque de cette édition devient disponible ou est restitué, il satisfait la réservation selon la file FIFO.
   - **Invariant structurel** : Une file d'attente active (réservations au statut `WAITING`) n'existe que si **aucun exemplaire de l'édition n'est disponible dans tout le réseau** (car tout exemplaire disponible localement déclenche le statut `READY`, et tout exemplaire disponible sur l'autre site déclenche immédiatement l'assignation, le transfert et le statut `AWAITING_TRANSFER`).
3. **Table pivot de contribution (`EditionContributor`)** :
   - Permet de gérer les œuvres multi-auteurs (co-auteurs, anthologies) et les créations collaboratives (ex: scénariste et dessinateur pour une bande dessinée, réalisateur et scénariste pour un DVD).
   - Attribut `role` (enum `ContributorRole` : `AUTHOR`, `ILLUSTRATOR`, `TRANSLATOR`, `DIRECTOR`, `OTHER`).
   - Attribut `position` (entier) pour respecter l'ordre officiel d'apparition des crédits sur les fiches bibliographiques.
4. **Monnaie et Précision Financière** :
   - La monnaie officielle retenue pour le projet est le **Dirham Marocain (MAD)**, conformément au cadre de travail d'Education Media Company à Agadir.
   - Les montants de pénalité et tarifs journaliers sont strictement stockés en type **`DECIMAL(10,2)`**. L'utilisation des nombres à virgule flottante (`float`/`double`) est formellement proscrite pour éviter les dérives d'arrondi binaire.
5. **Horodatage et Fuseaux Horaires** :
   - Toutes les dates sont instanciées sous forme de **`DateTimeImmutable`** en temps universel coordonné (**UTC**) en base de données.
   - L'affichage dans l'interface utilisateur s'effectue dans le fuseau horaire du Maroc (`Africa/Casablanca`).
6. **Entité dédiée pour les Transferts (`Transfer`)** :
   - Un transfert physique entre deux sites possède son cycle de vie propre (`REQUESTED`, `IN_TRANSIT`, `RECEIVED`, `CANCELLED`), des horodatages de traçabilité (`requestedAt`, `shippedAt`, `receivedAt`), et peut exister indépendamment d'une réservation (ex: rééquilibrage de stock entre médiathèques).
   - La valeur `Transfer.status = REQUESTED` signifie « en attente d'expédition » (ordre de transfert émis, exemplaire mis de côté et retiré de la circulation, impossible à emprunter par un tiers).
7. **Localisation d'un exemplaire en transit et gestion des annulations** :
   - Lorsqu'un exemplaire passe au statut `IN_TRANSIT`, **`Copy.currentBranchId` conserve l'identifiant du site source** (lieu physique de départ) jusqu'à ce que le bibliothécaire du site récepteur valide la réception (`Transfer.status = RECEIVED`), moment où `Copy.currentBranchId` bascule vers le site de destination et où `Copy.status` devient `HELD_FOR_PICKUP` (ou `AVAILABLE`).
   - **Annulation en cours de transit** : Si un adhérent annule sa réservation alors que le document est déjà expédié (`IN_TRANSIT`), le transfert physique ne peut être stoppé en cours de route. À l'arrivée sur le site de destination (`Transfer.status = RECEIVED`), le système constate l'annulation : si un autre adhérent attend cette édition sur ce site, elle lui est immédiatement allouée (`READY`, délai de 5 jours) ; sinon, l'exemplaire devient `AVAILABLE` dans le catalogue local du site d'arrivée.
8. **Relation Utilisateur-Adhérent (`User ||--o| Member`)** :
   - Relation optionnelle `0..1` : un compte utilisateur (`User`) peut être un administrateur (`ROLE_ADMIN`) ou un bibliothécaire (`ROLE_LIBRARIAN`) sans posséder de profil lecteur (`Member`).

---

## 4. Périmètre Volontairement Minimal (Arbitrages "Challenge 2 Jours")

Pour garantir un niveau de qualité irréprochable sans dispersion dans le budget temps imparti :
- **Entité `Transfer`** : Cycle logistique minimal en 4 états sans gestion de colisage multi-exemplaires ni transporteurs externes.
- **Rôles de contribution secondaires** : Prise en charge des illustrateurs, traducteurs et réalisateurs pour la recherche et l'affichage des notices, sans modules complexes de gestion des droits d'auteur.
- **Entité `Branch`** : Limitée aux deux établissements du réseau avec leurs coordonnées essentielles.

---

## 5. Détail des Entités Clés

### `Plan` (Formule d'abonnement)
- `id` (int PK) : Identifiant.
- `name` (string) : Nom commercial (ex: *Standard*, *Jeunesse*, *Privilège*).
- `maxSimultaneousLoans` (int) : Nombre maximum d'exemplaires empruntables simultanément.
- `loanDurationDays` (int) : Durée autorisée d'un prêt en jours calendaires.
- `maxExtensions` (int) : Nombre maximum de prolongations autorisées par prêt.
- `extensionDurationDays` (int) : Durée de chaque prolongation en jours.
- `penaltyRatePerDay` (decimal 10,2) : Tarif journalier appliqué par jour de retard en MAD.

### `Member` (Adhérent)
- `id` (int PK) : Identifiant.
- `userId` (int FK, UNIQUE) : Relation OneToOne optionnelle côté User (`User 1:0..1 Member`).
- `planId` (int FK) : Formule souscrite déterminant les quotas et durées.
- `homeBranchId` (int FK) : Médiathèque principale d'inscription.
- `membershipNumber` (string, UNIQUE) : Numéro de carte lecteur unique (ex: `ADH-2026-0001`).
- `subscriptionEndsAt` (DateTimeImmutable) : Date et heure d'expiration de l'adhésion.
- `createdAt` (DateTimeImmutable) : Horodatage d'inscription.

### `Penalty` (Pénalité)
- `id` (int PK) : Identifiant.
- `loanId` (int FK) : Emprunt concerné.
- `amount` (decimal 10,2) : Montant en MAD (jamais de float).
- `reason` (enum `PenaltyReason`) :
  - `LATE` : Retard de restitution (calculé : `jours de retard * taux journalier du forfait`).
  - `LOST` : Déclaration de perte d'un exemplaire.
  - `DAMAGED` : Exemplaire restitué abîmé nécessitant réparation ou remplacement.
- `isPaid` (boolean) : Statut de règlement (`false` par défaut).
- `paidAt` (DateTimeImmutable, nullable) : Date de règlement effectif.
- `createdAt` (DateTimeImmutable) : Date d'émission de la pénalité.

### `Reservation` (Réservation)
- `id` (int PK) : Identifiant.
- `editionId` (int FK) : Édition demandée.
- `memberId` (int FK) : Adhérent demandeur.
- `pickupBranchId` (int FK) : Médiathèque choisie pour le retrait.
- `assignedCopyId` (int FK, nullable) : Exemplaire physique affecté dès qu'il est réservé/rapatrié.
- `activeMemberEditionKey` (string, nullable, UNIQUE) : Clé de verrouillage base de données (vaut `"memberId_editionId"` tant que la réservation est active sous statut `WAITING`, `AWAITING_TRANSFER` ou `READY` ; réinitialisée à `NULL` dès qu'elle est terminée sous statut `FULFILLED`, `EXPIRED` ou `CANCELLED`).
- `status` (enum `ReservationStatus`) :
  - `WAITING` : En attente dans la file FIFO (aucun exemplaire n'est encore disponible ni assigné).
  - `AWAITING_TRANSFER` : Exemplaire physique assigné et en cours de transfert vers le site de retrait choisi (`Transfer.status = IN_TRANSIT`).
  - `READY` : Exemplaire arrivé et disponible au site de retrait ; compte à rebours de 5 jours enclenché.
  - `FULFILLED` : Exemplaire retiré par l'adhérent (transformé en prêt effectif).
  - `EXPIRED` : Délai de 5 jours dépassé sans retrait (exemplaire réaffecté au suivant ou libéré).
  - `CANCELLED` : Annulée par l'adhérent ou le personnel.
- `createdAt` (DateTimeImmutable) : Horodatage d'inscription pour l'ordre FIFO strict.
- `pickupDeadlineAt` (DateTimeImmutable, nullable) : Date/heure limite de retrait (`date d'arrivée effective au site de retrait + 5 jours`).
- `fulfilledAt` (DateTimeImmutable, nullable) : Horodatage de retrait.

---

## 6. Contraintes d'Intégrité & Stratégie d'Indexation

### Contraintes d'Unicité (UNIQUE)
1. `User.email` : Unicité stricte des comptes utilisateurs.
2. `Edition.isbn` : Unicité de l'ISBN (nullable pour autoriser les DVD dépourvus d'ISBN).
3. `Copy.barcode` : Unicité du code-barres physique.
4. `Member.userId` : Unicité garantissant qu'un compte utilisateur ne peut avoir qu'un seul profil adhérent.
5. `Member.membershipNumber` : Unicité du numéro d'adhérent.
6. `Loan.activeCopyId` : Unicité garantissant physiquement au niveau SGBD qu'un exemplaire ne peut avoir qu'un seul prêt actif à la fois.
7. `Reservation.activeMemberEditionKey` : Unicité garantissant physiquement au niveau SGBD qu'un adhérent ne peut avoir qu'une seule réservation active (`WAITING`, `AWAITING_TRANSFER`, `READY`) sur une édition donnée.
8. `EditionContributor.(edition_id, contributor_id, role)` : Unicité interdisant les doublons de rôle pour un même contributeur sur une même édition.

### Index de Recherche et Clés Étrangères
- **Clés étrangères** : Index B-Tree systématiques sur l'ensemble des colonnes de liaison (`work_id`, `edition_id`, `copy_id`, `member_id`, `plan_id`, `branch_id`, etc.).
- **Contrôle de quota d'emprunt** :
  - `Loan.(member_id, returned_at)` : Index composite essentiel pour compter instantanément le nombre d'emprunts en cours d'un adhérent (`WHERE member_id = :id AND returned_at IS NULL`) lors de la vérification du quota.
- **Recherche Catalogue sur `Edition.title`** :
  - *Note technique* : Un index B-Tree classique n'accélère pas les recherches par sous-chaîne `LIKE '%terme%'`. Cependant, pour le volume du réseau (~8 000 notices), le temps de parcours d'une table complète en mémoire sous MySQL 8 est négligeable pour ~8 000 notices. L'index B-Tree sur `title` accélère les tris alphabétiques et les recherches par préfixe `LIKE 'terme%'`, tandis qu'un index `FULLTEXT` pourra être configuré pour les recherches multi-mots.
- **Index de Requêtes Métier Fréquentes** :
  - `Copy.status` : Index pour filtrer les exemplaires en rayon.
  - `Loan.due_date` : Index pour identifier rapidement les retards.
  - `Reservation.(edition_id, status, created_at)` : Index composite pour la sélection immédiate du premier réservant FIFO.
  - `Reservation.pickup_deadline_at` : Index pour la commande de purge des réservations expirées.
  - `Transfer.status` : Index pour la liste de travail logistique des bibliothécaires.

---

## 7. Règles d'Intégrité Concurrente sous MySQL 8

### A. Règle « Un seul prêt actif par exemplaire » et respect strict du quota adhérent
- **Problématique 1 (Double prêt concurrent)** : Deux requêtes simultanées tentant d'emprunter le même exemplaire.
- **Problématique 2 (Dépassement concurrent du quota)** : Deux requêtes simultanées pour un même adhérent qui a droit à 1 dernier prêt peuvent toutes deux lire un quota non atteint et insérer deux nouveaux prêts, dépassant le forfait autorisé.
- **Architecture de protection et ordre déterministe des verrous** :
  1. **Ordre strict des verrous (Anti-Deadlock)** : Toute opération d'emprunt dans `LoanService::borrow()` s'exécute dans `wrapInTransaction()` et applique un ordre immuable d'acquisition des verrous :
     - **Étape 1 : Verrouillage de l'Adhérent (`Member`)** via `LockMode::PESSIMISTIC_WRITE`. Le service compte ses prêts actifs avec l'index `Loan.(member_id, returned_at)`. Si le quota du forfait est déjà atteint, l'opération échoue immédiatement avec `LoanQuotaExceededException`.
     - **Étape 2 : Verrouillage de l'Exemplaire (`Copy`)** via `LockMode::PESSIMISTIC_WRITE`. Le service vérifie que `Copy.status === AVAILABLE`.
     - *Bénéfice anti-interblocage* : L'ordre immuable (Member d'abord, Copy ensuite) élimine tout risque de deadlock croisé entre transactions concurrentes.
  2. **Niveau Base de Données (Filet de sécurité physique)** : La table `loan` intègre une colonne nullable `active_copy_id` protégée par une contrainte `UNIQUE (active_copy_id)`.
     - Lors de la création du prêt : `Loan.activeCopyId = Copy.id`.
     - Lors de la restitution : `Loan.activeCopyId = NULL`.
     - *Comportement MySQL/InnoDB* : InnoDB autorise une infinité de valeurs `NULL` dans une colonne `UNIQUE`. Ainsi, tous les prêts passés ont `active_copy_id = NULL` sans conflit, mais **deux transactions concurrentes tentant d'enregistrer un prêt actif pour le même exemplaire sont immédiatement bloquées par le moteur MySQL** avec une violation d'unicité `Duplicate entry '<copy_id>' for key 'loan_unique_active_copy'`.

### B. Règle « Une seule réservation active par adhérent et par édition » et allocation FIFO sans doublon
- **Problématique 1 (Double réservation sous REPEATABLE READ)** : Deux requêtes concurrentes d'un même adhérent peuvent lire 0 réservation active et insérer deux lignes en doublon.
- **Problématique 2 (Attribution concurrente lors d'un retour ou expiration)** : Si deux exemplaires de la même édition sont retournés au même instant à deux comptoirs, les deux processus pourraient attribuer leurs exemplaires respectifs à la même première réservation de la file FIFO si elle restait au statut `WAITING`.
- **Architecture de protection** :
  1. **Réservation initiale** :
     - Verrouillage pessimiste (`LockMode::PESSIMISTIC_WRITE`) sur la ligne de l'adhérent (`Member`) pour sérialiser ses demandes et lever `ActiveReservationAlreadyExistsException`.
     - Colonne de sécurité SGBD nullable `active_member_edition_key` (`UNIQUE`), valant `"memberId_editionId"` tant que la réservation est active (`WAITING`, `AWAITING_TRANSFER` ou `READY`), et `NULL` dès qu'elle est terminée (`FULFILLED`, `EXPIRED`, `CANCELLED`).
  2. **Dépouillement de la file d'attente (Retour d'exemplaire & Expiration)** :
     - Lors du retour d'un exemplaire ou de l'expiration d'une réservation, la sélection de la réservation suivante dans la file FIFO s'effectue obligatoirement avec un verrouillage exclusif sur la réservation cible :
       ```sql
       SELECT * FROM reservation 
       WHERE edition_id = :editionId AND status = 'WAITING' 
       ORDER BY created_at ASC 
       LIMIT 1 
       FOR UPDATE;
       ```
     - En Doctrine : `$em->createQuery(...)->setLockMode(LockMode::PESSIMISTIC_WRITE)->getOneOrNullResult()`.
     - **Séparation stricte via `AWAITING_TRANSFER`** : Dès qu'un exemplaire est assigné et nécessite un transport, la réservation bascule immédiatement à l'état `AWAITING_TRANSFER` (et non `WAITING`). Elle quitte ainsi instantanément le filtre `status = 'WAITING'`.
     - Ce double mécanisme (verrouillage `FOR UPDATE` + bascule immédiate hors du statut `WAITING`) garantit qu'aucun autre retour simultané ne pourra sélectionner cette même réservation : la première transaction verrouille et passe la réservation au statut `AWAITING_TRANSFER` (ou `READY`), et la seconde transaction traite obligatoirement la réservation suivante de la file FIFO.

---

## 8. Services Métier, Exceptions et Matrice de Traçabilité des Tests (Niveau 2)

### A. Services du Domaine (`src/Service/`)
- **`PenaltyCalculator`** : Calcul déterministe des jours de retard (`max(0, ceil((retour - échéance)))`) et application du barème journalier du plan via `bcmul`.
- **`LoanService`** :
  - `borrow(Member $member, Copy $copy): Loan` : Vérifie l'abonnement, le quota du plan, l'absence d'impayés, la disponibilité et pose le verrou `activeCopyId`.
  - `return(Loan $loan): ?Penalty` : Calcule et émet la pénalité si en retard, libère `activeCopyId` et notifie la file d'attente.
  - `extend(Loan $loan): Loan` : Valide l'éligibilité (non échu, quota prolongations non atteint, aucune réservation en attente).
  - `markLost(Copy $copy, ?Loan $loan, ?string $amount): ?Penalty` : Sortie d'inventaire (`LOST`) et pénalité de remplacement.
  - `markDamaged(Copy $copy, ?Loan $loan, ?string $amount): ?Penalty` : Sortie temporaire (`DAMAGED`) et pénalité de réparation.
- **`ReservationQueueService`** :
  - `reserve(Member $member, Edition $edition, Branch $pickupBranch): Reservation` : Inscription FIFO horodatée avec clé unique `activeMemberEditionKey`.
  - `handleCopyReturned(Copy $copy): ?Reservation` : Réattribution FIFO immédiate (`READY` si local, `Transfer` si distant).
  - `expireReservations(): int` : Purge des réservations prêtes non retirées sous 5 jours et réallocation au suivant.
- **`TransferService`** :
  - `requestTransfer()`, `shipTransfer()`, `receiveTransfer()` : Gestion logistique inter-sites.

### B. Matrice de Traçabilité des Tests Automatisés (PHPUnit 12 — 29 Tests)

| # | Règle Métier Spécifiée (Section 9.2 du Plan) | Classe de Test | Méthode de Test | Résultat |
|---|---|---|---|---|
| 1 | Retour dans les délais (aucune pénalité) | `PenaltyCalculatorTest` | `test_return_on_time_generates_no_penalty` | Validé |
| 2 | Retour anticipé (aucune pénalité) | `PenaltyCalculatorTest` | `test_return_before_due_date_generates_no_penalty` | Validé |
| 3 | Montant de pénalité de retard selon tarif journalier | `PenaltyCalculatorTest` | `test_return_late_generates_correct_penalty_amount_based_on_plan_daily_rate` | Validé |
| 4 | Calcul avec tarif journalier décimal | `PenaltyCalculatorTest` | `test_fractional_daily_rate_calculation` | Validé |
| 5 | Calcul de la date d'échéance selon formule | `LoanServiceTest` | `test_borrow_sets_due_date_according_to_plan_duration` | Validé |
| 6 | Blocage si quota d'emprunts simultanés atteint | `LoanServiceTest` | `test_borrow_refused_when_simultaneous_loan_quota_reached` | Validé |
| 7 | Blocage si adhésion de l'usager expirée | `LoanServiceTest` | `test_borrow_refused_when_member_subscription_is_expired` | Validé |
| 8 | Blocage si pénalité financière impayée | `LoanServiceTest` | `test_borrow_refused_when_member_has_unpaid_penalty` | Validé |
| 9 | Blocage si exemplaire non disponible (`ON_LOAN`, etc.) | `LoanServiceTest` | `test_borrow_refused_when_copy_is_not_available` | Validé |
| 10 | Retour dans les délais sans pénalité | `LoanServiceTest` | `test_return_on_time_generates_no_penalty` | Validé |
| 11 | Retour en retard générant une pénalité `LATE` | `LoanServiceTest` | `test_return_late_generates_correct_penalty_amount_based_on_plan_daily_rate` | Validé |
| 12 | Prolongation acceptée sans réservation concurrente | `LoanServiceTest` | `test_extend_accepted_when_no_reservation_waiting` | Validé |
| 13 | Prolongation refusée si prêt déjà en retard | `LoanServiceTest` | `test_extend_refused_when_loan_is_already_overdue` | Validé |
| 14 | Prolongation refusée si quota max atteint | `LoanServiceTest` | `test_extend_refused_when_max_extensions_quota_reached` | Validé |
| 15 | Prolongation refusée si réservation en attente | `LoanServiceTest` | `test_extend_refused_when_reservation_is_waiting_on_edition` | Validé |
| 16 | File d'attente de réservation dans l'ordre FIFO | `ReservationQueueServiceTest` | `test_reservation_queue_follows_strict_fifo_order` | Validé |
| 17 | Retour d'exemplaire activant la réservation (`READY`) | `ReservationQueueServiceTest` | `test_return_triggers_waiting_reservation_and_sets_it_ready` | Validé |
| 18 | Réservation inter-sites déclenchant un transfert | `ReservationQueueServiceTest` | `test_return_for_inter_branch_reservation_creates_transfer` | Validé |
| 19 | Expiration de réservation réallouant l'exemplaire | `ReservationQueueServiceTest` | `test_expired_reservation_hands_copy_over_to_next_member_in_queue` | Validé |
| 20 | Rejet d'une seconde réservation active identique | `ReservationQueueServiceTest` | `test_reserve_refused_when_member_already_has_active_reservation_for_same_edition` | Validé |
| 21 | Réservation refusée si adhésion expirée | `ReservationQueueServiceTest` | `test_reserve_refused_when_member_subscription_is_expired` | Validé |
| 22 | Cycle de vie d'un transfert avec réservation | `TransferServiceTest` | `test_transfer_workflow_updates_copy_branch_and_activates_pickup_ready` | Validé |
| 23 | Transfert logistique sans réservation liée | `TransferServiceTest` | `test_transfer_workflow_without_reservation_makes_copy_available` | Validé |
| 24 | Transition d'état de transfert invalide rejetée | `TransferServiceTest` | `test_invalid_transfer_transition_throws_exception` | Validé |
| 25 | Document déclaré perdu avec pénalité (`LOST`) | `TransferServiceTest` | `test_mark_copy_lost_removes_from_circulation_and_creates_penalty` | Validé |
| 26 | Document dégradé retiré de la circulation (`DAMAGED`) | `TransferServiceTest` | `test_mark_copy_damaged_removes_from_circulation` | Validé |
| 27 | Rejet physique MySQL du double emprunt simultané | `DoubleBorrowConcurrencyTest` | `test_concurrent_double_loan_is_prevented_by_active_copy_id_unique_index` | Validé |
| 28 | Coexistence historique sans conflit des prêts rendus | `DoubleBorrowConcurrencyTest` | `test_returned_loans_do_not_conflict_because_active_copy_id_is_null` | Validé |
| 29 | Rejet physique MySQL de la double réservation active | `DoubleBorrowConcurrencyTest` | `test_active_reservation_unique_key_blocks_duplicate_concurrent_reservations` | Validé |

### C. Matrice des Tests de Sécurité et d'Autorisation (Niveau 3)

| # | Règle / Scénario d'Autorisation | Classe de Test | Méthode de Test | Résultat |
|---|---|---|---|---|
| 30 | Prolongation autorisée pour le titulaire du prêt | `LoanVoterTest` | `test_vote_on_attribute_extend_granted_to_loan_owner` | Validé |
| 31 | Prolongation refusée pour un tiers adhérent | `LoanVoterTest` | `test_vote_on_attribute_extend_denied_to_other_member` | Validé |
| 32 | Prolongation autorisée pour un bibliothécaire | `LoanVoterTest` | `test_vote_on_attribute_extend_granted_to_librarian` | Validé |
| 33 | Vote neutre sur attribut non géré | `LoanVoterTest` | `test_vote_abstained_on_unsupported_attribute` | Validé |
| 34 | Annulation autorisée pour le titulaire de la réservation | `ReservationVoterTest` | `test_vote_on_attribute_cancel_granted_to_reservation_owner` | Validé |
| 35 | Annulation refusée pour un tiers adhérent | `ReservationVoterTest` | `test_vote_on_attribute_cancel_denied_to_other_member` | Validé |
| 36 | Annulation autorisée pour un bibliothécaire | `ReservationVoterTest` | `test_vote_on_attribute_cancel_granted_to_librarian` | Validé |
| 37 | Vote neutre sur attribut de réservation non géré | `ReservationVoterTest` | `test_vote_abstained_on_unsupported_attribute` | Validé |
| 38 | Page de connexion accessible publiquement (200 OK) | `SecurityAccessTest` | `test_login_page_is_publicly_accessible` | Validé |
| 39 | Accès espace adhérent refusé aux anonymes (redirection login) | `SecurityAccessTest` | `test_member_area_requires_authentication` | Validé |
| 40 | Accès guichet refusé aux adhérents sans rôle bibliothécaire (403) | `SecurityAccessTest` | `test_librarian_desk_forbidden_for_regular_member` | Validé |
| 41 | Accès back-office refusé aux adhérents (403) | `SecurityAccessTest` | `test_admin_area_forbidden_for_regular_member` | Validé |
| 42 | Connexion réussie et redirection vers espace adhérent | `SecurityAccessTest` | `test_successful_login_redirects_appropriately` | Validé |
| 43 | Rejet des identifiants invalides avec message d'erreur | `SecurityAccessTest` | `test_invalid_credentials_shows_error` | Validé |

### D. Matrice des Tests Catalogue Public, Espace Adhérent et Guichet (Niveau 3)

| # | Règle / Parcours Utilisateur | Classe de Test | Méthode de Test | Résultat |
|---|---|---|---|---|
| 44 | Catalogue accessible publiquement avec liste d'éditions | `CatalogueControllerTest` | `test_catalogue_index_displays_editions` | Validé |
| 45 | Recherche par mot-clé (titre ou auteur) | `CatalogueControllerTest` | `test_catalogue_search_by_query` | Validé |
| 46 | Filtrage par type de support (`BOOK`, `COMIC`, `DVD`) | `CatalogueControllerTest` | `test_catalogue_filter_by_type` | Validé |
| 47 | Fiche détaillée avec exemplaires et statuts par site | `CatalogueControllerTest` | `test_edition_detail_shows_copies_and_availability` | Validé |
| 48 | Réservation directe depuis la notice détaillée | `CatalogueControllerTest` | `test_authenticated_member_can_reserve_edition` | Validé |
| 49 | Espace adhérent affichant emprunts, réservations et pénalités | `MemberAreaControllerTest` | `test_member_dashboard_displays_loans_and_reservations` | Validé |
| 50 | Prolongation réussie d'un emprunt en cours | `MemberAreaControllerTest` | `test_member_can_extend_eligible_loan` | Validé |
| 51 | Refus d'annulation sur la réservation d'un tiers | `MemberAreaControllerTest` | `test_member_cannot_cancel_other_member_reservation` | Validé |
| 52 | Guichet bibliothécaire affichant la recherche d'exemplaire | `LibrarianDeskControllerTest` | `test_librarian_desk_page_loads_for_librarian` | Validé |
| 53 | Enregistrement d'un emprunt express au guichet | `LibrarianDeskControllerTest` | `test_quick_borrow_action_success` | Validé |
| 54 | Enregistrement d'un retour avec calcul immédiat de pénalité | `LibrarianDeskControllerTest` | `test_quick_return_action_creates_penalty_if_late` | Validé |

### E. Matrice des Tests Statistiques et Rotation des Fonds (Niveau 3)

| # | Indicateur / Calcul Statistique | Classe de Test | Méthode de Test | Résultat |
|---|---|---|---|---|
| 55 | Calcul exact du taux de rotation global et par site | `StatisticsServiceTest` | `test_turnover_rate_calculation_per_branch_and_global` | Validé |
| 56 | Palmarès des documents les plus empruntés (TOP N) | `StatisticsServiceTest` | `test_most_borrowed_editions_ranking` | Validé |
| 57 | Identification exhaustive des exemplaires jamais empruntés | `StatisticsServiceTest` | `test_never_borrowed_copies_list` | Validé |
| 58 | Tableau de bord `/admin/stats` accessible aux bibliothécaires | `StatisticsControllerTest` | `test_statistics_dashboard_displays_all_kpis` | Validé |

### F. Matrice des Tests d'Intégration Open Library (Niveau 4)

| # | Fonctionnalité Open Library | Classe de Test | Méthode de Test | Résultat |
|---|---|---|---|---|
| 59 | Appel API avec parsing JSON des titres, auteurs et jaquettes | `OpenLibraryClientTest` | `test_fetch_by_isbn_success_with_complete_data` | Validé |
| 60 | En-tête `User-Agent` officiel et limitation conforme aux directives | `OpenLibraryClientTest` | `test_client_sends_official_user_agent_header` | Validé |
| 61 | Gestion gracieuse d'une réponse 404 (ISBN inexistant) | `OpenLibraryClientTest` | `test_fetch_by_isbn_returns_null_on_404` | Validé |
| 62 | Détection des métadonnées incomplètes (`isComplete = false`) | `OpenLibraryClientTest` | `test_fetch_by_isbn_marks_incomplete_when_fields_missing` | Validé |
| 63 | Création en base locale avec statut `COMPLETE` | `BookImporterTest` | `test_import_creates_new_work_edition_and_contributors` | Validé |
| 64 | Création avec statut `TO_COMPLETE` si données partielles | `BookImporterTest` | `test_import_flags_incomplete_when_metadata_missing` | Validé |
| 65 | Non-écrasement des notices existantes (`SKIPPED`, DEC-006) | `BookImporterTest` | `test_import_skips_existing_isbn_without_overwriting` | Validé |
| 66 | Import par lot CLI `app:import-isbn` avec fichier texte | `ImportIsbnCommandTest` | `test_execute_batch_import_from_file` | Validé |
| 67 | Interface web bibliothécaire `/librarian/import` avec prévisualisation | `LibrarianImportControllerTest` | `test_import_single_isbn_web_form` | Validé |

---

## 9. Synthèse Globale de la Suite de Tests (85 Tests / 382 Assertions)

```
PHPUnit 12.5.44 (PHP 8.3.36)
Configuration: phpunit.dist.xml
Résultat: OK (85 tests, 382 assertions — 100% passants)
Temps d'exécution moyen: ~39 secondes
```

- **Tests Unitaires et Domaine (29 tests)** : Règles métier pures, calculs de pénalités décimales, horloges simulées `MockClock`, concurrence MySQL.
- **Tests de Sécurité et Autorisation (14 tests)** : Hiérarchie des rôles, Voters contextuels `LoanVoter` et `ReservationVoter`, pare-feu Symfony.
- **Tests Fonctionnels Catalogue & Usagers (15 tests)** : Recherche, navigation, fiches détaillées, espace adhérent, guichet express.
- **Tests Statistiques & Indicateurs (6 tests)** : Taux de rotation, requêtes d'agrégation groupée, top emprunts, fonds dormants.
- **Tests d'Intégration Externe Open Library (21 tests)** : Client HTTP, mocks d'API externe, commande CLI par lot avec rapport, formulaires web.

---

## 10. Architecture Applicative et Points d'Entrée

```mermaid
graph TD
    subgraph Interfaces Publiques et Métier
        UI_CATALOGUE["Catalogue Public<br/>(/catalogue)"]
        UI_MEMBER["Espace Adhérent<br/>(/member)"]
        UI_DESK["Guichet Bibliothécaire<br/>(/librarian/desk)"]
        UI_IMPORT["Import Open Library Web<br/>(/librarian/import)"]
        UI_STATS["Statistiques & Rotation<br/>(/admin/stats)"]
        CLI_IMPORT["Commande CLI par Lot<br/>(app:import-isbn)"]
        CLI_EXPIRE["Purge Réservations<br/>(app:reservations:expire)"]
    end

    subgraph Couche Sécurité & Contrôle
        SEC_FW["Firewall Symfony"]
        VOTER_L["LoanVoter"]
        VOTER_R["ReservationVoter"]
    end

    subgraph Services du Domaine
        SRV_LOAN["LoanService"]
        SRV_RES["ReservationQueueService"]
        SRV_TRANS["TransferService"]
        SRV_PEN["PenaltyCalculator"]
        SRV_STAT["StatisticsService"]
        SRV_IMP["BookImporter"]
        CLI_OL["OpenLibraryClient"]
    end

    subgraph Persistance & Concurrence
        ORM["Doctrine ORM 3.8"]
        DB[(MySQL 8.0 InnoDB<br/>REPEATABLE READ)]
    end

    UI_CATALOGUE --> SEC_FW
    UI_MEMBER --> VOTER_L
    UI_MEMBER --> VOTER_R
    UI_DESK --> SRV_LOAN
    UI_DESK --> SRV_RES
    UI_IMPORT --> SRV_IMP
    CLI_IMPORT --> SRV_IMP
    SRV_IMP --> CLI_OL
    UI_STATS --> SRV_STAT

    SRV_LOAN --> ORM
    SRV_RES --> ORM
    SRV_TRANS --> ORM
    SRV_STAT --> ORM
    ORM --> DB
```

