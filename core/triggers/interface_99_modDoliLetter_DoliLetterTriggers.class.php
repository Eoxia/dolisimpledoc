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
 * or see https://www.gnu.org/
 */


/**
 * \file    core/triggers/interface_99_modDoliletter_DoliletterTriggers.class.php
 * \ingroup doliletter
 * \brief   Doliletter trigger.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 *  Class of triggers for Doliletter module
 */
class InterfaceDoliLetterTriggers extends DolibarrTriggers
{
	/**
	 * @var DoliDB Database handler
	 */
	protected $db;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = "demo";
		$this->description = "Doliletter triggers.";
		$this->version = '0.0.3';
		$this->picto = 'Doliletter@Doliletter';
	}

	/**
	 * Trigger name
	 *
	 * @return string Name of trigger file
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Trigger description
	 *
	 * @return string Description of trigger file
	 */
	public function getDesc()
	{
		return $this->description;
	}

	/**
	 * Function called when a Dolibarrr business event is done.
	 * All functions "runTrigger" are triggered if file
	 * is inside directory core/triggers
	 *
	 * @param string 		$action 	Event action code
	 * @param CommonObject 	$object 	Object
	 * @param User 			$user 		Object user
	 * @param Translate 	$langs 		Object langs
	 * @param Conf 			$conf 		Object conf
	 * @return int              		<0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		//echo '<pre>'; print_r( $conf ); echo '</pre>'; exit;
		if (empty($conf->doliletter->enabled)) return 0; // If module is not enabled, we do nothing

		// Data and type of action are stored into $object and $action
		switch ($action) {

			case 'DOLILETTER_ENVELOPE_SENTBYMAIL' :
				//echo '<pre>'; print_r( $object ); echo '</pre>'; exit;
				require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
				$contact_temp = new Contact($this->db);
				require_once __DIR__ . "/../../class/envelope_email.class.php";
				$now = dol_now();
				$mail = new EmailSending($this->db);
				$mail->fk_envelope = $object->id;
				$mail->date_creation = $mail->db->idate($now);
				$mail->status = 1;
				$mail->entity = $object->entity;
				$mail->fk_user = $user->id;
				$mail->sender_fullname = $user->firstname . ' ' . $user->lastname;
				foreach($object->sendtoid as $contactid)
				{
					$mail->fk_socpeople = $contactid;
					$contact_temp->fetch($contactid);
					$mail->recipient_email = $contact_temp->email;
					$mail->contact_fullname = $contact_temp->firstname . ' ' . $contact_temp->lastname;
					$mailtemp = $mail;
					$result = $mailtemp->create($user);
				}

				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
				$now = dol_now();
				$actioncomm = new ActionComm($this->db);
				$contact = new Contact($this->db);
				$signatory = new EnvelopeSignature($this->db);

				$contact->fetch($object->fk_contact);
				$signatory = $signatory->fetchSignatory('E_SENDER', $object->id);

				if (is_array($signatory)) {
					$signatory = array_shift($signatory);
				}

				$actioncomm->elementtype = 'envelope@doliletter';
				$actioncomm->code        = 'AC_DOLILETTER_ENVELOPE_SENTBYMAIL';
				$actioncomm->type_code   = 'AC_OTH_AUTO';
				$actioncomm->label       = $langs->trans('EnvelopeSendbyMailTrigger');
				$actioncomm->note        = $langs->trans('EnvelopeSendbyMailTriggerContent', $contact->firstname . ' ' . $contact->lastname, dol_print_date(dol_now()), $signatory->firstname . ' ' . $signatory->lastname);
				$actioncomm->datep       = $now;
				$actioncomm->socid       = $object->fk_soc;
				$actioncomm->contactid   = $object->fk_contact;
				$actioncomm->contact_id  = $object->fk_contact;
				$actioncomm->socpeopleassigned = array($object->fk_contact => $object->fk_contact);
				$actioncomm->fk_element  = $object->id;
				$actioncomm->userownerid = $user->id;
				$actioncomm->percentage  = -1;

				$actioncomm->create($user);
				break;
			case 'ENVELOPE_LETTER' :
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
				$now = dol_now();
				$actioncomm = new ActionComm($this->db);
				$contact = new Contact($this->db);
				$signatory = new EnvelopeSignature($this->db);
				$letter = new LetterSending($this->db);

				$contact->fetch($object->fk_contact);
				$signatory = $signatory->fetchSignatory('E_SENDER', $object->id);

				if (is_array($signatory)) {
					$signatory = array_shift($signatory);
				}
				$lettersending = $letter->fetchAll('', '', 0, 0, array('customsql' => ' fk_envelope =' . $object->id));
				if (is_array($lettersending)) {
					$lettersending = end($lettersending);
				}

				$actioncomm->elementtype = 'envelope@doliletter';
				$actioncomm->code        = 'AC_ENVELOPE_LETTER';
				$actioncomm->type_code   = 'AC_OTH_AUTO';
				$actioncomm->label       = $langs->trans('EnvelopeSendbyLetterTrigger');
				$actioncomm->note        = $langs->trans('EnvelopeSendbyLetterTriggerContent', $contact->firstname . ' ' . $contact->lastname, dol_print_date(dol_now()), $signatory->firstname . ' ' . $signatory->lastname) . '<br>' . $langs->trans('LetterCode:', $lettersending->letter_code);
				$actioncomm->datep       = $now;
				$actioncomm->socid       = $object->fk_soc;
				$actioncomm->contactid   = $object->fk_contact;
				$actioncomm->contact_id  = $object->fk_contact;
				$actioncomm->socpeopleassigned = array($object->fk_contact => $object->fk_contact);
				$actioncomm->fk_element  = $object->id;
				$actioncomm->userownerid = $user->id;
				$actioncomm->percentage  = -1;

				$actioncomm->create($user);
				break;

			case 'ENVELOPE_CREATE' :
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
				$now = dol_now();
				$actioncomm = new ActionComm($this->db);

				$actioncomm->elementtype = 'envelope@doliletter';
				$actioncomm->code        = 'AC_ENVELOPE_CREATE';
				$actioncomm->type_code   = 'AC_OTH_AUTO';
				$actioncomm->label       = $langs->trans('EnvelopeCreateTrigger');
				$actioncomm->datep       = $now;
				$actioncomm->fk_element  = $object->id;
				$actioncomm->userownerid = $user->id;
				$actioncomm->percentage  = -1;

				$result = $actioncomm->create($user);
				break;
			case 'ENVELOPE_MODIFY' :

				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
				$now = dol_now();
				$actioncomm = new ActionComm($this->db);

				$actioncomm->elementtype = 'envelope@doliletter';
				$actioncomm->code        = 'AC_ENVELOPE_MODIFY';
				$actioncomm->type_code   = 'AC_OTH_AUTO';
				$actioncomm->label       = $langs->trans('EnvelopeModifyTrigger');
				$actioncomm->datep       = $now;
				$actioncomm->fk_element  = $object->id;
				$actioncomm->userownerid = $user->id;
				$actioncomm->percentage  = -1;

				$actioncomm->create($user);
				break;
			case 'ENVELOPE_DELETE' :

				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
				$now = dol_now();
				$actioncomm = new ActionComm($this->db);

				$actioncomm->elementtype = 'envelope@doliletter';
				$actioncomm->code        = 'AC_ENVELOPE_DELETE';
				$actioncomm->type_code   = 'AC_OTH_AUTO';
				$actioncomm->label       = $langs->trans('EnvelopeDeleteTrigger');
				$actioncomm->datep       = $now;
				$actioncomm->fk_element  = $object->id;
				$actioncomm->userownerid = $user->id;
				$actioncomm->percentage  = -1;

				$actioncomm->create($user);
				break;
			case 'ENVELOPE_SIGN' :

				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
				$now = dol_now();
				$actioncomm = new ActionComm($this->db);

				$actioncomm->elementtype = 'envelope@doliletter';
				$actioncomm->code        = 'AC_ENVELOPE_SIGN';
				$actioncomm->type_code   = 'AC_OTH_AUTO';
				$actioncomm->label       = $langs->trans('EnvelopeSignTrigger');
				$actioncomm->datep       = $now;
				$actioncomm->fk_element  = $object->id;
				$actioncomm->userownerid = $user->id;
				$actioncomm->percentage  = -1;

				$actioncomm->create($user);
				break;


			default:
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				break;
		}


		return 0;
	}
}
