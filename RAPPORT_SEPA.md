# Rapport de stage — Module Helpy pour Dolibarr ERP/CRM

**Projet :** Dolibarr ERP/CRM — module personnalisé « AgeBF / Helpy » pour Bruxelles Formation  
**Auteur :** Soufiane Achraa — Stage 2026 — TECHGEST ICCBXL  
**Version livrée :** 5.2  
**Date de mise à jour :** 15 juin 2026

---

## 1. Contexte et besoin

Bruxelles Formation gère les **avantages sociaux** de ses employés via Dolibarr ERP : packs santé, lunettes, sport, hospitalisation, naissance, etc. Chaque pack est représenté par une **facture fournisseur** réglée en **virements SEPA groupés** via Belfius.

Quatre difficultés métier ont motivé la création du module :

1. **Fête des enfants** — identifier chaque année les enfants d'employés éligibles (< 16 ans au 1er janvier) sans saisie manuelle.
2. **Documents RH** — suivre les compositions de ménage remises par chaque employé.
3. **Préparation des virements fastidieuse** — créer manuellement les demandes de virement, une par une, avant de générer le fichier SEPA.
4. **Rapprochement bancaire opaque** — un virement Belfius regroupe plusieurs factures en une seule ligne globale. Dolibarr natif ne montre pas quel pack est inclus dans quel virement. Pointer le relevé relevait du travail manuel incertain.

À cela s'est ajouté un **besoin de gestion des assurances complémentaires** (hospi. / dentaire) par contact, avec synchronisation depuis le numéro Assurcard.

---

## 2. Solution livrée — cinq pages sous le menu Helpy

### 2.1 Fête des enfants

- Cron quotidien : calcul de l'âge de tous les contacts ayant une date de naissance.
- Les contacts `poste = 'Fils'` ou `'Fille'` avec âge < 16 ans reçoivent la case **"Fête des enfants" cochée**.
- Le Tiers parent reçoit automatiquement `fete_enfants = 1` et `nb_enfants_invites = N`.
- Page avec filtre « Invités seulement / Tous » et export CSV UTF-8 + BOM Excel.

### 2.2 Documents Tiers

- Liste tous les Tiers avec le nombre de fichiers joints.
- Détection automatique de la **composition de ménage** (nom de fichier contenant "composition", "compostion" ou "ménage").
- Indicateurs **Fournie / Manquante / Aucun document** ; filtre en 3 boutons.
- Visualisation dans un popup modal (80 % de l'écran), renommage inline (admin), upload intégré pour les Tiers sans document.
- Toute opération met à jour `llx_ecm_files` (onglet Documents natif Dolibarr).

### 2.3 Paiements à effectuer

- Tableau de bord des factures de packs par année (Soldées / Partielles / Impayées ; Attendu / Reçu / Reste).
- **Bouton « Préparer le lot de virements SEPA »** : en **un clic**, crée les demandes de virement (`demande_prelevement()`) pour toutes les factures impayées de l'année, puis ouvre l'écran standard Dolibarr de génération SEPA.
- **Garde-fou volontaire** : le bouton *prépare* mais **ne génère pas** le fichier SEPA. La vérification, le choix du compte/date et la génération restent un geste humain.
- Tri cliquable sur toutes les colonnes ; filtres multicritères ; export CSV.

### 2.4 Rapprochement bancaire

Résout le problème SEPA : un virement bancaire regroupe plusieurs factures mais le relevé Belfius n'affiche qu'une ligne globale.

- **Filtre Toutes / Clients / Fournisseurs** : la page couvre désormais à la fois les factures fournisseurs (packs SEPA) et les factures clients.
- **Import Belfius (scénario B)** — rapprochement à deux niveaux :
  - **Sûr (réf. SEPA)** : la communication Belfius contient `FICHIER : DOL/AAAAMMJJ/CTxx`, exactement le `MsgId` du fichier SEPA généré par Dolibarr. Correspondance au texte exact, zéro ambiguïté.
  - **Repli automatique** : montant total + date si le `MsgId` n'est pas encore enregistré.
- **Rapprochement inline** : saisir le N° de relevé Belfius sur chaque virement et cliquer **Rapprocher** — met à jour `llx_bank.rappro = 1` et `llx_bank.num_releve`.
- Tri cliquable sur toutes les colonnes ; export CSV par facture.

### 2.5 Assurances

- Liste tous les Tiers avec leurs contacts dépliables au clic.
- Cases à cocher **Assurance Hospi.** et **Assurance Dentaire** par contact.
- **Bouton Sync Assurcard** : coche automatiquement Hospi. pour tous les contacts ayant un N° Assurcard renseigné.
- Mise en évidence (fond jaune) des contacts Assurcard sans Hospi. cochée.
- **Filtre Tiers archivés** : les Tiers inactifs (employés décédés ou partis) sont masqués par défaut. Bouton « Afficher les archivés » avec compteur ; badge ⚰ + opacité réduite sur les lignes archivées.
- Bandeau de statistiques : nb Tiers, contacts, Hospi., Dentaire, Assurcard, Assurcard sans Hospi.

---

## 3. Points techniques clés

| Aspect | Détail |
|---|---|
| **Aucune modification du cœur Dolibarr** | La solution s'appuie à 100 % sur les APIs existantes et sur une table additive propre au module (`llx_agebf_lot_sepa`). La désactivation du module est sans effet sur le cœur. |
| **Clé de rapprochement SEPA** | `MsgId = DOL/AAAAMMJJ/CTxx`, stocké dans `llx_agebf_lot_sepa` à la génération du fichier. |
| **Extrafields** | 5 champs sur `llx_socpeople_extrafields` (`age_1jan`, `fete_enfants`, `fk_parent`, `assurance_hospi`, `assurance_dentaire`) créés automatiquement à l'activation. Contrainte `UNIQUE KEY` sur `fk_object` pour garantir l'intégrité des upserts (`INSERT … ON DUPLICATE KEY UPDATE`). |
| **Défense SQL** | `SHOW COLUMNS` avant tout `JOIN` sur les colonnes extrafields potentiellement absentes — évite les erreurs `Unknown column` si le module n'a pas encore été réactivé après une mise à jour. |
| **Filtre Tiers inactifs** | `AND s.status = 1` dans la requête principale d'Assurances, conditionnel à `$show_inactifs`. Sort SQL `ORDER BY s.status DESC` pour afficher les archivés en bas de liste. |
| **Séparation client/fournisseur** | Deux tableaux PHP distincts (`$factures` et `$factures_client`) pour éviter les collisions de `rowid` entre `llx_facture` et `llx_facture_fourn` qui démarrent toutes deux à 1. |
| **Pré-requis SEPA** | Compte émetteur avec IBAN/BIC valides ; chaque Tiers fournisseur doit disposer d'un IBAN par défaut. |

---

## 4. État d'avancement (v5.2)

| Fonctionnalité | État |
|---|---|
| Fête des enfants (calcul âge + cron + export CSV) | ✅ Livré |
| Documents Tiers (composition de ménage, upload, renommage) | ✅ Livré |
| Paiements à effectuer (factures packs, filtres, export CSV) | ✅ Livré |
| Bouton Préparer lot SEPA (1 clic) | ✅ Livré |
| Rapprochement bancaire — factures fournisseurs | ✅ Livré |
| Import Belfius scénario B (réf. SEPA + repli montant/date) | ✅ Livré |
| Rapprochement bancaire — factures clients | ✅ Livré (v5.1) |
| Assurances — Hospi. / Dentaire / Sync Assurcard | ✅ Livré (v5.1) |
| Assurances — Filtre Tiers archivés / décédés | ✅ Livré (v5.2) |
| Tri cliquable sur toutes les colonnes (les deux pages) | ✅ Livré |
| Test de génération SEPA en navigateur | À valider en production |

---

## 5. Points à confirmer pour la mise en production

- **Configuration SEPA** : vérifier que le compte émetteur et les **IBAN des fournisseurs** sont bien renseignés (sinon la génération est bloquée).
- **Source de la communication structurée** (OGM `+++…+++`) : confirmer qui la remplit à la création des factures.
- **Vérification Philip** : confirmer que le `CTxx` du relevé Belfius correspond bien au `MsgId` du fichier généré.
- **Backfill `llx_agebf_lot_sepa`** : script à écrire pour alimenter la table à partir des fichiers XML SEPA déjà générés dans les cycles précédents.
- **Assurances en production** : le champ `assurcard` doit être renseigné dans Dolibarr pour bénéficier de la sync automatique.

---

## 6. Bugs corrigés en cours de développement

| Bug | Cause | Correction |
|---|---|---|
| `Unknown column 'ef.beneficiaire'` | La colonne extrafield n'était pas toujours présente | `SHOW COLUMNS` défensif avant le `JOIN` |
| Liens écritures bancaires Belfius cassés | `account=1` au lieu de `account=2` | Corrigé dans `agebf_packs.php` (×2) et `agebf_compta.php` (×1) |
| Bug PHP 8 — `count(): null given` | Variable locale `$conf` (niveau de confiance) écrasait l'objet global `$conf` de Dolibarr | Renommée `$confiance` |
| Contacts en doublon dans la page Assurances | Table `llx_socpeople_extrafields` sans contrainte `UNIQUE KEY` sur `fk_object` — les scripts de test inséraient plusieurs lignes par contact | Suppression des doublons + `ALTER TABLE … ADD UNIQUE KEY` |

---

## 7. Conclusion

Le module couvre désormais **l'ensemble du cycle RH et financier** de Bruxelles Formation :

- Identification des enfants éligibles → **Fête des enfants**
- Suivi des documents → **Documents Tiers**
- Préparation des virements → **Paiements à effectuer**
- Exécution Belfius → **Rapprochement bancaire** (fournisseurs SEPA + clients)
- Suivi des couvertures assurance → **Assurances** (Hospi. / Dentaire)

**Principe central respecté :** aucune table ni fichier standard de Dolibarr n'est modifié. Le module s'active et se désactive proprement, sans impact sur le cœur.
