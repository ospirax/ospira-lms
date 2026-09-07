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

namespace theme_ospira\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Renderer overrides for theme_ospira: Google Fonts injection, the
 * dark-mode toggle, and the custom Dashboard/My Courses block regions.
 * Picked up automatically by theme_overridden_renderer_factory (see
 * config.php).
 *
 * @package theme_ospira
 */
class core_renderer extends \theme_boost\output\core_renderer {

    /**
     * Adds the Poppins Google Fonts <link> tags. Not a CSS @import in the
     * SCSS pipeline - scssphp treats a remote @import as a local file
     * import and throws when it can't find one.
     *
     * @return string
     */
    public function standard_head_html(): string {
        $fonts = '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n"
            . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n"
            . '<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">' . "\n";

        return $fonts . parent::standard_head_html();
    }

    /**
     * Adds the dark-mode toggle button and its script right after <body>
     * opens. Not standard_end_of_body_html() - Boost nests that inside a
     * "footer-content-popover" that's display:none until opened, making
     * the button unclickable. Toggles an "ospira-dark" class on <html> and
     * remembers the choice in localStorage; CSS lives in scss/ospira.scss.
     *
     * @return string
     */
    public function standard_top_of_body_html(): string {
        $toggle = <<<HTML
<button id="ospira-theme-toggle" aria-label="Toggle dark mode" title="Toggle dark mode">&#9728;</button>
<script>
(function () {
    var STORAGE_KEY = 'ospira-theme';
    var htmlEl = document.documentElement;
    var btn = document.getElementById('ospira-theme-toggle');
    if (!btn) {
        return;
    }

    function applyTheme(isDark) {
        htmlEl.classList.toggle('ospira-dark', isDark);
        btn.textContent = isDark ? '☾' : '☀';
    }

    var saved = localStorage.getItem(STORAGE_KEY);
    applyTheme(saved === 'dark');

    btn.addEventListener('click', function () {
        var nowDark = !htmlEl.classList.contains('ospira-dark');
        applyTheme(nowDark);
        localStorage.setItem(STORAGE_KEY, nowDark ? 'dark' : 'light');
    });
})();
</script>
HTML;

        return parent::standard_top_of_body_html() . $toggle;
    }

    /**
     * Swaps Moodle's default My Moodle blocks (Timeline, Calendar, Course
     * overview) for a custom Ospira dashboard, without touching
     * /my/index.php or the "Dashboard" nav link.
     *
     * Matched on pagelayout, not pagetype - /my/courses.php shares the
     * same pagetype ('my-index') as /my/index.php despite being a
     * different page, which was rendering the Dashboard hero on My
     * Courses too. pagelayout ('mydashboard' vs 'mycourses')
     * distinguishes them correctly.
     *
     * @param string $regionname
     * @return string
     */
    public function custom_block_region($regionname) {
        if ($regionname === 'content' && $this->page->pagelayout === 'mydashboard') {
            return $this->render_from_template('theme_ospira/dashboard', theme_ospira_get_dashboard_context());
        }

        // My Courses: block_myoverview already has real search/sort/
        // filter/favourite functionality - prepend a custom header above
        // it instead of replacing it.
        if ($regionname === 'content' && $this->page->pagelayout === 'mycourses') {
            $header = $this->render_from_template('theme_ospira/mycourses-header', theme_ospira_get_mycourses_context());
            return $header . parent::custom_block_region($regionname);
        }

        return parent::custom_block_region($regionname);
    }
}
