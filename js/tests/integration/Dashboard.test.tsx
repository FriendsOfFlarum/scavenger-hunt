import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import app from 'flarum/forum/app';
import mq from 'mithril-query';
import { jest } from '@jest/globals';
import forumExtend from '../../src/forum/extend';
import DashboardPage from '../../src/forum/dashboard/DashboardPage';
import HuntDashboardPage from '../../src/forum/dashboard/HuntDashboardPage';
import type Hunt from '../../src/common/models/Hunt';

beforeAll(() => {
  bootstrapForum();
  app.bootExtensions({ 'fof-scavenger-hunt': { extend: forumExtend } });
  app.boot();
});

afterEach(() => {
  jest.restoreAllMocks();
  app.forum.pushAttributes({ scavengerHuntCanManage: false });
});

type Tree = ReturnType<typeof mq>;

// Core moves `.App-titleControl` into the phone header, so the nav collapses into a dropdown there.
function navLabel(page: Tree): string {
  return page.first('.ScavengerDashboard-nav .App-titleControl .Dropdown-toggle .Button-labelText').textContent ?? '';
}

describe('DashboardPage', () => {
  it('puts the nav in a title control dropdown and the new Hunt button in the primary control', () => {
    const page = mq({ view: () => <DashboardPage routeName="fof-scavenger-hunt.dashboard" /> });

    app.forum.pushAttributes({ scavengerHuntCanManage: true });
    page.redraw();

    expect(page).toHaveElement('.ScavengerDashboard-nav .App-primaryControl .ScavengerDashboard-newHunt');
    expect(page).toHaveElement('.ScavengerDashboard-nav .App-titleControl.Dropdown--select');
    expect(navLabel(page)).toContain('fof-scavenger-hunt.forum.dashboard.all_hunts');
  });

  it('draws the Hunts table in a horizontally scrollable box', async () => {
    app.forum.pushAttributes({ scavengerHuntCanManage: true });
    const hunt = app.store.pushPayload<Hunt>({
      data: { type: 'scavenger-hunts', id: '1', attributes: { title: 'Snowman', partCount: 3, participantCount: 2, finisherCount: 1 } },
    });
    jest.spyOn(app.store, 'find').mockResolvedValue([hunt] as never);

    const page = mq({ view: () => <DashboardPage routeName="fof-scavenger-hunt.dashboard" /> });
    await Promise.resolve();
    page.redraw();

    expect(page).toHaveElement('.ScavengerDashboard-tableScroll > table.ScavengerDashboard-table');
  });
});

describe('HuntDashboardPage', () => {
  it('collapses the tabs into a dropdown labelled with the active tab', () => {
    const replaceState = jest.spyOn(window.history, 'replaceState').mockImplementation(() => {});
    const page = mq({ view: () => <HuntDashboardPage routeName="fof-scavenger-hunt.dashboard.hunt" /> });

    expect(page).toHaveElement('.ScavengerDashboard-nav .App-titleControl.Dropdown--select');
    expect(navLabel(page)).toContain('fof-scavenger-hunt.forum.dashboard.tabs.overview');
    expect(page).toHaveElement('.ScavengerDashboard-nav .Dropdown-menu .item-overview.active');

    page.click('.ScavengerDashboard-nav .Dropdown-menu .item-participants button');

    expect(navLabel(page)).toContain('fof-scavenger-hunt.forum.dashboard.tabs.participants');
    expect(page).toHaveElement('.ScavengerDashboard-nav .Dropdown-menu .item-participants.active');
    expect(page).not.toHaveElement('.ScavengerDashboard-nav .Dropdown-menu .item-overview.active');
    expect(replaceState).toHaveBeenCalled();
  });
});
