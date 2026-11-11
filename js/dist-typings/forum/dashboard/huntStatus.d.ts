import type Hunt from '../../common/models/Hunt';
export type HuntStatus = 'disabled' | 'scheduled' | 'live' | 'ended';
export default function huntStatus(hunt: Hunt, now?: Date): HuntStatus;
