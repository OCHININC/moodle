<?php

/// This page prints a particular instance of library
require_once("../../config.php");
require_once("lib.php");
require_once($CFG->libdir . '/completionlib.php');
require_once("$CFG->libdir/rsslib.php");

$id = optional_param('id', 0, PARAM_INT);           // Course Module ID
$g  = optional_param('g', 0, PARAM_INT);            // Library ID

$tab  = optional_param('tab', LIBRARY_NO_VIEW, PARAM_ALPHA);    // browsing entries by categories?
$displayformat = optional_param('displayformat',-1, PARAM_INT);  // override of the library display format

$mode       = optional_param('mode', '', PARAM_ALPHA);           // term entry cat date letter search author approval
$hook       = optional_param('hook', '', PARAM_CLEAN);           // the term, entry, cat, etc... to look for based on mode
$fullsearch = optional_param('fullsearch', 0,PARAM_INT);         // full search (concept and definition) when searching?
$sortkey    = optional_param('sortkey', '', PARAM_ALPHA);// Sorted view: CREATION | UPDATE | FIRSTNAME | LASTNAME...
$sortorder  = optional_param('sortorder', 'ASC', PARAM_ALPHA);   // it defines the order of the sorting (ASC or DESC)
$offset     = optional_param('offset', 0,PARAM_INT);             // entries to bypass (for paging purposes)
$page       = optional_param('page', 0,PARAM_INT);               // Page to show (for paging purposes)
$show       = optional_param('show', '', PARAM_ALPHA);           // [ concept | alias ] => mode=term hook=$show
$category   = null;

if (!empty($id)) {
    if (! $cm = get_coursemodule_from_id('library', $id)) {
        print_error('invalidcoursemodule');
    }
    if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
        print_error('coursemisconf');
    }
    if (! $library = $DB->get_record("library", array("id"=>$cm->instance))) {
        print_error('invalidid', 'library');
    }

} else if (!empty($g)) {
    if (! $library = $DB->get_record("library", array("id"=>$g))) {
        print_error('invalidid', 'library');
    }
    if (! $course = $DB->get_record("course", array("id"=>$library->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance("library", $library->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
    $id = $cm->id;
} else {
    print_error('invalidid', 'library');
}

require_course_login($course->id, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/library:view', $context);

// Prepare format_string/text options
$fmtoptions = array(
    'context' => $context);

require_once($CFG->dirroot . '/comment/lib.php');
comment::init();

/// redirecting if adding a new entry
if ($tab == LIBRARY_ADDENTRY_VIEW ) {
    redirect("edit.php?cmid=$cm->id&amp;mode=$mode");
}

/// setting the defaut number of entries per page if not set
if ( !$entriesbypage = $library->entbypage ) {
    $entriesbypage = $CFG->library_entbypage;
}

// If we have received a page, recalculate offset and page size.
$pagelimit = $entriesbypage;
if ($page > 0 && $offset == 0) {
    $offset = $page * $entriesbypage;
} else if ($page < 0) {
    $offset = 0;
    $pagelimit = 0;
}

/// setting the default values for the display mode of the current library
/// only if the library is viewed by the first time
if ( $dp = $DB->get_record('library_formats', array('name'=>$library->displayformat)) ) {
/// Based on format->defaultmode, we build the defaulttab to be showed sometimes
    $showtabs = library_get_visible_tabs($dp);
    switch ($dp->defaultmode) {
        case 'cat':
            $defaulttab = LIBRARY_CATEGORY_VIEW;

            // Handle defaultmode if 'category' tab is disabled. Fallback to 'standard' tab.
            if (!in_array(LIBRARY_CATEGORY, $showtabs)) {
                $defaulttab = LIBRARY_STANDARD_VIEW;
            }

            break;
        case 'date':
            $defaulttab = LIBRARY_DATE_VIEW;

            // Handle defaultmode if 'date' tab is disabled. Fallback to 'standard' tab.
            if (!in_array(LIBRARY_DATE, $showtabs)) {
                $defaulttab = LIBRARY_STANDARD_VIEW;
            }

            break;
        case 'author':
            $defaulttab = LIBRARY_AUTHOR_VIEW;

            // Handle defaultmode if 'author' tab is disabled. Fallback to 'standard' tab.
            if (!in_array(LIBRARY_AUTHOR, $showtabs)) {
                $defaulttab = LIBRARY_STANDARD_VIEW;
            }

            break;
        default:
            $defaulttab = LIBRARY_STANDARD_VIEW;
    }
/// Fetch the rest of variables
    $printpivot = $dp->showgroup;
    if ( $mode == '' and $hook == '' and $show == '') {
        $mode      = $dp->defaultmode;
        $hook      = $dp->defaulthook;
        $sortkey   = $dp->sortkey;
        $sortorder = $dp->sortorder;
    }
} else {
    $defaulttab = LIBRARY_STANDARD_VIEW;
    $showtabs = array($defaulttab);
    $printpivot = 1;
    if ( $mode == '' and $hook == '' and $show == '') {
        $mode = 'letter';
        $hook = 'ALL';
    }
}

if ( $displayformat == -1 ) {
     $displayformat = $library->displayformat;
}

if ( $show ) {
    $mode = 'term';
    $hook = $show;
    $show = '';
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
case 'search': /// looking for terms containing certain word(s)
    $tab = LIBRARY_STANDARD_VIEW;

    //Clean a bit the search string
    $hook = trim(strip_tags($hook));

break;

case 'entry':  /// Looking for a certain entry id
    $tab = LIBRARY_STANDARD_VIEW;
    if ( $dp = $DB->get_record("library_formats", array("name"=>$library->displayformat)) ) {
        $displayformat = $dp->popupformatname;
    }
break;

case 'cat':    /// Looking for a certain cat
    $tab = LIBRARY_CATEGORY_VIEW;

    // Validation - we don't want to display 'category' tab if it is disabled.
    if (!in_array(LIBRARY_CATEGORY, $showtabs)) {
        $tab = LIBRARY_STANDARD_VIEW;
    }

    if ( $hook > 0 ) {
        $category = $DB->get_record("library_categories", array("id"=>$hook));
    }
break;

case 'approval':    /// Looking for entries waiting for approval
    $tab = LIBRARY_APPROVAL_VIEW;
    // Override the display format with the approvaldisplayformat
    if ($library->approvaldisplayformat !== 'default' && ($df = $DB->get_record("library_formats",
            array("name" => $library->approvaldisplayformat)))) {
        $displayformat = $df->popupformatname;
    }
    if ( !$hook and !$sortkey and !$sortorder) {
        $hook = 'ALL';
    }
break;

case 'term':   /// Looking for entries that include certain term in its concept, definition or aliases
    $tab = LIBRARY_STANDARD_VIEW;
break;

case 'date':
    $tab = LIBRARY_DATE_VIEW;

    // Validation - we dont want to display 'date' tab if it is disabled.
    if (!in_array(LIBRARY_DATE, $showtabs)) {
        $tab = LIBRARY_STANDARD_VIEW;
    }

    if ( !$sortkey ) {
        $sortkey = 'UPDATE';
    }
    if ( !$sortorder ) {
        $sortorder = 'desc';
    }
break;

case 'author':  /// Looking for entries, browsed by author
    $tab = LIBRARY_AUTHOR_VIEW;

    // Validation - we dont want to display 'author' tab if it is disabled.
    if (!in_array(LIBRARY_AUTHOR, $showtabs)) {
        $tab = LIBRARY_STANDARD_VIEW;
    }

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

switch ( $tab ) {
case LIBRARY_IMPORT_VIEW:
case LIBRARY_EXPORT_VIEW:
case LIBRARY_APPROVAL_VIEW:
    $showcommonelements = 0;
break;

default:
    $showcommonelements = 1;
break;
}

// Trigger module viewed event.
library_view($library, $course, $cm, $context, $mode);

/// Printing the heading
$strlibraries = get_string("modulenameplural", "library");
$strlibrary = get_string("modulename", "library");
$strallcategories = get_string("allcategories", "library");
$straddentry = get_string("addentry", "library");
$strnoentries = get_string("noentries", "library");
$strsearchindefinition = get_string("searchindefinition", "library");
$strsearch = get_string("search");
$strwaitingapproval = get_string('waitingapproval', 'library');

/// If we are in approval mode, print special header
$PAGE->set_title($library->name);
$PAGE->set_heading($course->fullname);
$url = new moodle_url('/mod/library/view.php', array('id'=>$cm->id));
if (isset($mode)) {
    $url->param('mode', $mode);
}
$PAGE->set_url($url);
$PAGE->force_settings_menu();

if (!empty($CFG->enablerssfeeds) && !empty($CFG->library_enablerssfeeds)
    && $library->rsstype && $library->rssarticles) {

    $rsstitle = format_string($course->shortname, true, array('context' => context_course::instance($course->id))) . ': '. format_string($library->name);
    rss_add_http_header($context, 'mod_library', $library, $rsstitle);
}

if ($tab == LIBRARY_APPROVAL_VIEW) {
    require_capability('mod/library:approve', $context);
    $PAGE->navbar->add($strwaitingapproval);
    echo $OUTPUT->header();
    echo $OUTPUT->heading($strwaitingapproval);
} else { /// Print standard header
    echo $OUTPUT->header();
}

///Image Header
//echo $OUTPUT->heading(format_string($library->name), 2);
$headerImg = get_library_img('library', $library, $cm->id);

print_library_header($mode,$hook,$strsearch,$fullsearch,$strsearchindefinition,$cm->id, $headerImg, $library->headertitle, $library->headerdescription);
print_category_section($cm, $library, $hook, $category);

/// All this depends if whe have $showcommonelements
if ($showcommonelements) {
/// To calculate available options
    $availableoptions = '';

/// Decide about to print the import link
    /*if (has_capability('mod/library:import', $context)) {
        $availableoptions = '<span class="helplink">' .
                            '<a href="' . $CFG->wwwroot . '/mod/library/import.php?id=' . $cm->id . '"' .
                            '  title="' . s(get_string('importentries', 'library')) . '">' .
                            get_string('importentries', 'library') . '</a>' .
                            '</span>';
    }
/// Decide about to print the export link
    if (has_capability('mod/library:export', $context)) {
        if ($availableoptions) {
            $availableoptions .= '&nbsp;/&nbsp;';
        }
        $availableoptions .='<span class="helplink">' .
                            '<a href="' . $CFG->wwwroot . '/mod/library/export.php?id=' . $cm->id .
                            '&amp;mode='.$mode . '&amp;hook=' . urlencode($hook) . '"' .
                            '  title="' . s(get_string('exportentries', 'library')) . '">' .
                            get_string('exportentries', 'library') . '</a>' .
                            '</span>';
    }*/

/// Decide about to print the approval link
    if (has_capability('mod/library:approve', $context)) {
    /// Check we have pending entries
        if ($hiddenentries = $DB->count_records('library_entries', array('libraryid'=>$library->id, 'approved'=>0))) {
            if ($availableoptions) {
                $availableoptions .= '<br />';
            }
            $availableoptions .='<span class="helplink">' .
                                '<a href="' . $CFG->wwwroot . '/mod/library/view.php?id=' . $cm->id .
                                '&amp;mode=approval' . '"' .
                                '  title="' . s(get_string('waitingapproval', 'library')) . '">' .
                                get_string('waitingapproval', 'library') . ' ('.$hiddenentries.')</a>' .
                                '</span>';
        }
    }

/// Start to print library controls
//        print_box_start('librarycontrol clearfix');
    echo '<div class="librarycontrol" style="text-align: right">';
    echo $availableoptions;

/// The print icon
    if ( $showcommonelements and $mode != 'search' && false) {
        if (has_capability('mod/library:manageentries', $context) or $library->allowprintview) {
            $params = array(
                'id'        => $cm->id,
                'mode'      => $mode,
                'hook'      => $hook,
                'sortkey'   => $sortkey,
                'sortorder' => $sortorder,
                'offset'    => $offset,
                'pagelimit' => $pagelimit
            );
            $printurl = new moodle_url('/mod/library/print.php', $params);
            $printtitle = get_string('printerfriendly', 'library');
            $printattributes = array(
                'class' => 'printicon',
                'title' => $printtitle
            );
            echo html_writer::link($printurl, $printtitle, $printattributes);
        }
    }
/// End library controls
//        print_box_end(); /// librarycontrol
    echo '</div><br />';

//        print_box('&nbsp;', 'clearer');
}

/// Info box
//if ($library->intro && $showcommonelements) {
//    echo $OUTPUT->box(format_module_intro('library', $library, $cm->id), 'generalbox', 'intro');
//}

/// Show the add entry button if allowed
if (has_capability('mod/library:write', $context) && $showcommonelements ) {
    echo '<div class="singlebutton libraryaddentry">';
    echo "<form class=\"form form-inline mb-1\" id=\"newentryform\" method=\"get\" action=\"$CFG->wwwroot/mod/library/edit.php\">";
    echo '<div>';
    echo "<input type=\"hidden\" name=\"cmid\" value=\"$cm->id\" />";
    echo '<input type="submit" value="'.get_string('addentry', 'library').'" class="btn btn-outline-primary" />';
    echo '</div>';
    echo '</form>';
    echo "</div>\n";
}
if (has_capability('mod/library:managecategories', $context) ) {
    echo '<div class="singlebutton">';
        echo "<form class=\"form form-inline mb-1\" id=\"newcategoryform\" method=\"get\" action=\"$CFG->wwwroot/mod/library/editcategories.php\">";
            echo '<div>';
                echo "<input type=\"hidden\" name=\"id\" value=\"$cm->id\" />";
                echo "<input type=\"hidden\" name=\"mode\" value=\"cat\" />";
                echo '<input type="submit" value="'.get_string('editcategories', 'library').'" class="btn btn-outline-primary" />';
            echo '</div>';
        echo '</form>';
    echo "</div>\n";
    echo '<div class="singlebutton">';
        echo "<form class=\"form form-inline mb-1\" id=\"newepicverform\" method=\"get\" action=\"$CFG->wwwroot/mod/library/editepicversions.php\">";
            echo '<div>';
                echo "<input type=\"hidden\" name=\"id\" value=\"$cm->id\" />";
                echo "<input type=\"hidden\" name=\"mode\" value=\"epicver\" />";
                echo '<input type="submit" value="'.get_string('editepicversions', 'library').'" class="btn btn-outline-primary" />';
            echo '</div>';
        echo '</form>';
    echo "</div>\n";
    echo '<div class="singlebutton">';
    echo "<form class=\"form form-inline mb-1\" id=\"newentrytypeform\" method=\"get\" action=\"$CFG->wwwroot/mod/library/editentrytypes.php\">";
        echo '<div>';
            echo "<input type=\"hidden\" name=\"id\" value=\"$cm->id\" />";
            echo "<input type=\"hidden\" name=\"mode\" value=\"entrytype\" />";
            echo '<input type="submit" value="'.get_string('editentrytypes', 'library').'" class="btn btn-outline-primary" />';
        echo '</div>';
    echo '</form>';
echo "</div>\n";
}

require("tabs.php");

require("sql.php");

/// printing the entries
$entriesshown = 0;
$currentpivot = '';
$paging = NULL;

if ($allentries) {
    //Decide if we must show the ALL link in the pagebar
    $specialtext = '';
    if ($library->showall) {
        $specialtext = get_string("allentries","library");
    }

    //Build paging bar
    $baseurl = new moodle_url('/mod/library/view.php', ['id' => $id, 'mode' => $mode, 'hook' => $hook,
        'sortkey' => $sortkey, 'sortorder' => $sortorder, 'fullsearch' => $fullsearch]);
    $paging = library_get_paging_bar($count, $page, $entriesbypage, $baseurl->out() . '&amp;',
        9999, 10, '&nbsp;&nbsp;', $specialtext, -1);

    echo '<div class="paging">';
    echo $paging;
    echo '</div>';

    //load ratings
    require_once($CFG->dirroot.'/rating/lib.php');
    if ($library->assessed != RATING_AGGREGATE_NONE) {
        $ratingoptions = new stdClass;
        $ratingoptions->context = $context;
        $ratingoptions->component = 'mod_library';
        $ratingoptions->ratingarea = 'entry';
        $ratingoptions->items = $allentries;
        $ratingoptions->aggregate = $library->assessed;//the aggregation method
        $ratingoptions->scaleid = $library->scale;
        $ratingoptions->userid = $USER->id;
        $ratingoptions->returnurl = $CFG->wwwroot.'/mod/library/view.php?id='.$cm->id;
        $ratingoptions->assesstimestart = $library->assesstimestart;
        $ratingoptions->assesstimefinish = $library->assesstimefinish;

        $rm = new rating_manager();
        $allentries = $rm->get_ratings($ratingoptions);
    }

    foreach ($allentries as $entry) {

        // Setting the pivot for the current entry
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

                // print the group break if apply

                echo '<div>';
                echo '<table cellspacing="0" class="librarycategoryheader">';

                echo '<tr>';
                if ($userispivot) {
                // printing the user icon if defined (only when browsing authors)
                    echo '<th align="left">';
                    $user = mod_library_entry_query_builder::get_user_from_record($entry);
                    echo $OUTPUT->user_picture($user, array('courseid'=>$course->id));
                    $pivottoshow = fullname($user, has_capability('moodle/site:viewfullnames', context_course::instance($course->id)));
                } else {
                    echo '<th >';
                }

                echo $OUTPUT->heading($pivottoshow, 3);
                echo "</th></tr></table></div>\n";
            }
        }

        /// highlight the term if necessary
        if ($mode == 'search') {
            //We have to strip any word starting by + and take out words starting by -
            //to make highlight works properly
            $searchterms = explode(' ', $hook);    // Search for words independently
            foreach ($searchterms as $key => $searchterm) {
                if (preg_match('/^\-/',$searchterm)) {
                    unset($searchterms[$key]);
                } else {
                    $searchterms[$key] = preg_replace('/^\+/','',$searchterm);
                }
                //Avoid highlight of <2 len strings. It's a well known hilight limitation.
                if (strlen($searchterm) < 2) {
                    unset($searchterms[$key]);
                }
            }
            $strippedsearch = implode(' ', $searchterms);    // Rebuild the string
            $entry->highlight = $strippedsearch;
        }

        /// and finally print the entry.
        library_print_entry($course, $cm, $library, $entry, $mode, $hook,1,$displayformat);
        $entriesshown++;
    }
    // The all entries value may be a recordset or an array.
    if ($allentries instanceof moodle_recordset) {
        $allentries->close();
    }
}
if ( !$entriesshown ) {
    echo $OUTPUT->box(get_string("noentries","library"), "generalbox boxaligncenter boxwidthwide");
}

if (!empty($formsent)) {
    // close the form properly if used
    echo "</div>";
    echo "</form>";
}

if ( $paging ) {
    echo '<hr />';
    echo '<div class="paging">';
    echo $paging;
    echo '</div>';
}
echo '<br />';
library_print_tabbed_table_end();

/// Finish the page
echo $OUTPUT->footer();