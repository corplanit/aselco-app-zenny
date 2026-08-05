import {
  IonButton,
  IonCard,
  IonCardContent,
  IonCardHeader,
  IonCardSubtitle,
  IonCardTitle,
  IonContent,
  IonPage,
} from '@ionic/react';
import { notificationsOutline } from 'ionicons/icons';
import { useState } from 'react';
import AppHeader from '../components/AppHeader';
import EmptyState from '../components/EmptyState';
import { notifications as seed } from '../data/mockData';

const Notifications: React.FC = () => {
  const [items, setItems] = useState(seed);
  const unread = items.filter((n) => n.unread).length;

  const markAllRead = () => {
    setItems((prev) => prev.map((n) => ({ ...n, unread: false })));
  };

  const markRead = (id: string) => {
    setItems((prev) => prev.map((n) => (n.id === id ? { ...n, unread: false } : n)));
  };

  return (
    <IonPage>
      <AppHeader title="Notifications" icon={notificationsOutline} backHref="/tabs/home" />
      <IonContent>
        <div className="page-pad">
          <div
            style={{
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
              marginBottom: 12,
            }}
          >
            <p style={{ margin: 0, color: 'var(--aselco-ink-500)', fontSize: 14 }}>
              {unread} unread
            </p>
            <IonButton size="small" fill="clear" disabled={unread === 0} onClick={markAllRead}>
              Mark all read
            </IonButton>
          </div>

          {items.length === 0 ? (
            <EmptyState title="No notifications" />
          ) : (
            items.map((n) => (
              <IonCard
                key={n.id}
                button
                className={n.unread ? 'notif-unread' : undefined}
                onClick={() => markRead(n.id)}
              >
                <IonCardHeader>
                  <IonCardSubtitle>{n.date}</IonCardSubtitle>
                  <IonCardTitle style={{ fontSize: 16 }}>{n.title}</IonCardTitle>
                </IonCardHeader>
                <IonCardContent>{n.body}</IonCardContent>
              </IonCard>
            ))
          )}
        </div>
      </IonContent>
    </IonPage>
  );
};

export default Notifications;
