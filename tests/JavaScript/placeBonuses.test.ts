import assert from 'node:assert/strict';
import test from 'node:test';
import {
    filterBonuses,
    formatBonusPaidAt,
    formatBonusPaidAtComment,
    normalizeBonusPaidAtForInput,
} from '../../resources/js/lib/placeBonuses.ts';

test('bonus filter returns all bonuses for all types', () => {
    const bonuses = [
        { id: 1, type: 'regres' },
        { id: 2, type: 'sp' },
        { id: 3, type: 'ostalo' },
    ];

    assert.deepEqual(
        filterBonuses(bonuses, 'all').map((bonus) => bonus.id),
        [1, 2, 3],
    );
});

test('bonus filter returns only bonuses for selected type', () => {
    const bonuses = [
        { id: 1, type: 'regres' },
        { id: 2, type: 'sp' },
        { id: 3, type: 'regres' },
    ];

    assert.deepEqual(
        filterBonuses(bonuses, 'regres').map((bonus) => bonus.id),
        [1, 3],
    );
});

test('bonus paid at is formatted in Slovenian locale', () => {
    assert.equal(formatBonusPaidAt('2026-06-15'), '15. 6. 2026');
});

test('bonus paid at supports ssr iso datetime payloads', () => {
    assert.equal(
        formatBonusPaidAt('2026-06-14T22:00:00.000000Z'),
        '15. 6. 2026',
    );
});

test('bonus paid at comment includes Slovenian formatted date', () => {
    assert.equal(
        formatBonusPaidAtComment('2026-06-15'),
        'Izplačano: 15. 6. 2026',
    );
});

test('bonus paid at comment is empty when date is missing', () => {
    assert.equal(formatBonusPaidAtComment(null), null);
});

test('bonus paid at input value keeps plain date values', () => {
    assert.equal(normalizeBonusPaidAtForInput('2026-06-15'), '2026-06-15');
});

test('bonus paid at input value extracts date from ssr iso payload', () => {
    assert.equal(
        normalizeBonusPaidAtForInput('2026-06-14T22:00:00.000000Z'),
        '2026-06-15',
    );
});

test('bonus paid at input value is empty when date is missing', () => {
    assert.equal(normalizeBonusPaidAtForInput(null), '');
});
