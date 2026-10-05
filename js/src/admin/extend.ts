import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';

export default [
  new Extend.Admin()
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
    ),
] satisfies object[];
