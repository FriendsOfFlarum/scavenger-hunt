import UserPage from 'flarum/forum/components/UserPage';
import type User from 'flarum/common/models/User';
import type Mithril from 'mithril';
import type Completion from '../../common/models/Completion';
export default class TrophiesPage extends UserPage {
    protected completions: Completion[] | null;
    oninit(vnode: Mithril.Vnode): void;
    show(user: User): void;
    content(): JSX.Element;
}
