/** Aligns with Laravel Api\V1\UserResource */

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
  contact_no: string | null;
  role: string | null;
  profile_photo_url: string;
}

export interface LoginPayload {
  email: string;
  password: string;
  device_name?: string;
}

export interface RegisterPayload {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  terms: boolean;
}

export interface LoginResponse {
  token: string;
  token_type: string;
  user: AuthUser;
}

export interface RegisterResponse {
  message: string;
  email_verified: boolean;
  user: AuthUser;
}

export interface ApiErrorBody {
  message?: string;
  email_verified?: boolean;
  user?: AuthUser;
  errors?: Record<string, string[]>;
  account_link?: AccountLink;
}

export class ApiError extends Error {
  status: number;
  data: ApiErrorBody;

  constructor(message: string, status: number, data: ApiErrorBody = {}) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.data = data;
  }
}

export interface MembershipStatus {
  needs_membership_stepper: boolean;
  has_pending_link: boolean;
  has_validated_link: boolean;
  pending_count: number;
  validated_count: number;
  link_count?: number;
  max_links?: number;
  can_add_another_link?: boolean;
}

export interface AccountLink { 
  id: number;
  account_number: string;
  owner_name: string;
  status: 'pending' | 'validated';
  validated_at: string | null;
  validated_by?: string | null;
  created_at: string;
}

export interface StoreAccountLinkPayload {
  account_number: string;
  owner_name: string;
  privacy_accepted: true;
}

export interface StoreAccountLinkResponse {
  message: string;
  account_link: AccountLink;
  link_count?: number;
  max_links?: number;
  can_add_another_link?: boolean;
  needs_membership_stepper?: boolean;
}

export interface MembershipPrivacy {
  title: string;
  summary: string;
  body: string;
}


//ZENNY added lines, kay mag-error kung magparun kog npm run build

export interface LinkedAccount {
  account_no: string;
  customer: string | null;
  status: string | null;
  meter_no: string | null;
  address: string | null;
  rate_class: string | null;
}

export interface ServiceInfo {
  account_number: string;
  owner_name: string | null;
  status: string;
  meter_no: string | null;
  address: string | null;
  rate_class: string | null;
  source: 'linked_account' | 'account_link';
}

export interface DashboardConsumer {
  name: string;
  email: string;
  contact_no: string | null;
}

export interface DashboardBilling {
  amount_due: number | null;
  pending_count: number;
  billing_period: string | null;
  due_date: string | null;
  as_of: string;
  has_data: boolean;
}

export interface DashboardSummary {
  consumer: DashboardConsumer;
  service: ServiceInfo | null;
  billing: DashboardBilling;
  linked_accounts: LinkedAccount[];
  account_links: AccountLink[];
  wallet: null;
}

export interface LedgerEntry {
  id: string | number;
  type: 'bill' | 'payment';
  title: string;
  date: string;
  posted_at: string;
  ref: string;
  amount: number;
  debit: number;
  credit: number;
  kwh: number | null;
  demand_kw?: number | null;
  previous_reading?: number | null;
  present_reading?: number | null;
  balance?: number | null;
  bill_month?: string | null;
  due_date: string | null;
}

export interface LedgerPagination {
  page: number;
  per_page: number;
  total: number;
  last_page: number;
  from: number;
  to: number;
}

export interface LedgerAccount {
  account_number: string;
  consumer_name: string | null;
  consumer_address: string | null;
  consumer_status: string | null;
}

export interface LedgerSummary {
  account_number: string;
  current_balance: number;
  current_due: number;
  total_paid: number;
  kwh_used: number;
  billing_period: string | null;
  due_date: string | null;
  pending_count: number;
}

export interface LedgerHistoryItem {
  bill_month: string | null;
  label: string;
  debit: number;
  credit: number;
  kwh: number;
  balance: number | null;
  due_date: string | null;
}

export interface LedgerResponse {
  account: LedgerAccount;
  accounts: string[];
  summary: LedgerSummary;
  history: LedgerHistoryItem[];
  entries: LedgerEntry[];
  sort: 'latest' | 'oldest';
  pagination?: LedgerPagination;
}