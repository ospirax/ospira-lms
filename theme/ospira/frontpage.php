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
 * Site front page content: the Ospira hero, feature strip and benefits.
 *
 * NOT a standalone page - core /index.php includes this between
 * $OUTPUT->header() and $OUTPUT->footer() when $CFG->customfrontpageinclude
 * points at it, so Moodle's navbar and page chrome stay intact and the
 * stat cards can show real per-user numbers.
 *
 * Enable with:
 *   php admin/cli/cfg.php --name=customfrontpageinclude \
 *       --set=/path/to/moodle/theme/ospira/frontpage.php
 *
 * This replaces the Label activity that previously held the same markup
 * with hardcoded counts. Delete that Label once this is switched on, or the
 * hero renders twice.
 *
 * @package theme_ospira
 */

defined('MOODLE_INTERNAL') || die();

global $CFG, $OUTPUT;

require_once($CFG->dirroot . '/theme/ospira/lib.php');

echo $OUTPUT->render_from_template(
    'theme_ospira/frontpage',
    theme_ospira_get_frontpage_context()
);
