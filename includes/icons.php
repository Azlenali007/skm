<?php
/**
 * Sikkim Gaming Platform - Vector Icon Helper
 * High-definition SVG icons for categories and games
 */

function getCategoryIconSvg(string $slug): string {
    switch ($slug) {
        case 'hot-slots':
            return '<svg class="w-7 h-7 text-amber-500" viewBox="0 0 24 24" fill="currentColor">
                <path d="M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2zm2 4v8h3V8H6zm5 0v8h3V8h-3zm5 0v8h3V8h-3zM7 10h1v4H7v-4zm5 0h1v4h-1v-4zm5 0h1v4h-1v-4z"/>
            </svg>';
        case 'lottery':
            return '<svg class="w-7 h-7 text-rose-500" viewBox="0 0 24 24" fill="currentColor">
                <circle cx="12" cy="12" r="9" fill="#fecdd3" stroke="#e11d48" stroke-width="2"/>
                <path d="M10 9h4v2h-2v4h-2v-4H9V9h1z" fill="#be123c"/>
                <circle cx="7" cy="8" r="1.5" fill="#e11d48"/>
                <circle cx="17" cy="8" r="1.5" fill="#e11d48"/>
            </svg>';
        case 'original':
            return '<svg class="w-7 h-7 text-indigo-500" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2.5a.5.5 0 01.447.276l2.5 5A.5.5 0 0114.5 8.5H13v6h2a.5.5 0 01.447.724l-3 6a.5.5 0 01-.894 0l-3-6A.5.5 0 019 14.5h2v-6H9.5a.5.5 0 01-.447-.724l2.5-5A.5.5 0 0112 2.5z"/>
                <circle cx="12" cy="11" r="2" fill="#ffffff"/>
            </svg>';
        case 'slots':
            return '<svg class="w-7 h-7 text-blue-600" viewBox="0 0 24 24" fill="currentColor">
                <rect x="3" y="5" width="18" height="14" rx="3" fill="#bfdbfe" stroke="#1d4ed8" stroke-width="2"/>
                <circle cx="7" cy="12" r="2" fill="#1d4ed8"/>
                <circle cx="12" cy="12" r="2" fill="#1d4ed8"/>
                <circle cx="17" cy="12" r="2" fill="#1d4ed8"/>
            </svg>';
        case 'fishing':
            return '<svg class="w-7 h-7 text-cyan-500" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 3c-4.97 0-9 4.03-9 9 0 2.12.74 4.07 1.97 5.61L3 21l3.39-1.97C7.93 20.26 9.88 21 12 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm4 11l-3-1v3l-2-2v-4l5 2v2z"/>
            </svg>';
        case 'sports':
            return '<svg class="w-7 h-7 text-emerald-500" viewBox="0 0 24 24" fill="currentColor">
                <path d="M19 5h-2V3H7v2H5c-1.1 0-2 .9-2 2v1c0 2.55 1.92 4.63 4.39 4.94A5.01 5.01 0 0011 15.9V19H7v2h10v-2h-4v-3.1c1.86-.41 3.29-1.89 3.61-3.96C19.08 11.63 21 9.55 21 7V5c0-1.1-.9-2-2-2zM5 8V7h2v3.82C5.84 10.4 5 9.3 5 8zm14 0c0 1.3-.84 2.4-2 2.82V7h2v1z"/>
            </svg>';
        case 'casino':
            return '<svg class="w-7 h-7 text-purple-600" viewBox="0 0 24 24" fill="currentColor">
                <circle cx="12" cy="12" r="9" fill="#e9d5ff" stroke="#7e22ce" stroke-width="2"/>
                <circle cx="12" cy="12" r="4" fill="#7e22ce"/>
                <circle cx="12" cy="6" r="1.5" fill="#7e22ce"/>
                <circle cx="12" cy="18" r="1.5" fill="#7e22ce"/>
                <circle cx="6" cy="12" r="1.5" fill="#7e22ce"/>
                <circle cx="18" cy="12" r="1.5" fill="#7e22ce"/>
            </svg>';
        case 'rummy':
        default:
            return '<svg class="w-7 h-7 text-red-500" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
            </svg>';
    }
}
