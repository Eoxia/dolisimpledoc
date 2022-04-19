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
 *       \file       public/signature/signature_success.php
 *       \ingroup    doliletter
 *       \brief      Public page to view success on signature
 */

if ( ! defined('NOREQUIREUSER'))  define('NOREQUIREUSER', '1');
if ( ! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if ( ! defined('NOREQUIREMENU'))  define('NOREQUIREMENU', '1');
if ( ! defined('NOREQUIREHTML'))  define('NOREQUIREHTML', '1');
if ( ! defined('NOLOGIN'))        define("NOLOGIN", 1); // This means this output page does not require to be logged.
if ( ! defined('NOCSRFCHECK'))    define("NOCSRFCHECK", 1); // We accept to go on this page from external web site.
if ( ! defined('NOIPCHECK'))		define('NOIPCHECK', '1'); // Do not check IP defined into conf $dolibarr_main_restrict_ip
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

require_once '../../lib/doliletter_function.lib.php';
require_once '../../class/envelope.class.php';

global $conf, $langs, $db, $user;

$documentName = GETPOST('document_name');
$id           = GETPOST('id');
$type         = GETPOST('type');

$ecmfile = new EcmFiles($db);

$upload_dir = $conf->doliletter->multidir_output[isset($object->entity) ? $object->entity : 1];

// Load translation files required by the page
$langs->loadLangs(array("doliletter@doliletter", "other", "errors"));

switch ($type) {
	case 'envelope':
		$object         = new Envelope($db);
		$signatory      = new EnvelopeSignature($db);
		break;
}

$object->fetch($id);

/*
 * View
 */

if (empty($conf->global->DOLILETTER_SIGNATURE_ENABLE_PUBLIC_INTERFACE)) {
	print $langs->trans('SignaturePublicInterfaceForbidden');
	exit;
}

$morejs  = array("/doliletter/js/signature-pad.min.js", "/doliletter/js/doliletter.js.php");
$morecss = array("/doliletter/css/doliletter.css");

llxHeaderSignature($langs->trans("Signature"), "", 0, 0, $morejs, $morecss);

?>
<div class="digirisk-signature-container">
	<p class="center"><?php echo $langs->trans("AcknowledgementReceiptSigned", $documentName); ?> </p>
	<?php if ($conf->global->DOLILETTER_SHOW_DOCUMENTS_ON_PUBLIC_INTERFACE) : ?>
		<table class="wpeo-gridlayout grid-2 file-generation">
			<?php if ($type == 'envelope') : ?>
				<?php
				$envelope_filelist = dol_dir_list($upload_dir . '/' . $object->element . '/' . $object->ref);

				if (!empty($envelope_filelist)) {
					$file = array_shift($envelope_filelist);
					$fileurl = $file['fullname'];
					$envelope_filename = $file['name'];
					$envelope_file = new EcmFiles($db);
					$envelope_file->fetch(0, '', 'doliletter/envelope/'.$object->ref.'/'.$envelope_filename, '', '', 'doliletter_envelope', $object->id);
				}

				$acknowledgementreceipt_filelist = dol_dir_list($upload_dir . '/' . $object->element . '/' . $object->ref . '/acknowledgementreceipt');

				if (!empty($acknowledgementreceipt_filelist)) {
					$file = array_shift($acknowledgementreceipt_filelist);
					$fileurl = $file['fullname'];
					$acknowledgementreceipt_filename = $file['name'];
					$acknowledgementreceipt_file = new EcmFiles($db);

					$acknowledgementreceipt_file->fetch(0, '', 'doliletter/envelope/'.$object->ref.'/acknowledgementreceipt/'.$acknowledgementreceipt_filename, '', '', 'doliletter_envelope', $object->id);

				}
				?>
				<?php

				?>
				<?php if (dol_strlen($envelope_file->share) > 0) : ?>

					<tr>
						<td>
							<strong class="grid-align-middle"><?php echo $langs->trans("YourEnvelope"); ?></strong>
						</td>
						<td>
							<a href="<?php echo './../../../../document.php?hashp=' . $envelope_file->share ?>">
								<span class="wpeo-button button-primary button-radius-2 grid-align-right"><i class="button-icon fas fa-file-pdf"></i><?php echo '  ' . $langs->trans('ShowDocument'); ?></span>
							</a>
						</td>
					</tr>
				<?php endif; ?>
				<?php if (dol_strlen($acknowledgementreceipt_file->share)) : ?>
					<tr>
						<td>
							<strong class="grid-align-middle"><?php echo $langs->trans("YourAcknowledgementReceipt"); ?></strong>
						</td>
						<td>
							<a href="<?php echo './../../../../document.php?hashp=' . $acknowledgementreceipt_file->share ?>">
								<span class="wpeo-button button-primary button-radius-2 grid-align-right"><i class="button-icon fas fa-file-pdf"></i><?php echo '  ' . $langs->trans('ShowDocument'); ?></span>
							</a>
						</td>
					</tr>
				<?php endif; ?>
			<?php endif; ?>
		</table>
	<?php endif; ?>
</div>

<br>
<?php


// End of page
llxFooter('', 'public');
$db->close();

