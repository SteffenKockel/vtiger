<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

Class Settings_SharingAccess_SaveAjax_Action extends Vtiger_SaveAjax_Action {

	public function process(Vtiger_Request $request) {
		$modulePermissions = $request->get('permissions');
		$modulePermissions[4] = $modulePermissions[6];

		foreach($modulePermissions as $tabId => $permission) {
			$moduleModel = Settings_SharingAccess_Module_Model::getInstance($tabId);
			$moduleModel->set('permission', $permission);

			try {
				$moduleModel->save();
			} catch (AppException $e) {
				
			}
		}
		Settings_SharingAccess_Module_Model::recalculateSharingRules();

		$response = new Vtiger_Response();
		$response->setEmitType(Vtiger_Response::$EMIT_JSON);
		$response->emit();
	}
}