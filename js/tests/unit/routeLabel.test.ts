import routeLabel from '../../src/forum/utils/routeLabel';

describe('routeLabel', () => {
  it('turns FastRoute patterns into readable paths', () => {
    expect(routeLabel({ name: 'discussion', path: '/d/{id:\\d+(?:-[^/]*)?}[/{near:[^/]*}]' })).toBe('discussion — /d/:id');
    expect(routeLabel({ name: 'user', path: '/u/{username}[/{filter:[^/]*}]' })).toBe('user — /u/:username');
    expect(routeLabel({ name: 'index', path: '/all' })).toBe('index — /all');
  });
});
