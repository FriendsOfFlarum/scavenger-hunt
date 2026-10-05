import app from 'flarum/forum/app';
import Component, { type ComponentAttrs } from 'flarum/common/Component';
import InfoTile from 'flarum/common/components/InfoTile';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';
import type Hunt from '../../common/models/Hunt';
import type HuntStats from '../../common/models/HuntStats';

export interface HuntOverviewAttrs extends ComponentAttrs {
  hunt: Hunt;
}

function seconds(ms: number | null): string {
  return ms === null ? '—' : `${(ms / 1000).toFixed(1)} s`;
}

function rate(part: number, whole: number): string {
  return whole ? `${Math.round((part / whole) * 100)}%` : '—';
}

export default class HuntOverview extends Component<HuntOverviewAttrs> {
  protected stats: HuntStats | null = null;

  oninit(vnode: Mithril.Vnode<HuntOverviewAttrs, this>) {
    super.oninit(vnode);

    app.store.find<HuntStats>('scavenger-hunt-stats', this.attrs.hunt.id()!).then((stats) => {
      this.stats = stats;
      m.redraw();
    });
  }

  view() {
    const stats = this.stats;

    if (!stats) return <LoadingIndicator />;

    const t = (key: string) => app.translator.trans(`fof-scavenger-hunt.forum.dashboard.overview.${key}`);

    return (
      <div className="HuntOverview">
        <div className="HuntOverview-tiles">
          <InfoTile icon="fas fa-users">
            {stats.participants()} <span className="helpText">{t('participants')}</span>
          </InfoTile>
          <InfoTile icon="fas fa-puzzle-piece">
            {stats.finds()} <span className="helpText">{t('finds')}</span>
          </InfoTile>
          <InfoTile icon="fas fa-trophy">
            {stats.completions()} <span className="helpText">{t('completions')}</span>
          </InfoTile>
          <InfoTile icon="fas fa-eye">
            {rate(stats.finds(), stats.shown())} <span className="helpText">{t('find_rate')}</span>
          </InfoTile>
        </div>

        <h3>{t('parts_heading')}</h3>
        <table className="ScavengerDashboard-table">
          <thead>
            <tr>
              <th>{t('part')}</th>
              <th>{t('shown')}</th>
              <th>{t('found')}</th>
              <th>{t('find_rate')}</th>
              <th>{t('average_claim')}</th>
            </tr>
          </thead>
          <tbody>
            {stats.parts().map((part) => (
              <tr>
                <td>{part.name}</td>
                <td>{part.shown}</td>
                <td>{part.found}</td>
                <td>{rate(part.found, part.shown)}</td>
                <td>{seconds(part.averageClaimMs)}</td>
              </tr>
            ))}
          </tbody>
        </table>
        <p className="helpText">{t('shown_help')}</p>

        <h3>{t('completions_heading')}</h3>
        {stats.completionsPerDay().length ? (
          <table className="ScavengerDashboard-table">
            <tbody>
              {stats.completionsPerDay().map((day) => (
                <tr>
                  <td>{day.date}</td>
                  <td>{day.count}</td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <p className="helpText">{t('no_completions')}</p>
        )}
      </div>
    );
  }
}
