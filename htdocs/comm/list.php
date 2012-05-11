<?php

/* Copyright (C) 2001-2006 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis@dolibarr.fr>
 * Copyright (C) 2011      Philippe Grand       <philippe.grand@atoo-net.com>
 * Copyright (C) 2011-2012 Herve Prot           <herve.prot@symeos.com>
 * Copyright (C) 2011      Patrick Mary           <laube@hotmail.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/comm/list.php
 *	\ingroup    commercial societe
 *	\brief      List of customers
 */

require("../main.inc.php");
require_once(DOL_DOCUMENT_ROOT."/core/class/html.formother.class.php");

$langs->load("companies");
$langs->load("customers");
$langs->load("suppliers");
$langs->load("commercial");

// Security check
$socid = GETPOST("socid");
if ($user->societe_id)
    $socid = $user->societe_id;
$result = restrictedArea($user, 'societe', $socid, '');

$type = GETPOST("type", 'int');
$pstcomm = GETPOST("pstcomm");
$search_sale = GETPOST("search_sale");

$object = new Societe($couch);

if($_GET['json']=="list")
{
    $output = array(
    "sEcho" => intval($_GET['sEcho']),
    "iTotalRecords" => 0,
    "iTotalDisplayRecords" => 0,
    "aaData" => array()
     );

    $result = $object->getView("societe","list");

    //print_r($result);
    //exit;
    $iTotal=  count($result->rows);
    $output["iTotalRecords"]=$iTotal;
    $output["iTotalDisplayRecords"]=$iTotal;

    foreach($result->rows AS $aRow) {
       unset($aRow->value->class);
       unset($aRow->value->_rev);
       $output["aaData"][]=$aRow->value;
       unset($aRow);
    }

    header('Content-type: application/json');
    echo json_encode($output);
    exit;
}

if($_GET['json']=="edit")
{
    sleep(1);
    print "sauv";
    
    exit;//ajouter fonction
}

/*
 * Actions
 */
if ($_GET["action"] == 'cstc') {
    $sql = "UPDATE " . MAIN_DB_PREFIX . "societe SET fk_stcomm = " . $_GET["stcomm"];
    $sql .= " WHERE rowid = " . $_GET["socid"];
    $result = $db->query($sql);
}
// Select every potentiels.
$sql = "SELECT code, label, sortorder";
$sql.= " FROM ".MAIN_DB_PREFIX."c_prospectlevel";
$sql.= " WHERE active > 0";
$sql.= " ORDER BY sortorder";
$resql = $db->query($sql);
if ($resql)
{
    $tab_level = array();
    while ($obj = $db->fetch_object($resql))
        {     
            $level=$obj->code;
            // Put it in the array sorted by sortorder
            $tab_level[$obj->sortorder] = $level;
        }

 // Added by Matelli (init list option)
   $options = '<option value="">&nbsp;</option>';
   foreach ($tab_level as $tab_level_label)
     {
     $options .= '<option value="'.$tab_level_label.'">';
     $options .= $langs->trans($tab_level_label);
     $options .= '</option>';
     }        
}

/*
 * View
 */

$htmlother = new FormOther($db);


llxHeader('', $langs->trans("ThirdParty"), $help_url, '', '', '', '');

if ($type != '') {
    if ($type == 0)
        $titre = $langs->trans("ListOfSuspects");
    elseif ($type == 1)
        $titre = $langs->trans("ListOfProspects");
    else
        $titre = $langs->trans("ListOfCustomers");
}
else
    $titre = $langs->trans("ListOfAll");

print '<div class="row">';

print start_box($titre,"twelve","16-Companies.png");

$i=0;
$obj=new stdClass();

print '<table class="display dt_act" id="societe" >';
// Ligne des titres 
print'<thead>';
print'<tr>';
print'<th>';
print'</th>';
$obj->aoColumns[$i]->mDataProp = "_id";
$obj->aoColumns[$i]->bUseRendered = false;
$obj->aoColumns[$i]->bSearchable = false;
$obj->aoColumns[$i]->bVisible = false;
$i++;
print'<th class="essential">';
print $langs->trans("Company");
print'</th>';
$obj->aoColumns[$i]->mDataProp = "ThirdPartyName";
$obj->aoColumns[$i]->bUseRendered = true;
$obj->aoColumns[$i]->bSearchable = true;
$obj->aoColumns[$i]->fnRender= '%function(obj) {
var ar = [];
ar[ar.length] = "<a href=\"'.DOL_URL_ROOT.'/societe/fiche.php?id=";
ar[ar.length] = obj.aData._id;
ar[ar.length] = "\"><img src=\"'.DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/ico/icSw2/16-Apartment-Building.png\" border=\"0\" alt=\"Afficher societe : ";
ar[ar.length] = obj.aData.ThirdPartyName.toString();
ar[ar.length] = "\" title=\"Afficher soci&eacute;t&eacute; : ";
ar[ar.length] = obj.aData.ThirdPartyName.toString();
ar[ar.length] = "\"></a> <a href=\"'.DOL_URL_ROOT.'/societe/fiche.php?id=";
ar[ar.length] = obj.aData._id;
ar[ar.length] = "\">";
ar[ar.length] = obj.aData.ThirdPartyName.toString();
ar[ar.length] = "</a>";
var str = ar.join("");
return str;
}%';
$i++;
print'<th class="essential">';
print $langs->trans("Town");
print'</th>';
$obj->aoColumns[$i]->mDataProp = "Town";
$obj->aoColumns[$i]->sClass = "center edit";
$obj->aoColumns[$i]->sDefaultContent = "";
$i++;
print'<th class="essential">';
print $langs->trans("Zip");
print'</th>';
$obj->aoColumns[$i]->mDataProp = "Zip";
$obj->aoColumns[$i]->sClass = "center";
$obj->aoColumns[$i]->bVisible = false;
$obj->aoColumns[$i]->sDefaultContent = "";
$i++;
/*if (empty($conf->global->SOCIETE_DISABLE_STATE)) {
    print'<th class="essential">';
    print $langs->trans("State");
    print'</th>';
}*/
if ($conf->categorie->enabled) {
    print'<th class="essential">';
    print $langs->trans('Categories');
    print'</th>';
    $obj->aoColumns[$i]->mDataProp = "category";
    $obj->aoColumns[$i]->sDefaultContent = "";
    $obj->aoColumns[$i]->sClass = "edit";
    $i++;
}
print'<th class="essential">';
print $langs->trans('SalesRepresentatives');
print'</th>';
$obj->aoColumns[$i]->mDataProp = "SalesRepresentatives";
$obj->aoColumns[$i]->sDefaultContent = "";
$i++;
print'<th class="essential">';
print $langs->trans('Siren');
print'</th>';
$obj->aoColumns[$i]->mDataProp = "idprof1";
$obj->aoColumns[$i]->bVisible = false;
$obj->aoColumns[$i]->sDefaultContent = "";
$i++;
print'<th class="essential">';
print $langs->trans('Ape');
print'</th>';
$obj->aoColumns[$i]->mDataProp = "idprof2";
$obj->aoColumns[$i]->bVisible = false;
$obj->aoColumns[$i]->sDefaultContent = "";
$i++;
print'<th class="essential">';
print $langs->trans("ProspectLevelShort");
print'</th>';
$obj->aoColumns[$i]->mDataProp = "potentiel";
$obj->aoColumns[$i]->sDefaultContent = "";
$i++;
print'<th class="essential">';
print $langs->trans("Status");
print'</th>';
$obj->aoColumns[$i]->mDataProp = "Status";
$obj->aoColumns[$i]->sClass = "center";
$obj->aoColumns[$i]->sWidth = "100px";
$obj->aoColumns[$i]->sDefaultContent = "ST_NEVER";
$obj->aoColumns[$i]->fnRender = '%function(obj) {
var status = new Array();
var stcomm = obj.aData.Status;
if(typeof stcomm === "undefined")
    stcomm = "ST_NEVER";';
foreach ($object->fk_status->values as $key => $aRow)
{
    $obj->aoColumns[$i]->fnRender.= 'status["'.$key.'"]= new Array("'.$langs->trans($aRow->label).'","'.$aRow->cssClass.'");';
}
$obj->aoColumns[$i]->fnRender.= 'var ar = [];
ar[ar.length] = "<span class=\"lbl ";
ar[ar.length] = status[stcomm][1];
ar[ar.length] = " sl_status\">";
ar[ar.length] = status[stcomm][0];
ar[ar.length] = "</span>";
var str = ar.join("");
return str;
}%';
$i++;
print'<th class="essential">';
print $langs->trans("Date");
print'</th>';
$obj->aoColumns[$i]->mDataProp = "tms";
$obj->aoColumns[$i]->sType="date";
$obj->aoColumns[$i]->sClass = "center";
$obj->aoColumns[$i]->sWidth = "200px";
$obj->aoColumns[$i]->fnRender = '%function(obj) {
if(obj.aData.tms)
{
    var date = new Date(obj.aData.tms*1000);
    return date.toLocaleDateString();
}
else
    return null;
}%';
print'</tr>';
print'</thead>';
print'<tfoot>';
/* input search view */
$i=0;
print'<tr>';
print'<th id="'.$i.'"></th>';
$i++;
print'<th id="'.$i.'"><input type="text" placeholder="' . $langs->trans("Search Company") . '" /></th>';
$i++;
print'<th id="'.$i.'"><input type="text" placeholder="' . $langs->trans("Search Town") . '" /></th>';
$i++;
print'<th id="'.$i.'"><input type="text" placeholder="' . $langs->trans("Search Zip") . '" /></th>';
$i++;
/*if(empty($conf->global->SOCIETE_DISABLE_STATE)) {
    print'<th></th>';
    $i++;
}*/
if ($conf->categorie->enabled) {
        print'<th id="'.$i.'"><input type="text" placeholder="' . $langs->trans("Search category") . '" /></th>';
        $i++;
}
print'<th id="'.$i.'"><input type="text" placeholder="' . $langs->trans("Search sales") . '" /></th>';
$i++;
print'<th id="'.$i.'"><input type="text" placeholder="' . $langs->trans("Search siren") . '" /></th>';
$i++;
print'<th id="'.$i.'"></th>';
$i++;
print'<th id="'.$i.'"></th>';
$i++;
print'<th id="'.$i.'"></th>';
$i++;
print'<th id="'.$i.'"></th>';
$i++;
print'</tr>';
print'</tfoot>';
print'<tbody>';
print'</tbody>';

print "</table>";

print $object->_datatables($obj,"societe",true,true);

print end_box();
print '</div>'; // end row

llxFooter();
?>
 
