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
 * Behat steps for local_quizbulkedit.
 *
 * @package    local_quizbulkedit
 * @category   test
 * @copyright  2026 misko92
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Behat steps for local_quizbulkedit.
 */
class behat_local_quizbulkedit extends behat_base {
    /**
     * Fire the events a browser sends when it stores the page in its back/forward cache.
     *
     * WebDriver-controlled Chrome does not use the back/forward cache, so this
     * simulates what happens on a student's real browser when they press Back.
     *
     * @When /^the browser stores the page in its back\/forward cache$/
     */
    public function the_browser_stores_the_page_in_its_back_forward_cache(): void {
        $this->execute_script("window.dispatchEvent(new PageTransitionEvent('pagehide', {persisted: true}));");
    }

    /**
     * Fire the event a browser sends when it shows a page from its back/forward cache.
     *
     * @When /^the browser restores the page from its back\/forward cache$/
     */
    public function the_browser_restores_the_page_from_its_back_forward_cache(): void {
        $this->execute_script("window.dispatchEvent(new PageTransitionEvent('pageshow', {persisted: true}));");
        $this->wait_for_pending_js();
    }
}
