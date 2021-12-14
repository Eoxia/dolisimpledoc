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
 * \file        class/workunit.class.php
 * \ingroup     digiriskdolibarr
 * \brief       This file is a class file for WorkUnit
 */

/**
 * Class for WorkUnit
 */
require_once __DIR__ . "/envelope_sending.class.php";
class EnvelopeLetter extends EnvelopeSending
{
	/**
	 * @var int  Does this object support multicompany module ?
	 * 0=No test on entity, 1=Test with field entity, 'field@table'=Test with link by field@table
	 */
	public $element = 'EnvelopeLetter';

	public $table_element = 'doliletter_letter_sending';


	/**
	 * @var array  Array with all fields and their property. Do not use it as a static var. It may be modified by constructor.
	 */
	public $fields=array(
		'rowid'         => array('type'=>'integer', 'label'=>'TechnicalID', 'enabled'=>'1', 'position'=>1, 'notnull'=>1, 'visible'=>0, 'noteditable'=>'1', 'index'=>1, 'comment'=>"Id"),
		'fk_envelope'           => array('type'=>'integer', 'label'=>'fk_envelope', 'enabled'=>'1', 'position'=>10, 'notnull'=>1, 'visible'=>1, 'noteditable'=>'1', 'default'=>'(PROV)', 'index'=>1, 'searchall'=>1, 'showoncombobox'=>'1', 'comment'=>"Reference of object"),
		'fk_socpeople'       => array('type'=>'integer', 'label'=>'fk_socpeople', 'enabled'=>'1', 'position'=>20, 'notnull'=>0, 'visible'=>0,),
		'fk_user'       => array('type'=>'integer', 'label'=>'fk_user', 'enabled'=>'1', 'position'=>20, 'notnull'=>0, 'visible'=>0,),
		'sender_fullname'        => array('type'=>'varchar(255)', 'label'=>'sender_fullname', 'enabled'=>'1', 'position'=>30, 'notnull'=>1, 'visible'=>-1,),
		'contact_fullname'        => array('type'=>'varchar(255)', 'label'=>'contact_fullname', 'enabled'=>'1', 'position'=>30, 'notnull'=>1, 'visible'=>-1,),
		'recipient_address' => array('type'=>'varchar(255)', 'label'=>'recipient_adress', 'enabled'=>'1', 'position'=>40, 'notnull'=>1, 'visible'=>-2,),
		'date_creation'           => array('type'=>'datetime', 'label'=>'tms', 'enabled'=>'1', 'position'=>50, 'notnull'=>0, 'visible'=>-2,),
		'status'        => array('type'=>'smallint', 'label'=>'Status', 'enabled'=>'1', 'position'=>70, 'notnull'=>1, 'default' => 1, 'visible'=>1, 'index'=>1,),
		'letter_code'        => array('type'=>'varchar(255) ', 'label'=>'letter_code', 'enabled'=>'1', 'position'=>70, 'notnull'=>1, 'default' => 1, 'visible'=>1, 'index'=>1,),
	);

	public $rowid;
	public $fk_envelope;
	public $fk_socpeople;
	public $contact_fullname;
	public $recipient_address;
	public $date_creation;
	public $status;
	public $letter_code;
	public $fk_user;
	public $sender_fullname;
	/**
	 * @var int  Does this object support multicompany module ?
	 * 0=No test on entity, 1=Test with field entity, 'field@table'=Test with link by field@table
	 */
	public $ismultientitymanaged = 1;

	/**
	 * @var int  Does object support extrafields ? 0=No, 1=Yes
	 */
	public $isextrafieldmanaged = 1;

	/**
	 * @var string String with name of icon for workunit. Must be the part after the 'object_' into object_workunit.png
	 */
	public $picto = 'doliletter32px@doliletter';

	/**
	 * Constructor
	 *
	 * @param DoliDb $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		global $conf, $langs;

		$this->db = $db;

		if (empty($conf->global->MAIN_SHOW_TECHNICAL_ID) && isset($this->fields['rowid'])) $this->fields['rowid']['visible'] = 0;
		if (empty($conf->multicompany->enabled) && isset($this->fields['entity'])) $this->fields['entity']['enabled'] = 0;

		// Unset fields that are disabled
		foreach ($this->fields as $key => $val)
		{
			if (isset($val['enabled']) && empty($val['enabled']))
			{
				unset($this->fields[$key]);
			}
		}

		// Translate some data of arrayofkeyval
		if (is_object($langs))
		{
			foreach ($this->fields as $key => $val)
			{
				if (is_array($val['arrayofkeyval']))
				{
					foreach ($val['arrayofkeyval'] as $key2 => $val2)
					{
						$this->fields[$key]['arrayofkeyval'][$key2] = $langs->trans($val2);
					}
				}
			}
		}
	}
}
