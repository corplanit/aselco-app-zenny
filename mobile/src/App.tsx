import { IonApp, IonRouterOutlet, setupIonicReact } from '@ionic/react';
import { IonReactRouter } from '@ionic/react-router';
import { Redirect, Route } from 'react-router-dom';
import Assistant from './pages/Assistant';
import Complaints from './pages/Complaints';
import MainTabs from './pages/MainTabs';
import Notifications from './pages/Notifications';
import Support from './pages/Support';

/* Core CSS required for Ionic components to work properly */
import '@ionic/react/css/core.css';

/* Basic CSS for apps built with Ionic */
import '@ionic/react/css/normalize.css';
import '@ionic/react/css/structure.css';
import '@ionic/react/css/typography.css';

/* Optional CSS utils */
import '@ionic/react/css/padding.css';
import '@ionic/react/css/float-elements.css';
import '@ionic/react/css/text-alignment.css';
import '@ionic/react/css/text-transformation.css';
import '@ionic/react/css/flex-utils.css';
import '@ionic/react/css/display.css';

/* Keep light theme for this UI lesson */
/* import '@ionic/react/css/palettes/dark.system.css'; */

import './theme/variables.css';
import './theme/global.css';

setupIonicReact();

/**
 * App shell:
 * - /tabs/*  → MainTabs (bottom tab bar visible)
 * - /notifications, /assistant, /complaints, /support → stack pages (no tab bar)
 */
const App: React.FC = () => (
  <IonApp>
    <IonReactRouter>
      <IonRouterOutlet>
        <Route path="/tabs" component={MainTabs} />
        <Route exact path="/notifications" component={Notifications} />
        <Route exact path="/assistant" component={Assistant} />
        <Route exact path="/complaints" component={Complaints} />
        <Route exact path="/support" component={Support} />
        <Route exact path="/">
          <Redirect to="/tabs/home" />
        </Route>
      </IonRouterOutlet>
    </IonReactRouter>
  </IonApp>
);

export default App;
