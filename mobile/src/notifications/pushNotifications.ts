import { Capacitor } from '@capacitor/core';
import { PushNotifications } from '@capacitor/push-notifications';
import { registerDeviceToken } from '../api/notifications';

let initialized = false;
let currentAuthToken: string | null = null;

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
