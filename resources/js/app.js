import './bootstrap.js';

import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

import 'admin-lte';

import Alpine from 'alpinejs';
window.Alpine = Alpine;

// Chart.js — exposed globally so admin views can render charts inline
// without re-bundling. The reporting module imports more controllers
// on top of this (see reporting-dashboard.js).
import {
    Chart, BarController, BarElement,
    LineController, LineElement, PointElement,
    DoughnutController, ArcElement,
    CategoryScale, LinearScale,
    Tooltip, Legend, Title, Filler,
} from 'chart.js';
Chart.register(
    BarController, BarElement,
    LineController, LineElement, PointElement,
    DoughnutController, ArcElement,
    CategoryScale, LinearScale,
    Tooltip, Legend, Title, Filler,
);
window.Chart = Chart;

// DataTables registers as a side-effect import and exposes `window.cukDataTable`.
import './admin/data-table.js';
import { resourceCrud } from './admin/resource-crud.js';

// Custom Alpine components for the back-office.
import { notesGrid } from './admin/notes-grid.js';
import { selectionWizard } from './admin/selection-wizard.js';
import { reportingDashboard } from './admin/reporting-dashboard.js';
import { settingsForm, settingsEditor } from './admin/settings-form.js';

Alpine.data('notesGrid', notesGrid);
Alpine.data('selectionWizard', selectionWizard);
Alpine.data('reportingDashboard', reportingDashboard);
Alpine.data('settingsForm', settingsForm);
Alpine.data('settingsEditor', settingsEditor);
Alpine.data('resourceCrud', resourceCrud);

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
