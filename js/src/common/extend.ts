import Extend from 'flarum/common/extenders';
import Asset from './models/Asset';
import Completion from './models/Completion';
import Encounter from './models/Encounter';
import Find from './models/Find';
import HidingSpotDefinition from './models/HidingSpotDefinition';
import Hunt from './models/Hunt';
import HuntStats from './models/HuntStats';
import Participant from './models/Participant';
import HuntTemplate from './models/HuntTemplate';
import Location from './models/Location';
import Part from './models/Part';

export default [
  new Extend.Store()
    .add('scavenger-hunts', Hunt)
    .add('scavenger-hunt-parts', Part)
    .add('scavenger-hunt-locations', Location)
    .add('scavenger-hunt-encounters', Encounter)
    .add('scavenger-hunt-finds', Find)
    .add('scavenger-hunt-templates', HuntTemplate)
    .add('scavenger-hunt-spots', HidingSpotDefinition)
    .add('scavenger-hunt-completions', Completion)
    .add('scavenger-hunt-stats', HuntStats)
    .add('scavenger-hunt-participants', Participant)
    .add('scavenger-hunt-assets', Asset),
] satisfies object[];
