// Drop-in for sonner's `toast` API: toast(title), toast.success(title, { description }), toast.error(...), toast.dismiss()
const emit = (type) => (title, opts = {}) =>
    window.dispatchEvent(new CustomEvent('toast', { detail: { type, title, description: opts.description ?? '' } }));

export const toast = Object.assign(emit('normal'), {
    success: emit('success'),
    error: emit('error'),
    info: emit('info'),
    warning: emit('warning'),
    message: emit('normal'),
    dismiss: () => window.dispatchEvent(new CustomEvent('toast:dismiss')),
});
export default toast;
