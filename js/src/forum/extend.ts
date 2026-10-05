import Extend from 'flarum/common/extenders';
import commonExtend from '../common/extend';
import HuntCompletedNotification from './components/HuntCompletedNotification';

export default [
  ...commonExtend,
  new Extend.Routes()
    .add('fof-scavenger-hunt.dashboard', '/scavenger-hunt/dashboard', () => import('./dashboard/DashboardPage'))
    .add('fof-scavenger-hunt.dashboard.hunt', '/scavenger-hunt/dashboard/:id', () => import('./dashboard/HuntDashboardPage'))
    .add('fof-scavenger-hunt.hunt', '/hunts/:slug', () => import('./components/HuntPage'))
    .add('fof-scavenger-hunt.user.trophies', '/u/:username/trophies', () => import('./components/TrophiesPage')),
  new Extend.Notification().add('scavengerHuntCompleted', HuntCompletedNotification),
] satisfies object[];
