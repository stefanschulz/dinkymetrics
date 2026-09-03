/**
 * @package     TheLoom.Module
 * @subpackage  Site.DinkyMetrics
 *
 * @copyright   Copyright (C) 2026 The Loom / Stefan Schulz. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 * @link        https://www.the-loom.de
 *
 * Two small, unrelated jobs for the Figures list's admin edit screen:
 *
 * 1. Keep a row's one-line summary in step with what was just edited in its modal.
 *    Presentation only: mirrors src/Field/FigureSummary.php's wording, but reads
 *    straight from the DOM rather than duplicating translated strings — the currently
 *    selected <option>'s text is already in the right language. Recomputed once, when
 *    the modal finishes closing, not on every keystroke.
 *
 * 2. Give a freshly added row's modal a real, unique id and open it straight away.
 *    joomla-field-subform.js only renames attributes on elements that carry a `name`
 *    (see fixUniqueAttributes in joomla-field-subform.js) — a hand-authored id like
 *    "dm-modal-figuresX" on our own <div class="modal"> is invisible to that logic and
 *    would stay literally "figuresX" in every row added after the first, breaking
 *    data-bs-target. Its own "subform-row-add" event fires after that renaming has
 *    already happened, so by the time it reaches us the row's real group name
 *    (row.dataset.group) is already correct — we only need to copy it onto the modal.
 */

((doc) => {
  const field = (row, suffix) => row.querySelector(`[name$="${suffix}"]`);
  const value = (row, suffix) => field(row, suffix)?.value.trim() ?? '';

  const optionText = (row, suffix) => {
    const select = field(row, suffix);

    return select?.selectedOptions?.[0]?.textContent.trim() ?? '';
  };

  // Category/parent options are indented with dashes and non-breaking spaces for depth.
  const cleanCategoryText = (text) => text.replace(/^[\s -]+/, '');

  const withPrefixSuffix = (row, text) => {
    const prefix = value(row, '[prefix]');
    const suffix = value(row, '[suffix]');

    return prefix || suffix ? `${prefix} ${text}${suffix}`.trim() : text;
  };

  /**
   * @param {HTMLElement} row  The .dinkymetrics-figure wrapper.
   * @returns {{caption: string, sourceText: string, detail: string}}
   */
  const summarise = (row) => {
    const source = value(row, '[source]') || 'literal';
    const sourceText = optionText(row, '[source]');
    let detail = '';

    switch (source) {
      case 'years_since': {
        const date = value(row, '[date]');
        const unit = optionText(row, '[unit]');
        detail = date ? `${date} (${unit})` : unit;
        break;
      }

      case 'content_count':
      case 'category_count': {
        const idField = source === 'content_count' ? '[category]' : '[parent]';
        detail = cleanCategoryText(optionText(row, idField));
        // Deliberately not reproducing every filter clause (featured, status, skip
        // empty, …) here — the category name is what most changes a summary's
        // meaning, and the full detail is exactly right again after the next save.
        break;
      }

      default: {
        const literal = value(row, '[value]');
        detail = literal ? withPrefixSuffix(row, literal) : '';
      }
    }

    return { caption: value(row, '[label]'), sourceText, detail };
  };

  /**
   * @param {HTMLElement} row  The .dinkymetrics-figure wrapper whose modal just closed.
   * @returns {void}
   */
  const refreshSummary = (row) => {
    const { caption, sourceText, detail } = summarise(row);
    const captionEl = row.querySelector('.dinkymetrics-figure__caption');
    const sourceEl = row.querySelector('.dinkymetrics-figure__source');
    let detailEl = row.querySelector('.dinkymetrics-figure__detail');

    if (captionEl && caption) {
      captionEl.textContent = caption;
    }

    if (sourceEl && sourceText) {
      sourceEl.textContent = sourceText;
    }

    if (!detail) {
      detailEl?.remove();
    } else if (detailEl) {
      detailEl.textContent = detail;
    } else if (sourceEl) {
      detailEl = doc.createElement('span');
      detailEl.className = 'dinkymetrics-figure__detail';
      detailEl.textContent = detail;
      sourceEl.after(detailEl);
    }
  };

  // hidden.bs.modal bubbles (Bootstrap's EventHandler.trigger defaults to bubbles: true),
  // so one delegated listener on the document covers every row, present now or added
  // later.
  doc.addEventListener('hidden.bs.modal', (event) => {
    const row = event.target.closest?.('.dinkymetrics-figure');

    if (row) {
      refreshSummary(row);
    }
  });

  doc.addEventListener('subform-row-add', (event) => {
    const row = event.detail?.row;

    if (!(row instanceof HTMLElement) || !row.matches('.dinkymetrics-figure')) {
      return;
    }

    const modal = row.querySelector('.dinkymetrics-figure__modal');
    const trigger = row.querySelector('.dinkymetrics-figure__edit');

    if (!modal || !trigger) {
      return;
    }

    const modalId = `dm-modal-${row.dataset.group}`;
    modal.id = modalId;
    trigger.setAttribute('data-bs-target', `#${modalId}`);

    // The new row is empty; go straight to editing it rather than showing a blank
    // summary line that the editor would have to click into anyway.
    trigger.click();
  });
})(document);
