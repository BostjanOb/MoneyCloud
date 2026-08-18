import assert from 'node:assert/strict';
import test from 'node:test';
import {
    formatEuro,
    formatSlovenianPercent,
} from '../../resources/js/lib/utils.ts';

const NON_BREAKING_SPACE = '\u00a0';

test('euro amounts keep the unit on the same line as the number', () => {
    assert.equal(formatEuro('40790.43'), `40.790,43${NON_BREAKING_SPACE}€`);
    // sl-SI renders negatives with U+2212 MINUS SIGN, not a hyphen.
    assert.equal(formatEuro(-1.45), `\u22121,45${NON_BREAKING_SPACE}€`);
});

test('percentages keep the unit on the same line as the number', () => {
    assert.equal(formatSlovenianPercent('4.41'), `4,41${NON_BREAKING_SPACE}%`);
    assert.equal(formatSlovenianPercent(34.95), `34,95${NON_BREAKING_SPACE}%`);
});
