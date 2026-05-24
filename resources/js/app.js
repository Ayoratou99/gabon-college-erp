import './bootstrap.js';

import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

import 'admin-lte';

import Alpine from 'alpinejs';
window.Alpine = Alpine;

// Custom Alpine components for the back-office.
import { notesGrid } from './admin/notes-grid.js';
import { selectionWizard } from './admin/selection-wizard.js';
import { reportingDashboard } from './admin/reporting-dashboard.js';

Alpine.data('notesGrid', notesGrid);
Alpine.data('selectionWizard', selectionWizard);
Alpine.data('reportingDashboard', reportingDashboard);

Alpine.start();

/**
 * Auto-init dismissible alerts and tooltips present on page load.
 * Custom widgets can register against document on `cuk:ready`.
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        new bootstrap.Tooltip(el);
    });
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => {
        new bootstrap.Popover(el);
    });
    document.dispatchEvent(new CustomEvent('cuk:ready'));
});
