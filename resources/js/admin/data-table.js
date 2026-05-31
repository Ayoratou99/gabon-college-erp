import DataTable from 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';
import 'datatables.net-buttons-bs5';

/**
 * French locale, applied once per table. Inlined to avoid pulling another
 * remote JSON file on every page render.
 */
const FR_LOCALE = {
    sProcessing:     'Traitement en cours…',
    sSearch:         'Rechercher&nbsp;:',
    sLengthMenu:     '_MENU_ par page',
    sInfo:           '_START_–_END_ sur _TOTAL_',
    sInfoEmpty:      '0 résultat',
    sInfoFiltered:   '(filtré sur _MAX_)',
    sInfoPostFix:    '',
    sLoadingRecords: 'Chargement…',
    sZeroRecords:    'Aucun résultat',
    sEmptyTable:     'Aucune donnée disponible',
    oPaginate: {
        sFirst:    '«',
        sPrevious: '‹',
        sNext:     '›',
        sLast:     '»',
    },
    oAria: {
        sSortAscending:  ': activer pour trier par ordre croissant',
        sSortDescending: ': activer pour trier par ordre décroissant',
    },
};

/**
 * Wire a `<table>` to a server-side AJAX endpoint following the DataTables
 * protocol. Returns the underlying DataTable instance so callers can hook
 * filter changes (drawing, redrawing, etc.).
 *
 * @param {string|HTMLElement} selector
 * @param {{ url: string, columns: Array, order?: Array, filters?: () => Object, pageLength?: number }} opts
 */
export function cukDataTable(selector, opts) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    return new DataTable(selector, {
        processing: true,
        serverSide: true,
        responsive: true,
        autoWidth: false,
        pageLength: opts.pageLength ?? 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        order: opts.order ?? [],
        language: FR_LOCALE,
        ajax: {
            url: opts.url,
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            data: (d) => {
                if (typeof opts.filters === 'function') {
                    return { ...d, filters: opts.filters() };
                }
                return d;
            },
        },
        columns: opts.columns,
    });
}

window.cukDataTable = cukDataTable;
