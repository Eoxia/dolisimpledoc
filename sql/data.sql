INSERT INTO `llx_c_sender_service` (`rowid`, `entity`, `ref`, `label`, `description`, `active`) VALUES(1, 0, 'LRAR', 'LRAR', '', 1);
INSERT INTO `llx_c_sender_service` (`rowid`, `entity`, `ref`, `label`, `description`, `active`) VALUES(2, 0, 'MAIL', 'MAIL', '', 1);
INSERT INTO `llx_c_sender_service` (`rowid`, `entity`, `ref`, `label`, `description`, `active`) VALUES(3, 0, 'FAX', 'FAX', '', 1);

INSERT INTO `llx_c_element_types` (`rowid`, `entity`, `ref`, `label`, `description`, `active`) VALUES(1, 0, 'thirdparty', 'thirdparty', '', 1);
INSERT INTO `llx_c_element_types` (`rowid`, `entity`, `ref`, `label`, `description`, `active`) VALUES(2, 0, 'contract', 'contract', '', 1);
INSERT INTO `llx_c_element_types` (`rowid`, `entity`, `ref`, `label`, `description`, `active`) VALUES(3, 0, 'propal', 'propal', '', 1);
INSERT INTO `llx_c_element_types` (`rowid`, `entity`, `ref`, `label`, `description`, `active`) VALUES(4, 0, 'project', 'project', '', 1);
INSERT INTO `llx_c_element_types` (`rowid`, `entity`, `ref`, `label`, `description`, `active`) VALUES(5, 0, 'facture', 'facture', '', 1);
INSERT INTO `llx_c_element_types` (`rowid`, `entity`, `ref`, `label`, `description`, `active`) VALUES(6, 0, 'order', 'order', '', 1);
INSERT INTO `llx_c_element_types` (`rowid`, `entity`, `ref`, `label`, `description`, `active`) VALUES(7, 0, 'product', 'product', '', 1);

INSERT INTO `llx_c_type_contact` (`rowid`,`element`, `source`, `code`, `libelle`, `active`, `module`, `position`) VALUES(500, 'envelope', 'internal', 'TEST', 'test', 1, null, 0);
INSERT INTO `llx_c_type_contact` (`rowid`,`element`, `source`, `code`, `libelle`, `active`, `module`, `position`) VALUES(501, 'envelope', 'external', 'TEST', 'test', 1, null, 0);
