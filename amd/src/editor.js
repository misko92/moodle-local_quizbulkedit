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
 * Bulk-fill toolbar, select-all and change highlighting for the quiz bulk edit table.
 *
 * @module     local_quizbulkedit/editor
 * @copyright  2026 misko92
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';
import {add as addToast} from 'core/toast';
import {watchFormById, markFormChangedFromNode} from 'core_form/changechecker';

/** Characters for random passwords, without easily confused ones (0/O, 1/l/I). */
const PASSWORD_CHARS = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

/**
 * Make a random password.
 *
 * @param {number} length
 * @returns {string}
 */
const randomPassword = (length = 6) => {
    const values = new Uint32Array(length);
    window.crypto.getRandomValues(values);
    return Array.from(values, (v) => PASSWORD_CHARS[v % PASSWORD_CHARS.length]).join('');
};

/**
 * Highlight a cell whose value differs from what was loaded.
 *
 * @param {HTMLInputElement|HTMLSelectElement} input
 */
const refreshChanged = (input) => {
    input.closest('td').classList.toggle('table-warning', input.value !== input.dataset.original);
};

/**
 * Set an input's value as if the user typed it.
 *
 * @param {HTMLInputElement|HTMLSelectElement} input
 * @param {string} value
 */
const setValue = (input, value) => {
    input.value = value;
    input.classList.remove('is-invalid');
    refreshChanged(input);
    markFormChangedFromNode(input);
};

/**
 * Initialise the page.
 *
 * @param {string} formId
 */
export const init = (formId) => {
    const form = document.getElementById(formId);
    if (!form) {
        return;
    }
    watchFormById(formId);

    const fieldSelect = form.querySelector('[data-action="bulkfield"]');
    const valueLabel = form.querySelector('[data-region="bulkvaluelabel"]');
    const valueControls = [...form.querySelectorAll('[data-bulkvalue]')];
    const valueControl = () => valueControls.find((control) => control.dataset.bulkvalue === fieldSelect.value);
    const randomButton = form.querySelector('[data-action="bulkrandom"]');
    const selectAll = form.querySelector('[data-action="selectall"]');
    const rowBoxes = () => [...form.querySelectorAll('[data-action="select"]')];
    const selectedRows = () => rowBoxes().filter((box) => box.checked).map((box) => box.closest('tr'));

    const syncToolbar = () => {
        const active = valueControl();
        valueControls.forEach((control) => {
            control.hidden = control !== active;
        });
        valueLabel.htmlFor = active.id;
        randomButton.hidden = fieldSelect.value !== 'password';
    };
    fieldSelect.addEventListener('change', syncToolbar);
    syncToolbar();

    const requireSelection = async() => {
        const rows = selectedRows();
        if (!rows.length) {
            addToast(await getString('selectfirst', 'local_quizbulkedit'), {type: 'warning'});
        }
        return rows;
    };

    form.querySelector('[data-action="bulkapply"]').addEventListener('click', async() => {
        const field = fieldSelect.value;
        const value = valueControl().value;
        (await requireSelection()).forEach((row) => {
            const control = row.querySelector(`[data-field="${field}"]`);
            // A quiz can't copy review options from itself, so its own option is missing.
            const allowed = control.tagName !== 'SELECT' || [...control.options].some((o) => o.value === value);
            if (!control.disabled && allowed) {
                setValue(control, value);
            }
        });
    });

    randomButton.addEventListener('click', async() => {
        (await requireSelection()).forEach((row) => {
            setValue(row.querySelector('input[data-field="password"]'), randomPassword());
        });
    });

    selectAll.addEventListener('change', () => {
        rowBoxes().forEach((box) => {
            box.checked = selectAll.checked;
        });
    });
    form.addEventListener('change', (e) => {
        if (e.target.dataset.action === 'select') {
            const boxes = rowBoxes();
            selectAll.checked = boxes.every((box) => box.checked);
            selectAll.indeterminate = !selectAll.checked && boxes.some((box) => box.checked);
        }
    });

    form.addEventListener('input', (e) => {
        if (e.target.dataset.original !== undefined) {
            refreshChanged(e.target);
        }
    });

    // Enter in a toolbar value box should apply, not submit the whole form.
    valueControls.forEach((control) => control.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            form.querySelector('[data-action="bulkapply"]').click();
        }
    }));
};
