{*<!--
/*********************************************************************************
** The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ********************************************************************************/
-->*}
{strip}
<div class="container-fluid" id="TaxCalculationsContainer">
	<div class="widget_header row-fluid">
		<div class="row-fluid"><h3>{vtranslate('LBL_TAX_CALCULATIONS', $QUALIFIED_MODULE)}</h3></div>
	</div>
	<hr>
	<div class="contents row-fluid paddingTop20">
		<div class="span6">
			{assign var=CREATE_TAX_URL value=$TAX_RECORD_MODEL->getCreateTaxUrl()}
			<div class="marginBottom10px">
				<button type="button" class="btn addTax addButton" data-url="{$CREATE_TAX_URL}" data-type="0"><i class="icon-plus icon-white"></i>&nbsp;&nbsp;<strong>{vtranslate('LBL_ADD_NEW_TAX', $QUALIFIED_MODULE)}</strong></button>
			</div>
			<table class="table table-bordered inventoryTaxTable themeTableColor">
				<thead>
					<tr class="blockHeader">
						<th colspan="3">
							{vtranslate('LBL_PRODUCT_SERVICE_TAXES', $QUALIFIED_MODULE)}
						</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td class="themeTextColor" style="border-left: none;"><strong>{vtranslate('LBL_TAX_NAME', $QUALIFIED_MODULE)}</strong></td>
						<td class="themeTextColor" style="border-left: none;"><strong>{vtranslate('LBL_TAX_VALUE', $QUALIFIED_MODULE)}</strong></td>
						<td class="themeTextColor" style="border-left: none;"><strong>{vtranslate('LBL_STATUS', $QUALIFIED_MODULE)}</strong></td>
					</tr>
					{foreach item=PRODUCT_SERVICE_TAX_MODEL from=$PRODUCT_AND_SERVICES_TAXES}
						<tr class="opacity" data-taxid="{$PRODUCT_SERVICE_TAX_MODEL->get('taxid')}" data-taxtype="{$PRODUCT_SERVICE_TAX_MODEL->getType()}">
							<td style="border-left: none;"><label class="taxLabel textOverflowEllipsis">{$PRODUCT_SERVICE_TAX_MODEL->getName()}</label></td>
							<td style="border-left: none;"><span class="taxPercentage">{$PRODUCT_SERVICE_TAX_MODEL->getTax()}%</span></td>
							<td style="border-left: none;"><input type="checkbox" class="editTaxStatus" {if !$PRODUCT_SERVICE_TAX_MODEL->isDeleted()}checked{/if} />
								<div class="pull-right actions">
									<a class="editTax cursorPointer" data-url="{$PRODUCT_SERVICE_TAX_MODEL->getEditTaxUrl()}"><i title="{vtranslate('LBL_EDIT', $MODULE)}" class="icon-pencil alignBottom"></i></a>&nbsp;
								</div>
							</td>
						</tr>
					{/foreach}
				</tbody>
			</table>
		</div>
		<div class="span6">
			<div class="marginBottom10px">
				<button type="button" class="btn addTax addButton" data-url="{$CREATE_TAX_URL}" data-type="1"><i class="icon-plus icon-white"></i>&nbsp;&nbsp;<strong>{vtranslate('LBL_ADD_NEW_TAX', $QUALIFIED_MODULE)}</strong></button>
			</div>
			<table class="table table-bordered shippingTaxTable themeTableColor">
				<thead>
					<tr class="blockHeader">
						<th colspan="3">
							{vtranslate('LBL_SHIPPING_HANDLING_TAXES', $QUALIFIED_MODULE)}
						</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td class="themeTextColor" style="border-left: none;"><strong>{vtranslate('LBL_TAX_NAME', $QUALIFIED_MODULE)}</strong></td>
						<td class="themeTextColor" style="border-left: none;"><strong>{vtranslate('LBL_TAX_VALUE', $QUALIFIED_MODULE)}</strong></td>
						<td class="themeTextColor" style="border-left: none;"><strong>{vtranslate('LBL_STATUS', $QUALIFIED_MODULE)}</strong></td>
					</tr>
					{foreach item=SHIPPING_HANDLING_TAX_MODEL from=$SHIPPING_AND_HANDLING_TAXES}
						<tr class="opacity" data-taxid="{$SHIPPING_HANDLING_TAX_MODEL->get('taxid')}" data-taxtype="{$SHIPPING_HANDLING_TAX_MODEL->getType()}">
							<td style="border-left: none;"><label class="taxLabel">{$SHIPPING_HANDLING_TAX_MODEL->getName()}</label></td>
							<td style="border-left: none;"><span class="taxPercentage">{$SHIPPING_HANDLING_TAX_MODEL->getTax()}%</span></td>
							<td style="border-left: none;"><input type="checkbox" class="editTaxStatus" {if !$SHIPPING_HANDLING_TAX_MODEL->isDeleted()}checked{/if} />
								<div class="pull-right actions">
									<a class="editTax cursorPointer" data-url="{$SHIPPING_HANDLING_TAX_MODEL->getEditTaxUrl()}"><i title="{vtranslate('LBL_EDIT', $MODULE)}" class="icon-pencil alignMiddle"></i></a>
								</div>
							</td>
						</tr>
					{/foreach}
				</tbody>
			</table>
		</div>
	</div>
</div>
{/strip}