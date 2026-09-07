// --------------------
// Axios Setup
// --------------------
import axios from 'axios';

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios = axios;

// --------------------
// Laravel Echo + Pusher
// --------------------
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Production-ready Echo instance (Pusher Cloud — do not override wsHost)
window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'ap1',
    forceTLS: (import.meta.env.VITE_PUSHER_USETLS ?? 'true') === 'true',
    encrypted: true,
    authEndpoint: '/broadcasting/auth',
    auth: {
        headers: {
            'X-CSRF-TOKEN': document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content'),
        },
    },
    disableStats: true,
    enabledTransports: ['ws', 'wss'],
});

// --------------------
// Debug Logging (optional, remove in production)
// --------------------
console.log('Echo initialized:', window.Echo);

window.Echo.connector.pusher.connection.bind('connected', () => {
    console.log('✅ Pusher connected:', window.Echo.connector.pusher.connection.socket_id);
});

window.Echo.connector.pusher.connection.bind('error', (err) => {
    console.error('❌ Pusher connection error:', err);
});

window.Echo.connector.pusher.connection.bind('disconnected', () => {
    console.warn('⚠️ Pusher disconnected');
});

// --------------------
// Helper: Subscribe to private chat channels
// --------------------
window.subscribeToConversation = (conversationId, callback) => {
    return window.Echo.private(`conversations.${conversationId}`)
        .listen('.message.sent', callback);
};

window.subscribeToUser = (userId, callback) => {
    return window.Echo.private(`users.${userId}`)
        .listen('.conversation.updated', callback);
};

