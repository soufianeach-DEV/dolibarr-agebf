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

        // ligne (par beneficiaire)
        $db->query("INSERT INTO {$P}prelevement_lignes
            (fk_prelevement_bons, fk_soc, fk_user, statut, client_nom, amount, number)
            VALUES ($bon_id, " . (int)$l->soc_id . ", 1, 2,
                    '" . $db->real_escape_string($l->tiers) . "', $mont,
                    '" . $db->real_escape_string($sup_iban) . "')");
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

    echo "Lot $ref : " . count($lignes) . " virements, total " . number_format($total, 2, ',', '.') . " EUR -> $ref.xml\n";
}

echo "OK - $idx_lot lots SEPA crees, $total_tx virements au total.\n";
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
