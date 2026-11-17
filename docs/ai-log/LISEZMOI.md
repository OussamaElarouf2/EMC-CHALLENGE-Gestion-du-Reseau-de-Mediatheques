# Guide des Traces d'Utilisation de l'IA (LISEZMOI.md)
**Challenge PFE 2026 — Education Media Company (EMC)**  
**Candidat** : Oussama El Arouf | **Date** : 17 Novembre 2026  
**Livrable Bonus (+15 points)** : Traces d'utilisation de l'intelligence artificielle

---

## 1. Outils d'IA Utilisés & Usages Spécifiques

Dans le cadre du développement de ce projet, les outils d'assistance par intelligence artificielle ont été mobilisés selon un cadre méthodologique strict : l'IA propose et accélère, l'ingénieur valide, arbitre, teste et assume l'entière responsabilité du code livré.

| Outil / Modèle | Usage Précis dans le Projet |
|---|---|
| **Antigravity CLI (Moteur Google Gemini 2.5 Pro)** | • **Architecture & Conception** : formalisation du schéma conceptuel FRBR, propositions de contraintes d'intégrité et rédaction des ADR (`docs/DECISIONS.md`).<br>• **Génération de Boilerplate** : squelettes des 13 entités Doctrine, énumérations PHP 8.3 typées, contrôleurs CRUD EasyAdmin 4/5.<br>• **Fixtures & Tests** : volumétrie de données réalistes via Faker (~300 exemplaires, 52 usagers), rédaction des cas de tests unitaires, d'intégration et fonctionnels PHPUnit 12.<br>• **Interface & Gabarits** : intégration des maquettes Twig / Bootstrap 5 (catalogue, espace lecteur, guichet express, statistiques). |
| **Documentation Officielle & Moteurs d'inspection** | Validation des spécifications Symfony 7.4, Doctrine ORM 3.8, PHPUnit 12 et directives officielles de l'API Open Library (en-têtes et rate limiting). |

---

## 2. Structure du Dossier et de l'Archive `traces-ia.zip`

L'archive `traces-ia.zip` regroupe l'historique complet et non altéré des échanges, organisé par sessions chronologiques et thématiques :

```
traces-ia/
├── LISEZMOI.md                   # Ce document explicatif et d'analyse critique
├── 2026-11-17-session-1.md       # Session 1 (Étapes 0 à 5 : Initialisation, modèle FRBR, Docker, fixtures, sécurité PHP2Shell)
├── 2026-11-17-session-2.md       # Session 2 (Étapes 6 à 7 : Back-Office EasyAdmin, services du domaine, concurrence MySQL, tests)
├── 2026-11-17-session-3.md       # Session 3 (Étapes 8 à 16 : Auth, Catalogue, Espace adhérent, Open Library, Docker 1-commande, Note de cadrage)
└── plans/                        # Plans d'action détaillés validés avant chaque étape d'exécution
    ├── step_0_scoping_plan.md
    ├── step_1_plan.md
    ├── step_2_plan.md
    ├── step_3_plan.md
    ├── step_4_plan.md
    └── php_8_3_security_upgrade_plan.md
```

> **Garantie d'Assainissement des Secrets** :  
> L'ensemble des traces a fait l'objet d'un audit de sécurité automatisé et manuel. Aucun mot de passe personnel, clé d'API privée, jeton d'accès ou identifiant confidentiel ne figure dans ces documents. Seuls les identifiants publics de démonstration locale (`Admin123!`, `Member123!`) y sont mentionnés.

---

## 3. Trois Enseignements Concrets Tirés de l'Expérience

### Enseignement 1 : Où l'IA a fait gagner un temps précieux (Productivité & Exhaustivité)
* **Contexte** : La mise en place des fondations (Niveau 1) et de la couverture de tests (Niveau 2 à 4) représentait un volume de code considérable (13 entités, 12 CRUD EasyAdmin, fixtures réalistes de 300 exemplaires et 85 tests automatisés).
* **Gain constaté** : L'IA a permis de diviser par 4 le temps nécessaire à la rédaction du boilerplate Symfony 7.4 : création instantanée des attributs Doctrine (`#[ORM\Column]`, `#[ORM\ManyToOne]`), configuration méthodique des enums PHP 8.3, et génération assistée des fixtures Faker avec des titres réels (Victor Hugo, Saint-Exupéry, Hergé) et des codes-barres formatés (`EXP-PARIS-0001`).
* **Bénéfice ingénieur** : Ce temps économisé a été réinvesti là où la valeur ajoutée humaine est irremplaçable : la réflexion sur les règles de concurrence sous MySQL 8 (`REPEATABLE READ`), le calibrage des transactions atomiques et la résolution du modèle FRBR.

---

### Enseignement 2 : Où l'IA s'est trompée (Limites de raisonnement sur les frameworks récents)
* **Cas concret 1 — Requête DQL avec `GROUP BY` sous Doctrine ORM 3** :  
  Lors du calcul des statistiques de consultation (Étape 12), l'IA a proposé la requête DQL suivante :
  ```sql
  SELECT e as edition, COUNT(l.id) as borrowCount 
  FROM App\Entity\Loan l 
  JOIN l.copy c 
  JOIN c.edition e 
  GROUP BY e.id
  ```
  Or, sous Doctrine 3, cette syntaxe déclenche une `QueryException` immédiate car une entité non racine (`e`) ne peut être sélectionnée dans un `SELECT` si l'entité racine (`l`) n'est pas elle-même sélectionnée.
* **Cas concret 2 — Détachement d'entité dans `WebTestCase`** :  
  Dans les tests fonctionnels de contrôleurs (Étape 10 & 11), l'IA a rédigé des assertions en appelant `$em->refresh($loan)` après une soumission de formulaire HTTP. Cependant, chaque requête exécutée par le client WebTestCase réinitialise le conteneur de dépendances et ferme l'EntityManager, rendant l'entité détachée (`Entity has no identity or is detached`).
* **Cas concret 3 — Risque de double allocation dans la file FIFO (Étape 3)** :  
  L'IA avait initialement maintenu les réservations au statut `WAITING` lorsqu'un exemplaire situé sur un autre site leur était assigné mais en cours de transport. Cette conception aurait permis à une restitution simultanée locale d'allouer un second exemplaire à la même réservation.

---

### Enseignement 3 : Comment les erreurs ont été interceptées et corrigées (Méthode & Rigueur)
* **Mécanismes de détection** :
  1. **Exécution systématique de la suite de tests automatisés (`php bin/phpunit`)** : Aucun composant n'a été considéré comme terminé sans exécution complète des tests. C'est l'exécution des tests d'intégration qui a instantanément fait remonter la `QueryException` de Doctrine et les erreurs de détachement d'entités.
  2. **Revue stricte des diffs avant chaque commit (`git diff`)** : Le protocole en 5 temps a permis d'intercepter dès la conception l'ambiguïté de la file FIFO avant même qu'une seule ligne de code ne soit écrite.
* **Actions correctives apportées par l'ingénieur** :
  - *Correction DQL* : Réécriture de la requête d'agrégation en sélectionnant la clé étrangère brute `IDENTITY(c.edition) as editionId`, puis hydratation déterministe via le repository.
  - *Correction WebTestCase* : Remplacement de `$em->refresh()` par une ré-interrogation explicite de la base via le nouvel EntityManager du conteneur réinitialisé (`static::getContainer()->get(EntityManagerInterface::class)->find(...)`).
  - *Correction Conceptuelle FIFO* : Introduction de l'état pivot fondamental **`AWAITING_TRANSFER`** (DEC-003), qui sort immédiatement la réservation de la file d'attente active `status = 'WAITING'` dès qu'un ordre de transport est émis.

---

## 4. Conclusion
L'assistance par IA s'est avérée un démultiplicateur d'efficacité remarquable pour les tâches répétitives et la structuration des tests. Toutefois, la garantie de robustesse, la sécurité des accès concurrents et la cohérence globale du système n'ont pu être obtenues que grâce à une **supervision humaine constante, critique et intransigeante**.
