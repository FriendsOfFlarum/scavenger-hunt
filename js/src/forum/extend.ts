import Extend from 'flarum/common/extenders';
import commonExtend from '../common/extend';

export default [
  ...commonExtend,
  new Extend.Routes()
    .add('fof-scavenger-hunt.dashboard', '/scavenger-hunt/dashboard', () => import('./dashboard/DashboardPage'))
    .add('fof-scavenger-hunt.dashboard.hunt', '/scavenger-hunt/dashboard/:id', () => import('./dashboard/HuntDashboardPage')),
] satisfies object[];
