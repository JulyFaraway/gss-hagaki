<?php
/**
 * Just do it.
 */

require_once(__DIR__.'/../vendor/autoload.php');

use GSSHagaki\GSSHagaki;

$url    = 'https://docs.google.com/spreadsheets/d/18D7qa_EpXJLGxpQoAqp2jdKbM-JRisOOJ7RMPniXhyo/edit?usp=sharing';
$hagaki = new GSSHagaki($url);

