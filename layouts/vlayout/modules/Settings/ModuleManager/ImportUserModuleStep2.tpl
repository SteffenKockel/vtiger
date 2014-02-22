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
{strip}
<div class="container-fluid" id="importModules">
	<div class="widget_header row-fluid">
		<h3>{vtranslate('LBL_IMPORT_MODULE_FROM_FILE', $QUALIFIED_MODULE)}</h3>
	</div><hr>
	<div class="contents">
		<div class="row-fluid">
			<div id="vtlib_modulemanager_import_div">
				<form method="POST" action="index.php">
					<input type="hidden" name="module" value="ModuleManager">
					<input type="hidden" name="parent" value="Settings">
					{if $MODULEIMPORT_FAILED neq ''}
						<div class="span10">
							<b>{vtranslate('LBL_FAILED', $QUALIFIED_MODULE)}</b>
						</div>
						<div class="span10">
							{if $VERSION_NOT_SUPPORTED eq 'true'}
								<font color=red><b>{vtranslate('LBL_VERSION_NOT_SUPPORTED', $QUALIFIED_MODULE)}</b></font>
							{else}	
								{if $MODULEIMPORT_FILE_INVALID eq "true"}
									<font color=red><b>{vtranslate('LBL_INVALID_FILE', $QUALIFIED_MODULE)}</b></font> {vtranslate('LBL_INVALID_IMPORT_TRY_AGAIN', $QUALIFIED_MODULE)}
								{else}
									<font color=red>{vtranslate('LBL_UNABLE_TO_UPLOAD', $QUALIFIED_MODULE)}</font> {vtranslate('LBL_UNABLE_TO_UPLOAD2', $QUALIFIED_MODULE)}
								{/if}
							{/if}
						</div>
						<input type="hidden" name="view" value="List">
						<button  class="btn btn-success" type="submit"><strong>{vtranslate('LBL_FINISH', $QUALIFIED_MODULE)}</strong></button>
					{else}
						<table class="table table-bordered">
							<thead>
								<tr class="blockHeader">
									<th colspan="2"><strong>{vtranslate('LBL_VERIFY_IMPORT_DETAILS',$QUALIFIED_MODULE)}</strong></th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td><b>{vtranslate('LBL_MODULE_NAME', $QUALIFIED_MODULE)}</b></td>
									<td>
										{vtranslate($MODULEIMPORT_NAME, $QUALIFIED_MODULE)}
										{if $MODULEIMPORT_EXISTS eq 'true'} <font color=red><b>{vtranslate('LBL_EXISTS', $QUALIFIED_MODULE)}</b></font> {/if}
									</td>
								</tr>
								<tr>
									<td><b>{vtranslate('LBL_REQ_VTIGER_VERSION', $QUALIFIED_MODULE)}</b></td>
									<td>{$MODULEIMPORT_DEP_VTVERSION}</td>
								</tr>
								{assign var="need_license_agreement" value="false"}
								{if $MODULEIMPORT_LICENSE}
									{assign var="need_license_agreement" value="true"}
								<tr>
									<td width=20%><b>{vtranslate('LBL_LICENSE', $QUALIFIED_MODULE)}</b></td>
									<td>
										<textarea readonly class='row-fluid'>{$MODULEIMPORT_LICENSE}</textarea><br>
											{if $MODULEIMPORT_EXISTS neq 'true'}
												{literal}<input type="checkbox"  onclick="if(this.form.saveButton){if(this.checked){this.form.saveButton.disabled=false;}else{this.form.saveButton.disabled=true;}}">{/literal}  {vtranslate('LBL_LICENSE_ACCEPT_AGREEMENT', $QUALIFIED_MODULE)}
											{/if}
										</td>
								</tr>
								{/if}
							</tbody>
						</table>
						<div class="modal-footer">
							{if $MODULEIMPORT_EXISTS eq 'true' || $MODULEIMPORT_DIR_EXISTS eq 'true'}
								<input type="hidden" name="view" value="List">
								<button class="btn btn-success" class="crmbutton small delete" 
									   onclick="this.form.mode.value='';">
									<strong>{vtranslate('LBL_CANCEL', $MODULE)}</strong>
								</button>
								{if $MODULEIMPORT_EXISTS eq 'true'}
									<input type="hidden" name="view" value="ModuleImport">
									<input type="hidden" name="module_import_file" value="{$MODULEIMPORT_FILE}">
									<input type="hidden" name="module_import_type" value="{$MODULEIMPORT_TYPE}">
									<input type="hidden" name="module_import_name" value="{$MODULEIMPORT_NAME}">
									<input type="hidden" name="mode" value="importUserModuleStep3">

									<input type="checkbox" class="pull-right" onclick="this.form.mode.value='updateUserModuleStep3';this.form.submit();" >
									<span class="pull-right">I would like to update now.&nbsp;</span>
								{/if}
							
							{else}
								<input type="hidden" name="view" value="ModuleImport">
								<input type="hidden" name="module_import_file" value="{$MODULEIMPORT_FILE}">
								<input type="hidden" name="module_import_type" value="{$MODULEIMPORT_TYPE}">
								<input type="hidden" name="module_import_name" value="{$MODULEIMPORT_NAME}">
								<input type="hidden" name="mode" value="importUserModuleStep3">
								<span class="span6 pull-right">
									{vtranslate('LBL_PROCEED_WITH_IMPORT', $QUALIFIED_MODULE)}
									<div class=" pull-right cancelLinkContainer">
										<a class="cancelLink" type="reset" data-dismiss="modal" onclick="javascript:window.history.back();">{vtranslate('LBL_NO', $MODULE)}</a>
									</div>
									<button  class="btn btn-success" type="submit" name="saveButton"
									{if $need_license_agreement eq 'true'} disabled {/if}><strong>{vtranslate('LBL_YES')}</strong></button>

								</span>
							{/if}
						</div>
					{/if}
				</form>
			</div>
		</div>
	</div>
</div>
{/strip}