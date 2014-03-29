/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/
jQuery.Class('Settings_Currency_Js', {
	
	//holds the currency instance
	currencyInstance : false,
	
	/**
	 * This function used to triggerAdd Currency
	 */
	triggerAdd : function(event) {
		event.stopPropagation();
		var instance = Settings_Currency_Js.currencyInstance;
		instance.showEditView();
	},
	
	/**
	 * This function used to trigger Edit Currency
	 */
	triggerEdit : function(event, id) {
		event.stopPropagation();
		var instance = Settings_Currency_Js.currencyInstance;
		instance.showEditView(id);
	},
	
	/**
	 * This function used to trigger Delete Currency
	 */
	triggerDelete : function(event, id) {
		event.stopPropagation();
		var currentTarget = jQuery(event.currentTarget);
		var currentTrEle = currentTarget.closest('tr');
		var instance = Settings_Currency_Js.currencyInstance;
		instance.transformEdit(id).then(
			function(data) {
				var callBackFunction = function(data) {
					var form = jQuery('#transformCurrency');
					
					//register all select2 Elements
					app.showSelect2ElementView(form.find('select.select2'));
					
					form.submit(function(e) {
						e.preventDefault();
						var transferCurrencyEle = form.find('select[name="transform_to_id"]');
						instance.deleteCurrency(id, transferCurrencyEle, currentTrEle);
					})
				}
				
				app.showModalWindow(data,function(data){
					if(typeof callBackFunction == 'function'){
						callBackFunction(data);
					}
				}, {'width':'500px'});
			}, function(error, err) {
				
			}
		);
	}
	
}, {
	
	//constructor
	init : function() {
		Settings_Currency_Js.currencyInstance = this;
	},
	
	/*
	 * function to show editView for Add/Edit Currency
	 * @params: id - currencyId
	 */
	showEditView : function(id) {
		var thisInstance = this;
		var aDeferred = jQuery.Deferred();
		
		var progressIndicatorElement = jQuery.progressIndicator({
			'position' : 'html',
			'blockInfo' : {
				'enabled' : true
			}
		});
		
		var params = {};
		params['module'] = app.getModuleName();
		params['parent'] = app.getParentModuleName();
		params['view'] = 'EditAjax';
		params['record'] = id;
		
		AppConnector.request(params).then(
			function(data) {
				var callBackFunction = function(data) {
					var form = jQuery('#editCurrency');
					var record = form.find('[name="record"]').val();
					
					//register all select2 Elements
					app.showSelect2ElementView(form.find('select.select2'));
					var currencyStatus = form.find('[name="currency_status"]').is(':checked');
					if(record != '' && currencyStatus) {
						//While editing currency, register the status change event
						thisInstance.registerCurrencyStatusChangeEvent(form);
					}
					//If we change the currency name, change the code and symbol for that currency
					thisInstance.registerCurrencyNameChangeEvent(form);
					
					var params = app.validationEngineOptions;
					params.onValidationComplete = function(form, valid){
						if(valid) {
							thisInstance.saveCurrencyDetails(form);
							return valid;
						}
					}
					form.validationEngine(params);
					
					form.submit(function(e) {
						e.preventDefault();
					})
				}
				
				progressIndicatorElement.progressIndicator({'mode' : 'hide'});
				app.showModalWindow(data,function(data){
					if(typeof callBackFunction == 'function'){
						callBackFunction(data);
					}
				}, {'width':'600px'});
			},
			function(error) {
				progressIndicatorElement.progressIndicator({'mode' : 'hide'});
				//TODO : Handle error
				aDeferred.reject(error);
			}
		);
		return aDeferred.promise();
	},
	
	/**
	 * Register Change event for currency status
	 */
	registerCurrencyStatusChangeEvent : function(form) {
		/*If the status changed to Inactive while editing currency, 
		currency should transfer to other existing currencies */
		form.find('[name="currency_status"]').on('change', function(e) {
			var currentTarget = jQuery(e.currentTarget);
			if(currentTarget.is(':checked')) {
				form.find('div.transferCurrency').addClass('hide');
			} else {
				form.find('div.transferCurrency').removeClass('hide');
			}
		})
	},
	
	/**
	 * Register Change event for currency Name
	 */
	registerCurrencyNameChangeEvent : function(form) {
		var currencyNameEle = form.find('select[name="currency_name"]');
		//on change of currencyName, update the currency code & symbol
		currencyNameEle.on('change', function() {
			var selectedCurrencyOption = currencyNameEle.find('option:selected');
			form.find('[name="currency_code"]').val(selectedCurrencyOption.data('code'));
			form.find('[name="currency_symbol"]').val(selectedCurrencyOption.data('symbol'));
		})
	},
	
	/**
	 * This function will save the currency details
	 */
	saveCurrencyDetails : function(form) {
		var thisInstance = this;
		var progressIndicatorElement = jQuery.progressIndicator({
			'position' : 'html',
			'blockInfo' : {
				'enabled' : true
			}
		});
		
		var data = form.serializeFormData();
		data['module'] = app.getModuleName();
		data['parent'] = app.getParentModuleName();
		data['action'] = 'SaveAjax';
		
		AppConnector.request(data).then(
			function(data) {
				if(data['success']) {
					progressIndicatorElement.progressIndicator({'mode' : 'hide'});
					app.hideModalWindow();
					var params = {};
					params.text = app.vtranslate('JS_CURRENCY_DETAILS_SAVED');
					Settings_Vtiger_Index_Js.showMessage(params);
					thisInstance.loadListViewContents();
				}
			},
			function(error) {
				progressIndicatorElement.progressIndicator({'mode' : 'hide'});
				//TODO : Handle error
			}
		);
	},
	
	/**
	 * This function will load the listView contents after Add/Edit currency
	 */
	loadListViewContents : function() {
		var thisInstance = this;
		var progressIndicatorElement = jQuery.progressIndicator({
			'position' : 'html',
			'blockInfo' : {
				'enabled' : true
			}
		});
		
		var params = {};
		params['module'] = app.getModuleName();
		params['parent'] = app.getParentModuleName();
		params['view'] = 'List';
		
		AppConnector.request(params).then(
			function(data) {
				progressIndicatorElement.progressIndicator({'mode' : 'hide'});
				//replace the new list view contents
				jQuery('#listViewContents').html(data);
				//thisInstance.triggerDisplayTypeEvent();
			}, function(error, err) {
				progressIndicatorElement.progressIndicator({'mode' : 'hide'});
			}
		);
	},
	
	/**
	 * This function will show the Transform Currency view while delete the currency
	 */
	transformEdit : function(id) {
		var aDeferred = jQuery.Deferred();
		
		var params = {};
		params['module'] = app.getModuleName();
		params['parent'] = app.getParentModuleName();
		params['view'] = 'TransformEditAjax';
		params['record'] = id;
		
		AppConnector.request(params).then(
			function(data) {
				aDeferred.resolve(data);
			}, function(error, err) {
				aDeferred.reject();
			});
		return aDeferred.promise();
	},
	
	/**
	 * This function will delete the currency and save the transferCurrency details
	 */
	deleteCurrency : function(id, transferCurrencyEle, currentTrEle) {
		var transferCurrencyId = transferCurrencyEle.find('option:selected').val();
		var params = {};
		params['module'] = app.getModuleName();
		params['parent'] = app.getParentModuleName();
		params['action'] = 'DeleteAjax';
		params['record'] = id;
		params['transform_to_id'] = transferCurrencyId;
		
		AppConnector.request(params).then(
			function(data) {
				app.hideModalWindow();
				var params = {};
				params.text = app.vtranslate('JS_CURRENCY_DELETED_SUEESSFULLY');
				Settings_Vtiger_Index_Js.showMessage(params);
				currentTrEle.fadeOut('slow').remove();
			}, function(error, err) {
				
			});
	},
	
	triggerDisplayTypeEvent : function() {
		var widthType = app.cacheGet('widthType', 'narrowWidthType');
		if(widthType) {
			var elements = jQuery('.listViewEntriesTable').find('td,th');
			elements.attr('class', widthType);
		}
	},
    
    registerRowClick : function() {
      var thisInstance = this;
      jQuery('#listViewContents').on('click','.listViewEntries',function(e) {
          var currentRow = jQuery(e.currentTarget);
          if(currentRow.find('.icon-pencil ').length <= 0) {
              return;
          } 
          thisInstance.showEditView(currentRow.data('id'));
      })  
    },
    
    registerEvents : function() {
        this.registerRowClick();
	}
	
});

jQuery(document).ready(function(){
	var currencyInstance = new Settings_Currency_Js();
    currencyInstance.registerEvents();
})
