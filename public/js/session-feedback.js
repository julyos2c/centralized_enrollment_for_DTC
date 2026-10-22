/* Explicit response checks only: no request retries, token refresh or session extension. */
(function () {
    window.dtcHttpFailure = function (status) {
        const messages = {
            401: 'Your session has ended. Sign in again to continue.',
            403: 'Access is restricted. Your account may require a password change, or you may not have permission.',
            419: 'This page has expired. Reload it before trying again. Unsaved changes may need to be copied first.'
        };
        const message = messages[status] || 'The request failed. Please try again when you are ready.';
        if (window.dtcToast) window.dtcToast.error(message);
        else window.alert(message);
    };
    window.dtcCheckResponse = function (response) {
        const redirectedToAuth = response.redirected && /\/(login|account\/password)(?:[?#]|$)/.test(response.url);
        if (!response.ok || redirectedToAuth) {
            window.dtcHttpFailure(redirectedToAuth ? 401 : response.status);
            const error = new Error('Request was not completed.');
            error.dtcReported = true;
            throw error;
        }
        return response;
    };
    window.dtcRequestFailure = function (error) {
        if (!error || !error.dtcReported) window.dtcHttpFailure(0);
    };
    window.dtcFetch = function (url, options) {
        return fetch(url, options).then(window.dtcCheckResponse);
    };
})();
