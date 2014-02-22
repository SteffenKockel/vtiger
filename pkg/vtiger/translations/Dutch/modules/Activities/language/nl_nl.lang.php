<?php

/*******************************************************************************
 * The contents of this file are subject to the following licences:
 * - SugarCRM Public License Version 1.1.2 http://www.sugarcrm.com/SPL
 * - vtiger CRM Public License Version 1.0 
 * You may not use this file except in compliance with the License
 * Software distributed under the License is distributed on an  "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License for
 * the specific language governing rights and limitations under the License.
 * The Original Code is: SugarCRM Open Source
 * The Initial Developer of the Original Code is SugarCRM, Inc.
 * Portions created by SugarCRM are Copyright (C) SugarCRM, Inc.;
 * Portions created by vtiger are Copyright (C) vtiger.
 * Portions created by Vicus are Copyright (C) Vicus.
 * All Rights Reserved.
 * Feel free to use / redistribute these languagefiles under the VPL 1.0.
 * This translations is based on earlier work of: 
 * - IT-Online.nl <www.it-online.nl>
 * - Weltevree.org <www.Weltevree.org>
 ********************************************************************************/

/*******************************************************************************
 * Vicus eBusiness Solutions Version Control
 * @package 	NL-Dutch
 * Description	Dutch language pack for vtiger CRM version 5.3.x
 * @author	$Author: luuk $
 * @version 	$Revision: 1.3 $ $Date: 2011/11/14 17:07:26 $
 * @source	$Source: /var/lib/cvs/vtiger530/Dutch/modules/Activities/language/nl_nl.lang.php,v $
 * @copyright	Copyright (c)2005-2011 Vicus eBusiness Solutions bv <info@vicus.nl>
 * @license	vtiger CRM Public License Version 1.0 (by definition)
 ********************************************************************************/
 
$mod_strings = Array(
'LBL_MODULE_NAME'=>'Activiteiten',
'LBL_MODULE_TITLE'=>'Activiteiten: Home',
'LBL_SEARCH_FORM_TITLE'=>'Zoek activiteit ',
'LBL_LIST_FORM_TITLE'=>'Activiteitenlijst',
'LBL_NEW_FORM_TITLE'=>'Nieuwe activiteit',
'LBL_TASK_INFORMATION'=>'Taak informatie',
'LBL_EVENT_INFORMATION'=>'Activiteit informatie',

'LBL_NAME'=>'Onderwerp:',
'LBL_CONTACT_NAME'=>'Contactnaam:',
'LBL_OPEN_ACTIVITIES'=>'Open activiteiten',
'LBL_ACTIVITY'=>'Activiteit:',
'LBL_HISTORY'=>'Geschiedenis',
'LBL_UPCOMING'=>"Mijn nieuwe en wachtende activiteiten",
'LBL_TODAY'=>'Doorlopend ',

'LBL_NEW_TASK_BUTTON_TITLE'=>'Nieuwe taak [Alt+N]',
'LBL_NEW_TASK_BUTTON_KEY'=>'N',
'LBL_NEW_TASK_BUTTON_LABEL'=>'Nieuwe taak',
'LBL_SCHEDULE_MEETING_BUTTON_TITLE'=>'Vergadering plannen [Alt+M]',
'LBL_SCHEDULE_MEETING_BUTTON_KEY'=>'M',
'LBL_SCHEDULE_MEETING_BUTTON_LABEL'=>'Vergadering plannen',
'LBL_SCHEDULE_CALL_BUTTON_TITLE'=>'Telefoongesprek plannen [Alt+C]',
'LBL_SCHEDULE_CALL_BUTTON_KEY'=>'C',
'LBL_SCHEDULE_CALL_BUTTON_LABEL'=>'Telefoongesprek plannen',
'LBL_NEW_NOTE_BUTTON_TITLE'=>'Nieuwe notitie [Alt+T]',
'LBL_NEW_ATTACH_BUTTON_TITLE'=>'Bijlage toevoegen [Alt+F]',
'LBL_NEW_NOTE_BUTTON_KEY'=>'T',
'LBL_NEW_ATTACH_BUTTON_KEY'=>'F',
'LBL_NEW_NOTE_BUTTON_LABEL'=>'Nieuwe notitie',
'LBL_NEW_ATTACH_BUTTON_LABEL'=>'Bijlage toevoegen',
'LBL_TRACK_EMAIL_BUTTON_TITLE'=>'E-mail volgen [Alt+K]',
'LBL_TRACK_EMAIL_BUTTON_KEY'=>'K',
'LBL_TRACK_EMAIL_BUTTON_LABEL'=>'E-mail volgen',

'LBL_LIST_CLOSE'=>'Sluiten',
'LBL_LIST_STATUS'=>'Status',
'LBL_LIST_CONTACT'=>'Contact',
//Added for 4.2 release for Account column support as shown by Fredy
'LBL_LIST_ACCOUNT'=>'Account',
'LBL_LIST_RELATED_TO'=>'Gerelateerd aan',
'LBL_LIST_DUE_DATE'=>'Vervaldatum',
'LBL_LIST_DATE'=>'Datum',
'LBL_LIST_SUBJECT'=>'Onderwerp',
'LBL_LIST_LAST_MODIFIED'=>'Gewijzigd op',
'LBL_LIST_RECURRING_TYPE'=>'Herhaaltype',

'ERR_DELETE_RECORD'=>"Een veld moet gespecificeerd zijn om de account te verwijderen.",
'NTC_NONE_SCHEDULED'=>'Geen planning.',

// Added fields for Attachments in Activities/SubPanelView.php
'LBL_ATTACHMENTS'=>'Bijlage',
'LBL_NEW_ATTACHMENT'=>'Nieuwe bijlage',

//Added fields after RC1 - Release
'LBL_ALL'=>'Alles',
'LBL_CALL'=>'Telefoongesprek',
'LBL_MEETING'=>'Vergadering',
'LBL_TASK'=>'Taak',

//Added for 4GA Release
'Subject'=>'Onderwerp',
'Assigned To'=>'Toegewezen aan',
'Start Date & Time'=>'Begindatum & tijd',
'Time Start'=>'Begintijd',
'Due Date'=>'Einddatum',
'Related To'=>'Gerelateerd aan',
'Contact Name'=>'Contactnaam',
'Status'=>'Status',
'Priority'=>'Prioriteit',
'Visibility'=>'Overzicht',
'Send Notification'=>'Stuur notificatie',
'Created Time'=>'Gemaakt',
'Modified Time'=>'Gewijzigd',
'Activity Type'=>'Activiteit',
'Description'=>'Omschrijving',
'Duration'=>'Duur',
'Duration Minutes'=>'Tijdsduur in minuten',
'Location'=>'Locatie',
'No Time'=>'Geen tijd',
//Added for Send Reminder 4.2 release
'Send Reminder'=>'Stuur herinnering',
'LBL_YES'=>'Ja',
'LBL_NO'=>'Nee',
'LBL_DAYS'=>'dagen',
'LBL_MINUTES'=>'minuten ',
'LBL_HOURS'=>'uren',
'LBL_BEFORE_EVENT'=>'Voor afspraak',
//Added for CustomView 4.2 Release
'Close'=>'Sluiten',
'Start Date'=>'Startdatum',
'Type'=>'Type',
'End Date'=>'Einddatum',
'Recurrence'=> 'Herhaalafspraken',
'Recurring Type'=> 'Herhalingtype',
//Activities - Notification Error
'LBL_NOTIFICATION_ERROR'=>'Mail Error : Uw uitgaande mailserver is niet geconfigureerd en of gebruikersnaam en of toegangscode zijn niet correct',
// Mike Crowe Mod --------------------------------------------------------added for generic search
'LBL_GENERAL_INFORMATION'=>'Algemene informatie',


);

?>
