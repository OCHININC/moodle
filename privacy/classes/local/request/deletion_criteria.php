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
 * The \core_privacy\local\request\deletion_criteria class.
 *
 * @package core_privacy
 * @copyright 2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_privacy\local\request;

defined('MOODLE_INTERNAL') || die();

/**
 * The deletion_criteria class is used to describe conditions for a set of
 * data due to be deleted.
 *
 * @copyright 2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class deletion_criteria {
    /**
     * @var context The context being deleted.
     */
    protected $context = null;

    /**
     * Constructor for a new deletion_criteria.
     *
     * @param   \context $context The context being deleted.
     */
    public function __construct(\context $context) {
        $this->context = $context;
    }

    /**
     * Get the context to be deleted.
     *
     * @return  \context
     */
    public function get_context() {
        return $this->context;
    }
}