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
 *		\ingroup    envelope
 *		\brief      Page to create/edit/view envelope
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
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';

require_once './class/envelope.class.php';
require_once './class/letter_sending.class.php';
require_once './class/email_sending.class.php';
require_once './core/modules/doliletter/mod_envelope_standard.php';
require_once './lib/doliletter_envelope.lib.php';
require_once './lib/doliletter.lib.php';
require_once './lib/doliletter_function.lib.php';

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
$contact        = new Contact($db);
$project        = new Project($db);
$signatory      = new EnvelopeSignature($db);
$refEnvelopeMod = new $conf->global->DOLILETTER_ENVELOPE_ADDON();
$extrafields    = new ExtraFields($db);
$usertmp        = new User($db);
$letter         = new LetterSending($db);

$object->fetch($id);
if ($object->fk_contact > 0) {
	$linked_contact = $contact;
	$linked_contact->fetch($object->fk_contact);
}

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
$permissionnote = $user->rights->doliletter->envelope->write; // Used by the include of actions_setnotes.inc.php
$permissiondellink = $user->rights->envelope->letter->write; // Used by the include of actions_dellink.inc.php
$upload_dir = $conf->doliletter->multidir_output[$conf->entity];
$thirdparty = new Societe($db);
$thirdparty->fetch($object->fk_soc);

// Security check (enable the most restrictive one)
if ($user->socid > 0) accessforbidden();
if ($user->socid > 0) $socid = $user->socid;
if (empty($conf->doliletter->enabled)) accessforbidden();
if (!$permissiontoread) accessforbidden();


/*
 * Actions
 */

//cancelling current action
if (GETPOST('cancel')) $action = null;
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

	$triggermodname = 'DOLILETTER_ENVELOPE_MODIFY'; // Name of trigger action code to execute when we modify record

	// Actions cancel, add, update, update_extras, confirm_validate, confirm_delete, confirm_deleteline, confirm_clone, confirm_close, confirm_setdraft, confirm_reopen
	//include DOL_DOCUMENT_ROOT.'/core/actions_addupdatedelete.inc.php';

	// Action to add record
	if ($action == 'add' && $permissiontoadd) {
		// Get parameters
		$society_id     = GETPOST('fk_soc');
		$content        = GETPOST('content', 'restricthtml');
		$note_private   = GETPOST('note_private');
		$note_public    = GETPOST('note_public');
		$label          = GETPOST('label');
		$contact_id     = GETPOST('fk_contact');
		$project_id     = GETPOST('fk_project');

		// Initialize object
		$now                   = dol_now();
		$object->ref           = $refEnvelopeMod->getNextValue($object);
		$object->ref_ext       = 'doliletter_' . $object->ref;
		$object->date_creation = $object->db->idate($now);
		$object->tms           = $now;
		$object->import_key    = "";
		$object->note_private  = $note_private;
		$object->note_public   = $note_public;
		$object->label         = $label;

		$object->fk_soc        = $society_id;
		$object->fk_contact    = $contact_id;
		$object->fk_project    = $project_id;

		$object->content       = $content;
		$object->entity = $conf->entity ?: 1;

		$object->fk_user_creat = $user->id ? $user->id : 1;

		// Check parameters
		if (empty($society_id) || $society_id == -1) {
			setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Society')), null, 'errors');
			$error++;
		}
		if (empty($label)) {
			setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Label')), null, 'errors');
			$error++;
		}
		if (empty($contact_id)) {
			setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Contact')), null, 'errors');
			$error++;
		}

		if (!$error) {
			$result = $object->create($user, false);
			if ($result > 0) {
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
		$content        = GETPOST('content', 'restricthtml');
		$label          = GETPOST('label');
		$contact_id     = GETPOST('fk_contact');
		$project_id     = GETPOST('fk_project');

		$object->label      = $label;
		$object->fk_soc     = $society_id;
		$object->content    = $content;
		$object->fk_contact = $contact_id;
		$object->fk_project = $project_id;

		$object->fk_user_creat = $user->id ? $user->id : 1;
		if (!$error) {
			$result = $object->update($user, false);
			if ($result > 0) {
				$signatory->deleteSignatoriesSignatures($object->id, 0);
				$object->setStatusCommon($user, 0);
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
		$object->setStatusCommon($user, -1);
		$urltogo = DOL_URL_ROOT . '/custom/doliletter/envelope_list.php';
		header("Location: " . $urltogo);
		exit;
	}

	if ($action == 'confirm_setLocked' && GETPOST('confirm') == 'yes') {

		$extintervenant_id  = $object->fk_contact;

		//Check email of intervenants
		$contact->fetch($extintervenant_id);
		if (!dol_strlen($contact->email)) {
			setEventMessages($langs->trans('ErrorNoEmailForExtIntervenant', $langs->transnoentitiesnoconv('ExtIntervenant')), null, 'errors');
			$error++;
		}

		if (!$error) {
			$result = $signatory->setSignatory($object->id,'socpeople', array($extintervenant_id), 'E_RECEIVER', 1);

			if ($result > 0) {
				$contact->fetch($extintervenant_id);
				setEventMessages($langs->trans('AddAttendantMessage') . ' ' . $contact->firstname . ' ' . $contact->lastname, array());
				$object->call_trigger('ENVELOPE_LOCK', $user);
				// Creation attendant OK
				$object->setStatusCommon($user, 2);
				$urltogo = str_replace('__ID__', $result, $backtopage);
				$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urltogo); // New method to autoselect project after a New on another form object creation
				header("Location: " . $urltogo);
				exit;
			}
			else
			{
				// Creation attendant KO
				if (!empty($object->errors)) setEventMessages(null, $object->errors, 'errors');
				else  setEventMessages($object->error, null, 'errors');
			}
		}
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
	$autocopy = 'MAIN_MAIL_AUTOCOPY_ENVELOPE_TO';
	$trackid = 'envelope'.$object->id;

	/*
	 * Send mail
	 */
	if (($action == 'send' || $action == 'relance') && !$_POST['addfile'] && !$_POST['removAll'] && !$_POST['removedfile'] && !$_POST['cancel'] && !$_POST['modelselected']) {
		if (empty($trackid)) {
			$trackid = GETPOST('trackid', 'aZ09');
		}

		$subject = '';
		$actionmsg = '';
		$actionmsg2 = '';

		$langs->load('mails');

		if (is_object($object)) {
			$result = $object->fetch($id);

			$sendtosocid = 0; // Id of related thirdparty
			if (method_exists($object, "fetch_thirdparty") && !in_array($object->element, array('member', 'user', 'expensereport', 'societe', 'contact'))) {
				$resultthirdparty = $object->fetch_thirdparty();
				$thirdparty = $object->thirdparty;
				if (is_object($thirdparty)) {
					$sendtosocid = $thirdparty->id;
				}
			} elseif ($object->element == 'member' || $object->element == 'user') {
				$thirdparty = $object;
				if ($object->socid > 0) {
					$sendtosocid = $object->socid;
				}
			} elseif ($object->element == 'expensereport') {
				$tmpuser = new User($db);
				$tmpuser->fetch($object->fk_user_author);
				$thirdparty = $tmpuser;
				if ($object->socid > 0) {
					$sendtosocid = $object->socid;
				}
			} elseif ($object->element == 'societe') {
				$thirdparty = $object;
				if (is_object($thirdparty) && $thirdparty->id > 0) {
					$sendtosocid = $thirdparty->id;
				}
			} elseif ($object->element == 'contact') {
				$contact = $object;
				if ($contact->id > 0) {
					$contact->fetch_thirdparty();
					$thirdparty = $contact->thirdparty;
					if (is_object($thirdparty) && $thirdparty->id > 0) {
						$sendtosocid = $thirdparty->id;
					}
				}
			} else {
				dol_print_error('', "Use actions_sendmails.in.php for an element/object '".$object->element."' that is not supported");
			}

			if (is_object($hookmanager)) {
				$parameters = array();
				$reshook = $hookmanager->executeHooks('initSendToSocid', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
			}
		} else {
			$thirdparty = $mysoc;
		}

		if ($result > 0) {
			$sendto = '';
			$sendtocc = '';
			$sendtobcc = '';
			$sendtoid = array();
			$sendtouserid = array();
			$sendtoccuserid = array();

			// Define $sendto
			$receiver = $_POST['receiver'];
			if (!is_array($receiver)) {
				if ($receiver == '-1') {
					$receiver = array();
				} else {
					$receiver = array($receiver);
				}
			}

			$tmparray = array();
			if (trim($_POST['sendto'])) {
				// Recipients are provided into free text field
				$tmparray[] = trim($_POST['sendto']);
			}

			if (trim($_POST['tomail'])) {
				// Recipients are provided into free hidden text field
				$tmparray[] = trim($_POST['tomail']);
			}

			if (count($receiver) > 0) {
				// Recipient was provided from combo list
				foreach ($receiver as $key => $val) {
					if ($val == 'thirdparty') { // Key selected means current third party ('thirdparty' may be used for current member or current user too)
						$tmparray[] = dol_string_nospecial($thirdparty->getFullName($langs), ' ', array(",")).' <'.$thirdparty->email.'>';
					} elseif ($val == 'contact') { // Key selected means current contact
						$tmparray[] = dol_string_nospecial($contact->getFullName($langs), ' ', array(",")).' <'.$contact->email.'>';
						$sendtoid[] = $contact->id;
					} elseif ($val) {	// $val is the Id of a contact
						$tmparray[] = $thirdparty->contact_get_property((int) $val, 'email');
						$sendtoid[] = ((int) $val);
					}
				}
			}

			if (!empty($conf->global->MAIN_MAIL_ENABLED_USER_DEST_SELECT)) {
				$receiveruser = $_POST['receiveruser'];
				if (is_array($receiveruser) && count($receiveruser) > 0) {
					$fuserdest = new User($db);
					foreach ($receiveruser as $key => $val) {
						$tmparray[] = $fuserdest->user_get_property($val, 'email');
						$sendtouserid[] = $val;
					}
				}
			}

			$sendto = implode(',', $tmparray);

			// Define $sendtocc
			$receivercc = $_POST['receivercc'];
			if (!is_array($receivercc)) {
				if ($receivercc == '-1') {
					$receivercc = array();
				} else {
					$receivercc = array($receivercc);
				}
			}
			$tmparray = array();
			if (trim($_POST['sendtocc'])) {
				$tmparray[] = trim($_POST['sendtocc']);
			}
			if (count($receivercc) > 0) {
				foreach ($receivercc as $key => $val) {
					if ($val == 'thirdparty') {	// Key selected means current thirdparty (may be usd for current member or current user too)
						// Recipient was provided from combo list
						$tmparray[] = dol_string_nospecial($thirdparty->name, ' ', array(",")).' <'.$thirdparty->email.'>';
					} elseif ($val == 'contact') {	// Key selected means current contact
						// Recipient was provided from combo list
						$tmparray[] = dol_string_nospecial($contact->name, ' ', array(",")).' <'.$contact->email.'>';
						//$sendtoid[] = $contact->id;  TODO Add also id of contact in CC ?
					} elseif ($val) {				// $val is the Id of a contact
						$tmparray[] = $thirdparty->contact_get_property((int) $val, 'email');
						//$sendtoid[] = ((int) $val);  TODO Add also id of contact in CC ?
					}
				}
			}
			if (!empty($conf->global->MAIN_MAIL_ENABLED_USER_DEST_SELECT)) {
				$receiverccuser = $_POST['receiverccuser'];

				if (is_array($receiverccuser) && count($receiverccuser) > 0) {
					$fuserdest = new User($db);
					foreach ($receiverccuser as $key => $val) {
						$tmparray[] = $fuserdest->user_get_property($val, 'email');
						$sendtoccuserid[] = $val;
					}
				}
			}
			$sendtocc = implode(',', $tmparray);

			if (dol_strlen($sendto)) {
				// Define $urlwithroot
				$urlwithouturlroot = preg_replace('/'.preg_quote(DOL_URL_ROOT, '/').'$/i', '', trim($dolibarr_main_url_root));
				$urlwithroot = $urlwithouturlroot.DOL_URL_ROOT; // This is to use external domain name found into config file
				//$urlwithroot=DOL_MAIN_URL_ROOT;					// This is to use same domain name than current

				require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';

				$langs->load("commercial");

				$reg = array();
				$fromtype = GETPOST('fromtype', 'alpha');
				if ($fromtype === 'robot') {
					$from = dol_string_nospecial($conf->global->MAIN_MAIL_EMAIL_FROM, ' ', array(",")).' <'.$conf->global->MAIN_MAIL_EMAIL_FROM.'>';
				} elseif ($fromtype === 'user') {
					$from = dol_string_nospecial($user->getFullName($langs), ' ', array(",")).' <'.$user->email.'>';
				} elseif ($fromtype === 'company') {
					$from = dol_string_nospecial($conf->global->MAIN_INFO_SOCIETE_NOM, ' ', array(",")).' <'.$conf->global->MAIN_INFO_SOCIETE_MAIL.'>';
				} elseif (preg_match('/user_aliases_(\d+)/', $fromtype, $reg)) {
					$tmp = explode(',', $user->email_aliases);
					$from = trim($tmp[($reg[1] - 1)]);
				} elseif (preg_match('/global_aliases_(\d+)/', $fromtype, $reg)) {
					$tmp = explode(',', $conf->global->MAIN_INFO_SOCIETE_MAIL_ALIASES);
					$from = trim($tmp[($reg[1] - 1)]);
				} elseif (preg_match('/senderprofile_(\d+)_(\d+)/', $fromtype, $reg)) {
					$sql = 'SELECT rowid, label, email FROM '.MAIN_DB_PREFIX.'c_email_senderprofile';
					$sql .= ' WHERE rowid = '.(int) $reg[1];
					$resql = $db->query($sql);
					$obj = $db->fetch_object($resql);
					if ($obj) {
						$from = dol_string_nospecial($obj->label, ' ', array(",")).' <'.$obj->email.'>';
					}
				} else {
					$from = dol_string_nospecial($_POST['fromname'], ' ', array(",")).' <'.$_POST['frommail'].'>';
				}

				$replyto = dol_string_nospecial($_POST['replytoname'], ' ', array(",")).' <'.$_POST['replytomail'].'>';
				$message = GETPOST('message', 'restricthtml');
				$subject = GETPOST('subject', 'restricthtml');

				// Make a change into HTML code to allow to include images from medias directory with an external reabable URL.
				// <img alt="" src="/dolibarr_dev/htdocs/viewimage.php?modulepart=medias&amp;entity=1&amp;file=image/ldestailleur_166x166.jpg" style="height:166px; width:166px" />
				// become
				// <img alt="" src="'.$urlwithroot.'viewimage.php?modulepart=medias&amp;entity=1&amp;file=image/ldestailleur_166x166.jpg" style="height:166px; width:166px" />
				$message = preg_replace('/(<img.*src=")[^\"]*viewimage\.php([^\"]*)modulepart=medias([^\"]*)file=([^\"]*)("[^\/]*\/>)/', '\1'.$urlwithroot.'/viewimage.php\2modulepart=medias\3file=\4\5', $message);

				$sendtobcc = GETPOST('sendtoccc');
				// Autocomplete the $sendtobcc
				// $autocopy can be MAIN_MAIL_AUTOCOPY_PROPOSAL_TO, MAIN_MAIL_AUTOCOPY_ORDER_TO, MAIN_MAIL_AUTOCOPY_INVOICE_TO, MAIN_MAIL_AUTOCOPY_SUPPLIER_PROPOSAL_TO...
				if (!empty($autocopy)) {
					$sendtobcc .= (empty($conf->global->$autocopy) ? '' : (($sendtobcc ? ", " : "").$conf->global->$autocopy));
				}

				$deliveryreceipt = $_POST['deliveryreceipt'];

				if ($action == 'send' || $action == 'relance') {
					$actionmsg2 = $langs->transnoentities('MailSentBy').' '.CMailFile::getValidAddress($from, 4, 0, 1).' '.$langs->transnoentities('To').' '.CMailFile::getValidAddress($sendto, 4, 0, 1);
					if ($message) {
						$actionmsg = $langs->transnoentities('MailFrom').': '.dol_escape_htmltag($from);
						$actionmsg = dol_concatdesc($actionmsg, $langs->transnoentities('MailTo').': '.dol_escape_htmltag($sendto));
						if ($sendtocc) {
							$actionmsg = dol_concatdesc($actionmsg, $langs->transnoentities('Bcc').": ".dol_escape_htmltag($sendtocc));
						}
						$actionmsg = dol_concatdesc($actionmsg, $langs->transnoentities('MailTopic').": ".$subject);
						$actionmsg = dol_concatdesc($actionmsg, $langs->transnoentities('TextUsedInTheMessageBody').":");
						$actionmsg = dol_concatdesc($actionmsg, $message);
					}
				}

				// Create form object
				include_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
				$formmail = new FormMail($db);
				$formmail->trackid = $trackid; // $trackid must be defined

				$attachedfiles = $formmail->get_attached_files();
				$filepath = $attachedfiles['paths'];
				$filename = $attachedfiles['names'];
				$mimetype = $attachedfiles['mimes'];

				// Make substitution in email content
				$substitutionarray = getCommonSubstitutionArray($langs, 0, null, $object);
				$substitutionarray['__EMAIL__'] = $sendto;
				$substitutionarray['__CHECK_READ__'] = (is_object($object) && is_object($object->thirdparty)) ? '<img src="'.DOL_MAIN_URL_ROOT.'/public/emailing/mailing-read.php?tag='.urlencode($object->thirdparty->tag).'&securitykey='.urlencode($conf->global->MAILING_EMAIL_UNSUBSCRIBE_KEY).'" width="1" height="1" style="width:1px;height:1px" border="0"/>' : '';

				$parameters = array('mode'=>'formemail');
				complete_substitutions_array($substitutionarray, $langs, $object, $parameters);

				$subject = make_substitutions($subject, $substitutionarray);
				$message = make_substitutions($message, $substitutionarray);

				if (is_object($object) && method_exists($object, 'makeSubstitution')) {
					$subject = $object->makeSubstitution($subject);
					$message = $object->makeSubstitution($message);
				}

				// Send mail (substitutionarray must be done just before this)
				if (empty($sendcontext)) {
					$sendcontext = 'standard';
				}
				$mailfile = new CMailFile($subject, $sendto, $from, $message, $filepath, $mimetype, $filename, $sendtocc, $sendtobcc, $deliveryreceipt, -1, '', '', $trackid, '', $sendcontext);

				if ($mailfile->error) {
					setEventMessages($mailfile->error, $mailfile->errors, 'errors');
					$action = 'presend';
				} else {
					$result = $mailfile->sendfile();
					if ($result) {
						// Initialisation of datas of object to call trigger
						if (is_object($object)) {
							if (empty($actiontypecode)) {
								$actiontypecode = 'AC_OTH_AUTO'; // Event insert into agenda automatically
							}

							$object->socid = $sendtosocid; // To link to a company
							$object->sendtoid = $sendtoid; // To link to contact-addresses. This is an array.
							$object->actiontypecode = $actiontypecode; // Type of event ('AC_OTH', 'AC_OTH_AUTO', 'AC_XXX'...)
							$object->actionmsg = $actionmsg; // Long text (@todo Replace this with $message, we already have details of email in dedicated properties)
							$object->actionmsg2 = $actionmsg2; // Short text ($langs->transnoentities('MailSentBy')...);

							$object->trackid = $trackid;
							$object->fk_element = $object->id;
							$object->elementtype = $object->element;
							if (is_array($attachedfiles) && count($attachedfiles) > 0) {
								$object->attachedfiles = $attachedfiles;
							}
							if (is_array($sendtouserid) && count($sendtouserid) > 0 && !empty($conf->global->MAIN_MAIL_ENABLED_USER_DEST_SELECT)) {
								$object->sendtouserid = $sendtouserid;
							}

							$object->email_msgid = $mailfile->msgid; // @todo Set msgid into $mailfile after sending
							$object->email_from = $from;
							$object->email_subject = $subject;
							$object->email_to = $sendto;
							$object->email_tocc = $sendtocc;
							$object->email_tobcc = $sendtobcc;
							$object->email_subject = $subject;
							$object->email_msgid = $mailfile->msgid;

							// Call of triggers (you should have set $triggersendname to execute trigger. $trigger_name is deprecated)
							if (!empty($triggersendname) || !empty($trigger_name)) {
								// Call trigger
								$result = $object->call_trigger(empty($triggersendname) ? $trigger_name : $triggersendname, $user);
								if ($result < 0) {
									$error++;
								}
								// End call triggers
								if ($error) {
									setEventMessages($object->error, $object->errors, 'errors');
								} else {
									$object->setStatusCommon($user, 4);
									$object->last_email_sent_date = dol_now();
									$object->update($user);
								}
							}
							// End call of triggers
						}

						// Redirect here
						// This avoid sending mail twice if going out and then back to page
						$mesg = $langs->trans('MailSuccessfulySent', $mailfile->getValidAddress($from, 2), $mailfile->getValidAddress($sendto, 2));
						setEventMessages($mesg, null, 'mesgs');

						$moreparam = '';
						if (isset($paramname2) || isset($paramval2)) {
							$moreparam .= '&'.($paramname2 ? $paramname2 : 'mid').'='.$paramval2;
						}
						header('Location: '.$_SERVER["PHP_SELF"].'?'.($paramname ? $paramname : 'id').'='.(is_object($object) ? $object->id : '').$moreparam);
						exit;
					} else {
						$langs->load("other");
						$mesg = '<div class="error">';
						if ($mailfile->error) {
							$mesg .= $langs->transnoentities('ErrorFailedToSendMail', dol_escape_htmltag($from), dol_escape_htmltag($sendto));
							$mesg .= '<br>'.$mailfile->error;
						} else {
							$mesg .= $langs->transnoentities('ErrorFailedToSendMail', dol_escape_htmltag($from), dol_escape_htmltag($sendto));
							if (!empty($conf->global->MAIN_DISABLE_ALL_MAILS)) {
								$mesg .= '<br>Feature is disabled by option MAIN_DISABLE_ALL_MAILS';
							} else {
								$mesg .= '<br>Unkown Error, please refers to your administrator';
							}
						}
						$mesg .= '</div>';

						setEventMessages($mesg, null, 'warnings');
						$action = 'presend';
					}
				}
			} else {
				$langs->load("errors");
				setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv("MailTo")), null, 'warnings');
				dol_syslog('Try to send email with no recipient defined', LOG_WARNING);
				$action = 'presend';
			}
		} else {
			$langs->load("errors");
			setEventMessages($langs->trans('ErrorFailedToReadObject', $object->element), null, 'errors');
			dol_syslog('Failed to read data of object id='.$object->id.' element='.$object->element);
			$action = 'presend';
		}
	}

	if ($action == 'lettersend') {
		$error = 0;
		$receiver = GETPOST('receiver');
		$lettercode = GETPOST('lettercode');

		$letter->fk_envelope = $object->id;
		$letter->date_creation = $letter->db->idate($now);
		$letter->status = 1;
		$letter->fk_user = $user->id;
		$letter->entity = $object->entity;
		$letter->sender_fullname = $user->firstname . ' ' . $user->lastname;
		$letter->fk_socpeople = $receiver;

		$contact->fetch($receiver);

		$letter->recipient_address = $contact->address;
		$letter->contact_fullname = $contact->firstname . ' ' . $contact->lastname;
		$letter->letter_code = $lettercode;
		$result = $letter->create($user);

		if ($result > 0) {
			$object->setStatusCommon($user, 3);
			$object->call_trigger('ENVELOPE_LETTER', $user);

			// Submit file
			if ( ! empty($conf->global->MAIN_UPLOAD_DOC)) {

				if ( ! empty($_FILES)) {
					if (is_array($_FILES['userfile']['tmp_name'])) $userfiles = $_FILES['userfile']['tmp_name'];
					else $userfiles                                           = array($_FILES['userfile']['tmp_name']);

					foreach ($userfiles as $key => $userfile) {
						if (empty($_FILES['userfile']['tmp_name'][$key])) {
							$error++;
							if ($_FILES['userfile']['error'][$key] == 1 || $_FILES['userfile']['error'][$key] == 2) {
								setEventMessages($langs->trans('ErrorFileSizeTooLarge'), null, 'errors');
							}
						}
					}

					if ( ! $error) {
						$filedir = $upload_dir . '/' . $object->element . '/' . $object->ref;
						if (!is_dir($filedir)) {
							dol_mkdir($filedir);
						}
						$LFdir = $filedir . '/linked_files';
						if (!is_dir($LFdir)) {
							dol_mkdir($LFdir);
						}

						$result = dol_add_file_process($LFdir, 0, 1, 'userfile', '', null, '', 0, $object);
					}
				}
			}
		}
		unset($action);
	}

	if ($action == 'stockTmpFile') {

		$type = GETPOST('type');

		if ( ! empty($conf->global->MAIN_UPLOAD_DOC)) {

			if ( ! empty($_FILES)) {
				if (is_array($_FILES['userfile']['tmp_name'])) $userfiles = $_FILES['userfile']['tmp_name'];
				else $userfiles                                           = array($_FILES['userfile']['tmp_name']);

				foreach ($userfiles as $key => $userfile) {
					if (empty($_FILES['userfile']['tmp_name'][$key])) {
						$error++;
						if ($_FILES['userfile']['error'][$key] == 1 || $_FILES['userfile']['error'][$key] == 2) {
							setEventMessages($langs->trans('ErrorFileSizeTooLarge'), null, 'errors');
						}
					}
				}

				if ( ! $error) {
					$filedir = $upload_dir . '/' . $object->element . '/' . $object->ref;
					if (!is_dir($filedir)) {
						dol_mkdir($filedir);
					}
					$SPDir = $filedir . '/' . strtolower($type);
					if (!is_dir($SPDir)) {
						dol_mkdir($SPDir);
					}
					$SP_sub_dir = $SPDir . '/uploaded_file';
					if (!is_dir($SP_sub_dir)) {
						dol_mkdir($SP_sub_dir);
					}
					$SP_tmp_dir = $SP_sub_dir . '/tmp';
					if (!is_dir($SP_tmp_dir)) {
						dol_mkdir($SP_tmp_dir);
					}
					$tmp_files = dol_dir_list($SP_tmp_dir);
					if (!empty($tmp_files)) {
						foreach ($tmp_files as $tmp_file) {
							unlink($tmp_file['fullname']);
						}
					}
					$result = dol_add_file_process($SP_tmp_dir, 0, 1, 'userfile', '', null, '', 0, $object);
				}
			}
		}
	}

	if ($action == 'addAcknowledgementReceipt') {

		$filedir = $upload_dir . '/' . $object->element . '/' . $object->ref . '/acknowledgementreceipt/uploaded_file';
		$tmp_filedir = $filedir . '/tmp';
		$tmp_files = dol_dir_list($tmp_filedir);

		if (!empty($tmp_files)) {
			foreach ($tmp_files as $tmp_file) {
				$result = rename($tmp_file['fullname'], $filedir.'/'. $tmp_file['name']);

				// Submit file
				if ($result > 0) {
					// Presend form
					$modelmail = 'envelope';
					$defaulttopic = 'InformationMessage';
					$diroutput = $upload_dir . '/' . $object->element;
					$trackid = 'envelope'.$object->id;


					$ref = dol_sanitizeFileName($object->ref);
					include_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
					$fileparams = dol_most_recent_file($diroutput.'/'.$ref, '');
					$file = $fileparams['fullname'];

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
					$object->call_trigger('ENVELOPE_ACKNOWLEDGEMENT_RECEIPT', $user);
					$object->setStatusCommon($user, 6);

					if ($forcebuilddoc)    // If there is no default value for supplier invoice, we do not generate file, even if modelpdf was set by a manual generation
					{

						if (method_exists($object, 'generateDocument'))
						{
							$result = $object->generateDocument('deimos', $langs, $hidedetails, $hidedesc, $hideref);

							if ($result < 0) {
								dol_print_error($db, $object->error, $object->errors);
								exit();
							}
							$fileparams = dol_most_recent_file($diroutput.'/'.$ref, preg_quote($ref, '/').'[^\-]+');
							$file = $fileparams['fullname'];
						}
					}
				}
			}
		} else {
			setEventMessages($langs->trans('NoFileLinked', $langs->transnoentitiesnoconv('NoFileLinked')), null, 'errors');
		}
	}

	if ($action == 'addSendingProof') {

		$filedir = $upload_dir . '/' . $object->element . '/' . $object->ref . '/sendingproof/uploaded_file';
		$tmp_filedir = $filedir . '/tmp';
		$tmp_files = dol_dir_list($tmp_filedir);

		if (!empty($tmp_files)) {
			foreach ($tmp_files as $tmp_file) {
				$result = rename($tmp_file['fullname'], $filedir.'/'. $tmp_file['name']);

				// Submit file
				if ($result > 0) {
					// Presend form
					$modelmail = 'envelope';
					$defaulttopic = 'InformationMessage';
					$diroutput = $upload_dir . '/' . $object->element;
					$trackid = 'envelope'.$object->id;


					$ref = dol_sanitizeFileName($object->ref);
					include_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
					$fileparams = dol_most_recent_file($diroutput.'/'.$ref, '');
					$file = $fileparams['fullname'];

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
					$object->call_trigger('ENVELOPE_SENDING_PROOF', $user);

					if ($forcebuilddoc)    // If there is no default value for supplier invoice, we do not generate file, even if modelpdf was set by a manual generation
					{

						if (method_exists($object, 'generateDocument'))
						{
							$result = $object->generateDocument('ares', $langs, $hidedetails, $hidedesc, $hideref);

							if ($result < 0) {
								dol_print_error($db, $object->error, $object->errors);
								exit();
							}
							$fileparams = dol_most_recent_file($diroutput.'/'.$ref, preg_quote($ref, '/').'[^\-]+');
							$file = $fileparams['fullname'];
						}
					}
				}
			}
		} else {
			setEventMessages($langs->trans('NoFileLinked', $langs->transnoentitiesnoconv('NoFileLinked')), null, 'errors');
		}
	}

	// Action clone object
	if ($action == 'confirm_clone' && $confirm == 'yes') {
		if ($object->status > 0) {
			$object->status = 0;
		}
		$object->ref = $refEnvelopeMod->getNextValue($object);
		$result = $object->create($user);

		if ($result > 0) {
			header("Location: " . $_SERVER["PHP_SELF"] . '?id=' . $result);
			exit;
		}
	}
}

/*
 * View
 *
 * Put here all code to build page
 */

$form          = new Form($db);
$formother     = new FormOther($db);
$formfile      = new FormFile($db);
$formproject   = new FormProjets($db);
$lettersending = $letter->fetchAll('', '', 0, 0, array('customsql' => ' fk_envelope =' . $object->id));
if (is_array($lettersending)) {
	$lettersending = end($lettersending);
} else {
	$lettersending = new LetterSending($db);
}
$signatory = $signatory->fetchSignatory('E_SENDER', $id);
if (is_array($signatory)) $signatory = array_shift($signatory);
$title        = $langs->trans("Envelope");
$title_create = $langs->trans("NewEnvelope");
$title_edit   = $langs->trans("ModifyEnvelope");
$help_url     = '';
$morejs   = array("/doliletter/js/signature-pad.min.js", "/doliletter/js/doliletter.js.php");
$morecss  = array("/doliletter/css/doliletter.css");

llxHeader('', $title, $help_url, '', '', '', $morejs, $morecss);

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
	unset($object->fields['fk_contact']);
	unset($object->fields['fk_project']);

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
	$events[1] = array('method' => 'getContacts', 'url' => dol_buildpath('/core/ajax/contacts.php?showempty=1', 1), 'htmlname' => 'fk_contact', 'params' => array('add-customer-contact' => 'disabled'));
	print $form->select_company(GETPOST('fromtype') == 'thirdparty' ? GETPOST('fromid') : GETPOST('fk_soc'), 'fk_soc', '', 'SelectThirdParty', 1, 0, $events, 0, 'minwidth300');
	print ' <a href="'.DOL_URL_ROOT.'/societe/card.php?action=create&backtopage='.urlencode($_SERVER["PHP_SELF"].'?action=create').'" target="_blank"><span class="fa fa-plus-circle valignmiddle paddingleft" title="'.$langs->trans("AddThirdParty").'"></span></a>';
	print '</td></tr>';

	//Contact -- Contact
	print '<tr><td class="fieldrequired">'.$langs->trans("Contact").'</td><td>';
	print $form->selectcontacts(GETPOST('fk_soc', 'int'), GETPOST('fk_contact'), 'fk_contact', 1, '', '', 0, 'quatrevingtpercent', false, 0, array(), false, '', 'fk_contact');
	print '</td></tr>';

	//Project -- Projet
	print '<tr class="oddeven"><td><label for="Project">' . $langs->trans("ProjectLinked") . '</label></td><td>';
	$numprojet = $formproject->select_projects(GETPOST('fromid'),  GETPOST('fk_project'), 'fk_project', 0, 0, 1, 0, 0, 0, 0, '', 0, 0, 'minwidth300');
	print ' <a href="' . DOL_URL_ROOT . '/projet/card.php?&action=create&status=1&backtopage=' . urlencode($_SERVER["PHP_SELF"] . '?action=create') . '"><span class="fa fa-plus-circle valignmiddle" title="' . $langs->trans("AddProject") . '"></span></a>';
	print '</td></tr>';

	//Content -- Contenu
	print '<tr class=""><td><label for="content">'.$langs->trans("Content").'</label></td><td>';
	$doleditor = new DolEditor('content', GETPOST('content'), '', 150, 'dolibarr_details', '', false, true, $conf->global->FCKEDITOR_ENABLE_SOCIETE, ROWS_3, '90%');
	$doleditor->Create();
	print '</td></tr>';

//	//PublicNote -- Note publique
//	print '<tr class="content_field"><td><label for="note_public">'.$langs->trans("PublicNote").'</label></td><td>';
//	$doleditor = new DolEditor('note_public', GETPOST('note_public'), '', 90, 'dolibarr_details', '', false, true, $conf->global->FCKEDITOR_ENABLE_SOCIETE, ROWS_3, '90%');
//	$doleditor->Create();
//	print '</td></tr>';
//
//	//PrivateNote -- Note privée
//	print '<tr class="content_field"><td><label for="note_private">'.$langs->trans("PrivateNote").'</label></td><td>';
//	$doleditor = new DolEditor('note_private', GETPOST('note_private'), '', 90, 'dolibarr_details', '', false, true, $conf->global->FCKEDITOR_ENABLE_SOCIETE, ROWS_3, '90%');
//	$doleditor->Create();
//	print '</td></tr>';

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
}

// Part to edit record
if (($id || $ref) && $action == 'edit' ||$action == 'confirm_setInProgress') {

	print load_fiche_titre($langs->trans("EditEnvelope"), '', 'object_'.$object->picto);

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
	unset($object->fields['fk_contact']);
	unset($object->fields['fk_project']);

	// Common attributes
//	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_add.tpl.php';

	//Label - Libellé
	print '<tr><td class="fieldrequired">'.$langs->trans("Label").'</td><td>';
	print '<input name="label" id="label" value="'. $object->label .'" >';
	print '</td></tr>';

	//Society -- Société
	print '<tr><td class="fieldrequired">'.$langs->trans("Society").'</td><td>';
	$events = array();
	$events[1] = array('method' => 'getContacts', 'url' => dol_buildpath('/core/ajax/contacts.php?showempty=1', 1), 'htmlname' => 'contact', 'params' => array('add-customer-contact' => 'disabled'));
	print $form->select_company($object->fk_soc, 'fk_soc', '', 'SelectThirdParty', 1, 0, $events, 0, 'minwidth300');
	print ' <a href="'.DOL_URL_ROOT.'/societe/card.php?action=create&backtopage='.urlencode($_SERVER["PHP_SELF"].'?action=create').'" target="_blank"><span class="fa fa-plus-circle valignmiddle paddingleft" title="'.$langs->trans("AddThirdParty").'"></span></a>';
	print '</td></tr>';

	//Contact -- Contact
	print '<tr><td class="fieldrequired">'.$langs->trans("Contact").'</td><td>';
	print $form->selectcontacts(GETPOST('fk_soc', 'int'), $object->fk_contact, 'fk_contact', 1, '', '', 0, 'quatrevingtpercent', false, 0, array(), false, '', 'fk_contact');
	print '</td></tr>';

	//Contact -- Contact
	print '<tr class="oddeven"><td><label for="ACCProject">' . $langs->trans("ProjectLinked") . '</label></td><td>';
	$numprojet = $formproject->select_projects(0,  $object->fk_project, 'fk_project', 0, 0, 1, 0, 0, 0, 0, '', 0, 0, 'minwidth300');
	print ' <a href="' . DOL_URL_ROOT . '/projet/card.php?&action=create&status=1&backtopage=' . urlencode($_SERVER["PHP_SELF"] . '?action=create') . '"><span class="fa fa-plus-circle valignmiddle" title="' . $langs->trans("AddProject") . '"></span></a>';
	print '</td></tr>';

	//Content -- Contenu
	print '<tr class="content_field"><td><label for="content">'.$langs->trans("Content").'</label></td><td>';
	$doleditor = new DolEditor('content', $object->content, '', 90, 'dolibarr_details', '', false, true, $conf->global->FCKEDITOR_ENABLE_SOCIETE, ROWS_3, '90%');
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
if ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'confirm_setInProgress' && $action != 'create' && $action != 'letterpresend'))) {
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


	// SetLocked confirmation
	if (($action == 'setLocked' && (empty($conf->use_javascript_ajax) || ! empty($conf->dol_use_jmobile)))		// Output when action = clone if jmobile or no js
		|| ( ! empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile))) {

		$img = '<img alt="" src="./../../custom/doliletter/img/lock_envelope.png" />';

		$formquestion = array(
			array('type' => 'other', 'name' => 'lock_validation', 'label' => '<span class="">' . $langs->trans("ConfirmLockEnvelope", $object->ref, dol_print_date($signatory->signature_date), $signatory->firstname . ' ' . $signatory->lastname) . '</span>'),
			array('type' => 'other', 'name' => 'OK', 'label' => '', 'value' => $img, 'moreattr' => 'readonly'),
		);

		$formconfirm .= $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('LockEnvelope'),'', 'confirm_setLocked', $formquestion, 'yes', 'actionButtonLock', 400, 550);
	}

	// Upload sending proof
	if (($action == 'uploadSendingProof' && (empty($conf->use_javascript_ajax) || ! empty($conf->dol_use_jmobile)))		// Output when action = clone if jmobile or no js
		|| ( ! empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile))) {							// Always output when not jmobile nor js

		$img = '<img alt="" src="./../../custom/doliletter/img/sending_proof_confirmation.png" />';

		$formquestion = array(
			array('type' => 'other', 'name' => 'sending_proof_confirmation', 'label' => '<span class="">' .   $langs->trans('ConfirmUploadSendingProof', $object->ref) . '</span>'),
			array('type' => 'other', 'name' => 'OK', 'label' => '', 'value' => $img, 'moreattr' => 'readonly'),
		);

		$formconfirm .= $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('UploadSendingProof'),'', 'addSendingProof', $formquestion, 'yes', 'actionButtonSendingProof', 370, 450, 1);
	}

	// Upload acknowledgement receipt
	if (($action == 'uploadAcknowledgementReceipt' && (empty($conf->use_javascript_ajax) || ! empty($conf->dol_use_jmobile)))		// Output when action = clone if jmobile or no js
		|| ( ! empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile))) {							// Always output when not jmobile nor js

		$img = '<img alt="" src="./../../custom/doliletter/img/acknowledgement_receipt_confirmation.png" />';

		$formquestion = array(
			array('type' => 'other', 'name' => 'acknowledgement_receipt_confirmation', 'label' => '<span class="">' .   $langs->trans('ConfirmUploadAcknowledgementReceipt', $object->ref) . '</span>'),
			array('type' => 'other', 'name' => 'OK', 'label' => '', 'value' => $img, 'moreattr' => 'readonly'),
		);

		$formconfirm .= $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('UploadAcknowledgementReceipt'),'', 'addAcknowledgementReceipt', $formquestion, 'yes', 'actionButtonAcknowledgementReceipt', 385, 470, 1);
	}

	// setInProgress confirmation
	if (($action == 'setInProgress' && (empty($conf->use_javascript_ajax) || ! empty($conf->dol_use_jmobile)))		// Output when action = clone if jmobile or no js
		|| ( ! empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile))) {							// Always output when not jmobile nor js
		$formconfirm .= $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('ReOpenEnvelope'), $langs->trans('ConfirmReOpenEnvelope', $object->ref), 'confirm_setInProgress', '', 'yes', 'actionButtonInProgress', 350, 600);
	}

	// Clone confirmation
	if (($action == 'clone' && (empty($conf->use_javascript_ajax) || ! empty($conf->dol_use_jmobile)))		// Output when action = clone if jmobile or no js
		|| ( ! empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile))) {							// Always output when not jmobile nor js
		// Define confirmation messages
		$formquestionclone = array(
			'text' => $langs->trans("CloneEnvelope", $object->ref),
		);

		$formconfirm .= $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('ToClone'), $langs->trans('ConfirmCloneEnvelope', $object->ref), 'confirm_clone', $formquestionclone, 'yes', 'actionButtonClone', 350, 600);
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

	$contact->fetch($object->fk_contact);
	// Object card
	// ------------------------------------------------------------
	$linkback = '<a href="'.dol_buildpath('/doliletter/envelope_list.php', 1).'?restore_lastsearch_values=1'.(!empty($socid) ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';
	// Project
	$project->fetch($object->fk_project);
	$morehtmlref = '<div class="refidno">';

	$morehtmlref .=  '<tr><td class="titlefield">';
	$morehtmlref .=  $langs->trans("ThirdParty");
	$morehtmlref .= ' : ';
	$morehtmlref .=  '</td>';
	$morehtmlref .=  '<td>';
	$morehtmlref .=  $thirdparty->getNomUrl(1);
	$morehtmlref .=  '</td></tr><br>';

	$morehtmlref .=  '<tr><td class="titlefield">';
	$morehtmlref .=  $langs->trans("Contact");
	$morehtmlref .= ' : ';
	$morehtmlref .=  '</td></tr>';
	$morehtmlref .=  '<tr><td>';
	$morehtmlref .=  $contact->getNomUrl(1);
	$morehtmlref .=  '</td><br>';
	$morehtmlref .= $langs->trans('Project') . ' : ' . $project->getNomUrl(1);
	$morehtmlref .= '</tr>';
	$morehtmlref .=  '</td><br>';

	if ($object->status > 1) {
		$signatory = $signatory->fetchSignatory('E_SENDER', $id);
		$signatory = array_shift($signatory);
		$signature_url = dol_buildpath('/custom/doliletter/public/signature/add_signature?track_id=' . $signatory->signature_url . '&type=envelope', 1);
	}

	if ($object->status == 3 || $object->status == 6) {
		$morehtmlref .=  '<tr><td>';
		$morehtmlref .= $langs->trans('RegisteredMailCode') . ' : ' . $lettersending->letter_code;
		$morehtmlref .= '</td></tr>';
	}

	if ($object->status == 4 || $object->status == 5) {
		$morehtmlref .=  '<tr><td>';
		$morehtmlref .=  $langs->trans('SignatureLink') . ' : ' . '<a href="'.$signature_url.'">' . $signature_url . '</a>';
		$morehtmlref .= '</td></tr>';
	}


	$morehtmlref .= '</div>';

	dol_banner_tab($object, 'ref', $linkback, 0, 'ref', 'ref', $morehtmlref, '', 0, '' );

	print '<div class="fichecenter">';
	print '<div class="">';
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border centpercent tableforfield">'."\n";

	print '<tr><td class="titlefield">';
	print $langs->trans("Content");
	print '</td>';
	print '<td>';
	print '<div class="longmessagecut" style="min-height: 150px">';
	print dol_htmlentitiesbr($object->content); //wrap -> middle?
	print '</div>';
	print '</td></tr>';

	//unused display of information
	unset($object->fields['fk_soc']);
	unset($object->fields['fk_contact']);
	unset($object->fields['fk_project']);
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
		if ($permissiontoadd) {
			$button_edit = '<a class="butAction" id="actionButtonEdit" href="' . $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit&token='.newToken().'"' .'>' . $langs->trans("Modify"). '</a>' . "\n";
			$button_edit_with_confirm = '<span class="butAction" id="actionButtonInProgress">' . $langs->trans("Modify"). '</span>' . "\n";
			$button_edit_disabled = '<a class="butActionRefused classfortooltip"  title="'. $langs->trans('CantEditAlreadySigned').'"'.'>' . $langs->trans("Modify") . '</a>' . "\n";
			print ($object->status == 0 ?  $button_edit : ($object->status == 1 ? $button_edit_with_confirm : $button_edit_disabled));
			print '<a class="'. ($object->status == 0 ? 'butAction" id="actionButtonSign" href="' . DOL_URL_ROOT . '/custom/doliletter/envelope_signature.php'.'?id='.$object->id.'&mode=init&token='.newToken().'"' : 'butActionRefused classfortooltip" title="'. $langs->trans('AlreadySigned').'"')  .' >' . $langs->trans("Sign") . '</a>' . "\n";
			print '<span class="' . ($object->status == 1 ? 'butAction"  id="actionButtonLock"' : 'butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans("EnvelopeMustBeSigned")) . '"') . '>' . $langs->trans("Lock") . '</span>';
			print '<a class="'. ($object->status == 2 ? 'butAction" id="actionButtonSendMail" href="' . $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=presend&mode=init&model='.$modelselected.'&token='.newToken().'"'  : 'butActionRefused classfortooltip" title="'. $langs->trans('MustBeSignedBeforeSending').'"') . ' >' . $langs->trans("SendMail") . '</a>' . "\n";
			print '<a class="'. ($object->status == 2 ? 'butAction" id="actionButtonSendLetter" href="' . $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=letterpresend&mode=init&model='.$modelselected.'&token='.newToken().'"' : 'butActionRefused classfortooltip" title="'. $langs->trans('MustBeSignedBeforeSending').'"').' >' . $langs->trans("SendLetter") . '</a>' . "\n";
			print '<a class="'. ($object->status == 3 ? 'butAction" id="actionSendingProof" href="' . $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=uploadSendingProof&token='.newToken() : 'butActionRefused classfortooltip" title="'. $langs->trans('MustBeSentBeforeSendingProof').'"').'">' . $langs->trans("UploadSendingProof") . '</a>' . "\n";
			print '<a class="'. ($object->status == 3 ? 'butAction" id="actionButtonUploadReceiptAcknowledgement" href="' . $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=uploadAcknowledgementReceipt&token='.newToken() : 'butActionRefused classfortooltip" title="'. $langs->trans('MustBeSentBeforeAcknowledgementReceipt').'"').'">' . $langs->trans("UploadAcknowledgementReceipt") . '</a>' . "\n";
			print '<span class="butAction" id="actionButtonClone" title="" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=clone' . '">' . $langs->trans("ToClone") . '</span>';
		} else {
			print '<a class="butActionRefused classfortooltip" href="#" title="' . dol_escape_htmltag($langs->trans("NotEnoughPermissions")) . '">' . $langs->trans('Modify') . '</a>' . "\n";
			print '<a class="butActionRefused classfortooltip" href="#" title="' . dol_escape_htmltag($langs->trans("NotEnoughPermissions")) . '">' . $langs->trans('Sign') . '</a>' . "\n";
			print '<a class="butActionRefused classfortooltip" href="#" title="' . dol_escape_htmltag($langs->trans("NotEnoughPermissions")) . '">' . $langs->trans('Lock') . '</a>' . "\n";
			print '<a class="butActionRefused classfortooltip" href="#" title="' . dol_escape_htmltag($langs->trans("NotEnoughPermissions")) . '">' . $langs->trans('SendMail') . '</a>' . "\n";
			print '<a class="butActionRefused classfortooltip" href="#" title="' . dol_escape_htmltag($langs->trans("NotEnoughPermissions")) . '">' . $langs->trans('SendLetter') . '</a>' . "\n";
		}
		if ($permissiontodelete) {
			print '<a class="butActionDelete" id="actionButtonSendMail" href="' . $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=delete&token='.newToken().'">' . $langs->trans("Delete") . '</a>' . "\n";
		} else {
			print '<a class="butActionRefused classfortooltip" href="#" title="' . dol_escape_htmltag($langs->trans("NotEnoughPermissions")) . '">' . $langs->trans('Delete') . '</a>' . "\n";
		}
	}
	print '</div>'."\n";


	if ($action == 'uploadAcknowledgementReceipt')
	{
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"] . '?id=' . $id .'" enctype="multipart/form-data">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="addAcknowledgementReceipt">';

		print load_fiche_titre($langs->trans('UploadAcknowledgementReceipt'), null, null);

		// Disponibilité des plans
		print '<tr>';
		print '<td class="titlefield">' . $form->editfieldkey($langs->trans("AddFile"), 'acknowledgementReceipt', '', $object, 0) . '</td>';
		print '<td>';
		print '<input hidden class="from-type" value="acknowledgementReceipt" />';
		print '<input class="flat" type="file" name="userfile[]" id="acknowledgementReceipt" />';

		print '</td></tr>';
//		print '<input class="butAction" type="submit" name="addAcknowledgementReceipt" id="addAcknowledgementReceipt" value="'. $langs->trans('Send').'"/>';
		//avec cbox de validation mais ça perd le $_FILES
		print '<span class="butAction" id="actionButtonAcknowledgementReceipt">' . $langs->trans("Send") . '</span>';

		print '</form>';
	}

	if ($action == 'uploadSendingProof')
	{
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"] . '?id=' . $id .'" enctype="multipart/form-data">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="addSendingProof">';

		print load_fiche_titre($langs->trans('UploadSendingProof'), null, null);

		print '<tr>';
		print '<td class="titlefield">' . $form->editfieldkey($langs->trans("SendingProof"), 'SendingProof', '', $object, 0) . '</td>';
		print '<td>';
		print '<input hidden class="from-type" value="sendingProof" />';
		print '<input class="flat" type="file" name="userfile[]" id="sendingProof" />';
		print '</td></tr>';
//		print '<input class="butAction" type="" name="actionButtonSendingProof" id="actionButtonSendingProof" value="'. $langs->trans('Send').'"/>';
		//avec cbox de validation mais ça perd le $_FILES
		print '<span class="butAction" id="actionButtonSendingProof">' . $langs->trans("Send") . '</span>';

		print '</td></tr>';

		print '</form>';
	}

	if ($action != 'presend' && $action != 'letterpresend' && $action != 'uploadAcknowledgementReceipt' && $action != 'uploadSendingProof') {
		print '<div class="fichecenter"><div class="fichehalfleft">';
		print '<a name="builddoc"></a>'; // ancre

		$includedocgeneration = 1;

		// Documents
		if ($includedocgeneration) {
			$objref = dol_sanitizeFileName($object->ref);
			$relativepath = $objref.'/'.$objref.'.pdf';
			$filedir = $conf->doliletter->dir_output.'/'.$object->element.'/'.$objref;
			$generated_files = dol_dir_list($filedir.'/', 'files');
			$document_generated = 0;
			foreach ($generated_files as $generated_file) {
				if (!preg_match('/specimen/', $generated_file['name'])) {
					$document_generated += 1;
				}
			}
			$urlsource = $_SERVER["PHP_SELF"]."?id=".$object->id;
			$genallowed = $user->rights->doliletter->envelope->read; // If you can read, you can build the PDF to read content
			$delallowed = $user->rights->doliletter->envelope->write; // If you can create/edit, you can remove a file on card
			print dolilettershowdocuments('doliletter:Envelope', $object->element.'/'.$objref, $filedir, $urlsource, $genallowed, 0, $conf->global->DOLILETTER_ENVELOPE_ADDON_PDF, 1, 0, 0, '', 0, '', '', '', $langs->defaultlang, $object->status < 3 ? ( $document_generated > 0 ? 0 : 1) : 0, $document_generated > 0 ? $langs->trans('DocumentHasAlreadyBeenGenerated') : $langs->trans('EnvelopeMustBeLockedToGenerateDocument'));
		}

		if ($object->status == 3 || $object->status == 6) {
			print '<br>';
			$linked_files_files = count(dol_dir_list($filedir.'/linked_files'));
			print dolilettershowdocuments('doliletter', $object->element.'/'.$objref.'/linked_files', $filedir.'/linked_files', $urlsource, 0, 0, $conf->global->DOLILETTER_ACKNOWLEDGEMENTRECEIPT_ADDON_PDF, 1, 0, 0, $langs->trans('SendingLinkedFiles'), 0, '', '', '', $langs->defaultlang, $linked_files_files > 0 ? 0 : 1, $generated_files > 0 ? $langs->trans('DocumentHasAlreadyBeenGenerated') : $langs->trans('EnvelopeMustBeLockedToGenerateDocument'));
		}

		if ($object->status == 3 || $object->status == 5 || $object->status == 6) {
			print '<br>';
			$sending_proof_files = count(dol_dir_list($filedir.'/sendingproof'));
			print dolilettershowdocuments('doliletter', $object->element.'/'.$objref.'/sendingproof', $filedir.'/sendingproof', $urlsource, 0, 0, $conf->global->DOLILETTER_ACKNOWLEDGEMENTRECEIPT_ADDON_PDF, 1, 0, 0, $langs->trans('SendingProof'), 0, '', '', '', $langs->defaultlang, $sending_proof_files > 0 ? 0 : 1, $generated_files > 0 ? $langs->trans('DocumentHasAlreadyBeenGenerated') : $langs->trans('EnvelopeMustBeLockedToGenerateDocument'));
		}

		if ($object->status >= 5) {
			$acknowledgement_receipt_files = count(dol_dir_list($filedir.'/acknowledgementreceipt'));
			print dolilettershowdocuments('doliletter:AcknowledgementReceipt', $object->element.'/'.$objref.'/acknowledgementreceipt', $filedir.'/acknowledgementreceipt', $urlsource, $permissiontoadd, 0, $conf->global->DOLILETTER_ACKNOWLEDGEMENTRECEIPT_ADDON_PDF, 1, 0, 0, $langs->trans('AcknowledgementReceipt'), 0, '', '', '', $langs->defaultlang, $acknowledgement_receipt_files > 0 ? 0 : 1, $generated_files > 0 ? $langs->trans('DocumentHasAlreadyBeenGenerated') : $langs->trans('EnvelopeMustBeLockedToGenerateDocument'));
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
			$outputlangs->loadLangs(array('doliletter'));
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

		$receiver = $signatory->fetchSignatory('E_RECEIVER', $id);
		$receiver = array_shift($receiver);
		// Make substitution in email content
		$substitutionarray = getCommonSubstitutionArray($outputlangs, 0, $arrayoffamiliestoexclude, $object);
		$substitutionarray['__CHECK_READ__'] = (is_object($object) && is_object($object->thirdparty)) ? '<img src="'.DOL_MAIN_URL_ROOT.'/public/emailing/mailing-read.php?tag='.$object->thirdparty->tag.'&securitykey='.urlencode($conf->global->MAILING_EMAIL_UNSUBSCRIBE_KEY).'" width="1" height="1" style="width:1px;height:1px" border="0"/>' : '';
		$substitutionarray['__PERSONALIZED__'] = ''; // deprecated
		$substitutionarray['__CONTACTCIVNAME__'] = '';
		$substitutionarray['__SIGNATURE_LINK__'] = dol_buildpath('/custom/doliletter/public/signature/add_signature.php?track_id=' . $receiver->signature_url  . '&type=' . $object->element, 3);
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


} else if ($action == 'letterpresend') {

	// Build document if it not exists
	$diroutput = $upload_dir . '/' . $object->element;
	$outputlangs = $langs;

	$fileparams = dol_most_recent_file($diroutput.'/'.$object->ref, '');
	$file = $fileparams['fullname'];

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

	$res = $object->fetch_optionals();

	$head = envelopePrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans("EnvelopeSending"), -1, "doliletter@doliletter");
	print load_fiche_titre($langs->trans('SendLetter'), '', "doliletter32px@doliletter");

	print dol_get_fiche_head(array(), '');

	$contact_list= array();

	print '<form method="POST" enctype="multipart/form-data" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="lettersend">';
	print '<input type="hidden" name="id" value="'.$object->id.'">';
	//Combobox multiple selection for contacts saved as receivers


	print '<table>';
	print '<tr class="minwidth400"><td>'.$langs->trans("Receivers").'</td><td class="minwidth400">';
	print '<input hidden name="receiver[]" id="receiver[]" value="' . $linked_contact->id .'">';
	print $linked_contact->getNomUrl(1);
//	print $form->selectcontacts($object->fk_soc, $object->fk_contact, 'receiver[]', 0, '', '', 0, 'quatrevingtpercent', false, 0, array(), false, 'multiple', 'receiver');
	print '</td></tr>';
	print '<tr class="minwidth400"><td>'.$langs->trans("LetterCode").'</td><td class="minwidth400">';
	print '<input name="lettercode">';
	print '</td></tr>';
	// Preuve de dépôt
	print '<tr>';
	print '<td class="titlefield">' . $form->editfieldkey($langs->trans("LinkedFiles"), 'linkedFiles', '', $object, 0) . '</td>';
	print '<td>';
	print '<input class="flat" type="file" name="userfile[]" id="LinkedFiles" />';
	print '</td></tr>';

	print '</table>'."<br>";


	//button save -> to lettersend action
	print '<input type="submit" class="button" name="lettersend" value="'.dol_escape_htmltag($langs->trans("Send")).'">';
	print '&nbsp; ';
	print '<input type="'.($backtopage ? "submit" : "button").'" class="button button-cancel" name="cancel" value="'.dol_escape_htmltag($langs->trans("Cancel")).'"'.($backtopage ? '' : ' onclick="javascript:history.go(-1)"').'>'; // Cancel for create does not post form if we don't know the backtopage

	print '</div>';

	print '</form>';
}


// End of page
llxFooter();
$db->close();
