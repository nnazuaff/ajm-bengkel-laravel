import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { runInNewContext } from 'node:vm';

const root = fileURLToPath(new URL('../', import.meta.url));
const scratch = join(root, '.hermes');
mkdirSync(scratch, { recursive: true });
const output = mkdtempSync(join(scratch, 'browser-search-check-'));

try {
    const compiled = spawnSync(process.execPath, [
        join(root, 'node_modules/typescript/bin/tsc'),
        '--project', join(root, 'tsconfig.json'),
        '--noEmit', 'false', '--outDir', output,
        '--rootDir', join(root, 'resources/js'),
    ], { cwd: root, encoding: 'utf8' });
    assert.ifError(compiled.error);
    assert.equal(compiled.status, 0, compiled.stdout + compiled.stderr);

    // ponytail: DOM mocks exercise the handler; layout stays in browser QA.
    class Element {
        constructor(tagName = 'div', parent = null) {
            this.tagName = tagName;
            this.parent = parent;
        }
        closest(selector) {
            return selector.split(', ').includes(this.tagName)
                ? this : this.parent?.closest(selector) ?? null;
        }
    }
    class HTMLElement extends Element {
        get isContentEditable() {
            return this.editable || this.parent?.isContentEditable || false;
        }
    }
    let focused = null;
    class HTMLInputElement extends HTMLElement {
        constructor(options = {}) {
            super('input');
            Object.assign(this, {
                disabled: false, readOnly: false, type: 'search',
                rects: true, visibility: 'visible', fieldsetDisabled: false,
            }, options);
        }
        getClientRects() { return this.rects ? [{}] : []; }
        matches(selector) {
            assert.equal(selector, ':disabled');
            return this.disabled || this.fieldsetDisabled;
        }
        focus() {
            if (!this.disabled && !this.fieldsetDisabled) focused = this;
        }
    }
    let handler;
    let inputs = [];
    runInNewContext(readFileSync(join(output, 'app.js'), 'utf8'), {
        Element, HTMLElement, HTMLInputElement,
        document: {
            addEventListener(type, callback) {
                assert.equal(type, 'keydown');
                assert.equal(handler, undefined);
                handler = callback;
            },
            querySelectorAll(selector) {
                assert.equal(selector, 'input[data-workshop-search]');
                return inputs;
            },
        },
        window: { getComputedStyle: (input) => ({ visibility: input.visibility }) },
    });
    assert.equal(typeof handler, 'function');

    let checks = 0;
    function check(name, event = {}, candidates = [new HTMLInputElement()], expected = 0) {
        inputs = candidates;
        focused = null;
        let prevented = false;
        handler({
            key: 'k', ctrlKey: true, target: new HTMLElement(),
            preventDefault() { prevented = true; }, ...event,
        });
        assert.equal(focused, expected === null ? null : inputs[expected], name);
        assert.equal(prevented, expected !== null, `${name}: browser shortcut`);
        checks++;
    }

    check('Ctrl+K');
    check('Cmd+K', { ctrlKey: false, metaKey: true });
    check('uppercase key', { key: 'K' });
    check('non-element target', { target: null });
    check('first eligible visible input', {}, [
        new HTMLInputElement({ rects: false }),
        new HTMLInputElement({ visibility: 'hidden' }),
        new HTMLInputElement({ visibility: 'collapse' }),
        new HTMLInputElement({ type: 'hidden' }),
        new HTMLInputElement({ disabled: true }),
        new HTMLInputElement({ readOnly: true }),
        new HTMLInputElement(), new HTMLInputElement(),
    ], 6);
    check('no search input', {}, [], null);
    check('only hidden inputs', {}, [new HTMLInputElement({ rects: false })], null);
    for (const event of [
        { defaultPrevented: true }, { isComposing: true }, { repeat: true },
        { altKey: true }, { shiftKey: true }, { ctrlKey: false }, { key: 'j' },
    ]) check(JSON.stringify(event), event, undefined, null);
    for (const tag of ['input', 'textarea', 'select']) {
        const field = new HTMLElement(tag);
        check(`editing ${tag}`, { target: field }, undefined, null);
        check(`inside ${tag}`, { target: new HTMLElement('span', field) }, undefined, null);
    }
    const editable = Object.assign(new HTMLElement(), { editable: true });
    check('contenteditable', { target: editable }, undefined, null);
    check('contenteditable descendant', { target: new HTMLElement('span', editable) }, undefined, null);
    check('disabled fieldset', {}, [
        new HTMLInputElement({ fieldsetDisabled: true }), new HTMLInputElement(),
    ], 1);

    console.log(`browser-search-check: ${checks} passed (compiled resources/js/app.ts)`);
} finally {
    rmSync(output, { recursive: true, force: true });
}
