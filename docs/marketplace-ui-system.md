# Shared Marketplace UI system

Implemented in existing PHP views. No financial service or calculation contract is changed.

Shared components: marketplace_icons.php (local stroke SVG), marketplace_global_sidebar.php, marketplace_store_sidebar.php. marketplace-system.css and marketplace-system.js are loaded by the global view, per-store entry and standalone integration settings. Local SVG sprite supplies contextual action icons without a remote icon runtime.

Global/store scope labels, breadcrumb, orange selected navigation, product submenus, store switcher, desktop sidebar and mobile drawer. Drawer supports close button, overlay, Escape, focus return, focus containment, and inert closed navigation/background. Existing route authorization remains authoritative. Preferences lead to central global settings.

Consistent neutral surfaces, typography, controls, table density and numeric typography; existing status colors and unknown data semantics remain. Income filters use a four-column desktop and two-column mobile layout. Existing modals, HPP modes, price changes, imports and synchronization preserve their own behavior. A static demo ROAS and unconditional Balanced label were removed.

Visual QA: desktop 1280×720 and mobile iframe 390×700 with synthetic income data. Navigation opens/closes, active page and nested products expose real existing links, icons render from local assets, closed drawer is absent from accessibility tree, behind-drawer main content is inert. Desktop and mobile screenshots saved to conversation visualization directory. PHP syntax and income/withdrawal regression checks pass. Preview HTML fixtures are removed after QA.
