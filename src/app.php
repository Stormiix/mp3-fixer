<?php
/**
 * MP3Fixer app | src/app.php
 *
 * @author Stormiix <madadj4@gmail.com>
 * @license BSD 3-Clause License
 * @version 0.0
 * @copyright Copyright (c) 2018, Stormix.co
 */

require '../vendor/autoload.php';

use Stormiix\MP3Fixer\MP3Fixer;

$fixer = new MP3Fixer("input","output");
var_dump($fixer->getInputFiles());

?>
