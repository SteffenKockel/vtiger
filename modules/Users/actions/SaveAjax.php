<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/
vimport('~~/include/Webservices/Custom/ChangePassword.php');

class Users_SaveAjax_Action extends Vtiger_SaveAjax_Action {
	
	function __construct() {
		parent::__construct();
		$this->exposeMethod('userExists');
		$this->exposeMethod('savePassword');
	}

	public function checkPermission(Vtiger_Request $request) {
		$currentUserModel = Users_Record_Model::getCurrentUserModel();

		if(!$currentUserModel->isAdminUser()) {
			throw new AppException('LBL_PERMISSION_DENIED', 'Vtiger');
		}
	}

	public function process(Vtiger_Request $request) {
		
		$mode = $request->get('mode');
		if (!empty($mode)) {
			$this->invokeExposedMethod($mode, $request);
			return;
		}
		
		$recordModel = $this->saveRecord($request);

		$fieldModelList = $recordModel->getModule()->getFields();
		$result = array();
		foreach ($fieldModelList as $fieldName => $fieldModel) {
			$fieldValue = $displayValue = Vtiger_Util_Helper::toSafeHTML($recordModel->get($fieldName));
			if ($fieldModel->getFieldDataType() !== 'currency') {
				$displayValue = $fieldModel->getDisplayValue($fieldValue, $recordModel->getId());
			}
			if($fieldName == 'language') {
				$displayValue =  Vtiger_Language_Handler::getLanguageLabel($fieldValue);
			}
			$result[$fieldName] = array('value' => $fieldValue, 'display_value' => $displayValue);
		}

		$result['_recordLabel'] = $recordModel->getName();
		$result['_recordId'] = $recordModel->getId();

		$response = new Vtiger_Response();
		$response->setEmitType(Vtiger_Response::$EMIT_JSON);
		$response->setResult($result);
		$response->emit();
	}

	/**
	 * Function to get the record model based on the request parameters
	 * @param Vtiger_Request $request
	 * @return Vtiger_Record_Model or Module specific Record Model instance
	 */
	public function getRecordModelFromRequest(Vtiger_Request $request) {
		$recordModel = parent::getRecordModelFromRequest($request);
		$fieldName = $request->get('field');

		if ($fieldName === 'is_admin' && !$request->get('value')) {
			$recordModel->set($fieldName, 'off');
		}
		return $recordModel;
	}
	
		
	public function userExists(Vtiger_Request $request){
		$module = $request->getModule();
		$userName = $request->get('user_name');
		$userModuleModel = Users_Module_Model::getCleanInstance($module);
		$status = $userModuleModel->checkDuplicateUser($userName);
		$response = new Vtiger_Response();
		$response->setResult($status);
		$response->emit();
	}
	
	public function savePassword(Vtiger_Request $request) {
		$module = $request->getModule();
		$userModel = vglobal('current_user');
		$newPassword = $request->get('new_password');
		$oldPassword = $request->get('old_password');
		
		$wsUserId = vtws_getWebserviceEntityId($module, $request->get('userid'));
		$wsStatus = vtws_changePassword($wsUserId, $oldPassword, $newPassword, $newPassword, $userModel);
		
		$response = new Vtiger_Response();
		if ($wsStatus['message']) {
			$response->setResult($wsStatus);
		} else {
			$response->setError('JS_PASSWORD_INCORRECT_OLD', 'JS_PASSWORD_INCORRECT_OLD');
		}
		$response->emit();
	}
}
