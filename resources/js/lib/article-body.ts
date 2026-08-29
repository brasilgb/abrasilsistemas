/**
 * Blog post bodies written with the old plain-text editor used a hand-rolled
 * "markdown-lite" syntax (## / ### headings, "- " list items, ![]() images,
 * **bold** / *italic* inline). The admin form now edits bodies as real HTML via
 * a rich text editor, but existing posts still have the old syntax stored —
 * these helpers let both the admin form (to seed the editor) and the public
 * page (to render old posts) treat the two formats the same way.
 */

const escapeHtml = (text: string) =>
    text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

function inlineToHtml(text: string): string {
    return escapeHtml(text)
        .replace(/\[(.+?)]\((\S+?)\)/g, '<a href="$2">$1</a>')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/(?:^|\W)_(.+?)_(?=\W|$)/g, (match, inner) =>
            match.replace(`_${inner}_`, `<em>${inner}</em>`),
        )
        .replace(/\*(.+?)\*/g, '<em>$1</em>');
}

/** A body is treated as "legacy" plain text unless it already looks like HTML. */
export function isHtmlContent(content: string): boolean {
    return /<\/?(p|h2|h3|ul|ol|li|figure|img|strong|em|a|br|blockquote)[ >]/i.test(
        content,
    );
}

/** Convert an old markdown-lite body into the HTML the rich text editor expects. */
export function legacyBodyToHtml(content: string): string {
    const lines = content.replace(/\r\n/g, '\n').split('\n');
    const blocks: string[] = [];
    let paragraph: string[] = [];
    let list: string[] = [];

    const flushParagraph = () => {
        if (paragraph.length) {
            blocks.push(`<p>${paragraph.map(inlineToHtml).join(' ')}</p>`);
            paragraph = [];
        }
    };
    const flushList = () => {
        if (list.length) {
            blocks.push(
                `<ul>${list.map((item) => `<li>${inlineToHtml(item)}</li>`).join('')}</ul>`,
            );
            list = [];
        }
    };

    lines.forEach((rawLine) => {
        const line = rawLine.trim();
        const image = line.match(/^!\[(.*)]\((\S+)\)$/);

        if (!line) {
            flushParagraph();
            flushList();
        } else if (image) {
            flushParagraph();
            flushList();
            const alt = escapeHtml(image[1]);
            blocks.push(
                `<figure><img src="${escapeHtml(image[2])}" alt="${alt}">${alt ? `<figcaption>${alt}</figcaption>` : ''}</figure>`,
            );
        } else if (line.startsWith('### ')) {
            flushParagraph();
            flushList();
            blocks.push(`<h3>${inlineToHtml(line.slice(4))}</h3>`);
        } else if (line.startsWith('## ') || line.startsWith('# ')) {
            flushParagraph();
            flushList();
            blocks.push(
                `<h2>${inlineToHtml(line.replace(/^#{1,2} /, ''))}</h2>`,
            );
        } else if (line.startsWith('- ')) {
            flushParagraph();
            list.push(line.slice(2));
        } else {
            flushList();
            paragraph.push(line);
        }
    });
    flushParagraph();
    flushList();

    return blocks.join('');
}

/** Normalize any stored body (old plain text or new HTML) into HTML ready to render or edit. */
export function bodyToHtml(content: string): string {
    return isHtmlContent(content) ? content : legacyBodyToHtml(content);
}
