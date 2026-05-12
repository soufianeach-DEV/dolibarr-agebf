-- ============================================================
-- DONNÉES DE TEST — Module AgeBF v2.0
-- 1 Tiers + 27 parents employés + 31 enfants = 58 contacts
-- Référence âge : 1er janvier 2026
-- ============================================================

-- Nettoyage des données précédentes
DELETE sp FROM llx_socpeople sp
  INNER JOIN llx_societe s ON s.rowid = sp.fk_soc
  WHERE s.nom = 'Bruxelles Formation';
DELETE FROM llx_societe WHERE nom = 'Bruxelles Formation';

-- ── 1. Tiers ────────────────────────────────────────────────
INSERT INTO llx_societe (nom, entity, datec, fk_user_creat, status, client,
  address, zip, town, phone, email)
VALUES ('Bruxelles Formation', 1, NOW(), 1, 1, 0,
  'Boulevard Anspach 65', '1000', 'Bruxelles',
  '+32 2 371 74 00', 'info@bruxellesformation.be');

SET @bf = (SELECT rowid FROM llx_societe
           WHERE nom = 'Bruxelles Formation' AND entity = 1 LIMIT 1);

-- ── 2. Contacts ─────────────────────────────────────────────
--  Parents (27) + Enfants (31)
INSERT INTO llx_socpeople
  (fk_soc, lastname, firstname, poste, birthday, entity, datec, fk_user_creat, statut)
VALUES
-- ═══ Famille Dupont ════════════════════════════════════════
(@bf,'Dupont','Jean',        'Formateur',                    '1978-03-15',1,NOW(),1,1),
(@bf,'Dupont','Marie',       'Gestionnaire pédagogique',     '1980-06-22',1,NOW(),1,1),
(@bf,'Dupont','Thomas',      'Fils',                         '2012-03-15',1,NOW(),1,1), -- 13 ✅
(@bf,'Dupont','Emma',        'Fille',                        '2009-12-07',1,NOW(),1,1), -- 16 ❌
-- ═══ Famille Martin ════════════════════════════════════════
(@bf,'Martin','Pierre',      'Conseiller pédagogique',       '1975-09-10',1,NOW(),1,1),
(@bf,'Martin','Sophie',      'Chargée de communication',     '1977-11-23',1,NOW(),1,1),
(@bf,'Martin','Lucas',       'Fils',                         '2015-01-08',1,NOW(),1,1), -- 10 ✅
(@bf,'Martin','Chloé',       'Fille',                        '2010-11-30',1,NOW(),1,1), -- 15 ✅
-- ═══ Famille Lecomte ═══════════════════════════════════════
(@bf,'Lecomte','David',      'Responsable RH',               '1982-02-04',1,NOW(),1,1),
(@bf,'Lecomte','Isabelle',   'Coordinatrice pédagogique',    '1984-07-19',1,NOW(),1,1),
(@bf,'Lecomte','Nathan',     'Fils',                         '2013-06-05',1,NOW(),1,1), -- 12 ✅
(@bf,'Lecomte','Zoé',        'Fille',                        '2016-02-14',1,NOW(),1,1), --  9 ✅
-- ═══ Famille Bernard ═══════════════════════════════════════
(@bf,'Bernard','Marc',       'Technicien informatique',      '1979-12-01',1,NOW(),1,1),
(@bf,'Bernard','Claire',     'Gestionnaire administrative',  '1981-03-30',1,NOW(),1,1),
(@bf,'Bernard','Hugo',       'Fils',                         '2011-09-20',1,NOW(),1,1), -- 14 ✅
(@bf,'Bernard','Léa',        'Fille',                        '2008-12-03',1,NOW(),1,1), -- 17 ❌
-- ═══ Famille Dubois ════════════════════════════════════════
(@bf,'Dubois','Alain',       'Chargé de projet',             '1976-05-17',1,NOW(),1,1),
(@bf,'Dubois','Nathalie',    'Conseillère en orientation',   '1978-08-28',1,NOW(),1,1),
(@bf,'Dubois','Mathis',      'Fils',                         '2014-04-17',1,NOW(),1,1), -- 11 ✅
(@bf,'Dubois','Camille',     'Fille',                        '2011-12-15',1,NOW(),1,1), -- 14 ✅
-- ═══ Famille Laurent ═══════════════════════════════════════
(@bf,'Laurent','François',   'Directeur de formation',       '1971-08-12',1,NOW(),1,1),
(@bf,'Laurent','Anne',       'Secrétaire de direction',      '1973-04-05',1,NOW(),1,1),
(@bf,'Laurent','Julien',     'Fils',                         '2019-03-22',1,NOW(),1,1), --  6 ✅
(@bf,'Laurent','Sarah',      'Fille',                        '2009-07-14',1,NOW(),1,1), -- 16 ❌
(@bf,'Laurent','Clara',      'Fille',                        '2014-08-30',1,NOW(),1,1), -- 11 ✅
-- ═══ Famille Simon ═════════════════════════════════════════
(@bf,'Simon','Nicolas',      'Formateur',                    '1983-01-29',1,NOW(),1,1),
(@bf,'Simon','Céline',       'Assistante RH',                '1985-10-14',1,NOW(),1,1),
(@bf,'Simon','Théo',         'Fils',                         '2021-05-10',1,NOW(),1,1), --  4 ✅
(@bf,'Simon','Lola',         'Fille',                        '2013-09-18',1,NOW(),1,1), -- 12 ✅
-- ═══ Famille Rousseau ══════════════════════════════════════
(@bf,'Rousseau','Patrick',   'Chef de projet digital',       '1977-06-30',1,NOW(),1,1),
(@bf,'Rousseau','Valérie',   'Comptable',                    '1979-02-18',1,NOW(),1,1),
(@bf,'Rousseau','Antoine',   'Fils',                         '2008-06-25',1,NOW(),1,1), -- 17 ❌
(@bf,'Rousseau','Manon',     'Fille',                        '2018-01-12',1,NOW(),1,1), --  7 ✅
-- ═══ Famille Moreau ════════════════════════════════════════
(@bf,'Moreau','Bruno',       'Formateur senior',             '1974-11-07',1,NOW(),1,1),
(@bf,'Moreau','Christine',   'Documentaliste',               '1976-09-25',1,NOW(),1,1),
(@bf,'Moreau','Kévin',       'Fils',                         '2020-11-03',1,NOW(),1,1), --  5 ✅
(@bf,'Moreau','Julie',       'Fille',                        '2010-08-22',1,NOW(),1,1), -- 15 ✅
(@bf,'Moreau','Alexis',      'Fils',                         '2015-04-07',1,NOW(),1,1), -- 10 ✅
-- ═══ Famille Petit ═════════════════════════════════════════
(@bf,'Petit','Stéphane',     'Analyste pédagogique',         '1980-07-03',1,NOW(),1,1),
(@bf,'Petit','Sandrine',     'Coordinatrice projets',        '1982-12-11',1,NOW(),1,1),
(@bf,'Petit','Clément',      'Fils',                         '2023-02-14',1,NOW(),1,1), --  2 ✅
(@bf,'Petit','Inès',         'Fille',                        '2012-07-30',1,NOW(),1,1), -- 13 ✅
(@bf,'Petit','Tom',          'Fils',                         '2010-05-19',1,NOW(),1,1), -- 15 ✅
-- ═══ Famille Gallet ════════════════════════════════════════
(@bf,'Gallet','Jean-Marc',   'Directeur adjoint',            '1969-05-21',1,NOW(),1,1),
(@bf,'Gallet','Élise',       'Chargée relations entreprises','1972-09-09',1,NOW(),1,1),
(@bf,'Gallet','Raphaël',     'Fils',                         '2017-09-05',1,NOW(),1,1), --  8 ✅
(@bf,'Gallet','Sofia',       'Fille',                        '2014-11-20',1,NOW(),1,1), -- 11 ✅
-- ═══ Famille Durand ════════════════════════════════════════
(@bf,'Durand','Michel',      'Ingénieur pédagogique',        '1981-04-16',1,NOW(),1,1),
(@bf,'Durand','Isabelle',    'Assistante de direction',      '1983-08-02',1,NOW(),1,1),
(@bf,'Durand','Noé',         'Fils',                         '2016-06-28',1,NOW(),1,1), --  9 ✅
(@bf,'Durand','Alice',       'Fille',                        '2022-03-15',1,NOW(),1,1), --  3 ✅
-- ═══ Parent isolé — Renard ════════════════════════════════
(@bf,'Renard','Catherine',   'Formatrice senior',            '1979-01-30',1,NOW(),1,1),
(@bf,'Renard','Pierre-Antoine','Fils',                       '2019-07-04',1,NOW(),1,1), --  6 ✅
-- ═══ Parent isolé — Bonnet ════════════════════════════════
(@bf,'Bonnet','André',       'Chargé d''insertion professionnelle','1977-03-22',1,NOW(),1,1),
(@bf,'Bonnet','Léonie',      'Fille',                        '2021-12-01',1,NOW(),1,1), --  4 ✅
(@bf,'Bonnet','Maxime',      'Fils',                         '2012-08-15',1,NOW(),1,1), -- 13 ✅
-- ═══ Parent isolé — Gros ══════════════════════════════════
(@bf,'Gros','Marie-France',  'Chargée de communication',     '1983-06-14',1,NOW(),1,1),
(@bf,'Gros','Anaïs',         'Fille',                        '2018-06-20',1,NOW(),1,1); --  7 ✅

-- ── 3. Lien fk_parent ────────────────────────────────────────
-- Chaque enfant est lié au 1er adulte de même nom de famille
INSERT INTO llx_socpeople_extrafields (fk_object, fk_parent)
SELECT
  child.rowid,
  (
    SELECT parent.rowid
    FROM llx_socpeople AS parent
    WHERE parent.lastname = child.lastname
      AND parent.fk_soc   = @bf
      AND parent.poste NOT IN ('Fils','Fille')
    ORDER BY parent.rowid
    LIMIT 1
  ) AS parent_id
FROM llx_socpeople AS child
WHERE child.poste IN ('Fils','Fille')
  AND child.fk_soc = @bf
ON DUPLICATE KEY UPDATE fk_parent = VALUES(fk_parent);
