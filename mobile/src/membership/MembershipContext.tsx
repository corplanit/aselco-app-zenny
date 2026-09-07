import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react';
import * as membershipApi from '../api/membership';
import type {
  AccountLink,
  LinkedAccount,
  MemberProfile,
  MembershipStatus,
  StoreAccountLinkPayload,
  StoreAccountLinkResponse,
  StoreMemberProfilePayload,
  StoreMemberProfileResponse,
} from '../api/types';
import { useAuth } from '../auth/AuthContext';

interface MembershipContextValue {
  status: MembershipStatus | null;
  links: AccountLink[];
  linkedAccounts: LinkedAccount[];
  profile: MemberProfile | null;
  isLoading: boolean;
  linksLoading: boolean;
  needsMembershipStepper: boolean;
  hasPersonalInfo: boolean;
  linkCount: number;
  canAddAnotherLink: boolean;
  refreshStatus: () => Promise<MembershipStatus | null>;
  refreshLinks: () => Promise<AccountLink[]>;
  refreshLinkedAccounts: () => Promise<LinkedAccount[]>;
  refreshProfile: () => Promise<MemberProfile | null>;
  submitLink: (payload: StoreAccountLinkPayload) => Promise<StoreAccountLinkResponse>;
  saveProfile: (payload: StoreMemberProfilePayload) => Promise<StoreMemberProfileResponse>;
  markStepperComplete: () => void;
}

const MembershipContext = createContext<MembershipContextValue | undefined>(undefined);

const emptyStatus = (): MembershipStatus => ({
  needs_membership_stepper: true,
  has_pending_link: false,
  has_validated_link: false,
  has_personal_info: false,
  pending_count: 0,
  validated_count: 0,
  link_count: 0,
  max_links: 2,
  can_add_another_link: true,
});

export function MembershipProvider({ children }: { children: ReactNode }) {
  const { token, isAuthenticated } = useAuth();
  const [status, setStatus] = useState<MembershipStatus | null>(null);
  const [links, setLinks] = useState<AccountLink[]>([]);
  const [linkedAccounts, setLinkedAccounts] = useState<LinkedAccount[]>([]);
  const [profile, setProfile] = useState<MemberProfile | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [linksLoading, setLinksLoading] = useState(false);

  const refreshLinks = useCallback(async () => {
    if (!token) {
      setLinks([]);
      return [];
    }

    setLinksLoading(true);
    try {
      const result = await membershipApi.listAccountLinks(token);
      const next = Array.isArray(result.data) ? result.data : [];
      setLinks(next);
      return next;
    } catch {
      return [];
    } finally {
      setLinksLoading(false);
    }
  }, [token]);

  const refreshLinkedAccounts = useCallback(async () => {
    if (!token) {
      setLinkedAccounts([]);
      return [];
    }

    try {
      const result = await membershipApi.listLinkedAccounts(token);
      const next = Array.isArray(result.data) ? result.data : [];
      setLinkedAccounts(next);
      return next;
    } catch {
      return [];
    }
  }, [token]);

  const refreshStatus = useCallback(async () => {
    if (!token) {
      setStatus(null);
      setLinks([]);
      setLinkedAccounts([]);
      setProfile(null);
      return null;
    }

    // Do not toggle boot `isLoading` when status already exists (avoids full-app spinner loop).
    const isInitial = status === null;
    if (isInitial) {
      setIsLoading(true);
    }

    try {
      const next = await membershipApi.getMembershipStatus(token);
      setStatus(next);
      return next;
    } catch {
      setStatus((prev) => {
        if (prev && prev.needs_membership_stepper === false) {
          return prev;
        }
        return emptyStatus();
      });
      return null;
    } finally {
      if (isInitial) {
        setIsLoading(false);
      }
    }
  }, [token, status]);

  const refreshProfile = useCallback(async () => {
    if (!token) {
      setProfile(null);
      return null;
    }

    try {
      const result = await membershipApi.getMemberProfile(token);
      const next = result.data ?? null;
      setProfile(next);
      return next;
    } catch {
      return null;
    }
  }, [token]);

  useEffect(() => {
    if (!isAuthenticated || !token) {
      setStatus(null);
      setLinks([]);
      setLinkedAccounts([]);
      setProfile(null);
      setIsLoading(false);
      setLinksLoading(false);
      return;
    }

    let cancelled = false;
    setIsLoading(true);
    setLinksLoading(true);

    (async () => {
      try {
        const [nextStatus, nextLinks, nextLinked, nextProfile] = await Promise.all([
          membershipApi.getMembershipStatus(token),
          membershipApi.listAccountLinks(token),
          membershipApi.listLinkedAccounts(token).catch(() => ({ data: [] as LinkedAccount[] })),
          membershipApi.getMemberProfile(token).catch(() => ({ data: null as MemberProfile | null })),
        ]);
        if (!cancelled) {
          setStatus(nextStatus);
          setLinks(Array.isArray(nextLinks.data) ? nextLinks.data : []);
          setLinkedAccounts(Array.isArray(nextLinked.data) ? nextLinked.data : []);
          setProfile(nextProfile.data ?? null);
        }
      } catch {
        if (!cancelled) {
          setStatus(emptyStatus());
          setLinks([]);
          setLinkedAccounts([]);
          setProfile(null);
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false);
          setLinksLoading(false);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [isAuthenticated, token]);

  const submitLink = useCallback(
    async (payload: StoreAccountLinkPayload) => {
      if (!token) {
        throw new Error('Not authenticated');
      }

      const result = await membershipApi.submitAccountLink(token, payload);
      const hasPersonalInfo = result.has_personal_info ?? Boolean(profile);

      setStatus((prev) => ({
        needs_membership_stepper: result.needs_membership_stepper ?? !hasPersonalInfo,
        has_pending_link: true,
        has_validated_link: prev?.has_validated_link ?? false,
        has_personal_info: hasPersonalInfo,
        pending_count: (prev?.pending_count ?? 0) + 1,
        validated_count: prev?.validated_count ?? 0,
        link_count: result.link_count ?? (prev?.link_count ?? 0) + 1,
        max_links: result.max_links ?? 2,
        can_add_another_link: result.can_add_another_link ?? false,
      }));

      if (result.account_link) {
        setLinks((prev) => [result.account_link, ...prev.filter((l) => l.id !== result.account_link.id)]);
      }

      void refreshStatus();
      void membershipApi.listAccountLinks(token).then((res) => setLinks(res.data ?? []));

      return result;
    },
    [token, refreshStatus, profile],
  );

  const saveProfile = useCallback(
    async (payload: StoreMemberProfilePayload) => {
      if (!token) {
        throw new Error('Not authenticated');
      }

      const result = await membershipApi.saveMemberProfile(token, payload);
      setProfile(result.data);
      setStatus((prev) => ({
        ...(prev ?? emptyStatus()),
        needs_membership_stepper: result.needs_membership_stepper,
        has_personal_info: result.has_personal_info,
      }));
      void refreshStatus();
      return result;
    },
    [token, refreshStatus],
  );

  const markStepperComplete = useCallback(() => {
    setStatus((prev) =>
      prev
        ? { ...prev, needs_membership_stepper: false }
        : {
            ...emptyStatus(),
            needs_membership_stepper: false,
            can_add_another_link: false,
          },
    );
  }, []);

  const linkCount = status?.link_count ?? links.length;
  const canAddAnotherLink = status?.can_add_another_link ?? linkCount < 2;
  const hasPersonalInfo = Boolean(status?.has_personal_info || profile);

  const value = useMemo<MembershipContextValue>(
    () => ({
      status,
      links,
      linkedAccounts,
      profile,
      isLoading,
      linksLoading,
      needsMembershipStepper: Boolean(isAuthenticated && status?.needs_membership_stepper),
      hasPersonalInfo,
      linkCount,
      canAddAnotherLink,
      refreshStatus,
      refreshLinks,
      refreshLinkedAccounts,
      refreshProfile,
      submitLink,
      saveProfile,
      markStepperComplete,
    }),
    [
      status,
      links,
      linkedAccounts,
      profile,
      isLoading,
      linksLoading,
      isAuthenticated,
      hasPersonalInfo,
      linkCount,
      canAddAnotherLink,
      refreshStatus,
      refreshLinks,
      refreshLinkedAccounts,
      refreshProfile,
      submitLink,
      saveProfile,
      markStepperComplete,
    ],
  );

  return <MembershipContext.Provider value={value}>{children}</MembershipContext.Provider>;
}

export function useMembership(): MembershipContextValue {
  const ctx = useContext(MembershipContext);
  if (!ctx) {
    throw new Error('useMembership must be used within MembershipProvider');
  }
  return ctx;
}