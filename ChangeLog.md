# CHANGELOG AGEBF FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 3.7 (2026-06-02)

### Nouvelle fonctionnalite : Suivi des paiements SEPA

- Nouvelle page **Suivi des paiements** (menu Helpy → Suivi des paiements)
- Resout le probleme identifie par Philip : dans Dolibarr, un virement SEPA regroupe plusieurs factures mais on ne voyait pas quel pack (lunettes, sport, sante...) etait inclus dans quel virement
- La page part des **virements SEPA** et decompose chacun en detail :
  - Tableau principal : 1 ligne par virement — date, reference SEPA, releve bancaire Belfius (cliquable), statut rapproche, nb factures, montant total
  - **Detail expandable** (bouton triangle) : Tiers + Pack (badge colore) + N° facture + Montant paye pour chaque facture du virement
  - Bandeau stats : nb virements, rapproches/non-rapproches, montants
  - Filtres : annee, statut rapproche (Tous/Oui/Non), recherche par reference SEPA
  - Export CSV : 1 ligne par facture avec tous les champs (Date, Ref SEPA, Releve, Rapproche, Tiers, Pack, Facture, Montant)
  - Liens directs : fiche paiement fournisseur, ecritures bancaires Belfius, fiche facture, fiche Tiers
- ZIP : `module_agebf-3.7.zip`

## 3.6 (2026-06-02)

- Page **Documents** : renommage de fichier desormais disponible pour les admins sur **tous les documents** (Fournie + Manquante), pas seulement les lignes "Manquante"
- Permet a la secretaire d'harmoniser les noms de fichiers meme quand la composition de menage est deja detectee
- ZIP : `module_agebf-3.6.zip`

## 3.5 (2026-05-25)

- Nouvelle page **Suivi des packs** (menu Helpy → Suivi des packs)
  - Liste toutes les factures fournisseurs (packs avantages sociaux) par annee
  - Filtres : annee, statut (Soldee/Partielle/Impayee), pack, tiers, N° facture, communication structuree, montant min/max
  - Badges colores par type de pack
  - Colonne Virement : bouton Preparer virement + lien vers ecritures bancaires
  - Export CSV complet
- ZIP : `module_agebf-3.5.zip`

## 3.2 (2026-05-20)

- Module renomme **Helpy** dans la liste des modules (Configuration → Modules/Applications)
- Menu gauche renomme **Documents Tiers** (plus explicite)
- Indicateurs texte sur la page Documents : **Fournie** / **Manquante** / **Aucun document**
- Filtre Documents corrige : 3 boutons (Tous / Avec composition / Sans composition)
- Visualisation des fichiers directement dans un **popup modal** (80% de l'ecran)
- Renommage de fichier inline pour les admins sur les lignes "Manquante"
- Bouton **Voir** disponible sur tous les Tiers ayant des documents
- ZIP : `module_agebf-3.2.zip`

## 3.1 (2026-05-20)

- Nouvelle page **Documents** (menu Helpy → Documents)
  - Liste tous les Tiers avec le nombre de documents joints
  - Detection de la composition de menage (nom de fichier contenant "compos" ou "menage")
  - Indicateurs visuels OK / Manquant par Tiers
  - Filtre "Sans composition seulement"
  - Lien direct vers la page Documents de chaque Tiers
- Version module mise a jour en 3.1 (refresh general Dolibarr)
- ZIP : `module_agebf-3.1.zip`

## 3.0 (2026-05-18)

- Menu haut renomme **Helpy**
- Icones Font Awesome : fa-child, fa-birthday-cake
- Colonne Parent (Tiers) en premiere position
- Tri alphabetique cliquable sur toutes les colonnes
- ZIP format compatible Dolibarr : module_*-x.y*.zip

## 2.0 (2026-05-12)

### Refonte complète du modèle de données

- Nouveau modèle : **1 Tiers par employé adulte** (27 Tiers) + contacts Fils/Fille liés via `fk_soc`
- Le lien parent→enfant est désormais natif Dolibarr (`fk_soc`) — plus fiable qu'un champ custom `fk_parent`
- Abandon du modèle précédent (1 seul Tiers « Bruxelles Formation » + 58 contacts)

### Nouveaux champs extrafields

- `fete_enfants` (boolean) sur les **contacts** — coché si Fils/Fille < 16 ans
- `fete_enfants` (boolean) sur les **Tiers** — coché si au moins 1 enfant du Tiers qualifie
- `nb_enfants_invites` (int) sur les **Tiers** — nombre d'enfants < 16 ans liés au Tiers

### Changements métier

- Critère d'invitation : âge **strictement < 16** au 1er janvier (les 15 ans sont inclus)
- Poste renommé `'Fils'` / `'Fille'` (remplace `'Enfant'`)
- Calcul de l'âge étendu à **tous les contacts** ayant une date de naissance (pas seulement Fils/Fille)

### Interface

- Page principale : filtre "Invités seulement" / "Tous les enfants"
- **Export CSV** (UTF-8 + BOM Excel) des enfants invités
- Boutons d'action déplacés en **haut de page**
- Colonne "Parent (Tiers)" avec lien direct vers la fiche Tiers
- Colonne "Fonction" affichant `note_public` du Tiers
- Bandeau de statistiques (total, < 16 ans, cases cochées)

### Technique

- Propagation automatique `fete_enfants` et `nb_enfants_invites` vers le Tiers dans le cron
- Suppression de la propagation `fk_parent` (remplacée par `fk_soc`)
- Suppression du menu de navigation principale (moins encombrant)
- Cron quotidien à minuit configurable via Windows Task Scheduler ou crontab Linux

## 1.0 (2026-05-01)

Initial version — calcul âge contacts `poste='Enfant'` au 1er janvier, affichage liste ✔/✘.
