<?php
/*+********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ********************************************************************************/

require_once('include/database/PearDatabase.php');
require_once('user_privileges/default_module_view.php');

global $adb, $singlepane_view, $currentModule;
$idlist = vtlib_purify($_REQUEST['idlist']);
$dest_mod = vtlib_purify($_REQUEST['destination_module']);
$parenttab = getParentTab();

$forCRMRecord = vtlib_purify($_REQUEST['parentid']);

if($singlepane_view == 'true')
	$action = "DetailView";
else
	$action = "CallRelatedList";

$storearray = array();
if(!empty($_REQUEST['idlist'])) {
	// Split the string of ids
	$storearray = explode (";",trim($idlist,";"));
} else if(!empty($_REQUEST['entityid'])){
	$storearray = array($_REQUEST['entityid']);
}
$focus = CRMEntity::getInstance($currentModule);
foreach($storearray as $id)
{
	if($id != '')
	{			
		if($dest_mod == 'Contacts') { //When we select contact from potential related list
			$sql = "insert into vtiger_contpotentialrel values (?,?)";
			$adb->pquery($sql, array($id, $forCRMRecord));
		} elseif($dest_mod == 'Products') {//when we select product from potential related list
			$sql = "insert into vtiger_seproductsrel values (?,?,?)";
			$adb->pquery($sql, array($forCRMRecord, $id,'Potentials'));
		} elseif($dest_mod == 'Documents') {
			$sql = "insert into vtiger_senotesrel values (?,?)";
			$adb->pquery($sql, array($forCRMRecord, $id));
		} else {
			$focus->save_related_module($currentModule, $forCRMRecord, $dest_mod, $id);
		}
	}
}

header("Location: index.php?action=$action&module=$currentModule&record=".$forCRMRecord."&parenttab=".$parenttab);

?>