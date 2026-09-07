<?php
defined('MOODLE_INTERNAL') || die();

$THEME->name = 'ospira';
$THEME->parents = ['boost'];
$THEME->sheets = [];
$THEME->editor_sheets = [];

$THEME->scss = function($theme) {
    return theme_ospira_get_main_scss_content($theme);
};

$THEME->layouts = [
    // Use Boost's layouts as-is - the front page hero is styled via the
    // #page-site-index rules in scss/ospira.scss, not a layout override.
];

$THEME->rendererfactory = 'theme_overridden_renderer_factory';