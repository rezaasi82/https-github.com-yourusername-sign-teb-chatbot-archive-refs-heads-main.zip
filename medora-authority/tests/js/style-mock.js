/**
 * Stand-in for stylesheet imports under Jest.
 *
 * Components import their CSS so the bundler can extract it; that import has no
 * meaning in a test, and without this mapping Jest tries to parse CSS as
 * JavaScript and fails.
 */
module.exports = {};
