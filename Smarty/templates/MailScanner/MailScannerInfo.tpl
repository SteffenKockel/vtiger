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

<script language="JAVASCRIPT" type="text/javascript">
{literal}
function performScanNow(app_key, scannername) {
	$('status').style.display = 'inline';
	new Ajax.Request(
		'index.php',
		{queue: {position: 'end', scope: 'command'},
			method: 'post',
			postBody: 'module=Settings&action=SettingsAjax&file=MailScanner' + 
					  '&mode=scannow&service=MailScanner&app_key=' + encodeURIComponent(app_key)+ '&scannername=' + encodeURIComponent(scannername),
			onComplete: function(response) {
				$('status').style.display = 'none';
			}
		}
	);
}
{/literal}
</script>

<br>
<table align="center" border="0" cellpadding="0" cellspacing="0" width="98%">
<tbody>
<tr>
	<td valign="top"><img src="{'showPanelTopLeft.gif'|@vtiger_imageurl:$THEME}"></td>
    <td class="showPanelBg" style="padding: 10px;" valign="top" width="100%">

	<form action="index.php" method="post" id="form" onsubmit="VtigerJS_DialogBox.block();">
		<input type='hidden' name='module' value='Settings'>
		<input type='hidden' name='action' value='MailScanner'>
		<input type='hidden' name='mode' value='edit'>
		<input type='hidden' name='scannername' value='{$SCANNERINFO.scannername}'>
		<input type='hidden' name='return_action' value='MailScanner'>
		<input type='hidden' name='return_module' value='Settings'>
		<input type='hidden' name='parenttab' value='Settings'>

        <br>

		<div align=center>
			{include file='SetMenu.tpl'}
				<!-- DISPLAY -->
				<table border=0 cellspacing=0 cellpadding=5 width=100% class="settingsSelUITopLine">
				<tr>
					<td width=50 rowspan=2 valign=top><img src="{'mailScanner.gif'|@vtiger_imageurl:$THEME}" alt="{$MOD.LBL_MAIL_SCANNER}" width="48" height="48" border=0 title="{$MOD.LBL_MAIL_SCANNER}"></td>
					<td class=heading2 valign=bottom><b><a href="index.php?module=Settings&action=index&parenttab=Settings">{$MOD.LBL_SETTINGS}</a> > {$MOD.LBL_MAIL_SCANNER}</b></td>
				</tr>
				<tr>
					<td valign=top class="small">{$MOD.LBL_MAIL_SCANNER_DESCRIPTION}</td>
				</tr>
				</table>
				
				<br>
				<table border=0 cellspacing=0 cellpadding=10 width=100% >
				<tr>
				<td>

				<table border=0 cellspacing=0 cellpadding=5 width=100% class="tableHeading">
				<tr>
				<td class="big" width="70%"><strong>{$MOD.LBL_MAILBOX} {$MOD.LBL_INFORMATION}</strong></td>
				<td width="30%" nowrap align="right">
					{if $SCANNERINFO.isvalid eq true}

					{if $SCANNERINFO.rules neq false}
					<input type="button" class="crmbutton small delete" value="{$MOD.LBL_SCAN_NOW}" 
						onclick="performScanNow('{$APP_KEY}','{$SCANNERINFO.scannername}')" />
					{/if}

					<input type="submit" class="crmbutton small cancel" onclick="this.form.mode.value='folder'" value="{$MOD.LBL_SELECT} {$MOD.LBL_FOLDERS}" />
					<input type="submit" class="crmbutton small create" onclick="this.form.mode.value='rule'" value="{$MOD.LBL_SETUP} {$MOD.LBL_RULE}" />
					{/if}
					<input type="submit" class="crmbutton small edit" value="{$APP.LBL_EDIT}" />
				</td>
				</tr>
				</table>
				
				<table border=0 cellspacing=0 cellpadding=0 width=100% class="listRow">
				<tr>
	         	    <td class="small" valign=top ><table width="100%"  border="0" cellspacing="0" cellpadding="5">
						<tr>
                            <td width="20%" nowrap class="small cellLabel"><strong>{$MOD.LBL_SCANNER} {$MOD.LBL_NAME}</strong></td>
                            <td width="80%" class="small cellText">{$SCANNERINFO.scannername}</td>
                        </tr>
                        <tr>
                            <td width="20%" nowrap class="small cellLabel"><strong>{$MOD.LBL_SERVER} {$MOD.LBL_NAME}</strong></td>
                            <td width="80%" class="small cellText">{$SCANNERINFO.server}</td>
                        </tr>
                        <tr>
							<td width="20%" nowrap class="small cellLabel"><strong>{$MOD.LBL_PROTOCOL}</strong></td>
			                <td width="80%" class="small cellText">{$SCANNERINFO.protocol}</td>
						</tr>
						<tr>
			                <td width="20%" nowrap class="small cellLabel"><strong>{$MOD.LBL_USERNAME}</strong></td>
               				<td width="80%" class="small cellText">{$SCANNERINFO.username}</td>
                        </tr>
						<tr>
			                <td width="20%" nowrap class="small cellLabel"><strong>{$MOD.LBL_SSL} {$MOD.LBL_TYPE}</strong></td>
               				<td width="80%" class="small cellText">{$SCANNERINFO.ssltype}</td>
                        </tr>
						<tr>
			                <td width="20%" nowrap class="small cellLabel"><strong>{$MOD.LBL_SSL} {$MOD.LBL_METHOD}</strong></td>
               				<td width="80%" class="small cellText">{$SCANNERINFO.sslmethod}</td>
                        </tr>
						<tr>
			                <td width="20%" nowrap class="small cellLabel"><strong>{$MOD.LBL_CONNECT} {$MOD.LBL_URL_CAPS}</strong></td>
               				<td width="80%" class="small cellText">{$SCANNERINFO.connecturl}</td>
                        </tr>
						<tr>
			                <td width="20%" nowrap class="small cellLabel"><strong>{$MOD.LBL_STATUS}</strong></td>
               				<td width="80%" class="small cellText">
								{if $SCANNERINFO.isvalid eq true}<font color=green><b>{$MOD.LBL_ENABLED}</b></font>
								{elseif $SCANNERINFO.isvalid eq false}<font color=red><b>{$MOD.LBL_DISABLED}</b></font>{/if}
							</td>
                        </tr>
				    </td>
            	</tr>
				</table>	
				{if $SCANNERINFO.isvalid}
					<table border=0 cellspacing=0 cellpadding=5 width=100% class="tableHeading">
					<tr>
					<td class="big" width="70%"><strong>{$MOD.LBL_SCANNING} {$MOD.LBL_INFORMATION}</strong></td>
					<td width="30%" nowrap align="right">&nbsp;</td>
					</tr>
					</table>

					<table border=0 cellspacing=0 cellpadding=0 width=100% class="listRow">
					<tr>
	        	 	    <td class="small" valign=top ><table width="100%"  border="0" cellspacing="0" cellpadding="5">
							<tr>
                    	        <td width="20%" nowrap class="small cellLabel"><strong>{$MOD.LBL_LOOKFOR}</strong></td>
                        	    <td width="80%" class="small cellText">
									{if $SCANNERINFO.searchfor eq 'ALL'}{$MOD.LBL_ALL}
									{elseif $SCANNERINFO.searchfor eq 'UNSEEN'}{$MOD.LBL_UNREAD}{/if}
									{$MOD.LBL_MESSAGES_FROM_LASTSCAN}
									{if $SCANNERINFO.requireRescan} [{$MOD.LBL_INCLUDE} {$MOD.LBL_SKIPPED}] {/if}
								</td>
                        	</tr>
							<tr>
                           		<td width="20%" nowrap class="small cellLabel"><strong>{$MOD.LBL_AFTER_SCAN}</strong></td>
                           		<td width="80%" class="small cellText">
									{if $SCANNERINFO.markas eq 'SEEN'}{$MOD.LBL_MARK_MESSAGE_AS} {$MOD.LBL_READ}{/if}
								</td>
    	                    </tr>
						</td>
					</tr>
					</table>
				{/if}
				</td>
				</tr>
				</table>
			
			</td>
			</tr>
			</table>
		</td>
	</tr>
	</table>
		
	</div>

</td>
        <td valign="top"><img src="{'showPanelTopRight.gif'|@vtiger_imageurl:$THEME}"></td>
   </tr>
</tbody>
</form>
</table>

</tr>
</table>

</tr>
</table>
