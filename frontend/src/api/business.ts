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

export const submitAppeal = (businessId: number, reason: string) =>
  client.post(`/businesses/${businessId}/credit-score/appeal`, { reason });

export const postRepaymentOutcome = (phone: string, loanAmount: number, outcome: string) =>
  client.post('/lender/repayment-outcome', { phone, loan_amount: loanAmount, outcome });

export const getBookkeepingSales = (businessId: number) =>
  client.get(`/bk/sales`);

export const getBookkeepingDailyReport = () =>
  client.get(`/bk/reports/daily`);

export const getBookkeepingSummary = (days: number = 30) =>
  client.get(`/bk/reports/summary?days=${days}`);

export const getBookkeepingProducts = () =>
  client.get(`/bk/reports/products`);

export const getBookkeepingDebtors = () =>
  client.get(`/bk/reports/debtors`);

export const getEnhancedScore = (businessId: number) =>
  client.post(`/businesses/${businessId}/credit-score/enhanced`);

export const getLenderPreApprovals = (apiKey: string, minScore: number = 500) =>
  client.get(`/lender/pre-approvals?min_score=${minScore}`, {
    headers: { 'X-Lender-API-Key': apiKey }
  });

export const getLenderPortfolio = (apiKey: string) =>
  client.get(`/lender/portfolio`, {
    headers: { 'X-Lender-API-Key': apiKey }
  });

export const getLenderRevenue = (apiKey: string) =>
  client.get(`/lender/revenue`, {
    headers: { 'X-Lender-API-Key': apiKey }
  });

export const getEmployees = () =>
  client.get('/bk/employees');

export const getEmployeePerformance = () =>
  client.get('/bk/employees/performance');

export const clockInEmployee = (employeeId: number, branchId: number) =>
  client.post(`/bk/employees/${employeeId}/clock-in`, { branch_id: branchId });

export const clockOutEmployee = (employeeId: number) =>
  client.post(`/bk/employees/${employeeId}/clock-out`);

export const createEmployee = (data: object) =>
  client.post('/bk/employees', data);