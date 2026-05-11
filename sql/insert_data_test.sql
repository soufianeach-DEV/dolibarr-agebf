-- ============================================================
-- DONNÉES DE TEST — Module AgeBF
-- Tiers : Bruxelles Formation
-- Contacts : collaborateurs + enfants
-- Référence âge : 1er janvier 2026
-- ============================================================

-- 1. Création du Tiers Bruxelles Formation
INSERT INTO llx_societe (nom, entity, datec, fk_user_creat, status, client, address, zip, town, phone, email)
VALUES ('Bruxelles Formation', 1, NOW(), 1, 1, 0, 'Boulevard Anspach 65', '1000', 'Bruxelles', '+32 2 371 74 00', 'info@bruxellesformation.be');

-- 2. Récupération de l'ID de Bruxelles Formation
SET @bf_id = (SELECT rowid FROM llx_societe WHERE nom = 'Bruxelles Formation' AND entity = 1 LIMIT 1);

-- 3. Insertion des contacts (parents = collaborateurs BF + enfants)
INSERT INTO llx_socpeople (fk_soc, lastname, firstname, poste, birthday, entity, datec, fk_user_creat, statut) VALUES
-- Famille Dupont
(@bf_id, 'Dupont',   'Jean',     'Formateur',                  '1978-03-15', 1, NOW(), 1, 1),
(@bf_id, 'Dupont',   'Marie',    'Gestionnaire pédagogique',   '1980-06-22', 1, NOW(), 1, 1),
(@bf_id, 'Dupont',   'Thomas',   'Enfant',                     '2012-03-15', 1, NOW(), 1, 1), -- 13 ans ✅ INVITÉ
(@bf_id, 'Dupont',   'Emma',     'Enfant',                     '2009-12-07', 1, NOW(), 1, 1), -- 16 ans ❌ NON INVITÉ

-- Famille Martin
(@bf_id, 'Martin',   'Pierre',   'Conseiller pédagogique',     '1975-09-10', 1, NOW(), 1, 1),
(@bf_id, 'Martin',   'Sophie',   'Chargée de communication',   '1977-11-23', 1, NOW(), 1, 1),
(@bf_id, 'Martin',   'Lucas',    'Enfant',                     '2015-01-08', 1, NOW(), 1, 1), -- 10 ans ✅ INVITÉ
(@bf_id, 'Martin',   'Chloé',    'Enfant',                     '2010-11-30', 1, NOW(), 1, 1), -- 15 ans ❌ NON INVITÉ

-- Famille Lecomte
(@bf_id, 'Lecomte',  'David',    'Responsable RH',             '1982-02-04', 1, NOW(), 1, 1),
(@bf_id, 'Lecomte',  'Isabelle', 'Coordinatrice pédagogique',  '1984-07-19', 1, NOW(), 1, 1),
(@bf_id, 'Lecomte',  'Nathan',   'Enfant',                     '2013-06-05', 1, NOW(), 1, 1), -- 12 ans ✅ INVITÉ
(@bf_id, 'Lecomte',  'Zoé',      'Enfant',                     '2016-02-14', 1, NOW(), 1, 1), --  9 ans ✅ INVITÉ

-- Famille Bernard
(@bf_id, 'Bernard',  'Marc',     'Technicien informatique',    '1979-12-01', 1, NOW(), 1, 1),
(@bf_id, 'Bernard',  'Claire',   'Gestionnaire administrative', '1981-03-30', 1, NOW(), 1, 1),
(@bf_id, 'Bernard',  'Hugo',     'Enfant',                     '2011-09-20', 1, NOW(), 1, 1), -- 14 ans ✅ INVITÉ
(@bf_id, 'Bernard',  'Léa',      'Enfant',                     '2008-12-03', 1, NOW(), 1, 1), -- 17 ans ❌ NON INVITÉ

-- Famille Dubois
(@bf_id, 'Dubois',   'Alain',    'Chargé de projet',           '1976-05-17', 1, NOW(), 1, 1),
(@bf_id, 'Dubois',   'Nathalie', 'Conseillère en orientation',  '1978-08-28', 1, NOW(), 1, 1),
(@bf_id, 'Dubois',   'Mathis',   'Enfant',                     '2014-04-17', 1, NOW(), 1, 1), -- 11 ans ✅ INVITÉ
(@bf_id, 'Dubois',   'Camille',  'Enfant',                     '2011-12-15', 1, NOW(), 1, 1); -- 14 ans ✅ INVITÉ
