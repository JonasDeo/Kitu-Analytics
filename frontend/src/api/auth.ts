import client from './client';

export const register = (name: string, phone: string, password: string) =>
  client.post('/auth/register', { name, phone, password });

export const verifyOtp = (phone: string, otp: string) =>
  client.post('/auth/verify-otp', { phone, otp });

export const login = (phone: string, password: string) =>
  client.post('/auth/login', { phone, password });

export const logout = () =>
  client.post('/auth/logout');

export const me = () =>
  client.get('/auth/me');