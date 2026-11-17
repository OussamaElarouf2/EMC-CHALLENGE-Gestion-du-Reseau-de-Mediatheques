# Guide des Traces d'Utilisation de l'IA (LISEZMOI.md)
**Challenge PFE 2026 — Education Media Company (EMC)**  
**Candidat** : Oussama El Arouf | **Date** : 17 Novembre 2026  
**Livrable Bonus (+15 points)** : Traces d'utilisation de l'intelligence artificielle

---

## 1. Outils d'IA Utilisés & Usages Spécifiques

Dans le cadre du développement de ce projet, les outils d'assistance par intelligence artificielle ont été utilisés comme un accélérateur, sous contrôle et validation systématique du candidat :

| Outil / Modèle | Usage Précis dans le Projet |
|---|---|
| **Antigravity CLI (Moteur Google Gemini Flash)** | • **Modélisation & Structure** : aide à la formalisation du schéma de la base de données (les 13 entités) et rédaction des justifications de conception.<br>• **Génération de Code Répétitif** : écriture rapide des classes d'entités, des contrôleurs d'administration et des gabarits Twig / Bootstrap 5.<br>• **Jeux de Données (Fixtures)** : création d'un jeu d'essai réaliste avec Faker (~300 livres/DVD, 52 adhérents avec différents forfaits).<br>• **Écriture des Tests** : proposition des squelettes de tests automatisés (PHPUnit 12) pour vérifier les règles métier. |
| **Documentation Officielle** | Vérification des règles Symfony 7.4, de PHP 8.3 et des recommandations de l'API Open Library. |

---

## 2. Structure du Dossier et de l'Archive `traces-ia.zip`

L'archive `traces-ia.zip` contient l'ensemble des échanges et plans d'action du projet :

```
traces-ia/
├── LISEZMOI.md                   # Ce document explicatif et d'analyse critique
├── 2026-11-17-session-1.md       # Session 1 : Lancement, modèle de données, Docker, fixtures et sécurité PHP
├── 2026-11-17-session-2.md       # Session 2 : Back-office d'administration, règles métier et tests
├── 2026-11-17-session-3.md       # Session 3 : Catalogue public, espace adhérent, API Open Library et livraison
└── plans/                        # Plans d'action détaillés préparés avant chaque étape
    ├── step_0_scoping_plan.md
    ├── step_1_plan.md
    ├── step_2_plan.md
    ├── step_3_plan.md
    ├── step_4_plan.md
    └── php_8_3_security_upgrade_plan.md
```

> **Garantie d'Assainissement des Secrets** :  
> Toutes les traces ont été nettoyées. Aucun mot de passe personnel, clé privée ou secret confidentiel ne figure dans ces fichiers. Seuls les comptes de test locaux nécessaires à l'évaluation (`Admin123!`, `Member123!`) sont présents.

---

## 3. Trois Enseignements Concrets Tirés de l'Expérience

### Enseignement 1 : Où l'IA a fait gagner un temps précieux (Génération de code et de données)
* **Ce qui prenait beaucoup de temps** : Créer manuellement 13 tables en base de données avec tous leurs champs, écrire les formulaires d'administration, et inventer 300 faux livres avec des auteurs, des titres réels et des codes-barres pour tester l'application.
* **Le gain apporté par l'IA** : L'IA a généré tout ce code de base en quelques minutes. Elle a aussi créé des fixtures très réalistes avec de vrais titres (*Le Petit Prince*, *Tintin*, etc.) et plusieurs profils d'utilisateurs (adhérent standard, adhérent en retard, etc.).
* **Ce que cela m'a permis de faire** : Au lieu de perdre des heures à taper du code répétitif, j'ai pu me concentrer sur les règles importantes demandées par le sujet : comment calculer les pénalités de retard, comment gérer les quotas de prêt, et comment faire fonctionner les deux médiathèques ensemble.

---

### Enseignement 2 : Où l'IA s'est trompée (Erreurs logiques et petits pièges)
Voici 3 exemples simples et concrets où l'IA a fait des erreurs :

1. **Le piège logique de la réservation (Risque de donner deux livres à la même personne)** :
   * *L'erreur de l'IA* : Quand un adhérent réservait un livre disponible uniquement dans l'autre médiathèque, l'IA laissait sa réservation au statut « En attente ».
   * *Pourquoi c'était un problème* : Si un autre exemplaire du même livre était rendu au guichet entre-temps, le système croyait que la personne attendait toujours un livre et lui attribuait un deuxième exemplaire !
2. **L'inversion de paramètres dans le code** :
   * *L'erreur de l'IA* : En écrivant du code pour créer un abonnement ou un prêt, l'IA a parfois inversé l'ordre des arguments (par exemple en mettant le nombre maximum de prêts à la place de la durée d'emprunt en jours).
   * *Pourquoi c'était un problème* : PHP générait une erreur de type immédiate car il attendait un texte et recevait un nombre, ou appliquait les mauvaises limites.
3. **Le calcul injuste du délai de retrait de 5 jours (Décision de conception)** :
   * *L'erreur de l'IA* : Quand un document réservé était rendu dans une médiathèque mais devait être transféré à l'autre site pour être retiré, l'IA lançait le compte à rebours des 5 jours dès la première restitution au guichet de départ.
   * *Pourquoi c'était une mauvaise décision* : Si la navette mettait 2 jours pour acheminer le livre, l'adhérent n'avait plus que 3 jours pour venir le chercher au lieu des 5 jours promis ! C'était pénalisant et injuste pour l'usager.

---

### Enseignement 3 : Comment j'ai détecté et corrigé ces erreurs
* **Pour le piège de la réservation** :
  * *Comment je l'ai repéré* : En relisant le sujet et en réfléchissant au parcours du document entre les deux médiathèques. J'ai remarqué qu'il manquait une étape intermédiaire pendant le voyage du document.
  * *Comment je l'ai corrigé* : J'ai demandé d'ajouter un état intermédiaire clair : « En attente de transfert » (`AWAITING_TRANSFER`). Dès qu'un livre commence son transport, la réservation passe dans cet état et quitte la file d'attente. Ainsi, aucun autre livre ne peut lui être attribué par erreur.
* **Pour les inversions de paramètres** :
  * *Comment je l'ai repéré* : Très facilement grâce à la commande de test `php bin/phpunit`. Le terminal m'indiquait en rouge le fichier exact et la ligne où l'erreur se trouvait avec le message explicatif de PHP.
  * *Comment je l'ai corrigé* : En ouvrant la classe concernée pour vérifier l'ordre exact attendu dans le constructeur, puis en remettant les arguments dans le bon ordre.
* **Pour le délai de retrait de 5 jours** :
  * *Comment je l'ai repéré* : En me mettant à la place de l'adhérent qui attend son livre. J'ai compris qu'on ne pouvait pas faire démarrer un délai avant que le document ne soit réellement disponible entre les mains de l'adhérent.
  * *Comment je l'ai corrigé* : J'ai imposé que le compte à rebours des 5 jours ne commence qu'au moment précis où le bibliothécaire du site de retrait réceptionne le colis et passe la réservation au statut « Prêt » (`READY`). Pendant tout le transport, l'exemplaire est marqué « En transit » sans entamer le délai de l'usager.

---

## 4. Conclusion
L'IA est une aide formidable pour aller vite sur le code de base et la syntaxe. Mais elle ne remplace pas la vigilance du développeur : il faut vérifier ce qu'elle écrit, tester chaque fonctionnalité pas à pas avec les tests automatisés, et garder le bon sens métier pour que les règles de l'application restent justes et cohérentes.
