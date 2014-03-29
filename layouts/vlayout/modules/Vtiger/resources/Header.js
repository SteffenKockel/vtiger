/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

jQuery.Class("Vtiger_Header_Js", {
    quickCreateModuleCache: {},
    self: false,
    getInstance: function() {
        if (this.self != false) {
            return this.self;
        }
        this.self = new Vtiger_Header_Js();
        return this.self;
    }
}, {
    menuContainer: false,
    contentContainer: false,
    quickCreateCallBacks: [],
    init: function() {
        this.setMenuContainer('.navbar-fixed-top').setContentsContainer('.mainContainer');
    },
    setMenuContainer: function(element) {
        if (element instanceof jQuery) {
            this.menuContainer = element;
        } else {
            this.menuContainer = jQuery(element);
        }
        return this;
    },
    getMenuContainer: function() {
        return this.menuContainer;
    },
    setContentsContainer: function(element) {
        if (element instanceof jQuery) {
            this.contentContainer = element;
        } else {
            this.contentContainer = jQuery(element);
        }
        return this;
    },
    getContentsContainer: function() {
        return this.contentContainer;
    },
    getQuickCreateForm: function(url, moduleName, params) {
        var thisInstance = this;
        var aDeferred = jQuery.Deferred();
        var requestParams;
        if (typeof params == 'undefined') {
            params = {};
        }
        if ((!params.noCache) || (typeof (params.noCache) == "undefined")) {
            if (typeof Vtiger_Header_Js.quickCreateModuleCache[moduleName] != 'undefined') {
                aDeferred.resolve(Vtiger_Header_Js.quickCreateModuleCache[moduleName]);
                return aDeferred.promise();
            }
        }
        requestParams = url;
        if (typeof params.data != "undefined") {
            var requestParams = {};
            requestParams['data'] = params.data;
            requestParams['url'] = url;
        }
        AppConnector.request(requestParams).then(function(data) {
            if ((!params.noCache) || (typeof (params.noCache) == "undefined")) {
                Vtiger_Header_Js.quickCreateModuleCache[moduleName] = data;
            }
            aDeferred.resolve(data);
        });
        return aDeferred.promise();
    },
    registerQuickCreateCallBack: function(callBackFunction) {
        if (typeof callBackFunction != 'function') {
            return false;
        }
        this.quickCreateCallBacks.push(callBackFunction);
        return true;
    },
    /**
     * Function which will align the contents container at specified height depending on the top fixed menu
     * It will caliculate the height by following formaula menuContianer.height+1	 *
     */
    alignContentsContainer: function(topValue,speed, effect) {
        if (typeof topValue == 'undefined') {
            topValue = '69px';
        }
        var contentsContainer = this.getContentsContainer();
        contentsContainer.animate({'margin-top': topValue}, speed, effect);
        return this;
    },
    /**
     * Function to save the quickcreate module
     * @param accepts form element as parameter
     * @return returns deferred promise
     */
    quickCreateSave: function(form) {
        var aDeferred = jQuery.Deferred();
        var quickCreateSaveUrl = form.serializeFormData();
        AppConnector.request(quickCreateSaveUrl).then(
                function(data) {
                    //TODO: App Message should be shown
                    aDeferred.resolve(data);
                },
                function(textStatus, errorThrown) {
                    aDeferred.reject(textStatus, errorThrown);
                }
        );
        return aDeferred.promise();
    },
    /**
     * Function to navigate from quickcreate to editView Fullform
     * @param accepts form element as parameter
     */
    quickCreateGoToFullForm: function(form, editViewUrl) {
        var formData = form.serializeFormData();
        //As formData contains information about both view and action removed action and directed to view
        delete formData.module;
        delete formData.action;
        var formDataUrl = jQuery.param(formData);
        var completeUrl = editViewUrl + "&" + formDataUrl;
        window.location.href = completeUrl;
    },
    setAnnouncement: function() {
//        var announcementoff = app.cacheGet('announcement.turnoff' , false);
//        var announcementBtn = jQuery('#announcementBtn');
//        var thisInstance = this;
//        if(announcementoff === true) {
//            jQuery('#announcement').hide();
//            announcementBtn.attr('src', app.vimage_path('btnAnnounceOff.png'));
//            thisInstance.alignContentsContainer('69px',0,'linear');
//        }
//        else{
//            jQuery('#announcement').show();
//            announcementBtn.attr('src', app.vimage_path('btnAnnounce.png'));
//            thisInstance.alignContentsContainer('92px',0,'linear');
//        } 
    },
    registerAnnouncement: function() {
        var thisInstance = this;
        var announcementBtn = jQuery('#announcementBtn');
        var announcementTurnOffKey = 'announcement.turnoff';

        announcementBtn.click(function(e, manual) {
            var displayStatus = jQuery('#announcement').css('display');
            if (displayStatus == 'none') {
                jQuery('#announcement').show();
                thisInstance.alignContentsContainer('92px',200,'linear');
                announcementBtn.attr('src', app.vimage_path('btnAnnounce.png'));

                // Turn-on always
                if (!manual) {
                    app.cacheSet(announcementTurnOffKey, false);
                }
            } else {
                thisInstance.alignContentsContainer('69px',200,'linear');
                jQuery('#announcement').hide();
                announcementBtn.attr('src', app.vimage_path('btnAnnounceOff.png'));

                // Turn-off always
                // NOTE: Add preference on server - to reenable on announcement content change.
                if (!manual) {
                    app.cacheSet(announcementTurnOffKey, true);
                }

            }
        });

        if (app.cacheGet(announcementTurnOffKey, false)) {
            announcementBtn.trigger('click', true);
        }
    },
    registerCalendarButtonClickEvent: function() {
        var element = jQuery('#calendarBtn');
        var dateFormat = element.data('dateFormat');
        var currentDate = element.data('date');
        var vtigerDateFormat = app.convertToDatePickerFormat(dateFormat);
        element.on('click', function(e) {
            e.stopImmediatePropagation();
            element.closest('div.nav').find('div.open').removeClass('open');
            var calendar = jQuery('#' + element.data('datepickerId'));
            if (jQuery(calendar).is(':visible')) {
                element.DatePickerHide();
            } else {
                element.DatePickerShow();
            }
        })
        element.DatePicker({
            format: vtigerDateFormat,
            date: currentDate,
            calendars: 1,
            starts: 1,
            className: 'globalCalendar'
        });
    },
    handleQuickCreateData: function(data, params) {
        if (typeof params == 'undefined') {
            params = {};
        }
        var thisInstance = this;
        app.showModalWindow(data, function(data) {
            var quickCreateForm = data.find('form[name="QuickCreate"]');
            var moduleName = quickCreateForm.find('[name="module"]').val();
            var editViewInstance = Vtiger_Edit_Js.getInstanceByModuleName(moduleName);
            editViewInstance.registerBasicEvents(quickCreateForm);
            quickCreateForm.validationEngine(app.validationEngineOptions);
            if (typeof params.callbackPostShown != "undefined") {
                params.callbackPostShown(quickCreateForm);
            }
            thisInstance.registerQuickCreatePostLoadEvents(quickCreateForm, params);
            app.registerEventForDatePickerFields(quickCreateForm);
            var quickCreateContent = quickCreateForm.find('.quickCreateContent');
            var quickCreateContentHeight = quickCreateContent.height();
            var contentHeight = parseInt(quickCreateContentHeight);
            if (contentHeight > 300) {
                app.showScrollBar(jQuery('.quickCreateContent'), {
                    'height': '300px'
                });
            }
        });
    },
    registerQuickCreatePostLoadEvents: function(form, params) {
        var thisInstance = this;
        var submitSuccessCallbackFunction = params.callbackFunction;
        var goToFullFormCallBack = params.goToFullFormcallback;
        if (typeof submitSuccessCallbackFunction == 'undefined') {
            submitSuccessCallbackFunction = function() {
            };
        }

        form.on('submit', function(e) {
            var form = jQuery(e.currentTarget);
            var module = form.find('[name="module"]').val();
            //Form should submit only once for multiple clicks also
            if (typeof form.data('submit') != "undefined") {
                return false;
            } else {
                var invalidFields = form.data('jqv').InvalidFields;

                if (invalidFields.length > 0) {
                    //If validation fails, form should submit again
                    form.removeData('submit');
                    form.closest('#globalmodal').find('.modal-header h3').progressIndicator({
                        'mode': 'hide'
                    });
                    e.preventDefault();
                    return;
                } else {
                    //Once the form is submiting add data attribute to that form element
                    form.data('submit', 'true');
                    form.closest('#globalmodal').find('.modal-header h3').progressIndicator({
                        smallLoadingImage: true,
                        imageContainerCss: {
                            display: 'inline',
                            'margin-left': '18%',
                            position: 'absolute'
                        }
                    });
                }

                var recordPreSaveEvent = jQuery.Event(Vtiger_Edit_Js.recordPreSave);
                form.trigger(recordPreSaveEvent, {
                    'value': 'edit',
                    'module': module
                });
                if (!(recordPreSaveEvent.isDefaultPrevented())) {
                    thisInstance.quickCreateSave(form).then(
                            function(data) {
                                app.hideModalWindow();
                                submitSuccessCallbackFunction(data);
                                var registeredCallBackList = thisInstance.quickCreateCallBacks;
                                for (var index = 0; index < registeredCallBackList.length; index++) {
                                    var callBack = registeredCallBackList[index];
                                    callBack({
                                        'data': data,
                                        'name': form.find('[name="module"]').val()
                                    });
                                }
                            },
                            function(error, err) {
                            }
                    );
                } else {
                    //If validation fails in recordPreSaveEvent, form should submit again
                    form.removeData('submit');
                    form.closest('#globalmodal').find('.modal-header h3').progressIndicator({
                        'mode': 'hide'
                    });
                }
                e.preventDefault();
            }
        });

        form.find('#goToFullForm').on('click', function(e) {
            var form = jQuery(e.currentTarget).closest('form');
            var editViewUrl = jQuery(e.currentTarget).data('editViewUrl');
            if (typeof goToFullFormCallBack != "undefined") {
                goToFullFormCallBack(form);
            }
            thisInstance.quickCreateGoToFullForm(form, editViewUrl);
        });

        this.registerTabEventsInQuickCreate(form);
    },
    registerTabEventsInQuickCreate: function(form) {
        var tabElements = form.find('.nav.nav-pills , .nav.nav-tabs').find('a');

        //This will remove the name attributes and assign it to data-element-name . We are doing this to avoid
        //Multiple element to send as in calendar
        var quickCreateTabOnHide = function(tabElement) {
            var container = jQuery(tabElement.attr('data-target'));

            container.find('[name]').each(function(index, element) {
                element = jQuery(element);
                element.attr('data-element-name', element.attr('name')).removeAttr('name');
            });
        }

        //This will add the name attributes and get value from data-element-name . We are doing this to avoid
        //Multiple element to send as in calendar
        var quickCreateTabOnShow = function(tabElement) {
            var container = jQuery(tabElement.attr('data-target'));

            container.find('[data-element-name]').each(function(index, element) {
                element = jQuery(element);
                element.attr('name', element.attr('data-element-name')).removeAttr('data-element-name');
            });
        }

        tabElements.on('shown', function(e) {
            var previousTab = jQuery(e.relatedTarget);
            var currentTab = jQuery(e.currentTarget);

            quickCreateTabOnHide(previousTab);
            quickCreateTabOnShow(currentTab);
            
            //while switching tabs we have to clear the invalid fields list
            form.data('jqv').InvalidFields = [];

        });
		
        //To show aleady non active element , this we are doing so that on load we can remove name attributes for other fields
        quickCreateTabOnHide(tabElements.closest('li').filter(':not(.active)').find('a'));
    },
    basicSearch: function() {
        var thisInstance = this;
        jQuery('#globalSearchValue').keypress(function(e) {
            var currentTarget = jQuery(e.currentTarget)
            if (e.which == 13) {
                thisInstance.labelSearch(currentTarget);
            }
        });
    },
    labelSearch: function(currentTarget) {
        var val = currentTarget.val();
        if (val == '') {
            alert(app.vtranslate('JS_PLEASE_ENTER_SOME_VALUE'));
            currentTarget.focus();
            return false;
        }
        var basicSearch = new Vtiger_BasicSearch_Js();
        var progress = jQuery.progressIndicator();
        basicSearch.search(val).then(function(data) {
            basicSearch.showSearchResults(data);
            progress.progressIndicator({
                'mode': 'hide'
            });
        });
    },
    registerEvents: function() {
        var thisInstance = this;
        jQuery('#globalSearch').click(function() {
            var advanceSearchInstance = new Vtiger_AdvanceSearch_Js();
            advanceSearchInstance.initiateSearch().then(function() {
                advanceSearchInstance.selectBasicSearchValue();
            });
        });
        jQuery('#searchIcon').on('click', function(e) {
            var currentTarget = jQuery('#globalSearchValue');
            var pressEvent = jQuery.Event("keypress");
            pressEvent.which = 13;
            currentTarget.trigger(pressEvent);
        });
        thisInstance.registerAnnouncement();
        //this.setAnnouncement();
        jQuery('#announcementBtn').trigger('click');

        this.registerCalendarButtonClickEvent();
        jQuery('#moreMenu').click(function(e) {
            var moreElem = jQuery(e.currentTarget);
            var moreMenu = jQuery('.moreMenus', moreElem)
            var index = jQuery(".modulesList > li", thisInstance.getMenuContainer()).length;
            // for left aligning the more menus dropdown if the modules list is below 5
            if (index < 5) {
                moreMenu.css('left', 0).addClass('leftAligned');
            }
        });

        //After selecting the global search module, focus the input element to type
        jQuery('#basicSearchModulesList').change(function() {
            jQuery('#globalSearchValue').focus();
        });

        thisInstance.basicSearch();
        jQuery('#quickCreateModules').on("click", ".quickCreateModule", function(e, params) {
            if (typeof params == 'undefined') {
                params = {};
            }

            if (typeof params.callbackFunction == 'undefined') {
                params.callbackFunction = function() {
                };
            }

            var quickCreateElem = jQuery(e.currentTarget);
            var quickCreateUrl = quickCreateElem.data('url');
            var quickCreateModuleName = quickCreateElem.data('name');

            var progress = jQuery.progressIndicator();
            thisInstance.getQuickCreateForm(quickCreateUrl, quickCreateModuleName, params).then(function(data) {
                thisInstance.handleQuickCreateData(data, params);
                progress.progressIndicator({
                    'mode': 'hide'
                });
            });

        });
        jQuery('#basicSearchModulesList_chzn').find('.chzn-results').slimScroll({
            height: '450px',
            railVisible: true,
            alwaysVisible: true,
            size: '6px',
        });



        //Added to support standard resolution 1024x768
        if (window.outerWidth <= 1024) {
            $('.headerLinksContainer').css('margin-right', '8px');
        }

        // setting sidebar Height wrt Content
        var contentsHeight = jQuery('.contentsDiv').outerHeight();
        jQuery(".mainContainer > .span2").css('height', contentsHeight);
    }
});
jQuery(document).ready(function() {


    Vtiger_Header_Js.getInstance().registerEvents();

});