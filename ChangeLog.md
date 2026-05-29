# CHANGELOG AGEBF FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 3.3 (2026-05-21)

### Nouvelle page — Suivi des packs ASBL

- Nouvelle entrée de menu **Suivi des packs** (Helpy → Suivi des packs)
- Page centralisant les factures liées aux packs vendus par l'ASBL partenaire de Bruxelles Formation
- Packs disponibles : **Mutuelle**, **Hospitalisation**, **Lunettes**, **Dentaire**
- Un Tiers peut avoir plusieurs packs à son nom (ex. un pack par membre de la famille)
- **Bénéficiaire** du pack distinct du Tiers payeur (extrafield sur la facture)
- **Communication structurée belge (OGM)** au format `+++NNN/NNNN/NNNNN+++` — extrafield sur la facture
- Suivi du paiement en 3 versements : **Soldée** / **Partielle** / **Impayée**
- Lien direct vers la fiche facture Dolibarr et vers le mouvement bancaire SEPA
- Filtre par **année** et par **statut de paiement**
- Recherche libre (Tiers, pack, OGM, bénéficiaire)
- Bandeau de statistiques : total factures / soldées / partielles / impayées / montant attendu vs reçu
- Légende colorée par type de pack (badge couleur)
- ZIP : `module_agebf-3.3.zip`

## 3.2 (2026-05-21)

### Page Documents — Tiers

- Module renomme **Helpy** dans la liste des modules (Configuration → Modules/Applications)
- Menu gauche renomme **Documents Tiers** (plus explicite)
- Indicateurs texte : **Fournie** (vert) / **Manquante** (orange) / **Aucun document** (rouge)
- Filtre 3 boutons : **Tous les Tiers** / **Avec composition** / **Sans composition**
- Bandeau de statistiques etendu : Total / Fournie / Manquante / Aucun document
- Visualisation des fichiers dans un **popup modal** (80 % de l'ecran) avec bouton Voir
- Renommage de fichier inline pour les admins (lignes "Manquante") avec mise a jour `llx_ecm_files`
- Bouton **Voir** sur tous les Tiers ayant des documents (composition valide ou non)
- **Bouton Ajouter document** pour les Tiers sans aucun fichier joint :
  - Formulaire d'upload integre directement dans le tableau
  - Deplacement du fichier vers `documents/societe/[id]/`
  - Insertion automatique dans `llx_ecm_files` (visible dans l'onglet natif Dolibarr)
  - Detection immediate : si le nom contient "composition" ou "menage" → statut **Fournie** a la validation
- Correction POST-Redirect-GET sur renommage et upload (supprime le dialogue "Confirmer le renvoi")
- Regex de detection renforcee : `/composi?tion|m[ee]nage/i` (tolerant les fautes de frappe)
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
