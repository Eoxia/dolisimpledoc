<?php
/* Copyright (C) 2003		Rodolphe Quiedeville		<rodolphe@quiedeville.org>
 * Copyright (C) 2004-2010	Laurent Destailleur			<eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012	Regis Houssin				<regis.houssin@inodbox.com>
 * Copyright (C) 2008		Raphael Bertrand (Resultic)	<raphael.bertrand@resultic.fr>
 * Copyright (C) 2011		Fabrice CHERRIER
 * Copyright (C) 2013-2020  Philippe Grand	            <philippe.grand@atoo-net.com>
 * Copyright (C) 2015       Marcos García               <marcosgdf@gmail.com>
 * Copyright (C) 2018-2020  Frédéric France             <frederic.france@netlogic.fr>
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
 * or see https://www.gnu.org/
 */

/**
 *	\file       htdocs/core/modules/contract/doc/pdf_strato.modules.php
 *	\ingroup    ficheinter
 *	\brief      Strato contracts template class file
 */
require_once DOL_DOCUMENT_ROOT.'/core/modules/contract/modules_contract.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

require_once __DIR__ . '/../modules_trackingnumber.php';

/**
 *	Class to build contracts documents with model Strato
 */
class pdf_nerio extends ModelePDFTrackingNumber
{
	/**
	 * @var DoliDb Database handler
	 */
	public $db;

	/**
	 * @var string model name
	 */
	public $name;

	/**
	 * @var string model description (short text)
	 */
	public $description;

	/**
	 * @var string document type
	 */
	public $type;

	/**
	 * @var array Minimum version of PHP required by module.
	 * e.g.: PHP ≥ 5.6 = array(5, 6)
	 */
	public $phpmin = array(5, 6);

	/**
	 * Dolibarr version of the loaded document
	 * @var string
	 */
	public $version = 'dolibarr';

	/**
	 * @var int page_largeur
	 */
	public $page_largeur;

	/**
	 * @var int page_hauteur
	 */
	public $page_hauteur;

	/**
	 * @var array format
	 */
	public $format;

	/**
	 * @var int marge_gauche
	 */
	public $marge_gauche;

	/**
	 * @var int marge_droite
	 */
	public $marge_droite;

	/**
	 * @var int marge_haute
	 */
	public $marge_haute;

	/**
	 * @var int marge_basse
	 */
	public $marge_basse;

	/**
	 * Issuer
	 * @var Societe
	 */
	public $emetteur;

	/**
	 * Recipient
	 * @var Societe
	 */
	public $recipient;

	/**
	 *	Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs, $mysoc;

		$this->db = $db;
		$this->name = 'nerio';
		$this->description = $langs->trans("StandardContractsTemplate");

		// Page size for A4 format
		$this->type = 'pdf';
		$formatarray = pdf_getFormat();

		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur, $this->page_hauteur);
		$this->marge_gauche = isset($conf->global->MAIN_PDF_MARGIN_LEFT) ? $conf->global->MAIN_PDF_MARGIN_LEFT : 10;
		$this->marge_droite = isset($conf->global->MAIN_PDF_MARGIN_RIGHT) ? $conf->global->MAIN_PDF_MARGIN_RIGHT : 10;
		$this->marge_haute = isset($conf->global->MAIN_PDF_MARGIN_TOP) ? $conf->global->MAIN_PDF_MARGIN_TOP : 10;
		$this->marge_basse = isset($conf->global->MAIN_PDF_MARGIN_BOTTOM) ? $conf->global->MAIN_PDF_MARGIN_BOTTOM : 10;

		$this->option_logo = 1; // Display logo
		$this->option_tva = 0; // Manage the vat option FACTURE_TVAOPTION
		$this->option_modereg = 0; // Display payment mode
		$this->option_condreg = 0; // Display payment terms
		$this->option_codeproduitservice = 0; // Display product-service code
		$this->option_multilang = 0; // Available in several languages
		$this->option_draft_watermark = 1; // Support add of a watermark on drafts

		// Get source company
		$this->emetteur = $mysoc;
		if (empty($this->emetteur->country_code)) $this->emetteur->country_code = substr($langs->defaultlang, -2); // By default, if not defined

		// Define position of columns
		$this->posxdesc = $this->marge_gauche + 1;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Function to build pdf onto disk
	 *
	 *  @param		Envelope			$object				Object to generate
	 *  @param		Translate		$outputlangs		Lang output object
	 *  @param		string			$srctemplatepath	Full path of source filename for generator using a template file
	 *  @param		int				$hidedetails		Do not show line details
	 *  @param		int				$hidedesc			Do not show desc
	 *  @param		int				$hideref			Do not show ref
	 *  @return		int									1=OK, 0=KO
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		// phpcs:enable
		global $user, $langs, $conf, $hookmanager, $mysoc;
		$date = new DateTime();
		if (!is_object($outputlangs)) $outputlangs = $langs;
		// For backward compatibility with FPDF, force output charset to ISO, because FPDF expect text to be encoded in ISO
		if (!empty($conf->global->MAIN_USE_FPDF)) $outputlangs->charset_output = 'ISO-8859-1';

		// Load traductions files required by page
		$outputlangs->loadLangs(array("main", "dict", "companies", "contracts"));

		if ($conf->doliletter->dir_output)
		{
			$object->fetch_thirdparty();

			// Definition of $dir and $file
			if ($object->specimen)
			{
				$dir = $conf->doliletter->dir_output;
				$file = $dir."/SPECIMEN.pdf";
			} else {
				$objectref = dol_sanitizeFileName($object->ref);
				$dir = $conf->doliletter->multidir_output[$conf->entity]."/envelope/".$objectref .'/trackingnumber';
			}
			if (!file_exists($dir))
			{
				if (dol_mkdir($dir) < 0)
				{
					$this->error = $outputlangs->trans("ErrorCanNotCreateDir", $dir);
					return 0;
				}
			}
			// find appropriate doc number
			if (!$object->specimen) {
				$docnum = 0;
					do {
						$date = dol_print_date(dol_now(),'dayxcard');
						$filename = $date . '_' . $objectref . '_TN' . '_' . $docnum . '.pdf';
						$filename = str_replace(' ', '_', $filename);
						$filename = dol_sanitizeFileName($filename);
						if ($object->status < 2) {
							$filename = $date . '_' . $objectref . '_' . $docnum. '_SP_specimen_unsigned' . '.pdf';
						}
						$file = $dir.'/'.$filename;
						$docnum++;
					} while(file_exists($file));
			}
			if (file_exists($dir))
			{
				$signatory = new EnvelopeSignature($this->db);
				$signatory = $signatory->fetchSignatory('E_RECEIVER', $object->id);
				$receiver    = array_shift($signatory);

				// Add pdfgeneration hook
				if (!is_object($hookmanager))
				{
					include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
					$hookmanager = new HookManager($this->db);
				}
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters = array('file'=>$file, 'object'=>$object, 'outputlangs'=>$outputlangs);
				global $action;
				$reshook = $hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks

				$pdf = pdf_getInstance($this->format);
				$default_font_size = pdf_getPDFFontSize($outputlangs); // Must be after pdf_getInstance
				$heightforinfotot = 50; // Height reserved to output the info and total part
				$heightforfreetext = (isset($conf->global->MAIN_PDF_FREETEXT_HEIGHT) ? $conf->global->MAIN_PDF_FREETEXT_HEIGHT : 5); // Height reserved to output the free text on last page
				$heightforfooter = $this->marge_basse + 8; // Height reserved to output the footer (value include bottom margin)
				if (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS)) $heightforfooter += 6;
				$pdf->SetAutoPageBreak(1, 0);

				if (class_exists('TCPDF'))
				{
					$pdf->setPrintHeader(false);
					$pdf->setPrintFooter(false);
				}
				$pdf->SetFont(pdf_getPDFFont($outputlangs));
				// Set path to the background PDF File
				if (!empty($conf->global->MAIN_ADD_PDF_BACKGROUND))
				{
					$pagecount = $pdf->setSourceFile($conf->mycompany->dir_output.'/'.$conf->global->MAIN_ADD_PDF_BACKGROUND);
					$tplidx = $pdf->importPage(1);
				}

				$pdf->Open();
				$pagenb = 0;
				$pdf->SetDrawColor(128, 128, 128);

				$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
				$pdf->SetSubject($outputlangs->transnoentities("Envelope"));
				$pdf->SetCreator("Dolibarr ".DOL_VERSION);
				$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
				$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref)." ".$outputlangs->transnoentities("ContractCard")." ".$outputlangs->convToOutputCharset($object->thirdparty->name));
				if (!empty($conf->global->MAIN_DISABLE_PDF_COMPRESSION)) $pdf->SetCompression(false);

				$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite); // Left, Top, Right

				// New page
				$pdf->AddPage();
				if (!empty($tplidx)) $pdf->useTemplate($tplidx);
				$pagenb++;
				$this->_pagehead($pdf, $object, 1, $outputlangs);
				$pdf->SetFont('', '', $default_font_size - 1);
				$pdf->MultiCell(0, 3, ''); // Set interline to 3
				$pdf->SetTextColor(0, 0, 0);

				$tab_top = 90;
				$tab_top_newpage = (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD) ? 42 : 10);

//				// Display notes
//				if (!empty($object->note_public))
//				{
//					$tab_top -= 2;
//
//					$pdf->SetFont('', '', $default_font_size - 1);
//					$pdf->writeHTMLCell(190, 3, $this->posxdesc - 1, $tab_top - 1, dol_htmlentitiesbr($object->note_public), 0, 1);
//					$nexY = $pdf->GetY();
//					$height_note = $nexY - $tab_top;
//
//					// Rect takes a length in 3rd parameter
//					$pdf->SetDrawColor(192, 192, 192);
//					$pdf->Rect($this->marge_gauche, $tab_top - 1, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $height_note + 1);
//
//					$tab_top = $nexY + 6;
//				}

				$iniY = $tab_top + 7;
				$curY = $tab_top + 7;
				$nexY = $tab_top + 2;

				$pdf->SetXY($this->marge_gauche, $tab_top);

				$pdf->MultiCell(0, 2, ''); // Set interline to 3. Then writeMultiCell must use 3 also.

				$tab_top -= 2;

				$pdf->SetFont('', '', $default_font_size - 1);

				require_once DOL_DOCUMENT_ROOT.'/ecm/class/ecmdirectory.class.php';
				require_once DOL_DOCUMENT_ROOT.'/ecm/class/ecmfiles.class.php';
				$ecmfile = new EcmFiles($this->db);
				$filedir = $conf->doliletter->dir_output.'/'.$object->element.'/'.$object->ref . '/';
				$filelist = dol_dir_list($filedir, 'files');
				$filepath = preg_split('/documents\//',$filelist[0]['fullname'])[1];

				$ecmfile->fetch(0, '', $filepath, '', '', 'doliletter_envelope', $object->id);
				$letter = new LetterSending($this->db);
				$lettersending = $letter->fetchAll('', '', 0, 0, array('customsql' => ' fk_envelope =' . $object->id));
				if (is_array($lettersending)) {
					$lettersending = end($lettersending);
				} else {
					$lettersending = $letter;
				}

				if ($object->status == 3) {
					$pdf->writeHTMLCell(190, 3, $this->posxdesc - 1, $tab_top - 1, $langs->trans('TrackingNumberTextLetter', $object->ref, $lettersending->letter_code) . '<br>' . $langs->trans('SentDocumentSignedSha', $ecmfile->label), 0, 1);
				}

				$nexY = $pdf->GetY();
				$height_note = $nexY - $tab_top;

				// Rect takes a length in 3rd parameter
				$pdf->SetDrawColor(192, 192, 192);
				$pdf->Rect($this->marge_gauche, $tab_top - 1, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $height_note + 1);

				$tab_top = $nexY + 6;

				if (is_countable($object->lines)) {
					$nblines = count($object->lines);
				}
				else {
					$nblines = 0;
				}

				// Loop on each lines
				for ($i = 0; $i < $nblines; $i++)
				{
					$objectligne = $object->lines[$i];

					$valide = $objectligne->id ? 1 : 0;

					if ($valide > 0 || $object->specimen)
					{
						$curX = $this->posxdesc - 1;
						$curY = $nexY;
						$pdf->SetFont('', '', $default_font_size - 1); // Into loop to work with multipage
						$pdf->SetTextColor(0, 0, 0);

						$pdf->setTopMargin($tab_top_newpage);
						$pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext + $heightforinfotot); // The only function to edit the bottom margin of current page to set it.
						$pageposbefore = $pdf->getPage();

						// Description of product line

						if ($objectligne->date_ouverture_prevue) {
							$datei = dol_print_date($objectligne->date_ouverture_prevue, 'day', false, $outputlangs, true);
						} else {
							$datei = $langs->trans("Unknown");
						}

						if ($objectligne->date_fin_validite) {
							$durationi = convertSecondToTime($objectligne->date_fin_validite - $objectligne->date_ouverture_prevue, 'allwithouthour');
							$datee = dol_print_date($objectligne->date_fin_validite, 'day', false, $outputlangs, true);
						} else {
							$durationi = $langs->trans("Unknown");
							$datee = $langs->trans("Unknown");
						}

						if ($objectligne->date_ouverture) {
							$daters = dol_print_date($objectligne->date_ouverture, 'day', false, $outputlangs, true);
						} else {
							$daters = $langs->trans("Unknown");
						}

						if ($objectligne->date_cloture) {
							$datere = dol_print_date($objectligne->date_cloture, 'day', false, $outputlangs, true);
						} else {
							$datere = $langs->trans("Unknown");
						}

						$txtpredefinedservice = $objectligne->product_ref;
						if ($objectligne->product_label)
						{
							$txtpredefinedservice .= ' - ';
							$txtpredefinedservice .= $objectligne->product_label;
						}

						$desc = dol_htmlentitiesbr($objectligne->desc, 1); // Desc (not empty for free lines)
						$txt = '';
						$txt .= $outputlangs->transnoentities("Quantity").' : <strong>'.$objectligne->qty.'</strong> - '.$outputlangs->transnoentities("UnitPrice").' : <strong>'.price($objectligne->subprice).'</strong>'; // Desc (not empty for free lines)
						if (empty($conf->global->CONTRACT_HIDE_PLANNED_DATE_ON_PDF))
						{
							$txt .= '<br>';
							$txt .= $outputlangs->transnoentities("DateStartPlannedShort")." : <strong>".$datei."</strong> - ".$outputlangs->transnoentities("DateEndPlanned")." : <strong>".$datee.'</strong>';
						}
						if (empty($conf->global->CONTRACT_HIDE_REAL_DATE_ON_PDF))
						{
							$txt .= '<br>';
							$txt .= $outputlangs->transnoentities("DateStartRealShort")." : <strong>".$daters.'</strong>';
							if ($objectligne->date_cloture) $txt .= " - ".$outputlangs->transnoentities("DateEndRealShort")." : '<strong>'".$datere.'</strong>';
						}

						$pdf->startTransaction();
						$pdf->writeHTMLCell(0, 0, $curX, $curY, dol_concatdesc($txtpredefinedservice, dol_concatdesc($txt, $desc)), 0, 1, 0);
						$pageposafter = $pdf->getPage();
						if ($pageposafter > $pageposbefore)	// There is a pagebreak
						{
							$pdf->rollbackTransaction(true);
							$pageposafter = $pageposbefore;
							//print $pageposafter.'-'.$pageposbefore;exit;
							$pdf->setPageOrientation('', 1, $heightforfooter); // The only function to edit the bottom margin of current page to set it.
							$pdf->writeHTMLCell(0, 0, $curX, $curY, dol_concatdesc($txtpredefinedservice, dol_concatdesc($txt, $desc)), 0, 1, 0);
							$pageposafter = $pdf->getPage();
							$posyafter = $pdf->GetY();

							if ($posyafter > ($this->page_hauteur - ($heightforfooter + $heightforfreetext + $heightforinfotot)))	// There is no space left for total+free text
							{
								if ($i == ($nblines - 1))	// No more lines, and no space left to show total, so we create a new page
								{
									$pdf->AddPage('', '', true);
									if (!empty($tplidx)) $pdf->useTemplate($tplidx);
									if (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)) $this->_pagehead($pdf, $object, 0, $outputlangs);
									$pdf->setPage($pageposafter + 1);
								}
							} else {
								// We found a page break

								// Allows data in the first page if description is long enough to break in multiples pages
								if (!empty($conf->global->MAIN_PDF_DATA_ON_FIRST_PAGE))
									$showpricebeforepagebreak = 1;
								else $showpricebeforepagebreak = 0;
							}
						} else // No pagebreak
						{
							$pdf->commitTransaction();
						}

						$nexY = $pdf->GetY() + 2;
						$pageposafter = $pdf->getPage();

						$pdf->setPage($pageposbefore);
						$pdf->setTopMargin($this->marge_haute);
						$pdf->setPageOrientation('', 1, 0); // The only function to edit the bottom margin of current page to set it.

						// We suppose that a too long description is moved completely on next page
						if ($pageposafter > $pageposbefore) {
							$pdf->setPage($pageposafter); $curY = $tab_top_newpage;
						}

						$pdf->SetFont('', '', $default_font_size - 1); // We reposition the default font

						// Detect if some page were added automatically and output _tableau for past pages
						while ($pagenb < $pageposafter)
						{
							$pdf->setPage($pagenb);
							if ($pagenb == 1)
							{
								$this->_tableau($pdf, $object, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter - $heightforfreetext, 0, $outputlangs, 0, 1);
							} else {
								$this->_tableau($pdf, $object, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter - $heightforfreetext, 0, $outputlangs, 1, 1);
							}
							$this->_pagefoot($pdf, $object, $outputlangs, 1);
							$pagenb++;
							$pdf->setPage($pagenb);
							$pdf->setPageOrientation('', 1, 0); // The only function to edit the bottom margin of current page to set it.
						}

						if (isset($object->lines[$i + 1]->pagebreak) && $object->lines[$i + 1]->pagebreak)
						{
							if ($pagenb == 1)
							{
								$this->_tableau($pdf, $object, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter - $heightforfreetext, 0, $outputlangs, 0, 1);
							} else {
								$this->_tableau($pdf, $object, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter - $heightforfreetext, 0, $outputlangs, 1, 1);
							}
							$this->_pagefoot($pdf, $object, $outputlangs, 1);
							// New page
							$pdf->AddPage();
							if (!empty($tplidx)) $pdf->useTemplate($tplidx);
							$pagenb++;
						}
					}
				}

				if ($object->status == 5) {
					// Show square
					if ($pagenb == 1)
					{
//						$this->_tableau($pdf, $object, $tab_top, $this->page_hauteur - $tab_top - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, 0, 0);
						$this->tabSignature($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforinfotot - $heightforfreetext - $heightforfooter, $outputlangs, $object);
						$bottomlasttab = $this->page_hauteur - $heightforfooter - $heightforfooter + 1;
					} else {
//						$this->_tableau($pdf, $object, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, 0, 0);
						$this->tabSignature($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforinfotot - $heightforfreetext - $heightforfooter, $outputlangs, $object);
						$bottomlasttab = $this->page_hauteur - $heightforfooter - $heightforfooter + 1;
					}
				}


				$this->_pagefoot($pdf, $object, $outputlangs);
				if (method_exists($pdf, 'AliasNbPages')) $pdf->AliasNbPages();

				$pdf->Close();

				$pdf->Output($file, 'F');

				// Add pdfgeneration hook
				if (!is_object($hookmanager))
				{
					include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
					$hookmanager = new HookManager($this->db);
				}
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters = array('file'=>$file, 'object'=>$object, 'outputlangs'=>$outputlangs);
				global $action;
				$reshook = $hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
				if ($reshook < 0)
				{
					$this->error = $hookmanager->error;
					$this->errors = $hookmanager->errors;
				}

				if (!empty($conf->global->MAIN_UMASK))
					@chmod($file, octdec($conf->global->MAIN_UMASK));

				$this->result = array('fullpath'=>$file);
				$signatory = new EnvelopeSignature($this->db);
				$signatory->deleteSignatoriesSignatures($object->id);

				return 1;
			} else {
				$this->error = $langs->trans("ErrorCanNotCreateDir", $dir);
				return 0;
			}
		} else {
			$this->error = $langs->trans("ErrorConstantNotDefined", "CONTRACT_OUTPUTDIR");
			return 0;
		}
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *   Show table for lines
	 *
	 *   @param		TCPDF		$pdf     		Object PDF
	 *   @param		string		$tab_top		Top position of table
	 *   @param		string		$tab_height		Height of table (rectangle)
	 *   @param		int			$nexY			Y
	 *   @param		Translate	$outputlangs	Langs object
	 *   @param		int			$hidetop		Hide top bar of array
	 *   @param		int			$hidebottom		Hide bottom bar of array
	 *   @return	void
	 */
	protected function _tableau(&$pdf, $object, $tab_top, $tab_height, $nexY, $outputlangs, $hidetop = 0, $hidebottom = 0)
	{
		global $conf;

		// Force to disable hidetop and hidebottom
		$hidebottom = 0;
		if ($hidetop) $hidetop = -1;

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$pdf->SetFont('','', $default_font_size - 1);

		$pdf->MultiCell(0, 3, '');		// Set interline to 3
		$pdf->SetXY($this->marge_gauche, $tab_top + 8);
		$text= $object->content;
		if ($object->duree > 0)
		{
			$totaltime=convertSecondToTime($object->duree,'all',$conf->global->MAIN_DURATION_OF_WORKDAY);
			$text.=($text?' - ':'').$outputlangs->trans("Total").": ".$totaltime;
		}
		$desc=dol_htmlentitiesbr($text,1);
		//print $outputlangs->convToOutputCharset($desc); exit;

		$pdf->writeHTMLCell(180, 3, 10, $tab_top + 8, $outputlangs->convToOutputCharset($desc), 0, 1);
		$nexY = $pdf->GetY();

		$pdf->line($this->marge_gauche, $nexY, $this->page_largeur-$this->marge_droite, $nexY);

		$pdf->MultiCell(0, 3, '');		// Set interline to 3. Then writeMultiCell must use 3 also.


		// Output Rect
		$this->printRect($pdf, $this->marge_gauche, $tab_top, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $tab_height + 3); // Rect takes a length in 3rd parameter and 4th parameter
	}

	/**
	 * Show footer signature of page
	 * @param   TCPDF       $pdf            Object PDF
	 * @param   int         $tab_top        tab height position
	 * @param   int         $tab_height     tab height
	 * @param   Translate   $outputlangs    Object language for output
	 * @return void
	 */
	protected function tabSignature(&$pdf, $tab_top, $tab_height, $outputlangs, $object)
	{
		global $conf;

		$signatory = new EnvelopeSignature($this->db);
		$receiver    = array_shift($signatory->fetchSignatory('E_RECEIVER', $object->id));

//		$recipient = array_shift($signatory->fetchSignatory('E_SOCIETY', $object->id));
		if (dol_strlen($receiver->signature) > 0) {
			$tempdir = $conf->doliletter->multidir_output[isset($object->entity) ? $object->entity : 1] . '/temp/';

			//Signatures
			if (!empty($receiver) && $receiver > 0) {
				$encoded_image = explode(",",  $receiver->signature)[1];
				$decoded_image = base64_decode($encoded_image);
				file_put_contents($tempdir."signature.png", $decoded_image);
				$test = $tempdir."signature.png";
			}
		}

		$file_list = dol_dir_list(DOL_DATA_ROOT . '/doliletter/' . $object->element . '/' . $object->ref, 'files');
		if ( is_array($file_list[0]) ) {
			$filename = $file_list[0]['relativename'];
		}

		$pdf->SetDrawColor(128, 128, 128);
		$posmiddle = $this->marge_gauche + round(($this->page_largeur - $this->marge_gauche - $this->marge_droite) / 2);
		$posy = $tab_top + $tab_height + 3 + 3;

		$contact = new Contact($this->db);
		$contact->fetch($object->fk_contact);

		$pdf->SetXY($this->marge_gauche, $posy);
//		$pdf->MultiCell($posmiddle - $this->marge_gauche - 5, 5,  $outputlangs->transnoentities('TrackingNumberTextMail', $receiver->firstname . ' ' . $receiver->lastname, preg_replace('/AR_/', '', $filename), 'mail', dol_print_date($receiver->signature_date), $receiver->ip ), 0, 'L', 0);

		$pdf->SetXY($this->marge_gauche, $posy + 10);
		$pdf->Image($test, $this->marge_gauche, $posy - 5, 50, 50); // width=0 (auto)
		$pdf->MultiCell($posmiddle - $this->marge_gauche - 5, 30, '', 1);

//		$pdf->SetXY($posmiddle + 5, $posy);
//		$pdf->MultiCell($this->page_largeur - $this->marge_droite - $posmiddle - 5, 5, $outputlangs->transnoentities("ContactNameAndSignature", $this->recipient->name), 0, 'L', 0);
//
//		$pdf->SetXY($posmiddle + 5, $posy + 5);
//		$pdf->Image($test, $this->marge_gauche, $posy - 5, 50, 50); // width=0 (auto)
//		$pdf->MultiCell($this->page_largeur - $this->marge_droite - $posmiddle - 5, 30, '', 1);
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *  Show top header of page.
	 *
	 *  @param	TCPDF		$pdf     		Object PDF
	 *  @param  Envelope    $object     	Object to show
	 *  @param  int	    	$showaddress    0=no, 1=yes
	 *  @param  Translate	$outputlangs	Object lang for output
	 *  @return	void
	 */
	protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs)
	{
		global $conf, $langs;

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		// Load traductions files required by page
		$outputlangs->loadLangs(array("main", "dict", "contract", "companies"));

		pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);

		//Affiche le filigrane brouillon - Print Draft Watermark
		if ($object->statut == 0 && (!empty($conf->global->CONTRACT_DRAFT_WATERMARK)))
		{
			pdf_watermark($pdf, $outputlangs, $this->page_hauteur, $this->page_largeur, 'mm', $conf->global->CONTRACT_DRAFT_WATERMARK);
		}

		//Prepare next
		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetFont('', 'B', $default_font_size + 3);

		$posx = $this->page_largeur - $this->marge_droite - 100;
		$posy = $this->marge_haute;

		$pdf->SetXY($this->marge_gauche, $posy);

		// Logo
		$logo = $conf->mycompany->dir_output.'/logos/'.$this->emetteur->logo;
		if ($this->emetteur->logo)
		{
			if (is_readable($logo))
			{
				$height = pdf_getHeightForLogo($logo);
				$pdf->Image($logo, $this->marge_gauche, $posy, 0, $height); // width=0 (auto)
			} else {
				$pdf->SetTextColor(200, 0, 0);
				$pdf->SetFont('', 'B', $default_font_size - 2);
				$pdf->MultiCell(100, 3, $outputlangs->transnoentities("ErrorLogoFileNotFound", $logo), 0, 'L');
				$pdf->MultiCell(100, 3, $outputlangs->transnoentities("ErrorGoToGlobalSetup"), 0, 'L');
			}
		} else {
			$text = $this->emetteur->name;
			$pdf->MultiCell(100, 4, $outputlangs->convToOutputCharset($text), 0, 'L');
		}

		$pdf->SetFont('', 'B', $default_font_size + 3);
		$pdf->SetXY($posx, $posy);
		$pdf->SetTextColor(0, 0, 60);
		$title = $outputlangs->transnoentities("TrackingNumber");
		$pdf->MultiCell(100, 4, $title, '', 'R');

		$pdf->SetFont('', 'B', $default_font_size + 2);

		$posy += 5;
		$pdf->SetXY($posx, $posy);
		$pdf->SetTextColor(0, 0, 60);
		$pdf->MultiCell(100, 4, $outputlangs->transnoentities("Ref")." : ".$outputlangs->convToOutputCharset($object->ref), '', 'R');

		$posy += 1;
		$pdf->SetFont('', '', $default_font_size);

		$posy += 4;
		$pdf->SetXY($posx, $posy);
		$pdf->SetTextColor(0, 0, 60);
		$pdf->MultiCell(100, 3, $outputlangs->transnoentities("Date")." : ".dol_print_date($object->date_creation, "day", false, $outputlangs, true), '', 'R');

		if ($object->thirdparty->code_client)
		{
			$posy += 4;
			$pdf->SetXY($posx, $posy);
			$pdf->SetTextColor(0, 0, 60);
			$pdf->MultiCell(100, 3, $outputlangs->transnoentities("CustomerCode")." : ".$outputlangs->transnoentities($object->thirdparty->code_client), '', 'R');
		}

		if ($showaddress)
		{
			// Sender properties
			$carac_emetteur = '';
			// Add internal contact of proposal if defined
			$arrayidcontact = $object->getIdContact('internal', 'INTERREPFOLL');
			if (count($arrayidcontact) > 0)
			{
				$object->fetch_user($arrayidcontact[0]);
				$carac_emetteur .= ($carac_emetteur ? "\n" : '').$outputlangs->transnoentities("Name").": ".$outputlangs->convToOutputCharset($object->user->getFullName($outputlangs))."\n";
			}

			$carac_emetteur .= pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, '', 0, 'source', $object);

			// Show sender
			$posy = 42;
			$posx = $this->marge_gauche;
			if (!empty($conf->global->MAIN_INVERT_SENDER_RECIPIENT)) $posx = $this->page_largeur - $this->marge_droite - 80;
			$hautcadre = 40;

			// Show sender frame
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetFont('', '', $default_font_size - 2);
			$pdf->SetXY($posx, $posy - 5);
			$pdf->SetXY($posx, $posy);
			$pdf->SetFillColor(230, 230, 230);
			$pdf->MultiCell(82, $hautcadre, "", 0, 'R', 1);

			// Show sender name
			$pdf->SetXY($posx + 2, $posy + 3);
			$pdf->SetTextColor(0, 0, 60);
			$pdf->SetFont('', 'B', $default_font_size);
			$pdf->MultiCell(80, 3, $outputlangs->convToOutputCharset($this->emetteur->name), 0, 'L');
			$posy = $pdf->getY();

			// Show sender information
			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->SetXY($posx + 2, $posy);
			$pdf->MultiCell(80, 4, $carac_emetteur, 0, 'L');


			// If CUSTOMER contact defined, we use it
			$usecontact = false;
			$arrayidcontact = $object->getIdContact('external', 'CUSTOMER');
			if (count($arrayidcontact) > 0)
			{
				$usecontact = true;
				$result = $object->fetch_contact($arrayidcontact[0]);
			}

			$this->recipient = $object->thirdparty;

			// Recipient name
			if ($usecontact && ($object->contact->fk_soc != $object->thirdparty->id && (!isset($conf->global->MAIN_USE_COMPANY_NAME_OF_CONTACT) || !empty($conf->global->MAIN_USE_COMPANY_NAME_OF_CONTACT)))) {
				$thirdparty = $object->contact;
			} else {
				$thirdparty = $object->thirdparty;
			}

			$this->recipient->name = pdfBuildThirdpartyName($thirdparty, $outputlangs);

			$contact = new Contact($this->db);
			$contact->fetch($object->fk_contact);
			$carac_client = pdf_build_address($outputlangs, $this->recipient, $object->thirdparty, $contact, 1, 'target', $object);

			// Show recipient
			$widthrecbox = 100;
			if ($this->page_largeur < 210) $widthrecbox = 84; // To work with US executive format
			$posy = 42;
			$posx = $this->page_largeur - $this->marge_droite - $widthrecbox;
			if (!empty($conf->global->MAIN_INVERT_SENDER_RECIPIENT)) $posx = $this->marge_gauche;

			// Show recipient frame
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetFont('', '', $default_font_size - 2);
			$pdf->SetXY($posx + 2, $posy - 5);
			$pdf->Rect($posx, $posy, $widthrecbox, $hautcadre);
			$pdf->SetTextColor(0, 0, 0);

			// Show recipient name
			$pdf->SetXY($posx + 2, $posy + 3);
			$pdf->SetFont('', 'B', $default_font_size);
			$pdf->MultiCell($widthrecbox, 4, $this->recipient->name, 0, 'L');

			$posy = $pdf->getY();

			// Show recipient information
			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->SetXY($posx + 2, $posy);
			$pdf->MultiCell($widthrecbox, 4, $carac_client, 0, 'L');
		}
	}

	/**
	 *  Show joined files after expense report. Need this->emetteur object
	 *
	 *  @param  PDF			$pdf     			PDF
	 *  @param  Object		$object				Object to show
	 *  @param  Translate	$outputlangs		Object lang for output
	 *  @param  int			$hidefreetext		1=Hide free text
	 *  @return int								Return height of bottom margin including footer text
	 */

	protected function _joinedFiles(&$pdf, $object, $outputlangs, $hidefreetext = 0)
	{
		global $conf;
		$showdetails = $conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS;

		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
		require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';

		$DPI 		= 96;
		$MM_IN_INCH = 25.4;
		$A4_HEIGHT 	= 297;
		$A4_WIDTH 	= 210;
		$MAX_WIDTH 	= 800;
		$MAX_HEIGHT = 500;

		$upload_dir 	= $conf->doliletter->multidir_output[$conf->entity ?: $conf->entity] . '/envelope/' . $object->ref . '/trackingnumber/uploaded_file';
		$arrayoffiles 	= dol_dir_list($upload_dir);

		if ( !empty( $arrayoffiles ) ) {
			foreach ($arrayoffiles as $file) {

				$proofFilename = $file['name'];
				$pdfname = $file['level1name'] . '.pdf';
				$filename = $file['fullname'];

				if (preg_match('/\.(jpg|png|jpeg)$/', $file['name'])) {

					list($width, $height, $type) = getimagesize($filename);

					$ratio = $width / $height;
					$portrait = $height > $width ? true : false;
					$widthScale = $MAX_WIDTH / $width;
					$heightScale = $MAX_HEIGHT / $height;

					$scale = min($widthScale, $heightScale);

					$width = round($scale * $width * $MM_IN_INCH / $DPI);
					$height = round($scale * $height * $MM_IN_INCH / $DPI);

					$pdf->AddPage($portrait ? 'P' : 'L');
					$pagenb++;
					$pdf->SetXY($this->marge_gauche, $this->marge_haute);

					if (!$portrait) {
						$pdf->Cell(100, 0, $proofFilename, 1, 1, 'C', $pdf->Image(
							$filename, ($A4_HEIGHT - $width) / 2,
							($A4_WIDTH - $height) / 2,
							$width,
							$height
						));
					} else {
						$pdf->Cell(100, 0, $proofFilename, 1, 1, 'C', $pdf->Image(
							$filename, ($A4_WIDTH - $width) / 2,
							($A4_HEIGHT - $height) / 2,
							$width,
							$height
						));
					}

				} else if (preg_match('/\.(pdf)$/', $file['name']) && $pdfname !== $file['name']) {
					//Rajouter condition pour que si le pdf n'a pas de trailer il y ait une image par défaut

					$pagesNbr = $pdf->setSourceFile($filename);

					for ($p = 1; $p <= $pagesNbr; $p++) {

						$templateIdx = $pdf->ImportPage($p);
						$size = $pdf->getTemplatesize($templateIdx);
						$portrait = $size['h'] > $size['w'] ? true : false;

						$pdf->AddPage($portrait ? 'P' : 'L');
						$pagenb++;
						$pdf->SetXY($this->marge_gauche - 5, $this->marge_haute - 5);
						$pdf->Cell(100, 0, $proofFilename, 1, 1, 'C', $pdf->useTemplate($templateIdx));
					}
				}
			}
		}
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *   	Show footer of page. Need this->emetteur object
	 *
	 *   	@param	PDF			$pdf     			PDF
	 * 		@param	Contrat		$object				Object to show
	 *      @param	Translate	$outputlangs		Object lang for output
	 *      @param	int			$hidefreetext		1=Hide free text
	 *      @return	integer
	 */
	protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
	{
		global $conf;
		if ($object->status == 3) {
			$this->_joinedFiles($pdf, $object, $outputlangs);
		}
		$showdetails = empty($conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS) ? 0 : $conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS;
		return pdf_pagefoot($pdf, $outputlangs, 'CONTRACT_FREE_TEXT', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext);
	}
}
