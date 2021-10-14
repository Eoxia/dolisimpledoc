<?php
/* Copyright (C) 2021 Eoxia <dev@eoxia.com>
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
 * 	\defgroup   doliletter     Module DoliLetter
 *  \brief      DoliLetter module descriptor.
 *
 *  \file       htdocs/custom/doliletter/core/modules/modDoliLetter.class.php
 *  \ingroup    doliletter
 *  \brief      Description and activation file for module DoliLetter
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 *  Description and activation class for module doliletter
 */
class modDoliLetter extends DolibarrModules {
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db) {
		global $langs, $conf;

		$this->db = $db;

		$this->numero          = 500000; // TODO Go on page https://wiki.dolibarr.org/index.php/List_of_modules_id to reserve an id number for your module
		$this->rights_class    = 'doliletter';
		$this->family          = '';
		$this->module_position = '';
		$this->familyinfo      = array('Eoxia' => array('position' => '01', 'label' => $langs->trans("Eoxia")));
		$this->name            = preg_replace('/^mod/i', '', get_class($this));
		$this->description     = $langs->trans('DoliLetterDescription');
		$this->descriptionlong = $langs->trans('DoliLetterDescriptionLong');
		$this->editor_name     = 'Eoxia';
		$this->editor_url      = 'https://eoxia.com/';
		$this->version         = '1.0.0';
		$this->const_name      = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto           = 'doliletter@doliletter';

		$this->module_parts = array(
			// Set this to 1 if module has its own trigger directory (core/triggers)
			'triggers' => 0,
			// Set this to 1 if module has its own login method file (core/login)
			'login' => 0,
			// Set this to 1 if module has its own substitution function file (core/substitutions)
			'substitutions' => 0,
			// Set this to 1 if module has its own menus handler directory (core/menus)
			'menus' => 0,
			// Set this to 1 if module overwrite template dir (core/tpl)
			'tpl' => 0,
			// Set this to 1 if module has its own barcode directory (core/modules/barcode)
			'barcode' => 0,
			// Set this to 1 if module has its own models directory (core/modules/xxx)
			'models' => 1,
			// Set this to 1 if module has its own printing directory (core/modules/printing)
			'printing' => 0,
			// Set this to 1 if module has its own theme directory (theme)
			'theme' => 0,
			// Set this to relative path of css file if module has its own css file
			'css' => array(),
			// Set this to relative path of js file if module must load a js on all pages
			'js' => array(),
			// Set here all hooks context managed by module. To find available hook context, make a "grep -r '>initHooks(' *" on source code. You can also set hook context to 'all'
			'hooks' => array(),
			// Set this to 1 if features of module are opened to external users
			'moduleforexternal' => 0,
		);

		$this->dirs = array("/doliletter/temp");

		// Config pages. Put here list of php page, stored into doliletter/admin directory, to use to setup module.
		$this->config_page_url = array("setup.php@doliletter");

		// Dependencies
		$this->hidden       = false;
		$this->depends      = array('modFckEditor'); // List of module class names as string that must be enabled if this module is enabled. Example: array('always1'=>'modModuleToEnable1','always2'=>'modModuleToEnable2', 'FR1'=>'modModuleToEnableFR'...)
		$this->requiredby   = array(); // List of module class names as string to disable if this one is disabled. Example: array('modModuleToDisable1', ...)
		$this->conflictwith = array(); // List of module class names as string this module is in conflict with. Example: array('modModuleToDisable1', ...)

		// The language file dedicated to your module
		$this->langfiles = array("doliletter@doliletter");

		// Prerequisites
		$this->phpmin                = array(5, 6); // Minimum version of PHP required by module
		$this->need_dolibarr_version = array(13, -3); // Minimum version of Dolibarr required by module

		// Messages at activation
		$this->warnings_activation     = array(); // Warning to show when we activate module. array('always'='text') or array('FR'='textfr','ES'='textes'...)
		$this->warnings_activation_ext = array(); // Warning to show when we activate an external module. array('always'='text') or array('FR'='textfr','ES'='textes'...)
		//$this->automatic_activation  = array('FR'=>'DoliLetterWasAutomaticallyActivatedBecauseOfYourCountryChoice');
		//$this->always_enabled        = true; // If true, can't be disabled

		// Constants
		// List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
		// Example: $this->const=array(1 => array('DOLISIMPLEDOC_MYNEWCONST1', 'chaine', 'myvalue', 'This is a constant to add', 1),
		//                             2 => array('DOLISIMPLEDOC_MYNEWCONST2', 'chaine', 'myvalue', 'This is another constant to add', 0, 'current', 1)
		// );
		$this->const = array(
			// CONST DOCUMENT
			1 => array('DOLILETTER_ENVELOPE_ADDON','chaine', 'mod_envelope_standard','', $conf->entity),
		);

		if (!isset($conf->doliletter) || !isset($conf->doliletter->enabled)) {
			$conf->doliletter = new stdClass();
			$conf->doliletter->enabled = 0;
		}

		// Array to add new pages in new tabs
		$this->tabs = array();
		// Example:
		// $this->tabs[] = array('data'=>'objecttype:+tabname1:Title1:mylangfile@doliletter:$user->rights->doliletter->read:/doliletter/mynewtab1.php?id=__ID__');  					// To add a new tab identified by code tabname1
		// $this->tabs[] = array('data'=>'objecttype:+tabname2:SUBSTITUTION_Title2:mylangfile@doliletter:$user->rights->othermodule->read:/doliletter/mynewtab2.php?id=__ID__',  	// To add another new tab identified by code tabname2. Label will be result of calling all substitution functions on 'Title2' key.
		// $this->tabs[] = array('data'=>'objecttype:-tabname:NU:conditiontoremove');                                                     										// To remove an existing tab identified by code tabname

		// Dictionaries
		$this->dictionaries=array(
			'langs'=>'doliletter@doliletter',
			// List of tables we want to see into dictonnary editor
			'tabname'=>array(MAIN_DB_PREFIX."c_sender_service"),
			// Label of tables
			'tablib'=>array("SenderService"),
			// Request to select fields
			'tabsql'=>array('SELECT f.rowid as rowid, f.ref, f.label, f.active FROM '.MAIN_DB_PREFIX.'c_sender_service as f'),
			// Sort order
			'tabsqlsort'=>array("label ASC"),
			// List of fields (result of select to show dictionary)
			'tabfield'=>array("ref,label"),
			// List of fields (list of fields to edit a record)
			'tabfieldvalue'=>array("ref,label"),
			// List of fields (list of fields for insert)
			'tabfieldinsert'=>array("ref,label"),
			// Name of columns with primary key (try to always name it 'rowid')
			'tabrowid'=>array("rowid"),
			// Condition to show each dictionary
			'tabcond'=>array($conf->doliletter->enabled, $conf->doliletter->enabled, $conf->doliletter->enabled)
		);
		/* Example:
		$this->dictionaries=array(
			'langs'=>'doliletter@doliletter',
			// List of tables we want to see into dictonnary editor
			'tabname'=>array(MAIN_DB_PREFIX."table1", MAIN_DB_PREFIX."table2", MAIN_DB_PREFIX."table3"),
			// Label of tables
			'tablib'=>array("Table1", "Table2", "Table3"),
			// Request to select fields
			'tabsql'=>array('SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table1 as f', 'SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table2 as f', 'SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table3 as f'),
			// Sort order
			'tabsqlsort'=>array("label ASC", "label ASC", "label ASC"),
			// List of fields (result of select to show dictionary)
			'tabfield'=>array("code,label", "code,label", "code,label"),
			// List of fields (list of fields to edit a record)
			'tabfieldvalue'=>array("code,label", "code,label", "code,label"),
			// List of fields (list of fields for insert)
			'tabfieldinsert'=>array("code,label", "code,label", "code,label"),
			// Name of columns with primary key (try to always name it 'rowid')
			'tabrowid'=>array("rowid", "rowid", "rowid"),
			// Condition to show each dictionary
			'tabcond'=>array($conf->doliletter->enabled, $conf->doliletter->enabled, $conf->doliletter->enabled)
		);
		*/

		// Boxes/Widgets
		$this->boxes = array();

		// Cronjobs (List of cron jobs entries to add when module is enabled)
		$this->cronjobs = array();

		// Permissions provided by this module
		$this->rights = array();
		$r            = 0;
		/* DoliLetter PERMISSIONS */
		$this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
		$this->rights[$r][1] = $langs->trans('ReadEnvelope');
		$this->rights[$r][4] = 'envelope';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
		$this->rights[$r][1] = $langs->trans('CreateEnvelope');
		$this->rights[$r][4] = 'envelope';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
		$this->rights[$r][1] = $langs->trans('DeleteEnvelope');
		$this->rights[$r][4] = 'envelope';
		$this->rights[$r][5] = 'delete';

		// Main menu entries to add
		$this->menu = array();
		$r          = 0;
		// Add here entries to declare new menus
		$this->menu[$r++] = array(
			'fk_menu'=>'', // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'=>'top', // This is a Top menu entry
			'titre'=>'ModuleDoliLetterName',
			'prefix' => img_picto('', $this->picto, 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu'=>'doliletter',
			'leftmenu'=>'',
			'url'=>'/doliletter/doliletterindex.php',
			'langs'=>'doliletter@doliletter', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position'=>1000 + $r,
			'enabled'=>'$conf->doliletter->enabled', // Define condition to show or hide menu entry. Use '$conf->doliletter->enabled' if entry must be visible if module is enabled.
			'perms'=>'1', // Use 'perms'=>'$user->rights->doliletter->letter->read' if you want your menu with a permission rules
			'target'=>'',
			'user'=>2, // 0=Menu for internal users, 1=external users, 2=both
		);

		$this->menu[$r++] = array(
			'fk_menu'=>'fk_mainmenu=doliletter', // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'=>'left', // This is a Top menu entry
			'titre'=>$langs->trans('DoliLetterIndex'),
			'mainmenu'=>'doliletter',
			'leftmenu'=>'doliletterindex',
			'url'=>'/doliletter/doliletterindex.php',
			'langs'=>'doliletter@doliletter', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position'=>1000 + $r,
			'enabled'=>'$conf->doliletter->enabled', // Define condition to show or hide menu entry. Use '$conf->doliletter->enabled' if entry must be visible if module is enabled.
			'perms'=>'1', // Use 'perms'=>'$user->rights->doliletter->letter->read' if you want your menu with a permission rules
			'target'=>'',
			'user'=>2, // 0=Menu for internal users, 1=external users, 2=both
		);

		$this->menu[$r++]=array(
			'fk_menu'=>'fk_mainmenu=doliletter',	    // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'=>'left', // This is a Left menu entry
			'titre'=>$langs->trans('DoliLetterCreate'),
			'mainmenu'=>'doliletter',
			'leftmenu'=>'enveloppe_card',
			'url'=>'/doliletter/envelope_card.php?action=create',
			'langs'=>'doliletter@doliletter', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position'=>1100+$r,
			'enabled'=>'$conf->doliletter->enabled',  // Define condition to show or hide menu entry. Use '$conf->digiriskdolibarr->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms'=>'1', // Use 'perms'=>'$user->rights->digiriskdolibarr->level1->level2' if you want your menu with a permission rules
			'target'=>'',
			'user'=>0, // 0=Menu for internal users, 1=external users, 2=both
		);

		$this->menu[$r++]=array(
			'fk_menu'=>'fk_mainmenu=doliletter',	    // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'=>'left', // This is a Left menu entry
			'titre'=>'<i class="fas fa-list"></i>  ' . $langs->trans('DoliLetterList'),
			'mainmenu'=>'doliletter',
			'leftmenu'=>'enveloppe_list',
			'url'=>'/doliletter/envelope_list.php',
			'langs'=>'doliletter@doliletter', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position'=>1100+$r,
			'enabled'=>'$conf->doliletter->enabled',  // Define condition to show or hide menu entry. Use '$conf->digiriskdolibarr->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms'=>'1', // Use 'perms'=>'$user->rights->digiriskdolibarr->level1->level2' if you want your menu with a permission rules
			'target'=>'',
			'user'=>0, // 0=Menu for internal users, 1=external users, 2=both
		);
	}

	/**
	 *  Function called when module is enabled.
	 *  The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *  It also creates data directories
	 *
	 *  @param      string  $options    Options when enabling module ('', 'noboxes')
	 *  @return     int             	1 if OK, 0 if KO
	 */
	public function init($options = '') {
		$this->_load_tables('/doliletter/sql/');

		$sql = array();

		return $this->_init($sql, $options);
	}

	/**
	 *  Function called when module is disabled.
	 *  Remove from database constants, boxes and permissions from Dolibarr database.
	 *  Data directories are not deleted
	 *
	 *  @param      string	$options    Options when enabling module ('', 'noboxes')
	 *  @return     int                 1 if OK, 0 if KO
	 */
	public function remove($options = '') {
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
