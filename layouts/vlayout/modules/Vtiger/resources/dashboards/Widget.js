/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

jQuery.Class('Vtiger_Widget_Js',{

	widgetPostLoadEvent : 'Vtiget.Dashboard.PostLoad',
	widgetPostRefereshEvent : 'Vtiger.Dashboard.PostRefresh',

	getInstance : function(container, widgetName, moduleName) {
		if(typeof moduleName == 'undefined') {
			moduleName = app.getModuleName();
		}
		var widgetClassName = widgetName.toCamelCase();
		var moduleClass = window[moduleName+"_"+widgetClassName+"_Widget_Js"];
		var fallbackClass = window["Vtiger_"+widgetClassName+"_Widget_Js"];
		
		var basicClass = Vtiger_Widget_Js;
		if(typeof moduleClass != 'undefined') {
			var instance = new moduleClass(container);
		}else if(typeof fallbackClass != 'undefined') {
			var instance = new fallbackClass(container);
		}
		else{
			var instance = new basicClass(container);
		}
		return instance;
	}
},{

	container : false,
	plotContainer : false,

	init : function (container) {
		this.setContainer(jQuery(container));
		this.registerWidgetPostLoadEvent(container);
		this.registerWidgetPostRefreshEvent(container);
	},

	getContainer : function() {
		return this.container;
	},

	setContainer : function(element) {
		this.container = element;
		return this;
	},

	isEmptyData : function() {
		var container = this.getContainer();
		return (container.find('.noDataMsg').length > 0) ? true : false;
	},

	getUserDateFormat : function() {
		return jQuery('#userDateFormat').val();
	},


	getPlotContainer : function(useCache) {
		if(typeof useCache == 'undefined'){
			useCache = false;
		}
		if(this.plotContainer == false || !useCache) {
			var container = this.getContainer();
			this.plotContainer = container.find('.widgetChartContainer');
		}
		return this.plotContainer;
	},

	restrictContentDrag : function(){
		this.getContainer().on('mousedown.draggable', function(e){
			var element = jQuery(e.target);
			var isHeaderElement = element.closest('.dashboardWidgetHeader').length > 0 ? true : false;
			if(isHeaderElement){
				return;
			}
			//Stop the event propagation so that drag will not start for contents
			e.stopPropagation();
		})
	},

	convertToDateRangePicketFormat : function(userDateFormat) {
		if(userDateFormat == 'yyyy-mm-dd') {
			return 'yyyy-MM-dd';
		}else if( userDateFormat == 'mm-dd-yyyy') {
			return 'MM-dd-yyyy';
		}else if(userDateFormat == 'dd-mm-yyyy') {
			return 'dd-MM-yyyy';
		}
	},

	loadChart : function() {

	},

	positionNoDataMsg : function() {
		var container = this.getContainer();
		var widgetContentsContainer = container.find('.dashboardWidgetContent');
		var noDataMsgHolder = widgetContentsContainer.find('.noDataMsg');
		noDataMsgHolder.position({
				'my' : 'center center',
				'at' : 'center center',
				'of' : widgetContentsContainer
		})
	},


	//Place holdet can be extended by child classes and can use this to handle the post load
	postLoadWidget : function() {
		if(!this.isEmptyData()) {
			this.loadChart();
		}else{
			this.positionNoDataMsg();
		}
		this.registerSectionClick();
		this.registerFilter();
		this.registerFilterChangeEvent();
		this.restrictContentDrag();
	},

	postRefreshWidget : function() {
		if(!this.isEmptyData()) {
			this.loadChart();
		}else{
			this.positionNoDataMsg();
		}
	},

	getFilterData : function() {
		return {};
	},

	refreshWidget : function() {
		var parent = this.getContainer();
		var element = parent.find('a[name="drefresh"]');
		var url = element.data('url');

		var contentContainer = parent.find('.dashboardWidgetContent');
		var params = url;
		var widgetFilters = parent.find('.widgetFilter');
		if(widgetFilters.length > 0) {
			params = {};
			params.url = url;
			params.data = {}
			widgetFilters.each(function(index, domElement){
				var widgetFilter = jQuery(domElement);
				if(widgetFilter.is('.dateRange')){
					var dateRangeVal = widgetFilter.val();
					//If not value exists for date field then dont send the value
					if(dateRangeVal.length <= 0) {
						return true;
					}
					var name = widgetFilter.attr('name');
					var dateRangeValComponents = dateRangeVal.split(',');
					params.data[name] = {};
					params.data[name].start = dateRangeValComponents[0];
					params.data[name].end = dateRangeValComponents[1];
				}else{
					var filterName = widgetFilter.attr('name');
					var filterValue = widgetFilter.val();
					params.data[filterName] = filterValue;
				}
			});
		}
		var filterData = this.getFilterData();
		if(! jQuery.isEmptyObject(filterData)) {
			if(typeof params == 'string') {
				url = params;
				params = {};
				params.url = url
				params.data = {};
			}
			params.data = jQuery.extend(params.data, this.getFilterData())
		}
		var refreshContainer = parent.find('.refresh');
		refreshContainer.progressIndicator({
			'smallLoadingImage' : true
		});
		AppConnector.request(params).then(
			function(data){
				refreshContainer.progressIndicator({'mode': 'hide'});
				contentContainer.html(data).trigger(Vtiger_Widget_Js.widgetPostRefereshEvent);
			},
			function(){
				refreshContainer.progressIndicator({'mode': 'hide'});
			}
		);
	},

	registerFilter : function() {
		var thisInstance = this;
		var container = this.getContainer();
		var dateRangeElement = container.find('input.dateRange');
		var dateChanged = false;
		if(dateRangeElement.length <= 0) {
			return;
		}
		var customParams = {
			calendars: 3,
			mode: 'range',
			className : 'rangeCalendar',
			onChange: function(formated) {
				dateChanged = true;
				var element = jQuery(this).data('datepicker').el;
				jQuery(element).val(formated);
			},
			onHide : function() {
				if(dateChanged){
					container.find('a[name="drefresh"]').trigger('click');
					dateChanged = false;
				}
			},
			onBeforeShow : function(elem) {
				jQuery(elem).css('z-index','3');
			}
		}
		dateRangeElement.addClass('dateField').attr('data-date-format',thisInstance.getUserDateFormat());
		app.registerEventForDatePickerFields(dateRangeElement,false,customParams);
	},

	registerFilterChangeEvent : function() {
		this.getContainer().on('change', '.widgetFilter', function(e) {
			var widgetContainer = jQuery(e.currentTarget).closest('li');
			widgetContainer.find('a[name="drefresh"]').trigger('click');
		})
	},

	registerWidgetPostLoadEvent : function(container) {
		var thisInstance = this;
		container.on(Vtiger_Widget_Js.widgetPostLoadEvent, function(e) {
			thisInstance.postLoadWidget();
		})
	},

	registerWidgetPostRefreshEvent : function(container) {
		var thisInstance = this;
		container.on(Vtiger_Widget_Js.widgetPostRefereshEvent, function(e) {
			thisInstance.postRefreshWidget();
		});
	},

	registerSectionClick : function() {
		this.getContainer().on('jqplotClick', function() {
			var sectionData = arguments[3];
			var assignedUserId = sectionData[0];
			//TODO : we need to construct the list url with the sales stage and filters
		});

	}
});

Vtiger_Widget_Js('Vtiger_History_Widget_Js', {}, {
	
	postLoadWidget: function() {
		this._super();
		
		var widgetContent = jQuery('.dashboardWidgetContent', this.getContainer());
		widgetContent.css({height: widgetContent.height()-40});
		this.registerLoadMore();
	},
	
	postRefreshWidget: function() {
		this._super();
		this.registerLoadMore();
	},
	
	registerLoadMore: function() {
		var thisInstance  = this;
		var parent = thisInstance.getContainer();
		var contentContainer = parent.find('.dashboardWidgetContent');

		var loadMoreHandler = contentContainer.find('.load-more');
		loadMoreHandler.click(function(){
			var parent = thisInstance.getContainer();
			var element = parent.find('a[name="drefresh"]');
			var url = element.data('url');
			var params = url;

			var widgetFilters = parent.find('.widgetFilter');
			if(widgetFilters.length > 0) {
				params = { url: url, data: {}};
				widgetFilters.each(function(index, domElement){
					var widgetFilter = jQuery(domElement);
					var filterName = widgetFilter.attr('name');
					var filterValue = widgetFilter.val();
					params.data[filterName] = filterValue;
				});
			}
		
			var filterData = thisInstance.getFilterData();
			if(! jQuery.isEmptyObject(filterData)) {
				if(typeof params == 'string') {
					params = { url: url, data: {}};
				}
				params.data = jQuery.extend(params.data, thisInstance.getFilterData())
			}
			
			// Next page.
			params.data['page'] = loadMoreHandler.data('nextpage');
			
			var refreshContainer = parent.find('.refresh');
			refreshContainer.progressIndicator({
				'smallLoadingImage' : true
			});
			AppConnector.request(params).then(function(data){
				refreshContainer.progressIndicator({'mode': 'hide'});
				loadMoreHandler.replaceWith(data);
				thisInstance.registerLoadMore();
			}, function(){
				refreshContainer.progressIndicator({'mode': 'hide'});
			});
		});
	}
	
});


Vtiger_Widget_Js('Vtiger_Funnel_Widget_Js',{},{

	loadChart : function() {
		var container = this.getContainer();
		var data = container.find('.widgetData').val();
		var labels = new Array();
		var dataInfo = JSON.parse(data);
		for(var i=0; i<dataInfo.length; i++) {
			labels[i] = dataInfo[i][2];
			dataInfo[i][1] = parseFloat(dataInfo[i][1]);
		}
		this.getPlotContainer(false).jqplot([dataInfo],  {
			seriesDefaults: {
				renderer:jQuery.jqplot.FunnelRenderer,
				rendererOptions:{
					sectionMargin: 12,
					widthRatio: 0.3,
					showDataLabels:true,
					dataLabels: 'value'
				}
			},
			legend: {
				show: true,
				location: 'ne',
				placement: 'outside',
				labels:labels,
				xoffset:20
			}
		});
	},


	registerSectionClick : function() {
		this.getContainer().on('jqplotDataClick', function() {
			var sectionData = arguments[3];
			var salesStageValue = sectionData[0];
			//TODO : we need to construct the list url with the sales stage and filters
		})

	}
});



Vtiger_Widget_Js('Vtiger_Pie_Widget_Js',{},{

	/**
	 * Function which will give chart related Data
	 */
	getChartRelatedData : function() {
		var container = this.getContainer();
		var jData = container.find('.widgetData').val();
		var data = JSON.parse(jData);
		var chartData = [];
		for(var index in data) {
			var row = data[index];
			var rowData = [row.last_name, parseFloat(row.amount), row.id];
			chartData.push(rowData);
		}
		return chartData;
	},
	
	loadChart : function() {
		var chartData = this.getChartRelatedData();
		
		this.getPlotContainer(false).jqplot([chartData], {
			seriesDefaults:{
				renderer:jQuery.jqplot.PieRenderer,
				rendererOptions: {
					showDataLabels: true,
					dataLabels: 'value'
				}
			},
			legend: {
				show: true,
				location: 'e'
			}
		});
	},

	registerSectionClick : function() {
		this.getPlotContainer().on('jqplotDataClick', function() {
			var sectionData = arguments[3];
			var assignedUserId = sectionData[2];
			//TODO : we need to construct the list url with the sales stage and filters
		})

	}
});


Vtiger_Widget_Js('Vtiger_Barchat_Widget_Js',{},{

	loadChart : function() {
		var container = this.getContainer();
		var jData = container.find('.widgetData').val();
		var data = JSON.parse(jData);
		var chartData = [];
		var xLabels = new Array();
		var yMaxValue = 0;
		for(var index in data) {
			var row = data[index];
			row[0] = parseInt(row[0]);
			xLabels.push(app.getDecodedValue(row[1]))
			chartData.push(row[0]);
			if(parseInt(row[0]) > yMaxValue){
				yMaxValue = parseInt(row[0]);
			}
		}
		yMaxValue = yMaxValue + 2;
		this.getPlotContainer(false).jqplot([chartData], {
			seriesDefaults:{
				renderer:jQuery.jqplot.BarRenderer,
				rendererOptions: {
					showDataLabels: true,
					dataLabels: 'value',
					varyBarColor : true
				},
				pointLabels: {show: true}
			},
			 axes: {
				xaxis: {
					  tickRenderer: jQuery.jqplot.CanvasAxisTickRenderer,
					  renderer: jQuery.jqplot.CategoryAxisRenderer,
					  ticks: xLabels,
					  tickOptions: {
						angle: -45
					  }
				},
				yaxis: {
					min:0,
					max: yMaxValue,
					 tickOptions: {
						formatString: '%d'
					},
					pad : 1.2
				}
			}
		});
	}
});

Vtiger_Widget_Js('Vtiger_MultiBarchat_Widget_Js',{

	/**
	 * Function which will give char related Data like data , x labels and legend labels as map
	 */
	getCharRelatedData : function() {
		var container = this.getContainer();
		var data = container.find('.widgetData').val();
		var users = new Array();
		var stages = new Array();
		var count = new Array();
		for(var i=0; i<data.length;i++) {
			if($.inArray(data[i].last_name, users) == -1) {
				users.push(data[i].last_name);
			}
			if($.inArray(data[i].sales_stage, stages) == -1) {
				stages.push(data[i].sales_stage);
			}
		}
		
		for(j in stages) {
			var salesStageCount = new Array();
			for(i in users) {
				var salesCount = 0;
				for(var k in data) {
					var userData = data[k];
					if(userData.sales_stage == stages[j] && userData.last_name == users[i]) {
						salesCount = parseInt(userData.count);
						break;
					}
				}
				salesStageCount.push(salesCount);
			}
			count.push(salesStageCount);
		}
		return {
			'data' : count,
			'ticks' : users,
			'labels' : stages
		}
	},

	loadChart : function(){
		var chartRelatedData = this.getCharRelatedData();
		var chartData = chartRelatedData.data;
		var ticks = chartRelatedData.ticks;
		var labels = chartRelatedData.labels;

this.getPlotContainer(false).jqplot( chartData, {
			stackSeries: true,
			captureRightClick: true,
			seriesDefaults:{
				renderer:$.jqplot.BarRenderer,
				rendererOptions: {
					// Put a 30 pixel margin between bars.
					barMargin: 10,
					// Highlight bars when mouse button pressed.
					// Disables default highlighting on mouse over.
					highlightMouseDown: true
				},
				pointLabels: {show: true}
			},
			axes: {
				xaxis: {
					renderer: $.jqplot.CategoryAxisRenderer,
					tickRenderer: $.jqplot.CanvasAxisTickRenderer,
					tickOptions: {
						angle: -45
					},
					ticks: ticks
				},
				yaxis: {
					// Don't pad out the bottom of the data range.  By default,
					// axes scaled as if data extended 10% above and below the
					// actual range to prevent data points right on grid boundaries.
					// Don't want to do that here.
					padMin: 0,
					min:0
				}
			},
			legend: {
				show: true,
				location: 'e',
				placement: 'outside',
				labels:labels
			}
	  });
	}

});

// NOTE Widget-class name camel-case convention
Vtiger_Widget_Js('Vtiger_Minilist_Widget_Js', {}, {
	
	postLoadWidget: function() {
		app.hideModalWindow();
	}
});

Vtiger_Widget_Js('Vtiger_Tagcloud_Widget_Js',{},{

	postLoadWidget : function() {
		this._super();
		this.registerTagCloud();
		this.registerTagClickEvent();
	},
	
	registerTagCloud : function() {
		jQuery('#tagCloud').find('a').tagcloud({
			size: {
			  start: parseInt('12'), 
			  end: parseInt('30'), 
			  unit: 'px'
			}, 
			color: {
			  start: "#0266c9", 
			  end: "#759dc4"
			}
		});
	},
	
	registerChangeEventForModulesList : function() {
		jQuery('#tagSearchModulesList').on('change',function(e) {
			var modulesSelectElement = jQuery(e.currentTarget);
			if(modulesSelectElement.val() == 'all'){
				jQuery('[name="tagSearchModuleResults"]').removeClass('hide');
			} else{
				jQuery('[name="tagSearchModuleResults"]').removeClass('hide');
				var selectedOptionValue = modulesSelectElement.val();
				jQuery('[name="tagSearchModuleResults"]').filter(':not(#'+selectedOptionValue+')').addClass('hide');        
			}
		});
	},
	
	registerTagClickEvent : function(){
		var thisInstance = this;
		var container = this.getContainer();
		container.on('click','.tagName',function(e) {
			var tagElement = jQuery(e.currentTarget);
			var tagId = tagElement.data('tagid');
			var params = {
				'module' : app.getModuleName(),
				'view' : 'TagCloudSearchAjax',
				'tag_id' : tagId,
				'tag_name' : tagElement.text()
			}
			AppConnector.request(params).then(
				function(data) {
					var params = {
						'data' : data,
						'css'  : {'min-width' : '40%'}
					}
					app.showModalWindow(params);
					thisInstance.registerChangeEventForModulesList();
				}
			)			
		});
	},

	postRefreshWidget : function() {
		this._super();
		this.registerTagCloud();
	}
});

/* Notebook Widget */
Vtiger_Widget_Js('Vtiger_Notebook_Widget_Js', {
	
}, {
	
	// Override widget specific functions.
	postLoadWidget: function() {
		this.reinitNotebookView();
	},
	
	reinitNotebookView: function() {
		var self = this;
		app.showScrollBar(jQuery('.dashboard_notebookWidget_viewarea', this.container), {'height':'200px'});
		jQuery('.dashboard_notebookWidget_edit', this.container).click(function(){
			self.editNotebookContent();
		});
		jQuery('.dashboard_notebookWidget_save', this.container).click(function(){
			self.saveNotebookContent();
		});
	},
	
	editNotebookContent: function() {
		jQuery('.dashboard_notebookWidget_text', this.container).show();
		jQuery('.dashboard_notebookWidget_view', this.container).hide();
	},
	
	saveNotebookContent: function() {
		var self = this;
		var refreshContainer = this.container.find('.refresh');
		var textarea = jQuery('.dashboard_notebookWidget_textarea', this.container);
		
		var url = this.container.data('url');
		var params = url + '&content=true&mode=save&contents=' + encodeURIComponent(textarea.val());
		
		refreshContainer.progressIndicator({
			'smallLoadingImage' : true
		});
		AppConnector.request(params).then(function(data) {
			refreshContainer.progressIndicator({'mode': 'hide'});
			jQuery('.dashboardWidgetContent', self.container).html(data);
			self.reinitNotebookView();
		});
	}
});