/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/
jQuery.Class('Settings_LayoutEditor_Js', {

}, {
	updatedBlockSequence : {},

	reactiveFieldsList : [],

	updatedRelatedList : {'updated' : [], 'deleted' : []},

	removeModulesArray : false,

	inActiveFieldsList : false,

	updatedBlockFieldsList : [],

	updatedBlocksList : [],

	blockNamesList : [],

	/**
	 * Function to set the removed modules array used in related list
	 */
	setRemovedModulesList : function() {
		var thisInstance = this;
		var relatedList = jQuery('#relatedTabOrder');
		var container = relatedList.find('.relatedTabModulesList');
		thisInstance.removeModulesArray = JSON.parse(container.find('.RemovedModulesListArray').val());
	},

	/**
	 * Function to set the inactive fields list used to show the inactive fields
	 */
	setInactiveFieldsList : function() {
		var thisInstance = this;
		var contents = jQuery('#layoutEditorContainer').find('.contents');
		thisInstance.inActiveFieldsList = JSON.parse(contents.find('.inActiveFieldsArray').val());
	},

	/**
	 * Function to regiser the event to make the blocks sortable
	 */
	makeBlocksListSortable : function() {
		var thisInstance = this;
		var contents = jQuery('#layoutEditorContainer').find('.contents');
		var table = contents.find('.blockSortable');
		contents.sortable({
			'containment' : contents,
			'items' : table,
			'revert' : true,
			'tolerance':'pointer',
			'cursor' : 'move',
			'update' : function(e, ui) {
				thisInstance.updateBlockSequence();
			}
		});
	},

	/**
	 * Function which will update block sequence
	 */
	updateBlockSequence : function() {
		var thisInstance = this;
		var progressIndicatorElement = jQuery.progressIndicator({
			'position' : 'html',
			'blockInfo' : {
				'enabled' : true
			}
		});

		var sequence = JSON.stringify(thisInstance.updateBlocksListByOrder());
		var params = {};
		params['module'] = app.getModuleName();
		params['parent'] = app.getParentModuleName();
		params['action'] = 'Block';
		params['mode'] = 'updateSequenceNumber';
		params['sequence'] = sequence;

		AppConnector.request(params).then(
			function(data) {
				progressIndicatorElement.progressIndicator({'mode' : 'hide'});
				var params = {};
				params['text'] = app.vtranslate('JS_BLOCK_SEQUENCE_UPDATED');
				Settings_Vtiger_Index_Js.showMessage(params);
			},
			function(error) {
				progressIndicatorElement.progressIndicator({'mode' : 'hide'});
			}
		);
	},

	/**
	 * Function which will arrange the sequence number of blocks
	 */
	updateBlocksListByOrder : function() {
		var thisInstance = this;
		var contents = jQuery('#layoutEditorContainer').find('.contents');
		contents.find('.editFieldsTable').each(function(index,domElement){
			var blockTable = jQuery(domElement);
			var blockId = blockTable.data('blockId');
			var actualBlockSequence = blockTable.data('sequence');
			var expectedBlockSequence = (index+1);

			if(expectedBlockSequence != actualBlockSequence) {
				blockTable.data('sequence', expectedBlockSequence);
			}
			thisInstance.updatedBlockSequence[blockId] = expectedBlockSequence;
		});
		return thisInstance.updatedBlockSequence;
	},

	/**
	 * Function to regiser the event to make the related modules sortable
	 */
	makeRelatedModuleSortable : function() {
		var thisInstance = this;
		var relatedModulesContainer = jQuery('#relatedTabOrder');
		var modulesList = relatedModulesContainer.find('li.relatedModule');
		relatedModulesContainer.sortable({
			'containment' : relatedModulesContainer,
			'items' : modulesList,
			'revert' : true,
			'tolerance':'pointer',
			'cursor' : 'move',
			'update' : function(e, ui) {
				thisInstance.showSaveButton();
			}
		});
	},

	/**
	 * Function which will enable the save button in realted tabs list
	 */
	showSaveButton : function() {
		var relatedList = jQuery('#relatedTabOrder');
		var saveButton = relatedList.find('.saveRelatedList');
		if(saveButton.attr('disabled') ==  'disabled') {
			saveButton.removeAttr('disabled');
		}
	},

	/**
	 * Function which will disable the save button in related tabs list
	 */
	disableSaveButton : function() {
		var relatedList = jQuery('#relatedTabOrder');
		var saveButton = relatedList.find('.saveRelatedList');
		saveButton.attr('disabled', 'disabled');
	},

	/**
	 * Function to register all the relatedList Events
	 */
	registerRelatedListEvents : function() {
		var thisInstance = this;
		var relatedList = jQuery('#relatedTabOrder');
		var container = relatedList.find('.relatedTabModulesList');
		var allModulesListArray = JSON.parse(container.find('.ModulesListArray').val());
		var ulEle = container.find('ul.relatedModulesList');

		var selectEle = container.find('[name="addToList"]');
		app.showSelect2ElementView(selectEle, {maximumSelectionSize : 1, closeOnSelect : true, dropdownCss : {'z-index' : 0}});
		selectEle.on('change', function() {
			var selectedVal = selectEle.val();
			var moduleLabel = allModulesListArray[selectedVal];
			//remove the element if its already exists
			ulEle.find('.module_'+selectedVal[0]).remove();

			//append li element for the selected module
			var liEle = container.find('.moduleCopy').clone(true, true);
			liEle.data('relationId', selectedVal[0]).find('.moduleLabel').text(moduleLabel);
			ulEle.append(liEle.removeClass('hide moduleCopy').addClass('relatedModule module_'+selectedVal[0]));
			thisInstance.makeRelatedModuleSortable();

			//remove that selected module from the select element
			selectEle.select2('data',[]);
			selectEle.find('option[value="'+selectedVal[0]+'"]').remove();

			thisInstance.removeModulesArray.splice(thisInstance.removeModulesArray.indexOf(selectedVal[0]),1);
			thisInstance.showSaveButton();
		})

		//register the event to click on close the related module
		container.find('.close').one('click', function(e) {
			var currentTarget = jQuery(e.currentTarget);
			thisInstance.showSaveButton();
			var liEle = currentTarget.closest('li.relatedModule');
			var relationId = liEle.data('relationId');
			var moduleLabel = liEle.find('.moduleLabel').text();
			liEle.fadeOut('slow').addClass('deleted');
			selectEle.append('<option value="'+relationId+'">'+moduleLabel+'</option>');
		})

		//register click event for save related  list button
		relatedList.on('click', '.saveRelatedList', function(e) {
			var currentTarget = jQuery(e.currentTarget);
			if(currentTarget.attr('disabled') != 'disabled') {
				thisInstance.disableSaveButton();
				thisInstance.updatedRelatedList['deleted'] = [];
				for(var key in thisInstance.removeModulesArray) {
					thisInstance.updatedRelatedList['deleted'].push(thisInstance.removeModulesArray[key]);
				}
				thisInstance.saveRelatedListInfo();
			}
		})
	},

	/**
	 * Function to save the updated information in related list
	 */
	saveRelatedListInfo : function() {
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
		params['action'] = 'Relation';
		params['related_info'] = thisInstance.getUpdatedModulesInfo();
		params['sourceModule'] = jQuery('#selectedModuleName').val();

		AppConnector.request(params).then(
			function(data) {
				progressIndicatorElement.progressIndicator({'mode' : 'hide'});
				var params = {};
				params['text'] = app.vtranslate('JS_RELATED_INFO_SAVED');
				Settings_Vtiger_Index_Js.showMessage(params);
				aDeferred.resolve(data);
			},
			function(error) {
				progressIndicatorElement.progressIndicator({'mode' : 'hide'});
				aDeferred.reject(error);
			}
		);
		return aDeferred.promise();
	},

	/**
	 * Function to get the updates happened with the related modules list
	 */
	getUpdatedModulesInfo : function() {
		var thisInstance = this;
		var relatedList = jQuery('#relatedTabOrder');
		var removedModulesList = relatedList.find('li.relatedModule').filter('.deleted');
		var updatedModulesList = relatedList.find('li.relatedModule').not('.deleted');
		thisInstance.updatedRelatedList['updated'] = [];

		//update deleted related modules list
		removedModulesList.each(function(index,domElement) {
			var relationId = jQuery(domElement).data('relationId');
			thisInstance.updatedRelatedList['deleted'].push(relationId);
		});
		//update the existing related modules list
		updatedModulesList.each(function(index,domElement){
			var relationId = jQuery(domElement).data('relationId');
			thisInstance.updatedRelatedList['updated'].push(relationId);
		});
		return thisInstance.updatedRelatedList;
	},

	/**
	 * Function to regiser the event to make the fields sortable
	 */
	makeFieldsListSortable : function() {
		var thisInstance = this;
		var contents = jQuery('#layoutEditorContainer').find('.contents');
		var table = contents.find('.editFieldsTable');
		table.find('ul[name=sortable1], ul[name=sortable2]').sortable({
			'containment' : '#moduleBlocks',
			'revert' : true,
			'tolerance':'pointer',
			'cursor' : 'move',
			'connectWith' : '.connectedSortable',
			'update' : function(e, ui) {
				var currentField = ui['item'];
				thisInstance.showSaveFieldSequenceButton();
				thisInstance.createUpdatedBlocksList(currentField);
				// rearrange the older block fields
				if(ui.sender) {
					var olderBlock = ui.sender.closest('.editFieldsTable');
					thisInstance.reArrangeBlockFields(olderBlock);
				}
			}
		});
	},

	/**
	 * Function to show the save button of fieldSequence
	 */
	showSaveFieldSequenceButton : function() {
		var thisInstance = this;
		var layout = jQuery('#detailViewLayout');
		var saveButton = layout.find('.saveFieldSequence');
		if(app.isHidden(saveButton)) {
			thisInstance.updatedBlocksList = [];
			thisInstance.updatedBlockFieldsList = [];
			saveButton.removeClass('hide');
			var params = {};
			params['text'] = app.vtranslate('JS_SAVE_THE_CHANGES_TO_UPDATE_FIELD_SEQUENCE');
			Settings_Vtiger_Index_Js.showMessage(params);
		}
	},

	/**
	 * Function which will hide the saveFieldSequence button
	 */
	hideSaveFieldSequenceButton : function() {
		var layout = jQuery('#detailViewLayout');
		var saveButton = layout.find('.saveFieldSequence');
		saveButton.addClass('hide');
	},

	/**
	 * Function to create the blocks list which are updated while sorting
	 */
	createUpdatedBlocksList : function(currentField) {
		var thisInstance = this;
		var block = currentField.closest('.editFieldsTable');
		var updatedBlockId = block.data('blockId');
		if(jQuery.inArray(updatedBlockId, thisInstance.updatedBlocksList) == -1) {
			thisInstance.updatedBlocksList.push(updatedBlockId);
		}
		thisInstance.reArrangeBlockFields(block);
	},

	/**
	 * Function that rearranges fields in the block when the fields are moved
	 * @param <jQuery object> block
	 */
	reArrangeBlockFields : function(block) {
		// 1.get the containers, 2.compare the length, 3.if uneven then move the last element
		var leftSideContainer = block.find('ul[name=sortable1]');
		var rightSideContainer = block.find('ul[name=sortable2]');
		if(leftSideContainer.children().length < rightSideContainer.children().length) {
			var lastElementInRightContainer = rightSideContainer.children(':last');
			leftSideContainer.append(lastElementInRightContainer);
		} else if(leftSideContainer.children().length > rightSideContainer.children().length+1) {	//greater than 1
			var lastElementInLeftContainer = leftSideContainer.children(':last');
			rightSideContainer.append(lastElementInLeftContainer);
		}
	},
	/**
	 * Function to create the list of updated blocks with all the fields and their sequences
	 */
	createUpdatedBlockFieldsList : function() {
		var thisInstance = this;
		var contents = jQuery('#layoutEditorContainer').find('.contents');

		for(var index in  thisInstance.updatedBlocksList) {
			var updatedBlockId = thisInstance.updatedBlocksList[index];
			var updatedBlock = contents.find('.block_'+updatedBlockId);
			var firstBlockSortFields = updatedBlock.find('ul[name=sortable1]');
			var editFields = firstBlockSortFields.find('.editFields');
			var expectedFieldSequence = 1;
			editFields.each(function(i,domElement){
				var fieldEle = jQuery(domElement);
				var fieldId = fieldEle.data('fieldId');
				thisInstance.updatedBlockFieldsList.push({'fieldid' : fieldId,'sequence' : expectedFieldSequence, 'block' : updatedBlockId});
				expectedFieldSequence = expectedFieldSequence+2;
			});
			var secondBlockSortFields = updatedBlock.find('ul[name=sortable2]');
			var secondEditFields = secondBlockSortFields.find('.editFields');
			var sequenceValue = 2;
			secondEditFields.each(function(i,domElement){
				var fieldEle = jQuery(domElement);
				var fieldId = fieldEle.data('fieldId');
				thisInstance.updatedBlockFieldsList.push({'fieldid' : fieldId,'sequence' : sequenceValue, 'block' : updatedBlockId});
				sequenceValue = sequenceValue+2;
			});
		}
	},

	/**
	 * Function to register click event for save button of fields sequence
	 */
	registerFieldSequenceSaveClick : function() {
		var thisInstance = this;
		var layout = jQuery('#detailViewLayout');
		layout.on('click', '.saveFieldSequence', function() {
			thisInstance.hideSaveFieldSequenceButton();
			thisInstance.createUpdatedBlockFieldsList();
			thisInstance.updateFieldSequence();
		});
	},

	/**
	 * Function will save the field sequences
	 */
	updateFieldSequence : function() {
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
		params['action'] = 'Field';
		params['mode'] = 'move';
		params['updatedFields'] = thisInstance.updatedBlockFieldsList;

		AppConnector.request(params).then(
			function(data) {
				progressIndicatorElement.progressIndicator({'mode' : 'hide'});
				window.location.reload();
				var params = {};
				params['text'] = app.vtranslate('JS_FIELD_SEQUENCE_UPDATED');
				Settings_Vtiger_Index_Js.showMessage(params);
			},
			function(error) {
				progressIndicatorElement.progressIndicator({'mode' : 'hide'});
			}
		);
	},

	/**
	 * Function to register click evnet add custom field button
	 */
	registerAddCustomFieldEvent : function() {
		var thisInstance = this;
		var contents = jQuery('#layoutEditorContainer').find('.contents');
		contents.find('.addCustomField').click(function(e) {
			var blockId = jQuery(e.currentTarget).closest('.editFieldsTable').data('blockId');
			var addFieldContainer = contents.find('.createFieldModal').clone(true, true);
			addFieldContainer.removeClass('hide');

			var callBackFunction = function(data) {
				//register all select2 Elements
				app.showSelect2ElementView(data.find('select'));

				var form = data.find('.createCustomFieldForm');
				form.attr('id', 'createFieldForm');
				var select2params = {tags: [],tokenSeparators: [","]}
				app.showSelect2ElementView(form.find('[name="pickListValues"]'), select2params);

				thisInstance.registerFieldTypeChangeEvent(form);

				var params = app.getvalidationEngineOptions(true);
				params.onValidationComplete = function(form, valid){
					if(valid) {
						var fieldTypeValue = jQuery('[name="fieldType"]',form).val();
						if(fieldTypeValue == 'Picklist' || fieldTypeValue == 'MultiSelectCombo') {
							var pickListValueElement = jQuery('#picklistUi',form);
							var pickLisValues = pickListValueElement.val();
							var pickListValuesArray = pickLisValues.split(',');
							var pickListValuesArraySize = pickListValuesArray.length;
							var specialChars = /["]/ ;
							for(var i=0;i<pickListValuesArray.length;i++) {
								if (specialChars.test(pickListValuesArray[i])) {
									var select2Element = app.getSelect2ElementFromSelect(pickListValueElement);
									var message = app.vtranslate('JS_SPECIAL_CHARACTERS')+' " '+app.vtranslate('JS_NOT_ALLOWED');
									select2Element.validationEngine('showPrompt', message , 'error','bottomLeft',true);
									return false;
								}
							}
							var lowerCasedpickListValuesArray = jQuery.map(pickListValuesArray, function(item, index) {
																	return item.toLowerCase();
																});
							var uniqueLowerCasedpickListValuesArray = jQuery.unique(lowerCasedpickListValuesArray);
							var uniqueLowerCasedpickListValuesArraySize = uniqueLowerCasedpickListValuesArray.length;
							var arrayDiffSize = pickListValuesArraySize-uniqueLowerCasedpickListValuesArraySize;
							if(arrayDiffSize > 0) {
								var select2Element = app.getSelect2ElementFromSelect(pickListValueElement);
								var message = app.vtranslate('JS_DUPLICATES_VALUES_FOUND');
								select2Element.validationEngine('showPrompt', message , 'error','bottomLeft',true);
								return false;
							}

						}
						var saveButton = form.find(':submit');
						saveButton.attr('disabled', 'disabled');
						thisInstance.addCustomField(blockId, form).then(
							function(data) {
								var result = data['result'];
								var params = {};
								if(data['success']) {
									app.hideModalWindow();
									params['text'] = app.vtranslate('JS_CUSTOM_FIELD_ADDED');
									Settings_Vtiger_Index_Js.showMessage(params);
									thisInstance.showCustomField(result);
								} else {
									var message = data['error']['message'];
									form.find('[name="fieldLabel"]').validationEngine('showPrompt', message , 'error','topLeft',true);
									saveButton.removeAttr('disabled');
								}
							}
						);
					}
					//To prevent form submit
					return false;
				}
				form.validationEngine(params);
			}

			app.showModalWindow(addFieldContainer,function(data) {
				if(typeof callBackFunction == 'function') {
					callBackFunction(data);
				}
			}, {'width':'1000px'});
		});
	},

	/**
	 * Function to create the array of block names list
	 */
	setBlocksListArray : function(form) {
		var thisInstance = this;
		thisInstance.blockNamesList = [];
		var blocksListSelect = form.find('[name="beforeBlockId"]');
		blocksListSelect.find('option').each(function(index, ele) {
			var option = jQuery(ele);
			var label = option.data('label');
			thisInstance.blockNamesList.push(label);
		})
	},

	/**
	 * Function to save the custom field details
	 */
	addCustomField : function(blockId, form) {
		var thisInstance = this;
		var modalHeader = form.closest('#globalmodal').find('.modal-header h3');
		var aDeferred = jQuery.Deferred();

		modalHeader.progressIndicator({smallLoadingImage : true, imageContainerCss : {display : 'inline', 'margin-left' : '18%',position : 'absolute'}});

		var params = form.serializeFormData();
		params['module'] = app.getModuleName();
		params['parent'] = app.getParentModuleName();
		params['action'] = 'Field';
		params['mode'] = 'add';
		params['blockid'] = blockId;
		params['sourceModule'] = jQuery('#selectedModuleName').val();

		AppConnector.request(params).then(
			function(data) {
				modalHeader.progressIndicator({'mode' : 'hide'});
				aDeferred.resolve(data);
			},
			function(error) {
				modalHeader.progressIndicator({'mode' : 'hide'});
				aDeferred.reject(error);
			}
		);
		return aDeferred.promise();
	},

	/**
	 * Function to register change event for fieldType while adding custom field
	 */
	registerFieldTypeChangeEvent : function(form) {
		var thisInstance = this;
		var lengthInput = form.find('[name="fieldLength"]');

		//special validators while adding new field
		var lengthValidator = [{'name' : 'DecimalMaxLength'}];
		var maxLengthValidator = [{'name' : 'MaxLength'}];
		var decimalValidator = [{'name' : 'FloatingDigits'}];

		//By default add the max length validator
		lengthInput.data('validator', maxLengthValidator);

		//register the change event for field types
		form.find('[name="fieldType"]').on('change', function(e) {
			var currentTarget = jQuery(e.currentTarget);
			var lengthInput = form.find('[name="fieldLength"]');
			var selectedOption = currentTarget.find('option:selected');

			//hide all the elements like length, decimal,picklist
			form.find('.supportedType').addClass('hide');

			if(selectedOption.data('lengthsupported')) {
				form.find('.lengthsupported').removeClass('hide');
				lengthInput.data('validator', maxLengthValidator);
			}

			if(selectedOption.data('decimalsupported')) {
				var decimalFieldUi = form.find('.decimalsupported');
				decimalFieldUi.removeClass('hide');

				var decimalInput = decimalFieldUi.find('[name="decimal"]');
				var maxFloatingDigits = selectedOption.data('maxfloatingdigits');

				if(typeof maxFloatingDigits != "undefined") {
					decimalInput.data('validator', decimalValidator);
					lengthInput.data('validator', lengthValidator);
				}

				if(selectedOption.data('decimalreadonly')) {
					decimalInput.val(maxFloatingDigits).attr('readonly', true);
				} else {
					decimalInput.removeAttr('readonly').val('');
				}
			}

			if(selectedOption.data('predefinedvalueexists')) {
				var pickListUi = form.find('.preDefinedValueExists');
				pickListUi.removeClass('hide');
			}
            if(selectedOption.data('picklistoption')) {
                var pickListOption = form.find('.picklistOption');
				pickListOption.removeClass('hide');
            }
		})
	},

	/**
	 * Function to add new custom field ui to the list
	 */
	showCustomField : function(result) {
		var thisInstance = this;
		var contents = jQuery('#layoutEditorContainer').find('.contents');
		var relatedBlock = contents.find('.block_'+result['blockid']);
		var fieldCopy = contents.find('.newCustomFieldCopy').clone(true, true);
		var fieldContainer = fieldCopy.find('div.marginLeftZero.border1px');
		fieldContainer.addClass('opacity editFields').attr('data-field-id', result['id']).attr('data-block-id', result['blockid']);
		fieldContainer.find('.deleteCustomField, .saveFieldDetails').attr('data-field-id', result['id']);
		fieldContainer.find('.fieldLabel').text(result['label']);
		if(!result['customField']){
			fieldContainer.find('.deleteCustomField').remove();
		}
		var block = relatedBlock.find('.blockFieldsList');
		var sortable1 = block.find('ul[name=sortable1]');
		var length1 = sortable1.children().length;
		var sortable2 = block.find('ul[name=sortable2]');
		var length2 = sortable2.children().length;
		// Deciding where to add the new field
		if(length1 > length2) {
			sortable2.append(fieldCopy.removeClass('hide newCustomFieldCopy'));
		} else {
			sortable1.append(fieldCopy.removeClass('hide newCustomFieldCopy'));
		}
		var form = fieldCopy.find('form.fieldDetailsForm');
		thisInstance.setFieldDetails(result, form);
		thisInstance.makeFieldsListSortable();
	},

	/**
	 * Function to set the field info for edit field actions
	 */
	setFieldDetails : function(result, form) {
		var thisInstance = this;
		//add field label to the field details
		form.find('.modal-header').html(jQuery('<strong>'+result['label']+'</strong>'));

		var defaultValueUi = form.find('.defaultValueUi');
		if(result['mandatory']) {
			form.find('[name="mandatory"]').filter(':checkbox').attr('checked', true);
		}
		if(result['presence']) {
			form.find('[name="presence"]').filter(':checkbox').attr('checked', true);
		}
		if(result['quickcreate']) {
			form.find('[name="quickcreate"]').filter(':checkbox').attr('checked', true);
		}
		if(result['isQuickCreateDisabled']) {
			form.find('[name="quickcreate"]').filter(':checkbox').attr('readonly', 'readonly').addClass('optionDisabled');
		}
		if(result['isSummaryField']) {
			form.find('[name="summaryfield"]').filter(':checkbox').attr('checked', true);
		}
		if(result['isSummaryFieldDisabled']) {
			form.find('[name="summaryfield"]').filter(':checkbox').attr('readonly', 'readonly').addClass('optionDisabled');
		}
		if(result['masseditable']) {
			form.find('[name="masseditable"]').filter(':checkbox').attr('checked', true);
		}
		if(result['defaultvalue']) {
			form.find('[name="defaultvalue"]').filter(':checkbox').attr('checked', true);
			defaultValueUi.removeClass('zeroOpacity');
		} else {
			defaultValueUi.addClass('zeroOpacity');
		}
		//based on the field model it will give the respective ui for the field
		var fieldModel = Vtiger_Field_Js.getInstance(result);
		var fieldUi = fieldModel.getUiTypeSpecificHtml();
		defaultValueUi.html(fieldUi);
		defaultValueUi.find('.chzn-select').removeClass('chzn-select');

		//Handled Time field UI
		var timeField = defaultValueUi.find('.timepicker-default');
		timeField.removeClass('timePicker timepicker-default');
		timeField.attr('data-toregister','time');

		//Handled date field UI
		var dateField = defaultValueUi.find('.dateField')
		dateField.removeClass('dateField');
		dateField.attr('data-toregister','date');

		defaultValueUi.find('[data-validation-engine]').attr('data-validation-engine','validate[required,funcCall[Vtiger_Base_Validator_Js.invokeValidation]]');
		defaultValueUi.find('[name*='+result['name']+']').attr('name', 'fieldDefaultValue');
		defaultValueUi.find('[name="fieldDefaultValue"]').attr('disabled','disabled');
		defaultValueUi.find('input').addClass('input-medium');
		defaultValueUi.find('.select2').addClass('row-fluid');
	},

	/**
	 * Function to register click event for add custom block button
	 */
	registerAddCustomBlockEvent : function() {
		var thisInstance = this;
		var contents = jQuery('#layoutEditorContainer').find('.contents');
		contents.find('.addCustomBlock').click(function(e) {
			var addBlockContainer = contents.find('.addBlockModal').clone(true, true);

			var callBackFunction = function(data) {
				data.find('.addBlockModal').removeClass('hide');
				//register all select2 Elements
				app.showSelect2ElementView(data.find('select'));

				var form = data.find('.addCustomBlockForm');
				thisInstance.setBlocksListArray(form);
				var fieldLabel = form.find('[name="label"]');
				var params = app.validationEngineOptions;
				params.onValidationComplete = function(form, valid){
					if(valid) {
						var formData = form.serializeFormData();
						if(jQuery.inArray(formData['label'], thisInstance.blockNamesList) == -1) {
							thisInstance.saveBlockDetails(form).then(
								function(data) {
									var params = {};
									if(data['success']) {
										var result = data['result'];
										thisInstance.displayNewCustomBlock(result);
										thisInstance.updateNewSequenceForBlocks(result['sequenceList']);
										thisInstance.appendNewBlockToBlocksList(result, form);
										thisInstance.makeFieldsListSortable();

										params['text'] = app.vtranslate('JS_CUSTOM_BLOCK_ADDED');
									} else {
										params['text'] = data['error']['message'];
										params['type'] = 'error';
									}
									Settings_Vtiger_Index_Js.showMessage(params);
								}
							);
							app.hideModalWindow();
							return valid;
						} else {
							var result = app.vtranslate('JS_BLOCK_NAME_EXISTS');
							fieldLabel.validationEngine('showPrompt', result , 'error','topLeft',true);
							e.preventDefault();
							return;
						}
					}
				}
				form.validationEngine(params);

				form.submit(function(e) {
					e.preventDefault();
				})
			}
			app.showModalWindow(addBlockContainer,function(data) {
				if(typeof callBackFunction == 'function') {
					callBackFunction(data);
				}
			}, {'width':'1000px'});
		});
	},

	/**
	 * Function to save the new custom block details
	 */
	saveBlockDetails : function(form) {
		var thisInstance = this;
		var aDeferred = jQuery.Deferred();
		var progressIndicatorElement = jQuery.progressIndicator({
			'position' : 'html',
			'blockInfo' : {
				'enabled' : true
			}
		});
		var params = form.serializeFormData();
		params['module'] = app.getModuleName();
		params['parent'] = app.getParentModuleName();
		params['sourceModule'] = jQuery('#selectedModuleName').val();
		params['action'] = 'Block';
		params['mode'] = 'save';

		AppConnector.request(params).then(
			function(data) {
				progressIndicatorElement.progressIndicator({'mode' : 'hide'});
				aDeferred.resolve(data);
			},
			function(error) {
				progressIndicatorElement.progressIndicator({'mode' : 'hide'});
				aDeferred.reject(error);
			}
		);
		return aDeferred.promise();
	},

	/**
	 * Function used to display the new custom block ui after save
	 */
	displayNewCustomBlock : function(result) {
		var thisInstance = this;
		var contents = jQuery('#layoutEditorContainer').find('.contents');
		var beforeBlockId = result['beforeBlockId'];
		var beforeBlock = contents.find('.block_'+beforeBlockId);

		var newBlockCloneCopy = contents.find('.newCustomBlockCopy').clone(true, true);
		newBlockCloneCopy.data('blockId', result['id']).find('.blockLabel').append(jQuery('<strong>'+result['label']+'</strong>'));
		newBlockCloneCopy.find('.blockVisibility').data('blockId', result['id']);
		if(result['isAddCustomFieldEnabled']) {
			newBlockCloneCopy.find('.addCustomField').removeClass('hide');
		}
		beforeBlock.after(newBlockCloneCopy.removeClass('hide newCustomBlockCopy').addClass('editFieldsTable block_'+result['id']));

		newBlockCloneCopy.find('.blockFieldsList').sortable({'connectWith' : '.blockFieldsList'});
	},

	/**
	 * Function to update the sequence for all blocks after adding new Block
	 */
	updateNewSequenceForBlocks : function(sequenceList) {
		var contents = jQuery('#layoutEditorContainer').find('.contents');
		jQuery.each(sequenceList, function(blockId, sequence) {
			contents.find('.block_'+blockId).data('sequence', sequence);
		});
	},

	/**
	 * Function to update the block list with the new block label in the clone container
	 */
	appendNewBlockToBlocksList : function(result, form) {
		var contents = jQuery('#layoutEditorContainer').find('.contents');
		var hiddenAddBlockModel = contents.find('.addBlockModal');
		var blocksListSelect = hiddenAddBlockModel.find('[name="beforeBlockId"]');
		var option = jQuery("<option>",{
                  		value: result['id'],
                  		text: result['label']
            		})
		blocksListSelect.append(option.attr('data-label', result['label']));
	},

	/**
	 * Function to update the block list to remove the deleted custom block label in the clone container
	 */
	removeBlockFromBlocksList : function(blockId) {
		var contents = jQuery('#layoutEditorContainer').find('.contents');
		var hiddenAddBlockModel = contents.find('.addBlockModal');
		var blocksListSelect = hiddenAddBlockModel.find('[name="beforeBlockId"]');
		blocksListSelect.find('option[value="'+blockId+'"]').remove();
	},

	/**
	 * Function to register the change event for block visibility
	 */
	registerBlockVisibilityEvent : function() {
		var thisInstance = this;
		var contents = jQuery('#layoutEditorContainer').find('.contents');
		contents.on('click', 'li.blockVisibility', function(e) {
			var currentTarget = jQuery(e.currentTarget);
			var oldDisplayStatus = currentTarget.data('visible');
			if(oldDisplayStatus == '0') {
				currentTarget.find('.icon-ok').removeClass('hide');
				currentTarget.data('visible', '1');
			} else {
				currentTarget.find('.icon-ok').addClass('hide');
				currentTarget.data('visible', '0');
			}
			thisInstance.updateBlockStatus(currentTarget);
		})
	},

	/**
	 * Function to save the changed visibility for the block
	 */
	updateBlockStatus : function(currentTarget) {
		var thisInstance = this;
		var blockStatus = currentTarget.data('visible');
		var progressIndicatorElement = jQuery.progressIndicator({
			'position' : 'html',
			'blockInfo' : {
				'enabled' : true
			}
		});
		var params = {};
		params['module'] = app.getModuleName();
		params['parent'] = app.getParentModuleName();
		params['sourceModule'] = jQuery('#selectedModuleName').val();
		params['action'] = 'Block';
		params['mode'] = 'save';
		params['blockid'] = currentTarget.data('blockId');
		params['display_status'] = blockStatus;

		AppConnector.request(params).then(
			function(data) {
				progressIndicatorElement.progressIndicator({'mode' : 'hide'});
				var params = {};
				if(blockStatus == '1') {
					params['text'] = app.vtranslate('JS_BLOCK_VISIBILITY_SHOW');
				} else {
					params['text'] = app.vtranslate('JS_BLOCK_VISIBILITY_HIDE');
				}
				Settings_Vtiger_Index_Js.showMessage(params);
			},
			function(error) {
				progressIndicatorElement.progressIndicator({'mode' : 'hide'});
			}
		);
	},

	/**
	 * Function to register the click event for inactive fields list
	 */
	registerInactiveFieldsEvent : function() {
		var thisInstance = this;
		var contents = jQuery('#layoutEditorContainer').find('.contents');
		contents.on('click', 'li.inActiveFields', function(e) {
			var currentTarget = jQuery(e.currentTarget);
			var currentBlock = currentTarget.closest('.editFieldsTable');
			var blockId = currentBlock.data('blockId');
			//If there are no hidden fields, show pnotify
			if(jQuery.isEmptyObject(thisInstance.inActiveFieldsList[blockId])) {
				var params = {};
				params['text'] = app.vtranslate('JS_NO_HIDDEN_FIELDS_EXISTS');
				params['type'] = 'error';
				Settings_Vtiger_Index_Js.showMessage(params);
			} else {
				var inActiveFieldsContainer = contents.find('.inactiveFieldsModal').clone(true, true);

				var callBackFunction = function(data) {
					data.find('.inactiveFieldsModal').removeClass('hide');
					thisInstance.reactiveFieldsList = [];
					var form = data.find('.inactiveFieldsForm');
					thisInstance.showHiddenFields(blockId, form);
					//register click event for reactivate button in the inactive fields modal
					data.find('[name="reactivateButton"]').click(function(e) {
						thisInstance.createReactivateFieldslist(blockId, form);
						thisInstance.reActivateHiddenFields(currentBlock);
						app.hideModalWindow();
					});
				}

				app.showModalWindow(inActiveFieldsContainer,function(data) {
					if(typeof callBackFunction == 'function') {
						callBackFunction(data);
					}
				}, {'width':'1000px'});
			}
		});

	},

	/**
	 * Function to show the list of inactive fields in the modal
	 */
	showHiddenFields : function(blockId, form) {
		var thisInstance = this;
		jQuery.each(thisInstance.inActiveFieldsList[blockId], function(key, value) {
			var inActiveField = jQuery('<div class="span4 marginLeftZero padding-bottom1per"><label class="checkbox">\n\
									<input type="checkbox" class="inActiveField" value="'+key+'" />&nbsp;'+value+'</label></div>');
			form.find('.inActiveList').append(inActiveField);
		});
	},

	/**
	 * Function to create the list of reactivate fields list
	 */
	createReactivateFieldslist : function(blockId, form) {
		var thisInstance = this;
		form.find('.inActiveField').each(function(index,domElement){
			var element = jQuery(domElement);
			var fieldId = element.val();
			if(element.is(':checked')) {
				delete thisInstance.inActiveFieldsList[blockId][fieldId];
				thisInstance.reactiveFieldsList.push(fieldId);
			}
		});
	},

	/**
	 * Function to unHide the selected fields in the inactive fields modal
	 */
	reActivateHiddenFields : function(currentBlock) {
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
		params['action'] = 'Field';
		params['mode'] = 'unHide';
		params['blockId'] = currentBlock.data('blockId');
		params['fieldIdList'] = JSON.stringify(thisInstance.reactiveFieldsList);

		AppConnector.request(params).then(
			function(data) {
				for(index in data.result) {
					thisInstance.showCustomField(data.result[index]);
				}
				progressIndicatorElement.progressIndicator({'mode' : 'hide'});
				var params = {};
				params['text'] = app.vtranslate('JS_SELECTED_FIELDS_REACTIVATED');
				Settings_Vtiger_Index_Js.showMessage(params);
			},
			function(error) {
				progressIndicatorElement.progressIndicator({'mode' : 'hide'});
			}
		);
	},
	/**
	 * Function to register the click event for delete custom block
	 */
	registerDeleteCustomBlockEvent : function() {
		var thisInstance = this;
		var contents = jQuery('#layoutEditorContainer').find('.contents');
		var table = contents.find('.editFieldsTable');
		contents.on('click', 'li.deleteCustomBlock', function(e) {
			var currentTarget = jQuery(e.currentTarget);
			var table = currentTarget.closest('div.editFieldsTable');
			var blockId = table.data('blockId');

			var message = app.vtranslate('JS_LBL_ARE_YOU_SURE_YOU_WANT_TO_DELETE');
			Vtiger_Helper_Js.showConfirmationBox({'message' : message}).then(
				function(e) {
					thisInstance.deleteCustomBlock(blockId);
				},
				function(error, err){

				}
			);
		});
	},

	/**
	 * Function to delete the custom block
	 */
	deleteCustomBlock : function(blockId) {
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
		params['action'] = 'Block';
		params['mode'] = 'delete';
		params['blockid'] = blockId;

		AppConnector.request(params).then(
			function(data) {
				progressIndicatorElement.progressIndicator({'mode' : 'hide'});
				var params = {};
				if(data['success']) {
					thisInstance.removeDeletedBlock(blockId);
					thisInstance.removeBlockFromBlocksList(blockId);
					params['text'] = app.vtranslate('JS_CUSTOM_BLOCK_DELETED');
				} else {
					params['text'] = data['error']['message'];
					params['type'] = 'error';
				}
				Settings_Vtiger_Index_Js.showMessage(params);
			},
			function(error) {
				progressIndicatorElement.progressIndicator({'mode' : 'hide'});
			}
		);
	},

	/**
	 * Function to remove the deleted custom block from the ui
	 */
	removeDeletedBlock : function(blockId) {
		var contents = jQuery('#layoutEditorContainer').find('.contents');
		var deletedTable = contents.find('.block_'+blockId);
		deletedTable.fadeOut('slow').remove();
	},

	/**
	 * Function to register the click event for delete custom field
	 */
	registerDeleteCustomFieldEvent : function(contents) {
		var thisInstance = this;
		if(typeof contents == 'undefined') {
			contents = jQuery('#layoutEditorContainer').find('.contents');
		}
		contents.find('a.deleteCustomField').click(function(e) {
			var currentTarget = jQuery(e.currentTarget);
			var fieldId = currentTarget.data('fieldId');
			var message = app.vtranslate('JS_LBL_ARE_YOU_SURE_YOU_WANT_TO_DELETE');
			Vtiger_Helper_Js.showConfirmationBox({'message' : message}).then(
				function(e) {
					thisInstance.deleteCustomField(fieldId).then(
						function(data) {
							var field = currentTarget.closest('div.editFields');
							var blockId = field.data('blockId');
							field.parent().fadeOut('slow').remove();
							var block = jQuery('#block_'+blockId);
							thisInstance.reArrangeBlockFields(block);
							var params = {};
							params['text'] = app.vtranslate('JS_CUSTOM_FIELD_DELETED');
							Settings_Vtiger_Index_Js.showMessage(params);
						},function(error, err) {

						}
					);
				},
				function(error, err){

				}
			);
		});
	},

	/**
	 * Function to delete the custom field
	 */
	deleteCustomField : function(fieldId) {
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
		params['action'] = 'Field';
		params['mode'] = 'delete';
		params['fieldid'] = fieldId;

		AppConnector.request(params).then(
			function(data) {
				progressIndicatorElement.progressIndicator({'mode' : 'hide'});
				aDeferred.resolve(data);
			},
			function(error) {
				progressIndicatorElement.progressIndicator({'mode' : 'hide'});
				aDeferred.reject();
			}
		);
		return aDeferred.promise();
	},


	/**
	 * Function to register the click event for save button after edit field details
	 */
	registerSaveFieldDetailsEvent : function(form) {
		var thisInstance = this;
		var submitButtton = form.find('.saveFieldDetails');
		var fieldId = submitButtton.data('fieldId');
		var block = submitButtton.closest('.editFieldsTable');
		var blockId = block.data('blockId');
		//close the drop down
		submitButtton.closest('.btn-group').removeClass('open');
		//adding class opacity to fieldRow - to give opacity to the actions of the fields
		var fieldRow = submitButtton.closest('.editFields');
		fieldRow.addClass('opacity');

		thisInstance.saveFieldDetails(submitButtton).then(
			function(data) {
				var result = data['result'];
				var fieldLabel = fieldRow.find('.fieldLabel');
				if(result['presence'] == '1') {
					fieldRow.parent().fadeOut('slow').remove();

					if(jQuery.isEmptyObject(thisInstance.inActiveFieldsList[blockId])) {
						if(thisInstance.inActiveFieldsList.length == '0') {
							thisInstance.inActiveFieldsList = {};
						}
						thisInstance.inActiveFieldsList[blockId] = {};
						thisInstance.inActiveFieldsList[blockId][fieldId] = result['label'];
					} else {
						thisInstance.inActiveFieldsList[blockId][fieldId] = result['label'];
					}
					thisInstance.reArrangeBlockFields(block);
				}
				if(result['mandatory']) {
					if(fieldLabel.find('.redColor').length == '0') {
						fieldRow.find('.fieldLabel').append(jQuery('<span class="redColor">*</span>'));
					}
				} else {
					fieldRow.find('.fieldLabel').find('.redColor').remove();
				}

				//updating the hidden container with saved values.
				var dropDownMenu = form.closest('.dropdown-menu');
				app.destroyChosenElement(form);
				var selectElemet = form.find('.defaultValueUi ').find('select');
				var selectedvalue = selectElemet.val();
				selectElemet.removeAttr('disabled');
				selectElemet.find('option').removeAttr('selected');
				if(selectedvalue != null ){
					if(typeof(selectElemet.attr('multiple')) == 'undefined'){
						var encodedSelectedValue = selectedvalue.replace(/"/g, '\\"');
						selectElemet.find('[value="'+encodedSelectedValue+'"]').attr('selected','selected');
					} else {
						for (var i = 0; i < selectedvalue.length; i++) {
							var encodedSelectedValue = selectedvalue[i].replace(/"/g, '\\"');
							selectElemet.find('[value="'+encodedSelectedValue+'"]').attr('selected','selected');
						}
					}
				}
				//handled registration of time field
				var timeFieldElement = form.find('[data-toregister="time"]');
				if(timeFieldElement.length > 0){
					app.destroyTimeFields(timeFieldElement);
				}
				var basicContents = form.closest('.editFields').find('.basicFieldOperations');
				basicContents.html(form);
				dropDownMenu.remove();
			},
			function(error, err) {

			}
			);
	},

	/**
	 * Function to save all the field details which are changed
	 */
	saveFieldDetails : function(currentTarget) {
		var thisInstance = this;
		var aDeferred = jQuery.Deferred();
		var form = currentTarget.closest('form.fieldDetailsForm');
		var fieldId = currentTarget.data('fieldId');
		var progressIndicatorElement = jQuery.progressIndicator({
			'position' : 'html',
			'blockInfo' : {
				'enabled' : true
			}
		});

		var params = form.serializeFormData();
		params['module'] = app.getModuleName();
		params['parent'] = app.getParentModuleName();
		params['action'] = 'Field';
		params['mode'] = 'save';
		params['fieldid'] = fieldId;
		params['sourceModule'] = jQuery('#selectedModuleName').val();

		AppConnector.request(params).then(
			function(data) {
				progressIndicatorElement.progressIndicator({'mode' : 'hide'});
				var params = {};
				params['text'] = app.vtranslate('JS_FIELD_DETAILS_SAVED');
				Settings_Vtiger_Index_Js.showMessage(params);
				aDeferred.resolve(data);
			},
			function(error) {
				progressIndicatorElement.progressIndicator({'mode' : 'hide'});
				aDeferred.reject();
			}
		);
		return aDeferred.promise();
	},

	/**
	 * Function to register the cahnge event for mandatory & default checkboxes in edit field details
	 */
	registerFieldDetailsChange : function(contents) {
		if(typeof contents == 'undefined') {
			contents = jQuery('#layoutEditorContainer').find('.contents');
		}
		contents.on('change', '[name="mandatory"]', function(e) {
			var currentTarget = jQuery(e.currentTarget);
			if(currentTarget.attr('readonly') != 'readonly') {
				var form = currentTarget.closest('.fieldDetailsForm');
				var quickcreateEle = form.find('[name="quickcreate"]').filter(':checkbox').not('.optionDisabled');
				var presenceEle = form.find('[name="presence"]').filter(':checkbox').not('.optionDisabled');
				if(currentTarget.is(':checked')) {
					quickcreateEle.attr('checked', true).attr('readonly', 'readonly');
					presenceEle.attr('checked', true).attr('readonly', 'readonly');
				} else {
					quickcreateEle.removeAttr('readonly');
					presenceEle.removeAttr('readonly');
				}
			}
		})

		contents.on('change', '[name="defaultvalue"]', function(e) {
			var currentTarget = jQuery(e.currentTarget);
			var defaultValueUi = currentTarget.closest('span').find('.defaultValueUi');
			var defaultField = defaultValueUi.find('[name="fieldDefaultValue"]');
			if(currentTarget.is(':checked')) {
				defaultValueUi.removeClass('zeroOpacity');
				defaultField.removeAttr('disabled');
				if(defaultField.is('select')){
					defaultField.trigger("liszt:updated");
				}
			} else {
				defaultField.attr('disabled', 'disabled');
			//	defaultField.val('');
				defaultValueUi.addClass('zeroOpacity');
			}
		})

	},

	/**
	 * Function to register the click event for related modules list tab
	 */
	relatedModulesTabClickEvent : function() {
		var thisInstance = this;
		var contents = jQuery('#layoutEditorContainer').find('.contents');
		var relatedContainer = contents.find('#relatedTabOrder');
		var relatedTab = contents.find('.relatedListTab');
		relatedTab.click(function() {
			if(relatedContainer.find('.relatedTabModulesList').length > 0) {

			} else {
				thisInstance.showRelatedTabModulesList(relatedContainer);
			}
		});
	},

	/**
	 * Function to show the related tab modules list in the tab
	 */
	showRelatedTabModulesList : function(relatedContainer) {
		var thisInstance = this;
		var params = {};
		params['module'] = app.getModuleName();
		params['parent'] = app.getParentModuleName();
		params['sourceModule'] = jQuery('#selectedModuleName').val();
		params['view'] = 'Index';
		params['mode'] = 'showRelatedListLayout';

		AppConnector.request(params).then(
			function(data) {
				relatedContainer.html(data);
				if(jQuery(data).find('.relatedListContainer').length > 0) {
					thisInstance.makeRelatedModuleSortable();
					thisInstance.registerRelatedListEvents();
					thisInstance.setRemovedModulesList();
				}
			},
			function(error) {
			}
		);
	},

	/**
	 * Function to get the respective module layout editor through pjax
	 */
	getModuleLayoutEditor : function(selectedModule) {
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
		params['view'] = 'Index';
		params['sourceModule'] = selectedModule;

		AppConnector.requestPjax(params).then(
			function(data) {
				progressIndicatorElement.progressIndicator({'mode' : 'hide'});
				aDeferred.resolve(data);
			},
			function(error) {
				progressIndicatorElement.progressIndicator({'mode' : 'hide'});
				aDeferred.reject();
			}
		);
		return aDeferred.promise();
	},

	/**
	 * Function to register the change event for layout editor modules list
	 */
	registerModulesChangeEvent : function() {
		var thisInstance = this;
		var container = jQuery('#layoutEditorContainer');
		var contentsDiv = container.closest('.contentsDiv');

		app.showSelect2ElementView(container.find('[name="layoutEditorModules"]'), {dropdownCss : {'z-index' : 0}});

		container.on('change', '[name="layoutEditorModules"]', function(e) {
			var currentTarget = jQuery(e.currentTarget);
			var selectedModule = currentTarget.val();
			thisInstance.getModuleLayoutEditor(selectedModule).then(
				function(data) {
					contentsDiv.html(data);
					thisInstance.registerEvents();
				}
			);
		});

	},

	/**
	 * Function to register click event for drop-downs in fields list
	 */
	avoidDropDownClick : function(dropDownContainer) {
		dropDownContainer.find('.dropdown-menu').click(function(e) {
			e.stopPropagation();
		});
	},

	registerEditFieldDetailsClick : function(contents) {
		var thisInstance = this;
		if(typeof contents == 'undefined') {
			contents = jQuery('#layoutEditorContainer').find('.contents');
		}
		contents.find('.editFieldDetails').click(function(e) {
			var currentTarget = jQuery(e.currentTarget);
			var fieldRow = currentTarget.closest('div.editFields');
			fieldRow.removeClass('opacity');
			var basicDropDown = fieldRow.find('.basicFieldOperations');
			var dropDownContainer = currentTarget.closest('.btn-group');
			dropDownContainer.find('.dropdown-menu').remove();
			var dropDown = basicDropDown.clone().removeClass('basicFieldOperations hide').addClass('dropdown-menu');
			dropDownContainer.append(dropDown);
			var dropDownMenu  = dropDownContainer.find('.dropdown-menu');
			var params = app.getvalidationEngineOptions(true);
			params.binded = false,
			params.onValidationComplete = function(form,valid){
				if(valid) {
					thisInstance.registerSaveFieldDetailsEvent(form);
				}
				return false;
			}
			dropDownMenu.find('form').validationEngine(params);
			var defaultValueUiContainer = basicDropDown.find('.defaultValueUi');


			//handled registration of chosen for select element
			var selectElements = defaultValueUiContainer.find('select');
			if(selectElements.length > 0) {
				dropDownMenu.find('select').addClass('chzn-select');
				app.changeSelectElementView(dropDownMenu);
			}

			//handled registration of time field
			var timeFieldElement = defaultValueUiContainer.find('[data-toregister="time"]');
			if(timeFieldElement.length > 0){
				dropDownMenu.find('[data-toregister="time"]').addClass('timepicker-default timePicker');
				app.registerEventForTimeFields(dropDownMenu);
			}

			//handled registration for date fields
			 var dateField = defaultValueUiContainer.find('[data-toregister="date"]');
			 if(dateField.length > 0) {
				 dropDownMenu.find('[data-toregister="date"]').addClass('dateField');
				 app.registerEventForDatePickerFields(dropDownMenu);
			 }
			thisInstance.avoidDropDownClick(dropDownContainer);

			dropDownMenu.on('change', ':checkbox', function(e) {
				var currentTarget = jQuery(e.currentTarget);
				if(currentTarget.attr('readonly') == 'readonly') {
					var status = jQuery(e.currentTarget).is(':checked');
					if(!status){
						jQuery(e.currentTarget).attr('checked','checked')
					}else{
						jQuery(e.currentTarget).removeAttr('checked');
					}
					e.preventDefault();
				}
			});

			//added for drop down position change
			var offset = currentTarget.offset(),
                height = currentTarget.outerHeight(),
                dropHeight = dropDown.outerHeight(),
                viewportBottom = $(window).scrollTop() + document.documentElement.clientHeight,
                dropTop = offset.top + height,
                enoughRoomBelow = dropTop + dropHeight <= viewportBottom;
			   if(!enoughRoomBelow) {
				   dropDown.addClass('bottom-up');
			   } else {
				   dropDown.removeClass('bottom-up');
			   }

			var callbackFunction = function() {
				fieldRow.addClass('opacity');
				dropDown.remove();
                jQuery('body').off('click.dropdown.data-api.layouteditor');
			}
			thisInstance.addClickOutSideEvent(dropDown, callbackFunction);
			jQuery('body').on('click.dropdown.data-api.layouteditor',function(e){
                var target = jQuery(e.target);
                //user clicked on time picker
                if(target.closest('.ui-timepicker-list').length > 0) {
                    e.stopPropagation();
                }
            })
		});
	},

	/**
	 * Function to register all the events for blocks
	 */
	registerBlockEvents : function() {
		var thisInstance = this;
		thisInstance.makeBlocksListSortable();
		thisInstance.registerAddCustomFieldEvent();
		thisInstance.registerBlockVisibilityEvent();
		thisInstance.registerInactiveFieldsEvent();
		thisInstance.registerDeleteCustomBlockEvent();
	},

	/**
	 * Function to register all the events for fields
	 */
	registerFieldEvents : function(contents) {
		var thisInstance = this;
		if(typeof contents == 'undefined') {
			contents = jQuery('#layoutEditorContainer').find('.contents');
		}
		app.registerEventForDatePickerFields(contents);
		app.registerEventForTimeFields(contents);
		app.changeSelectElementView(contents);

		thisInstance.makeFieldsListSortable();
		thisInstance.registerDeleteCustomFieldEvent(contents);
		thisInstance.registerFieldDetailsChange(contents);
		thisInstance.registerEditFieldDetailsClick(contents);

		contents.find(':checkbox').change(function(e) {
			var currentTarget = jQuery(e.currentTarget);
			if(currentTarget.attr('readonly') == 'readonly') {
				var status = jQuery(e.currentTarget).is(':checked');
				if(!status){
					jQuery(e.currentTarget).attr('checked','checked')
				}else{
					jQuery(e.currentTarget).removeAttr('checked');
				}
				e.preventDefault();
			}
		});
	},

    /*
	 * Function to add clickoutside event on the element - By using outside events plugin
	 * @params element---On which element you want to apply the click outside event
	 * @params callbackFunction---This function will contain the actions triggered after clickoutside event
	 */
	addClickOutSideEvent : function(element, callbackFunction) {
		element.one('clickoutside',callbackFunction);
	},

	/**
	 * register events for layout editor
	 */
	registerEvents : function() {
		var thisInstance = this;

		thisInstance.registerBlockEvents();
		thisInstance.registerFieldEvents();
		thisInstance.setInactiveFieldsList();
		thisInstance.registerAddCustomBlockEvent();
		thisInstance.registerFieldSequenceSaveClick();

		thisInstance.relatedModulesTabClickEvent();
		thisInstance.registerModulesChangeEvent();
	}

});

jQuery(document).ready(function() {
	var instance = new Settings_LayoutEditor_Js();
	instance.registerEvents();
})

Vtiger_WholeNumberGreaterThanZero_Validator_Js("Vtiger_FloatingDigits_Validator_Js",{
	
	/**
	 *Function which invokes field validation
	 *@param accepts field element as parameter
	 * @return error if validation fails true on success
	 */
	invokeValidation: function(field, rules, i, options){
		var rangeInstance = new Vtiger_FloatingDigits_Validator_Js();
		rangeInstance.setElement(field);
		var response = rangeInstance.validate();
		if(response != true){
			return rangeInstance.getError();
		}
	}
	
},{
	/**
	 * Function to validate the decimals length
	 * @return true if validation is successfull
	 * @return false if validation error occurs
	 */
	validate: function(){
		var response = this._super();
		if(response != true){
			return response;
		}else{
			var fieldValue = this.getFieldValue();
			if (fieldValue < 2 || fieldValue > 5) {
				var errorInfo = app.vtranslate('JS_PLEASE_ENTER_NUMBER_IN_RANGE_2TO5');
				this.setError(errorInfo);
				return false;
			}
			
			var specialChars = /^[+]/ ;
			if (specialChars.test(fieldValue)) {
				var error = app.vtranslate('JS_CONTAINS_ILLEGAL_CHARACTERS');
				this.setError(error);
				return false;
			}
			return true;
		}
	}
});

Vtiger_WholeNumberGreaterThanZero_Validator_Js("Vtiger_DecimalMaxLength_Validator_Js",{
	
	/**
	 *Function which invokes field validation
	 *@param accepts field element as parameter
	 * @return error if validation fails true on success
	 */
	invokeValidation: function(field, rules, i, options){
		var rangeInstance = new Vtiger_DecimalMaxLength_Validator_Js();
		rangeInstance.setElement(field);
		var response = rangeInstance.validate();
		if(response != true){
			return rangeInstance.getError();
		}
	}
	
},{
	/**
	 * Function to validate the fieldLength
	 * @return true if validation is successfull
	 * @return false if validation error occurs
	 */
	validate: function(){
		var response = this._super();
		if(response != true){
			return response;
		}else{
			var fieldValue = this.getFieldValue();
			var decimalFieldValue = jQuery('#createFieldForm').find('[name="decimal"]').val();
			var fieldLength = parseInt(64)-parseInt(decimalFieldValue);
			if (fieldValue > fieldLength && !(fieldLength < 0) && fieldLength >= 59) {
				var errorInfo = app.vtranslate('JS_LENGTH_SHOULD_BE_LESS_THAN_EQUAL_TO')+' '+fieldLength;
				this.setError(errorInfo);
				return false;
			}
			
			var specialChars = /^[+]/ ;
			if (specialChars.test(fieldValue)) {
				var error = app.vtranslate('JS_CONTAINS_ILLEGAL_CHARACTERS');
				this.setError(error);
				return false;
			}
			return true;
		}
	}
});

Vtiger_WholeNumberGreaterThanZero_Validator_Js("Vtiger_MaxLength_Validator_Js",{
	
	/**
	 *Function which invokes field validation
	 *@param accepts field element as parameter
	 * @return error if validation fails true on success
	 */
	invokeValidation: function(field, rules, i, options){
		var rangeInstance = new Vtiger_DecimalMaxLength_Validator_Js();
		rangeInstance.setElement(field);
		var response = rangeInstance.validate();
		if(response != true){
			return rangeInstance.getError();
		}
	}
	
},{
	/**
	 * Function to validate the fieldLength
	 * @return true if validation is successfull
	 * @return false if validation error occurs
	 */
	validate: function(){
		var response = this._super();
		if(response != true){
			return response;
		}else{
			var fieldValue = this.getFieldValue();
			if (fieldValue > 255) {
				var errorInfo = app.vtranslate('JS_LENGTH_SHOULD_BE_LESS_THAN_EQUAL_TO')+' 255';
				this.setError(errorInfo);
				return false;
			}
			
			var specialChars = /^[+]/ ;
			if (specialChars.test(fieldValue)) {
				var error = app.vtranslate('JS_CONTAINS_ILLEGAL_CHARACTERS');
				this.setError(error);
				return false;
			}
			return true;
		}
	}
});

Vtiger_Base_Validator_Js("Vtiger_FieldLabel_Validator_Js",{
	
	/**
	 *Function which invokes field validation
	 *@param accepts field element as parameter
	 * @return error if validation fails true on success
	 */
	invokeValidation: function(field, rules, i, options){
		var instance = new Vtiger_FieldLabel_Validator_Js();
		instance.setElement(field);
		var response = instance.validate();
		if(response != true){
			return instance.getError();
		}
	}
	
},{
	/**
	 * Function to validate the field label
	 * @return true if validation is successfull
	 * @return false if validation error occurs
	 */
	validate: function(){
		var fieldValue = this.getFieldValue();
		return this.validateValue(fieldValue);
	},
	
	validateValue : function(fieldValue){
		var specialChars = /[&\<\>\:\'\"\,\_]/ ;

		if (specialChars.test(fieldValue)) {
			var errorInfo = app.vtranslate('JS_SPECIAL_CHARACTERS')+" & < > ' \" : , _ "+app.vtranslate('JS_NOT_ALLOWED');
			this.setError(errorInfo);
			return false;
		} 
        return true;
	}
});

Vtiger_Base_Validator_Js("Vtiger_PicklistFieldValues_Validator_Js",{
	
	/**
	 *Function which invokes field validation
	 *@param accepts field element as parameter
	 * @return error if validation fails true on success
	 */
	invokeValidation: function(field, rules, i, options){
		var instance = new Vtiger_PicklistFieldValues_Validator_Js();
		instance.setElement(field);
		var response = instance.validate();
		if(response != true){
			return instance.getError();
		}
	}
	
},{
	/**
	 * Function to validate the field label
	 * @return true if validation is successfull
	 * @return false if validation error occurs
	 */
	validate: function(){
		var fieldValue = this.getFieldValue();
		return this.validateValue(fieldValue);
	},
	
	validateValue : function(fieldValue){
		var specialChars = /(\<|\>)/gi ;
		if (specialChars.test(fieldValue)) {
			var errorInfo = app.vtranslate('JS_SPECIAL_CHARACTERS')+" < >"+app.vtranslate('JS_NOT_ALLOWED');
			this.setError(errorInfo);
			return false;
		} 
        return true;
	}
});