-- Copyright (C) 2021 EOXIA <dev@eoxia.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.

CREATE TABLE llx_doliletter_letter_sending(
    rowid                   integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
    date_creation           datetime NOT NULL,
    contact_fullname        varchar(255),
    recipient_address varchar(255),
    fk_envelope             integer NOT NULL,
    fk_socpeople            integer NOT NULL,
    fk_user                 integer NOT NULL,
    sender_fullname         varchar(255),
    letter_code             varchar(128),
    status                  integer NOT NULL
) ENGINE=innodb;
