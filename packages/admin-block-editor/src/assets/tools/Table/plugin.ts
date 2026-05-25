import Table from './table';
import * as $ from './utils/dom';
import { MarkdownUtils } from '../utils/MarkdownUtils';
import he from 'he';
import { BlockToolData, API } from '@editorjs/editorjs';
import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data';

import { IconTable, IconTableWithHeadings, IconTableWithoutHeadings, IconStretch, IconCollapse, IconStar } from '@codexteam/icons';

/** Block data persisted by EditorJS and round-tripped through Markdown. */
export interface TableData extends BlockToolData {
  content?: string[][];
  withHeadings?: boolean;
  stickyHeadings?: boolean;
  columnAlignments?: string[];
}

/** GFM delimiter-cell markers for each column alignment. */
const ALIGNMENT_SEPARATORS: Record<string, string> = {
  '': '---',
  left: ':---',
  center: ':--:',
  right: '---:',
};
/**
 * @typedef {object} Tune - setting for the table
 * @property {string} name - tune name
 * @property {HTMLElement} icon - icon for the tune
 * @property {boolean} isActive - default state of the tune
 * @property {void} setTune - set tune state to the table data
 */
/**
 * @typedef {object} TableConfig - object with the data transferred to form a table
 * @property {boolean} withHeading - setting to use cells of the first row as headings
 * @property {string[][]} content - two-dimensional array which contains table content
 */
/**
 * @typedef {object} TableConstructor
 * @property {TableConfig} data — previously saved data
 * @property {TableConfig} config - user config for Tool
 * @property {object} api - Editor.js API
 * @property {boolean} readOnly - read-only mode flag
 */
/**
 * @typedef {import('@editorjs/editorjs').PasteEvent} PasteEvent
 */
interface TableConfig {
  withHeadings?: boolean;
  stretched?: boolean;
  content?: string[][];
  rows?: number;
  cols?: number;
  maxcols?: number;
  maxrows?: number;
  [key: string]: any;
}

interface TableConstructor {
  data: TableConfig;
  config: TableConfig;
  api: any;
  readOnly: boolean;
  block: any;
}

type PasteEvent = any;


/**
 * Table block for Editor.js
 */
export default class TableBlock {
  api: any;
  readOnly: boolean;
  config: TableConfig;
  data: TableConfig;
  table: Table | null;
  block: any;
  container!: HTMLElement;

  /** Class that makes the heading row sticky, in the editor and on the front (via {.table-sticky-header}) */
  static readonly STICKY_CLASS = 'table-sticky-header';

  /**
   * Notify core that read-only mode is supported
   *
   * @returns {boolean}
   */
  static get isReadOnlySupported(): boolean {
    return true;
  }

  /**
   * Allow to press Enter inside the CodeTool textarea
   *
   * @returns {boolean}
   * @public
   */
  static get enableLineBreaks(): boolean {
    return true;
  }

  /**
   * Do not sanitize <br> and basic inline tags while inline toolbar enabled (upstream #144)
   *
   * @returns {object}
   * @public
   */
  static get sanitize(): Record<string, boolean> {
    return {
      br: true,
      u: true,
      b: true,
      i: true,
      del: true,
      p: true,
      a: true
    };
  }

  /**
   * Render plugin`s main Element and fill it with saved data
   *
   * @param {TableConstructor} init
   */
  constructor({data, config, api, readOnly, block}: TableConstructor) {
    this.api = api;
    this.readOnly = readOnly;
    this.config = config;
    this.data = {
      withHeadings: this.getConfig('withHeadings', false, data),
      stickyHeadings: this.getConfig('stickyHeadings', false, data),
      stretched: this.getConfig('stretched', false, data),
      content: data && data.content ? data.content : [],
      columnAlignments: data && data.columnAlignments ? data.columnAlignments : []
    };
    this.table = null;
    this.block = block;
  }

  /**
   * Get Tool toolbox settings
   * icon - Tool icon's SVG
   * title - title to show in toolbox
   *
   * @returns {{icon: string, title: string}}
   */
  static get toolbox(): { icon: string; title: string } {
    return {
      icon: IconTable,
      title: 'Table'
    };
  }

  /**
   * Return Tool's view
   *
   * @returns {HTMLDivElement}
   */
  render(): HTMLElement {
    /** creating table */
    this.table = new Table(this.readOnly, this.api, this.data, this.config);

    /** creating container around table */
    this.container = $.make('div', this.api.styles.block);
    this.container.appendChild(this.table.getWrapper());

    this.table.setHeadingsSetting(this.data.withHeadings);
    this.container.classList.toggle(TableBlock.STICKY_CLASS, !!this.data.stickyHeadings);

    return this.container;
  }

  /**
   * Returns plugin settings
   *
   * @returns {Array}
   */
  renderSettings(): any[] {
    const settings: any[] = [
      {
        label: this.api.i18n.t('With headings'),
        icon: IconTableWithHeadings,
        isActive: this.data.withHeadings,
        closeOnActivate: true,
        toggle: true,
        onActivate: () => {
          this.data.withHeadings = true;
          this.table!.setHeadingsSetting(this.data.withHeadings);
        }
      }, {
        label: this.api.i18n.t('Without headings'),
        icon: IconTableWithoutHeadings,
        isActive: !this.data.withHeadings,
        closeOnActivate: true,
        toggle: true,
        onActivate: () => {
          this.data.withHeadings = false;
          this.table!.setHeadingsSetting(this.data.withHeadings);
          // Sticky only applies to a heading row; drop it when headings are removed.
          this.data.stickyHeadings = false;
          this.container.classList.remove(TableBlock.STICKY_CLASS);
        }
      }
    ];

    // Sticky heading only makes sense with a heading row, so offer it only then.
    if (this.data.withHeadings) {
      settings.push({
        label: this.api.i18n.t('Sticky heading'),
        icon: IconStar,
        isActive: this.data.stickyHeadings,
        closeOnActivate: true,
        toggle: true,
        onActivate: () => {
          this.data.stickyHeadings = !this.data.stickyHeadings;
          this.container.classList.toggle(TableBlock.STICKY_CLASS, !!this.data.stickyHeadings);
        }
      });
    }

    settings.push({
      label: this.data.stretched ? this.api.i18n.t('Collapse') : this.api.i18n.t('Stretch'),
      icon: this.data.stretched ? IconCollapse : IconStretch,
      closeOnActivate: true,
      toggle: true,
      onActivate: () => {
        this.data.stretched = !this.data.stretched;
        this.block.stretched = this.data.stretched;
      }
    });

    return settings;
  }
  /**
   * Extract table data from the view
   *
   * @returns {TableData} - saved data
   */
  save(): TableConfig {
    const tableContent = this.table!.getData();

    const result = {
      withHeadings: this.data.withHeadings,
      stickyHeadings: this.data.stickyHeadings,
      stretched: this.data.stretched,
      content: tableContent,
      columnAlignments: this.table!.getColumnAlignments()
    };

    return result;
  }

  /**
   * Plugin destroyer
   *
   * @returns {void}
   */
  destroy(): void {
    this.table!.destroy();
  }

  /**
   * A helper to get config value.
   *
   * @param {string} configName - the key to get from the config.
   * @param {any} defaultValue - default value if config doesn't have passed key
   * @param {object} savedData - previously saved data. If passed, the key will be got from there, otherwise from the config
   * @returns {any} - config value.
   */
  getConfig(configName: string, defaultValue: any = undefined, savedData: any = undefined): any {
    const data = this.data || savedData;

    // Respect explicitly-saved values (incl. `false`); fall back to config (incl. `false`),
    // then to defaultValue. Fixes withHeadings not being honoured (upstream #126/#134).
    if (data && configName in data) {
      return data[configName];
    }

    return this.config && configName in this.config ? this.config[configName] : defaultValue;
  }

  /**
   * Table onPaste configuration
   *
   * @public
   */
  static get pasteConfig(): { tags: string[] } {
    return { tags: ['TABLE', 'TR', 'TH', 'TD'] };
  }

  /**
   * On paste callback that is fired from Editor
   *
   * @param {PasteEvent} event - event with pasted data
   */
  onPaste(event: PasteEvent): void {
    const table = event.detail.data;

    /** Check if the first row is a header */
    const firstRowHeading = table.querySelector(':scope > thead, tr:first-of-type th');

    /** Get all rows from the table */
    const rows = Array.from(table.querySelectorAll('tr')) as HTMLTableRowElement[];

    /** Generate a content matrix */
    const content = rows.map((row) => {
      /** Get cells from row */
      const cells = Array.from(row.querySelectorAll('th, td')) as HTMLTableCellElement[];

      /** Return cells content, expanding horizontal merges (adapted from upstream #80) */
      const rowData: string[] = [];
      cells.forEach((cell) => {
        rowData.push(cell.innerHTML);

        /**
         * Map a pasted colspan onto Pushword's `->` markers so merged cells survive
         * the paste and round-trip through markdown. Rowspan is not handled: the
         * markdown renderer has no rowspan concept.
         */
        const colspan = parseInt(cell.getAttribute('colspan') || '1', 10);
        for (let i = 1; i < colspan; i++) {
          rowData.push('->');
        }
      });

      return rowData;
    });

    /**
     * Normalize ragged rows (e.g. partial table selections from Word/HTML) to a
     * rectangular matrix so the editor renders every column.
     */
    const maxCols = content.reduce((max, row) => Math.max(max, row.length), 0);
    content.forEach((row) => {
      while (row.length < maxCols) {
        row.push('');
      }
    });

    /** Update Tool's data */
    this.data = {
      withHeadings: firstRowHeading !== null,
      content
    };

    /** Update table block */
    if (this.table!.wrapper) {
      this.table!.wrapper.replaceWith(this.render());
    }
  }

  /**
   * Export block data to Markdown.
   *
   * @param {TableData} data - block data
   * @param {BlockTuneData} tunes - block tunes
   * @returns {Promise<string>} Markdown representation
   */
  static async exportToMarkdown(data: TableData, tunes?: BlockTuneData): Promise<string> {
    if (!data || !data.content) {
      return '';
    }

    const rows = data.content;
    if (rows.length === 0) {
      return '';
    }

    let markdown = '';
    const withHeadings = data.withHeadings ?? false;
    const alignments = data.columnAlignments ?? [];

    rows.forEach((row, rowIndex) => {
      // Decode HTML entities: contenteditable serializes `->` as `-&gt;`, which
      // the colspan processor would miss until CommonMark decodes it, but clean
      // source is preferable.
      const cells = row.map((cell) => he.decode(cell));
      markdown += '| ' + cells.join(' | ') + ' |\n';

      // The GFM delimiter row carries per-column alignment (`:---`, `:--:`, `---:`).
      if (withHeadings && rowIndex === 0) {
        const separators = cells.map((_, i) => ALIGNMENT_SEPARATORS[alignments[i]] ?? '---');
        markdown += '| ' + separators.join(' | ') + ' |\n';
      }
    });

    const formattedMarkdown = await MarkdownUtils.formatMarkdownWithPrettier(markdown);

    let out = MarkdownUtils.addAttributes(formattedMarkdown, tunes);

    // Sticky heading round-trips as the `{.table-sticky-header}` block attribute,
    // injected independently of the (multi-class-fragile) class tune machinery.
    if (data.stickyHeadings && !out.includes(TableBlock.STICKY_CLASS)) {
      out = out.startsWith('{')
        ? out.replace('}', ` .${TableBlock.STICKY_CLASS}}`)
        : `{.${TableBlock.STICKY_CLASS}}\n${out}`;
    }

    return out;
  }

  /**
   * Build a table block from its Markdown representation.
   *
   * @param {API} editor - Editor.js API
   * @param {string} markdown - Markdown table (optionally prefixed with a block-attribute line)
   * @returns {any} the inserted block
   */
  static importFromMarkdown(editor: API, markdown: string): any {
    const lines = markdown.split('\n');
    let i = 0;
    let tunes: BlockTuneData = {};
    const content: string[][] = [];
    let withHeadings = false;
    let stickyHeadings = false;
    let columnAlignments: string[] = [];

    while (i < lines.length) {
      if (!lines[i]) {
        break;
      }

      const line = lines[i]!;

      if (i === 0 && MarkdownUtils.startWithAttribute(line)) {
        tunes = MarkdownUtils.parseAttributes(line);
        // Sticky heading is owned by the table tool, not the class tune: lift it
        // out of the parsed class so it isn't emitted twice on the next export.
        if (typeof tunes.class === 'string' && tunes.class.includes(TableBlock.STICKY_CLASS)) {
          stickyHeadings = true;
          tunes.class = tunes.class
            .split(/\s+/)
            .filter((c) => c.replace(/^\./, '') !== TableBlock.STICKY_CLASS)
            .join(' ');
          if (tunes.class === '') {
            delete tunes.class;
          }
        }
        i++;
        continue;
      }

      if (line.includes('|')) {
        const cells = line
          .split('|')
          .map((cell) => cell.trim())
          .filter((cell) => cell !== '');
        content.push(cells);

        if (i + 1 < lines.length && lines[i + 1]?.trim().match(/^\|[\|\s\-:]+\|$/)) {
          withHeadings = true;
          // Read GFM alignment markers out of the delimiter row.
          columnAlignments = lines[i + 1]!
            .split('|')
            .map((cell) => cell.trim())
            .filter((cell) => cell !== '')
            .map((cell) => {
              const left = cell.startsWith(':');
              const right = cell.endsWith(':');
              if (left && right) return 'center';
              if (right) return 'right';
              if (left) return 'left';
              return '';
            });
          i++;
        }
      } else {
        break;
      }
      i++;
    }

    const block = editor.blocks.insert('table');
    editor.blocks.update(
      block.id,
      { content, withHeadings, stickyHeadings, columnAlignments },
      tunes,
    );

    return block;
  }

  /**
   * Detect a Markdown fragment produced by this tool.
   *
   * @param {string} markdown - candidate Markdown
   * @returns {boolean}
   */
  static isItMarkdownExported(markdown: string): boolean {
    return markdown.startsWith('|');
  }
}
