<?php
// Generateur de faux lots SEPA (bons de virement fournisseur) + fichiers XML pain.001
// a partir des paiements fournisseurs de test deja presents dans Dolibarr.
// Re-executable : nettoie d'abord les 4 tables prelevement_* avant de re-remplir.

$db = new mysqli('127.0.0.1', 'root', '', 'dolibarr');
if ($db->connect_errno) { fwrite(STDERR, "DB error: " . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8');

$P = 'llx_';                                   // prefixe des tables
$receipts = 'C:/xampp/htdocs/dolibarr/documents/paymentbybanktransfer/receipts';
$org_iban = 'BE68539007547034';                // compte Belfius de l'asso (sans espaces)
$org_bic  = 'GKCCBEBB';
$org_nom  = 'AGE Bruxelles Formation';

// --- 1. Charger les paiements groupes par mois ----------------------------------
$sql = "SELECT p.rowid AS pay_id,
               DATE_FORMAT(p.datep,'%Y-%m-%d') AS date_op,
               DATE_FORMAT(p.datep,'%Y%m')     AS ym,
               pff.amount                       AS montant,
               s.rowid                          AS soc_id,
               s.nom                            AS tiers,
               p.num_paiement                   AS ref_sepa,
               f.rowid                          AS fac_id,
               f.ref                            AS facture
        FROM {$P}paiementfourn p
        JOIN {$P}paiementfourn_facturefourn pff ON pff.fk_paiementfourn = p.rowid
        JOIN {$P}facture_fourn f ON f.rowid = pff.fk_facturefourn
        JOIN {$P}societe s ON s.rowid = f.fk_soc
        ORDER BY p.datep, p.rowid";
$res = $db->query($sql);
$mois = [];
while ($r = $res->fetch_object()) { $mois[$r->ym][] = $r; }
ksort($mois);

// --- 2. Nettoyage (re-executable) -----------------------------------------------
foreach (['prelevement', 'prelevement_lignes', 'prelevement_demande', 'prelevement_bons'] as $t) {
    $db->query("DELETE FROM {$P}{$t}");
}

// --- 3. Pour chaque mois : un bon de virement + ses lignes + le XML --------------
$idx_lot = 0;
$total_tx = 0;
$lots_csv = [];                                 // collecte pour le releve Belfius scenario B
foreach ($mois as $ym => $lignes) {
    $idx_lot++;
    $annee = substr($ym, 0, 4);
    $mm    = substr($ym, 4, 2);
    $ref   = 'ECH' . $ym;                       // ex: ECH202601 (varchar(12))
    $total = 0.0;
    foreach ($lignes as $l) { $total += (float) $l->montant; }
    $total = round($total, 2);
    $date_op = $lignes[0]->date_op;             // le 28 du mois
    $datec   = "$annee-$mm-01 09:00:00";
    $datetr  = "$date_op 10:00:00";

    // 3a. bon de virement (le "fichier SEPA")
    $db->query("INSERT INTO {$P}prelevement_bons
        (type, ref, entity, datec, amount, statut, credite, note, date_trans, method_trans, fk_user_trans, date_credit, fk_user_credit, fk_bank_account)
        VALUES ('bank-transfer', '" . $db->real_escape_string($ref) . "', 1, '$datec', $total, 1, 1,
                'Lot SEPA de test genere automatiquement', '$datetr', 0, 1, '$datetr', 1, 1)");
    $bon_id = $db->insert_id;

    // 3b. lignes / demandes / prelevement pour chaque paiement du lot
    $xml_tx = [];
    foreach ($lignes as $l) {
        $total_tx++;
        $sup_iban = 'BE' . str_pad((string)(10 + $l->pay_id), 2, '0', STR_PAD_LEFT)
                  . '00000000' . str_pad((string)$l->pay_id, 4, '0', STR_PAD_LEFT);
        $mont = round((float) $l->montant, 2);

        // ligne (par beneficiaire) — number = num_paiement (T-number) pour lier
        // sans ambiguite le lot a son virement (paiementfourn.num_paiement)
        $db->query("INSERT INTO {$P}prelevement_lignes
            (fk_prelevement_bons, fk_soc, fk_user, statut, client_nom, amount, number)
            VALUES ($bon_id, " . (int)$l->soc_id . ", 1, 2,
                    '" . $db->real_escape_string($l->tiers) . "', $mont,
                    '" . $db->real_escape_string($l->ref_sepa) . "')");
        $ligne_id = $db->insert_id;

        // demande (lien facture -> bon)
        $db->query("INSERT INTO {$P}prelevement_demande
            (entity, fk_facture_fourn, sourcetype, amount, date_demande, traite, date_traite, fk_prelevement_bons, fk_user_demande, number, type)
            VALUES (1, " . (int)$l->fac_id . ", 'supplier_invoice', $mont, '$datec', 1, '$datetr', $bon_id, 1,
                    '" . $db->real_escape_string($sup_iban) . "', 'ban')");

        // prelevement (lien facture -> ligne)
        $db->query("INSERT INTO {$P}prelevement
            (fk_facture_fourn, fk_prelevement_lignes)
            VALUES (" . (int)$l->fac_id . ", $ligne_id)");

        $xml_tx[] = [
            'e2e'  => $l->ref_sepa,
            'amt'  => number_format($mont, 2, '.', ''),
            'iban' => $sup_iban,
            'bic'  => $org_bic,
            'nom'  => $l->tiers,
            'comm' => $l->facture . ' - cotisation',
        ];
    }

    // 3c. fichier XML pain.001.001.03
    $xml = build_pain001($ref, $datec, $total, count($xml_tx), $date_op,
                         $org_nom, $org_iban, $org_bic, $xml_tx);
    file_put_contents("$receipts/$ref.xml", $xml);

    // 3d. memoriser le lot pour le releve Belfius (1 ligne globale par lot)
    $lots_csv[] = [
        'bon_id' => $bon_id,
        'ref'    => $ref,
        'ym'     => $ym,
        'date'   => $date_op,
        'total'  => $total,
        'nb'     => count($lignes),
    ];

    echo "Lot $ref (CT$bon_id) : " . count($lignes) . " virements, total " . number_format($total, 2, ',', '.') . " EUR -> $ref.xml\n";
}

// --- 4. Releve Belfius scenario B : 1 ligne globale "ORDRE COLLECTIF" par lot -----
$out = [];
$out[] = '"Compte :";"BE68 5390 0754 7034"';
$out[] = '"Type :";"Compte courant"';
$out[] = '"Periode :";"01/01/2026 - 31/12/2026"';
$out[] = '"Devise :";"EUR"';
$out[] = '"Date d\'extraction :";"' . date('d/m/Y') . '"';
$out[] = '';
$out[] = implode(';', array_map(fn($c) => '"' . $c . '"', [
    'Compte', 'Date de comptabilisation', "Numero d'extrait", 'Numero de transaction',
    'Compte contrepartie', 'Nom de la contrepartie', 'Rue et numero', 'Code postal et localite',
    'Transaction', 'Date valeur', 'Montant', 'Devise', 'BIC', 'Code pays', 'Communication',
]));
$i = 0;
foreach ($lots_csv as $lot) {
    $i++;
    $d        = $lot['date'];                                   // YYYY-MM-DD
    $dd       = date('d/m/Y', strtotime($d));
    $ddmm     = date('d-m', strtotime($d));
    $ymd      = date('Ymd', strtotime($d));                     // pour FICHIER : DOL/AAAAMMJJ/CTxx
    $extrait  = substr($lot['ym'], 0, 4) . '/' . str_pad((string)$i, 4, '0', STR_PAD_LEFT);
    $groupe   = 'DOL/' . substr($lot['ym'], 0, 4) . substr($lot['ym'], 4, 2) . '/0000';
    $ref_bq   = '0801' . strtoupper(substr(md5($lot['ref']), 0, 9));
    $comm     = 'VOTRE ORDRE COLLECTIF BELFIUS DIRECT NET BUS. FICHIER  : DOL/' . $ymd
              . '/CT' . $lot['bon_id'] . ' GROUPE : ' . $groupe
              . '           REF. : ' . $ref_bq . ' VAL. ' . $ddmm;
    $champs = [
        'BE68 5390 0754 7034', $dd, $extrait, (string)(2026100000 + $i),
        '', '', '', '',
        $comm, $dd, '-' . number_format($lot['total'], 2, ',', '.'), 'EUR', '', '', $comm,
    ];
    $out[] = implode(';', array_map(fn($c) => '"' . str_replace('"', '""', (string)$c) . '"', $champs));
}
$content = mb_convert_encoding(implode("\r\n", $out) . "\r\n", 'ISO-8859-1', 'UTF-8');
file_put_contents(__DIR__ . '/releve_belfius_lots.csv', $content);

echo "OK - $idx_lot lots SEPA crees, $total_tx virements au total.\n";
echo "Releve Belfius scenario B : " . count($lots_csv) . " lignes globales -> releve_belfius_lots.csv\n";
$db->close();

// ---------------------------------------------------------------------------------
function build_pain001($ref, $datec, $total, $nb, $date_exec, $org_nom, $org_iban, $org_bic, $tx)
{
    $msgid   = $ref . '-' . date('YmdHis', strtotime($datec));
    $credtm  = date('Y-m-d\TH:i:s', strtotime($datec));
    $ctrlsum = number_format($total, 2, '.', '');
    $x  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $x .= '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.001.001.03">' . "\n";
    $x .= "  <CstmrCdtTrfInitn>\n";
    $x .= "    <GrpHdr>\n";
    $x .= "      <MsgId>" . h($msgid) . "</MsgId>\n";
    $x .= "      <CreDtTm>$credtm</CreDtTm>\n";
    $x .= "      <NbOfTxs>$nb</NbOfTxs>\n";
    $x .= "      <CtrlSum>$ctrlsum</CtrlSum>\n";
    $x .= "      <InitgPty><Nm>" . h($org_nom) . "</Nm></InitgPty>\n";
    $x .= "    </GrpHdr>\n";
    $x .= "    <PmtInf>\n";
    $x .= "      <PmtInfId>" . h($ref) . "</PmtInfId>\n";
    $x .= "      <PmtMtd>TRF</PmtMtd>\n";
    $x .= "      <NbOfTxs>$nb</NbOfTxs>\n";
    $x .= "      <CtrlSum>$ctrlsum</CtrlSum>\n";
    $x .= "      <PmtTpInf><SvcLvl><Cd>SEPA</Cd></SvcLvl></PmtTpInf>\n";
    $x .= "      <ReqdExctnDt>$date_exec</ReqdExctnDt>\n";
    $x .= "      <Dbtr><Nm>" . h($org_nom) . "</Nm></Dbtr>\n";
    $x .= "      <DbtrAcct><Id><IBAN>" . h($org_iban) . "</IBAN></Id></DbtrAcct>\n";
    $x .= "      <DbtrAgt><FinInstnId><BIC>" . h($org_bic) . "</BIC></FinInstnId></DbtrAgt>\n";
    $x .= "      <ChrgBr>SLEV</ChrgBr>\n";
    foreach ($tx as $t) {
        $x .= "      <CdtTrfTxInf>\n";
        $x .= "        <PmtId><EndToEndId>" . h($t['e2e']) . "</EndToEndId></PmtId>\n";
        $x .= "        <Amt><InstdAmt Ccy=\"EUR\">" . h($t['amt']) . "</InstdAmt></Amt>\n";
        $x .= "        <CdtrAgt><FinInstnId><BIC>" . h($t['bic']) . "</BIC></FinInstnId></CdtrAgt>\n";
        $x .= "        <Cdtr><Nm>" . h($t['nom']) . "</Nm></Cdtr>\n";
        $x .= "        <CdtrAcct><Id><IBAN>" . h($t['iban']) . "</IBAN></Id></CdtrAcct>\n";
        $x .= "        <RmtInf><Ustrd>" . h($t['comm']) . "</Ustrd></RmtInf>\n";
        $x .= "      </CdtTrfTxInf>\n";
    }
    $x .= "    </PmtInf>\n";
    $x .= "  </CstmrCdtTrfInitn>\n";
    $x .= "</Document>\n";
    return $x;
}

function h($s) { return htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
