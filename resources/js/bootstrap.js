import axios from 'axios';

/**
 * Axios global configuration:
 *   - X-Requested-With for Laravel route detection
 *   - CSRF token from the <meta name="csrf-token"> tag (admin layout sets it)
 *   - throw on 4xx + 5xx so callers can use try/catch
 */
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrf = document.querySelector('meta[name="csrf-token"]');
if (csrf) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.getAttribute('content');
}

window.axios.defaults.withCredentials = true;
