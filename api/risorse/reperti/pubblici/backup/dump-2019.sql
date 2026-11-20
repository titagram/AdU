-- Esportazione del database del portale dei tributi
-- Server: portale-db01.agenzia-uscite.example
-- Data: 2019-11-14 03:12:07
-- Modalità: completa, senza trigger né procedure
--
-- Struttura della vecchia piattaforma (PHP 5.6, prima della migrazione).

CREATE TABLE `utenti` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codice_fiscale` varchar(16) NOT NULL,
  `password` varchar(32) NOT NULL,
  `ruolo` varchar(20) NOT NULL DEFAULT 'cittadino',
  `email` varchar(120) DEFAULT NULL,
  `ultimo_accesso` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `utenti` VALUES
(1,'RSSMRA62C15H501Q','5f4dcc3b5aa765d61d8327deb882cf99','cittadino','m.rossi@example.it','2019-09-03 08:14:51'),
(2,'BNCPLA55A41L219K','e10adc3949ba59abbe56e057f20f883e','cittadino','p.bianchi@example.it','2019-10-28 19:02:11'),
(3,'FRRGLI71T52F205X','25d55ad283aa400af464c76d713c07ad','funzionario','g.ferrari@agenzia-uscite.example','2019-11-12 07:45:03'),
(4,'MRTTNA48D63H501B','d8578edf8458ce06fbc5bb76a58c5ca4','amministratore','a.marchetti@agenzia-uscite.example','2019-11-13 22:31:40');

CREATE TABLE `cartelle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codice_fiscale` varchar(16) NOT NULL,
  `protocollo` varchar(20) NOT NULL,
  `importo` decimal(12,2) NOT NULL,
  `scadenza` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `cartelle` VALUES
(9001,'RSSMRA62C15H501Q','2019/000112','842.30','2019-12-31'),
(9002,'BNCPLA55A41L219K','2019/000118','1210.00','2019-12-31'),
(9003,'MRTTNA48D63H501B','2019/000205','318.55','2019-11-30');

-- Fine dell'esportazione. File rimosso il prima possibile, era solo di appoggio.
