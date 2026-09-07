import { Capacitor } from '@capacitor/core';
import { PushNotifications } from '@capacitor/push-notifications';
import { registerDeviceToken } from '../api/notifications';

let initialized = false;
let currentAuthToken: string | null = null;

function resolveDeepLink(data: Record<string, unknown> | undefined): string | null {
  if (!data) return null;

  const deepLink = typeof data.deep_link === 'string' ? data.deep_link : undefined;
  if (deepLink) return deepLink;

  const ticketId = data.ticket_id != null ? String(data.ticket_id) : undefined;
  if (ticketId) return `/tickets/${ticketId}`;

  const conversationId = data.conversation_id != null ? String(data.conversation_id) : undefined;
  if (conversationId || data.category === 'chat') return '/support/chat';

  const announcementId = data.announcement_id != null ? String(data.announcement_id) : undefined;
  if (announcementId || data.category === 'announcement') return '/notifications';

  if (data.category === 'billing' || data.category === 'wallet') return '/tabs/ledger';

  return null;
}

export const initializePushNotifications = async (authToken: string | null): Promise<void> => {
  currentAuthToken = authToken;

  if (!Capacitor.isNativePlatform()) {
    return;
  }

  if (!initialized) {
    initialized = true;

    await PushNotifications.addListener('registration', (token) => {
      console.info(`Push notification token: ${token.value}`);

      if (currentAuthToken) {
        void registerDeviceToken(currentAuthToken, token.value)
          .then(() => console.info('Push notification token registered with API.'))
          .catch((error) => console.error(`Unable to register push token with API: ${String(error)}`));
      }
    });

    await PushNotifications.addListener('registrationError', (error) => {
      console.error(`Push notification registration failed: ${JSON.stringify(error)}`);
    });

    await PushNotifications.addListener('pushNotificationReceived', (notification) => {
      console.info('Push notification received:', notification);
    });

    await PushNotifications.addListener('pushNotificationActionPerformed', (action) => {
      console.info('Push notification opened:', action.notification);
      const notificationData =
        (action.notification as { data?: Record<string, unknown> } | undefined)?.data ?? undefined;
      const actionData = (action as { data?: Record<string, unknown> } | undefined)?.data ?? undefined;
      const data = notificationData ?? actionData;
      const target = resolveDeepLink(data);

      if (target) {
        // Using full location fallback because this module is outside React router context.
        window.location.href = target;
      }
    });
  }

  try {
    let permission = await PushNotifications.checkPermissions();

    if (permission.receive === 'prompt') {
      permission = await PushNotifications.requestPermissions();
    }

    if (permission.receive === 'granted') {
      await PushNotifications.register();
    }
  } catch (error) {
    const message = error instanceof Error ? error.message : JSON.stringify(error);
    console.error(`Push notification setup failed: ${message}`);
  }
};
