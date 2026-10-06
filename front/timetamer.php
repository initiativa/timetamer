<?php
declare(strict_types=1);

use GlpiPlugin\Timetamer\TimeTamer;

require_once(__DIR__ . '/../../../front/_check_webserver_config.php');

Session::checkRight('ticket', READ);
Session::checkRight('ticket', UPDATE);
Session::checkRight('contract', READ);

Html::header(TimeTamer::getTypeName(Session::getPluralNumber()), '', 'management', TimeTamer::class);

TimeTamer::displayContent();

Html::footer();

