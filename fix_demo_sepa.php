<?php
// Prepare une demo SEPA propre :
//  1) constantes de config du module Paiement par virement bancaire (corrige l'erreur rouge)
//  2) IBAN/BIC valides sur le compte emetteur Belfius
//  3) IBAN belge VALIDE (mod-97) + RIB par defaut pour chaque tiers a payer (corrige les triangles)
//  4) reset des demandes de virement en attente -> le bouton "Preparer le lot" repart de zero
// Re-executable sans risque.

$db = new mysqli('127.0.0.1', 'root', '', 'dolibarr');
if ($db->connect_errno) { fwrite(STDERR, "DB error: " . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8');
$P = 'llx_';
function q($db, $sql) { if (!$db->query($sql)) { fwrite(STDERR, "SQL ERROR: " . $db->error . "\n  $sql\n"); exit(1); } return true; }

// --- helper : genere un IBAN belge valide depuis 10 chiffres (3 banque + 7 compte) ---
function be_iban($bank3, $acc7) {
    $base10 = $bank3 . $acc7;                       // 10 chiffres
    $nat = (int) $base10 % 97; if ($nat === 0) $nat = 97;
    $bban = $base10 . str_pad((string) $nat, 2, '0', STR_PAD_LEFT);  // 12 chiffres
    // check IBAN : BBAN + "BE00" -> B=11 E=14 -> mod 97
    $check_src = $bban . '111400';                  // BE = 11 14, + "00"
    // mod 97 sur grand nombre (chaine)
    $rem = 0;
    for ($i = 0; $i < strlen($check_src); $i++) $rem = ($rem * 10 + (int) $check_src[$i]) % 97;
    $kk = 98 - $rem;
    $iban = 'BE' . str_pad((string) $kk, 2, '0', STR_PAD_LEFT) . $bban;  // 16 chars
    return ['iban' => $iban, 'bank' => $bank3, 'acc' => $acc7, 'cle' => str_pad((string) $nat, 2, '0', STR_PAD_LEFT)];
}

// === 1. Constantes de config (corrige "configuration du module semble incomplete") ===
foreach ([
    'PAYMENTBYBANKTRANSFER_ID_BANKACCOUNT' => '1',  // compte emetteur = Belfius (rowid 1)
    'PAYMENTBYBANKTRANSFER_USER'           => '1',  // user admin
    'PAYMENTBYBANKTRANSFER_ADDDAYS'        => '0',
] as $name => $val) {
    q($db, "DELETE FROM {$P}const WHERE name='" . $db->real_escape_string($name) . "' AND entity=1");
    q($db, "INSERT INTO {$P}const (name, value, type, visible, entity) VALUES ('" . $db->real_escape_string($name) . "', '" . $db->real_escape_string($val) . "', 'chaine', 0, 1)");
}
echo "1) Config module OK (ID_BANKACCOUNT=1, USER=1)\n";

// === 2. Compte emetteur Belfius : IBAN/BIC/pays valides ===
$emit = be_iban('539', '0075470');                  // BExx 5390 0754 70xx
q($db, "UPDATE {$P}bank_account
        SET iban_prefix='" . $emit['iban'] . "', bic='GKCCBEBB', fk_pays=2,
            code_banque='539', number='0075470', cle_rib='" . $emit['cle'] . "',
            domiciliation='Belfius Banque', proprio='Bruxelles Formation'
        WHERE rowid=1");
echo "2) Compte emetteur Belfius -> IBAN " . $emit['iban'] . " / GKCCBEBB\n";

// === 3. IBAN belge valide + RIB par defaut pour chaque tiers a payer ===
$soc_ids = [];
$r = $db->query("SELECT DISTINCT f.fk_soc FROM {$P}facture_fourn f WHERE f.paye=0 AND f.fk_statut>=1");
while ($o = $r->fetch_object()) $soc_ids[] = (int) $o->fk_soc;

$nb_rib = 0;
foreach ($soc_ids as $sid) {
    // deja un RIB ban ? on saute
    $c = $db->query("SELECT rowid FROM {$P}societe_rib WHERE fk_soc=$sid AND type='ban' LIMIT 1");
    if ($c && $c->num_rows > 0) continue;

    $nom = '';
    $rn = $db->query("SELECT nom FROM {$P}societe WHERE rowid=$sid");
    if ($rn && ($on = $rn->fetch_object())) $nom = $on->nom;

    // compte unique deterministe par tiers
    $acc = str_pad((string) (1000000 + $sid), 7, '0', STR_PAD_LEFT);
    $be  = be_iban('068', $acc);

    q($db, "INSERT INTO {$P}societe_rib
            (type, label, fk_soc, datec, bank, code_banque, number, cle_rib, bic, iban_prefix,
             domiciliation, proprio, default_rib, fk_country, country_code, currency_code)
            VALUES ('ban', 'Compte Belfius', $sid, NOW(), 'Belfius', '068', '" . $be['acc'] . "', '" . $be['cle'] . "',
                    'GKCCBEBB', '" . $be['iban'] . "', 'Belfius Banque',
                    '" . $db->real_escape_string($nom) . "', 1, 2, 'BE', 'EUR')");
    $nb_rib++;
}
echo "3) RIB crees : $nb_rib (tiers a payer : " . count($soc_ids) . ")\n";

// === 4. Reset des demandes de virement en attente (demo propre) ===
$before = 0;
$rb = $db->query("SELECT COUNT(*) c FROM {$P}prelevement_demande WHERE traite=0");
if ($rb && ($ob = $rb->fetch_object())) $before = (int) $ob->c;
q($db, "DELETE FROM {$P}prelevement_demande WHERE traite=0");
echo "4) Demandes en attente supprimees : $before -> 0 (le bouton repartira de zero)\n";

echo "\nOK - demo prete. Recharge 'Paiements a effectuer' puis clique 'Preparer le lot'.\n";
$db->close();
