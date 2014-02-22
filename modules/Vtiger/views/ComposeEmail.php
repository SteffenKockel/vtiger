<?php
/* +***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * *********************************************************************************** */

class Vtiger_ComposeEmail_View extends Vtiger_Footer_View {

	function __construct() {
		parent::__construct();
		$this->exposeMethod('emailPreview');
        $this->exposeMethod('previewPrint');
		$this->exposeMethod('emailForward');
		$this->exposeMethod('emailEdit');
		$this->exposeMethod('composeMailData');
	}

	public function checkPermission(Vtiger_Request $request) {
		$moduleName = $request->getModule();

		if (!Users_Privileges_Model::isPermitted($moduleName, 'EditView')) {
			throw new AppException('LBL_PERMISSION_DENIED');
		}
	}

    function preProcess(Vtiger_Request $request, $display=true) {
        if($request->getMode() == 'previewPrint'){
            return;
        }
        return parent::preProcess($request,$display);
    }

	public function composeMailData($request){
		$moduleName = 'Emails';
		$moduleModel = Vtiger_Module_Model::getInstance($moduleName);
		$userRecordModel = Users_Record_Model::getCurrentUserModel();
		$sourceModule = $request->getModule();
		$cvId = $request->get('viewname');
		$selectedIds = $request->get('selected_ids',array());
		$excludedIds = $request->get('excluded_ids',array());
		$selectedFields = $request->get('selectedFields');
		$relatedLoad = $request->get('relatedLoad');
		$documentIds = $request->get('documentIds');

		$viewer = $this->getViewer($request);
		$viewer->assign('MODULE', $moduleName);
		$viewer->assign('VIEWNAME', $cvId);
		$viewer->assign('SELECTED_IDS', $selectedIds);
		$viewer->assign('EXCLUDED_IDS', $excludedIds);
		$viewer->assign('USER_MODEL', $userRecordModel);
		$viewer->assign('MAX_UPLOAD_SIZE', vglobal('upload_maxsize'));
		$viewer->assign('RELATED_MODULES', $moduleModel->getEmailRelatedModules());
		if ($documentIds) {
			$attachements = array();
			foreach ($documentIds as $documentId) {
				$documentRecordModel = Vtiger_Record_Model::getInstanceById($documentId, $sourceModule);
				if ($documentRecordModel->get('filelocationtype') == 'I') {
					$fileDetails = $documentRecordModel->getFileDetails();
					if ($fileDetails) {
						$fileDetails['fileid'] = $fileDetails['attachmentsid'];
						$fileDetails['docid'] = $fileDetails['crmid'];
						$fileDetails['attachment'] = $fileDetails['name'];
						$fileDetails['size'] = filesize($fileDetails['path'] . $fileDetails['attachmentsid'] . "_". $fileDetails['name']);
						$attachements[] = $fileDetails;
					}
				}
			}
			$viewer->assign('ATTACHMENTS', $attachements);
		}
        
        $searchKey = $request->get('search_key');
        $searchValue = $request->get('search_value');
		$operator = $request->get('operator');
        if(!empty($operator)) {
			$viewer->assign('OPERATOR',$operator);
			$viewer->assign('ALPHABET_VALUE',$searchValue);
            $viewer->assign('SEARCH_KEY',$searchKey);
		}
		
		$to =array();
		$toMailInfo = array();
		$selectIds = $this->getRecordsListFromRequest($request);

		$ccMailInfo = $request->get('ccemailinfo');
		if(empty($ccMailInfo)){
			$ccMailInfo = array();
		}

		$bccMailInfo = $request->get('bccemailinfo');
		if(empty($bccMailInfo)){
			$bccMailInfo = array();
		}
		
		foreach($selectIds as $id) {
			$recordModel = Vtiger_Record_Model::getInstanceById($id);
			if($selectedFields){
				foreach($selectedFields as $field) {
					$value = $recordModel->get($field);
					$emailOptOutValue = $recordModel->get('emailoptout');
					if(!empty($value) && (!$emailOptOutValue)) {
						$to[] =	$recordModel->get($field);
						$toMailInfo[$id][] = $value;
					}
				}
			}
		}
		$documentsModel = Vtiger_Module_Model::getInstance('Documents');
		$documentsURL = $documentsModel->getInternalDocumentsURL();

		$emailTemplateModuleModel = Settings_Vtiger_Module_Model::getInstance('Settings:EmailTemplates');
		$emailTemplateListURL = $emailTemplateModuleModel->getListViewUrl();
		
		$viewer->assign('DOCUMENTS_URL', $documentsURL);
		$viewer->assign('EMAIL_TEMPLATE_URL', $emailTemplateListURL);
		$viewer->assign('TO', $to);
		$viewer->assign('TOMAIL_INFO', $toMailInfo);
		$viewer->assign('CC', $request->get('cc'));
		$viewer->assign('CCMAIL_INFO', $ccMailInfo);
		$viewer->assign('BCC', $request->get('bcc'));
		$viewer->assign('BCCMAIL_INFO', $bccMailInfo);
		
		//EmailTemplate module percission check
		$userPrevilegesModel = Users_Privileges_Model::getCurrentUserPrivilegesModel();
		$viewer->assign('MODULE_IS_ACTIVE', $userPrevilegesModel->hasModulePermission(Vtiger_Module_Model::getInstance('EmailTemplates')->getId()));
		//
		
		if($relatedLoad){
			$viewer->assign('RELATED_LOAD', true);
		}
	}


	public function emailActionsData($request){
		$recordId = $request->get('record');
		$moduleName = $request->getModule();
		$viewer = $this->getViewer($request);
		$attachment = array();
		
		if(!$this->record) {
			$this->record = Vtiger_DetailView_Model::getInstance($moduleName, $recordId);
		}
		$recordModel = $this->record->getRecord();
		
		$this->composeMailData($request);
		$subject = $recordModel->get('subject');
		$description = $recordModel->get('description');
		$attachmentDetails = $recordModel->getAttachmentDetails();

        $viewer->assign('SUBJECT', $subject);
        $viewer->assign('DESCRIPTION', $description);
		$viewer->assign('ATTACHMENTS', $attachmentDetails);
        $viewer->assign('PARENT_EMAIL_ID', $recordId);
		$viewer->assign('PARENT_RECORD', $request->get('parentId'));
	}

	public function process(Vtiger_Request $request) {
		$mode = $request->getMode();
		if(!empty($mode)) {
			echo $this->invokeExposedMethod($mode, $request);
			return;
		}
		$this->composeMailData($request);
		$viewer = $this->getViewer($request);
		echo $viewer->view('ComposeEmailForm.tpl', $moduleName, true);
	}

	function postProcess(Vtiger_Request $request) {
		return;
	}

	public function getRecordsListFromRequest(Vtiger_Request $request) {
		$cvId = $request->get('viewname');
		$selectedIds = $request->get('selected_ids');
		$excludedIds = $request->get('excluded_ids');

		if(!empty($selectedIds) && $selectedIds != 'all') {
			if(!empty($selectedIds) && count($selectedIds) > 0) {
				return $selectedIds;
			}
		}

		$sourceRecord = $request->get('sourceRecord');
		$sourceModule = $request->get('sourceModule');
		if ($sourceRecord && $sourceModule) {
			$sourceRecordModel = Vtiger_Record_Model::getInstanceById($sourceRecord, $sourceModule);
			return $sourceRecordModel->getSelectedIdsList($request->get('parentModule'), $excludedIds);
		}

		$customViewModel = CustomView_Record_Model::getInstanceById($cvId);
		if($customViewModel) {
            $searchKey = $request->get('search_key');
            $searchValue = $request->get('search_value');
            $operator = $request->get('operator');
            if(!empty($operator)) {
                $customViewModel->set('operator', $operator);
                $customViewModel->set('search_key', $searchKey);
                $customViewModel->set('search_value', $searchValue);
            }
			return $customViewModel->getRecordIds($excludedIds);
		}
        return array();
	}

	/**
	 * Function to get the list of Script models to be included
	 * @param Vtiger_Request $request
	 * @return <Array> - List of Vtiger_JsScript_Model instances
	 */
	function getHeaderScripts(Vtiger_Request $request) {
		$headerScriptInstances = parent::getHeaderScripts($request);
		$moduleName = $request->getModule();

		$jsFileNames = array(
			"libraries.jquery.ckeditor.ckeditor",
			"libraries.jquery.ckeditor.adapters.jquery",
			'modules.Vtiger.resources.validator.BaseValidator',
			'modules.Vtiger.resources.validator.FieldValidator',
			"modules.Emails.resources.MassEdit",
			"modules.Emails.resources.EmailPreview",
			"modules.Vtiger.resources.CkEditor",
			'modules.Vtiger.resources.Popup',
			'libraries.jquery.jquery_windowmsg',
			'libraries.jquery.multiplefileupload.jquery_MultiFile'
		);

		$jsScriptInstances = $this->checkAndConvertJsScripts($jsFileNames);
		$headerScriptInstances = array_merge($headerScriptInstances, $jsScriptInstances);
		return $headerScriptInstances;
	}

	function emailPreview($request){
		$recordId = $request->get('record');
		$moduleName = $request->getModule();

		if(!$this->record){
		$this->record = Vtiger_DetailView_Model::getInstance($moduleName, $recordId);
		}
		$recordModel = $this->record->getRecord();

		$viewer = $this->getViewer($request);
		$TO = Zend_Json::decode(html_entity_decode($recordModel->get('saved_toid')));
		$CC = Zend_Json::decode(html_entity_decode($recordModel->get('ccmail')));
		$BCC = Zend_Json::decode(html_entity_decode($recordModel->get('bccmail')));
		
		$parentId = $request->get('parentId');
		if(empty($parentId)) {
			list($parentRecord, $status) = explode('@', reset(array_filter(explode('|', $recordModel->get('parent_id')))));
			$parentId = $parentRecord;
		}

		$viewer->assign('FROM', $recordModel->get('from_email'));
		$viewer->assign('TO',$TO);
		$viewer->assign('CC', implode(',',$CC));
		$viewer->assign('BCC', implode(',',$BCC));
		$viewer->assign('RECORD', $recordModel);
		$viewer->assign('MODULE', $moduleName);
		$viewer->assign('RECORD_ID', $recordId);
		$viewer->assign('PARENT_RECORD', $parentId);

		if($request->get('mode') == 'previewPrint') {
			$viewer->assign('USER_MODEL', Users_Record_Model::getCurrentUserModel());
            echo $viewer->view('EmailPreviewPrint.tpl',$moduleName,true);
        }else{
            echo $viewer->view('EmailPreview.tpl',$moduleName,true);
        }

	}

	function emailEdit($request){
		$viewer = $this->getViewer($request);
		$this->emailActionsData($request);

		$recordId = $request->get('record');
		$moduleName = $request->getModule();
		$viewer = $this->getViewer($request);

		if(!$this->record){
		$this->record = Vtiger_DetailView_Model::getInstance($moduleName, $recordId);
		}
		$recordModel = $this->record->getRecord();

		$TO = Zend_Json::decode(html_entity_decode($recordModel->get('saved_toid')));
		$CC = Zend_Json::decode(html_entity_decode($recordModel->get('ccmail')));
		$BCC = Zend_Json::decode(html_entity_decode($recordModel->get('bccmail')));

        $parentIds = explode('|',$recordModel->get('parent_id'));


        $toMailInfo = array();
        foreach($parentIds as $index=>$parentFieldId) {
            if(empty($parentFieldId)){
                continue;
            }
            $parentIdComponents = explode('@',$parentFieldId);
            $parentId = $parentIdComponents[0];
            //expecting parent ids and to will be same order
            $toMailInfo[$parentId][] = $TO[$index];
        }

		$viewer->assign('TO',$TO);
        $viewer->assign('TOMAIL_INFO', $toMailInfo);
		$viewer->assign('CC', implode(',',$CC));
		$viewer->assign('BCC', implode(',',$BCC));
        $viewer->assign('RECORDID', $request->get('record'));
		$viewer->assign('RELATED_LOAD', true);
		$viewer->assign('EMAIL_MODE', 'edit');
		echo $viewer->view('ComposeEmailForm.tpl', $moduleName, true);
	}

	function emailForward($request){
		$viewer = $this->getViewer($request);
		$moduleName = $request->getModule();
		$this->emailActionsData($request);
		$viewer->assign('TO', '');
		$viewer->assign('TOMAIL_INFO', '');
        $viewer->assign('RELATED_LOAD', true);
		$viewer->assign('EMAIL_MODE', 'forward');
		echo $viewer->view('ComposeEmailForm.tpl', $moduleName, true);
	}

    public function previewPrint($request) {
        $this->emailPreview($request);
    }
}
