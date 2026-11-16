# Journal des Décisions d'Architecture (ADR)

Ce document consigne de manière datée l'ensemble des décisions d'architecture, des arbitrages techniques et des réponses argumentées aux « Questions à trancher » du sujet.

---

## [2026-11-16] DEC-001 : Conventions linguistiques et vocabulaire du domaine

### Contexte
Le projet est développé dans le cadre du Challenge PFE EMC évalué par un jury francophone. Il est nécessaire d'établir dès le départ des conventions claires pour la langue du code, de la base de données, des interfaces et des documents de restitution, ainsi qu'un lexique commun.

### Décision
1. **Anglais** retenu pour :
   - Le code source (noms de classes, interfaces, méthodes, propriétés, constantes, enums).
   - Le schéma relationnel (tables, colonnes, contraintes, migrations).
   - Les tests automatisés (noms de méthodes de test, assertions).
   - Les messages de commits Git (convention Conventional Commits).
   - Le plan de développement (`plan.md`) et les notes d'échanges techniques (`docs/ai-log/`).
2. **Français** retenu pour :
   - L'interface utilisateur finale (textes des vues Twig, formulaires, messages flash, back-office).
   - Les documents d'évaluation et de cadrage (`README.md`, `INSTALL.md`, `docs/MODELE.md`, `docs/DECISIONS.md`, note conceptuelle 1 page PDF).
3. **Lexique bilingue du domaine** :
   - Œuvre $\rightarrow$ `Work`
   - Édition $\rightarrow$ `Edition`
   - Exemplaire $\rightarrow$ `Copy`
   - Adhérent $\rightarrow$ `Member`
   - Formule d'abonnement $\rightarrow$ `Plan`
   - Prêt $\rightarrow$ `Loan`
   - Réservation $\rightarrow$ `Reservation`
   - Pénalité $\rightarrow$ `Penalty`
   - Site / Médiathèque $\rightarrow$ `Branch`
   - Transfert inter-sites $\rightarrow$ `Transfer`

### Conséquences
- Lisibilité et maintenabilité conformes aux standards de développement Symfony/PHP.
- Compréhension immédiate pour les évaluateurs francophones lors des démonstrations et de la relecture de la documentation.
