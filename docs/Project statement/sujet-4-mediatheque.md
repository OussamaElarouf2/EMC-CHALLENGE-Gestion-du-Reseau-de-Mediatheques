# Sujet 4 — Gestion d'un réseau de médiathèques

## Contexte

Un réseau de deux médiathèques de quartier gère environ 8 000 documents (livres, bandes dessinées, DVD) et 1 500 adhérents. Le réseau souhaite un nouvel outil, utilisable par les bibliothécaires comme par les adhérents.

## Ce que veut le client

- Gérer le catalogue : œuvres, éditions, et exemplaires répartis entre les deux sites.
- Gérer les adhérents et leurs formules d'abonnement.
- Gérer les prêts, retours, prolongations et réservations.
- Appliquer des pénalités de retard.
- Faire circuler les exemplaires d'un site à l'autre.

---

## Niveau 1 — Modéliser

Concevez le modèle de données du domaine, créez les migrations et des données de démonstration réalistes (quelques centaines de documents, des adhérents, des prêts en cours et passés). Proposez un back-office permettant de gérer les principaux objets.

## Niveau 2 — Faire respecter les règles

- Nombre de prêts simultanés et durée de prêt selon la formule d'abonnement.
- Réservations avec file d'attente : le premier réservant est servi au retour du document.
- Une prolongation est accordée seulement quand personne n'attend le document.
- Pénalités de retard, et gestion des exemplaires perdus ou abîmés.
- Tests automatisés couvrant ces règles.

## Niveau 3 — En faire un produit

- Comptes et rôles : adhérent, bibliothécaire, administrateur.
- Catalogue public avec recherche.
- Espace adhérent : prêts en cours, réservations, historique.
- Statistiques : taux de rotation, documents les plus empruntés, documents jamais empruntés.

## Niveau 4 — S'ouvrir au monde

Utilisez l'API **Open Library** (gratuite) pour créer une fiche à partir d'un simple ISBN (titre, auteurs, éditeur, couverture), à l'unité et par lot.

## Niveau 5 — Livrer

Environnement Docker prêt à l'emploi et `INSTALL.md` (voir la démarche).

---

## Questions à trancher

Répondez-y dans `docs/DECISIONS.md`, en justifiant vos choix.

- Deux éditions d'un même roman sont-elles la même œuvre ?
- Un adhérent peut-il réserver un document disponible uniquement dans l'autre site ?
- Combien de temps un document réservé reste-t-il mis de côté une fois revenu ?
- Comment représenter les auteurs multiples, les traducteurs, les illustrateurs ?
- Que faire quand Open Library renvoie des données incomplètes ou différentes de celles du catalogue ?
