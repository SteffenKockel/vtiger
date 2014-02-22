<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

	include_once 'include/Webservices/DescribeObject.php';

	function vtws_describe_partial($elementType,$user){
		
		global $log,$adb;
		$webserviceObject = VtigerWebserviceObject::fromName($adb,$elementType);
		$handlerPath = $webserviceObject->getHandlerPath();
		$handlerClass = $webserviceObject->getHandlerClass();
		
		require_once $handlerPath;
		
		$handler = new $handlerClass($webserviceObject,$user,$adb,$log);
		$meta = $handler->getMeta();
		
		$types = vtws_listtypes(null, $user);
		if(!in_array($elementType,$types['types'])){
			throw new WebServiceException(WebServiceErrorCode::$ACCESSDENIED,"Permission to perform the operation is denied");
		}
		
		$entity = $handler->describePartial($elementType, array('leadsource'));
		VTWS_PreserveGlobal::flush();
		return $entity;
	}
	
?>