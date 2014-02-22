<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

abstract class EntityMeta{
	
	public static $RETRIEVE = "DetailView";
	public static $CREATE = "Save";
	public static $UPDATE = "EditView";
	public static $DELETE = "Delete";
	
	protected $webserviceObject;
	protected $objectName;
	protected $objectId;
	protected $user;
	protected $baseTable;
	protected $idColumn;
	
	protected $userAccessibleColumns;
	protected $columnTableMapping;
	protected $fieldColumnMapping;
	protected $mandatoryFields;
	protected $referenceFieldDetails;
	protected $emailFields;
	protected $ownerFields;
	protected $moduleFields;
	
	protected function EntityMeta($webserviceObject,$user){
		$this->webserviceObject = $webserviceObject;
		$this->objectName = $this->webserviceObject->getEntityName();
		$this->objectId = $this->webserviceObject->getEntityId();
		
		$this->user = $user;
	}
	
	public function getEmailFields(){
		if($this->emailFields === null){
			$this->emailFields =  array();
			foreach ($this->moduleFields as $fieldName=>$webserviceField) {
				if(strcasecmp($webserviceField->getFieldType(),'e') === 0){
					array_push($this->emailFields, $fieldName);
				}
			}
		}
		
		return $this->emailFields;
	}
	
	public function getFieldColumnMapping(){
		if($this->fieldColumnMapping === null){
			$this->fieldColumnMapping =  array();
			foreach ($this->moduleFields as $fieldName=>$webserviceField) {
				$this->fieldColumnMapping[$fieldName] = $webserviceField->getColumnName();
			}
			$this->fieldColumnMapping['id'] = $this->idColumn;
		}
		return $this->fieldColumnMapping;
	}
	
	public function getMandatoryFields(){
		if($this->mandatoryFields === null){
			$this->mandatoryFields =  array();
			foreach ($this->moduleFields as $fieldName=>$webserviceField) {
				if($webserviceField->isMandatory() === true){
					array_push($this->mandatoryFields,$fieldName);
				}
			}
		}
		return $this->mandatoryFields;
	}
	
	public function getReferenceFieldDetails(){
		if($this->referenceFieldDetails === null){
			$this->referenceFieldDetails =  array();
			foreach ($this->moduleFields as $fieldName=>$webserviceField) {
				if(strcasecmp($webserviceField->getFieldDataType(),'reference') === 0){
					$this->referenceFieldDetails[$fieldName] = $webserviceField->getReferenceList();
				}
			}
		}
		return $this->referenceFieldDetails;
	}
	
	public function getOwnerFields(){
		if($this->ownerFields === null){
			$this->ownerFields =  array();
			foreach ($this->moduleFields as $fieldName=>$webserviceField) {
				if(strcasecmp($webserviceField->getFieldDataType(),'owner') === 0){
					array_push($this->ownerFields, $fieldName);
				}
			}
		}
		return $this->ownerFields;
	}
	
	public function getObectIndexColumn(){
		return $this->idColumn;
	}
	
	public function getUserAccessibleColumns(){
		if($this->userAccessibleColumns === null){
			$this->userAccessibleColumns =  array();
			foreach ($this->moduleFields as $fieldName=>$webserviceField) {
				array_push($this->userAccessibleColumns,$webserviceField->getColumnName());
			}
			array_push($this->userAccessibleColumns,$this->idColumn);
		}
		return $this->userAccessibleColumns;
	}
	
	public function getColumnTableMapping(){
		if($this->columnTableMapping === null){
			$this->columnTableMapping =  array();
			foreach ($this->moduleFields as $fieldName=>$webserviceField) {
				$this->columnTableMapping[$webserviceField->getColumnName()] = $webserviceField->getTableName();
			}
			$this->columnTableMapping[$this->idColumn] = $this->baseTable;
		}
		return $this->columnTableMapping;
	}
	
	function getUser(){
		return $this->user;
	}
	
	function hasMandatoryFields($row){
		
		$mandatoryFields = $this->getMandatoryFields();
		$hasMandatory = true;
		foreach($mandatoryFields as $ind=>$field){
			if( !isset($row[$field])){
				throw new WebServiceException(WebServiceErrorCode::$MANDFIELDSMISSING,"$field does not have a value");
			}
		}
		return $hasMandatory;
		
	}
	
	public function getModuleFields(){
		return $this->moduleFields;
	}
	
	abstract function hasPermission($operation,$webserviceId);
	abstract function hasAssignPrivilege($ownerWebserviceId);
	abstract function hasDeleteAccess();
	abstract function hasAccess();
	abstract function hasReadAccess();
	abstract function hasWriteAccess();
	abstract function getEntityName();
	abstract function getEntityId();
	abstract function exists($recordId);
	abstract function getObjectEntityName($webserviceId);
	abstract public function getNameFields();
	abstract public function getName($webserviceId);
}
?>