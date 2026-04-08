export type SidebarState = 'expanded' | 'collapsed';

type ResolveSidebarSubmenuToggleParams = {
    currentSubmenuOpen: boolean;
    isMobile: boolean;
    sidebarState: SidebarState;
};

type ResolveSidebarSubmenuToggleResult = {
    nextSubmenuOpen: boolean;
    shouldExpandSidebar: boolean;
};

export function resolveSidebarSubmenuToggle({
    currentSubmenuOpen,
    isMobile,
    sidebarState,
}: ResolveSidebarSubmenuToggleParams): ResolveSidebarSubmenuToggleResult {
    if (!isMobile && sidebarState === 'collapsed') {
        return {
            nextSubmenuOpen: true,
            shouldExpandSidebar: true,
        };
    }

    return {
        nextSubmenuOpen: !currentSubmenuOpen,
        shouldExpandSidebar: false,
    };
}
