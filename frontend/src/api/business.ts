import client from './client';

export const getBusinesses = () =>
  client.get('/businesses');

export const createBusiness = (data: object) =>
  client.post('/businesses', data);

export const getBusiness = (id: number) =>
  client.get(`/businesses/${id}`);

export const getTransactions = (businessId: number) =>
  client.get(`/businesses/${businessId}/transactions`);

export const parseSms = (businessId: number, smsText: string) =>
  client.post(`/businesses/${businessId}/transactions/parse-sms`, {
    sms_text: smsText,
  });

export const requestCreditScore = (businessId: number) =>
  client.post(`/businesses/${businessId}/credit-score/request`);

export const getCreditScore = (businessId: number) =>
  client.get(`/businesses/${businessId}/credit-score`);

export const getAlerts = (businessId: number) =>
  client.get(`/businesses/${businessId}/alerts`);

export const getBusinessSummary = (businessId: number) =>
  client.get(`/businesses/${businessId}/summary`);

export const getNetworkAnalysis = (businessId: number) =>
  client.get(`/businesses/${businessId}/network`);

export const getForecast = (businessId: number) =>
  client.get(`/businesses/${businessId}/forecast`);

export const getBotCompliance = (businessId: number) =>
  client.get(`/businesses/${businessId}/bot-compliance`);

export const getCreditReport = (businessId: number) =>
  client.get(`/businesses/${businessId}/credit-report`, { responseType: 'blob' });