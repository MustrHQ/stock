<?php
/** Small inline SVG icon set — stroke icons that inherit the text colour. */
function icon($name, $size = 18, $label = '') {
    static $p = [
        'home'     => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>',
        'count'    => '<rect x="8" y="2.5" width="8" height="4" rx="1"/><path d="M16 4.5h2a2 2 0 0 1 2 2V20a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6.5a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/>',
        'trash'    => '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/>',
        'drop'     => '<path d="M12 2.5s6.5 6.8 6.5 11.5a6.5 6.5 0 0 1-13 0C5.5 9.3 12 2.5 12 2.5z"/>',
        'shield'   => '<path d="M12 22s8-3.5 8-10V5l-8-3-8 3v7c0 6.5 8 10 8 10z"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
        'truck'    => '<path d="M2.5 6h11.5v10H2.5z"/><path d="M14 9h4l3.5 3.5V16H14"/><circle cx="6.5" cy="18" r="2"/><circle cx="17.5" cy="18" r="2"/>',
        'cart'     => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2.5 3h2.5l2.4 12h11.1l2-8H6"/>',
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'chart'    => '<path d="M3 3v18h18"/><path d="M8 17v-5M13 17V8M18 17v-9"/>',
        'settings' => '<path d="M4 6h9M17 6h3M4 12h3M11 12h9M4 18h11M19 18h1"/><circle cx="15" cy="6" r="2"/><circle cx="9" cy="12" r="2"/><circle cx="17" cy="18" r="2"/>',
        'logout'   => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l-5-5 5-5"/><path d="M5 12h12"/>',
        'left'     => '<path d="m15 18-6-6 6-6"/>',
        'right'    => '<path d="m9 18 6-6-6-6"/>',
        'down'     => '<path d="m6 9 6 6 6-6"/>',
        'alert'    => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'printer'  => '<path d="M6 9V2.5h12V9"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M6 14h12v7.5H6z"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'check'    => '<path d="m5 12 5 5 9-9"/>',
        'x'        => '<path d="M18 6 6 18M6 6l12 12"/>',
        'box'      => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="M3 8l9 5 9-5"/><path d="M12 13v8"/>',
        'store'    => '<path d="M4 4h16l1 5H3z"/><path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/><path d="M5 12v9h14v-9"/><path d="M10 21v-5h4v5"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'info'     => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 8h.01"/>',
        'calendar' => '<rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/>',
        'send'     => '<path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4z"/>',
        'scan'     => '<path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 8v8M10 8v8M13 8v8M17 8v8"/>',
        'camera'   => '<path d="M4 7h3l2-3h6l2 3h3a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1z"/><circle cx="12" cy="13" r="4"/>',
        'upload'   => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/>',
        'download' => '<path d="M12 4v12M7 11l5 5 5-5"/><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/>',
        'refresh'  => '<path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 4v7h-7"/>',
        'phone'    => '<rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M11 18.5h2"/>',
        'link'     => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
        'save'     => '<path d="M5 3h11l5 5v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M7 3v5h8"/><path d="M7 21v-7h10v7"/>',
    ];
    $body = $p[$name] ?? $p['box'];
    $aria = $label !== '' ? 'role="img" aria-label="'.htmlspecialchars($label, ENT_QUOTES).'"' : 'aria-hidden="true"';
    return '<svg class="ic" width="'.(int)$size.'" height="'.(int)$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" '.
           'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" '.$aria.'>'.$body.'</svg>';
}
