USE `jia`;

CREATE TABLE `users` (
    `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `first_name` VARCHAR(45) NOT NULL,
    `last_name` VARCHAR(45) NOT NULL,
    `email` VARCHAR(80) NOT NULL,
    `pass` VARCHAR(255) NOT NULL,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `type` CHAR(1) NULL,
    `validation_code` CHAR(12) NOT NULL,
    `ip` VARCHAR(45) NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `email_UNIQUE` (`email` ASC),
    INDEX `login` (`email` ASC, `pass` ASC)
) ENGINE = InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE `instr` (
    `id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(80) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `name_UNIQUE` (`name` ASC)
) ENGINE = InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE `profiles` (
    `user_id` SMALLINT UNSIGNED NOT NULL,
    `instr_id` TINYINT UNSIGNED NULL,
    `bio` TEXT NULL,
    `pic` VARCHAR(100) NULL,
    `links` TINYTEXT NULL,
    PRIMARY KEY (`user_id`),
    CONSTRAINT `fk_user_id`
        FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT `fk_instr_id`
        FOREIGN KEY (`instr_id`)
        REFERENCES `instr` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE = InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE `events` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `venue` VARCHAR(80) NOT NULL,
    `date` DATE NOT NULL,
    `start_time` CHAR(4) NOT NULL,
    `end_time` CHAR(4) NOT NULL,
    `desc` TEXT NULL,
    `title` VARCHAR(80) NOT NULL,
    `user_id` SMALLINT UNSIGNED NOT NULL,
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `band` TEXT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_euser_id`
        FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`)
        ON DELETE CASCADE
) ENGINE = InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE `venues` (
    `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(80) NOT NULL,
    `desc` TEXT NOT NULL,
    `pic` VARCHAR(100) NULL,
    `links` TINYTEXT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE `rm_tokens` (
    `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `selector` CHAR(12) NOT NULL,
    `token` CHAR(64) NOT NULL,
    `user_id` SMALLINT UNSIGNED NOT NULL,
    `expires` DATE NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `selector_UNIQUE` (`selector` ASC),
    CONSTRAINT `fk_tuser_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE `auth_tokens` (
    `user_id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `token` CHAR(64) NOT NULL,
    `expires` DATETIME NOT NULL,
    PRIMARY KEY (`user_id`),
    UNIQUE (`token`)
) ENGINE = InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE `events_profiles` (
    `profile_id` SMALLINT UNSIGNED NOT NULL,
    `event_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`profile_id`, `event_id`),
    CONSTRAINT `fk_profile_id` FOREIGN KEY (`profile_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_event_id` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE `events_venues` (
    `venue_id` SMALLINT UNSIGNED NOT NULL,
    `event_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`venue_id`, `event_id`),
    CONSTRAINT `fk_venue_id` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_vevent_id` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB  DEFAULT CHARSET=utf8;

-- stored procedures

DELIMITER $$
CREATE PROCEDURE get_events (sd DATE, ed DATE)
BEGIN
SELECT id, venue, start_time, end_time, title, DATE_FORMAT(`date`, '%Y-%m-%d') as `fdate` FROM `events`
WHERE `date` BETWEEN sd AND ed
ORDER BY start_time ASC;
END$$
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE get_profile (type VARCHAR(4), uid SMALLINT, uname VARCHAR(91))
BEGIN
IF type = 'id' THEN
SELECT user_id, name AS instr_name, bio, pic, links, CONCAT_WS(' ', first_name, last_name) AS name FROM
`profiles` JOIN users ON user_id=users.id JOIN instr ON instr.id=instr_id WHERE user_id=uid;
ELSEIF type = 'name' THEN
SELECT user_id, name AS instr_name, bio, pic, links, CONCAT_WS(' ', first_name, last_name) AS name FROM
`profiles` JOIN users ON user_id=users.id JOIN instr ON instr.id=instr_id WHERE CONCAT_WS(' ', first_name, last_name)=uname;
END IF;
END$$
DELIMITER ;
-- get all profiles ordered by number of instr users and name
DELIMITER $$
CREATE PROCEDURE get_profiles ()
BEGIN
SELECT CONCAT_WS(' ', first_name, last_name) AS name, instr.name as instr_name
FROM `profiles` JOIN instr ON instr_id=instr.id JOIN users ON user_id=users.id
JOIN ( SELECT name, COUNT(*) AS cnt FROM `profiles` JOIN instr ON instr_id=id GROUP BY name) c ON c.name=instr.name
ORDER BY c.cnt DESC, name ASC;
END$$
DELIMITER ;
-- get all venues ordered by number of events
DELIMITER $$
CREATE PROCEDURE get_venues ()
BEGIN
SELECT name, pic, venues.id, `desc`
FROM venues
LEFT OUTER JOIN ( SELECT venue_id, COUNT(*) AS cnt FROM `events_venues` JOIN venues ON venue_id=venues.id GROUP BY venue_id) c ON c.venue_id=venues.id
ORDER BY c.cnt DESC, name ASC;
END $$
DELIMITER ;
-- Mass venue insert
DELIMITER $$
CREATE PROCEDURE mass_insert (club VARCHAR(60), _date DATE, _start CHAR(4), _end CHAR(4), _title VARCHAR(80), _id SMALLINT UNSIGNED)
BEGIN
DECLARE _vid SMALLINT;
DECLARE _eid SMALLINT;
DECLARE _pid SMALLINT;
SELECT venues.id INTO _vid FROM venues WHERE venues.name=club;
INSERT INTO `events` (venue, date, start_time, end_time, title, user_id) VALUES (club, _date, _start, _end, _title, _id);
SET _eid=LAST_INSERT_ID();
INSERT INTO events_venues (venue_id, events_venues.event_id) VALUES (_vid, _eid);
SELECT user_id INTO _pid FROM `profiles` WHERE user_id=_id;
IF _pid>=1 THEN
INSERT INTO events_profiles (events_profiles.event_id, profile_id) VALUES (_eid, _pid);
END IF;
END $$
DELIMITER ;

