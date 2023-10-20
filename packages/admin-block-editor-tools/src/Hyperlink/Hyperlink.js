import css2 from './Hyperlink.css';
import make from './../Abstract/make.js';
import SelectionUtils from 'editorjs-hyperlink/src/SelectionUtils';
import { IconLink, IconUnlink } from '@codexteam/icons';
// todo get selection utils https://github.com/codex-team/editor.js/blob/next/src/components/selection.ts
// and drop editorjs-hyperlink dependency

export default class Hyperlink {
    constructor({ data, config, api, readOnly }) {
        this.toolbar = api.toolbar;
        this.inlineToolbar = api.inlineToolbar;
        this.tooltip = api.tooltip;
        this.i18n = api.i18n;
        this.config = config;
        this.selection = new SelectionUtils();

        this.commandLink = 'createLink';
        this.commandUnlink = 'unlink';

        this.CSS = {
            wrapper: 'plugin-options-wrapper',
            wrapperShowed: 'plugin-options-wrapper-showed',
            button: 'ce-inline-tool',
            buttonActive: 'ce-inline-tool--active',
            buttonModifier: 'ce-inline-tool--link',
            buttonUnlink: 'ce-inline-tool--unlink',
            input: 'plugin-option-input',
            select: 'plugin-option-input',
        };

        this.avalaibleDesign = this.config.avalaibleDesign || [
            ['bouton', 'link-btn'],
            ['dissimulé', 'ninja'], //text-current no-underline border-0 font-normal
        ];

        this.nodes = {
            wrapper: null,
            input: null,
            selectTarget: null,
            selectRel: null,
            button: null,
        };

        this.inputOpened = false;
    }

    static get toolbox() {
        return {
            icon: IconLink,
            title: 'Link',
        };
    }

    render() {
        this.nodes.button = document.createElement('button');
        this.nodes.button.type = 'button';
        this.nodes.button.classList.add(this.CSS.button, this.CSS.buttonModifier);
        this.nodes.button.appendChild(Hyperlink.iconSvg('link'));
        this.nodes.button.appendChild(Hyperlink.iconSvg('unlink'));
        return this.nodes.button;
    }

    renderActions() {
        this.nodes.input = make.element('input', this.CSS.input, { placeholder: 'https://...' });

        this.nodes.hideForBot = make.switchInput(
            'hideForBot',
            this.i18n.t('Dissimuler pour les robots')
        );
        this.nodes.targetBlank = make.switchInput(
            'targetBlank',
            this.i18n.t('Ouvrir dans un nouvel onglet')
        );

        this.nodes.selectDesign = make.element('select', this.CSS.select);
        make.option(this.nodes.selectDesign, '0', this.i18n.t('Style'), { disabled: 'disabled' });
        make.option(this.nodes.selectDesign, '');
        for (let i = 0; i < this.avalaibleDesign.length; i++) {
            make.option(
                this.nodes.selectDesign,
                this.avalaibleDesign[i][1],
                this.avalaibleDesign[i][0]
            );
        }
        if (!!this.config.design) {
            this.nodes.selectDesign.value = this.config.design;
        }

        this.nodes.wrapper = document.createElement('div');
        this.nodes.wrapper.classList.add(this.CSS.wrapper);
        this.nodes.wrapper.append(
            this.nodes.input,
            this.nodes.hideForBot,
            this.nodes.targetBlank,
            this.nodes.selectDesign
        );

        this.nodes.wrapper.addEventListener('change', (event) => {
            this.save(event);
        });
        /** */
        this.nodes.wrapper.addEventListener('keydown', (event) => {
            if (event.keyCode === 13) {
                this.selection.collapseToEnd();
                this.inlineToolbar.close();
            }
        }); /**/

        return this.nodes.wrapper;
    }

    surround(range) {
        if (range) {
            if (!this.inputOpened) {
                this.selection.setFakeBackground();
                this.selection.save();
            } else {
                this.selection.restore();
                this.selection.removeFakeBackground();
            }
            const parentAnchor = this.selection.findParentTag('A');
            if (parentAnchor) {
                this.selection.expandToTag(parentAnchor);
                this.unlink();
                this.closeActions();
                this.checkState();
                this.toolbar.close();
                return;
            }
        }
        this.toggleActions();
    }

    get shortcut() {
        return this.config.shortcut || 'CMD+K';
    }

    get title() {
        return 'Hyperlink';
    }

    static get isInline() {
        return true;
    }

    static get sanitize() {
        return {
            a: {
                href: true,
                target: true,
                rel: true,
                class: true,
            },
        };
    }

    checkState(selection = null) {
        const anchorTag = this.selection.findParentTag('A');
        if (anchorTag) {
            this.nodes.button.classList.add(this.CSS.buttonUnlink);
            this.nodes.button.classList.add(this.CSS.buttonActive);
            this.openActions();
            const hrefAttr = anchorTag.getAttribute('href');
            const targetAttr = anchorTag.getAttribute('target');
            const relAttr = anchorTag.getAttribute('rel');
            const designAttr = anchorTag.getAttribute('class');
            this.nodes.input.value = !!hrefAttr ? hrefAttr : '';
            this.nodes.hideForBot.querySelector('input').checked = !!relAttr ? true : false;
            this.nodes.targetBlank.querySelector('input').checked = !!targetAttr ? true : false;
            this.nodes.selectDesign.value = designAttr ? designAttr : '0';
            this.selection.save();
        } else {
            this.nodes.button.classList.remove(this.CSS.buttonUnlink);
            this.nodes.button.classList.remove(this.CSS.buttonActive);
        }
        return !!anchorTag;
    }
    clear() {
        this.closeActions();
    }

    toggleActions() {
        if (!this.inputOpened) {
            this.openActions(true);
        } else {
            this.closeActions(false);
        }
    }

    openActions(needFocus = false) {
        this.nodes.wrapper.classList.add(this.CSS.wrapperShowed);
        if (needFocus) {
            this.nodes.input.focus();
        }
        this.inputOpened = true;
    }

    closeActions(clearSavedSelection = true) {
        if (this.selection.isFakeBackgroundEnabled) {
            const currentSelection = new SelectionUtils();
            currentSelection.save();
            this.selection.restore();
            this.selection.removeFakeBackground();
            currentSelection.restore();
        }
        this.nodes.wrapper.classList.remove(this.CSS.wrapperShowed);
        this.nodes.input.value = '';
        this.nodes.targetBlank.querySelector('input').checked = false;
        this.nodes.hideForBot.querySelector('input').checked = false;
        this.nodes.selectDesign.value = '';

        if (clearSavedSelection) {
            this.selection.clearSaved();
        }
        this.inputOpened = false;
    }

    save(event) {
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();

        let value = this.nodes.input.value || '';
        if (!value.trim()) {
            console.log('unlink');
            this.selection.restore();
            this.unlink();
            return;
        }

        this.selection.restore();
        this.selection.removeFakeBackground();
        this.insertLink();
    }

    insertLink() {
        let href = this.nodes.input.value || '';
        let target = this.nodes.targetBlank.querySelector('input').checked ? '_blank' : '';
        let rel = this.nodes.hideForBot.querySelector('input').checked ? 'encrypt' : '';
        let design = this.nodes.selectDesign.value || '';

        let anchorTag = this.initSelection ? this.initSelection : this.selection.findParentTag('A');
        if (anchorTag) {
            this.selection.expandToTag(anchorTag);
        } else {
            document.execCommand(this.commandLink, false, '#');
            anchorTag = this.selection.findParentTag('A');
            this.initSelection = anchorTag;
        }

        if (anchorTag) {
            anchorTag['href'] = href;
            anchorTag['href'] = href;
            if (!!target) {
                anchorTag['target'] = target;
            } else {
                anchorTag.removeAttribute('target');
            }
            if (!!rel) {
                anchorTag['rel'] = rel;
            } else {
                anchorTag.removeAttribute('rel');
            }
            if (!!design) {
                anchorTag.className = design;
            } else {
                anchorTag.removeAttribute('class');
            }
        }
        return anchorTag;
    }
    unlink() {
        document.execCommand(this.commandUnlink);
    }

    static iconSvg(name) {
        var icon = new DOMParser().parseFromString(
            name === 'link' ? IconLink : IconUnlink,
            'text/xml'
        );
        var icon = icon.firstChild;
        icon.classList.add('icon', 'icon--' + name);
        return icon;
    }
}
