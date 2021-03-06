<?php

class block_cocoon_steps_dark_edit_form extends block_edit_form {
    protected function specific_definition($mform) {
        global $CFG;
        $ccnFontList = include($CFG->dirroot . '/theme/edumy/ccn/font_handler/ccn_font_select.php');

        if (!empty($this->block->config) && is_object($this->block->config)) {
            $data = $this->block->config;
        } else {
            $data = new stdClass();
            $data->slidesnumber = 3;
        }


        // Fields for editing HTML block title and contents.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $mform->addElement('text', 'config_title', get_string('config_title', 'theme_edumy'));
        $mform->setDefault('config_title', 'See How It Works');
        $mform->setType('config_title', PARAM_TEXT);

        $mform->addElement('text', 'config_subtitle', get_string('config_subtitle', 'theme_edumy'));
        $mform->setDefault('config_subtitle', 'Cum doctus civibus efficiantur in imperdiet deterruisset.');
        $mform->setType('config_subtitle', PARAM_TEXT);

        // $radioarray=array();
        // $radioarray[] = $mform->createElement('radio', 'config_style', '', 'Standard', 0, $attributes);
        // $radioarray[] = $mform->createElement('radio', 'config_style', '', 'Fullsize', 1, $attributes);
        // $mform->addGroup($radioarray, 'config_style', 'Slider Size', array(' '), false);

        $slidesrange = array(
          3 => '3',
          4 => '4',
          5 => '5',
          6 => '6',
        );
        $slidesmax = 6;

        $mform->addElement('select', 'config_slidesnumber', get_string('config_items', 'theme_edumy'), $slidesrange);
        $mform->setDefault('config_slidesnumber', 3);

        for($i = 1; $i <= $slidesmax; $i++) {
            $mform->addElement('header', 'config_ccn_item' . $i , get_string('config_item', 'theme_edumy') . $i);

            $mform->addElement('text', 'config_title' . $i, get_string('config_title', 'theme_edumy', $i));
            $mform->setDefault('config_title' .$i , 'Choose What To Do');
            $mform->setType('config_title' . $i, PARAM_TEXT);

            $mform->addElement('textarea', 'config_body' . $i, get_string('config_body', 'theme_edumy', $i));
            $mform->setDefault('config_body' .$i , 'Aliquam dictum elit vitae mauris facilisis at dictum urna dignissim donec vel lectus vel felis.');
            $mform->setType('config_body' . $i, PARAM_TEXT);

            $mform->addElement('text', 'config_link' . $i, get_string('config_link', 'theme_edumy', $i));
            $mform->setDefault('config_link' .$i , '#');
            $mform->setType('config_link' . $i, PARAM_TEXT);

            $filemanageroptions = array('maxbytes'      => $CFG->maxbytes,
                                        'subdirs'       => 0,
                                        'maxfiles'      => 1,
                                        'accepted_types' => array('.jpg', '.png', '.gif'));

            $f = $mform->addElement('filemanager', 'config_image' . $i, get_string('config_image', 'theme_edumy', $i), null, $filemanageroptions);

        }

        $mform->addElement('header', 'config_ccn_colors', get_string('block_styles', 'theme_edumy'));

        $mform->addElement('text', 'config_color_bg', get_string('config_color_bg', 'theme_edumy'), array('class'=>'ccn_spectrum_class'));
        $mform->setDefault('config_color_bg', 'rgb(0, 8, 70)');
        $mform->setType('config_color_bg', PARAM_TEXT);

        $mform->addElement('text', 'config_color_title', get_string('config_color_title', 'theme_edumy'), array('class'=>'ccn_spectrum_class'));
        $mform->setDefault('config_color_title', 'rgb(255,255,255)');
        $mform->setType('config_color_title', PARAM_TEXT);

        $mform->addElement('text', 'config_color_subtitle', get_string('config_color_subtitle', 'theme_edumy'), array('class'=>'ccn_spectrum_class'));
        $mform->setDefault('config_color_subtitle', 'rgb(255,255,255)');
        $mform->setType('config_color_subtitle', PARAM_TEXT);

        $mform->addElement('text', 'config_color_item_title', get_string('config_color_item_title', 'theme_edumy'), array('class'=>'ccn_spectrum_class'));
        $mform->setDefault('config_color_item_title', 'rgb(255,255,255)');
        $mform->setType('config_color_item_title', PARAM_TEXT);

        $mform->addElement('text', 'config_color_item_body', get_string('config_color_item_body', 'theme_edumy'), array('class'=>'ccn_spectrum_class'));
        $mform->setDefault('config_color_item_body', 'rgba(255, 255, 255, .6)');
        $mform->setType('config_color_item_body', PARAM_TEXT);

        $mform->addElement('text', 'config_c_ccn_i_c', get_string('config_c_ccn_i_c', 'theme_edumy'), array('class'=>'ccn_spectrum_class'));
        $mform->setDefault('config_c_ccn_i_c', 'rgb(22, 32, 90)');
        $mform->setType('config_c_ccn_i_c', PARAM_TEXT);

        // $mform->addElement('text', 'config_color_course_card', get_string('config_color_course_card', 'theme_edumy'), array('class'=>'ccn_spectrum_class'));
        // $mform->setDefault('config_color_course_card', 'rgb(255, 255, 255)');
        // $mform->setType('config_color_course_card', PARAM_TEXT);
        //
        // $mform->addElement('text', 'config_color_course_title', get_string('config_color_course_title', 'theme_edumy'), array('class'=>'ccn_spectrum_class'));
        // $mform->setDefault('config_color_course_title', 'rgb(32, 51, 103)');
        // $mform->setType('config_color_course_title', PARAM_TEXT);
        //
        // $mform->addElement('text', 'config_color_course_price', get_string('config_color_course_price', 'theme_edumy'), array('class'=>'ccn_spectrum_class'));
        // $mform->setDefault('config_color_course_price', 'rgb(199, 85, 51)');
        // $mform->setType('config_color_course_price', PARAM_TEXT);
        //
        // $mform->addElement('text', 'config_color_course_enrol_btn', get_string('config_color_course_enrol_btn', 'theme_edumy'), array('class'=>'ccn_spectrum_class'));
        // $mform->setDefault('config_color_course_enrol_btn', '#79b530');
        // $mform->setType('config_color_course_enrol_btn', PARAM_TEXT);


        include($CFG->dirroot . '/theme/edumy/ccn/block_handler/edit.php');

    }

    function set_data($defaults) {
        if (!empty($this->block->config) && is_object($this->block->config)) {

            for($i = 1; $i <= $this->block->config->slidesnumber; $i++) {
                $field = 'image' . $i;
                $conffield = 'config_image' . $i;
                $draftitemid = file_get_submitted_draft_itemid($conffield);
                file_prepare_draft_area($draftitemid, $this->block->context->id, 'block_cocoon_steps_dark', 'items', $i, array('subdirs'=>false));
                $defaults->$conffield['itemid'] = $draftitemid;
                $this->block->config->$field = $draftitemid;
            }
        }

        parent::set_data($defaults);
    }
}
