import { reactive } from 'vue';

// The one notice shown at the top of the page after an action.
export const notice = reactive({ current: null });

export function showNotice(value) {
    notice.current = value ? { tone: value.tone || 'success', text: value.text } : null;
}
