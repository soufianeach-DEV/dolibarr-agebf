-- ============================================================
-- DONNÉES DE TEST — Fête des enfants Bruxelles Formation
-- Référence : 1er janvier 2026
-- Invités : enfants avec age_1jan < 15
-- Postes parents : fonctions réelles de Bruxelles Formation
-- ============================================================

-- FAMILLES (Tiers = collaborateurs de Bruxelles Formation)
INSERT INTO llx_societe (nom, entity, datec, fk_user_creat, status, client) VALUES
('Dupont Jean',    1, NOW(), 1, 1, 0),
('Martin Pierre',  1, NOW(), 1, 1, 0),
('Lecomte David',  1, NOW(), 1, 1, 0),
('Bernard Marc',   1, NOW(), 1, 1, 0),
('Dubois Alain',   1, NOW(), 1, 1, 0);

-- ============================================================
-- CONTACTS
-- Parents : postes réels Bruxelles Formation
-- Enfants : poste = 'Enfant'
-- ============================================================

-- FAMILLE DUPONT — Formateur & Gestionnaire pédagogique
INSERT INTO llx_socpeople (fk_soc, lastname, firstname, poste, birthday, entity, datec, fk_user_creat, statut) VALUES
((SELECT rowid FROM llx_societe WHERE nom='Dupont Jean'), 'Dupont', 'Jean',   'Formateur',              19780315, 1, NOW(), 1, 1),
((SELECT rowid FROM llx_societe WHERE nom='Dupont Jean'), 'Dupont', 'Marie',  'Gestionnaire pédagogique',19800622, 1, NOW(), 1, 1),
((SELECT rowid FROM llx_societe WHERE nom='Dupont Jean'), 'Dupont', 'Thomas', 'Enfant',                  20120315, 1, NOW(), 1, 1), -- 13 ans ✅ INVITÉ
((SELECT rowid FROM llx_societe WHERE nom='Dupont Jean'), 'Dupont', 'Emma',   'Enfant',                  20091207, 1, NOW(), 1, 1); -- 16 ans ❌ NON INVITÉ

-- FAMILLE MARTIN — Conseiller pédagogique & Chargée de communication
INSERT INTO llx_socpeople (fk_soc, lastname, firstname, poste, birthday, entity, datec, fk_user_creat, statut) VALUES
((SELECT rowid FROM llx_societe WHERE nom='Martin Pierre'), 'Martin', 'Pierre',  'Conseiller pédagogique',  19750910, 1, NOW(), 1, 1),
((SELECT rowid FROM llx_societe WHERE nom='Martin Pierre'), 'Martin', 'Sophie',  'Chargée de communication',19771123, 1, NOW(), 1, 1),
((SELECT rowid FROM llx_societe WHERE nom='Martin Pierre'), 'Martin', 'Lucas',   'Enfant',                  20150108, 1, NOW(), 1, 1), -- 10 ans ✅ INVITÉ
((SELECT rowid FROM llx_societe WHERE nom='Martin Pierre'), 'Martin', 'Chloé',   'Enfant',                  20101130, 1, NOW(), 1, 1); -- 15 ans ❌ NON INVITÉ

-- FAMILLE LECOMTE — Responsable RH & Coordinatrice pédagogique
INSERT INTO llx_socpeople (fk_soc, lastname, firstname, poste, birthday, entity, datec, fk_user_creat, statut) VALUES
((SELECT rowid FROM llx_societe WHERE nom='Lecomte David'), 'Lecomte', 'David',    'Responsable RH',           19820204, 1, NOW(), 1, 1),
((SELECT rowid FROM llx_societe WHERE nom='Lecomte David'), 'Lecomte', 'Isabelle', 'Coordinatrice pédagogique',19840719, 1, NOW(), 1, 1),
((SELECT rowid FROM llx_societe WHERE nom='Lecomte David'), 'Lecomte', 'Nathan',   'Enfant',                   20130605, 1, NOW(), 1, 1), -- 12 ans ✅ INVITÉ
((SELECT rowid FROM llx_societe WHERE nom='Lecomte David'), 'Lecomte', 'Zoé',      'Enfant',                   20160214, 1, NOW(), 1, 1); -- 9 ans  ✅ INVITÉ

-- FAMILLE BERNARD — Technicien informatique & Gestionnaire administrative
INSERT INTO llx_socpeople (fk_soc, lastname, firstname, poste, birthday, entity, datec, fk_user_creat, statut) VALUES
((SELECT rowid FROM llx_societe WHERE nom='Bernard Marc'), 'Bernard', 'Marc',   'Technicien informatique',   19791201, 1, NOW(), 1, 1),
((SELECT rowid FROM llx_societe WHERE nom='Bernard Marc'), 'Bernard', 'Claire', 'Gestionnaire administrative',19810330, 1, NOW(), 1, 1),
((SELECT rowid FROM llx_societe WHERE nom='Bernard Marc'), 'Bernard', 'Hugo',   'Enfant',                    20110920, 1, NOW(), 1, 1), -- 14 ans ✅ INVITÉ
((SELECT rowid FROM llx_societe WHERE nom='Bernard Marc'), 'Bernard', 'Léa',    'Enfant',                    20081203, 1, NOW(), 1, 1); -- 17 ans ❌ NON INVITÉ

-- FAMILLE DUBOIS — Chargé de projet & Conseillère en orientation
INSERT INTO llx_socpeople (fk_soc, lastname, firstname, poste, birthday, entity, datec, fk_user_creat, statut) VALUES
((SELECT rowid FROM llx_societe WHERE nom='Dubois Alain'), 'Dubois', 'Alain',    'Chargé de projet',          19760517, 1, NOW(), 1, 1),
((SELECT rowid FROM llx_societe WHERE nom='Dubois Alain'), 'Dubois', 'Nathalie', 'Conseillère en orientation', 19780828, 1, NOW(), 1, 1),
((SELECT rowid FROM llx_societe WHERE nom='Dubois Alain'), 'Dubois', 'Mathis',   'Enfant',                    20140417, 1, NOW(), 1, 1), -- 11 ans ✅ INVITÉ
((SELECT rowid FROM llx_societe WHERE nom='Dubois Alain'), 'Dubois', 'Camille',  'Enfant',                    20111215, 1, NOW(), 1, 1); -- 14 ans ✅ INVITÉ
