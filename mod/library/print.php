<?php

global $CFG;

require_once("../../config.php");
require_once("lib.php");

$id            = required_param('id', PARAM_INT);                     // Course Module ID
$sortorder     = optional_param('sortorder', 'asc', PARAM_ALPHA);     // Sorting order
$offset        = optional_param('offset', 0, PARAM_INT);              // number of entries to bypass
$pagelimit     = optional_param('pagelimit', 0, PARAM_INT);           // Number of entries per page, 0 if unlimited.
$displayformat = optional_param('displayformat',-1, PARAM_SAFEDIR);

$mode    = required_param('mode', PARAM_ALPHA);             // mode to show the entries
$hook    = optional_param('hook','ALL', PARAM_CLEAN);       // what to show
$sortkey = optional_param('sortkey','UPDATE', PARAM_ALPHA); // Sorting key

$url = new moodle_url('/mod/library/print.php', array('id'=>$id));
if ($sortorder !== 'asc') {
    $url->param('sortorder', $sortorder);
}
if ($offset !== 0) {
    $url->param('offset', $offset);
}
if ($displayformat !== -1) {
    $url->param('displayformat', $displayformat);
}
if ($sortkey !== 'UPDATE') {
    $url->param('sortkey', $sortkey);
}
if ($mode !== 'letter') {
    $url->param('mode', $mode);
}
if ($hook !== 'ALL') {
    $url->param('hook', $hook);
}
$PAGE->set_url($url);

if (! $cm = get_coursemodule_from_id('library', $id)) {
    print_error('invalidcoursemodule');
}

if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
    print_error('coursemisconf');
}

if (! $library = $DB->get_record("library", array("id"=>$cm->instance))) {
    print_error('invalidid', 'library');
}

if ($pagelimit < 0) {
    $pagelimit = 0;
}

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);

// Prepare format_string/text options
$fmtoptions = array(
    'context' => $context);

$PAGE->set_pagelayout('print');
$PAGE->set_title(get_string("modulenameplural", "library"));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

if (!has_capability('mod/library:manageentries', $context) and !$library->allowprintview) {
    notice(get_string('printviewnotallowed', 'library'));
}

/// setting the default values for the display mode of the current library
/// only if the library is viewed by the first time
if ( $dp = $DB->get_record('library_formats', array('name'=>$library->displayformat)) ) {
    $printpivot = $dp->showgroup;
    if ( $mode == '' and $hook == '' and $show == '') {
        $mode      = $dp->defaultmode;
        $hook      = $dp->defaulthook;
        $sortkey   = $dp->sortkey;
        $sortorder = $dp->sortorder;
    }
} else {
    $printpivot = 1;
    if ( $mode == '' and $hook == '' and $show == '') {
        $mode = 'letter';
        $hook = 'ALL';
    }
}

if ( $displayformat == -1 ) {
     $displayformat = $library->displayformat;
}

/// stablishing flag variables
if ( $sortorder = strtolower($sortorder) ) {
    if ($sortorder != 'asc' and $sortorder != 'desc') {
        $sortorder = '';
    }
}
if ( $sortkey = strtoupper($sortkey) ) {
    if ($sortkey != 'CREATION' and
        $sortkey != 'UPDATE' and
        $sortkey != 'FIRSTNAME' and
        $sortkey != 'LASTNAME'
        ) {
        $sortkey = '';
    }
}

switch ( $mode = strtolower($mode) ) {
case 'entry':  /// Looking for a certain entry id
    $tab = LIBRARY_STANDARD_VIEW;
break;

case 'cat':    /// Looking for a certain cat
    $tab = LIBRARY_CATEGORY_VIEW;
    if ( $hook > 0 ) {
        $category = $DB->get_record("library_categories", array("id"=>$hook));
    }
break;

case 'approval':    /// Looking for entries waiting for approval
    $tab = LIBRARY_APPROVAL_VIEW;
    if ( !$hook and !$sortkey and !$sortorder) {
        $hook = 'ALL';
    }
break;

case 'term':   /// Looking for entries that include certain term in its concept, definition or aliases
    $tab = LIBRARY_STANDARD_VIEW;
break;

case 'date':
    $tab = LIBRARY_DATE_VIEW;
    if ( !$sortkey ) {
        $sortkey = 'UPDATE';
    }
    if ( !$sortorder ) {
        $sortorder = 'desc';
    }
break;

case 'author':  /// Looking for entries, browsed by author
    $tab = LIBRARY_AUTHOR_VIEW;
    if ( !$hook ) {
        $hook = 'ALL';
    }
    if ( !$sortkey ) {
        $sortkey = 'FIRSTNAME';
    }
    if ( !$sortorder ) {
        $sortorder = 'asc';
    }
break;

case 'letter':  /// Looking for entries that begin with a certain letter, ALL or SPECIAL characters
default:
    $tab = LIBRARY_STANDARD_VIEW;
    if ( !$hook ) {
        $hook = 'ALL';
    }
break;
}

include_once("sql.php");

$entriesshown = 0;
$currentpivot = '';

$site = $DB->get_record("course", array("id"=>1));

// Print dialog link.
$printtext = get_string('print', 'library');
$printlinkatt = array('onclick' => 'window.print();return false;', 'class' => 'library_no_print printicon');
$printiconlink = html_writer::link('#', $printtext, $printlinkatt);
//echo  html_writer::tag('div', $printiconlink, array('class' => 'displayprinticon'));

//echo html_writer::tag('div', userdate(time()), array('class' => 'displaydate'));

$sitename = get_string("site") . ': <span class="strong">' . format_string($site->fullname) . '</span>';
//echo html_writer::tag('div', $sitename, array('class' => 'sitename'));

$coursename = get_string("course") . ': <span class="strong">' . format_string($course->fullname) . ' ('. format_string($course->shortname) . ')</span>';
//echo html_writer::tag('div', $coursename, array('class' => 'coursename'));

$modname = get_string("modulename","library") . ': <span class="strong">' . format_string($library->name, true) . '</span>';
//echo html_writer::tag('div', $modname, array('class' => 'modname'));

if ( $allentries ) {
    foreach ($allentries as $entry) {

        // Setting the pivot for the current entry.
        if ($printpivot) {

            $pivot = $entry->{$pivotkey};
            $upperpivot = core_text::strtoupper($pivot);
            $pivottoshow = core_text::strtoupper(format_string($pivot, true, $fmtoptions));

            // Reduce pivot to 1cc if necessary.
            if (!$fullpivot) {
                $upperpivot = core_text::substr($upperpivot, 0, 1);
                $pivottoshow = core_text::substr($pivottoshow, 0, 1);
            }

            // If there's a group break.
            if ($currentpivot != $upperpivot) {
                $currentpivot = $upperpivot;

                if ($userispivot) {
                    // Printing the user icon if defined (only when browsing authors).
                    $user = mod_library_entry_query_builder::get_user_from_record($entry);
                    $pivottoshow = fullname($user);
                }
                echo html_writer::tag('div', clean_text($pivottoshow), array('class' => 'mdl-align strong'));
            }
        }
        library_print_entry($course, $cm, $library, $entry, $mode, $hook, 1, $displayformat, true);
    }
    // The all entries value may be a recordset or an array.
    if ($allentries instanceof moodle_recordset) {
        $allentries->close();
    }
}

echo $OUTPUT->footer();

?>
<script>
    window.print();
</script>