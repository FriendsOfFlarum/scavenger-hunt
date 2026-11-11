import Model from 'flarum/common/Model';
export default class Asset extends Model {
    name(): string;
    url(): string;
    mimeType(): string;
    width(): number | null;
    height(): number | null;
    size(): number;
    createdAt(): Date;
    isInUse(): boolean;
}
