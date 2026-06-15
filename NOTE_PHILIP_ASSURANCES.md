# Note pour Philip — Page Assurances (Helpy v5.2)

**Date :** 15 juin 2026  
**Auteur :** Soufiane Achraa  
**Accès :** Menu Helpy → Assurances

---

## Ce que fait la page

La page **Assurances** centralise le suivi des assurances complémentaires
(hospitalisation et dentaire) pour tous les contacts liés aux Tiers
de Bruxelles Formation.

Vous pouvez :
- Voir d'un coup d'œil combien de contacts ont l'assurance Hospi. / Dentaire
- Cocher / décocher les cases directement dans la page
- Synchroniser automatiquement l'assurance Hospi. depuis le numéro Assurcard
- Masquer les employés décédés ou partis (Tiers archivés)

---

## Les cas que vous verrez dans les données de test

Le script de test a réparti les contacts en **8 scénarios** qui couvrent
toutes les combinaisons possibles :

| Sc. | Hospi. | Dentaire | N° Assurcard | Ce que vous voyez |
|-----|--------|----------|--------------|-------------------|
| 1   | ✓      |          | —            | Case Hospi. cochée seulement |
| 2   |        | ✓        | —            | Case Dentaire cochée seulement |
| 3   | ✓      | ✓        | —            | Les deux cases cochées |
| 4   |        |          | —            | Aucune case cochée |
| 5   | ✓      |          | BEL-XXXX     | Assurcard + Hospi. déjà synced |
| 6   |        |          | BEL-XXXX     | ⚠️ **Fond jaune** — Assurcard sans Hospi. |
| 7   | ✓      | ✓        | BEL-XXXX     | Assurcard + les deux cases |
| 8   |        | ✓        | BEL-XXXX     | ⚠️ **Fond jaune** — Assurcard sans Hospi. |

> Les scénarios **6 et 8** déclenchent le surlignage jaune dans la page
> et font apparaître le bouton **"Synchroniser assurcard → Hospi."**

---

## Comment tester chaque fonctionnalité

### 1. Voir les contacts dépliés

Cliquez sur le triangle ▶ devant un nom de Tiers pour voir ses contacts.
Ou utilisez **Déplier tout** pour tout ouvrir en une fois.

Le résumé par Tiers affiche : `✓ 3/6` (ex. : 3 contacts sur 6 ont Hospi.).

---

### 2. Cocher / décocher une assurance

- Déplier un Tiers
- Cocher ou décocher les cases Hospi. ou Dentaire
- Cliquer **Enregistrer les assurances** en bas de page

➡️ Toutes les modifications sont sauvegardées en une seule fois.

---

### 3. Bouton "Synchroniser assurcard → Hospi."

Ce bouton apparaît automatiquement quand des contacts ont un **N° Assurcard
renseigné mais la case Hospi. non cochée** (fond jaune dans le tableau).

- Cliquez le bouton → la case Hospi. est cochée pour tous ces contacts
- Le compteur "Assurcard sans Hospi." passe à **0**
- Le bouton disparaît (remplacé par un message vert)

> **Attention :** la synchronisation ne touche pas la case Dentaire.

---

### 4. Contacts avec fond jaune ⚠️

Un fond jaune signale les contacts qui ont un numéro Assurcard enregistré
**mais** dont la case Hospi. n'est pas cochée — incohérence à corriger.

Vous pouvez :
- Les corriger manuellement case par case
- Ou utiliser le bouton **Synchroniser** pour tout corriger en un clic

---

### 5. Tiers archivés (décédés / partis)

Par défaut, les employés inactifs **ne s'affichent pas** dans la page.

**Pour archiver un employé décédé :**
1. Ouvrir la fiche du Tiers (menu Tiers → chercher l'employé)
2. Actions → **Mettre en inactif**
3. Revenir sur la page Assurances → l'employé a disparu

**Pour les voir quand même :**
Un bouton **"Afficher les archivés (N)"** apparaît en haut de la barre de
boutons avec le nombre de Tiers inactifs. Cliquez dessus pour les afficher
en grisé avec le badge ⚰ **Archivé**.

> Les cases restent cochables même pour un Tiers archivé — vous pouvez
> donc conserver l'historique des assurances sans encombrer la vue normale.

---

## Bandeau de statistiques (en haut de la page)

| Indicateur | Signification |
|---|---|
| **Tiers** | Nombre d'employés actifs affichés |
| **Contacts total** | Nombre total de contacts (toutes personnes du foyer) |
| **Assurance Hospi.** | Contacts avec la case Hospi. cochée |
| **Assurance Dentaire** | Contacts avec la case Dentaire cochée |
| **N° Assurcard** | Contacts avec un numéro Assurcard renseigné |
| **Assurcard sans Hospi.** | ⚠️ À corriger — rouge si > 0, vert si = 0 |

---

## Questions fréquentes

**Q : Je modifie des cases et je navigue ailleurs sans sauvegarder — les modifications sont-elles perdues ?**  
R : Oui. Il faut cliquer **Enregistrer les assurances** avant de quitter la page.

**Q : Peut-on modifier un seul contact sans affecter les autres ?**  
R : Oui. L'enregistrement met à jour **tous** les contacts visibles à l'écran, mais seulement avec les valeurs actuellement affichées. Si vous ne touchez pas une case, elle reste identique.

**Q : Un Tiers archivé peut-il être réactivé ?**  
R : Oui. Dans la fiche Tiers → Actions → **Mettre en actif**. Il réapparaîtra dans la vue normale.

**Q : Le filtre "Afficher les archivés" est-il conservé si je change de page et reviens ?**  
R : Non — par sécurité, le filtre se réinitialise à chaque ouverture de la page (vue normale par défaut).

---

*Document généré dans le cadre du stage de développement — Soufiane Achraa, TECHGEST ICCBXL, 2026*
