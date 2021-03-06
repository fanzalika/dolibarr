<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2015      Jean-François Ferry	<jfefe@aternatik.fr>
 * Copyright (C) 2019      Nicolas ZABOURI      <info@inovea-conseil.com>
 * Copyright (C) 2020      Pierre Ardoin        <mapiolca@me.com>
 * Copyright (C) 2020      Tobias Sekan         <tobias.sekan@startmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/comm/index.php
 *	\ingroup    commercial
 *	\brief      Home page of commercial area
 */

require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/client.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/agenda.lib.php';
if (!empty($conf->contrat->enabled)) require_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
if (!empty($conf->propal->enabled))  require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
if (!empty($conf->supplier_proposal->enabled))  require_once DOL_DOCUMENT_ROOT.'/supplier_proposal/class/supplier_proposal.class.php';
if (!empty($conf->commande->enabled))  require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
if (!empty($conf->fournisseur->enabled) && empty($conf->global->MAIN_USE_NEW_SUPPLIERMOD) || ! empty($conf->supplier_order->enabled)) require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';

if (!$user->rights->societe->lire) accessforbidden();

$hookmanager = new HookManager($db);

// Initialize technical object to manage hooks. Note that conf->hooks_modules contains array
$hookmanager->initHooks(array('commercialindex'));

// Load translation files required by the page
$langs->loadLangs(array("commercial", "propal"));

$action = GETPOST('action', 'alpha');
$bid = GETPOST('bid', 'int');

// Securite acces client
$socid = GETPOST('socid', 'int');
if (isset($user->socid) && $user->socid > 0) {
	$action = '';
	$socid = $user->socid;
}

$max = 3;
$now = dol_now();

/*
 * Actions
 */


/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);
$companystatic = new Societe($db);
if (!empty($conf->propal->enabled)) $propalstatic = new Propal($db);
if (!empty($conf->supplier_proposal->enabled)) $supplierproposalstatic = new SupplierProposal($db);
if (!empty($conf->commande->enabled)) $orderstatic = new Commande($db);
if (!empty($conf->fournisseur->enabled) && empty($conf->global->MAIN_USE_NEW_SUPPLIERMOD) || !empty($conf->supplier_order->enabled)) $supplierorderstatic = new CommandeFournisseur($db);

llxHeader("", $langs->trans("CommercialArea"));

print load_fiche_titre($langs->trans("CommercialArea"), '', 'commercial');

print '<div class="fichecenter"><div class="fichethirdleft">';

if (!empty($conf->global->MAIN_SEARCH_FORM_ON_HOME_AREAS)) {		// This is useless due to the global search combo
	// Search proposal
	if (!empty($conf->propal->enabled) && $user->rights->propal->lire) {
		$listofsearchfields['search_proposal'] = array('text'=>'Proposal');
	}
	// Search customer order
	if (!empty($conf->commande->enabled) && $user->rights->commande->lire) {
		$listofsearchfields['search_customer_order'] = array('text'=>'CustomerOrder');
	}
	// Search supplier proposal
	if (!empty($conf->supplier_proposal->enabled) && $user->rights->supplier_proposal->lire) {
		$listofsearchfields['search_supplier_proposal'] = array('text'=>'SupplierProposalShort');
	}
	// Search supplier order
	if ((!empty($conf->fournisseur->enabled) && empty($conf->global->MAIN_USE_NEW_SUPPLIERMOD) || !empty($conf->supplier_order->enabled)) && $user->rights->fournisseur->commande->lire) {
		$listofsearchfields['search_supplier_order'] = array('text'=>'SupplierOrder');
	}
	// Search intervention
	if (!empty($conf->ficheinter->enabled) && $user->rights->ficheinter->lire) {
		$listofsearchfields['search_intervention'] = array('text'=>'Intervention');
	}
	// Search contract
	if (!empty($conf->contrat->enabled) && $user->rights->contrat->lire) {
		$listofsearchfields['search_contract'] = array('text'=>'Contract');
	}

	if (count($listofsearchfields)) {
		print '<form method="post" action="'.DOL_URL_ROOT.'/core/search.php">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<div class="div-table-responsive-no-min">';
		print '<table class="noborder nohover centpercent">';
		$i = 0;
		foreach ($listofsearchfields as $key => $value) {
			if ($i == 0) print '<tr class="liste_titre"><td colspan="3">'.$langs->trans("Search").'</td></tr>';
			print '<tr '.$bc[false].'>';
			print '<td class="nowrap"><label for="'.$key.'">'.$langs->trans($value["text"]).'</label></td><td><input type="text" class="flat inputsearch" name="'.$key.'" id="'.$key.'" size="18"></td>';
			if ($i == 0) print '<td class="noborderbottom" rowspan="'.count($listofsearchfields).'"><input type="submit" value="'.$langs->trans("Search").'" class="button "></td>';
			print '</tr>';
			$i++;
		}
		print '</table>';
		print '</div>';
		print '</form>';
		print '<br>';
	}
}


/*
 * Draft customer proposals
 */
if (!empty($conf->propal->enabled) && $user->rights->propal->lire) {
	$langs->load("propal");

	$sql = "SELECT p.rowid, p.ref, p.ref_client, p.total_ht, p.tva as total_tva, p.total as total_ttc, s.rowid as socid, s.nom as name, s.client, s.canvas";
	$sql .= ", s.code_client";
	$sql .= ", s.email";
	$sql .= ", s.entity";
	$sql .= ", s.code_compta";
	$sql .= " FROM ".MAIN_DB_PREFIX."propal as p";
	$sql .= ", ".MAIN_DB_PREFIX."societe as s";
	if (!$user->rights->societe->client->voir && !$socid) $sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
	$sql .= " WHERE p.fk_statut = 0";
	$sql .= " AND p.fk_soc = s.rowid";
	$sql .= " AND p.entity IN (".getEntity('propal').")";
	if (!$user->rights->societe->client->voir && !$socid) $sql .= " AND s.rowid = sc.fk_soc AND sc.fk_user = ".$user->id;
	if ($socid)	$sql .= " AND s.rowid = ".$socid;

	$resql = $db->query($sql);
	if ($resql) {
		$total = 0;
		$num = $db->num_rows($resql);
		$nbofloop = min($num, (empty($conf->global->MAIN_MAXLIST_OVERLOAD) ? 500 : $conf->global->MAIN_MAXLIST_OVERLOAD));
		startSimpleTable("ProposalsDraft", "comm/propal/list.php", "search_status=0", 2, $num);

		if ($num > 0) {
			$i = 0;

			while ($i < $nbofloop) {
				$obj = $db->fetch_object($resql);

				$propalstatic->id = $obj->rowid;
				$propalstatic->ref = $obj->ref;
				$propalstatic->ref_client = $obj->ref_client;
				$propalstatic->total_ht = $obj->total_ht;
				$propalstatic->total_tva = $obj->total_tva;
				$propalstatic->total_ttc = $obj->total_ttc;

				$companystatic->id = $obj->socid;
				$companystatic->name = $obj->name;
				$companystatic->client = $obj->client;
				$companystatic->code_client = $obj->code_client;
				$companystatic->code_fournisseur = $obj->code_fournisseur;
				$companystatic->canvas = $obj->canvas;
				$companystatic->entity = $obj->entity;
				$companystatic->email = $obj->email;
				$companystatic->code_compta = $obj->code_compta;

				print '<tr class="oddeven">';
				print '<td class="nowrap">'.$propalstatic->getNomUrl(1).'</td>';
				print '<td class="nowrap">'.$companystatic->getNomUrl(1, 'customer', 16).'</td>';
				print '<td class="nowrap right">'.price($obj->total_ht).'</td>';
				print '</tr>';

				$i++;
				$total += $obj->total_ht;
			}
		}

		addSummaryTableLine(3, $num, $nbofloop, $total, "NoProposal");
		finishSimpleTable(true);
		$db->free($resql);
	} else {
		dol_print_error($db);
	}
}


/*
 * Draft supplier proposals
 */
if (!empty($conf->supplier_proposal->enabled) && $user->rights->supplier_proposal->lire) {
	$langs->load("supplier_proposal");

	$sql = "SELECT p.rowid, p.ref, p.total_ht, p.tva as total_tva, p.total as total_ttc, s.rowid as socid, s.nom as name, s.client, s.canvas";
	$sql .= ", s.code_client";
	$sql .= ", s.code_fournisseur";
	$sql .= ", s.entity";
	$sql .= ", s.email";
	$sql .= " FROM ".MAIN_DB_PREFIX."supplier_proposal as p";
	$sql .= ", ".MAIN_DB_PREFIX."societe as s";
	if (!$user->rights->societe->client->voir && !$socid) $sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
	$sql .= " WHERE p.fk_statut = 0";
	$sql .= " AND p.fk_soc = s.rowid";
	$sql .= " AND p.entity IN (".getEntity('supplier_proposal').")";
	if (!$user->rights->societe->client->voir && !$socid) $sql .= " AND s.rowid = sc.fk_soc AND sc.fk_user = ".$user->id;
	if ($socid)	$sql .= " AND s.rowid = ".$socid;

	$resql = $db->query($sql);
	if ($resql) {
		$total = 0;
		$num = $db->num_rows($resql);
		$nbofloop = min($num, (empty($conf->global->MAIN_MAXLIST_OVERLOAD) ? 500 : $conf->global->MAIN_MAXLIST_OVERLOAD));
		startSimpleTable("SupplierProposalsDraft", "supplier_proposal/list.php", "search_status=0", 2, $num);

		if ($num > 0) {
			$i = 0;

			while ($i < $nbofloop) {
				$obj = $db->fetch_object($resql);

				$supplierproposalstatic->id = $obj->rowid;
				$supplierproposalstatic->ref = $obj->ref;
				$supplierproposalstatic->total_ht = $obj->total_ht;
				$supplierproposalstatic->total_tva = $obj->total_tva;
				$supplierproposalstatic->total_ttc = $obj->total_ttc;

				$companystatic->id = $obj->socid;
				$companystatic->name = $obj->name;
				$companystatic->client = $obj->client;
				$companystatic->code_client = $obj->code_client;
				$companystatic->code_fournisseur = $obj->code_fournisseur;
				$companystatic->canvas = $obj->canvas;
				$companystatic->entity = $obj->entity;
				$companystatic->email = $obj->email;

				print '<tr class="oddeven">';
				print '<td  class="nowrap">'.$supplierproposalstatic->getNomUrl(1).'</td>';
				print '<td class="nowrap">'.$companystatic->getNomUrl(1, 'supplier', 16).'</td>';
				print '<td class="nowrap right">'.price($obj->total_ht).'</td>';
				print '</tr>';

				$i++;
				$total += $obj->total_ht;
			}
		}

		addSummaryTableLine(3, $num, $nbofloop, $total, "NoProposal");
		finishSimpleTable(true);
		$db->free($resql);
	} else {
		dol_print_error($db);
	}
}


/*
 * Draft customer orders
 */
if (!empty($conf->commande->enabled) && $user->rights->commande->lire) {
	$langs->load("orders");

	$sql = "SELECT c.rowid, c.ref, c.ref_client, c.total_ht, c.tva as total_tva, c.total_ttc, s.rowid as socid, s.nom as name, s.client, s.canvas";
	$sql .= ", s.code_client";
	$sql .= ", s.email";
	$sql .= ", s.entity";
	$sql .= ", s.code_compta";
	$sql .= " FROM ".MAIN_DB_PREFIX."commande as c";
	$sql .= ", ".MAIN_DB_PREFIX."societe as s";
	if (!$user->rights->societe->client->voir && !$socid) $sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
	$sql .= " WHERE c.fk_soc = s.rowid";
	$sql .= " AND c.fk_statut = 0";
	$sql .= " AND c.entity IN (".getEntity('commande').")";
	if (!$user->rights->societe->client->voir && !$socid) $sql .= " AND s.rowid = sc.fk_soc AND sc.fk_user = ".$user->id;
	if ($socid)	$sql .= " AND c.fk_soc = ".$socid;

	$resql = $db->query($sql);
	if ($resql) {
		$total = 0;
		$num = $db->num_rows($resql);
		$nbofloop = min($num, (empty($conf->global->MAIN_MAXLIST_OVERLOAD) ? 500 : $conf->global->MAIN_MAXLIST_OVERLOAD));
		startSimpleTable("DraftOrders", "commande/list.php", "search_status=0", 2, $num);

		if ($num > 0) {
			$i = 0;

			while ($i < $nbofloop) {
				$obj = $db->fetch_object($resql);

				$orderstatic->id = $obj->rowid;
				$orderstatic->ref = $obj->ref;
				$orderstatic->ref_client = $obj->ref_client;
				$orderstatic->total_ht = $obj->total_ht;
				$orderstatic->total_tva = $obj->total_tva;
				$orderstatic->total_ttc = $obj->total_ttc;

				$companystatic->id = $obj->socid;
				$companystatic->name = $obj->name;
				$companystatic->client = $obj->client;
				$companystatic->code_client = $obj->code_client;
				$companystatic->code_fournisseur = $obj->code_fournisseur;
				$companystatic->canvas = $obj->canvas;
				$companystatic->email = $obj->email;
				$companystatic->entity = $obj->entity;

				print '<tr class="oddeven">';
				print '<td class="nowrap">'.$orderstatic->getNomUrl(1).'</td>';
				print '<td class="nowrap">'.$companystatic->getNomUrl(1, 'customer', 16).'</td>';
				print '<td class="nowrap right">'.price(!empty($conf->global->MAIN_DASHBOARD_USE_TOTAL_HT) ? $obj->total_ht : $obj->total_ttc).'</td>';
				print '</tr>';

				$i++;
				$total += $obj->total_ttc;
			}
		}

		addSummaryTableLine(3, $num, $nbofloop, $total, "NoProposal");
		finishSimpleTable(true);
		$db->free($resql);
	} else {
		dol_print_error($db);
	}
}


/*
 * Draft suppliers orders
 */
if ((!empty($conf->fournisseur->enabled) && empty($conf->global->MAIN_USE_NEW_SUPPLIERMOD) || !empty($conf->supplier_order->enabled)) && $user->rights->fournisseur->commande->lire) {
	$langs->load("orders");

	$sql = "SELECT cf.rowid, cf.ref, cf.ref_supplier, cf.total_ttc, s.rowid as socid, s.nom as name, s.client, s.canvas";
	$sql .= ", s.code_client";
	$sql .= ", s.code_fournisseur";
	$sql .= ", s.entity";
	$sql .= ", s.email";
	$sql .= " FROM ".MAIN_DB_PREFIX."commande_fournisseur as cf";
	$sql .= ", ".MAIN_DB_PREFIX."societe as s";
	if (!$user->rights->societe->client->voir && !$socid) $sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
	$sql .= " WHERE cf.fk_soc = s.rowid";
	$sql .= " AND cf.fk_statut = 0";
	$sql .= " AND cf.entity IN (".getEntity('supplier_order').")";
	if (!$user->rights->societe->client->voir && !$socid) $sql .= " AND s.rowid = sc.fk_soc AND sc.fk_user = ".$user->id;
	if ($socid)	$sql .= " AND cf.fk_soc = ".$socid;

	$resql = $db->query($sql);
	if ($resql) {
		$total = 0;
		$num = $db->num_rows($resql);
		$nbofloop = min($num, (empty($conf->global->MAIN_MAXLIST_OVERLOAD) ? 500 : $conf->global->MAIN_MAXLIST_OVERLOAD));
		startSimpleTable("DraftSuppliersOrders", "fourn/commande/list.php", "search_status=0", 2, $num);

		if ($num > 0) {
			$i = 0;

			while ($i < $nbofloop) {
				$obj = $db->fetch_object($resql);

				$supplierorderstatic->id = $obj->rowid;
				$supplierorderstatic->ref = $obj->ref;
				$supplierorderstatic->ref_supplier = $obj->ref_suppliert;
				$supplierorderstatic->total_ht = $obj->total_ht;
				$supplierorderstatic->total_tva = $obj->total_tva;
				$supplierorderstatic->total_ttc = $obj->total_ttc;

				$companystatic->id = $obj->socid;
				$companystatic->name = $obj->name;
				$companystatic->client = $obj->client;
				$companystatic->code_client = $obj->code_client;
				$companystatic->code_fournisseur = $obj->code_fournisseur;
				$companystatic->canvas = $obj->canvas;
				$companystatic->entity = $obj->entity;
				$companystatic->email = $obj->email;

				print '<tr class="oddeven">';
				print '<td class="nowrap">'.$supplierorderstatic->getNomUrl(1).'</td>';
				print '<td class="nowrap">'.$companystatic->getNomUrl(1, 'supplier', 16).'</td>';
				print '<td class="nowrap right">'.price(!empty($conf->global->MAIN_DASHBOARD_USE_TOTAL_HT) ? $obj->total_ht : $obj->total_ttc).'</td>';
				print '</tr>';

				$i++;
				$total += $obj->total_ttc;
			}
		}

		addSummaryTableLine(3, $num, $nbofloop, $total, "NoProposal");
		finishSimpleTable(true);
		$db->free($resql);
	} else {
		dol_print_error($db);
	}
}

print '</div><div class="fichetwothirdright"><div class="ficheaddleft">';

$max = 3;

/*
 * Last modified customers or prospects
 */
if (!empty($conf->societe->enabled) && $user->rights->societe->lire) {
	$langs->load("boxes");

	$sql = "SELECT s.rowid, s.nom as name, s.client, s.datec, s.tms, s.canvas";
	$sql .= ", s.code_client";
	$sql .= ", s.code_compta";
	$sql .= ", s.entity";
	$sql .= ", s.email";
	$sql .= " FROM ".MAIN_DB_PREFIX."societe as s";
	if (!$user->rights->societe->client->voir && !$socid) $sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
	$sql .= " WHERE s.client IN (1, 2, 3)";
	$sql .= " AND s.entity IN (".getEntity($companystatic->element).")";
	if (!$user->rights->societe->client->voir && !$socid) $sql .= " AND s.rowid = sc.fk_soc AND sc.fk_user = ".$user->id;
	if ($socid)	$sql .= " AND s.rowid = $socid";
	$sql .= " ORDER BY s.tms DESC";
	$sql .= $db->plimit($max, 0);

	$resql = $db->query($sql);
	if ($resql) {
		if (empty($conf->global->SOCIETE_DISABLE_PROSPECTS) && empty($conf->global->SOCIETE_DISABLE_CUSTOMERS)) {
			$header = "BoxTitleLastCustomersOrProspects";
		}
		elseif (!empty($conf->global->SOCIETE_DISABLE_CUSTOMERS)) {
			$header = "BoxTitleLastModifiedProspects";
		}
		else {
			$header = "BoxTitleLastModifiedCustomers";
		}

		$num = $db->num_rows($resql);
		startSimpleTable($langs->trans($header, min($max, $num)), "societe/list.php", "type=p,c", 1);

		if ($num) {
			$i = 0;

			while ($i < $num && $i < $max) {
				$objp = $db->fetch_object($resql);

				$companystatic->id = $objp->rowid;
				$companystatic->name = $objp->name;
				$companystatic->client = $objp->client;
				$companystatic->code_client = $objp->code_client;
				$companystatic->code_fournisseur = $objp->code_fournisseur;
				$companystatic->canvas = $objp->canvas;
				$companystatic->code_compta = $objp->code_compta;
				$companystatic->entity = $objp->entity;
				$companystatic->email = $objp->email;

				print '<tr class="oddeven">';
				print '<td class="nowrap">'.$companystatic->getNomUrl(1, 'customer', 48).'</td>';
				print '<td class="right" nowrap>'.$companystatic->getLibCustProspStatut().'</td>';
				print '<td class="right" nowrap>'.dol_print_date($db->jdate($objp->tms), 'day').'</td>';
				print '</tr>';

				$i++;
			}
		}

		addSummaryTableLine(3, $num);
		finishSimpleTable(true);
		$db->free($resql);
	} else {
		dol_print_error($db);
	}
}


/*
 * Last suppliers
 */
if ((!empty($conf->fournisseur->enabled) && empty($conf->global->MAIN_USE_NEW_SUPPLIERMOD) || !empty($conf->supplier_order->enabled) || !empty($conf->supplier_invoice->enabled)) && $user->rights->societe->lire) {
	$langs->load("boxes");

	$sql = "SELECT s.nom as name, s.rowid, s.datec as dc, s.canvas, s.tms as dm";
	$sql .= ", s.code_fournisseur";
	$sql .= ", s.entity";
	$sql .= ", s.email";
	$sql .= " FROM ".MAIN_DB_PREFIX."societe as s";
	if (!$user->rights->societe->client->voir && !$user->socid) $sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
	$sql .= " WHERE s.fournisseur = 1";
	$sql .= " AND s.entity IN (".getEntity($companystatic->element).")";
	if (!$user->rights->societe->client->voir && !$user->socid) $sql .= " AND s.rowid = sc.fk_soc AND sc.fk_user = ".$user->id;
	if ($socid)	$sql .= " AND s.rowid = ".$socid;
	$sql .= " ORDER BY s.datec DESC";
	$sql .= $db->plimit($max, 0);

	$resql = $db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		startSimpleTable($langs->trans("BoxTitleLastModifiedSuppliers", min($max, $num)), "societe/list.php", "type=f");

		if ($num) {
			$i = 0;
			while ($i < $num && $i < $max) {
				$objp = $db->fetch_object($resql);

				$companystatic->id = $objp->rowid;
				$companystatic->name = $objp->name;
				$companystatic->code_client = $objp->code_client;
				$companystatic->code_fournisseur = $objp->code_fournisseur;
				$companystatic->canvas = $objp->canvas;
				$companystatic->entity = $objp->entity;
				$companystatic->email = $objp->email;

				print '<tr class="oddeven">';
				print '<td class="nowrap">'.$companystatic->getNomUrl(1, 'supplier', 44).'</td>';
				print '<td class="right">'.dol_print_date($db->jdate($objp->dm), 'day').'</td>';
				print '</tr>';

				$i++;
			}
		}

		addSummaryTableLine(2, $num);
		finishSimpleTable(true);
		$db->free($resql);
	} else {
		dol_print_error($db);
	}
}


/*
 * Last actions
 */
if ($user->rights->agenda->myactions->read) {
	show_array_last_actions_done($max);
}


/*
 * Actions to do
 */
if ($user->rights->agenda->myactions->read) {
	show_array_actions_to_do(10);
}


/*
 * Latest contracts
 */
if (!empty($conf->contrat->enabled) && $user->rights->contrat->lire && 0) { // TODO A REFAIRE DEPUIS NOUVEAU CONTRAT
	$langs->load("contracts");

	$sql = "SELECT s.nom as name, s.rowid, s.canvas, ";
	$sql .= ", s.code_client";
	$sql .= ", s.entity";
	$sql .= ", s.email";
	$sql .= ", c.statut, c.rowid as contratid, p.ref, c.fin_validite as datefin, c.date_cloture as dateclo";
	$sql .= " FROM ".MAIN_DB_PREFIX."societe as s";
	$sql .= ", ".MAIN_DB_PREFIX."contrat as c";
	$sql .= ", ".MAIN_DB_PREFIX."product as p";
	if (!$user->rights->societe->client->voir && !$socid) $sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
	$sql .= " WHERE c.fk_soc = s.rowid";
	$sql .= " AND c.entity IN (".getEntity('contract').")";
	$sql .= " AND c.fk_product = p.rowid";
	if (!$user->rights->societe->client->voir && !$socid)	$sql .= " AND s.rowid = sc.fk_soc AND sc.fk_user = ".$user->id;
	if ($socid) $sql .= " AND s.rowid = ".$socid;
	$sql .= " ORDER BY c.tms DESC";
	$sql .= $db->plimit(5, 0);

	$resql = $db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		startSimpleTable($langs->trans("LastContracts", 5), "", "", 2);

		if ($num > 0) {
			$i = 0;
			$staticcontrat = new Contrat($db);

			while ($i < $num) {
				$obj = $db->fetch_object($resql);

				$companystatic->id = $objp->rowid;
				$companystatic->name = $objp->name;
				$companystatic->code_client = $objp->code_client;
				$companystatic->code_fournisseur = $objp->code_fournisseur;
				$companystatic->canvas = $objp->canvas;
				$companystatic->entity = $objp->entity;
				$companystatic->email = $objp->email;

				print '<tr class="oddeven">';
				print '<td><a href=\"../contrat/card.php?id=".$obj->contratid."\">".img_object($langs->trans("ShowContract","contract"), "contract")." ".$obj->ref."</a></td>';
				print '<td>'.$companystatic->getNomUrl(1, 'customer', 44).'</td>';
				print '<td class="right">'.$staticcontrat->LibStatut($obj->statut, 3).'</td>';
				print '</tr>';

				$i++;
			}
		}

		addSummaryTableLine(2, $num);
		finishSimpleTable(true);
		$db->free($resql);
	} else {
		dol_print_error($db);
	}
}


/*
 * Opened proposals
 */
if (!empty($conf->propal->enabled) && $user->rights->propal->lire) {
	$langs->load("propal");

	$sql = "SELECT s.nom as name, s.rowid, s.code_client";
	$sql .= ", s.entity";
	$sql .= ", s.email";
	$sql .= ", p.rowid as propalid, p.entity, p.total as total_ttc, p.total_ht, p.tva as total_tva, p.ref, p.ref_client, p.fk_statut, p.datep as dp, p.fin_validite as dfv";
	$sql .= " FROM ".MAIN_DB_PREFIX."societe as s";
	$sql .= ", ".MAIN_DB_PREFIX."propal as p";
	if (!$user->rights->societe->client->voir && !$socid) $sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
	$sql .= " WHERE p.fk_soc = s.rowid";
	$sql .= " AND p.entity IN (".getEntity('propal').")";
	$sql .= " AND p.fk_statut = 1";
	if (!$user->rights->societe->client->voir && !$socid) $sql .= " AND s.rowid = sc.fk_soc AND sc.fk_user = ".$user->id;
	if ($socid) $sql .= " AND s.rowid = ".$socid;
	$sql .= " ORDER BY p.rowid DESC";

	$resql = $db->query($sql);
	if ($resql) {
		$total = 0;
		$num = $db->num_rows($resql);
		$nbofloop = min($num, (empty($conf->global->MAIN_MAXLIST_OVERLOAD) ? 500 : $conf->global->MAIN_MAXLIST_OVERLOAD));
		startSimpleTable("ProposalsOpened", "comm/propal/list.php", "search_status=1", 4, $num);

		if ($num > 0) {
			$i = 0;

			while ($i < $nbofloop) {
				$obj = $db->fetch_object($resql);

				$propalstatic->id = $obj->propalid;
				$propalstatic->ref = $obj->ref;
				$propalstatic->ref_client = $obj->ref_client;
				$propalstatic->total_ht = $obj->total_ht;
				$propalstatic->total_tva = $obj->total_tva;
				$propalstatic->total_ttc = $obj->total_ttc;

				$companystatic->id = $obj->rowid;
				$companystatic->name = $obj->name;
				$companystatic->client = $obj->client;
				$companystatic->code_client = $obj->code_client;
				$companystatic->code_fournisseur = $obj->code_fournisseur;
				$companystatic->canvas = $obj->canvas;
				$companystatic->entity = $obj->entity;
				$companystatic->email = $obj->email;

				print '<tr class="oddeven">';

				// Ref
				print '<td class="nowrap" width="140">';
				print '<table class="nobordernopadding"><tr class="nocellnopadd">';
				print '<td class="nobordernopadding nowrap">';
				print $propalstatic->getNomUrl(1);
				print '</td>';
				print '<td width="18" class="nobordernopadding nowrap">';
				if ($db->jdate($obj->dfv) < ($now - $conf->propal->cloture->warning_delay)) print img_warning($langs->trans("Late"));
				print '</td>';
				print '<td width="16" align="center" class="nobordernopadding">';
				$filename = dol_sanitizeFileName($obj->ref);
				$filedir = $conf->propal->multidir_output[$obj->entity].'/'.dol_sanitizeFileName($obj->ref);
				$urlsource = $_SERVER['PHP_SELF'].'?id='.$obj->propalid;
				print $formfile->getDocumentsLink($propalstatic->element, $filename, $filedir);
				print '</td></tr></table>';
				print "</td>";

				print '<td class="nowrap">'.$companystatic->getNomUrl(1, 'customer', 44).'</td>';
				print '<td class="right">'.dol_print_date($db->jdate($obj->dp), 'day').'</td>';
				print '<td class="right">'.price(!empty($conf->global->MAIN_DASHBOARD_USE_TOTAL_HT) ? $obj->total_ht : $obj->total_ttc).'</td>';
				print '<td align="center" width="14">'.$propalstatic->LibStatut($obj->fk_statut, 3).'</td>';

				print '</tr>';

				$i++;
				$total += $obj->total_ttc;
			}
		}

		addSummaryTableLine(5, $num, $nbofloop, $total, "NoProposal", true);
		finishSimpleTable(true);
		$db->free($resql);
	} else {
		dol_print_error($db);
	}
}


/*
 * Opened Order
 */
if (!empty($conf->commande->enabled) && $user->rights->commande->lire) {
	$langs->load("orders");

	$sql = "SELECT s.nom as name, s.rowid, c.rowid as commandeid, c.total_ttc, c.total_ht, c.tva as total_tva, c.ref, c.ref_client, c.fk_statut, c.date_valid as dv, c.facture as billed";
	$sql .= ", s.code_client";
	$sql .= ", s.entity";
	$sql .= ", s.email";
	$sql .= " FROM ".MAIN_DB_PREFIX."societe as s";
	$sql .= ", ".MAIN_DB_PREFIX."commande as c";
	if (!$user->rights->societe->client->voir && !$socid) $sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
	$sql .= " WHERE c.fk_soc = s.rowid";
	$sql .= " AND c.entity IN (".getEntity('commande').")";
	$sql .= " AND (c.fk_statut = ".Commande::STATUS_VALIDATED." or c.fk_statut = ".Commande::STATUS_SHIPMENTONPROCESS.")";
	if (!$user->rights->societe->client->voir && !$socid) $sql .= " AND s.rowid = sc.fk_soc AND sc.fk_user = ".$user->id;
	if ($socid) $sql .= " AND s.rowid = ".$socid;
	$sql .= " ORDER BY c.rowid DESC";

	$resql = $db->query($sql);
	if ($resql) {
		$total = 0;
		$num = $db->num_rows($resql);
		$nbofloop = min($num, (empty($conf->global->MAIN_MAXLIST_OVERLOAD) ? 500 : $conf->global->MAIN_MAXLIST_OVERLOAD));
		startSimpleTable("OrdersOpened", "commande/list.php", "search_status=1", 4, $num);

		if ($num > 0) {
			$i = 0;

			while ($i < $nbofloop) {
				$obj = $db->fetch_object($resql);

				$orderstatic->id = $obj->commandeid;
				$orderstatic->ref = $obj->ref;
				$orderstatic->ref_client = $obj->ref_client;
				$orderstatic->total_ht = $obj->total_ht;
				$orderstatic->total_tva = $obj->total_tva;
				$orderstatic->total_ttc = $obj->total_ttc;

				$companystatic->id = $obj->rowid;
				$companystatic->name = $obj->name;
				$companystatic->client = $obj->client;
				$companystatic->code_client = $obj->code_client;
				$companystatic->code_fournisseur = $obj->code_fournisseur;
				$companystatic->canvas = $obj->canvas;
				$companystatic->entity = $obj->entity;
				$companystatic->email = $obj->email;

				print '<tr class="oddeven">';

				// Ref
				print '<td class="nowrap" width="140">';
				print '<table class="nobordernopadding"><tr class="nocellnopadd">';
				print '<td class="nobordernopadding nowrap">';
				print $orderstatic->getNomUrl(1);
				print '</td>';
				print '<td width="18" class="nobordernopadding nowrap">';
				//if ($db->jdate($obj->dfv) < ($now - $conf->propal->cloture->warning_delay)) print img_warning($langs->trans("Late"));
				print '</td>';
				print '<td width="16" align="center" class="nobordernopadding">';
				$filename = dol_sanitizeFileName($obj->ref);
				$filedir = $conf->commande->dir_output.'/'.dol_sanitizeFileName($obj->ref);
				$urlsource = $_SERVER['PHP_SELF'].'?id='.$obj->propalid;
				print $formfile->getDocumentsLink($orderstatic->element, $filename, $filedir);
				print '</td></tr></table>';
				print "</td>";

				print '<td class="nowrap">'.$companystatic->getNomUrl(1, 'customer', 44).'</td>';
				print '<td class="right">'.dol_print_date($db->jdate($obj->dp), 'day').'</td>';
				print '<td class="right">'.price(!empty($conf->global->MAIN_DASHBOARD_USE_TOTAL_HT) ? $obj->total_ht : $obj->total_ttc).'</td>';
				print '<td align="center" width="14">'.$orderstatic->LibStatut($obj->fk_statut, $obj->billed, 3).'</td>';
				print '</tr>'."\n";

				$i++;
				$total += $obj->total_ttc;
			}
		}

		addSummaryTableLine(5, $num, $nbofloop, $num, $total, "None", true);
		finishSimpleTable(true);
		$db->free($resql);
	} else {
		dol_print_error($db);
	}
}

print '</div></div></div>';

$parameters = array('user' => $user);
$reshook = $hookmanager->executeHooks('dashboardCommercials', $parameters, $object); // Note that $action and $object may have been modified by hook

// End of page
llxFooter();
$db->close();
