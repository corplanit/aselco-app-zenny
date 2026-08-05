import { IonContent, IonList, IonPage, useIonRouter } from '@ionic/react';
import {
  alertCircleOutline,
  checkmarkCircleOutline,
  documentTextOutline,
} from 'ionicons/icons';
import AppHeader from '../components/AppHeader';
import BalanceCard from '../components/BalanceCard';
import ListRow from '../components/ListRow';
import QuickActions from '../components/QuickActions';
import SectionHeader from '../components/SectionHeader';
import {
  activity,
  billing,
  member,
  notifications,
  quickActions,
  tokenWallet,
} from '../data/mockData';

const activityIcons: Record<string, string> = {
  checkmarkCircle: checkmarkCircleOutline,
  documentText: documentTextOutline,
  alertCircle: alertCircleOutline,
};

const Home: React.FC = () => {
  const router = useIonRouter();
  const unread = notifications.filter((n) => n.unread).length;

  return (
    <IonPage>
      <AppHeader
        showLogo
        assistant
        onAssistantClick={() => router.push('/assistant')}
        bell
        unreadCount={unread}
        onBellClick={() => router.push('/notifications')}
      />
      <IonContent>
        <div className="page-pad">
          <p style={{ margin: '0 0 12px', color: 'var(--aselco-ink-500)', fontSize: 14 }}>
            Good day, <strong style={{ color: 'var(--aselco-ink-900)' }}>{member.name}</strong>
          </p>

          <BalanceCard wallet={tokenWallet} billing={billing} />

          <SectionHeader title="Quick Actions" />
          <QuickActions actions={quickActions} />

          <SectionHeader title="Recent Activity" linkLabel="View All" linkHref="/tabs/ledger" />
          <IonList className="soft-list">
            {activity.map((a) => (
              <ListRow
                key={a.id}
                icon={activityIcons[a.icon]}
                title={a.title}
                meta={a.date}
                value={a.value}
                tone={a.tone}
                href={a.href}
              />
            ))}
          </IonList>
        </div>
      </IonContent>
    </IonPage>
  );
};

export default Home;
