import { apiRequest } from './client';

export function registerDeviceToken(authToken: string, deviceToken: string): Promise<{ message: string }> {
  return apiRequest('/device-tokens', {
    method: 'POST',
    token: authToken,
    body: JSON.stringify({ token: deviceToken, platform: 'android' }),
  });
}
