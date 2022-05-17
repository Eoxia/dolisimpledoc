<?php
/* Copyright (C) 2021 EOXIA <dev@eoxia.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *       \file       public/signature/add_signature.php
 *       \ingroup    doliletter
 *       \brief      Public page to add signature
 */

if ( ! defined('NOREQUIREUSER'))  define('NOREQUIREUSER', '1');
if ( ! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if ( ! defined('NOREQUIREMENU'))  define('NOREQUIREMENU', '1');
if ( ! defined('NOREQUIREHTML'))  define('NOREQUIREHTML', '1');
if ( ! defined('NOLOGIN'))        define("NOLOGIN", 1);           // This means this output page does not require to be logged.
if ( ! defined('NOCSRFCHECK'))    define("NOCSRFCHECK", 1);       // We accept to go on this page from external web site.
if ( ! defined('NOIPCHECK'))		define('NOIPCHECK', '1');      // Do not check IP defined into conf $dolibarr_main_restrict_ip
if ( ! defined('NOBROWSERNOTIF')) define('NOBROWSERNOTIF', '1');

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if ( ! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) { $i--; $j--; }
if ( ! $res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) $res          = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
if ( ! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) $res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
// Try main.inc.php using relative path
if ( ! $res && file_exists("../../main.inc.php")) $res       = @include "../../main.inc.php";
if ( ! $res && file_exists("../../../main.inc.php")) $res    = @include "../../../main.inc.php";
if ( ! $res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if ( ! $res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/ecm/class/ecmdirectory.class.php';
require_once DOL_DOCUMENT_ROOT.'/ecm/class/ecmfiles.class.php';

require_once '../../class/envelope.class.php';
require_once '../../lib/doliletter_function.lib.php';

global $conf, $langs, $user, $db;
// Load translation files required by the page
$langs->loadLangs(array("doliletter@doliletter", "other", "errors"));

// Get parameters
$track_id = GETPOST('track_id', 'alpha');
$action   = GETPOST('action', 'aZ09');
$url      = dirname($_SERVER['PHP_SELF']) . '/signature_success.php';
$source   = GETPOST('source', 'aZ09');
$type     = GETPOST('type', 'aZ09');

// Initialize technical objects
$user = new User($db);
$ecmfile = new EcmFiles($db);

switch ($type) {
	case 'envelope':
		$object         = new Envelope($db);
		$signatory      = new EnvelopeSignature($db);
		break;
}

$signatory->fetch('', '', " AND signature_url =" . "'" . $track_id . "'");
$object->fetch($signatory->fk_object);

$upload_dir = $conf->doliletter->multidir_output[isset($object->entity) ? $object->entity : 1];

/*
 * Actions
 */

$parameters = array();
$reshook    = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

// Action to add record
if ($action == 'addSignature') {
	$signatoryID  = GETPOST('signatoryID');
	$request_body = file_get_contents('php://input');
	$client_ip = $_SERVER['REMOTE_ADDR'];

	$signatory->fetch($signatoryID);
	$signatory->signature      = $request_body;
	$signatory->signature_date = dol_now();
	$signatory->ip = $client_ip;

	$object->fetch($signatory->fk_object);

	if ( ! $error) {
		$result = $signatory->update($user, false);
		if ($result > 0) {
			$signatory->setSigned($user, false);
			$object->setStatusCommon($user, 5);
			$object->call_trigger('ENVELOPE_RECIPIENT_SIGN', $user);
			if (method_exists($object, 'generateDocument'))
			{
				$result = $object->generateDocument('deimos', $langs, $hidedetails, $hidedesc, $hideref);

				if ($result < 0) {
					dol_print_error($db, $object->error, $object->errors);
					exit();
				}
				if ($conf->global->DOLILETTER_SHOW_DOCUMENTS_ON_PUBLIC_INTERFACE) {
					$filedir = $conf->doliletter->dir_output.'/'.$object->element.'/'.$object->ref . '/acknowledgementreceipt';
					$filelist = dol_dir_list($filedir, 'files');
					$filename = $filelist[0]['name'];

					$ecmfile->fetch(0, '', 'doliletter/envelope/'.$object->ref.'/acknowledgementreceipt/'.$filename, '', '', 'doliletter_envelope', $id);
					require_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';
					$ecmfile->share = getRandomPassword(true);
					$ecmfile->update($user);
				}
			}
			// Creation signature OK
			exit;
		} else {
			// Creation signature KO
			if ( ! empty($signatory->errors)) setEventMessages(null, $signatory->errors, 'errors');
			else setEventMessages($signatory->error, null, 'errors');
		}
	}
}

/*
 * View
 */

$form = new Form($db);

if (empty($conf->global->DOLILETTER_SIGNATURE_ENABLE_PUBLIC_INTERFACE)) {
	print $langs->trans('SignaturePublicInterfaceForbidden');
	exit;
}

$morejs  = array("/doliletter/js/signature-pad.min.js", "/doliletter/js/doliletter.js.php");
$morecss = array("/doliletter/css/doliletter.css");

llxHeaderSignature($langs->trans("Signature"), "", 0, 0, $morejs, $morecss);

$element = $signatory->fetchSignatory($signatory->role, $signatory->fk_object, $type);
$element = array_shift($element);
$url = DOL_URL_ROOT . '/custom/doliletter/public/signature/signature_success.php?document_name=' .  $object->ref . '-' . $object->label . '&id=' . $object->id . '&type=' . $type;

if (dol_strlen($element->signature)) {
	header("Location: ".$url);
	exit;
}
?>
<div class="digirisk-signature-container">
	<input hidden id="redirectURL" value="<?php echo $url ?>">
	<div class="wpeo-gridlayout grid-2">
		<div class="informations">
			<?php if ($conf->global->DOLILETTER_SHOW_DOCUMENTS_ON_PUBLIC_INTERFACE) : ?>
				<div class="wpeo-gridlayout grid-2 file-generation">
					<?php if ($type == 'envelope') : ?>
						<?php $filelist = dol_dir_list($upload_dir . '/' . $object->element . '/' . $object->ref, 'files');
						if (!empty($filelist)) {
							foreach ($filelist as $file) {
								if (!preg_match('/specimen/', $file['name'])) {
									$fileurl = $file['fullname'];
									$filename = $file['name'];
								}
							}
							$envelope_file = $ecmfile;
							$envelope_file->fetch(0, '', 'doliletter/envelope/'.$object->ref.'/'.$filename, '', '', 'doliletter_envelope', $object->id);
						}
						?>
						<?php if(dol_strlen($envelope_file->share)) : ?>
							<strong class="grid-align-middle"><?php echo $langs->trans("LinkedDocument"); ?></strong>
							<a href="<?php echo './../../../../document.php?hashp=' . $envelope_file->share ?>" target="_blank">
								<span class="wpeo-button button-primary button-radius-2 grid-align-right"><i class="button-icon fas fa-file-pdf"></i><?php echo '  ' . $langs->trans('ShowDocument'); ?></span>
							</a>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<br>
			<div class="wpeo-table table-flex table-2">
				<div class="table-row">
					<div class="table-cell"><?php echo $langs->trans("CompleteName"); ?></div>
					<div class="table-cell table-end"><?php echo $signatory->firstname . ' ' . $signatory->lastname; ?></div>
				</div>
				<div class="table-row">
					<div class="table-cell"><?php echo $langs->trans("DocumentName"); ?></div>
					<div class="table-cell table-end"><?php echo $object->ref . ' ' . $object->label; ?></div>
				</div>
			</div>
		</div>
		<div class="signature signatures-container">
			<input hidden class="role" value="E_RECEIVER">
			<div class="wpeo-gridlayout grid-2">
				<strong class="grid-align-middle"><?php echo $langs->trans("Signature"); ?></strong>
				<?php
				$modal_id = 'contact-' . $object->fk_contact;
				?>
				<div class="wpeo-button button-primary button-square-40 button-radius-2 grid-align-right wpeo-modal-event modal-signature-open modal-open" value="<?php echo $element->id ?>">
					<span><i class="fas fa-pen-nib"></i> <?php echo $langs->trans('Sign'); ?></span>
				</div>
			</div>
			<br>
			<div class="signature-element">
				<?php require  "../../core/tpl/doliletter_signature_view.tpl.php"; ?>
			</div>
		</div>
	</div>
<?php

llxFooter('', 'public');
$db->close();

