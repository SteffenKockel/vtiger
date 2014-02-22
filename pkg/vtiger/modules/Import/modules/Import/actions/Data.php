<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************ */

require_once 'include/Webservices/Create.php';
require_once 'include/Webservices/Update.php';
require_once 'include/Webservices/Delete.php';
require_once 'include/Webservices/Revise.php';
require_once 'include/Webservices/Retrieve.php';
require_once 'include/Webservices/DataTransform.php';
require_once 'vtlib/Vtiger/Utils.php';
require_once 'data/CRMEntity.php';
require_once 'include/QueryGenerator/QueryGenerator.php';
require_once 'vtlib/Vtiger/Mailer.php';

class Import_Data_Action extends Vtiger_Action_Controller {

	var $id;
	var $user;
	var $module;
	var $fieldMapping;
	var $mergeType;
	var $mergeFields;
	var $defaultValues;
	var $importedRecordInfo = array();
    protected $allPicklistValues = array();
	var $batchImport = true;

	static $IMPORT_RECORD_NONE = 0;
	static $IMPORT_RECORD_CREATED = 1;
	static $IMPORT_RECORD_SKIPPED = 2;
	static $IMPORT_RECORD_UPDATED = 3;
	static $IMPORT_RECORD_MERGED = 4;
	static $IMPORT_RECORD_FAILED = 5;

	public function __construct($importInfo, $user) {
		$this->id = $importInfo['id'];
		$this->module = $importInfo['module'];
		$this->fieldMapping = $importInfo['field_mapping'];
		$this->mergeType = $importInfo['merge_type'];
		$this->mergeFields = $importInfo['merge_fields'];
		$this->defaultValues = $importInfo['default_values'];
		$this->user = $user;
	}

	public function process(Vtiger_Request $request) {
		return;
	}

	public function getDefaultFieldValues($moduleMeta) {
		static $cachedDefaultValues = array();

		if (isset($cachedDefaultValues[$this->module])) {
			return $cachedDefaultValues[$this->module];
		}

		$defaultValues = array();
		if (!empty($this->defaultValues)) {
			if(!is_array($this->defaultValues)) {
				$this->defaultValues = Zend_Json::decode($this->defaultValues);
			}
			if($this->defaultValues != null) {
				$defaultValues = $this->defaultValues;
			}
		}
		$moduleFields = $moduleMeta->getModuleFields();
		$moduleMandatoryFields = $moduleMeta->getMandatoryFields();
		foreach ($moduleMandatoryFields as $mandatoryFieldName) {
			if (empty($defaultValues[$mandatoryFieldName])) {
				$fieldInstance = $moduleFields[$mandatoryFieldName];
				if($fieldInstance->getFieldDataType() == 'owner') {
					$defaultValues[$mandatoryFieldName] = $this->user->id;
				} elseif($fieldInstance->getFieldDataType() != 'datetime'
						&& $fieldInstance->getFieldDataType() != 'date'
						&& $fieldInstance->getFieldDataType() != 'time' && $fieldInstance->getFieldDataType() != 'reference') {
					$defaultValues[$mandatoryFieldName] = '????';
				}
			}
		}
		foreach ($moduleFields as $fieldName => $fieldInstance) {
			$fieldDefaultValue = $fieldInstance->getDefault();
			if(empty ($defaultValues[$fieldName])) {
				if($fieldInstance->getUIType() == '52') {
					$defaultValues[$fieldName] = $this->user->id;
				} elseif(!empty($fieldDefaultValue)) {
					$defaultValues[$fieldName] = $fieldDefaultValue;
				}
			}
		}
		$className = get_class($moduleMeta);
		if ($className != 'VtigerLineItemMeta') {
			$cachedDefaultValues[$this->module] = $defaultValues;
		}
		return $defaultValues;
	}

	public function import() {
		if(!$this->initializeImport()) return false;
		$this->importData();
		$this->finishImport();
	}

	public function importData() {
		$focus = CRMEntity::getInstance($this->module);
		$moduleModel = Vtiger_Module_Model::getInstance($this->module);
        // pre fetch the fields and premmisions of module
        Vtiger_Field_Model::getAllForModule($moduleModel);
        if($this->user->is_admin == 'off'){
            Vtiger_Field_Model::preFetchModuleFieldPermission($moduleModel->getId());
        }
        if(method_exists($focus, 'createRecords')) {
			$focus->createRecords($this);
		} else {
			$this->createRecords();
		}
		$this->updateModuleSequenceNumber();
	}

	public function initializeImport() {
		$lockInfo = Import_Lock_Action::isLockedForModule($this->module);
		if ($lockInfo != null) {
			if($lockInfo['userid'] != $this->user->id) {
				Import_Utils_Helper::showImportLockedError($lockInfo);
				return false;
			} else {
				return true;
			}
		} else {
			Import_Lock_Action::lock($this->id, $this->module, $this->user);
			return true;
		}
	}

	public function finishImport() {
		Import_Lock_Action::unLock($this->user, $this->module);
		Import_Queue_Action::remove($this->id);
	}

	public function updateModuleSequenceNumber() {
		$moduleName = $this->module;
		$focus = CRMEntity::getInstance($moduleName);
		$focus->updateMissingSeqNumber($moduleName);
	}

	public function updateImportStatus($entryId, $entityInfo) {
		$adb = PearDatabase::getInstance();
		$recordId = null;
		if (!empty($entityInfo['id'])) {
			$entityIdComponents = vtws_getIdComponents($entityInfo['id']);
			$recordId = $entityIdComponents[1];
		}
		$adb->pquery('UPDATE ' . Import_Utils_Helper::getDbTableName($this->user) . ' SET status=?, recordid=? WHERE id=?',
				array($entityInfo['status'], $recordId, $entryId));
	}

	public function createRecords() {
		$adb = PearDatabase::getInstance();
		$moduleName = $this->module;

		$focus = CRMEntity::getInstance($moduleName);
		$moduleHandler = vtws_getModuleHandlerFromName($moduleName, $this->user);
		$moduleMeta = $moduleHandler->getMeta();
		$moduleObjectId = $moduleMeta->getEntityId();
		$moduleFields = $moduleMeta->getModuleFields();

		$tableName = Import_Utils_Helper::getDbTableName($this->user);
		$sql = 'SELECT * FROM ' . $tableName . ' WHERE status = '. Import_Data_Action::$IMPORT_RECORD_NONE;

		if($this->batchImport) {
			$configReader = new Import_Config_Model();
			$importBatchLimit = $configReader->get('importBatchLimit');
			$sql .= ' LIMIT '. $importBatchLimit;
		}
		$result = $adb->query($sql);
		$numberOfRecords = $adb->num_rows($result);

		if ($numberOfRecords <= 0) {
			return;
		}

		$fieldMapping = $this->fieldMapping;
		$fieldColumnMapping = $moduleMeta->getFieldColumnMapping();

		for ($i = 0; $i < $numberOfRecords; ++$i) {
			$row = $adb->raw_query_result_rowdata($result, $i);
			$rowId = $row['id'];
			$entityInfo = null;
			$fieldData = array();
			foreach ($fieldMapping as $fieldName => $index) {
				$fieldData[$fieldName] = $row[$fieldName];
			}

			$mergeType = $this->mergeType;
			$createRecord = false;

			if(method_exists($focus, 'importRecord')) {
				$entityInfo = $focus->importRecord($this, $fieldData);
			} else {
				if (!empty($mergeType) && $mergeType != Import_Utils_Helper::$AUTO_MERGE_NONE) {

					$queryGenerator = new QueryGenerator($moduleName, $this->user);
					$queryGenerator->initForDefaultCustomView();
					$fieldsList = array('id');
					$queryGenerator->setFields($fieldsList);

					$mergeFields = $this->mergeFields;
					foreach ($mergeFields as $index => $mergeField) {
						if ($index != 0) {
							$queryGenerator->addConditionGlue(QueryGenerator::$AND);
						}
						$comparisonValue = $fieldData[$mergeField];
						$fieldInstance = $moduleFields[$mergeField];
						if ($fieldInstance->getFieldDataType() == 'owner') {
							$userId = getUserId_Ol($comparisonValue);
							$comparisonValue = getUserFullName($userId);
						}
						if ($fieldInstance->getFieldDataType() == 'reference') {
							if(strpos($comparisonValue, '::::') > 0) {
								$referenceFileValueComponents = explode('::::', $comparisonValue);
							} else {
								$referenceFileValueComponents = explode(':::', $comparisonValue);
							}
							if (count($referenceFileValueComponents) > 1) {
								$comparisonValue = trim($referenceFileValueComponents[1]);
							}
						}
						$queryGenerator->addCondition($mergeField, $comparisonValue, 'e', '', '', '', true);
					}
					$query = $queryGenerator->getQuery();
					$duplicatesResult = $adb->query($query);
					$noOfDuplicates = $adb->num_rows($duplicatesResult);

                    if ($noOfDuplicates > 0) {
						if ($mergeType == Import_Utils_Helper::$AUTO_MERGE_IGNORE) {
							$entityInfo['status'] = self::$IMPORT_RECORD_SKIPPED;
						} elseif ($mergeType == Import_Utils_Helper::$AUTO_MERGE_OVERWRITE ||
								$mergeType == Import_Utils_Helper::$AUTO_MERGE_MERGEFIELDS) {

							for ($index = 0; $index < $noOfDuplicates - 1; ++$index) {
								$duplicateRecordId = $adb->query_result($duplicatesResult, $index, $fieldColumnMapping['id']);
								$entityId = vtws_getId($moduleObjectId, $duplicateRecordId);
								vtws_delete($entityId, $this->user);
							}
							$baseRecordId = $adb->query_result($duplicatesResult, $noOfDuplicates - 1, $fieldColumnMapping['id']);
							$baseEntityId = vtws_getId($moduleObjectId, $baseRecordId);

							if ($mergeType == Import_Utils_Helper::$AUTO_MERGE_OVERWRITE) {
								$fieldData = $this->transformForImport($fieldData, $moduleMeta);
								$fieldData['id'] = $baseEntityId;
								$entityInfo = vtws_update($fieldData, $this->user);
								$entityInfo['status'] = self::$IMPORT_RECORD_UPDATED;
							}

							if ($mergeType == Import_Utils_Helper::$AUTO_MERGE_MERGEFIELDS) {
								$filteredFieldData = array();
								foreach ($fieldData as $fieldName => $fieldValue) {
									if (!empty($fieldValue)) {
										$filteredFieldData[$fieldName] = $fieldValue;
									}
								}
								
								// Custom handling for default values & mandatory fields
								// need to be taken care than normal import as we merge
								// existing record values with newer values.
								$fillDefault = false;
								$mandatoryValueChecks = false;
								
								$existingFieldValues = vtws_retrieve($baseEntityId, $this->user);
								$defaultFieldValues = $this->getDefaultFieldValues($moduleMeta);
								
								foreach ($existingFieldValues as $fieldName => $fieldValue) {
									if (empty($fieldValue)
											&& empty($filteredFieldData[$fieldName])
											&& !empty($defaultFieldValues[$fieldName])) {
										$filteredFieldData[$fieldName] = $defaultFieldValues[$fieldName];
									}
								}
								
								$filteredFieldData = $this->transformForImport($filteredFieldData, $moduleMeta, $fillDefault, $mandatoryValueChecks);
								$filteredFieldData['id'] = $baseEntityId;
								$entityInfo = vtws_revise($filteredFieldData, $this->user);
								$entityInfo['status'] = self::$IMPORT_RECORD_MERGED;
							}
						} else {
							$createRecord = true;
						}
					} else {
						$createRecord = true;
					}
				} else {
					$createRecord = true;
				}
				if ($createRecord) {
					$fieldData = $this->transformForImport($fieldData, $moduleMeta);
					if($fieldData == null) {
						$entityInfo = null;
					} else {
                        try{
                            $entityInfo = vtws_create($moduleName, $fieldData, $this->user);
                        } catch (Exception $e){
                            
                        }
					}
				}
			}
			if ($entityInfo == null) {
                $entityInfo = array('id' => null, 'status' => self::$IMPORT_RECORD_FAILED);
            } else if($createRecord){
                $entityInfo['status'] = self::$IMPORT_RECORD_CREATED;
                $entityIdComponents = vtws_getIdComponents($entityInfo['id']);
                $recordId = $entityIdComponents[1];
                $entityfields = getEntityFieldNames($this->module);
                switch($this->module) {
                   	case 'HelpDesk': $entityfields['fieldname'] = array('ticket_title');break;
					case 'Documents':$entityfields['fieldname'] = array('notes_title');break;
                }
                $label = '';
                if(is_array($entityfields['fieldname'])){
                    foreach($entityfields['fieldname'] as $field){
                        $label .= $fieldData[$field]." ";
                    }
                }else {
                    $label = $fieldData[$entityfields['fieldname']];
                }
                $label = trim($label);
                $adb->pquery('UPDATE vtiger_crmentity SET label=? WHERE crmid=?', array($label, $recordId));
            }

			$this->importedRecordInfo[$rowId] = $entityInfo;
			$this->updateImportStatus($rowId, $entityInfo);
		}
		unset($result);
		return true;
	}

	public function transformForImport($fieldData, $moduleMeta, $fillDefault=true, $checkMandatoryFieldValues=true) {
		$moduleFields = $moduleMeta->getModuleFields();
 		$defaultFieldValues = $this->getDefaultFieldValues($moduleMeta);
		foreach ($fieldData as $fieldName => $fieldValue) {
			$fieldInstance = $moduleFields[$fieldName];
			if ($fieldInstance->getFieldDataType() == 'owner') {
				$ownerId = getUserId_Ol(trim($fieldValue));
				if (empty($ownerId)) {
					$ownerId = getGrpId($fieldValue);
				}
				if (empty($ownerId) && isset($defaultFieldValues[$fieldName])) {
					$ownerId = $defaultFieldValues[$fieldName];
				}
				if(empty($ownerId) ||
							!Import_Utils_Helper::hasAssignPrivilege($moduleMeta->getEntityName(), $ownerId)) {
					$ownerId = $this->user->id;
				}
				$fieldData[$fieldName] = $ownerId;

			} elseif ($fieldInstance->getFieldDataType() == 'reference') {
				$entityId = false;
				if (!empty($fieldValue)) {
					if(strpos($fieldValue, '::::') > 0) {
						$fieldValueDetails = explode('::::', $fieldValue);
					} else if (strpos($fieldValue, ':::') > 0) {
						$fieldValueDetails = explode(':::', $fieldValue);
					} else {
						$fieldValueDetails = $fieldValue;
					}
					if (count($fieldValueDetails) > 1) {
						$referenceModuleName = trim($fieldValueDetails[0]);
						$entityLabel = trim($fieldValueDetails[1]);
						$entityId = getEntityId($referenceModuleName, $entityLabel);
					} else {
						$referencedModules = $fieldInstance->getReferenceList();
						$entityLabel = $fieldValue;
						foreach ($referencedModules as $referenceModule) {
							$referenceModuleName = $referenceModule;
							if ($referenceModule == 'Users') {
								$referenceEntityId = getUserId_Ol($entityLabel);
								if(empty($referenceEntityId) ||
										!Import_Utils_Helper::hasAssignPrivilege($moduleMeta->getEntityName(), $referenceEntityId)) {
									$referenceEntityId = $this->user->id;
								}
							}elseif ($referenceModule == 'Currency') {
								$referenceEntityId = getCurrencyId($entityLabel);
							} else {
								$referenceEntityId = getEntityId($referenceModule, $entityLabel);
							}
							if ($referenceEntityId != 0) {
								$entityId = $referenceEntityId;
								break;
							}
						}
					}
					if ((empty($entityId) || $entityId == 0) && !empty($referenceModuleName)) {
						if(isPermitted($referenceModuleName, 'EditView') == 'yes') {
                                                    try {
							$wsEntityIdInfo = $this->createEntityRecord($referenceModuleName, $entityLabel);
							$wsEntityId = $wsEntityIdInfo['id'];
							$entityIdComponents = vtws_getIdComponents($wsEntityId);
							$entityId = $entityIdComponents[1];
                                                    } catch (Exception $e) {
                                                        $entityId = false;
                                                    }
						}
					}
					$fieldData[$fieldName] = $entityId;
				} else {
					$referencedModules = $fieldInstance->getReferenceList();
					if ($referencedModules[0] == 'Users') {
						if(isset($defaultFieldValues[$fieldName])) {
							$fieldData[$fieldName] = $defaultFieldValues[$fieldName];
						}
						if(empty($fieldData[$fieldName]) ||
								!Import_Utils_Helper::hasAssignPrivilege($moduleMeta->getEntityName(), $fieldData[$fieldName])) {
							$fieldData[$fieldName] = $this->user->id;
						}
					} else {
						$fieldData[$fieldName] = '';
					}
				}

			} elseif ($fieldInstance->getFieldDataType() == 'picklist') {
				global $default_charset;
				if (empty($fieldValue) && isset($defaultFieldValues[$fieldName])) {
					$fieldData[$fieldName] = $fieldValue = $defaultFieldValues[$fieldName];
				}
				$olderCacheEnable = Vtiger_Cache::$cacheEnable;
				Vtiger_Cache::$cacheEnable = false;
                if(!isset($this->allPicklistValues[$fieldName])){
                    $this->allPicklistValues[$fieldName] = $fieldInstance->getPicklistDetails();
                } 
                $allPicklistDetails = $this->allPicklistValues[$fieldName];
               
				$allPicklistValues = array();
				foreach ($allPicklistDetails as $picklistDetails) {
					$allPicklistValues[] = $picklistDetails['value'];
				}

				$picklistValueInLowerCase = strtolower(htmlentities($fieldValue, ENT_QUOTES, $default_charset));
				$allPicklistValuesInLowerCase = array_map('strtolower', $allPicklistValues);
				$picklistDetails = array_combine($allPicklistValuesInLowerCase, $allPicklistValues);

				if (!in_array($picklistValueInLowerCase, $allPicklistValuesInLowerCase)) {
					$moduleObject = Vtiger_Module::getInstance($moduleMeta->getEntityName());
					$fieldObject = Vtiger_Field::getInstance($fieldName, $moduleObject);
					$fieldObject->setPicklistValues(array($fieldValue));
                    unset($this->allPicklistValues[$fieldName]);
				} else {
					$fieldData[$fieldName] = $picklistDetails[$picklistValueInLowerCase];
				}
				Vtiger_Cache::$cacheEnable = $olderCacheEnable;
			} else {
				if ($fieldInstance->getFieldDataType() == 'datetime' && !empty($fieldValue)) {
					if($fieldValue == null || $fieldValue == '0000-00-00 00:00:00') {
						$fieldValue = '';
					}
					$valuesList = explode(' ', $fieldValue);
					if(count($valuesList) == 1) $fieldValue = '';
					$fieldValue = getValidDBInsertDateTimeValue($fieldValue);
					if (preg_match("/^[0-9]{2,4}[-][0-1]{1,2}?[0-9]{1,2}[-][0-3]{1,2}?[0-9]{1,2} ([0-1][0-9]|[2][0-3])([:][0-5][0-9]){1,2}$/",
							$fieldValue) == 0) {
						$fieldValue = '';
					}
					$fieldData[$fieldName] = $fieldValue;
				}
				if ($fieldInstance->getFieldDataType() == 'date' && !empty($fieldValue)) {
					if($fieldValue == null || $fieldValue == '0000-00-00') {
						$fieldValue = '';
					}
					$fieldValue = getValidDBInsertDateValue($fieldValue);
					if (preg_match("/^[0-9]{2,4}[-][0-1]{1,2}?[0-9]{1,2}[-][0-3]{1,2}?[0-9]{1,2}$/", $fieldValue) == 0) {
						$fieldValue = '';
					}
					$fieldData[$fieldName] = $fieldValue;
				}
				if (empty($fieldValue) && isset($defaultFieldValues[$fieldName])) {
					$fieldData[$fieldName] = $fieldValue = $defaultFieldValues[$fieldName];
				}
			}
		}
		if($fillDefault) {
			foreach($defaultFieldValues as $fieldName => $fieldValue) {
				if (!isset($fieldData[$fieldName])) {
					$fieldData[$fieldName] = $defaultFieldValues[$fieldName];
				}
			}
		}

		// We should sanitizeData before doing final mandatory check below.
		$fieldData = DataTransform::sanitizeData($fieldData, $moduleMeta);
                
		if ($fieldData != null && $checkMandatoryFieldValues) {
			foreach ($moduleFields as $fieldName => $fieldInstance) {
				if(empty($fieldData[$fieldName]) && $fieldInstance->isMandatory()) {
					return null;
				}
			}
		}
		

		return $fieldData;
	}

	public function createEntityRecord($moduleName, $entityLabel) {
		$moduleHandler = vtws_getModuleHandlerFromName($moduleName, $this->user);
		$moduleMeta = $moduleHandler->getMeta();
		$moduleFields = $moduleMeta->getModuleFields();
		$mandatoryFields = $moduleMeta->getMandatoryFields();
		$entityNameFieldsString = $moduleMeta->getNameFields();
		$entityNameFields = explode(',', $entityNameFieldsString);
		$fieldData = array();
		foreach ($entityNameFields as $entityNameField) {
			$entityNameField = trim($entityNameField);
			if (in_array($entityNameField, $mandatoryFields)) {
				$fieldData[$entityNameField] = $entityLabel;
			}
		}
		foreach ($mandatoryFields as $mandatoryField) {
			if (empty($fieldData[$mandatoryField])) {
				$fieldInstance = $moduleFields[$mandatoryField];
				if ($fieldInstance->getFieldDataType() == 'owner') {
					$fieldData[$mandatoryField] = $this->user->id;
				} else if (!in_array($mandatoryField, $entityNameFields) && $fieldInstance->getFieldDataType() != 'reference') {
					$fieldData[$mandatoryField] = '????';
				}
			}
		}

		$fieldData = DataTransform::sanitizeData($fieldData, $moduleMeta);
		$entityIdInfo = vtws_create($moduleName, $fieldData, $this->user);
		$focus = CRMEntity::getInstance($moduleName);
		$focus->updateMissingSeqNumber($moduleName);
		return $entityIdInfo;
	}

	public function getImportStatusCount() {
		$adb = PearDatabase::getInstance();

		$tableName = Import_Utils_Helper::getDbTableName($this->user);
		$result = $adb->query('SELECT status FROM '.$tableName);

		$statusCount = array('TOTAL' => 0, 'IMPORTED' => 0, 'FAILED' => 0, 'PENDING' => 0,
								'CREATED' => 0, 'SKIPPED' => 0, 'UPDATED' => 0, 'MERGED' => 0);

		if($result) {
			$noOfRows = $adb->num_rows($result);
			$statusCount['TOTAL'] = $noOfRows;
			for($i=0; $i<$noOfRows; ++$i) {
				$status = $adb->query_result($result, $i, 'status');
				if(self::$IMPORT_RECORD_NONE == $status) {
					$statusCount['PENDING']++;

				} elseif(self::$IMPORT_RECORD_FAILED == $status) {
					$statusCount['FAILED']++;

				} else {
					$statusCount['IMPORTED']++;
					switch($status) {
						case self::$IMPORT_RECORD_CREATED	:	$statusCount['CREATED']++;
																break;
						case self::$IMPORT_RECORD_SKIPPED	:	$statusCount['SKIPPED']++;
																break;
						case self::$IMPORT_RECORD_UPDATED	:	$statusCount['UPDATED']++;
																break;
						case self::$IMPORT_RECORD_MERGED	:	$statusCount['MERGED']++;
																break;
					}
				}

			}
		}
		return $statusCount;
	}

	public static function runScheduledImport() {
		global $current_user;
		$scheduledImports = self::getScheduledImport();
		$vtigerMailer = new Vtiger_Mailer();
		$vtigerMailer->IsHTML(true);
		foreach ($scheduledImports as $scheduledId => $importDataController) {
			$current_user = $importDataController->user;
			$importDataController->batchImport = false;

			if(!$importDataController->initializeImport()) { continue; }
			$importDataController->importData();

			$importStatusCount = $importDataController->getImportStatusCount();

			$emailSubject = 'vtiger CRM - Scheduled Import Report for '.$importDataController->module;
            vimport('~~/modules/Import/ui/Viewer.php');
			$viewer = new Import_UI_Viewer();
			$viewer->assign('FOR_MODULE', $importDataController->module);
            $viewer->assign('INVENTORY_MODULES', getInventoryModules());
			$viewer->assign('IMPORT_RESULT', $importStatusCount);
			$importResult = $viewer->fetch('Import_Result_Details.tpl');
			$importResult = str_replace('align="center"', '', $importResult);
			$emailData = 'vtiger CRM has just completed your import process. <br/><br/>' .
							$importResult . '<br/><br/>'.
							'We recommend you to login to the CRM and check few records to confirm that the import has been successful.';

			$userName = getFullNameFromArray('Users', $importDataController->user->column_fields);
			$userEmail = $importDataController->user->email1;
			$vtigerMailer->to = array( array($userEmail, $userName));
			$vtigerMailer->Subject = $emailSubject;
			$vtigerMailer->Body    = $emailData;
			$vtigerMailer->Send();

			$importDataController->finishImport();
		}
		Vtiger_Mailer::dispatchQueue(null);
	}

	public static function getScheduledImport() {

		$scheduledImports = array();
		$importQueue = Import_Queue_Action::getAll(Import_Queue_Action::$IMPORT_STATUS_SCHEDULED);
		foreach($importQueue as $importId => $importInfo) {
			$userId = $importInfo['user_id'];
			$user = new Users();
			$user->id = $userId;
			$user->retrieve_entity_info($userId, 'Users');

			$scheduledImports[$importId] = new Import_Data_Action($importInfo, $user);
		}
		return $scheduledImports;
	}


    /*
     *  Function to get Record details of import
     *  @parms $user <User Record Model> Current Users
     *  @returns <Array> Import Records with the list of skipped records and failed records
     */
    public static function getImportDetails($user){
        $adb = PearDatabase::getInstance();
        $tableName = Import_Utils_Helper::getDbTableName($user);
		$result = $adb->pquery("SELECT * FROM $tableName where status IN (?,?)",array(self::$IMPORT_RECORD_SKIPPED,self::$IMPORT_RECORD_FAILED));
        $importRecords = array();
        if($result) {
            $headers = $adb->getColumnNames($tableName);
			$numOfHeaders = count($headers);
            for($i=0;$i<10;$i++){
                if($i>=3 && $i<$numOfHeaders){
                    $importRecords['headers'][] = $headers[$i];
                }
            }
			$noOfRows = $adb->num_rows($result);
			for($i=0; $i<$noOfRows; ++$i) {
                $row = $adb->fetchByAssoc($result,$i);
                $record= new Vtiger_Base_Model();
                foreach($importRecords['headers'] as $header){
                    $record->set($header,$row[$header]);
                }
                if($row['status'] == self::$IMPORT_RECORD_SKIPPED){
                    $importRecords['skipped'][] = $record;
                } else {
                    $importRecords['failed'][] = $record;
                }
            }
        return $importRecords;
        }
    }
	
	public function getImportRecordStatus($value) {
		$status = '';
		switch ($value) {
			case 'created': $status = self::$IMPORT_RECORD_CREATED;	break;
			case 'skipped': $status = self::$IMPORT_RECORD_SKIPPED;	break;
			case 'updated': $status = self::$IMPORT_RECORD_UPDATED;	break;
			case 'merged' :	$status = self::$IMPORT_RECORD_MERGED;	break;
			case 'failed' :	$status = self::$IMPORT_RECORD_FAILED;	break;
			case 'none' :	$status = self::$IMPORT_RECORD_NONE;	break;
		}
		return $status;
	}
}

?>
