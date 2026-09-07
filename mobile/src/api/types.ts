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
  code?: string;
  balance?: number;
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
  has_personal_info?: boolean;
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
  has_personal_info?: boolean;
}

export type CivilStatus = 'single' | 'married' | 'widowed' | 'separated' | 'divorced';
export type Sex = 'male' | 'female';

export interface MemberProfile {
  region_code: string | null;
  region_name: string;
  province_code: string | null;
  province_name: string | null;
  city_municipality_code: string | null;
  city_municipality_name: string;
  barangay_code: string | null;
  barangay_name: string;
  street: string | null;
  sitio: string | null;
  civil_status: CivilStatus;
  sex: Sex;
  contact_no: string;
  date_of_seminar: string | null;
  remarks: string | null;
  address: string | null;
}

export interface StoreMemberProfilePayload {
  region_code?: string | null;
  region_name: string;
  province_code?: string | null;
  province_name?: string | null;
  city_municipality_code?: string | null;
  city_municipality_name: string;
  barangay_code?: string | null;
  barangay_name: string;
  street?: string | null;
  sitio?: string | null;
  civil_status: CivilStatus;
  sex: Sex;
  contact_no: string;
  date_of_seminar?: string | null;
  remarks?: string | null;
}

export interface StoreMemberProfileResponse {
  message: string;
  data: MemberProfile;
  needs_membership_stepper: boolean;
  has_personal_info: boolean;
}

export interface MembershipPrivacy {
  title: string;
  summary: string;
  body: string;
}

export interface LinkedAccount {
  account_no: string;
  customer: string | null;
  status: string | null;
  meter_no?: string | null;
  address?: string | null;
  rate_class?: string | null;
}

export interface ServiceInfo {
  account_number: string;
  owner_name: string | null;
  status: string;
  meter_no?: string | null;
  address?: string | null;
  rate_class?: string | null;
  source?: 'linked_account' | 'account_link' | string | null;
}

export interface DashboardBilling {
  current_bill_id: number | null;
  amount_due: number | null;
  pending_count: number;
  billing_period: string | null;
  due_date: string | null;
  as_of: string;
  has_data: boolean;
  outstanding_bills: {
    id: number;
    amount: number;
    balance_due: number;
    status: string | null;
    account_number?: string | null;
    billing_date: string | null;
  }[];
}

export interface DashboardConsumer {
  name: string;
  email: string;
  contact_no: string | null;
}

export interface WalletAccount {
  account_number: string;
  balance: number;
}

export interface WalletSummary {
  account_number: string | null;
  balance: number;
  /** Sum of AST across all linked accounts (when provided by API). */
  total_balance?: number;
  unit: string;
  accounts: WalletAccount[];
}

export interface WalletTransaction {
  id: number;
  account_number: string | null;
  billing_upload_id?: number | null;
  type: 'load' | 'pay' | 'adjust' | 'reversal' | string;
  amount: number;
  balance_after: number;
  reference: string;
  cis_status: string;
  cis_external_ref?: string | null;
  created_at: string | null;
  bill?: {
    id: number;
    amount: number;
    balance_due: number;
    status: string | null;
  } | null;
}

export interface WalletTransactionsResponse {
  data: WalletTransaction[];
}

export interface CustomerWalletTransactionsResponse {
  data: WalletTransaction[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface PayWalletResponse {
  message: string;
  idempotent: boolean;
  id: number;
  account_number: string | null;
  type: string;
  amount: number;
  balance_after: number;
  balance: number;
  reference: string;
  cis_status: string;
  created_at: string | null;
}

export interface PayBillResponse {
  message: string;
  idempotent: boolean;
  id: number;
  account_number: string | null;
  billing_upload_id: number | null;
  type: string;
  amount: number;
  balance_after: number;
  reference: string;
  receipt_no: string;
  cis_status: string;
  created_at: string | null;
}

export interface DashboardSummary {
  consumer: DashboardConsumer;
  service: ServiceInfo | null;
  billing: DashboardBilling;
  linked_accounts: LinkedAccount[];
  account_links: AccountLink[];
  wallet: WalletSummary | null;
}

export interface LedgerAccount {
  account_number: string;
  consumer_name: string | null;
  consumer_address: string | null;
  consumer_status: string | null;
}

export interface LedgerConsumer {
  account_number: string;
  name: string | null;
  address: string | null;
  status: string | null;
  meter_no?: string | null;
  rate_class?: string | null;
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

export interface LedgerHistoryMonth {
  bill_month: string | null;
  label: string;
  debit: number;
  credit: number;
  kwh: number;
  balance: number | null;
  due_date: string | null;
}

export interface LedgerEntry {
  id: string;
  type: 'bill' | 'payment';
  title: string;
  date: string;
  posted_at?: string | null;
  ref: string;
  amount: number;
  debit?: number | null;
  credit?: number | null;
  kwh?: number | null;
  demand_kw?: number | null;
  previous_reading?: number | null;
  present_reading?: number | null;
  balance?: number | null;
  bill_month?: string | null;
  due_date?: string | null;
}

export interface LedgerPagination {
  page: number;
  per_page: number;
  total: number;
  last_page: number;
  from: number;
  to: number;
}

export interface LedgerResponse {
  account: LedgerAccount;
  consumer?: LedgerConsumer;
  accounts: string[];
  summary: LedgerSummary;
  history: LedgerHistoryMonth[];
  entries: LedgerEntry[];
  sort?: 'latest' | 'oldest';
  pagination?: LedgerPagination;
}

export type NotificationCategory = 'billing' | 'service' | 'alert';

export interface AppNotification {
  id: number;
  category: NotificationCategory;
  title: string;
  body: string;
  data: Record<string, unknown> | null;
  read_at: string | null;
  created_at: string;
  unread: boolean;
}

export interface NotificationsListResponse {
  data: AppNotification[];
  unread_count: number;
}

export interface NotificationPreferences {
  billing: boolean;
  service: boolean;
  alert: boolean;
}

// -----------------------------
// Customer tickets (Complaint & Request)
// -----------------------------

export type TicketStatus =
  | 'new'
  | 'endorsed'
  | 'assigned'
  | 'in_progress'
  | 'awaiting_feedback'
  | 'escalated'
  | 'resolved'
  | 'closed'
  | 'reopened';

export interface TicketCategoryMini {
  id: number;
  name: string;
  department_code: string;
  default_assignee_role: string;
  sla_minutes: number;
  requires_payment_check: boolean;
  requires_tsd_check: boolean;
}

export interface TicketStatusHistoryItem {
  id: number;
  from_status: TicketStatus | null;
  to_status: TicketStatus;
  changed_by: number | null;
  // Hidden for staff-authored notes; UI should only render when present.
  remarks: string | null;
  created_at: string | null;
}

export interface TicketActionItem {
  id: number;
  actor_role: string | null;
  action_taken: string | null;
  minutes_taken: number;
  requires_payment: boolean;
  requires_tsd_intervention: boolean;
  created_at: string | null;
}

export interface TicketAttachmentItem {
  id: number;
  file_type: string;
  file_size: number;
  uploaded_at: string | null;
  download_url: string;
}

export interface CustomerTicketDetail {
  id: number;
  ticket_no: string;
  customer_id: number;
  category: TicketCategoryMini | null;
  subcategory: string | null;
  channel: string;
  description: string;
  status: TicketStatus;
  priority: 'low' | 'normal' | 'high' | 'urgent' | string | null;
  assigned_to: number | null;
  assigned_department: string | null;
  sla_due_at: string | null;
  created_by: string;
  created_at: string | null;
  updated_at: string | null;
  attachments: TicketAttachmentItem[];
  status_history: TicketStatusHistoryItem[];
  actions: TicketActionItem[];
}

export interface CustomerTicketListItem {
  id: number;
  ticket_no: string;
  category?: { name?: string } | TicketCategoryMini | null;
  description?: string;
  status?: TicketStatus;
  created_at?: string | null;
  updated_at?: string | null;
}

export type AiIntent = 'general' | 'billing' | 'outage' | 'complaint' | 'account' | 'unknown' | string;
export type AiSuggestedAction = 'none' | 'file_ticket' | 'view_tickets' | 'pay_bill' | 'contact_support' | string;

export interface AiNextStep {
  label: string;
  href: string | null;
}

export interface AiCustomerReply {
  reply: string;
  intent: AiIntent;
  escalate: boolean;
  suggested_action: AiSuggestedAction;
  category_hint: string | null;
  next_step: AiNextStep;
  conversation_id: number;
  source: 'openai' | 'fallback' | string;
  workflow?: string;
  knowledge_used?: boolean;
  knowledge_sufficient?: boolean;
  citations?: {
    document_id: number;
    title: string;
    score: number;
    category?: string | null;
    excerpt?: string;
  }[];
  actions?: { label: string; href: string; action: string }[];
  follow_ups?: string[];
  suggested_questions?: { label: string; message: string }[];
  human_required?: boolean;
  escalation_reason?: string | null;
  billing_snapshot?: {
    amount_due?: number;
    pending_bill_count?: number;
    ast_balance?: number | null;
    can_pay_in_app?: boolean;
  } | null;
  open_tickets?: {
    id: number;
    ticket_no: string;
    status: string;
    category?: string | null;
  }[];
  can_mutate_billing?: boolean;
  can_mutate_tickets?: boolean;
}

export interface AiBootstrap {
  welcome: string[];
  suggested_questions: { label: string; message: string }[];
  actions: { label: string; href: string; action: string }[];
  capabilities: string[];
  limits: {
    can_mutate_billing: boolean;
    can_mutate_tickets: boolean;
    can_process_payments: boolean;
  };
}

export interface AiEscalateResponse {
  escalated: boolean;
  mode?: string;
  message?: string;
  code?: string;
  applies_financial_changes?: boolean;
  ticket?: {
    id: number;
    ticket_no: string;
    status: string;
    category?: { id?: number; name?: string; department_code?: string } | null;
  };
  next_step?: { label: string; href: string };
}

export interface AiConversationListItem {
  id: number;
  title: string | null;
  last_message_at: string | null;
  updated_at: string | null;
}

export interface AiConversationMessage {
  id: number;
  role: 'user' | 'assistant' | string;
  content: string;
  intent: string | null;
  suggested_action: string | null;
  escalate: boolean;
  created_at: string | null;
}

export interface AiConversationDetail {
  id: number;
  title: string | null;
  last_message_at: string | null;
  messages: AiConversationMessage[];
}
