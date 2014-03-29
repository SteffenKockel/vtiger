<?php
/*********************************************************************************
 * The contents of this file are subject to the SugarCRM Public License Version 1.1.2
 * ("License"); You may not use this file except in compliance with the
 * License. You may obtain a copy of the License at http://www.sugarcrm.com/SPL
 * Software distributed under the License is distributed on an  "AS IS"  basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License for
 * the specific language governing rights and limitations under the License.
 * The Original Code is:  SugarCRM Open Source
 * The Initial Developer of the Original Code is SugarCRM, Inc.
 * Portions created by SugarCRM are Copyright (C) SugarCRM, Inc.;
 * All Rights Reserved.
 * Contributor(s): ______________________________________.
 ********************************************************************************/
/*********************************************************************************
 * $Header: /advent/projects/wesat/vtiger_crm/sugarcrm/include/utils/EditViewUtils.php,v 1.188 2005/04/29 05:5 * 4:39 rank Exp
 * Description:  Includes generic helper functions used throughout the application.
 * Portions created by SugarCRM are Copyright (C) SugarCRM, Inc.
 * All Rights Reserved.
 * Contributor(s): ______________________________________..
 ********************************************************************************/

require_once('include/database/PearDatabase.php');
require_once('include/ComboUtil.php'); //new
require_once('include/utils/CommonUtils.php'); //new
require_once 'modules/PickList/DependentPickListUtils.php';

/** This function returns the vtiger_invoice object populated with the details from sales order object.
* Param $focus - Invoice object
* Param $so_focus - Sales order focus
* Param $soid - sales order id
* Return type is an object array
*/

function getConvertSoToInvoice($focus,$so_focus,$soid)
{
	global $log,$current_user;
	$log->debug("Entering getConvertSoToInvoice(".get_class($focus).",".get_class($so_focus).",".$soid.") method ...");
    $log->info("in getConvertSoToInvoice ".$soid);
    $xyz=array('bill_street','bill_city','bill_code','bill_pobox','bill_country','bill_state','ship_street','ship_city','ship_code','ship_pobox','ship_country','ship_state');
	for($i=0;$i<count($xyz);$i++){
		if (getFieldVisibilityPermission('SalesOrder', $current_user->id,$xyz[$i]) == '0'){
			$so_focus->column_fields[$xyz[$i]] = $so_focus->column_fields[$xyz[$i]];
		}
		else
			$so_focus->column_fields[$xyz[$i]] = '';
	}
	$focus->column_fields['salesorder_id'] = $soid;
	$focus->column_fields['subject'] = $so_focus->column_fields['subject'];
	$focus->column_fields['customerno'] = $so_focus->column_fields['customerno'];
	$focus->column_fields['duedate'] = $so_focus->column_fields['duedate'];
	$focus->column_fields['contact_id'] = $so_focus->column_fields['contact_id'];//to include contact name in Invoice
	$focus->column_fields['account_id'] = $so_focus->column_fields['account_id'];
	$focus->column_fields['exciseduty'] = $so_focus->column_fields['exciseduty'];
	$focus->column_fields['salescommission'] = $so_focus->column_fields['salescommission'];
	$focus->column_fields['purchaseorder'] = $so_focus->column_fields['purchaseorder'];
	$focus->column_fields['bill_street'] = $so_focus->column_fields['bill_street'];
	$focus->column_fields['ship_street'] = $so_focus->column_fields['ship_street'];
	$focus->column_fields['bill_city'] = $so_focus->column_fields['bill_city'];
	$focus->column_fields['ship_city'] = $so_focus->column_fields['ship_city'];
	$focus->column_fields['bill_state'] = $so_focus->column_fields['bill_state'];
	$focus->column_fields['ship_state'] = $so_focus->column_fields['ship_state'];
	$focus->column_fields['bill_code'] = $so_focus->column_fields['bill_code'];
	$focus->column_fields['ship_code'] = $so_focus->column_fields['ship_code'];
	$focus->column_fields['bill_country'] = $so_focus->column_fields['bill_country'];
	$focus->column_fields['ship_country'] = $so_focus->column_fields['ship_country'];
	$focus->column_fields['bill_pobox'] = $so_focus->column_fields['bill_pobox'];
	$focus->column_fields['ship_pobox'] = $so_focus->column_fields['ship_pobox'];
	$focus->column_fields['description'] = $so_focus->column_fields['description'];
	$focus->column_fields['terms_conditions'] = $so_focus->column_fields['terms_conditions'];
    $focus->column_fields['currency_id'] = $so_focus->column_fields['currency_id'];
    $focus->column_fields['conversion_rate'] = $so_focus->column_fields['conversion_rate'];

	$log->debug("Exiting getConvertSoToInvoice method ...");
	return $focus;

}

/** This function returns the detailed list of vtiger_products associated to a given entity or a record.
* Param $module - module name
* Param $focus - module object
* Param $seid - sales entity id
* Return type is an object array
*/


function getAssociatedProducts($module,$focus,$seid='')
{
	global $log;
	$log->debug("Entering getAssociatedProducts(".$module.",".get_class($focus).",".$seid."='') method ...");
	global $adb;
	$output = '';
	global $theme,$current_user;

	$no_of_decimal_places = getCurrencyDecimalPlaces();
	$theme_path="themes/".$theme."/";
	$image_path=$theme_path."images/";
	$product_Detail = Array();

	// DG 15 Aug 2006
	// Add "ORDER BY sequence_no" to retain add order on all inventoryproductrel items

	if($module == 'Quotes' || $module == 'PurchaseOrder' || $module == 'SalesOrder' || $module == 'Invoice')
	{
		$query="SELECT
					case when vtiger_products.productid != '' then vtiger_products.productname else vtiger_service.servicename end as productname,
 		            case when vtiger_products.productid != '' then vtiger_products.productcode else vtiger_service.service_no end as productcode,
					case when vtiger_products.productid != '' then vtiger_products.unit_price else vtiger_service.unit_price end as unit_price,
 		            case when vtiger_products.productid != '' then vtiger_products.qtyinstock else 'NA' end as qtyinstock,
 		            case when vtiger_products.productid != '' then 'Products' else 'Services' end as entitytype,
 		                        vtiger_inventoryproductrel.listprice,
 		                        vtiger_inventoryproductrel.description AS product_description,
 		                        vtiger_inventoryproductrel.*,vtiger_crmentity.deleted
 	                            FROM vtiger_inventoryproductrel
								LEFT JOIN vtiger_crmentity ON vtiger_crmentity.crmid=vtiger_inventoryproductrel.productid
 		                        LEFT JOIN vtiger_products
 		                                ON vtiger_products.productid=vtiger_inventoryproductrel.productid
 		                        LEFT JOIN vtiger_service
 		                                ON vtiger_service.serviceid=vtiger_inventoryproductrel.productid
 		                        WHERE id=?
 		                        ORDER BY sequence_no";
			$params = array($focus->id);
	}
	elseif($module == 'Potentials')
	{
		$query="SELECT
 		                        vtiger_products.productname,
 		                        vtiger_products.productcode,
 		                        vtiger_products.unit_price,
 		                        vtiger_products.qtyinstock,
 		                        vtiger_seproductsrel.*,vtiger_crmentity.deleted,
 		                        vtiger_crmentity.description AS product_description
 		                        FROM vtiger_products
 		                        INNER JOIN vtiger_crmentity
 		                                ON vtiger_crmentity.crmid=vtiger_products.productid
 		                        INNER JOIN vtiger_seproductsrel
 		                                ON vtiger_seproductsrel.productid=vtiger_products.productid
 		                        WHERE vtiger_seproductsrel.crmid=?";
			$params = array($seid);
	}
	elseif($module == 'Products')
	{
		$query="SELECT
 		                        vtiger_products.productid,
 		                        vtiger_products.productcode,
 		                        vtiger_products.productname,
 		                        vtiger_products.unit_price,
 		                        vtiger_products.qtyinstock,vtiger_crmentity.deleted,
 		                        vtiger_crmentity.description AS product_description,
 		                        'Products' AS entitytype
 		                        FROM vtiger_products
 		                        INNER JOIN vtiger_crmentity
 		                                ON vtiger_crmentity.crmid=vtiger_products.productid
 		                        WHERE vtiger_crmentity.deleted=0
 		                                AND productid=?";
			$params = array($seid);
	}
	elseif($module == 'Services')
	{
		$query="SELECT
 		                        vtiger_service.serviceid AS productid,
 		                        'NA' AS productcode,
 		                        vtiger_service.servicename AS productname,
 		                        vtiger_service.unit_price AS unit_price,
 		                        'NA' AS qtyinstock,vtiger_crmentity.deleted,
 		                        vtiger_crmentity.description AS product_description,
 		                       	'Services' AS entitytype
 								FROM vtiger_service
 		                        INNER JOIN vtiger_crmentity
 		                                ON vtiger_crmentity.crmid=vtiger_service.serviceid
 		                        WHERE vtiger_crmentity.deleted=0
 		                                AND serviceid=?";
			$params = array($seid);
	}

	$result = $adb->pquery($query, $params);
	$num_rows=$adb->num_rows($result);
	for($i=1;$i<=$num_rows;$i++)
	{
		$deleted = $adb->query_result($result,$i-1,'deleted');
		$hdnProductId = $adb->query_result($result,$i-1,'productid');
		$hdnProductcode = $adb->query_result($result,$i-1,'productcode');
		$productname=$adb->query_result($result,$i-1,'productname');
		$productdescription=$adb->query_result($result,$i-1,'product_description');
		$comment=$adb->query_result($result,$i-1,'comment');
		$qtyinstock=$adb->query_result($result,$i-1,'qtyinstock');
		$qty=$adb->query_result($result,$i-1,'quantity');
		$unitprice=$adb->query_result($result,$i-1,'unit_price');
		$listprice=$adb->query_result($result,$i-1,'listprice');
		$entitytype=$adb->query_result($result,$i-1,'entitytype');
		
		if(($deleted) || (!isset($deleted))){
			$product_Detail[$i]['productDeleted'.$i] = true;
		}elseif(!$deleted){
			$product_Detail[$i]['productDeleted'.$i] = false;
		}
		
		if (!empty($entitytype)) {
			$product_Detail[$i]['entityType'.$i]=$entitytype;
		}

		if($listprice == '')
			$listprice = $unitprice;
		if($qty =='')
			$qty = 1;

		//calculate productTotal
		$productTotal = $qty*$listprice;

		//Delete link in First column
		if($i != 1)
		{
			$product_Detail[$i]['delRow'.$i]="Del";
		}
		if(empty($focus->mode) && $seid!=''){
			$sub_prod_query = $adb->pquery("SELECT crmid as prod_id from vtiger_seproductsrel WHERE productid=? AND setype='Products'",array($seid));
		} else {
			$sub_prod_query = $adb->pquery("SELECT productid as prod_id from vtiger_inventorysubproductrel WHERE id=? AND sequence_no=?",array($focus->id,$i));
		}
		$subprodid_str='';
		$subprodname_str='';
		$subProductArray = array();
		if($adb->num_rows($sub_prod_query)>0){
			for($j=0;$j<$adb->num_rows($sub_prod_query);$j++){
				$sprod_id = $adb->query_result($sub_prod_query,$j,'prod_id');
				$sprod_name = $subProductArray[] = getProductName($sprod_id);
				$str_sep = "";
				if($j>0) $str_sep = ":";
				$subprodid_str .= $str_sep.$sprod_id;
				$subprodname_str .= $str_sep." - ".$sprod_name;
			}
		}

		$subprodname_str = str_replace(":","<br>",$subprodname_str);

		$product_Detail[$i]['subProductArray'.$i] = $subProductArray;
		$product_Detail[$i]['hdnProductId'.$i] = $hdnProductId;
		$product_Detail[$i]['productName'.$i]= from_html($productname);
		/* Added to fix the issue Product Pop-up name display*/
		if($_REQUEST['action'] == 'CreateSOPDF' || $_REQUEST['action'] == 'CreatePDF' || $_REQUEST['action'] == 'SendPDFMail')
			$product_Detail[$i]['productName'.$i]= htmlspecialchars($product_Detail[$i]['productName'.$i]);
		$product_Detail[$i]['hdnProductcode'.$i] = $hdnProductcode;
		$product_Detail[$i]['productDescription'.$i]= from_html($productdescription);
		if($module == 'Potentials' || $module == 'Products' || $module == 'Services') {
			$product_Detail[$i]['comment'.$i]= $productdescription;
		}else {
            $product_Detail[$i]['comment'.$i]= $comment;
		}

		if($module != 'PurchaseOrder' && $focus->object_name != 'Order') {
			$product_Detail[$i]['qtyInStock'.$i]=decimalFormat($qtyinstock);
		}
		$listprice = number_format($listprice, $no_of_decimal_places,'.','');
		$product_Detail[$i]['qty'.$i]=decimalFormat($qty);
		$product_Detail[$i]['listPrice'.$i]=$listprice;
		$product_Detail[$i]['unitPrice'.$i]=number_format($unitprice, $no_of_decimal_places,'.','');
		$product_Detail[$i]['productTotal'.$i]=$productTotal;
		$product_Detail[$i]['subproduct_ids'.$i]=$subprodid_str;
		$product_Detail[$i]['subprod_names'.$i]=$subprodname_str;
		$discount_percent = decimalFormat($adb->query_result($result,$i-1,'discount_percent'));
		$discount_amount = $adb->query_result($result,$i-1,'discount_amount');
		$discount_amount = decimalFormat(number_format($discount_amount, $no_of_decimal_places,'.',''));
		$discountTotal = '0.00';
		//Based on the discount percent or amount we will show the discount details

		//To avoid NaN javascript error, here we assign 0 initially to' %of price' and 'Direct Price reduction'(for Each Product)
		$product_Detail[$i]['discount_percent'.$i] = 0;
		$product_Detail[$i]['discount_amount'.$i] = 0;

		if($discount_percent != 'NULL' && $discount_percent != '')
		{
			$product_Detail[$i]['discount_type'.$i] = "percentage";
			$product_Detail[$i]['discount_percent'.$i] = $discount_percent;
			$product_Detail[$i]['checked_discount_percent'.$i] = ' checked';
			$product_Detail[$i]['style_discount_percent'.$i] = ' style="visibility:visible"';
			$product_Detail[$i]['style_discount_amount'.$i] = ' style="visibility:hidden"';
			$discountTotal = $productTotal*$discount_percent/100;
		}
		elseif($discount_amount != 'NULL' && $discount_amount != '')
		{
			$product_Detail[$i]['discount_type'.$i] = "amount";
			$product_Detail[$i]['discount_amount'.$i] = $discount_amount;
			$product_Detail[$i]['checked_discount_amount'.$i] = ' checked';
			$product_Detail[$i]['style_discount_amount'.$i] = ' style="visibility:visible"';
			$product_Detail[$i]['style_discount_percent'.$i] = ' style="visibility:hidden"';
			$discountTotal = $discount_amount;
		}
		else
		{
			$product_Detail[$i]['checked_discount_zero'.$i] = ' checked';
		}
		$totalAfterDiscount = $productTotal-$discountTotal;
		$totalAfterDiscount = number_format($totalAfterDiscount, $no_of_decimal_places,'.','');
		$discountTotal = number_format($discountTotal, $no_of_decimal_places,'.','');
		$product_Detail[$i]['discountTotal'.$i] = $discountTotal;
		$product_Detail[$i]['totalAfterDiscount'.$i] = $totalAfterDiscount;

		$taxTotal = '0.00';
		$taxTotal = number_format($taxTotal, $no_of_decimal_places,'.','');
		$product_Detail[$i]['taxTotal'.$i] = $taxTotal;

		//Calculate netprice
		$netPrice = $totalAfterDiscount+$taxTotal;
		//if condition is added to call this function when we create PO/SO/Quotes/Invoice from Product module
		if($module == 'PurchaseOrder' || $module == 'SalesOrder' || $module == 'Quotes' || $module == 'Invoice')
		{
			$taxtype = getInventoryTaxType($module,$focus->id);
			if($taxtype == 'individual')
			{
				//Add the tax with product total and assign to netprice
				$netPrice = $netPrice+$taxTotal;
			}
		}
		$product_Detail[$i]['netPrice'.$i] = $netPrice;

		//First we will get all associated taxes as array
		$tax_details = getTaxDetailsForProduct($hdnProductId,'all');
		//Now retrieve the tax values from the current query with the name
		for($tax_count=0;$tax_count<count($tax_details);$tax_count++)
		{
			$tax_name = $tax_details[$tax_count]['taxname'];
			$tax_label = $tax_details[$tax_count]['taxlabel'];
			$tax_value = '0.00';

			//condition to avoid this function call when create new PO/SO/Quotes/Invoice from Product module
			if($focus->id != '')
			{
				if($taxtype == 'individual')//if individual then show the entered tax percentage
					$tax_value = getInventoryProductTaxValue($focus->id, $hdnProductId, $tax_name);
				else//if group tax then we have to show the default value when change to individual tax
					$tax_value = $tax_details[$tax_count]['percentage'];
			}
			else//if the above function not called then assign the default associated value of the product
				$tax_value = $tax_details[$tax_count]['percentage'];

			$product_Detail[$i]['taxes'][$tax_count]['taxname'] = $tax_name;
			$product_Detail[$i]['taxes'][$tax_count]['taxlabel'] = $tax_label;
			$product_Detail[$i]['taxes'][$tax_count]['percentage'] = $tax_value;
		}

	}

	//set the taxtype
	$product_Detail[1]['final_details']['taxtype'] = $taxtype;

	//Get the Final Discount, S&H charge, Tax for S&H and Adjustment values
	//To set the Final Discount details
	$finalDiscount = '0.00';
	$product_Detail[1]['final_details']['discount_type_final'] = 'zero';

	$subTotal = ($focus->column_fields['hdnSubTotal'] != '')?$focus->column_fields['hdnSubTotal']:'0.00';
	$subTotal = number_format($subTotal, $no_of_decimal_places,'.','');

	$product_Detail[1]['final_details']['hdnSubTotal'] = $subTotal;
	$discountPercent = ($focus->column_fields['hdnDiscountPercent'] != '')?$focus->column_fields['hdnDiscountPercent']:'0.00';
	$discountAmount = ($focus->column_fields['hdnDiscountAmount'] != '')?$focus->column_fields['hdnDiscountAmount']:'0.00';
    if($discountPercent != '0'){
        $discountAmount = ($product_Detail[1]['final_details']['hdnSubTotal'] * $discountPercent / 100);
    }

	//To avoid NaN javascript error, here we assign 0 initially to' %of price' and 'Direct Price reduction'(For Final Discount)
	$discount_amount_final = '0.00';
	$discount_amount_final = number_format($discount_amount_final, $no_of_decimal_places,'.','');
    $product_Detail[1]['final_details']['discount_percentage_final'] = 0;
	$product_Detail[1]['final_details']['discount_amount_final'] = $discount_amount_final;

	if($focus->column_fields['hdnDiscountPercent'] != '0')
	{
		$finalDiscount = ($subTotal*$discountPercent/100);
		$product_Detail[1]['final_details']['discount_type_final'] = 'percentage';
		$product_Detail[1]['final_details']['discount_percentage_final'] = $discountPercent;
		$product_Detail[1]['final_details']['checked_discount_percentage_final'] = ' checked';
		$product_Detail[1]['final_details']['style_discount_percentage_final'] = ' style="visibility:visible"';
		$product_Detail[1]['final_details']['style_discount_amount_final'] = ' style="visibility:hidden"';
	}
	elseif($focus->column_fields['hdnDiscountAmount'] != '0')
	{
		$finalDiscount = $focus->column_fields['hdnDiscountAmount'];
		$product_Detail[1]['final_details']['discount_type_final'] = 'amount';
		$product_Detail[1]['final_details']['discount_amount_final'] = $discountAmount;
		$product_Detail[1]['final_details']['checked_discount_amount_final'] = ' checked';
		$product_Detail[1]['final_details']['style_discount_amount_final'] = ' style="visibility:visible"';
		$product_Detail[1]['final_details']['style_discount_percentage_final'] = ' style="visibility:hidden"';
	}
	$finalDiscount = number_format($finalDiscount, $no_of_decimal_places,'.','');
	$product_Detail[1]['final_details']['discountTotal_final'] = $finalDiscount;

	//To set the Final Tax values
	//we will get all taxes. if individual then show the product related taxes only else show all taxes
	//suppose user want to change individual to group or vice versa in edit time the we have to show all taxes. so that here we will store all the taxes and based on need we will show the corresponding taxes

	$taxtotal = '0.00';
	//First we should get all available taxes and then retrieve the corresponding tax values
	$tax_details = getAllTaxes('available','','edit',$focus->id);

	for($tax_count=0;$tax_count<count($tax_details);$tax_count++)
	{
		$tax_name = $tax_details[$tax_count]['taxname'];
		$tax_label = $tax_details[$tax_count]['taxlabel'];

		//if taxtype is individual and want to change to group during edit time then we have to show the all available taxes and their default values
		//Also taxtype is group and want to change to individual during edit time then we have to provide the asspciated taxes and their default tax values for individual products
		if($taxtype == 'group')
			$tax_percent = $adb->query_result($result,0,$tax_name);
		else
			$tax_percent = $tax_details[$tax_count]['percentage'];//$adb->query_result($result,0,$tax_name);

		if($tax_percent == '' || $tax_percent == 'NULL')
			$tax_percent = '0.00';
		$taxamount = ($subTotal-$finalDiscount)*$tax_percent/100;
		$taxamount = number_format($taxamount, $no_of_decimal_places,'.','');
		$taxtotal = $taxtotal + $taxamount;
		$product_Detail[1]['final_details']['taxes'][$tax_count]['taxname'] = $tax_name;
		$product_Detail[1]['final_details']['taxes'][$tax_count]['taxlabel'] = $tax_label;
		$product_Detail[1]['final_details']['taxes'][$tax_count]['percentage'] = $tax_percent;
		$product_Detail[1]['final_details']['taxes'][$tax_count]['amount'] = $taxamount;
	}
	$product_Detail[1]['final_details']['tax_totalamount'] = $taxtotal;

	//To set the Shipping & Handling charge
	$shCharge = ($focus->column_fields['hdnS_H_Amount'] != '')?$focus->column_fields['hdnS_H_Amount']:'0.00';
	$shCharge = number_format($shCharge, $no_of_decimal_places,'.','');
	$product_Detail[1]['final_details']['shipping_handling_charge'] = $shCharge;

	//To set the Shipping & Handling tax values
	//calculate S&H tax
	$shtaxtotal = '0.00';
	//First we should get all available taxes and then retrieve the corresponding tax values
	$shtax_details = getAllTaxes('available','sh','edit',$focus->id);

	//if taxtype is group then the tax should be same for all products in vtiger_inventoryproductrel table
	for($shtax_count=0;$shtax_count<count($shtax_details);$shtax_count++)
	{
		$shtax_name = $shtax_details[$shtax_count]['taxname'];
		$shtax_label = $shtax_details[$shtax_count]['taxlabel'];
		$shtax_percent = '0.00';
		//if condition is added to call this function when we create PO/SO/Quotes/Invoice from Product module
		if($module == 'PurchaseOrder' || $module == 'SalesOrder' || $module == 'Quotes' || $module == 'Invoice')
		{
			$shtax_percent = getInventorySHTaxPercent($focus->id,$shtax_name);
		}
		$shtaxamount = $shCharge*$shtax_percent/100;
		$shtaxtotal = $shtaxtotal + $shtaxamount;
		$product_Detail[1]['final_details']['sh_taxes'][$shtax_count]['taxname'] = $shtax_name;
		$product_Detail[1]['final_details']['sh_taxes'][$shtax_count]['taxlabel'] = $shtax_label;
		$product_Detail[1]['final_details']['sh_taxes'][$shtax_count]['percentage'] = $shtax_percent;
		$product_Detail[1]['final_details']['sh_taxes'][$shtax_count]['amount'] = $shtaxamount;
	}
	$shtaxtotal = number_format($shtaxtotal, $no_of_decimal_places,'.','');
	$product_Detail[1]['final_details']['shtax_totalamount'] = $shtaxtotal;

	//To set the Adjustment value
	$adjustment = ($focus->column_fields['txtAdjustment'] != '')?$focus->column_fields['txtAdjustment']:'0.00';
	$adjustment = number_format($adjustment, $no_of_decimal_places,'.','');
	$product_Detail[1]['final_details']['adjustment'] = $adjustment;

	//To set the grand total
	$grandTotal = ($focus->column_fields['hdnGrandTotal'] != '')?$focus->column_fields['hdnGrandTotal']:'0.00';
	$grandTotal = number_format($grandTotal, $no_of_decimal_places,'.','');
	$product_Detail[1]['final_details']['grandTotal'] = $grandTotal;

	$log->debug("Exiting getAssociatedProducts method ...");

	return $product_Detail;

}

/** This function returns the data type of the vtiger_fields, with vtiger_field label, which is used for javascript validation.
* Param $validationData - array of vtiger_fieldnames with datatype
* Return type array
*/

function split_validationdataArray($validationData)
{
	global $log;
	$log->debug("Entering split_validationdataArray(".$validationData.") method ...");
	$fieldName = '';
	$fieldLabel = '';
	$fldDataType = '';
	$rows = count($validationData);
	foreach($validationData as $fldName => $fldLabel_array)
	{
		if($fieldName == '')
		{
			$fieldName="'".$fldName."'";
		}
		else
		{
			$fieldName .= ",'".$fldName ."'";
		}
		foreach($fldLabel_array as $fldLabel => $datatype)
		{
			if($fieldLabel == '')
			{
				$fieldLabel = "'".addslashes($fldLabel)."'";
			}
			else
			{
				$fieldLabel .= ",'".addslashes($fldLabel)."'";
			}
			if($fldDataType == '')
			{
				$fldDataType = "'".$datatype ."'";
			}
			else
			{
				$fldDataType .= ",'".$datatype ."'";
			}
		}
	}
	$data['fieldname'] = $fieldName;
	$data['fieldlabel'] = $fieldLabel;
	$data['datatype'] = $fldDataType;
	$log->debug("Exiting split_validationdataArray method ...");
	return $data;
}


?>