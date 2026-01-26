<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Plugin version information.
 *
 * @package local_oauth2
 * @author Pau Ferrer Ocaña <pferre22@xtec.cat>
 * @author Lai Wei <lai.wei@enovation.ie>
 * @author Dorel Manolescu <dorel.manolescu@enovation.ie>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2025 Enovation Solutions
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component    = 'local_oauth2';
$plugin->release      = '1.0';
$plugin->version      = 2024100705;
$plugin->requires     = 2024100700;
$plugin->supported    = [405, 405];
$plugin->maturity     = MATURITY_STABLE;
