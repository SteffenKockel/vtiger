{*<!--
/*********************************************************************************
** The contents of this file are subject to the vtiger CRM Public License Version 1.0
* ("License"); You may not use this file except in compliance with the License
* The Original Code is:  vtiger CRM Open Source
* The Initial Developer of the Original Code is vtiger.
* Portions created by vtiger are Copyright (C) vtiger.
* All Rights Reserved.
*
********************************************************************************/
-->*}
{assign var=FINAL_DETAILS value=$RELATED_PRODUCTS.1.final_details}
<table class="table table-bordered mergeTables">
    <thead>
    <th colspan="2" class="detailViewBlockHeader">
	{vtranslate('LBL_ITEM_DETAILS', $MODULE_NAME)}
    </th>
    <th colspan="1" class="detailViewBlockHeader">
	{assign var=CURRENCY_INFO value=$RECORD->getCurrencyInfo()}
	{vtranslate('LBL_CURRENCY', $MODULE_NAME)} : {vtranslate($CURRENCY_INFO['currency_name'],$MODULE_NAME)}({$CURRENCY_INFO['currency_symbol']})
    </th>
    <th colspan="2" class="detailViewBlockHeader">
	{vtranslate('LBL_TAX_MODE', $MODULE_NAME)} : {vtranslate($FINAL_DETAILS.taxtype, $MODULE_NAME)}
    </th>
	</thead>
	<tbody>
    <tr>
	<td>
	    <span class="redColor">*</span><b>{vtranslate('LBL_ITEM_NAME',$MODULE_NAME)}</b>
	</td>
        <td>
	    <b>{vtranslate('LBL_QTY',$MODULE_NAME)}</b>
	</td>
        <td>
	    <b>{vtranslate('LBL_LIST_PRICE',$MODULE_NAME)}</b>
	</td>
        <td>
	    <b>{vtranslate('LBL_TOTAL',$MODULE_NAME)}</b>
	</td>
        <td>
	    <b class="pull-right">{vtranslate('LBL_NET_PRICE',$MODULE_NAME)}</b>
	</td>
    </tr>
    {foreach key=INDEX item=LINE_ITEM_DETAIL from=$RELATED_PRODUCTS}
	<tr>
	    <td>
		<div class="row-fluid">
		    {$LINE_ITEM_DETAIL["productName$INDEX"]}
		</div>
		{if $LINE_ITEM_DETAIL["productDeleted$INDEX"]}
			<div class="row-fluid redColor deletedItem">
				{if empty($LINE_ITEM_DETAIL["productName$INDEX"])}
					{vtranslate('LBL_THIS_LINE_ITEM_IS_DELETED_FROM_THE_SYSTEM_PLEASE_REMOVE_THIS_LINE_ITEM',$MODULE)}
				{else}
					{vtranslate('LBL_THIS',$MODULE)} {$LINE_ITEM_DETAIL["entityType$INDEX"]} {vtranslate('LBL_IS_DELETED_FROM_THE_SYSTEM_PLEASE_REMOVE_OR_REPLACE_THIS_ITEM',$MODULE)}
				{/if}
			</div>
		{/if}
		{if !empty($LINE_ITEM_DETAIL["subProductArray$INDEX"])}
		    <div>
			{foreach item=SUB_PRODUCT_NAME from=$LINE_ITEM_DETAIL["subProductArray$INDEX"]}
			    <div>
				{if !empty($SUB_PRODUCT_NAME)}
					- &nbsp; <em>{$SUB_PRODUCT_NAME}</em>
				{/if}
			    </div>
			{/foreach}
		    </div>
		{/if}
		{if !empty($LINE_ITEM_DETAIL["productName$INDEX"])}
			<div>
				{$LINE_ITEM_DETAIL["comment$INDEX"]|nl2br}
			</div>
		{/if}
	    </td>
	    <td>
		{$LINE_ITEM_DETAIL["qty$INDEX"]}
	    </td>
	    <td>
		<div>
		    {$LINE_ITEM_DETAIL["listPrice$INDEX"]}
		</div>
		<div>
		    {assign var=DISCOUNT_INFO value="{if $LINE_ITEM_DETAIL["discount_type$INDEX"] == 'amount'} {vtranslate('LBL_DIRECT_AMOUNT_DISCOUNT',$MODULE_NAME)} = {$LINE_ITEM_DETAIL["discountTotal$INDEX"]}
		    {elseif $LINE_ITEM_DETAIL["discount_type$INDEX"] == 'percentage'} {$LINE_ITEM_DETAIL["discount_percent$INDEX"]} % {vtranslate('LBL_OF',$MODULE_NAME)} {$LINE_ITEM_DETAIL["productTotal$INDEX"]} = {$LINE_ITEM_DETAIL["discountTotal$INDEX"]}
			{/if}"}
			(-)&nbsp; <b><a href="javascript:void(0)" class="individualDiscount inventoryLineItemDetails" data-info='{$DISCOUNT_INFO}'>{vtranslate('LBL_DISCOUNT',$MODULE_NAME)}</a> : </b>
		    </div>
		    <div>
			<b>{vtranslate('LBL_TOTAL_AFTER_DISCOUNT',$MODULE_NAME)} :</b>
		    </div>
		    {if $FINAL_DETAILS.taxtype neq 'group'}
			<div class="individualTaxContainer">
			    {assign var=INDIVIDUAL_TAX_INFO value="{vtranslate('LBL_TOTAL_AFTER_DISCOUNT',$MODULE_NAME)} = {$LINE_ITEM_DETAIL["totalAfterDiscount$INDEX"]}\r\n{foreach item=tax_details from=$LINE_ITEM_DETAIL["taxes"]}{$tax_details["taxlabel"]} : {$tax_details["percentage"]} % = {$tax_details["amount"]}\r\n{/foreach}\r\n{vtranslate('LBL_TOTAL_TAX_AMOUNT',$MODULE_NAME)} = {$LINE_ITEM_DETAIL["taxTotal$INDEX"]}"}
			    (+)&nbsp;<b><a href="javascript:void(0)" class="individualTax inventoryLineItemDetails" data-info='{$INDIVIDUAL_TAX_INFO}'>{vtranslate('LBL_TAX',$MODULE_NAME)} </a> : </b>
			</div>
		    {/if}
		</td>
		<td>
		    <div>
			{$LINE_ITEM_DETAIL["productTotal$INDEX"]}
		    </div>
		    <div>
			{$LINE_ITEM_DETAIL["discountTotal$INDEX"]}
		    </div>
		    <div>
			{$LINE_ITEM_DETAIL["totalAfterDiscount$INDEX"]}
		    </div>
		    {if $FINAL_DETAILS.taxtype neq 'group'}
			<div>
			    {$LINE_ITEM_DETAIL["taxTotal$INDEX"]}
			</div>
		    {/if}
		</td>
		<td>
		    <span class="pull-right">
			{$LINE_ITEM_DETAIL["netPrice$INDEX"]}
		    </span>
		</td>
	    </tr>
	    {/foreach}
	    </tbody>
	</table>

	<table class="table table-bordered">
	    <tr>
		<td width="83%">
		    <div class="pull-right">
			<b>{vtranslate('LBL_ITEMS_TOTAL',$MODULE_NAME)}</b>
		    </div>
		</td>
		<td>
		    <span class="pull-right">
			<b>{$FINAL_DETAILS["hdnSubTotal"]}</b>
		    </span>
		</td>
	    </tr>
	    <tr>
		<td width="83%">
		    <span class="pull-right">
			{assign var=FINAL_DISCOUNT_INFO value="{vtranslate('LBL_FINAL_DISCOUNT_AMOUNT',$MODULE_NAME)} = {if $FINAL_DETAILS['discount_type_final'] == 'percentage'} {$FINAL_DETAILS['discount_percentage_final']} % {vtranslate('LBL_OF',$MODULE_NAME)} {$FINAL_DETAILS['hdnSubTotal']} = {/if}{$FINAL_DETAILS['discountTotal_final']}"}
			(-)&nbsp;<b><a class="inventoryLineItemDetails" href="javascript:void(0)" id="finalDiscount" data-info="{$FINAL_DISCOUNT_INFO}">{vtranslate('LBL_DISCOUNT',$MODULE_NAME)}</a></b>
		    </span>
		</td>
		<td>
		    <span class="pull-right">
			{$FINAL_DETAILS['discountTotal_final']}
		    </span>

		</td>
	    </tr>
		<tr>
		<td width="83%">
		    <span class="pull-right">
			(+)&nbsp;<b>{vtranslate('LBL_SHIPPING_AND_HANDLING_CHARGES',$MODULE_NAME)} </b>
		    </span>
		</td>
		<td>
		    <span class="pull-right">
			{$FINAL_DETAILS["shipping_handling_charge"]}
		    </span>
		</td>
	    </tr>
		<tr>
		<td width="83%">
		    <span class="pull-right">
			<b>{vtranslate('LBL_PRE_TAX_TOTAL', $MODULE_NAME)} </b>
		    </span>
		</td>
		<td>
		    <span class="pull-right">
			{$FINAL_DETAILS["preTaxTotal"]}
		    </span>
		</td>
	    </tr>
	    <tr>
	    {if $FINAL_DETAILS.taxtype eq 'group'}
		<tr>
		    <td width="83%">
			<span class="pull-right">
			    {assign var=GROUP_TAX_INFO value="{vtranslate('LBL_TOTAL_AFTER_DISCOUNT',$MODULE_NAME)} = {$FINAL_DETAILS["totalAfterDiscount"]}\r\n{foreach item=tax_details from=$FINAL_DETAILS["taxes"]}{$tax_details["taxlabel"]} : {$tax_details["percentage"]} % = {$tax_details["amount"]}\r\n{/foreach}\r\n{vtranslate('LBL_TOTAL_TAX_AMOUNT',$MODULE_NAME)} = {$FINAL_DETAILS['tax_totalamount']}"}
			    (+)&nbsp;<b><a class="inventoryLineItemDetails" href="javascript:void(0)" id="finalTax"
					   data-info='{$GROUP_TAX_INFO}'>{vtranslate('LBL_TAX',$MODULE_NAME)}</a></b>
			</span>
		    </td>
		    <td>
			<span class="pull-right">
			    {$FINAL_DETAILS['tax_totalamount']}
			</span>
		    </td>
		</tr>
	    {/if}
		<td width="83%">
		    <span class="pull-right">
			{assign var=SHIPPING_HANDLING_TAX_INFO value="{vtranslate('LBL_SHIPPING_AND_HANDLING_CHARGES',$MODULE_NAME)} = {$FINAL_DETAILS["shipping_handling_charge"]}\r\n{foreach item=tax_details from=$FINAL_DETAILS["sh_taxes"]}{$tax_details["taxlabel"]} : {$tax_details["percentage"]} % = {$tax_details["amount"]}\r\n{/foreach}\r\n{vtranslate('LBL_TOTAL_TAX_AMOUNT',$MODULE_NAME)} = {$FINAL_DETAILS['shtax_totalamount']}"}
			(+)&nbsp;<b><a class="inventoryLineItemDetails" href="javascript:void(0)" id="shippingHandlingTax"
				       data-info='{$SHIPPING_HANDLING_TAX_INFO}'>
				{vtranslate('LBL_TAX_FOR_SHIPPING_AND_HANDLING',$MODULE_NAME)} </a></b>
		    </span>
		</td>
		<td>
		    <span class="pull-right">
			{$FINAL_DETAILS["shtax_totalamount"]}
		    </span>
		</td>
	    </tr>
	    <tr>
		<td width="83%">
		    <span class="pull-right">
			<b>{vtranslate('LBL_ADJUSTMENT',$MODULE_NAME)}</b>
		    </span>
		</td>
		<td>
		    <span class="pull-right">
			{$FINAL_DETAILS["adjustment"]}
		    </span>
		</td>
	    </tr>
	    <tr>
		<td width="83%">
		    <span class="pull-right">
			<b>{vtranslate('LBL_GRAND_TOTAL',$MODULE_NAME)}</b>
		    </span>
		</td>
		<td>
		    <span class="pull-right">
			{$FINAL_DETAILS["grandTotal"]}
		    </span>
		</td>
	    </tr>
	</table>