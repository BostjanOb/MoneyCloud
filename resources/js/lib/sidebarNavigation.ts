type SidebarNavigationItem<Href> = {
    href: Href;
    children?: { href: Href }[];
};

export function isSidebarNavGroupActive<Href>(
    item: SidebarNavigationItem<Href>,
    isCurrentUrl: (href: Href) => boolean,
): boolean {
    if (isCurrentUrl(item.href)) {
        return true;
    }

    return item.children?.some((child) => isCurrentUrl(child.href)) ?? false;
}
