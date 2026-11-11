import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';
import Icon from 'flarum/common/components/Icon';
import Asset from '../common/models/Asset';

export default [
  // Only the Asset library needs a model here; the rest stay out of the admin bundle.
  new Extend.Store().add('scavenger-hunt-assets', Asset),

  new Extend.Admin()
    .customSetting(
      () =>
        m('.Form-group.ScavengerHuntAdmin-dashboardLink', [
          m('a.Button', { href: app.forum.attribute<string>('baseUrl') + '/scavenger-hunt/dashboard' }, [
            m(Icon, { name: 'fas fa-binoculars', className: 'Button-icon' }),
            m('span.Button-label', app.translator.trans('fof-scavenger-hunt.admin.dashboard_link')),
          ]),
          m('.helpText', app.translator.trans('fof-scavenger-hunt.admin.dashboard_link_help')),
        ]),
      100
    )
    .setting(() => ({
      setting: 'fof-scavenger-hunt.showHeaderIndicator',
      type: 'boolean',
      label: app.translator.trans('fof-scavenger-hunt.admin.settings.show_header_indicator'),
      help: app.translator.trans('fof-scavenger-hunt.admin.settings.show_header_indicator_help'),
    }))
    .setting(() => ({
      setting: 'fof-scavenger-hunt.managersCanPlay',
      type: 'boolean',
      label: app.translator.trans('fof-scavenger-hunt.admin.settings.managers_can_play'),
      help: app.translator.trans('fof-scavenger-hunt.admin.settings.managers_can_play_help'),
    }))
    .setting(() => ({
      setting: 'fof-scavenger-hunt.maxPartsPerPage',
      type: 'number',
      min: 1,
      label: app.translator.trans('fof-scavenger-hunt.admin.settings.max_parts_per_page'),
      help: app.translator.trans('fof-scavenger-hunt.admin.settings.max_parts_per_page_help'),
    }))
    .setting(() => ({
      setting: 'fof-scavenger-hunt.tokenLifetime',
      type: 'number',
      min: 1,
      label: app.translator.trans('fof-scavenger-hunt.admin.settings.token_lifetime'),
      help: app.translator.trans('fof-scavenger-hunt.admin.settings.token_lifetime_help'),
    }))
    .setting(() => ({
      setting: 'fof-scavenger-hunt.lookupRateLimit',
      type: 'number',
      min: 1,
      label: app.translator.trans('fof-scavenger-hunt.admin.settings.lookup_rate_limit'),
      help: app.translator.trans('fof-scavenger-hunt.admin.settings.lookup_rate_limit_help'),
    }))
    .setting(() => ({
      setting: 'fof-scavenger-hunt.dailyLookupLimit',
      type: 'number',
      min: 1,
      label: app.translator.trans('fof-scavenger-hunt.admin.settings.daily_lookup_limit'),
      help: app.translator.trans('fof-scavenger-hunt.admin.settings.daily_lookup_limit_help'),
    }))
    .permission(
      () => ({
        icon: 'fas fa-binoculars',
        label: app.translator.trans('fof-scavenger-hunt.admin.permissions.play'),
        permission: 'fof-scavenger-hunt.play',
      }),
      'reply',
      50
    )
    .permission(
      () => ({
        icon: 'fas fa-map-marked-alt',
        label: app.translator.trans('fof-scavenger-hunt.admin.permissions.manage'),
        permission: 'fof-scavenger-hunt.manage',
      }),
      'moderate',
      50
    )
    .permission(
      () => ({
        icon: 'fas fa-upload',
        label: app.translator.trans('fof-scavenger-hunt.admin.permissions.upload_images'),
        permission: 'fof-scavenger-hunt.uploadImages',
      }),
      'moderate',
      49
    ),
] satisfies object[];
