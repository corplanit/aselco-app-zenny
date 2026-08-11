import {
  IonButton,
  IonContent,
  IonIcon,
  IonItem,
  IonLabel,
  IonList,
  IonPage,
  IonSpinner,
  IonToggle,
  useIonToast,
  useIonViewWillEnter,
} from '@ionic/react';
import {
  addCircleOutline,
  chevronForwardOutline,
  helpCircleOutline,
  linkOutline,
  lockClosedOutline,
  logOutOutline,
  personOutline,
} from 'ionicons/icons';
import { useState } from 'react';
import { useHistory } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import AppHeader from '../components/AppHeader';
import SectionHeader from '../components/SectionHeader';
import ServiceInfoCard from '../components/ServiceInfoCard';
import StatusPill from '../components/StatusPill';
import { member } from '../data/mockData';
import { useMembership } from '../membership/MembershipContext';
import { displayOrDash, resolveServiceInfo } from '../utils/serviceAccount';

function formatStatusLabel(status: string): string {
  if (status === 'pending') {
    return 'Pending';
  }
  if (status === 'validated') {
    return 'Validated';
  }
  return status;
}

const Profile: React.FC = () => {
  const { user, signOut } = useAuth();
  const {
    links,
    linkedAccounts,
    linksLoading,
    canAddAnotherLink,
    linkCount,
    refreshLinks,
    refreshStatus,
    refreshLinkedAccounts,
  } = useMembership();
  const history = useHistory();
  const [billReminders, setBillReminders] = useState(true);
  const [outageAlerts, setOutageAlerts] = useState(true);
  const [signingOut, setSigningOut] = useState(false);
  const [present] = useIonToast();

  useIonViewWillEnter(() => {
    // Silent refresh only — never flip global membership boot loading.
    void refreshLinks();
    void refreshStatus();
    void refreshLinkedAccounts();
  });

  const toast = (msg: string) =>
    present({ message: msg, duration: 1800, color: 'primary' });

  const displayName = user?.name ?? member.name;
  const displayEmail = user?.email ?? member.email;
  const service = resolveServiceInfo(linkedAccounts ?? [], links ?? []); //const service = resolveServiceInfo(linkedAccounts, links);
  const displayMobile = user?.contact_no;
  const initials =
    displayName
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part[0]?.toUpperCase() ?? '')
      .join('') || member.initials;

  const onSignOut = async () => {
    if (signingOut) {
      return;
    }
    setSigningOut(true);
    try {
      await signOut();
      history.replace('/login');
    } catch {
      present({ message: 'Could not sign out. Try again.', duration: 2000, color: 'danger' });
    } finally {
      setSigningOut(false);
    }
  };

  const onLinkAnother = () => {
    if (!canAddAnotherLink) {
      present({
        message: 'You can link up to 2 electric accounts.',
        duration: 2200,
        color: 'warning',
      });
      return;
    }
    history.push('/membership/setup?add=1');
  };

  return (
    <IonPage>
      <AppHeader title="Profile" icon={personOutline} />
      <IonContent>
        <div className="page-pad">
          <div style={{ display: 'flex', alignItems: 'center', gap: 14, marginBottom: 8 }}>
            <div className="avatar-circle">{initials}</div>
            <div>
              <h2 className="font-display" style={{ margin: 0, fontSize: 20 }}>
                {displayName}
              </h2>
              <p style={{ margin: '4px 0 0', color: 'var(--aselco-ink-500)', fontSize: 13 }}>
                {displayEmail}
              </p>
              <p style={{ margin: '2px 0 0', color: 'var(--aselco-ink-400)', fontSize: 12 }}>
                Linked requests {linkCount}/2
              </p>
            </div>
          </div>

          <SectionHeader title="Service Information" />
          <ServiceInfoCard service={service} loading={linksLoading} />

          <SectionHeader title="Account links" />
          <div className="soft-card">
            {linksLoading && links.length === 0 ? (
              <div style={{ display: 'grid', placeItems: 'center', padding: 20 }}>
                <IonSpinner name="crescent" color="primary" />
              </div>
            ) : links.length === 0 ? (
              <p style={{ margin: 0, fontSize: 13, color: 'var(--aselco-ink-500)' }}>
                No account link requests yet.
              </p>
            ) : (
              <>
                {linksLoading && (
                  <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: 6 }}>
                    <IonSpinner name="crescent" color="primary" style={{ width: 18, height: 18 }} />
                  </div>
                )}
                <IonList className="soft-list" style={{ margin: 0, background: 'transparent' }}>
                  {links.map((link) => (
                    <IonItem key={link.id} lines="full" detail={false}>
                      <IonIcon slot="start" icon={linkOutline} color="primary" />
                      <IonLabel>
                        <h3>Account {link.account_number}</h3>
                        <p>{link.owner_name}</p>
                      </IonLabel>
                      <StatusPill status={formatStatusLabel(link.status)} />
                    </IonItem>
                  ))}
                </IonList>
              </>
            )}

            <IonButton
              expand="block"
              fill="outline"
              className="profile-link-another"
              disabled={!canAddAnotherLink}
              onClick={onLinkAnother}
            >
              <IonIcon slot="start" icon={addCircleOutline} />
              {canAddAnotherLink ? 'Link another account' : 'Account link limit reached (2/2)'}
            </IonButton>
          </div>

          <SectionHeader title="Contact details" />
          <div className="soft-card">
            <div className="kv">
              <span className="kv__k">Mobile</span>
              <span className="kv__v">{displayOrDash(displayMobile)}</span>
            </div>
            <div className="kv">
              <span className="kv__k">Email</span>
              <span className="kv__v">{displayOrDash(displayEmail)}</span>
            </div>
          </div>

          <SectionHeader title="Notifications" />
          <IonList className="soft-list">
            <IonItem>
              <IonLabel>
                <h3>Bill reminders</h3>
                <p>Due date and new statement alerts</p>
              </IonLabel>
              <IonToggle
                checked={billReminders}
                onIonChange={(e) => setBillReminders(e.detail.checked)}
              />
            </IonItem>
            <IonItem>
              <IonLabel>
                <h3>Outage alerts</h3>
                <p>Scheduled interruptions in your area</p>
              </IonLabel>
              <IonToggle
                checked={outageAlerts}
                onIonChange={(e) => setOutageAlerts(e.detail.checked)}
              />
            </IonItem>
          </IonList>

          <SectionHeader title="Settings" />
          <IonList className="soft-list">
            <IonItem button detail={false} onClick={() => toast('Password change is a UI demo.')}>
              <IonIcon slot="start" icon={lockClosedOutline} color="primary" />
              <IonLabel>Change password</IonLabel>
              <IonIcon slot="end" icon={chevronForwardOutline} color="medium" />
            </IonItem>
            <IonItem button detail={false} routerLink="/support">
              <IonIcon slot="start" icon={helpCircleOutline} color="primary" />
              <IonLabel>Help & support</IonLabel>
              <IonIcon slot="end" icon={chevronForwardOutline} color="medium" />
            </IonItem>
            <IonItem button detail={false} disabled={signingOut} onClick={onSignOut}>
              <IonIcon slot="start" icon={logOutOutline} color="danger" />
              <IonLabel color="danger">{signingOut ? 'Signing out…' : 'Sign out'}</IonLabel>
            </IonItem>
          </IonList>
        </div>
      </IonContent>
    </IonPage>
  );
};

export default Profile;
