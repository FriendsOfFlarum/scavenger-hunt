import Model from 'flarum/common/Model';
import type { PartLayer } from './Part';
export interface HuntTemplatePart {
    key: string;
    name: string;
    reference: string;
    imageUrl: string | null;
    layer: PartLayer;
}
export default class HuntTemplate extends Model {
    title(): string;
    occasion(): string;
    imageUrl(): string | null;
    imageReference(): string | null;
    completedImageReference(): string | null;
    parts(): HuntTemplatePart[];
}
