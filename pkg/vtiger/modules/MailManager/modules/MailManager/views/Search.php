<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.1
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/

/**
 * Class used to controll search operations
 */
class MailManager_Search_View extends MailManager_Relation_View {

    /**
     * Processes the request for search Operation
     * @global <type> $current_user
     * @param Vtiger_Request $request
     * @return boolean
     */
	function process(Vtiger_Request $request) {
	
		$response = new Vtiger_Response(true);
		$viewer = $this->getViewer($request);
		if ('popupui' == $this->getOperationArg($request)) {
			$viewer->view( 'Search.Popupui.tpl', 'MailManager' );
			$response = false;
			
		} else if ('email' == $this->getOperationArg($request)) {
			global $current_user;

			$searchTerm = $request->get('q');
			if (empty($searchTerm)) $searchTerm = '%@'; // To avoid empty value of email to be filtered.
			else $searchTerm = "%$searchTerm%";
			
			$filteredResult = MailManager::lookupMailInVtiger($searchTerm, $current_user);

			MailManager_Utils_Helper::emitJSON($filteredResult);
			$response = false;
		}
		return $response;
	}
}

?>