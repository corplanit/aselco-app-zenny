import { apiRequest } from './client';
import { Capacitor } from '@capacitor/core';

export function registerDeviceToken(authToken: string, deviceToken: string): Promise<{ message: string }> {
  const platform = Capacitor.getPlatform(); // android | ios | web

  return apiRequest('/devices', {
    method: 'POST',
    token: authToken,
    body: JSON.stringify({
      token: deviceToken,
      platform: platform === 'ios' || platform === 'android' ? platform : 'android',
    }),
  });
}

export function unregisterDeviceToken(authToken: string, deviceToken: string): Promise<{ message: string }> {
  return apiRequest('/devices', {
    method: 'DELETE',
    token: authToken,
    body: JSON.stringify({ token: deviceToken }),
  });
}
