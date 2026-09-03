/**
 * @package     TheLoom.Module
 * @subpackage  Site.DinkyMetrics
 *
 * @copyright   Copyright (C) 2026 The Loom / Stefan Schulz. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 * @link        https://www.the-loom.de
 *
 * The count-up animation.
 *
 * It is decoration on top of a page that is already correct: every figure's final,
 * formatted value is rendered by the server and sits in .mod-dinkymetrics__number before
 * this file runs. So the safe move in every uncertain case — reduced motion, an unusable
 * locale, a value that is not a number — is to leave the DOM alone.
 *
 * The formatting here must match the server's, or the number would visibly change when
 * the tween finishes. tests/parity/cases.json pins the two together; the Intl call below
 * is the one line that has to stay in step with it.
 */

const EASINGS = {
  linear: (t) => t,
  'ease-out': (t) => 1 - (1 - t) ** 3,
  'ease-in-out': (t) => (t < 0.5 ? 4 * t ** 3 : 1 - (-2 * t + 2) ** 3 / 2),
};

const clamp = (value, min, max, fallback) => {
  const number = Number(value);

  return Number.isFinite(number) ? Math.min(max, Math.max(min, number)) : fallback;
};

/**
 * Count one figure up to the value already printed in it.
 *
 * @param {HTMLElement} value    The .mod-dinkymetrics__value element.
 * @param {object}      options  Shared per-instance settings.
 */
const countUp = (value, options) => {
  // Idempotent: scrolling back and forth must not start a second tween.
  if (value.dataset.done === '1') {
    return;
  }

  value.dataset.done = '1';

  const numberEl = value.querySelector('.mod-dinkymetrics__number');

  if (!numberEl) {
    return;
  }

  // The server's own string. Restoring it at the end is what guarantees the animation
  // cannot land a rounding step away from what the page said all along.
  const finalText = numberEl.textContent;
  const target = Number(value.dataset.target);
  const from = Number(value.dataset.from);
  const decimals = clamp(value.dataset.decimals, 0, 20, 0);

  if (!Number.isFinite(target) || !Number.isFinite(from) || target === from) {
    return;
  }

  let formatter;

  try {
    // No minimumFractionDigits: "decimals" is a ceiling, matching the server (see
    // src/Helper/Formatter.php) — a whole-number figure counts up as "30", not "30.0".
    formatter = new Intl.NumberFormat(options.locale, {
      maximumFractionDigits: decimals,
      useGrouping: value.dataset.grouping !== '0',
    });
  } catch (error) {
    // An unusable locale tag: the printed value stays, which is the correct one anyway.
    return;
  }

  const ease = EASINGS[options.easing] || EASINGS['ease-out'];
  const started = performance.now();

  // requestAnimationFrame bounds the work by time, not by the size of the number: a
  // figure of 1,400,000 costs exactly as many frames as one of 14.
  const step = (now) => {
    // Clamped at both ends: the timestamp handed to a rAF callback is the frame's start
    // time and may predate the performance.now() taken when the tween began, which would
    // otherwise make the first frame flash a negative number.
    const progress = Math.min(1, Math.max(0, (now - started) / options.duration));

    if (progress >= 1) {
      numberEl.textContent = finalText;

      return;
    }

    numberEl.textContent = formatter.format(from + (target - from) * ease(progress));
    requestAnimationFrame(step);
  };

  requestAnimationFrame(step);
};

/**
 * Wire up one module instance. Several may sit on the same page, each with its own
 * trigger, duration and locale, and none of them share state.
 *
 * @param {HTMLElement} root  A .mod-dinkymetrics element carrying the animation settings.
 */
const initInstance = (root) => {
  if (root.dataset.dmReady === '1') {
    return;
  }

  root.dataset.dmReady = '1';

  const values = root.querySelectorAll('.mod-dinkymetrics__value[data-target]');

  if (values.length === 0) {
    return;
  }

  const options = {
    locale: root.dataset.locale || document.documentElement.lang || undefined,
    duration: clamp(root.dataset.duration, 200, 10000, 1600),
    easing: root.dataset.easing,
  };

  const run = () => values.forEach((value) => countUp(value, options));

  // No IntersectionObserver (a very old browser): count up right away rather than not
  // at all — the figures are correct either way.
  if (root.dataset.trigger === 'on-load' || typeof IntersectionObserver === 'undefined') {
    run();

    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) {
          return;
        }

        observer.unobserve(entry.target);
        run();
      });
    },
    { threshold: clamp(root.dataset.threshold, 0, 1, 0.35) }
  );

  observer.observe(root);
};

const start = () => {
  // Binding, and deliberately not tied to the module's own "respect reduced motion"
  // parameter: that switch may only ever turn the animation off for everyone, never
  // force it on someone who asked their system for less movement.
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    return;
  }

  // Only instances that actually asked for an animation carry data-trigger, so a page
  // mixing animated and static instances behaves correctly with one shared script.
  document.querySelectorAll('.mod-dinkymetrics[data-trigger]').forEach(initInstance);
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', start, { once: true });
} else {
  start();
}
