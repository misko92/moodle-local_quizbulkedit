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

/* eslint-disable no-bitwise -- quiz review options are stored as bit fields. */

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

/** Review time bits, as in mod_quiz\question\display_options. */
const DURING = 0x10000;
const AFTER_CLOSE = 0x00010;

/** Review options that need "The attempt" at the same time (except during the attempt). */
const NEEDS_ATTEMPT = ['reviewcorrectness', 'reviewspecificfeedback', 'reviewgeneralfeedback', 'reviewrightanswer'];

/**
 * Apply the quiz settings form's review option rules (mirrors updater::normalise_review()).
 *
 * @param {Object<string, number>} review field => bits
 * @param {string[]} unusedDuring fields the question behaviour doesn't use during the attempt
 * @returns {Object<string, number>}
 */
const normaliseReview = (review, unusedDuring) => {
    review.reviewattempt |= DURING;
    review.reviewoverallfeedback &= ~DURING;
    unusedDuring.forEach((field) => {
        if (field in review) {
            review[field] &= ~DURING;
        }
    });
    review.reviewmarks &= review.reviewmaxmarks;
    NEEDS_ATTEMPT.forEach((field) => {
        review[field] &= review.reviewattempt | DURING;
    });
    return review;
};

/**
 * Whether a review checkbox can be changed, following the quiz settings form.
 *
 * @param {string} field
 * @param {number} bit
 * @param {Object<string, number>} review
 * @param {string[]} unusedDuring
 * @param {boolean} hasClose whether the quiz has a close date
 * @returns {boolean}
 */
const reviewLocked = (field, bit, review, unusedDuring, hasClose) => {
    if (bit === DURING && (['reviewattempt', 'reviewoverallfeedback'].includes(field) || unusedDuring.includes(field))) {
        return true;
    }
    if (bit === AFTER_CLOSE && !hasClose) {
        return true;
    }
    if (field === 'reviewmarks' && !(review.reviewmaxmarks & bit)) {
        return true;
    }
    return bit !== DURING && NEEDS_ATTEMPT.includes(field) && !(review.reviewattempt & bit);
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
    // Only quizzes left visible by the filter can be selected or changed in bulk.
    const rowBoxes = () => [...form.querySelectorAll('[data-action="select"]')].filter((box) => !box.closest('tr').hidden);
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
            if (field === 'review') {
                copyReview(row, value);
                return;
            }
            const control = row.querySelector(`[data-field="${field}"]`);
            if (!control.disabled) {
                setValue(control, value);
                if (field === 'timeclose') {
                    syncReview(row);
                }
            }
        });
    });

    randomButton.addEventListener('click', async() => {
        (await requireSelection()).forEach((row) => {
            setValue(row.querySelector('input[data-field="password"]'), randomPassword());
        });
    });

    const quizRows = [...form.querySelectorAll('tr[data-quizid]')];
    const reviewRow = (row) => form.querySelector(`tr[data-reviewfor="${row.dataset.quizid}"]`);
    const quizRow = (quizid) => form.querySelector(`tr[data-quizid="${quizid}"]`);
    const reviewInputs = (row) => [...row.querySelectorAll('input[data-review]')];
    const getReview = (row) => Object.fromEntries(reviewInputs(row).map((input) => [input.dataset.field, Number(input.value)]));
    const unusedDuring = (row) => reviewRow(row).dataset.unusedduring.split(' ').filter((field) => field.length);

    // Show the review hidden inputs in the grid: ticks, locked boxes and change highlights.
    const syncReview = (row) => {
        const review = getReview(row);
        const hasClose = row.querySelector('input[data-field="timeclose"]').value !== '';
        const originals = Object.fromEntries(
            reviewInputs(row).map((input) => [input.dataset.field, Number(input.dataset.original)])
        );
        reviewRow(row).querySelectorAll('input[data-reviewfield]').forEach((box) => {
            const field = box.dataset.reviewfield;
            const bit = Number(box.dataset.bit);
            box.checked = Boolean(review[field] & bit);
            box.disabled = reviewLocked(field, bit, review, unusedDuring(row), hasClose);
            box.closest('td').classList.toggle('table-warning', (review[field] & bit) !== (originals[field] & bit));
        });
        row.querySelector('td[data-cell="review"]').classList.toggle('table-warning',
            reviewInputs(row).some((input) => input.value !== input.dataset.original));
    };

    const setReview = (row, review) => {
        normaliseReview(review, unusedDuring(row));
        reviewInputs(row).forEach((input) => {
            input.value = String(review[input.dataset.field]);
        });
        markFormChangedFromNode(reviewInputs(row)[0]);
        syncReview(row);
    };

    const copyReview = (row, sourceId) => {
        const source = quizRow(sourceId);
        if (source && source !== row) {
            setReview(row, getReview(source));
        }
    };
    quizRows.forEach(syncReview);
    const setReviewOpen = (row, open) => {
        reviewRow(row).hidden = !open || row.hidden;
        row.querySelector('[data-action="togglereview"]').setAttribute('aria-expanded', open ? 'true' : 'false');
    };
    const isReviewOpen = (row) => row.querySelector('[data-action="togglereview"]').getAttribute('aria-expanded') === 'true';

    const filterInput = document.querySelector('[data-action="filter"]');
    const filterValue = form.querySelector('[data-region="filtervalue"]');
    const filterCount = document.querySelector('[data-region="filtercount"]');
    const applyFilter = async() => {
        const words = filterInput.value.toLowerCase().split(/\s+/).filter((word) => word.length);
        let shown = 0;
        quizRows.forEach((row) => {
            const text = row.dataset.search.toLowerCase();
            row.hidden = !words.every((word) => text.includes(word));
            setReviewOpen(row, isReviewOpen(row));
            shown += row.hidden ? 0 : 1;
        });
        filterValue.value = filterInput.value;
        filterCount.textContent = words.length ?
            await getString('filtercount', 'local_quizbulkedit', {shown, total: quizRows.length}) : '';
        const boxes = rowBoxes();
        selectAll.checked = boxes.length > 0 && boxes.every((box) => box.checked);
        selectAll.indeterminate = !selectAll.checked && boxes.some((box) => box.checked);
    };
    filterInput.addEventListener('input', applyFilter);
    filterInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
        }
    });
    applyFilter();

    form.addEventListener('click', (e) => {
        const toggle = e.target.closest('[data-action="togglereview"]');
        if (toggle) {
            const row = toggle.closest('tr');
            setReviewOpen(row, !isReviewOpen(row));
        } else if (e.target.closest('[data-action="togglereviewall"]')) {
            const visible = quizRows.filter((row) => !row.hidden);
            const open = !visible.every(isReviewOpen);
            visible.forEach((row) => setReviewOpen(row, open));
        }
    });

    selectAll.addEventListener('change', () => {
        rowBoxes().forEach((box) => {
            box.checked = selectAll.checked;
        });
    });
    form.addEventListener('change', (e) => {
        if (e.target.dataset.reviewfield) {
            const row = quizRow(e.target.closest('tr[data-reviewfor]').dataset.reviewfor);
            const review = getReview(row);
            const bit = Number(e.target.dataset.bit);
            review[e.target.dataset.reviewfield] = e.target.checked ?
                review[e.target.dataset.reviewfield] | bit : review[e.target.dataset.reviewfield] & ~bit;
            setReview(row, review);
        } else if (e.target.dataset.action === 'reviewcopy') {
            copyReview(e.target.closest('tr'), e.target.value);
            e.target.value = '';
        } else if (e.target.dataset.action === 'select') {
            const boxes = rowBoxes();
            selectAll.checked = boxes.every((box) => box.checked);
            selectAll.indeterminate = !selectAll.checked && boxes.some((box) => box.checked);
        }
    });

    form.addEventListener('input', (e) => {
        if (e.target.dataset.original !== undefined) {
            refreshChanged(e.target);
        }
        if (e.target.dataset.field === 'timeclose') {
            syncReview(e.target.closest('tr'));
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
