<?php

/**
 * @author Alex Wolden
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package moodle crowdauth
 *
 * Authentication Plugin: Atlassian Crowd Authentication
 *
 * Authenticates against Atlassian Crowd
 *
 * 2013-03-22  File created.
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}


require_once($CFG->libdir.'/authlib.php');
require_once('crowd_service.php');


/**
 * Plugin for crowd authentication.
 */
class auth_plugin_crowd extends auth_plugin_base {

    private $crowd_client;

    /**
     * Constructor.
     */
    function auth_plugin_crowd() {

        $this->authtype = 'crowd';
        $this->config = get_config('auth/crowd');
        $this->crowd_client = new crowd_service();

    }

    /**
     * Returns true if the username and password work or don't exist and false
     * if the user exists and the password is wrong.
     *
     * @param string $username The username
     * @param string $password The password
     * @return bool Authentication success or failure.
     */
    function user_login($username, $password) {
        global $DB;
        if(isloggedin()) return true;

        if($this->crowd_client->authorize($username, $password)){
            //sync groups and cohorts
            if($DB->get_record_sql('SELECT id FROM {user} WHERE username = ?', array($username))){
                $this->sync_cohorts();
                $this->sync_user_cohorts($username);
            }
            return true;
        }else{
            return false;
        }
    }
    function get_userinfo($username) {
        return $this->crowd_client->getUserByName($username);
    }
    function update_user_record($username) {
        global $CFG, $DB;

        $user_details = $this->crowd_client->getUserByName($username);
        //var_dump($user_details);
        $olduser = $DB->get_record('user', array('username'=>$username));
        //var_dump($username);
        if($olduser){
            if($olduser->email != $user_details['email'] || $olduser->firstname != $user_details['firstname'] || $olduser->lastname != $user_details['lastname']){
                $olduser->email = $user_details['email'];
                $olduser->firstname = $user_details['firstname'];
                $olduser->lastname = $user_details['lastname'];        
                $DB->update_record('user', $olduser);
            }
        }else{//create new user
            create_user_record($username, "AHD3!!2d#485", "crowd");
        }

        //run a sync on all groups -> cohorts
        //fires on every login, if it causes performance issues another
        //solution will have to be found
    }
    function prelogout_hook(){
        $this->crowd_client->deleteCookie();
    }
    function sync_user_cohorts($username){
        global $DB;

        $current_user_id = $DB->get_record_sql('SELECT id FROM {user} WHERE username = ?', array($username));
        $current_cohorts = $DB->get_records_sql('SELECT {cohort_members}.*, {cohort}.name FROM {cohort_members} JOIN {cohort} ON {cohort}.id = {cohort_members}.cohortid WHERE userid = ?', array($current_user_id->id));
        $all_cohorts = $DB->get_records_sql('SELECT id,name FROM {cohort}');

        $user_groups = $this->crowd_client->userGroups($username);

        //remove outdated cohort memberships
        foreach($current_cohorts as $cohort){
            $cohort_exists = FALSE;
            foreach($user_groups->groups as $group){
                if($group->name == $cohort->name) $cohort_exists = TRUE;
            }
            if(!$cohort_exists) $DB->delete_records_select("cohort_members", "id = ?", array($cohort->id));
        }
        //add any new cohort memberships
        foreach($user_groups->groups as $group){
            $cohort_exists = false;
            foreach($current_cohorts as $cohort){
                if($group->name == $cohort->name) $cohort_exists = TRUE;
            }            
            if(!$cohort_exists){
                $record = new stdClass();
                $record->cohortid = $DB->get_record_sql('SELECT id FROM {cohort} WHERE name = ?', array($group->name))->id;
                if(is_null($record->cohortid)){
			break;
                }
                $record->userid = $current_user_id->id;
                $record->timeadded = time();
                $DB->insert_record("cohort_members", $record, false);
            }            
        }
    }
    function sync_cohorts(){
        global $DB;

        $current_cohorts = $DB->get_records_sql('SELECT * FROM {cohort}');
        $allgroups = $this->crowd_client->listGroups();

        //remove outdated cohorts
        /* foreach($current_cohorts as $cohort){
            $cohort_exists = FALSE;
            foreach($allgroups->groups as $group){
                if($group->name == $cohort->name) $cohort_exists = TRUE;
            }
            if(!$cohort_exists) $DB->delete_records_select("cohort", "name = ?", array($cohort->name));
        } */

        //add any new cohorts
        foreach($allgroups->groups as $group){
            $cohort_exists = FALSE;
            $new_cohort = FALSE;
            foreach($current_cohorts as $cohort){
                if($group->name == $cohort->name){ 
                    if(!isset($cohort->description) || $group->description != $cohort->description){ 
                        $new_cohort = $cohort;
                        $new_cohort->description = $group->description;
                        $new_cohort->timemodified = (string)time(); 
                    }
                    $cohort_exists = TRUE;
                }
            }
            if(!$cohort_exists){
                //create new record
                $record = new stdClass();
                $record->name = $group->name;
                $record->contextid = 1;
                $record->descriptionformat = 1;
                $record->timecreated = time();
                $record->description = $group->description;
                $record->timecreated = $record->timemodified = time();
                $DB->insert_record("cohort", $record, false);
            }
            if($cohort_exists && $new_cohort){
                $DB->update_record("cohort", $new_cohort, false);
            }
        }
    }
    /**
     * Sync roles for this user - usually creator
     *
     * @param $user object user object (without system magic quotes)
     */
    function sync_roles($user) {
        $this->sync_cohorts();
        $this->sync_user_cohorts($user->username);
    }
    function loginpage_hook() {
        global $frm; // can be used to override submitted login form
        global $user; // can be used to replace authenticate_user_login()

        //auto generate username if person has valid token
        //if (isset($_COOKIE["crowd_token_key"]) && !isLoggedIn()){
            //$_POST["username"] = $this->crowd_client->getUsername($_COOKIE["crowd_token_key"]);
            //$_POST["password"] = md5("*************");
        //}
    }

    /**
     * No password updates.
     */
    function user_update_password($user, $newpassword) {

        $result = $this->crowd_client->changePassword($user->username, $newpassword);
        //var_dump($result);
        if($result->response == 400){
            
            return false;
        }else if($result->response == 204){
            return true;
        }

    }

    function prevent_local_passwords() {
        // just in case, we do not want to loose the passwords
        return false;
    }

    /**
     * No external data sync.
     *
     * @return bool
     */
    function is_internal() {
        //we do not know if it was internal or external originally
        return false;
    }

    /**
     * No changing of password.
     *
     * @return bool
     */
    function can_change_password() {
        return true;
    }

    /**
     * No password resetting.
     */
    function can_reset_password() {
        return false;
    }

    /**
     * Prints a form for configuring this authentication plugin.
     *
     * This function is called from admin/auth.php, and outputs a full page with
     * a form for configuring this plugin.
     *
     * @param array $config An object containing all the data for this page.
     * @param string $error
     * @param array $user_fields
     * @return void
     */
    function config_form($config, $err, $user_fields) {
        include 'config.html';
    }

    function process_config($config) {
        set_config("crowd_server", $config->crowd_server, "auth/crowd");
        set_config("crowd_application_name", $config->crowd_application_name, "auth/crowd");
        set_config("crowd_application_password", $config->crowd_application_password, "auth/crowd");
        set_config("sync_cohorts", $config->sync_cohorts, "auth/crowd"); 
        return true;
    }
}
?>
