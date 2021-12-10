<?php
/* Copyright (C) 2021 EOXIA <dev@eoxia.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *   	\file       envelope_card.php
 *		\ingroup    enveloppe
 *		\brief      Page to create/edit/view enveloppe
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) { $i--; $j--; }
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
require_once './class/envelope.class.php';
require_once './core/modules/doliletter/mod_envelope_standard.php';
require_once './lib/doliletter_envelope.lib.php';
require_once './lib/doliletter.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

global $db, $conf, $langs, $user, $hookmanager;

// Load translation files required by the page
$langs->loadLangs(array("doliletter@doliletter", "other"));

// Get parameters
$id          = GETPOST('id', 'int');
$action      = GETPOST('action', 'aZ09');
$massaction  = GETPOST('massaction', 'alpha'); // The bulk action (combo box choice into lists)
$confirm     = GETPOST('confirm', 'alpha');
$cancel      = GETPOST('cancel', 'aZ09');
$contextpage = GETPOST('contextpage', 'aZ') ?GETPOST('contextpage', 'aZ') : 'riskcard'; // To manage different context of search
$backtopage  = GETPOST('backtopage', 'alpha');

// Initialize technical objects
$object         = new Envelope($db);
$signatory      = new EnvelopeSignature($db);
$refEnvelopeMod = new $conf->global->DOLILETTER_ENVELOPE_ADDON();
$extrafields    = new ExtraFields($db);
$usertmp        = new User($db);

$object->fetch($id);

$hookmanager->initHooks(array('lettercard', 'globalcard')); // Note that conf->hooks_modules contains array

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// Initialize array of search criterias
$search_all = GETPOST("search_all", 'alpha');
$search = array();
foreach ($object->fields as $key => $val) {
	if (GETPOST('search_'.$key, 'alpha')) {
		$search[$key] = GETPOST('search_'.$key, 'alpha');
	}
}

if (empty($action) && empty($id) && empty($ref)) {
	$action = 'view';
}
$upload_dir = $conf->doliletter->multidir_output[$conf->entity ? $conf->entity : $conf->entity]."/envelope/".get_exdir(0, 0, 0, 1, $object);
// Load object
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php'; // Must be include, not include_once.

$permissiontoread = $user->rights->doliletter->envelope->read;
$permissiontoadd = $user->rights->doliletter->envelope->write; // Used by the include of actions_addupdatedelete.inc.php and actions_lineupdown.inc.php
$permissiontodelete = $user->rights->doliletter->envelope->delete || ($permissiontoadd && isset($object->status));
$permissionnote = $user->rights->envelope->envelope->write; // Used by the include of actions_setnotes.inc.php
$permissiondellink = $user->rights->envelope->letter->write; // Used by the include of actions_dellink.inc.php
$upload_dir = $conf->doliletter->multidir_output[$conf->entity];
$thirdparty = new Societe($db);
$thirdparty->fetch($object->fk_soc);

// Security check (enable the most restrictive one)
//if ($user->socid > 0) accessforbidden();
//if ($user->socid > 0) $socid = $user->socid;
//$isdraft = (($object->status == $object::STATUS_DRAFT) ? 1 : 0);
//restrictedArea($user, $object->element, $object->id, $object->table_element, '', 'fk_soc', 'rowid', $isdraft);
//if (empty($conf->envelope->enabled)) accessforbidden();
//if (!$permissiontoread) accessforbidden();


/*
 * Actions
 */

//action to send Email

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if ($action == 'remove_file') {
	if (!empty($upload_dir)) {
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

		$langs->load("other");
		$filetodelete = GETPOST('file', 'alpha');
		$file = $upload_dir.'/'.$filetodelete;
		$ret = dol_delete_file($file, 0, 0, 0, $object);
		if ($ret) setEventMessages($langs->trans("FileWasRemoved", $filetodelete), null, 'mesgs');
		else setEventMessages($langs->trans("ErrorFailToDeleteFile", $filetodelete), null, 'errors');

		// Make a redirect to avoid to keep the remove_file into the url that create side effects
		$urltoredirect = $_SERVER['REQUEST_URI'];
		$urltoredirect = preg_replace('/#builddoc$/', '', $urltoredirect);
		$urltoredirect = preg_replace('/action=remove_file&?/', '', $urltoredirect);

		header('Location: '.$urltoredirect);
		exit;
	}
	else {
		setEventMessages('BugFoundVarUploaddirnotDefined', null, 'errors');
	}
}

if (empty($reshook)) {

	$error = 0;

	$backurlforlist = dol_buildpath('/doliletter/envelope_list.php', 1);

	if (empty($backtopage) || ($cancel && empty($id))) {
		if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
			if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) {
				$backtopage = $backurlforlist;
			} else {
				$backtopage = dol_buildpath('/doliletter/envelope_card.php', 1).'?id='.($id > 0 ? $id : '__ID__');
			}
		}
	}

	$triggermodname = 'DOLISIMPLEDOC_SIMPLEDOC_MODIFY'; // Name of trigger action code to execute when we modify record

	// Actions cancel, add, update, update_extras, confirm_validate, confirm_delete, confirm_deleteline, confirm_clone, confirm_close, confirm_setdraft, confirm_reopen
	//include DOL_DOCUMENT_ROOT.'/core/actions_addupdatedelete.inc.php';

	// Action to add record
	if ($action == 'add' && $permissiontoadd) {
		// Get parameters
		$society_id     = GETPOST('fk_soc');
		$content        = GETPOST('content');
		$note_private     = GETPOST('note_private');
		$note_public        = GETPOST('note_public');
		$label        = GETPOST('label');

		// Initialize object preventionplan
		$now                   = dol_now();
		$object->ref           = $refEnvelopeMod->getNextValue($object);
		$object->ref_ext       = 'doliletter_' . $object->ref;
		$object->date_creation = $object->db->idate($now);
		$object->tms           = $now;
		$object->import_key    = "";
		$object->note_private  = $note_private;
		$object->note_public  = $note_public;
		$object->label        = $label;

		$object->fk_soc         = $society_id;
		$object->content        = $content;

		$object->fk_user_creat = $user->id ? $user->id : 1;

		// Check parameters
		switch (1) {
			case $society_id == -1:
				setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Society')), null, 'errors');
				$error++;
				break;
			case empty($label):
				setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Label')), null, 'errors');
				$error++;
				break;
			case empty($content):
				setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Content')), null, 'errors');
				$error++;
				break;
		}

		if (!$error) {
			$result = $object->create($user, false);
			if ($result > 0) {
				//$signatory->setSignatory($object->id,'user', array($user), 'E_SENDER');

//				if ($extresponsible_id > 0) {
//					$signatory->setSignatory($object->id,'socpeople', array($extresponsible_id), 'PP_EXT_SOCIETY_RESPONSIBLE');
//				}

				// Creation envelope OK
				$urltogo = str_replace('__ID__', $result, $backtopage);
				$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urltogo); // New method to autoselect project after a New on another form object creation
				header("Location: " . $urltogo);
				exit;
			}
			else {
				// Creation envelope KO
				if (!empty($object->errors)) setEventMessages(null, $object->errors, 'errors');
				else  setEventMessages($object->error, null, 'errors');
			}
		} else {
			$action = 'create';
		}
	}

	// Action to update record
	if ($action == 'update' && $permissiontoadd) {
		$society_id     = GETPOST('fk_soc');
		$content        = GETPOST('content');
		$label        = GETPOST('label');


		$object->label        = $label;
		$object->fk_soc         = $society_id;
		$object->content        = $content;

		$object->fk_user_creat = $user->id ? $user->id : 1;
		if (!$error) {
			$result = $object->update($user, false);
			if ($result > 0) {
				$urltogo = str_replace('__ID__', $result, $backtopage);
				$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urltogo); // New method to autoselect project after a New on another form object creation
				header("Location: " . $urltogo);
				exit;
			}
			else
			{
				if (!empty($object->errors)) setEventMessages(null, $object->errors, 'errors');
				else  setEventMessages($object->error, null, 'errors');
			}
		}  else {
			$action = 'edit';
		}
	}

	if ($action == 'confirm_delete' && GETPOST("confirm") == "yes")
	{
		$object->setStatusCommon($user, 0);
		$urltogo = DOL_URL_ROOT . '/custom/doliletter/envelope_list.php';
		header("Location: " . $urltogo);
		exit;
	}

	// Actions when linking object each other
	include DOL_DOCUMENT_ROOT.'/core/actions_dellink.inc.php';

	// Actions when printing a doc from card
	include DOL_DOCUMENT_ROOT.'/core/actions_printing.inc.php';

	// Action to move up and down lines of object
	//include DOL_DOCUMENT_ROOT.'/core/actions_lineupdown.inc.php';

	// Action to build doc
	include DOL_DOCUMENT_ROOT.'/core/actions_builddoc.inc.php';


	// Actions to send emails
	$triggersendname = 'DOLILETTER_ENVELOPE_SENTBYMAIL';
	$autocopy = 'MAIN_MAIL_AUTOCOPY_SIMPLEDOC_TO';
	$trackid = 'envelope'.$object->id;

	include DOL_DOCUMENT_ROOT.'/core/actions_sendmails.inc.php';
}




/*
 * View
 *
 * Put here all code to build page
 */

$form = new Form($db);
$formother = new FormOther($db);
$formfile = new FormFile($db);
$formproject = new FormProjets($db);

$title        = $langs->trans("Envelope");
$title_create = $langs->trans("NewEnvelope");
$title_edit   = $langs->trans("ModifyEnvelope");
$help_url     = '';

llxHeader('', $title, $help_url);

// Part to create
if ($action == 'create') {
	print load_fiche_titre($title_create, '', "doliletter32px@doliletter");

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';
	if ($backtopage) {
		print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
	}
	if ($backtopageforcancel) {
		print '<input type="hidden" name="backtopageforcancel" value="'.$backtopageforcancel.'">';
	}

	print dol_get_fiche_head(array(), '');

	print '<table class="border centpercent tableforfieldcreate">'."\n";

	unset($object->fields['ref']);
	unset($object->fields['model_pdf']);
	unset($object->fields['last_main_doc']);
	unset($object->fields['content']);
	unset($object->fields['note_public']);
	unset($object->fields['note_private']);
	unset($object->fields['fk_soc']);


	//Ref -- Ref
	print '<tr><td class="fieldrequired">'.$langs->trans("Ref").'</td><td>';
	print '<input hidden class="flat" type="text" size="36" name="ref" id="ref" value="'.$refEnvelopeMod->getNextValue($object).'">';
	print $refEnvelopeMod->getNextValue($object);
	print '</td></tr>';

	// Common attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_add.tpl.php';

	//Society -- Société
	print '<tr><td class="fieldrequired">'.$langs->trans("Society").'</td><td>';
	$events = array();
	$events[1] = array('method' => 'getContacts', 'url' => dol_buildpath('/core/ajax/contacts.php?showempty=1', 1), 'htmlname' => 'contact', 'params' => array('add-customer-contact' => 'disabled'));
	print $form->select_company(GETPOST('fromtype') == 'thirdparty' ? GETPOST('fromid') : GETPOST('fk_soc'), 'fk_soc', '', 'SelectThirdParty', 1, 0, $events, 0, 'minwidth300');
	print ' <a href="'.DOL_URL_ROOT.'/societe/card.php?action=create&backtopage='.urlencode($_SERVER["PHP_SELF"].'?action=create').'" target="_blank"><span class="fa fa-plus-circle valignmiddle paddingleft" title="'.$langs->trans("AddThirdParty").'"></span></a>';
	print '</td></tr>';


	//Content -- Contenue
	print '<tr class="content_field"><td><label for="content">'.$langs->trans("Content").'</label></td><td>';
	$doleditor = new DolEditor('content', GETPOST('content'), '', 90, 'dolibarr_notes', '', false, true, $conf->global->FCKEDITOR_ENABLE_SOCIETE, ROWS_3, '90%');
	$doleditor->Create();
	print '</td></tr>';

	//PublicNote -- Note publique
	print '<tr class="content_field"><td><label for="note_public">'.$langs->trans("PublicNote").'</label></td><td>';
	$doleditor = new DolEditor('note_public', GETPOST('note_public'), '', 90, 'dolibarr_notes', '', false, true, $conf->global->FCKEDITOR_ENABLE_SOCIETE, ROWS_3, '90%');
	$doleditor->Create();
	print '</td></tr>';

	//PrivateNote -- Note privée
	print '<tr class="content_field"><td><label for="note_private">'.$langs->trans("PrivateNote").'</label></td><td>';
	$doleditor = new DolEditor('note_private', GETPOST('note_private'), '', 90, 'dolibarr_notes', '', false, true, $conf->global->FCKEDITOR_ENABLE_SOCIETE, ROWS_3, '90%');
	$doleditor->Create();
	print '</td></tr>';


	// Other attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_add.tpl.php';

	print '</table>'."\n";

	print dol_get_fiche_end();

	print '<div class="center">';
	print '<input type="submit" class="button" name="add" value="'.dol_escape_htmltag($langs->trans("Create")).'">';
	print '&nbsp; ';
	print '<input type="'.($backtopage ? "submit" : "button").'" class="button button-cancel" name="cancel" value="'.dol_escape_htmltag($langs->trans("Cancel")).'"'.($backtopage ? '' : ' onclick="javascript:history.go(-1)"').'>'; // Cancel for create does not post form if we don't know the backtopage
	print '</div>';

	print '</form>';

	//dol_set_focus('input[name="ref"]');
}

// Part to edit record
if (($id || $ref) && $action == 'edit') {
	print load_fiche_titre($langs->trans("MyObject"), '', 'object_'.$object->picto);

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="id" value="'.$object->id.'">';
	if ($backtopage) {
		print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
	}
	if ($backtopageforcancel) {
		print '<input type="hidden" name="backtopageforcancel" value="'.$backtopageforcancel.'">';
	}

	print dol_get_fiche_head();

	print '<table class="border centpercent tableforfieldedit">'."\n";

	// Common attributes
	//include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_edit.tpl.php';

	unset($object->fields['ref']);
	unset($object->fields['model_pdf']);
	unset($object->fields['last_main_doc']);
	unset($object->fields['content']);
	unset($object->fields['note_public']);
	unset($object->fields['note_private']);
	unset($object->fields['fk_soc']);

	//Society -- Société
	print '<tr><td class="fieldrequired">'.$langs->trans("Society").'</td><td>';
	$events = array();
	$events[1] = array('method' => 'getContacts', 'url' => dol_buildpath('/core/ajax/contacts.php?showempty=1', 1), 'htmlname' => 'contact', 'params' => array('add-customer-contact' => 'disabled'));
	print $form->select_company($object->fk_soc, 'fk_soc', '', 'SelectThirdParty', 1, 0, $events, 0, 'minwidth300');
	print ' <a href="'.DOL_URL_ROOT.'/societe/card.php?action=create&backtopage='.urlencode($_SERVER["PHP_SELF"].'?action=create').'" target="_blank"><span class="fa fa-plus-circle valignmiddle paddingleft" title="'.$langs->trans("AddThirdParty").'"></span></a>';
	print '</td></tr>';

	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_edit.tpl.php';


	//Content -- Contenue
	print '<tr class="content_field"><td><label for="content">'.$langs->trans("Content").'</label></td><td>';
	$doleditor = new DolEditor('content', $object->content, '', 90, 'dolibarr_notes', '', false, true, $conf->global->FCKEDITOR_ENABLE_SOCIETE, ROWS_3, '90%');
	$doleditor->Create();
	print '</td></tr>';



	print '</table>';

	print dol_get_fiche_end();

	print '<div class="center"><input type="submit" class="button button-save" name="save" value="'.$langs->trans("Save").'">';
	print ' &nbsp; <input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans("Cancel").'">';
	print '</div>';

	print '</form>';
}

// Part to show record
if ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'create'))) {
	$res = $object->fetch_optionals();

	$head = envelopePrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans("Envelope"), -1, "doliletter@doliletter");

	$formconfirm = '';

	// Confirmation to delete
	if ($action == 'delete') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('DeleteMyObject'), $langs->trans('ConfirmDeleteObject'), 'confirm_delete', '', 0, 1);
	}
	// Confirmation to delete line
	if ($action == 'deleteline') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id.'&lineid='.$lineid, $langs->trans('DeleteLine'), $langs->trans('ConfirmDeleteLine'), 'confirm_deleteline', '', 0, 1);
	}

	// Confirmation of action xxxx
	if ($action == 'xxx') {
		$formquestion = array();
		/*
		$forcecombo=0;
		if ($conf->browser->name == 'ie') $forcecombo = 1;	// There is a bug in IE10 that make combo inside popup crazy
		$formquestion = array(
			// 'text' => $langs->trans("ConfirmClone"),
			// array('type' => 'checkbox', 'name' => 'clone_content', 'label' => $langs->trans("CloneMainAttributes"), 'value' => 1),
			// array('type' => 'checkbox', 'name' => 'update_prices', 'label' => $langs->trans("PuttingPricesUpToDate"), 'value' => 1),
			// array('type' => 'other',    'name' => 'idwarehouse',   'label' => $langs->trans("SelectWarehouseForStockDecrease"), 'value' => $formproduct->selectWarehouses(GETPOST('idwarehouse')?GETPOST('idwarehouse'):'ifone', 'idwarehouse', '', 1, 0, 0, '', 0, $forcecombo))
		);
		*/
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('XXX'), $text, 'confirm_xxx', $formquestion, 0, 1, 220);
	}

	// Call Hook formConfirm
	$parameters = array('formConfirm' => $formconfirm, 'lineid' => $lineid);
	$reshook = $hookmanager->executeHooks('formConfirm', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
	if (empty($reshook)) {
		$formconfirm .= $hookmanager->resPrint;
	} elseif ($reshook > 0) {
		$formconfirm = $hookmanager->resPrint;
	}

	// Print form confirm
	print $formconfirm;


	// Object card
	// ------------------------------------------------------------
	$linkback = '<a href="'.dol_buildpath('/doliletter/envelope_list.php', 1).'?restore_lastsearch_values=1'.(!empty($socid) ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';

	$morehtmlref = '<div class="refidno">';

	$morehtmlref .= '</div>';


	dol_banner_tab($object, 'ref', $linkback, 0, 'ref', 'ref', $morehtmlref);


	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border centpercent tableforfield">'."\n";



	//correct thirdparty display
	print '<tr><td class="titlefield">';
	print $langs->trans("ThirdParty");
	print '</td>';
	print '<td>';
	print $thirdparty->getNomUrl(1);
	print '</td></tr>';

	print '<tr><td class="titlefield">';
	print $langs->trans("Content");
	print '</td>';
	print '<td>';
	print dol_trunc($object->content, 60, 'wrap'); //wrap -> middle?
	print '</td></tr>';

	//unused display of information
	unset($object->fields['fk_soc']);
	unset($object->fields['content']);
	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_view.tpl.php';
	// Other attributes. Fields from hook formObjectOptions and Extrafields.
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_view.tpl.php';

	print '</table>';
	print '</div>';
	print '</div>';

	print '<div class="clearboth"></div>';

	print dol_get_fiche_end();

	// Buttons for actions
	if ($action != 'presend' && $action != 'editline') {
		print '<div class="tabsAction">'."\n";
		$parameters = array();
		$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
		if ($reshook < 0) {
			setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
		}

		if (empty($reshook)) {
			// Send
			$class = 'ModelePDFEnvelope';
			$modellist = call_user_func($class.'::liste_modeles', $db, 100);
			if (!empty($modellist))
			{
				asort($modellist);

				$modellist = array_filter($modellist, 'remove_index');

				if (is_array($modellist) && count($modellist) == 1)    // If there is only one element
				{
					$arraykeys = array_keys($modellist);
					$arrayvalues = preg_replace('/template_/','', array_values($modellist)[0]);

					$modellist[$arraykeys[0]] = $arrayvalues;
					$modelselected = $arraykeys[0];
				}
			}
			print dolGetButtonAction($langs->trans($object->status == 1 ? 'Sign' : 'alreadySigned'), '', '',  DOL_URL_ROOT . '/custom/doliletter/envelope_signature.php'.'?id='.$object->id.'&mode=init&token='.newToken(),  '', $object->status == 1);

			print dolGetButtonAction($langs->trans('SendMail'), '', 'presend', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=presend&mode=init&model='.$modelselected.'&token='.newToken(),  '', $object->status == 2);

			print dolGetButtonAction($langs->trans('SendLetter'), '', 'lettersend', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=lettersend&mode=init&token='.newToken(), '', $object->status == 2);

			print dolGetButtonAction($langs->trans('Modify'), '', 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit&token='.newToken(), '', $permissiontoadd);

			// Delete (need delete permission, or if draft, just need create/modify permission)
			print dolGetButtonAction($langs->trans('Delete'), '', 'delete', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=delete&token='.newToken(), '', $permissiontodelete || ($object->status == $object::STATUS_DRAFT && $permissiontoadd));
		}
		print '</div>'."\n";
	}


	if ($action != 'presend' && $action != 'lettersend') {
		print '<div class="fichecenter"><div class="fichehalfleft">';
		print '<a name="builddoc"></a>'; // ancre

		$includedocgeneration = 1;

		// Documents
		if ($includedocgeneration) {
			$objref = dol_sanitizeFileName($object->ref);
			$relativepath = $objref.'/'.$objref.'.pdf';
			$filedir = $conf->doliletter->dir_output.'/'.$object->element.'/'.$objref;
			$urlsource = $_SERVER["PHP_SELF"]."?id=".$object->id;
			$genallowed = $user->rights->doliletter->envelope->read; // If you can read, you can build the PDF to read content
			$delallowed = $user->rights->doliletter->envelope->write; // If you can create/edit, you can remove a file on card
			print $formfile->showdocuments('doliletter:Envelope', $object->element.'/'.$objref, $filedir, $urlsource, $genallowed, $delallowed, $conf->global->DOLILETTER_ENVELOPE_ADDON_PDF, 1, 0, 0, 28, 0, '', '', '', $langs->defaultlang);
		}

		print '</div><div class="fichehalfright"><div class="ficheaddleft">';

		$MAXEVENT = 10;

		$morehtmlright = '<a href="'.dol_buildpath('/doliletter/envelope_agenda.php', 1).'?id='.$object->id.'">';
		$morehtmlright .= $langs->trans("SeeAll");
		$morehtmlright .= '</a>';

		// List of actions on element
		include_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
		$formactions = new FormActions($db);
		$somethingshown = $formactions->showactions($object, $object->element.'@'.$object->module, '', 1, '', $MAXEVENT, '', $morehtmlright);

		print '</div></div></div>';
	}

	//Select mail models is same action as presend
	if (GETPOST('modelselected')) {
		$action = 'presend';
	}

	// Presend form
	$modelmail = 'envelope';
	$defaulttopic = 'InformationMessage';
	$diroutput = $upload_dir . '/' . $object->element;
	$trackid = 'envelope'.$object->id;

	if ($action == 'presend')
	{
		$langs->load("mails");

		$titreform = 'SendMail';

		$object->fetch_projet();

		if (!in_array($object->element, array('societe', 'user', 'member')))
		{
			$ref = dol_sanitizeFileName($object->ref);
			include_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
			$fileparams = dol_most_recent_file($diroutput.'/'.$ref, '');
			$file = $fileparams['fullname'];
		}

		// Define output language
		$outputlangs = $langs;
		$newlang = '';
		if ($conf->global->MAIN_MULTILANGS && empty($newlang) && !empty($_REQUEST['lang_id']))
		{
			$newlang = $_REQUEST['lang_id'];
		}
		if ($conf->global->MAIN_MULTILANGS && empty($newlang))
		{
			$newlang = $object->thirdparty->default_lang;
		}

		if (!empty($newlang))
		{
			$outputlangs = new Translate('', $conf);
			$outputlangs->setDefaultLang($newlang);
			// Load traductions files required by page
			$outputlangs->loadLangs(array('digiriskdolibarr'));
		}

		$topicmail = '';
		if (empty($object->ref_client)) {
			$topicmail = $outputlangs->trans($defaulttopic, '__REF__');
		} elseif (!empty($object->ref_client)) {
			$topicmail = $outputlangs->trans($defaulttopic, '__REF__ (__REFCLIENT__)');
		}

		// Build document if it not exists
		$allspecimen = true;
		$fileslist = dol_dir_list($fileparams['path']);
			foreach($fileslist as $item) {
				if (!preg_match('/specimen/', $item['name'])){
					$allspecimen = false;
				}
			}

		$needcreate = empty($file) || $allspecimen;

		$forcebuilddoc = true;
		if ($forcebuilddoc)    // If there is no default value for supplier invoice, we do not generate file, even if modelpdf was set by a manual generation
		{
			if (($needcreate || !is_readable($file)) && method_exists($object, 'generateDocument'))
			{
				$result = $object->generateDocument(GETPOST('model') ? GETPOST('model') : $object->model_pdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
				if ($result < 0) {
					dol_print_error($db, $object->error, $object->errors);
					exit();
				}
				$fileparams = dol_most_recent_file($diroutput.'/'.$ref, preg_quote($ref, '/').'[^\-]+');
				$file = $fileparams['fullname'];
			}
		}

		print '<div id="formmailbeforetitle" name="formmailbeforetitle"></div>';
		print '<div class="clearboth"></div>';
		print '<br>';
		print load_fiche_titre($langs->trans($titreform));

		print dol_get_fiche_head('');

		// Create form for email
		include_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
		$formmail = new FormMail($db);

		$formmail->param['langsmodels'] = (empty($newlang) ? $langs->defaultlang : $newlang);
		$formmail->fromtype = (GETPOST('fromtype') ?GETPOST('fromtype') : (!empty($conf->global->MAIN_MAIL_DEFAULT_FROMTYPE) ? $conf->global->MAIN_MAIL_DEFAULT_FROMTYPE : 'user'));

		$formmail->trackid = $trackid;

		if (!empty($conf->global->MAIN_EMAIL_ADD_TRACK_ID) && ($conf->global->MAIN_EMAIL_ADD_TRACK_ID & 2))	// If bit 2 is set
		{
			include DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
			$formmail->frommail = dolAddEmailTrackId($formmail->frommail, $trackid);
		}
		$formmail->withfrom = 1;

		// Fill list of recipient with email inside <>.
		$liste = array();

		if (!empty($object->socid) && $object->socid > 0 && !is_object($object->thirdparty) && method_exists($object, 'fetch_thirdparty')) {
			$object->fetch_thirdparty();
		}
		if (is_object($object->thirdparty))
		{
			foreach ($object->thirdparty->thirdparty_and_contact_email_array(0) as $key => $value) {
				$liste[$key] = $value;

			}
		}

		if (!empty($conf->global->MAIN_MAIL_ENABLED_USER_DEST_SELECT)) {
			$listeuser = array();
			$fuserdest = new User($db);

			$result = $fuserdest->fetchAll('ASC', 't.lastname', 0, 0, array('customsql'=>'t.statut=1 AND t.employee=1 AND t.email IS NOT NULL AND t.email<>\'\''), 'AND', true);
			if ($result > 0 && is_array($fuserdest->users) && count($fuserdest->users) > 0) {
				foreach ($fuserdest->users as $uuserdest) {
					$listeuser[$uuserdest->id] = $uuserdest->user_get_property($uuserdest->id, 'email');
				}
			} elseif ($result < 0) {
				setEventMessages(null, $fuserdest->errors, 'errors');
			}
			if (count($listeuser) > 0) {
				$formmail->withtouser = $listeuser;
				$formmail->withtoccuser = $listeuser;
			}
		}

		$formmail->withto = $liste;
		$formmail->withtofree = (GETPOSTISSET('sendto') ? (GETPOST('sendto', 'alphawithlgt') ? GETPOST('sendto', 'alphawithlgt') : '1') : '1');
		$formmail->withtocc = $liste;
		$formmail->withtoccc = $conf->global->MAIN_EMAIL_USECCC;
		$formmail->withtopic = $topicmail;
		$formmail->withfile = 2;
		$formmail->withbody = 1;
		$formmail->withdeliveryreceipt = 1;
		$formmail->withcancel = 1;

		//$arrayoffamiliestoexclude=array('system', 'mycompany', 'object', 'objectamount', 'date', 'user', ...);
		if (!isset($arrayoffamiliestoexclude)) $arrayoffamiliestoexclude = null;

		// Make substitution in email content
		$substitutionarray = getCommonSubstitutionArray($outputlangs, 0, $arrayoffamiliestoexclude, $object);
		$substitutionarray['__CHECK_READ__'] = (is_object($object) && is_object($object->thirdparty)) ? '<img src="'.DOL_MAIN_URL_ROOT.'/public/emailing/mailing-read.php?tag='.$object->thirdparty->tag.'&securitykey='.urlencode($conf->global->MAILING_EMAIL_UNSUBSCRIBE_KEY).'" width="1" height="1" style="width:1px;height:1px" border="0"/>' : '';
		$substitutionarray['__PERSONALIZED__'] = ''; // deprecated
		$substitutionarray['__CONTACTCIVNAME__'] = '';
		$parameters = array(
			'mode' => 'formemail'
		);
		complete_substitutions_array($substitutionarray, $outputlangs, $object, $parameters);

		// Find the good contact address
		$tmpobject = $object;

		$contactarr = array();
		$contactarr = $tmpobject->liste_contact(-1, 'external');

		if (is_array($contactarr) && count($contactarr) > 0) {
			require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
			$contactstatic = new Contact($db);

			foreach ($contactarr as $contact) {
				$contactstatic->fetch($contact['id']);
				$substitutionarray['__CONTACT_NAME_'.$contact['code'].'__'] = $contactstatic->getFullName($outputlangs, 1);
			}
		}

		// Array of substitutions
		$formmail->substit = $substitutionarray;

		// Array of other parameters
		$formmail->param['action'] = 'send';
		$formmail->param['models'] = $modelmail;
		$formmail->param['models_id'] = GETPOST('modelmailselected', 'int');
		$formmail->param['id'] = $object->id;
		$formmail->param['returnurl'] = $_SERVER["PHP_SELF"].'?id='.$object->id;
		$formmail->param['fileinit'] = array($file);

		// Show form
		print $formmail->get_form();

		print dol_get_fiche_end();
	}
	if ($action == 'lettersend')
	{
		//form for contacts
		//save -> to lettersave action, which creates the object and saves it in DB
	}
}

// End of page
llxFooter();
$db->close();
