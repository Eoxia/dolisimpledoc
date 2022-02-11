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
 *   	\file       envelope_signature.php
 *		\ingroup    doliletter
 *		\brief      Page to add/edit/view envelope_signature
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

require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';

require_once __DIR__ . '/class/envelope.class.php';
require_once __DIR__ . '/lib/doliletter_envelope.lib.php';

global $db, $langs, $user, $conf;

// Load translation files required by the page
$langs->loadLangs(array("doliletter@doliletter", "other"));

// Get parameters
$id                  = GETPOST('id', 'int');
$action              = GETPOST('action', 'aZ09');
$contextpage         = GETPOST('contextpage', 'aZ') ?GETPOST('contextpage', 'aZ') : 'envelope_signature'; // To manage different context of search
$backtopage          = GETPOST('backtopage', 'alpha');

// Initialize technical objects
$object    = new Envelope($db);
$signatory = new EnvelopeSignature($db);
$usertmp   = new User($db);
$contact   = new Contact($db);
$form      = new Form($db);

$object->fetch($id);

$hookmanager->initHooks(array('envelopesignature', 'globalcard')); // Note that conf->hooks_modules contains array

$permissiontoread = $user->rights->doliletter->envelope->read;
$permissiontoadd = $user->rights->doliletter->envelope->write; // Used by the include of actions_addupdatedelete.inc.php and actions_lineupdown.inc.php
$permissiontodelete = $user->rights->doliletter->envelope->delete || ($permissiontoadd && isset($object->status));
$permissionnote = $user->rights->doliletter->envelope->write; // Used by the include of actions_setnotes.inc.php
$permissiondellink = $user->rights->envelope->letter->write; // Used by the include of actions_dellink.inc.php
$upload_dir = $conf->doliletter->multidir_output[$conf->entity];

// Security check (enable the most restrictive one)
if ($user->socid > 0) accessforbidden();
if ($user->socid > 0) $socid = $user->socid;
if (empty($conf->doliletter->enabled)) accessforbidden();
if (!$permissiontoread) accessforbidden();

$upload_dir = $conf->doliletter->multidir_output[$conf->entity ? $conf->entity : $conf->entity]."/envelope/".get_exdir(0, 0, 0, 1, $object);

/*
/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($backtopage) || ($cancel && empty($id))) {
	if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
		$backtopage = dol_buildpath('/doliletter/envelope_signature.php', 1).'?id='.($object->id > 0 ? $object->id : '__ID__');
	}
}

// Action to add record
if ($action == 'addAttendant') {

	$object->fetch($id);
	$extintervenant_ids  = GETPOST('ext_intervenants');

	//Check email of intervenants
	if (!empty($extintervenant_ids) && $extintervenant_ids > 0) {
		foreach ($extintervenant_ids as $extintervenant_id) {
			$contact->fetch($extintervenant_id);
			if (!dol_strlen($contact->email)) {
				setEventMessages($langs->trans('ErrorNoEmailForExtIntervenant', $langs->transnoentitiesnoconv('ExtIntervenant')), null, 'errors');
				$error++;
			}
		}
	}

	if (!$error) {
		$result = $signatory->setSignatory($object->id,'socpeople', $extintervenant_ids, 'PP_EXT_SOCIETY_INTERVENANTS', 1);
		if ($result > 0) {
			foreach ($extintervenant_ids as $extintervenant_id) {
				$contact->fetch($extintervenant_id);
				setEventMessages($langs->trans('AddAttendantMessage') . ' ' . $contact->firstname . ' ' . $contact->lastname, array());
			}
			// Creation attendant OK
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

// Action to add record
if ($action == 'addSignature') {

	$request_body = file_get_contents('php://input');

	$role = GETPOST('role');
	$signatory->setSignatory($object->id,'user', array($user->id), $role);

	$signatory->fetch($signatory->id);
	$signatory->signature = $request_body;
	$signatory->signature_date = dol_now('tzuser');

	if (!$error) {

		$result = $signatory->update($user, false);

		if ($result > 0) {
			// Creation signature OK
			$signatory->setSigned($user, false);
			$urltogo = str_replace('__ID__', $result, $backtopage);
			$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urltogo); // New method to autoselect project after a New on another form object creation
			header("Location: " . $urltogo);
			if ($role == 'E_SENDER') {
				$object->setStatusCommon($user, 1);
			}
			$object->call_trigger('ENVELOPE_SIGN', $user);
			exit;
		}
		else
		{
			// Creation signature KO
			if (!empty($signatory->errors)) setEventMessages(null, $signatory->errors, 'errors');
			else  setEventMessages($signatory->error, null, 'errors');
		}
	}
}

// Action to set status STATUS_ABSENT
if ($action == 'setAbsent') {
	$signatoryID = GETPOST('signatoryID');

	$signatory->fetch($signatoryID);

	if (!$error) {
		$result = $signatory->setAbsent($user, false);
		if ($result > 0) {
			// set absent OK
			setEventMessages($langs->trans('Attendant').' '.$signatory->firstname.' '.$signatory->lastname.' '.$langs->trans('SetAbsentAttendant'),array());
			$urltogo = str_replace('__ID__', $result, $backtopage);
			$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urltogo); // New method to autoselect project after a New on another form object creation
			header("Location: " . $urltogo);
			exit;
		}
		else
		{
			// set absent KO
			if (!empty($signatory->errors)) setEventMessages(null, $signatory->errors, 'errors');
			else  setEventMessages($signatory->error, null, 'errors');
		}
	}
}

// Action to send Email
if ($action == 'send') {
	$signatoryID = GETPOST('signatoryID');
	$signatory->fetch($signatoryID);

	if (!$error) {
		$langs->load('mails');
		$sendto = $signatory->email;

		if (dol_strlen($sendto) && (!empty($conf->global->MAIN_MAIL_EMAIL_FROM))) {
			require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';

			$from = $conf->global->MAIN_MAIL_EMAIL_FROM;
			$url = dol_buildpath('/custom/doliletter/public/signature/add_signature.php?track_id='.$signatory->signature_url, 3);

			$message = $langs->trans('SignatureEmailMessage') . ' ' . $url;
			$subject = $langs->trans('SignatureEmailSubject') . ' ' . $object->ref;

			// Create form object
			// Send mail (substitutionarray must be done just before this)
			$mailfile = new CMailFile($subject, $sendto, $from, $message, array(), array(), array(), "", "", 0, -1, '', '', '', '', 'mail');

			if ($mailfile->error) {
				setEventMessages($mailfile->error, $mailfile->errors, 'errors');
			} else {
				if (!empty($conf->global->MAIN_MAIL_SMTPS_ID)) {
					$result = $mailfile->sendfile();
					if ($result) {
						$signatory->last_email_sent_date = dol_now('tzuser');
						$signatory->update($user, true);
						$signatory->setPendingSignature($user, false);
						setEventMessages($langs->trans('SendEmailAt').' '.$signatory->email,array());
						// This avoid sending mail twice if going out and then back to page
						header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
						exit;
					} else {
						$langs->load("other");
						$mesg = '<div class="error">';
						if ($mailfile->error) {
							$mesg .= $langs->transnoentities('ErrorFailedToSendMail', dol_escape_htmltag($from), dol_escape_htmltag($sendto));
							$mesg .= '<br>'.$mailfile->error;
						} else {
							$mesg .= $langs->transnoentities('ErrorFailedToSendMail', dol_escape_htmltag($from), dol_escape_htmltag($sendto));
						}
						$mesg .= '</div>';
						setEventMessages($mesg, null, 'warnings');
					}
				} else {
					setEventMessages($langs->trans('ErrorSetupEmail'), '', 'errors');
				}
			}
		} else {
			$langs->load("errors");
			setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv("MailTo")), null, 'warnings');
			dol_syslog('Try to send email with no recipient defined', LOG_WARNING);
		}
	} else {
		// Mail sent KO
		if (!empty($signatory->errors)) setEventMessages(null, $signatory->errors, 'errors');
		else  setEventMessages($signatory->error, null, 'errors');
	}
}

// Action to delete attendant
if ($action == 'deleteAttendant') {

	$signatoryToDeleteID = GETPOST('signatoryID');
	$signatory->fetch($signatoryToDeleteID);

	if (!$error) {
		$result = $signatory->setDeleted($user, false);
		if ($result > 0) {
			setEventMessages($langs->trans('DeleteAttendantMessage').' '.$signatory->firstname.' '.$signatory->lastname,array());
			// Deletion attendant OK
			$urltogo = str_replace('__ID__', $result, $backtopage);
			$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urltogo); // New method to autoselect project after a New on another form object creation
			header("Location: " . $urltogo);
			exit;
		}
		else
		{
			// Deletion attendant KO
			if (!empty($object->errors)) setEventMessages(null, $object->errors, 'errors');
			else  setEventMessages($object->error, null, 'errors');
		}
	} else {
		$action = 'create';
	}
}

/*
 *  View
 */

$title    = $langs->trans("EnvelopeSign");
$help_url = '';
$morejs   = array("/doliletter/js/signature-pad.min.js", "/doliletter/js/doliletter.js.php");
$morecss  = array("/doliletter/css/doliletter.css");

llxHeader('', $title, $help_url, '', '', '', $morejs, $morecss);

if (!empty($object->id)) $res = $object->fetch_optionals();

// Object card
// ------------------------------------------------------------

$head = envelopePrepareHead($object);
print dol_get_fiche_head($head, 'envelopeSign', $langs->trans("Sign"), -1, "doliletter@doliletter");
dol_strlen($object->label) ? $morehtmlref = ' - ' . $object->label : '';


dol_banner_tab($object, 'ref', '', 0, 'ref', 'ref', $morehtmlref, '', 0, $morehtmlleft);

print dol_get_fiche_end(); ?>


<div class="noticeSignatureSuccess wpeo-notice notice-success hidden">
	<div class="all-notice-content">
		<div class="notice-content">
			<div class="notice-title"><?php echo $langs->trans('AddSignatureSuccess') ?></div>
			<div class="notice-subtitle"><?php echo $langs->trans("AddSignatureSuccessText") . GETPOST('signature_id')?></div>
		</div>
		<?php
		if ($signatory->checkSignatoriesSignatures($object->id)) {
			print '<a style="width = 100%;margin-right:0" href="'.DOL_URL_ROOT . '/custom/doliletter/envelope_signature.php?id='.$id.'">'.'</a>';
		}
		?>
	</div>
</div>
<?php

// Part to show record
if ((empty($action) || ($action != 'create' && $action != 'edit'))) {
	$url = $_SERVER['REQUEST_URI'];
	$zone = "private";

	//Master builder -- Maitre Oeuvre
	$element = $signatory->fetchSignatory('E_SENDER', $id);
	if ($element > 0) {
		$element = array_shift($element);
		$usertmp->fetch($element->element_id);
	}

	print load_fiche_titre($langs->trans("SignatureSender"), '', '');

	print '<div class="signatures-container sender-signature">';

	print '<table class="border centpercent tableforfield">';
	print '<tr class="liste_titre">';
	print '<td>' . $langs->trans("Name") . '</td>';
	print '<td>' . $langs->trans("Role") . '</td>';
	print '<td class="center">' . $langs->trans("SendMailDate") . '</td>';
	print '<td class="center">' . $langs->trans("SignatureDate") . '</td>';
	//print '<td class="center">' . $langs->trans("Status") . '</td>';
	print '<td class="center">' . $langs->trans("Signature") . '</td>';
	print '</tr>';

	print '<tr class="oddeven"><td class="minwidth200">';
	print $usertmp->getNomUrl(1);
	print '</td><td class="role" value="E_SENDER">';
	print $langs->trans("Sender");
	print '</td><td class="center">';
	print dol_print_date($element->last_email_sent_date, 'dayhour');
	print '</td><td class="center">';
	print dol_print_date($element->signature_date, 'dayhour');
	//print '</td><td class="center">';
	//print $element->getLibStatut(5);
	print '</td>';

	if ($permissiontoadd) {
		print '<td class="center">';
		require __DIR__ . "/core/tpl/doliletter_signature_view.tpl.php";
		print '</td>';
	}
	print '</tr>';
	print '</table></div>';
	print '<br>';
	if ($object->status == 4) {
		//uniquement si le courrier a été envoyé par mail
		print load_fiche_titre($langs->trans("SignatureReceiver"), '', '');
		$element = $signatory->fetchSignatory('E_RECEIVER', $id);
		if ($element > 0) {
			$element = array_shift($element);
			$usertmp->fetch($element->element_id);
		}
		print '<div class="signatures-container receiver-signature">';

		print '<table class="border centpercent tableforfield">';
		print '<tr class="liste_titre">';
		print '<td>' . $langs->trans("Name") . '</td>';
		print '<td>' . $langs->trans("Role") . '</td>';
		print '<td class="center">' . $langs->trans("SendMailDate") . '</td>';
		print '<td class="center">' . $langs->trans("SignatureDate") . '</td>';
		//print '<td class="center">' . $langs->trans("Status") . '</td>';
		print '<td class="center">' . $langs->trans("Signature") . '</td>';
		print '</tr>';

		$contact->fetch($object->fk_contact);
		print '<tr class="oddeven"><td class="minwidth200">';
		print $contact->getNomUrl(1);
		print '</td><td class="role" value="E_RECEIVER">';
		print $langs->trans("Receiver");
		print '</td><td class="center">';
		print dol_print_date($object->last_email_sent_date, 'dayhour');
		print '</td><td class="center">';
		print dol_print_date($element->signature_date, 'dayhour');
		//print '</td><td class="center">';
		//print $element->getLibStatut(5);
		print '</td>';

		if ($permissiontoadd) {
			$modal_id = 'contact-' . $contact->id;
			print '<td class="center">';
			require __DIR__ . "/core/tpl/doliletter_signature_view.tpl.php";
			print '</td>';
		}
		print '</tr>';

		print '</table>';
		print '<br>';
	}
	print '<tr><td>';
	print '<a href="envelope_card?id='. $object->id .'" class="butAction">' . $langs->trans('GoBackToCard') . '</a>';
	print '</td></tr>';
}

// End of page
llxFooter();
$db->close();
