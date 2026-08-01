import { describe, expect, it } from 'vitest';
// The sync client is a flat-package asset (served at /api/editor/sync.js);
// its pure helpers are tested here because js-helper hosts the repo's only
// vitest harness. Monorepo-relative import — never shipped with the package.
import { extractRevision } from '../../flat/src/Resources/editor/sync.js';

const file = (frontmatter, body) => `---\n${frontmatter}\n---\n\n${body}`;

describe('extractRevision', () => {
    it('reads the revision stamp and strips the read-only comment', () => {
        expect(extractRevision(file('title: T\nrevision: abc123 # read only', 'Body.'))).toBe('abc123');
    });

    it('ignores a revision line inside the body', () => {
        // A `revision:` in a code fence must not poison If-Match.
        const text = file('title: T', '```yaml\nrevision: fake999\n```');
        expect(extractRevision(text)).toBeNull();
    });

    it('only searches the front-matter block, not past its closing delimiter', () => {
        const text = file('title: T', 'revision: fake999\n\nreal content');
        expect(extractRevision(text)).toBeNull();
    });

    it('returns null without front matter', () => {
        expect(extractRevision('Just a body.')).toBeNull();
    });

    it('handles CRLF delimiters from Windows editors', () => {
        expect(extractRevision('---\r\nrevision: def456 # read only\r\n---\r\n\r\nBody.')).toBe('def456');
    });

    it('does not mistake a body rule for the closing delimiter', () => {
        const text = file('revision: abc123 # read only\ntitle: T', 'Intro.\n\n---\n\nBelow.');
        expect(extractRevision(text)).toBe('abc123');
    });
});
