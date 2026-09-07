<?php
defined('MOODLE_INTERNAL') || die();

function theme_ospira_get_main_scss_content($theme) {
    global $CFG;

    $boostpath = $CFG->dirroot . '/theme/boost/scss';

    // Bootstrap variable overrides first - must precede default.scss, which
    // does @import "bootstrap" internally. See pre.scss for why.
    $scss = file_get_contents($CFG->dirroot . '/theme/ospira/scss/pre.scss');

    // Load Boost's base SCSS next (compiles Bootstrap/Moodle CSS using the
    // overridden variables above).
    $scss .= file_get_contents($boostpath . '/preset/default.scss');

    // Then load our custom overrides/extra rules.
    $scss .= file_get_contents($CFG->dirroot . '/theme/ospira/scss/ospira.scss');

    return $scss;
}

/**
 * Template context for the site front page hero.
 *
 * Rendered by frontpage.php, which core index.php includes via
 * $CFG->customfrontpageinclude. Replaces the hand-pasted Label that used to
 * hold this markup with hardcoded stat numbers.
 *
 * @return array Context for the theme_ospira/frontpage template.
 */
function theme_ospira_get_frontpage_context(): array {
    global $USER, $OUTPUT;

    // Guests and anonymous visitors have no enrolments; skip the queries
    // rather than running them to return a guaranteed zero.
    $loggedin = isloggedin() && !isguestuser();
    $courses = $loggedin ? enrol_get_all_users_courses($USER->id, true) : [];

    return [
        'loggedin' => $loggedin,
        // The parent is the learner - these are their own enrolments and
        // their own coursework, not a child's.
        'coursecount' => count($courses),
        'pendingcount' => $loggedin
            ? theme_ospira_count_pending_tasks($USER->id, array_keys($courses))
            : 0,
        'herophotourl' => $OUTPUT->image_url('hero/parent', 'theme_ospira')->out(false),
        'dashboardurl' => (new moodle_url('/my/'))->out(false),
        'courseindexurl' => (new moodle_url('/course/index.php'))->out(false),
    ];
}

/**
 * Outstanding Assignment/Quiz activities for $userid across $courseids.
 *
 * "Outstanding" deliberately does not hang on activity completion alone.
 * This used to require cm.completion > 0, which silently dropped every
 * activity whose teacher left completion tracking switched off - e.g.
 * "Module 4 Assessment" in the Ages 1-5 course, which never appeared in
 * Pending Tasks no matter how long it went undone. Completion state is
 * still trusted where tracking is on; real submission/attempt state is
 * the fallback where it isn't.
 *
 * Ordered dated-first (soonest due), then in course order - by course
 * sortorder and section number rather than cm.id, because cm.id is
 * creation order and put Module 7 ahead of Module 6 in the Ages 1-5
 * course, where the Module 6 quiz was added later.
 *
 * Shared by the Dashboard list and by the Dashboard/My Courses stat
 * tiles, so the count can never disagree with the list beneath it.
 *
 * Also returns the course and section the task sits in. Activity names
 * alone are not enough to identify a task: several courses reuse the same
 * module names, and one course names every quiz simply "Assessment", so a
 * bare list shows rows that look identical.
 *
 * @param int $userid
 * @param int[] $courseids
 * @return stdClass[] cmid, modname, taskname, duedate, coursename,
 *                    sectionname - keyed by cmid.
 */
function theme_ospira_get_pending_tasks(int $userid, array $courseids): array {
    global $DB;

    if (empty($courseids)) {
        return [];
    }

    list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
    $inparams['completionuser'] = $userid;
    $inparams['submissionuser'] = $userid;
    $inparams['attemptuser'] = $userid;

    // assign stores its deadline as duedate, quiz as timeclose; both are 0
    // rather than NULL when unset.
    $due = "CASE WHEN m.name = 'assign' THEN a.duedate ELSE q.timeclose END";

    $sql = "SELECT cm.id AS cmid, m.name AS modname,
                   CASE WHEN m.name = 'assign' THEN a.name ELSE q.name END AS taskname,
                   $due AS duedate,
                   c.fullname AS coursename,
                   cs.name AS sectionname
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module AND m.name IN ('assign', 'quiz')
              JOIN {course} c ON c.id = cm.course
              JOIN {course_sections} cs ON cs.id = cm.section
         LEFT JOIN {assign} a ON m.name = 'assign' AND a.id = cm.instance
         LEFT JOIN {quiz} q ON m.name = 'quiz' AND q.id = cm.instance
         LEFT JOIN {course_modules_completion} cmc
                ON cmc.coursemoduleid = cm.id AND cmc.userid = :completionuser
             WHERE cm.course $insql
               AND cm.visible = 1
               AND cm.deletioninprogress = 0
               AND NOT (cm.completion > 0 AND COALESCE(cmc.completionstate, 0) > 0)
               AND NOT EXISTS (
                       SELECT 1
                         FROM {assign_submission} asub
                        WHERE m.name = 'assign'
                          AND asub.assignment = cm.instance
                          AND asub.userid = :submissionuser
                          AND asub.latest = 1
                          AND asub.status = 'submitted')
               AND NOT EXISTS (
                       SELECT 1
                         FROM {quiz_attempts} qa
                        WHERE m.name = 'quiz'
                          AND qa.quiz = cm.instance
                          AND qa.userid = :attemptuser
                          AND qa.state = 'finished')
          ORDER BY (COALESCE($due, 0) = 0) ASC, duedate ASC,
                   c.sortorder ASC, cs.section ASC, cm.id ASC";

    return $DB->get_records_sql($sql, $inparams);
}

/**
 * Whether two display names say effectively the same thing, ignoring case,
 * punctuation and spacing - "MODULE 2 Understanding Child Development" and
 * "Module 2- Understanding Child Development" are the same name.
 *
 * Used to keep the Dashboard's task context line informative: most sections
 * here hold a single activity named after the section, so printing both
 * repeats the title back at the reader. Where the activity is named
 * something generic like "Assessment", the section is the only thing that
 * identifies it and must be shown.
 *
 * @param string $a
 * @param string $b
 * @return bool
 */
function theme_ospira_names_overlap(string $a, string $b): bool {
    $normalise = static function (string $value): string {
        $value = core_text::strtolower($value);
        // Generic activity words carry no meaning here - "Module 3
        // Assessment - Building Strong Family Relationships" and "MODULE 3
        // Building Strong Family Relationships" are the same name. Dropping
        // them also empties a name like "Assessment" on its own, which then
        // correctly compares as no overlap, so its section is kept.
        $value = preg_replace('/\b(assessment|assignment|quiz|test|activity|task)s?\b/', '', $value);
        return (string) preg_replace('/[^a-z0-9]+/', '', (string) $value);
    };

    $a = $normalise($a);
    $b = $normalise($b);

    if ($a === '' || $b === '') {
        return false;
    }

    return str_contains($a, $b) || str_contains($b, $a);
}

/**
 * Count of outstanding Assignment/Quiz activities, for the Dashboard and
 * My Courses stat tiles. Counts exactly what
 * theme_ospira_get_pending_tasks() lists.
 *
 * @param int $userid
 * @param int[] $courseids
 * @return int
 */
function theme_ospira_count_pending_tasks(int $userid, array $courseids): int {
    return count(theme_ospira_get_pending_tasks($userid, $courseids));
}

/**
 * Template context for the custom parent-facing Dashboard (replaces
 * Moodle's default My Moodle blocks - see
 * classes/output/core_renderer.php::custom_block_region()).
 *
 * The parent is the learner: courses, progress and pending tasks are all
 * the parent's own. Courses are grouped by the child's age bracket, but
 * it is the parent who enrols and completes the coursework.
 *
 * @return array Context for the theme_ospira/dashboard template.
 */
function theme_ospira_get_dashboard_context(): array {
    global $USER, $OUTPUT, $CFG;

    require_once($CFG->dirroot . '/course/lib.php');
    require_once($CFG->dirroot . '/completion/classes/progress.php');

    $courses = enrol_get_all_users_courses($USER->id, true);

    $courseprogress = [];
    $progresstotal = 0;
    $progresscount = 0;

    foreach ($courses as $course) {
        $percentage = \core_completion\progress::get_course_progress_percentage($course, $USER->id);
        $percentage = $percentage === null ? null : (int) round($percentage);

        if ($percentage !== null) {
            $progresstotal += $percentage;
            $progresscount++;
        }

        $courseprogress[] = [
            'id' => $course->id,
            'fullname' => format_string($course->fullname),
            'url' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'hasprogress' => $percentage !== null,
            'progress' => $percentage,
        ];
    }

    $averageprogress = $progresscount > 0 ? (int) round($progresstotal / $progresscount) : 0;

    // Same core function the stock "Recently accessed courses" block uses.
    $recentcourses = course_get_recent_courses($USER->id, 4);
    $recentlyaccessed = [];

    foreach ($recentcourses as $course) {
        $image = \core_course\external\course_summary_exporter::get_course_image($course);

        $recentlyaccessed[] = [
            'id' => $course->id,
            'fullname' => format_string($course->fullname),
            'url' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'imageurl' => $image ?: $OUTPUT->get_generated_image_for_id($course->id),
        ];
    }

    // Every outstanding task, not a truncated preview - a capped list read
    // as "Module 4 and Module 7 are missing" rather than as "there is more
    // below", which is the opposite of what a Pending Tasks list is for.
    $records = theme_ospira_get_pending_tasks($USER->id, array_keys($courses));
    $pendingtasks = [];

    foreach ($records as $record) {
        $viewscript = $record->modname === 'quiz' ? '/mod/quiz/view.php' : '/mod/assign/view.php';
        $hasduedate = !empty($record->duedate);

        // Sections are unnamed on some courses, and elsewhere named after
        // the single activity they contain. Either way show the course
        // alone rather than an empty separator or a repeat of the title.
        $sectionname = trim((string) $record->sectionname);

        if ($sectionname !== '' && theme_ospira_names_overlap($record->taskname, $sectionname)) {
            $sectionname = '';
        }

        $pendingtasks[] = [
            'name' => format_string($record->taskname),
            'coursename' => format_string($record->coursename),
            'hassectionname' => $sectionname !== '',
            'sectionname' => $sectionname === '' ? '' : format_string($sectionname),
            'hasduedate' => $hasduedate,
            'duedate' => $hasduedate ? userdate($record->duedate, get_string('strftimedatefullshort', 'langconfig')) : '',
            'url' => (new moodle_url($viewscript, ['id' => $record->cmid]))->out(false),
        ];
    }

    return [
        'firstname' => $USER->firstname,
        'activecoursecount' => count($courses),
        'pendingtaskcount' => count($pendingtasks),
        'averageprogress' => $averageprogress,
        'courseprogress' => $courseprogress,
        'hascourseprogress' => !empty($courseprogress),
        'recentlyaccessed' => $recentlyaccessed,
        'hasrecentlyaccessed' => !empty($recentlyaccessed),
        'pendingtasks' => $pendingtasks,
        'haspendingtasks' => !empty($pendingtasks),
        'courseindexurl' => (new moodle_url('/course/index.php'))->out(false),
        // redirect=0 to reach the real front page - this instance's
        // default-homepage setting otherwise sends / straight back to /my/.
        'homeurl' => (new moodle_url('/', ['redirect' => 0]))->out(false),
        'herophotourl' => $OUTPUT->image_url('dashboard/family', 'theme_ospira')->out(false),
    ];
}

/**
 * Template context for the custom header prepended above Moodle's own
 * "Course overview" block (block_myoverview) on the My Courses page - see
 * classes/output/core_renderer.php::custom_block_region(). Only the
 * header is custom; the real block renders untouched straight after it.
 *
 * @return array Context for the theme_ospira/mycourses-header template.
 */
function theme_ospira_get_mycourses_context(): array {
    global $USER, $CFG;

    require_once($CFG->dirroot . '/course/lib.php');
    require_once($CFG->dirroot . '/completion/classes/progress.php');

    $courses = enrol_get_all_users_courses($USER->id, true);

    $completedcount = 0;
    $progresstotal = 0;
    $progresscount = 0;

    foreach ($courses as $course) {
        $percentage = \core_completion\progress::get_course_progress_percentage($course, $USER->id);

        if ($percentage !== null) {
            $percentage = (int) round($percentage);
            $progresstotal += $percentage;
            $progresscount++;

            if ($percentage >= 100) {
                $completedcount++;
            }
        }
    }

    $averageprogress = $progresscount > 0 ? (int) round($progresstotal / $progresscount) : 0;

    return [
        'firstname' => $USER->firstname,
        'activecoursecount' => count($courses),
        'completedcount' => $completedcount,
        'pendingtaskcount' => theme_ospira_count_pending_tasks($USER->id, array_keys($courses)),
        'averageprogress' => $averageprogress,
    ];
}