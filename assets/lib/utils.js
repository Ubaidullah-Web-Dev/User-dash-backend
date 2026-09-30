// lodash.debounce (trailing edge, with cancel()) — the only lodash function the frontend used
export function debounce(fn, wait = 0) {
    let timer;
    const debounced = function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), wait);
    };
    debounced.cancel = () => clearTimeout(timer);
    debounced.flush = () => {};
    return debounced;
}

/** Trigger a browser download for a Blob (the frontend's createObjectURL + <a download> pattern). */
export function downloadBlob(blob, filename) {
    const url = window.URL.createObjectURL(new Blob([blob]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
}
