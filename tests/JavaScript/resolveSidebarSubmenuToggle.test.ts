import assert from 'node:assert/strict';
import test from 'node:test';
import { resolveSidebarSubmenuToggle } from '../../resources/js/lib/sidebarSubmenu.ts';

test('collapsed desktop click expands the sidebar and opens the submenu', () => {
    assert.deepStrictEqual(
        resolveSidebarSubmenuToggle({
            currentSubmenuOpen: false,
            isMobile: false,
            sidebarState: 'collapsed',
        }),
        {
            nextSubmenuOpen: true,
            shouldExpandSidebar: true,
        },
    );
});

test('expanded desktop click toggles the submenu without reopening the sidebar', () => {
    assert.deepStrictEqual(
        resolveSidebarSubmenuToggle({
            currentSubmenuOpen: true,
            isMobile: false,
            sidebarState: 'expanded',
        }),
        {
            nextSubmenuOpen: false,
            shouldExpandSidebar: false,
        },
    );
});

test('mobile click only toggles the submenu state', () => {
    assert.deepStrictEqual(
        resolveSidebarSubmenuToggle({
            currentSubmenuOpen: false,
            isMobile: true,
            sidebarState: 'collapsed',
        }),
        {
            nextSubmenuOpen: true,
            shouldExpandSidebar: false,
        },
    );
});
