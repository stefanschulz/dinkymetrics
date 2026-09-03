/**
 * @package     TheLoom.Module
 * @subpackage  Site.DinkyMetrics
 *
 * @copyright   Copyright (C) 2026 The Loom / Stefan Schulz. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 * @link        https://www.the-loom.de
 *
 * Keeps a figure row's collapsed summary in step with what was just typed.
 *
 * Presentation only, admin edit screen only: this mirrors src/Field/FigureSummary.php's
 * wording in JS, but reads straight from the DOM rather than duplicating translated
 * strings — the currently selected <option>'s text is already in the right language.
 * Recomputed once, when a row's <details> is closed, not on every keystroke: while a row
 * is open the editor is looking at the real fields anyway, and a fresh row added via
 * "+" starts open, so there is nothing stale to show before the first edit.
 */

((doc) => {
  const field = (row, suffix) => row.querySelector(`[name$="${suffix}"]`);
  const value = (row, suffix) => field(row, suffix)?.value.trim() ?? '';

  const optionText = (row, suffix) => {
    const select = field(row, suffix);

    return select?.selectedOptions?.[0]?.textContent.trim() ?? '';
  };

  // Category/parent options are indented with dashes and non-breaking spaces for depth.
  const cleanCategoryText = (text) => text.replace(/^[\s -]+/, '');

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
   * @param {HTMLDetailsElement} details
   * @returns {void}
   */
  const refresh = (details) => {
    const row = details.closest('.dinkymetrics-figure');

    if (!row) {
      return;
    }

    const { caption, sourceText, detail } = summarise(row);
    const captionEl = details.querySelector('.dinkymetrics-figure__caption');
    const sourceEl = details.querySelector('.dinkymetrics-figure__source');
    let detailEl = details.querySelector('.dinkymetrics-figure__detail');

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

  // The native "toggle" event does not bubble, so listening on the document only works
  // in the capture phase — which is exactly what lets one delegated listener cover rows
  // the joomla-field-subform web component adds after this script has already run.
  doc.addEventListener(
    'toggle',
    (event) => {
      if (event.target instanceof HTMLDetailsElement && event.target.matches('.dinkymetrics-figure__details')) {
        refresh(event.target);
      }
    },
    true
  );
})(document);
