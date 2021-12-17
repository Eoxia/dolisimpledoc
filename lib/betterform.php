<?php
/* Copyright (C) 2021	Noe Sellam	<noe.sellam@epitech.eu>
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
 * \file    lib/betterform.lib.php
 * \ingroup doliletter
 * \brief   unified form function
 */


function fetchAllAny($objecttype, $sortorder = '', $sortfield = '', $limit = 0, $offset = 0, array $filter = array(), $filtermode = 'AND') {
	global $conf, $db;

	$objecttype = is_string($objecttype) ?: get_class($objecttype);
	$type = new $objecttype($db);
	$records = array();
	$sql = 'SELECT ';
	$sql .= $type->getFieldList();
	$sql .= ' FROM '.MAIN_DB_PREFIX.$type->table_element;
	if (isset($type->ismultientitymanaged) && $type->ismultientitymanaged == 1) $sql .= ' WHERE entity IN ('.getEntity($type->table_element).')';
	else $sql .= ' WHERE 1 = 1';

	// Manage filter
	$sqlwhere = array();
	if (count($filter) > 0) {
		foreach ($filter as $key => $value) {
			if ($key == 'rowid') {
				$sqlwhere[] = $key.'='.$value;
			} elseif (in_array($type->fields[$key]['type'], array('date', 'datetime', 'timestamp'))) {
				$sqlwhere[] = $key.' = \''.$type->db->idate($value).'\'';
			} elseif ($key == 'customsql') {
				$sqlwhere[] = $value;
			} elseif (strpos($value, '%') === false) {
				$sqlwhere[] = $key.' IN ('.$type->db->sanitize($type->db->escape($value)).')';
			} else {
				$sqlwhere[] = $key.' LIKE \'%'.$type->db->escape($value).'%\'';
			}
		}
	}
	if (count($sqlwhere) > 0) {
		$sql .= ' AND ('.implode(' '.$filtermode.' ', $sqlwhere).')';
	}

	if (!empty($sortfield)) {
		$sql .= $type->db->order($sortfield, $sortorder);
	}
	if (!empty($limit)) {
		$sql .= ' '.$type->db->plimit($limit, $offset);
	}

	$resql = $type->db->query($sql);

	if ($resql) {
		$num = $type->db->num_rows($resql);
		$i = 0;
		while ($i < ($limit ? min($limit, $num) : $num))
		{
			$obj = $type->db->fetch_object($resql);

			$record = new Facture($db);
			$record->setVarsFromFetchObj($obj);

			$records[$record->id] = $record;

			$i++;
		}
		$type->db->free($resql);

		return $records;
	} else {
		$type->errors[] = 'Error '.$type->db->lasterror();
		dol_syslog(__METHOD__.' '.join(',', $type->errors), LOG_ERR);

		return -1;
	}
}

/**
 * Prints form to select objects of a given type
 *
 * @param $objecttype object of the type to select from
 * @param string $sortorder Sort Order
 * @param string $sortfield Sort field
 * @param int $limit limit
 * @param int $offset Offset
 * @param array $filter Filter array. Example array('field'=>'valueforlike', 'customurl'=>...)
 * @param string $filtermode Filter mode (AND or OR)
 * @param string $htmlname html form name
 * @param string $htmlid html form id
 * @param array $notid array of int for ids of element not to print (eg. all but thoses ids to be printed
 * @param boolean $multiplechoices wether to allow multiple choices or not
 * @return int                 int <0 if KO, else returns string containing form
 */

function selectForm($objecttype, $htmlname = 'form[]', $htmlid ='', $notid = array(), $sortorder = '', $sortfield = '', $limit = 0, $offset = 0, $filter = array(), $filtermode = 'AND', $multiplechoices = true) {
	//$error = 0;
	$str = '';
	if ($notid === null){
		$notid = array();
	}
	//print '<div> debug  ';
	if ($htmlid == '' && preg_match( '/\[]/', $htmlname))
		$htmlid == substr($htmlname, 0, -2);
	$records =fetchAllAny($objecttype);

	$str .=('<select class="minwidth200" data-select2-id="'.$htmlname.'" name="' . $htmlname . ($multiplechoices ? '" multiple ">' : '">'));
	foreach ($records as $line) {
		if (!in_array($line->id, $notid)) {
			$str .= '<option data-select2-id="'.$line->id.$line->ref.'" value="' . $line->id . '">' . $line->ref . '</option>';
		}
	}
	$str .= '</select>';
	//print 'debug </div>';
	//include_once DOL_DOCUMENT_ROOT.'/core/lib/ajax.lib.php';
	//$str .= ajax_combobox($htmlname);
	return $str;
}
