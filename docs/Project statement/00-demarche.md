# PFE Challenge EMC — La démarche

## En deux mots

Vous avez **7 jours** pour construire une application web complète avec **Symfony 7.4** et **MySQL**, sur **un sujet choisi parmi 4**.

Nous évaluons autant **votre manière de travailler** que **le résultat final**. L'IA est autorisée, et nous vous encourageons à nous montrer comment vous l'utilisez.

**Date limite : dimanche 15 novembre 2026 à 23h59 (heure du Maroc).**

---

## Le cadre

- **Durée** : 7 jours, jusqu'au dimanche 15 novembre 2026 à 23h59 (heure du Maroc).
- **Sujet** : vous choisissez **un seul sujet** parmi les 4 proposés :
  1. Billetterie d'une salle de spectacle
  2. Gestion d'un restaurant : carte, cuisine et stocks
  3. Plateforme de covoiturage régional
  4. Gestion d'un réseau de médiathèques
- **Stack imposée** : Symfony 7.4, PHP 8.2 ou plus, MySQL 8, Doctrine ORM et migrations. Le front est libre (Twig, Stimulus/Turbo, ou autre).
- **Travail individuel**, IA autorisée.

---

## Les 5 niveaux

Chaque sujet se découpe en 5 niveaux **cumulatifs**. Allez aussi loin que possible : trois niveaux solides valent mieux que cinq niveaux bâclés.

| Niveau | Thème | En résumé |
|---|---|---|
| **1** | Modéliser | Modèle de données du domaine, schéma MySQL, migrations, données de démonstration, opérations de base |
| **2** | Faire respecter les règles | Règles métier, intégrité des données, transactions, cycles de vie, tests automatisés |
| **3** | En faire un produit | Comptes et rôles, interface utilisable, tableaux de bord |
| **4** | S'ouvrir au monde | Intégration d'une API externe gratuite |
| **5** | Livrer | Environnement Docker prêt à l'emploi et guide d'installation |

### Des sujets volontairement ouverts

Chaque sujet décrit **un besoin**, pas une solution. La conception vous revient : entités, relations, règles, écrans.

Quand un point est ambigu, **tranchez, notez votre hypothèse et avancez**. Chaque sujet se termine par une section « Questions à trancher » : vos réponses, justifiées, font partie de l'évaluation.

### Niveau 5 : ce que nous attendons

- Une seule commande (`docker compose up`, ou une commande documentée) installe et lance l'application complète : PHP/Symfony, serveur web, MySQL.
- Les migrations et les données de démonstration s'appliquent automatiquement ou via une commande unique.
- Un fichier `INSTALL.md` guide pas à pas : prérequis, installation, comptes de démonstration, dépannage courant.
- L'installation est testée sur une machine propre.

---

## Votre dépôt GitHub

- Un dépôt **public**, sur **votre propre compte GitHub**.
- Créé **dès le premier jour**, avec un premier commit dès le squelette Symfony.
- Contenu attendu :
  - `README.md` : sujet choisi, niveaux atteints, ce qui fonctionne, ce qui reste à faire, comment lancer le projet ;
  - `docs/MODELE.md` : modèle de données (diagramme Mermaid ou image) et explication des choix ;
  - `docs/DECISIONS.md` : journal daté de vos décisions et hypothèses, dont vos réponses aux « Questions à trancher » ;
  - `tests/` : vos tests automatisés ;
  - `.env.example` : variables nécessaires, sans aucun secret ;
  - `INSTALL.md` (niveau 5).

---

## L'historique Git fait partie du livrable

Nous lisons votre historique comme un journal de bord : il doit raconter le projet du premier au dernier jour.

- **Commits fréquents et ciblés**, avec des messages explicites.
- **Le code produit avec l'IA est commité au fil de l'eau**, comme le reste. Vous pouvez le signaler (préfixe dans le message, trailer `Co-authored-by`, etc.).
- **Historique intact** sur la branche principale : conservez chaque commit tel quel, sans squash ni réécriture (`rebase`, `push --force`).

Un historique complet est une condition pour que votre travail soit évalué.

---

## La note conceptuelle

Joignez à votre rendu une **note d'une page** (PDF) expliquant **comment vous avez abordé le développement de ce projet** :

- pourquoi ce sujet ;
- comment vous avez compris et découpé le besoin ;
- vos grands choix de conception et d'architecture ;
- l'ordre dans lequel vous avez avancé, et pourquoi ;
- la place de l'IA dans votre façon de travailler ;
- ce que vous feriez avec une semaine de plus.

Une page, pas davantage : la capacité à synthétiser fait partie de l'exercice.

---

## L'usage de l'IA : points bonus

Utilisez les outils IA de votre choix : assistants conversationnels, agents de code, IDE augmentés…

Ce qui nous intéresse : **comment vous les pilotez**. Ce que vous demandez, comment vous vérifiez, ce que vous corrigez ou refusez.

Pour obtenir les points bonus, joignez à votre rendu **une archive ZIP de vos traces d'usage de l'IA** : conversations, sessions, prompts, fichiers de configuration d'agents, journaux… **À vous de trouver comment exporter ces traces** selon les outils utilisés : cela fait partie de l'exercice.

Ajoutez dans l'archive un court `LISEZMOI.md` :
- les outils utilisés et à quoi ;
- le contenu de l'archive ;
- vos 3 enseignements : où l'IA vous a fait gagner du temps, où elle s'est trompée, et comment vous l'avez repéré.

Avant l'envoi, retirez de l'archive et du dépôt toute clé d'API, tout mot de passe et toute donnée personnelle.

---

## Ce que nous évaluons

| Bloc | Critère | Points |
|---|---|---|
| **Méthode** | Historique Git | 15 |
| | Note conceptuelle | 10 |
| | Modèle de données et décisions documentées | 10 |
| | Tests automatisés | 10 |
| **Résultat** | Niveau 1 — Modéliser | 15 |
| | Niveau 2 — Règles | 15 |
| | Niveau 3 — Produit | 10 |
| | Niveau 4 — API externe | 7 |
| | Niveau 5 — Livrer | 8 |
| **Total** | | **100** |
| **Bonus** | Traces d'usage de l'IA : richesse, esprit critique, pertinence | **+15** |

Les candidats retenus seront invités à un échange de 20 à 30 minutes pour présenter leur projet.

---

## Le rendu

Au plus tard le **dimanche 15 novembre 2026 à 23h59 (heure du Maroc)**, envoyez à **pfe@educationmedia.ma** un email contenant :

1. le lien de votre dépôt GitHub public ;
2. le sujet choisi ;
3. votre note conceptuelle d'une page (PDF) ;
4. l'archive ZIP de vos traces IA (bonus), en pièce jointe ou par lien de téléchargement.

Nous prenons en compte le dernier commit poussé avant la date limite.

---

## Quelques conseils

- Lisez votre sujet en entier avant d'écrire la première ligne de code.
- Commencez par le modèle de données : c'est le socle de tous les niveaux.
- Utilisez des données de démonstration réalistes (DoctrineFixturesBundle ou équivalent) : elles rendent vos démonstrations parlantes.
- Poussez tôt et souvent.

Bon challenge !
