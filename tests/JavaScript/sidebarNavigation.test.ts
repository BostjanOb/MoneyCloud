import assert from 'node:assert/strict';
import test from 'node:test';
import { isSidebarNavGroupActive } from '../../resources/js/lib/sidebarNavigation.ts';

test('sidebar group is active when the parent url matches', () => {
    assert.equal(
        isSidebarNavGroupActive(
            {
                title: 'Nastavitve',
                href: '/nastavitve/davki',
                children: [
                    { title: 'Davčne nastavitve', href: '/nastavitve/davki' },
                    { title: 'Simboli', href: '/investicije/simboli' },
                ],
            },
            (href) => href === '/nastavitve/davki',
        ),
        true,
    );
});

test('sidebar group is active when a child url matches', () => {
    assert.equal(
        isSidebarNavGroupActive(
            {
                title: 'Nastavitve',
                href: '/nastavitve/davki',
                children: [
                    { title: 'Davčne nastavitve', href: '/nastavitve/davki' },
                    { title: 'Simboli', href: '/investicije/simboli' },
                ],
            },
            (href) => href === '/investicije/simboli',
        ),
        true,
    );
});

test('sidebar group is inactive when neither parent nor children match', () => {
    assert.equal(
        isSidebarNavGroupActive(
            {
                title: 'Nastavitve',
                href: '/nastavitve/davki',
                children: [
                    { title: 'Davčne nastavitve', href: '/nastavitve/davki' },
                    { title: 'Simboli', href: '/investicije/simboli' },
                ],
            },
            (href) => href === '/varcevanje',
        ),
        false,
    );
});
