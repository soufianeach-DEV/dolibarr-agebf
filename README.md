# Module AgeBF — Dolibarr ERP CRM

> Module custom développé dans le cadre d'un stage chez **Bruxelles Formation**.

**Auteur :** Soufiane Achraa — Stage 2026 — TECHGEST ICCBXL  
**Version :** 1.0  
**Compatibilité :** Dolibarr 19+, PHP 8.0+

---

## Objectif

Calculer automatiquement l'âge des contacts de type **Enfant** au 1er janvier de l'année en cours, et déterminer lesquels sont invités à la **fête des enfants** de Bruxelles Formation.

**Règle métier :** un enfant est invité s'il a **moins de 15 ans** au 1er janvier de l'année en cours.

---

## Fonctionnement

1. À l'activation du module, un champ `age_1jan` est automatiquement créé sur les contacts (extrafield)
2. Une tâche planifiée (cron) calcule chaque jour l'âge de tous les contacts ayant `poste = 'Enfant'` et une date de naissance renseignée
3. Le résultat est visible dans la page principale du module **AgeBF** dans Dolibarr

---

## Structure du module

```
agebf/
├── agebfindex.php                    # Page principale : liste des enfants + statut invitation
├── core/
│   └── modules/
│       └── modAgeBF.class.php        # Descripteur du module (cron, extrafield, menu)
├── class/
│   └── agebf.class.php               # Logique de calcul des âges
├── admin/
│   ├── setup.php                     # Page de configuration du module
│   └── about.php                     # Page À propos
├── sql/
│   ├── dolibarr_allversions.sql      # Script SQL chargé à l'activation (vide)
│   ├── insert_data_test.sql          # Données de test à charger via phpMyAdmin
│   ├── import_tiers.csv              # CSV import Tiers (Bruxelles Formation)
│   └── import_contacts.csv          # CSV import contacts
├── langs/
│   └── en_US/
│       └── agebf.lang                # Fichier de traduction
├── lib/
│   └── agebf.lib.php                 # Fonctions utilitaires
└── data_test.sql                     # Sauvegarde données de test (non chargé auto)
```

---

## Installation

### Prérequis

- Dolibarr 19+ installé et fonctionnel
- PHP 8.0+
- Modules **Tiers** et **Contacts/Adresses** activés dans Dolibarr

### Étape 1 — Copier le module

Copier le dossier `agebf/` dans le répertoire `htdocs/custom/` de votre installation Dolibarr :

```
htdocs/
└── custom/
    └── agebf/      ← coller ici
```

### Étape 2 — Activer le module

1. Se connecter à Dolibarr en tant qu'administrateur
2. Aller dans **Accueil → Configuration → Modules/Applications**
3. Rechercher **AgeBF** et cliquer sur **Activer**
4. Le champ `age_1jan` est créé automatiquement sur les contacts
5. Le cron apparaît dans **Outils → Travaux planifiés**

### Étape 3 — Charger les données de test

Ouvrir **phpMyAdmin** (`http://localhost/phpmyadmin`), sélectionner la base `dolibarr`, onglet **SQL**, puis coller et exécuter le contenu de [`sql/insert_data_test.sql`](sql/insert_data_test.sql).

Cela crée :
- 1 Tiers : **Bruxelles Formation**
- 20 contacts : 10 collaborateurs (postes réels BF) + 10 enfants avec dates de naissance

---

## Utilisation

### Lancer le calcul des âges

1. Aller dans **Outils → Travaux planifiés**
2. Cliquer sur le cron **"Calcul âge contacts au 1er janvier"**
3. Cliquer sur **"Activer la planification"** puis **"Lancer maintenant"**

### Voir les résultats

Cliquer sur le menu **AgeBF** dans la barre de navigation.

La page affiche :

| Colonne | Description |
|---|---|
| Nom / Prénom | Identité de l'enfant |
| Date de naissance | Birthday enregistré dans le contact |
| Âge au 1er janvier | Calculé par le cron |
| Invité(e) | ✔ Oui si âge < 15 ans — ✘ Non sinon |

---

## Résultat attendu (données de test, référence 1er janvier 2026)

| Enfant | Date de naissance | Âge | Invité |
|--------|-------------------|-----|--------|
| Zoé Lecomte | 2016-02-14 | 9 ans | ✔ Oui |
| Lucas Martin | 2015-01-08 | 10 ans | ✔ Oui |
| Mathis Dubois | 2014-04-17 | 11 ans | ✔ Oui |
| Nathan Lecomte | 2013-06-05 | 12 ans | ✔ Oui |
| Thomas Dupont | 2012-03-15 | 13 ans | ✔ Oui |
| Hugo Bernard | 2011-09-20 | 14 ans | ✔ Oui |
| Camille Dubois | 2011-12-15 | 14 ans | ✔ Oui |
| Chloé Martin | 2010-11-30 | 15 ans | ✘ Non |
| Emma Dupont | 2009-12-07 | 16 ans | ✘ Non |
| Léa Bernard | 2008-12-03 | 17 ans | ✘ Non |

---

## Automatisation

### Exécution manuelle (tests)

Outils → Travaux planifiés → **Lancer maintenant**

### Windows — Task Scheduler (production locale)

1. Ouvrir le **Planificateur de tâches** (`taskschd.msc`)
2. **Créer une tâche de base**
3. Configurer :
   - **Nom :** AgeBF Calcul âges Dolibarr
   - **Déclencheur :** Tous les jours à **00h05**
   - **Action :** Démarrer un programme
   - **Programme :** `C:\xampp\php\php.exe`
   - **Arguments :** `C:\xampp\htdocs\dolibarr\htdocs\cron\run.php`
4. Terminer

### Linux / Serveur (production)

Ajouter dans la crontab (`crontab -e`) :

```bash
5 0 * * * php /var/www/html/dolibarr/htdocs/cron/run.php
```

---

## Licences

- Code source : **GPLv3**
- Documentation : **GFDL**
