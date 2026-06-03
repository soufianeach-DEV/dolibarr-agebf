<?php
// Generateur de fausses donnees de test pour la page "Suivi des paiements SEPA".
// Cree des membres + factures fournisseurs + lignes (packs) + paiements + ecritures
// bancaires, avec un MIX de situations (sold./partielle x pointee/non pointee).
// Re-executable : nettoie d'abord ses propres donnees (ref S02699-*, code_client TESTGEN).
// Genere aussi un releve Belfius cible (releve_belfius_nouveau.csv) sur les virements NON pointes.

$db = new mysqli('127.0.0.1', 'root', '', 'dolibarr');
if ($db->connect_errno) { fwrite(STDERR, "DB error: " . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8');
$P = 'llx_';

function q($db, $sql) {
    if (!$db->query($sql)) { fwrite(STDERR, "SQL ERROR: " . $db->error . "\n  $sql\n"); exit(1); }
    return true;
}
function ids($db, $sql) {
    $r = $db->query($sql); $out = [];
    while ($r && ($o = $r->fetch_row())) $out[] = $o[0];
    return $out;
}

// ── 1. Nettoyage des donnees de test precedentes ─────────────────────────────────
$facs = ids($db, "SELECT rowid FROM {$P}facture_fourn WHERE ref LIKE 'S02699-%'");
$rows = $db->query("SELECT rowid, fk_bank FROM {$P}paiementfourn WHERE ref LIKE 'PAYG-%'");
$pay_ids = []; $bank_ids = [];
while ($rows && ($o = $rows->fetch_object())) { $pay_ids[] = (int)$o->rowid; if ($o->fk_bank) $bank_ids[] = (int)$o->fk_bank; }

if ($facs) {
    $inF = implode(',', array_map('intval', $facs));
    q($db, "DELETE FROM {$P}facture_fourn_det WHERE fk_facture_fourn IN ($inF)");
    q($db, "DELETE FROM {$P}prelevement_demande WHERE fk_facture_fourn IN ($inF)");
    q($db, "DELETE FROM {$P}prelevement WHERE fk_facture_fourn IN ($inF)");
}
if ($pay_ids) {
    $inP = implode(',', $pay_ids);
    q($db, "DELETE FROM {$P}paiementfourn_facturefourn WHERE fk_paiementfourn IN ($inP)");
    q($db, "DELETE FROM {$P}paiementfourn WHERE rowid IN ($inP)");
}
if ($bank_ids) q($db, "DELETE FROM {$P}bank WHERE rowid IN (" . implode(',', $bank_ids) . ")");
if ($facs)     q($db, "DELETE FROM {$P}facture_fourn WHERE rowid IN (" . implode(',', array_map('intval', $facs)) . ")");
q($db, "DELETE FROM {$P}societe WHERE code_client LIKE 'TESTGEN%'");

// ── 2. Definition du jeu de test ─────────────────────────────────────────────────
// [membre, pack_id, total_ttc, datef, [ [montant, decalage_mois, rappro], ... ] ]
$data = [
    // a) Soldees + entierement pointees (tout vert)
    ['Lambert Pierre', 1,  90, '2026-01-15', [[30,0,1],[30,1,1],[30,2,1]]],
    ['Martin Julie',   2, 120, '2026-02-15', [[60,0,1],[60,1,1]]],
    ['Leroy David',    4,  70, '2026-02-20', [[70,0,1]]],
    ['Simon Anne',     6, 150, '2026-03-10', [[50,0,1],[50,1,1],[50,2,1]]],
    // b) Soldees mais NON pointees (restant vert, rapprochement rouge) -> a tester
    ['Lefebvre Marc',  3,  80, '2026-03-15', [[40,0,0],[40,1,0]]],
    ['Moreau Camille', 1,  60, '2026-04-05', [[30,0,0],[30,1,0]]],
    ['Laurent Thomas', 15,100, '2026-04-12', [[100,0,0]]],
    // c) Partielles, versements existants pointes (restant rouge, rapprochement vert)
    ['Petit Laura',    8, 200, '2026-02-08', [[80,0,1]]],
    ['Renard Hugo',    7, 180, '2026-03-20', [[60,0,1],[60,1,1]]],
    ['Henry Sarah',    2, 150, '2026-04-18', [[50,0,1]]],
    // d) Partielles ET non pointees (restant rouge, rapprochement rouge)
    ['Mathieu Paul',  14, 140, '2026-05-02', [[70,0,0]]],
    ['Lambert Sophie', 4,  90, '2026-05-10', [[45,0,0]]],
    // e) Aucun versement (restant rouge, 0 virement)
    ['Bonnet Lucie',   9,  50, '2026-05-15', []],
    ['Girard Eric',    5, 110, '2026-05-20', []],
];

// packs : ref + label depuis llx_product
$packs = [];
$rp = $db->query("SELECT rowid, ref, label FROM {$P}product");
while ($o = $rp->fetch_object()) $packs[(int)$o->rowid] = $o;

$now = date('Y-m-d H:i:s');
$tnum = 101;            // num_paiement : T2605101, T2605102, ...
$facnum = 100;          // ref facture : S02699-0100, ...
$belfius = [];          // virements non pointes -> releve Belfius cible

foreach ($data as $d) {
    list($membre, $pack_id, $total, $datef, $versements) = $d;
    $facnum++;
    $fac_ref = 'S02699-' . str_pad((string)$facnum, 4, '0', STR_PAD_LEFT);

    // 2a. membre (tiers)
    q($db, "INSERT INTO {$P}societe (nom, entity, status, client, fournisseur, fk_pays, code_client, datec)
            VALUES ('" . $db->real_escape_string($membre) . "', 1, 1, 0, 0, 0, 'TESTGEN-$facnum', '$now')");
    $soc_id = $db->insert_id;

    // 2b. somme versee -> statut/paye
    $paye_tot = 0.0;
    foreach ($versements as $v) $paye_tot += $v[0];
    $solde = ($total - $paye_tot) < 0.01;
    $statut = $solde ? 2 : 1;
    $paye   = $solde ? 1 : 0;

    // 2c. facture fournisseur
    q($db, "INSERT INTO {$P}facture_fourn
        (ref, ref_supplier, entity, type, fk_soc, datec, datef, libelle, paye, amount,
         tva, total_tva, total_ht, total_ttc, fk_statut, fk_user_author, fk_user_valid,
         fk_cond_reglement, fk_mode_reglement)
        VALUES ('$fac_ref', '$fac_ref', 1, 0, $soc_id, '$now', '$datef',
                '" . $db->real_escape_string($membre) . "', $paye, $total,
                0, 0, $total, $total, $statut, 1, 1, 1, 2)");
    $fac_id = $db->insert_id;

    // 2d. ligne facture (le pack)
    $pk = $packs[$pack_id];
    q($db, "INSERT INTO {$P}facture_fourn_det
        (fk_facture_fourn, fk_product, label, qty, pu_ht, tva_tx, total_ht, total_ttc,
         product_type, rang, fk_code_ventilation)
        VALUES ($fac_id, $pack_id, '" . $db->real_escape_string($pk->ref) . "', 1, $total, 0,
                $total, $total, 1, 1, 0)");

    // 2e. versements + ecritures bancaires
    $n = 0; $nb = count($versements);
    foreach ($versements as $v) {
        $n++;
        list($mont, $off, $rappro) = $v;
        $tref = 'T2605' . str_pad((string)$tnum, 3, '0', STR_PAD_LEFT); $tnum++;

        // date du versement = datef (mois) + decalage, le 28
        $mois = (int)date('n', strtotime($datef)) + $off;
        $an   = (int)date('Y', strtotime($datef));
        while ($mois > 12) { $mois -= 12; $an++; }
        $dvers = sprintf('%04d-%02d-28', $an, $mois);
        $extrait = sprintf('%04d/%04d', $an, $mois);

        // ecriture bancaire
        $num_rel = $rappro ? $extrait : '';
        $label = 'Virement Belfius - ' . $fac_ref . ' - ' . $membre . ' - ' . $tref;
        q($db, "INSERT INTO {$P}bank
            (datec, dateo, datev, amount, label, fk_account, fk_type, num_releve, rappro, fk_user_author)
            VALUES ('$now', '$dvers', '$dvers', $mont,
                    '" . $db->real_escape_string($label) . "', 1, 'VIR',
                    " . ($num_rel !== '' ? "'" . $db->real_escape_string($num_rel) . "'" : "''") . ",
                    " . ($rappro ? 1 : 0) . ", 1)");
        $bank_id = $db->insert_id;

        // paiement fournisseur
        q($db, "INSERT INTO {$P}paiementfourn
            (ref, entity, datec, datep, amount, fk_user_author, fk_paiement, num_paiement, note, fk_bank, statut)
            VALUES ('PAYG-F$fac_id-$n', 1, '$now', '$dvers', $mont, 1, 2, '$tref',
                    'Versement $n/$nb - $fac_ref', $bank_id, 1)");
        $pay_id = $db->insert_id;

        // lien paiement <-> facture
        q($db, "INSERT INTO {$P}paiementfourn_facturefourn
            (fk_paiementfourn, fk_facturefourn, amount, multicurrency_tx)
            VALUES ($pay_id, $fac_id, $mont, 1)");

        // virement non pointe -> dans le releve Belfius cible
        if (!$rappro) {
            $belfius[] = [
                'date'  => date('d/m/Y', strtotime($dvers)),
                'ext'   => $extrait,
                'tx'    => $pay_id,
                'iban'  => 'BE' . str_pad((string)(10 + $pay_id), 2, '0', STR_PAD_LEFT)
                         . ' 0000 0000 ' . str_pad((string)$pay_id, 4, '0', STR_PAD_LEFT),
                'nom'   => $membre,
                'mont'  => '-' . number_format($mont, 2, ',', '.'),
                'comm'  => $fac_ref . ' - cotisation',
            ];
        }
    }
    echo sprintf("%-16s %-12s total %6.2f  verse %6.2f  %s\n",
        $membre, $fac_ref, $total, $paye_tot,
        $solde ? 'SOLDEE' : 'partielle');
}

// ── 3. Releve Belfius cible sur les virements NON pointes ────────────────────────
if ($belfius) {
    $out = [];
    $out[] = '"Compte :";"BE68 5390 0754 7034"';
    $out[] = '"Type :";"Compte courant"';
    $out[] = '"Periode :";"01/01/2026 - 31/07/2026"';
    $out[] = '"Devise :";"EUR"';
    $out[] = '"Date d\'extraction :";"' . date('d/m/Y') . '"';
    $out[] = '';
    $out[] = implode(';', array_map(fn($c) => '"' . $c . '"', [
        'Compte', 'Date de comptabilisation', "Numero d'extrait", 'Numero de transaction',
        'Compte contrepartie', 'Nom de la contrepartie', 'Rue et numero', 'Code postal et localite',
        'Transaction', 'Date valeur', 'Montant', 'Devise', 'BIC', 'Code pays', 'Communication',
    ]));
    $i = 0;
    foreach ($belfius as $b) {
        $i++;
        $champs = [
            'BE68 5390 0754 7034', $b['date'], $b['ext'], '202610' . str_pad((string)$i, 4, '0', STR_PAD_LEFT),
            $b['iban'], $b['nom'], '', '',
            'Virement SEPA', $b['date'], $b['mont'], 'EUR', 'GKCCBEBB', 'BE', $b['comm'],
        ];
        $out[] = implode(';', array_map(fn($c) => '"' . str_replace('"', '""', $c) . '"', $champs));
    }
    $content = mb_convert_encoding(implode("\r\n", $out) . "\r\n", 'ISO-8859-1', 'UTF-8');
    file_put_contents(__DIR__ . '/releve_belfius_nouveau.csv', $content);
    echo "\nReleve Belfius cible : " . count($belfius) . " virements non pointes -> releve_belfius_nouveau.csv\n";
}

echo "\nOK - " . count($data) . " factures de test creees.\n";
$db->close();
