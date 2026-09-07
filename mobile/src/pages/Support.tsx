import {
  IonAccordion,
  IonAccordionGroup,
  IonButton,
  IonContent,
  IonIcon,
  IonItem,
  IonLabel,
  IonList,
  IonPage,
  IonSearchbar,
  useIonRouter,
} from '@ionic/react';
import {
  callOutline,
  chatbubbleEllipsesOutline,
  chevronForwardOutline,
  headsetOutline,
  mailOutline,
  navigateOutline,
  phonePortraitOutline,
} from 'ionicons/icons';
import { useMemo, useState } from 'react';
import AppHeader from '../components/AppHeader';
import { support } from '../data/mockData';
import './Support.css';

const Support: React.FC = () => {
  const router = useIonRouter();
  const [query, setQuery] = useState('');

  const faqs = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return support.faqs;
    return support.faqs.filter(
      (f) => f.q.toLowerCase().includes(q) || f.a.toLowerCase().includes(q),
    );
  }, [query]);

  return (
    <IonPage>
      <AppHeader title="Support" icon={headsetOutline} backHref="/tabs/home" />
      <IonContent>
        <div className="support-page">
          <button
            type="button"
            className="support-live-cta"
            onClick={() => router.push('/support/chat', 'forward')}
          >
            <span className="support-live-cta__icon" aria-hidden="true">
              <IonIcon icon={chatbubbleEllipsesOutline} />
            </span>
            <span className="support-live-cta__copy">
              <strong>Live chat</strong>
              <span>Message ASELCO support now</span>
            </span>
            <IonIcon icon={chevronForwardOutline} />
          </button>

          <h2 className="support-section-title">Contact</h2>
          <IonList className="support-list" lines="full">
            <IonItem href={`tel:${support.hotline.replace(/[^\d+]/g, '')}`} detail={false}>
              <IonIcon slot="start" icon={callOutline} color="primary" />
              <IonLabel>
                <h3>Hotline</h3>
                <p>{support.hotline}</p>
              </IonLabel>
            </IonItem>
            <IonItem href={`sms:${support.mobile.replace(/\s/g, '')}`} detail={false}>
              <IonIcon slot="start" icon={phonePortraitOutline} color="primary" />
              <IonLabel>
                <h3>Mobile / SMS</h3>
                <p>{support.mobile}</p>
              </IonLabel>
            </IonItem>
            <IonItem href={`mailto:${support.email}`} detail={false}>
              <IonIcon slot="start" icon={mailOutline} color="primary" />
              <IonLabel>
                <h3>Email</h3>
                <p>{support.email}</p>
              </IonLabel>
            </IonItem>
            <IonItem lines="none" detail={false}>
              <IonIcon slot="start" icon={navigateOutline} color="primary" />
              <IonLabel>
                <h3>{support.office}</h3>
                <p>{support.hours}</p>
              </IonLabel>
            </IonItem>
          </IonList>

          <h2 className="support-section-title">FAQ</h2>
          <IonSearchbar
            className="support-search"
            value={query}
            placeholder="Search FAQ"
            debounce={200}
            onIonInput={(e) => setQuery(e.detail.value ?? '')}
          />
          <IonAccordionGroup className="support-faq">
            {faqs.map((f) => (
              <IonAccordion key={f.q} value={f.q}>
                <IonItem slot="header" lines="full">
                  <IonLabel>{f.q}</IonLabel>
                </IonItem>
                <div className="support-faq__answer" slot="content">
                  {f.a}
                </div>
              </IonAccordion>
            ))}
          </IonAccordionGroup>

          <div className="support-actions">
            <IonButton expand="block" size="small" fill="outline" onClick={() => router.push('/assistant')}>
              Ask AI Assistant
            </IonButton>
            <IonButton expand="block" size="small" fill="clear" onClick={() => router.push('/complaints')}>
              File a complaint
            </IonButton>
          </div>
        </div>
      </IonContent>
    </IonPage>
  );
};

export default Support;
