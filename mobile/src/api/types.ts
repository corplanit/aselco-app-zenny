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
