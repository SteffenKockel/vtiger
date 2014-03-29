<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

Class Settings_Webforms_ShowForm_View extends Settings_Vtiger_IndexAjax_View {

	public function checkPermission(Vtiger_Request $request) {
		parent::checkPermission($request);

		$recordId = $request->get('record');
		$moduleModel = Vtiger_Module_Model::getInstance($request->getModule());

		$currentUserPrivilegesModel = Users_Privileges_Model::getCurrentUserPrivilegesModel();
		if(!$recordId || !$currentUserPrivilegesModel->hasModulePermission($moduleModel->getId())) {
			throw new AppException('LBL_PERMISSION_DENIED');
		}
	}

	public function process(Vtiger_Request $request) {
		$recordId = $request->get('record');
		$qualifiedModuleName = $request->getModule(false);
		$moduleName = $request->getModule();

		$recordModel = Settings_Webforms_Record_Model::getInstanceById($recordId, $qualifiedModuleName);
		$selectedFieldsList = $recordModel->getSelectedFieldsList();
		foreach ($selectedFieldsList as $fieldName => $fieldModel) {
			if (Settings_Webforms_Record_Model::isCustomField($fieldName)) {
				$dataType = $fieldModel->getFieldDataType();
				if ($dataType != 'picklist' && $dataType != 'multipicklist') {
					$fieldModel->set('name', 'label:'.str_replace(' ', '_', html_entity_decode($fieldModel->get('label'))));
				}
			}
		}

		$viewer = $this->getViewer($request);
		$viewer->assign('RECORD_ID', $recordId);
		$viewer->assign('RECORD_MODEL', $recordModel);
		$viewer->assign('MODULE', $moduleName);
		$viewer->assign('QUALIFIED_MODULE', $qualifiedModuleName);
		$viewer->assign('SELECTED_FIELD_MODELS_LIST', $selectedFieldsList);
		$viewer->assign('ACTION_PATH', vglobal('site_URL').'/modules/Webforms/capture.php');
		$viewer->assign('USER_MODEL', Users_Record_Model::getCurrentUserModel());

		$viewer->view('ShowForm.tpl', $qualifiedModuleName);
	}
}