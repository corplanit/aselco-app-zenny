import {
  IonContent,
  IonIcon,
  IonItem,
  IonLabel,
  IonList,
  IonPage,
  IonToggle,
  useIonToast,
} from '@ionic/react';
import {
  chevronForwardOutline,
  helpCircleOutline,
  lockClosedOutline,
  logOutOutline,
  personOutline,
} from 'ionicons/icons';
import { useState } from 'react';
import AppHeader from '../components/AppHeader';
import SectionHeader from '../components/SectionHeader';
import { member } from '../data/mockData';

const Profile: React.FC = () => {
  const [billReminders, setBillReminders] = useState(true);
  const [outageAlerts, setOutageAlerts] = useState(true);
  const [present] = useIonToast();

  const toast = (msg: string) =>
    present({ message: msg, duration: 1800, color: 'primary' });

  return (
    <IonPage>
      <AppHeader title="Profile" icon={personOutline} />
      <IonContent>
        <div className="page-pad">
          <div style={{ display: 'flex', alignItems: 'center', gap: 14, marginBottom: 8 }}>
            <div className="avatar-circle">{member.initials}</div>
            <div>
              <h2 className="font-display" style={{ margin: 0, fontSize: 20 }}>{member.name}</h2>
              <p style={{ margin: '4px 0 0', color: 'var(--aselco-ink-500)', fontSize: 13 }}>
                Account {member.accountNo}
              </p>
            </div>
          </div>

          <SectionHeader title="Account details" />
          <div className="soft-card">
            <div className="kv">
              <span className="kv__k">Meter no.</span>
              <span className="kv__v">{member.meterNo}</span>
            </div>
            <div className="kv">
              <span className="kv__k">Rate class</span>
              <span className="kv__v">{member.rateClass}</span>
            </div>
            <div className="kv">
              <span className="kv__k">Member since</span>
              <span className="kv__v">{member.memberSince}</span>
            </div>
            <div className="kv">
              <span className="kv__k">Mobile</span>
              <span className="kv__v">{member.mobile}</span>
            </div>
            <div className="kv">
              <span className="kv__k">Email</span>
              <span className="kv__v">{member.email}</span>
            </div>
            <div className="kv">
              <span className="kv__k">Service address</span>
              <span className="kv__v">{member.address}</span>
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
            <IonItem button detail={false} onClick={() => toast('Signed out (UI demo only).')}>
              <IonIcon slot="start" icon={logOutOutline} color="danger" />
              <IonLabel color="danger">Sign out</IonLabel>
            </IonItem>
          </IonList>
        </div>
      </IonContent>
    </IonPage>
  );
};

export default Profile;
