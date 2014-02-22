/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

jQuery.Class("Vtiger_RelatedList_Js",{},{
	
	selectedRelatedTabElement : false,
	parentRecordId : false,
	parentModuleName : false,
	relatedModulename : false,
	relatedTabsContainer : false,
	detailViewContainer : false,
	relatedContentContainer : false,
	
	setSelectedTabElement : function(tabElement) {
		this.selectedRelatedTabElement = tabElement;
	},
	
	getSelectedTabElement : function(){
		return this.selectedRelatedTabElement;
	},
	
	getParentId : function(){
		return this.parentRecordId;
	},
	
	loadRelatedList : function(params){
		var aDeferred = jQuery.Deferred();
		var thisInstance = this;
		if(typeof this.relatedModulename== "undefined" || this.relatedModulename.length <= 0 ) {
			return;
		}
		var progressIndicatorElement = jQuery.progressIndicator({
			'position' : 'html',
			'blockInfo' : {
				'enabled' : true
			}
		});
		var completeParams = this.getCompleteParams();
		jQuery.extend(completeParams,params);
		AppConnector.request(completeParams).then(
			function(responseData){
				progressIndicatorElement.progressIndicator({
					'mode' : 'hide'
				})
				thisInstance.relatedTabsContainer.find('li').removeClass('active');
				thisInstance.selectedRelatedTabElement.addClass('active');
				thisInstance.relatedContentContainer.html(responseData);
				responseData = thisInstance.relatedContentContainer.html();
				//thisInstance.triggerDisplayTypeEvent();
				Vtiger_Helper_Js.showHorizontalTopScrollBar();
				jQuery('.pageNumbers',thisInstance.relatedContentContainer).tooltip();
				aDeferred.resolve(responseData);
				jQuery('input[name="currentPageNum"]', thisInstance.relatedContentContainer).val(completeParams.page);
				// Let listeners know about page state change.
				app.notifyPostAjaxReady();
			},
			
			function(textStatus, errorThrown){
				aDeferred.reject(textStatus, errorThrown);
			}
		);
		return aDeferred.promise();
	},
	
	triggerDisplayTypeEvent : function() {
		var widthType = app.cacheGet('widthType', 'narrowWidthType');
		if(widthType) {
			var elements = jQuery('.listViewEntriesTable').find('td,th');
			elements.attr('class', widthType);
		}
	},
	
	showSelectRelationPopup : function(){
		var aDeferred = jQuery.Deferred();
		var thisInstance = this;
		var popupInstance = Vtiger_Popup_Js.getInstance();
		popupInstance.show(this.getPopupParams(), function(responseString){
				var responseData = JSON.parse(responseString);
				var relatedIdList = Object.keys(responseData);
				thisInstance.addRelations(relatedIdList).then(
					function(data){
						var relatedCurrentPage = thisInstance.getCurrentPageNum();
						var params = {'page':relatedCurrentPage};
						thisInstance.loadRelatedList(params).then(function(data){
							aDeferred.resolve(data);
						});
					}
				);
			}
		);
		return aDeferred.promise();
	},

	addRelations : function(idList){
		var aDeferred = jQuery.Deferred();
		var sourceRecordId = this.parentRecordId;
		var sourceModuleName = this.parentModuleName;
		var relatedModuleName = this.relatedModulename;

		var params = {};
		params['mode'] = "addRelation";
		params['module'] = sourceModuleName;
		params['action'] = 'RelationAjax';
		
		params['related_module'] = relatedModuleName;
		params['src_record'] = sourceRecordId;
		params['related_record_list'] = JSON.stringify(idList);

		AppConnector.request(params).then(
			function(responseData){
				aDeferred.resolve(responseData);
			},

			function(textStatus, errorThrown){
				aDeferred.reject(textStatus, errorThrown);
			}
		);
		return aDeferred.promise();
	},

	getPopupParams : function(){
		var parameters = {};
		var parameters = {
			'module' : this.relatedModulename,
			'src_module' : this.parentModuleName,
			'src_record' : this.parentRecordId,
			'multi_select' : true
		}
		return parameters;
	},

	deleteRelation : function(relatedIdList) {
		var aDeferred = jQuery.Deferred();
		var params = {};
		params['mode'] = "deleteRelation";
		params['module'] = this.parentModuleName;
		params['action'] = 'RelationAjax';

		params['related_module'] = this.relatedModulename;
		params['src_record'] = this.parentRecordId;
		params['related_record_list'] = JSON.stringify(relatedIdList);

		AppConnector.request(params).then(
			function(responseData){
				aDeferred.resolve(responseData);
			},

			function(textStatus, errorThrown){
				aDeferred.reject(textStatus, errorThrown);
			}
		);
		return aDeferred.promise();
	},

	getCurrentPageNum : function() {
		return jQuery('input[name="currentPageNum"]',this.relatedContentContainer).val();
	},
	
	setCurrentPageNumber : function(pageNumber){
		jQuery('input[name="currentPageNum"]').val(pageNumber);
	},
	
	/**
	 * Function to get Order by
	 */
	getOrderBy : function(){
		return jQuery('#orderBy').val();
	},
	
	/**
	 * Function to get Sort Order
	 */
	getSortOrder : function(){
			return jQuery("#sortOrder").val();
	},
	
	getCompleteParams : function(){
		var params = {};
		params['view'] = "Detail";
		params['module'] = this.parentModuleName;
		params['record'] = this.getParentId(),
		params['relatedModule'] = this.relatedModulename,
		params['sortorder'] =  this.getSortOrder(),
		params['orderby'] =  this.getOrderBy(),
		params['page'] = this.getCurrentPageNum();
		params['mode'] = "showRelatedList"
		
		return params;
	},
	
	
	/**
	 * Function to handle Sort
	 */
	sortHandler : function(headerElement){
		var aDeferred = jQuery.Deferred();
		var fieldName = headerElement.data('fieldname');
		var sortOrderVal = headerElement.data('nextsortorderval');
		var sortingParams = {
			"orderby" : fieldName,
			"sortorder" : sortOrderVal,
			"tab_label" : this.selectedRelatedTabElement.data('label-key')
		}
		this.loadRelatedList(sortingParams).then(
				function(data){
					aDeferred.resolve(data);
				},

				function(textStatus, errorThrown){
					aDeferred.reject(textStatus, errorThrown);
				}
			);
		return aDeferred.promise();
	},
	
	/**
	 * Function to handle next page navigation
	 */
	nextPageHandler : function(){
		var aDeferred = jQuery.Deferred();
		var thisInstance = this;
		var pageLimit = jQuery('#pageLimit').val();
		var noOfEntries = jQuery('#noOfEntries').val();
		if(noOfEntries == pageLimit){
			var pageNumber = this.getCurrentPageNum();
			var nextPage = parseInt(pageNumber) + 1;
			var nextPageParams = {
				'page' : nextPage
			}
			this.loadRelatedList(nextPageParams).then(
				function(data){
					thisInstance.setCurrentPageNumber(nextPage);
					aDeferred.resolve(data);
				},

				function(textStatus, errorThrown){
					aDeferred.reject(textStatus, errorThrown);
				}
			);
		}
		return aDeferred.promise();
	},
	
	/**
	 * Function to handle next page navigation
	 */
	previousPageHandler : function(){
		var aDeferred = jQuery.Deferred();
		var thisInstance = this;
		var aDeferred = jQuery.Deferred();
		var pageNumber = this.getCurrentPageNum();
		if(pageNumber > 1){
			var previousPage = parseInt(pageNumber) - 1;
			var previousPageParams = {
				'page' : previousPage
			}
			this.loadRelatedList(previousPageParams).then(
				function(data){
					thisInstance.setCurrentPageNumber(previousPage);
					aDeferred.resolve(data);
				},

				function(textStatus, errorThrown){
					aDeferred.reject(textStatus, errorThrown);
				}
			);
		}
		return aDeferred.promise();
	},
	
	/**
	 * Function to handle page jump in related list
	 */
	pageJumpHandler : function(e){
		var aDeferred = jQuery.Deferred();
		var thisInstance = this;
		if(e.which == 13){
			var element = jQuery(e.currentTarget);
			var response = Vtiger_WholeNumberGreaterThanZero_Validator_Js.invokeValidation(element);
			if(typeof response != "undefined"){
				element.validationEngine('showPrompt',response,'',"topLeft",true);
				e.preventDefault();
			} else {
				element.validationEngine('hideAll');
				var jumpToPage = parseInt(element.val());
				var totalPages = parseInt(jQuery('#totalPageCount').text());
				if(jumpToPage > totalPages){
					var error = app.vtranslate('JS_PAGE_NOT_EXIST');
					element.validationEngine('showPrompt',error,'',"topLeft",true);
				}
				var invalidFields = element.parent().find('.formError');
				if(invalidFields.length < 1){
					var currentPage = jQuery('input[name="currentPageNum"]').val();
					if(jumpToPage == currentPage){
						var message = app.vtranslate('JS_YOU_ARE_IN_PAGE_NUMBER')+" "+jumpToPage;
						var params = {
							text: message,
							type: 'info'
						};
						Vtiger_Helper_Js.showMessage(params);
						e.preventDefault();
					}
					var jumptoPageParams = {
						'page' : jumpToPage
					}
					this.loadRelatedList(jumptoPageParams).then(
						function(data){
							thisInstance.setCurrentPageNumber(jumpToPage);
							aDeferred.resolve(data);
						},

						function(textStatus, errorThrown){
							aDeferred.reject(textStatus, errorThrown);
						}
					);
				} else {
					e.preventDefault();
				}
			}
		}
		return aDeferred.promise();
	},
	/**
	 * Function to add related record for the module
	 */
	addRelatedRecord : function(element){
		var aDeferred = jQuery.Deferred();
		var thisInstance = this;
		var	referenceModuleName = this.relatedModulename;
		var parentId = this.getParentId();
		var parentModule = this.parentModuleName;
		var quickCreateParams = {};
		var relatedParams = {};
		var relatedField = element.data('name');
		var fullFormUrl = element.data('url');
		relatedParams[relatedField] = parentId;
		var eliminatedKeys = new Array('view', 'module', 'mode', 'action');
		
		var preQuickCreateSave = function(data){

			var index,queryParam,queryParamComponents;
			
			//To handle switch to task tab when click on add task from related list of activities
			//As this is leading to events tab intially even clicked on add task
			if(typeof fullFormUrl != 'undefined' && fullFormUrl.indexOf('?')!== -1) {
				var urlSplit = fullFormUrl.split('?');
				var queryString = urlSplit[1];
				var queryParameters = queryString.split('&');
				for(index=0; index<queryParameters.length; index++) {
					queryParam = queryParameters[index];
					queryParamComponents = queryParam.split('=');
					if(queryParamComponents[0] == 'mode' && queryParamComponents[1] == 'Calendar'){
						data.find('a[data-tab-name="Task"]').trigger('click');
					}
				}
			}
			jQuery('<input type="hidden" name="sourceModule" value="'+parentModule+'" />').appendTo(data);
			jQuery('<input type="hidden" name="sourceRecord" value="'+parentId+'" />').appendTo(data);
			jQuery('<input type="hidden" name="relationOperation" value="true" />').appendTo(data);
			
			if(typeof relatedField != "undefined"){
				var field = data.find('[name="'+relatedField+'"]');
				//If their is no element with the relatedField name,we are adding hidden element with
				//name as relatedField name,for saving of record with relation to parent record
				if(field.length == 0){
					jQuery('<input type="hidden" name="'+relatedField+'" value="'+parentId+'" />').appendTo(data);
				}
			}
			for(index=0; index<queryParameters.length; index++) {
				queryParam = queryParameters[index];
				queryParamComponents = queryParam.split('=');
				if(jQuery.inArray(queryParamComponents[0], eliminatedKeys) == '-1' && data.find('[name="'+queryParamComponents[0]+'"]').length == 0) {
					jQuery('<input type="hidden" name="'+queryParamComponents[0]+'" value="'+queryParamComponents[1]+'" />').appendTo(data);
				}
			}

		}
		var postQuickCreateSave  = function(data) {
			thisInstance.loadRelatedList().then(
				function(data){
					aDeferred.resolve(data);
				})
		}
		
		//If url contains params then seperate them and make them as relatedParams
		if(typeof fullFormUrl != 'undefined' && fullFormUrl.indexOf('?')!== -1) {
			var urlSplit = fullFormUrl.split('?');
			var queryString = urlSplit[1];
			var queryParameters = queryString.split('&');
			for(var index=0; index<queryParameters.length; index++) {
				var queryParam = queryParameters[index];
				var queryParamComponents = queryParam.split('=');
				if(jQuery.inArray(queryParamComponents[0], eliminatedKeys) == '-1') {
					relatedParams[queryParamComponents[0]] = queryParamComponents[1];
				}
			}
		}
		
		quickCreateParams['data'] = relatedParams;
		quickCreateParams['callbackFunction'] = postQuickCreateSave;
		quickCreateParams['callbackPostShown'] = preQuickCreateSave;
		quickCreateParams['noCache'] = true;
		var quickCreateNode = jQuery('#quickCreateModules').find('[data-name="'+ referenceModuleName +'"]');
		if(quickCreateNode.length <= 0) {
			Vtiger_Helper_Js.showPnotify(app.vtranslate('JS_NO_CREATE_OR_NOT_QUICK_CREATE_ENABLED'))
		}
		quickCreateNode.trigger('click',quickCreateParams);
		return aDeferred.promise();
	},
	
	getRelatedPageCount : function(){
		var params = {};
		params['action'] = "RelationAjax";
		params['module'] = this.parentModuleName;
		params['record'] = this.getParentId(),
		params['relatedModule'] = this.relatedModulename,
		params['tab_label'] = this.selectedRelatedTabElement.data('label-key');
		params['mode'] = "getRelatedListPageCount"
		
		var element = jQuery('#totalPageCount');
		var totalPageNumber = element.text();
		if(totalPageNumber == ""){
			element.progressIndicator({});
			AppConnector.request(params).then(
				function(data) {
					var pageCount = data['result']['page'];
					element.text(pageCount);
					element.progressIndicator({'mode': 'hide'});
				},
				function(error,err){

				}
			);
		}
	},
	
	/**
	 * Function to get total records count in related list
	 */
	totalRecordsCount : function(){
		var aDeferred = jQuery.Deferred();
		var thisInstance = this;
			var params = {};
			params['action'] = "RelationAjax";
			params['module'] = thisInstance.parentModuleName;
			params['record'] = thisInstance.getParentId(),
			params['relatedModule'] = thisInstance.relatedModulename,
			params['tab_label'] = thisInstance.selectedRelatedTabElement.data('label-key');
			params['mode'] = "getRelatedListPageCount"

			AppConnector.request(params).then(
				function(data) {
					var totalNumberOfRecords = data['result']['numberOfRecords'];
					aDeferred.resolve(totalNumberOfRecords);
			},
			function(error,err){

			});
		return aDeferred.promise();
	},
	init : function(parentId, parentModule, selectedRelatedTabElement, relatedModuleName){
		this.selectedRelatedTabElement = selectedRelatedTabElement,
		this.parentRecordId = parentId;
		this.parentModuleName = parentModule;
		this.relatedModulename = relatedModuleName;
		this.relatedTabsContainer = selectedRelatedTabElement.closest('div.related');
		this.detailViewContainer = this.relatedTabsContainer.closest('div.detailViewContainer');
		this.relatedContentContainer = jQuery('div.contents',this.detailViewContainer);
		Vtiger_Helper_Js.showHorizontalTopScrollBar();
	}
})