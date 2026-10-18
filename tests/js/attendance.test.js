import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const source = await readFile(new URL('../../resources/js/attendance.js', import.meta.url), 'utf8');

function createClassList() {
    const values = new Set();
    return {
        add: (...names) => names.forEach((name) => values.add(name)),
        remove: (...names) => names.forEach((name) => values.delete(name)),
        contains: (name) => values.has(name),
    };
}

function createElement(tagName) {
    return {
        tagName,
        attributes: {},
        children: [],
        classList: createClassList(),
        setAttribute(name, value) { this.attributes[name] = value; },
        appendChild(child) { this.children.push(child); },
    };
}

async function submitWith(payload, responseOk = true, fetchRequest = async () => ({ ok: responseOk, json: async () => payload }), options = {}) {
    const submit = { disabled: false, textContent: 'Post Check-in' };
    const result = { hidden: true, textContent: '', classList: createClassList() };
    const content = { value: 'Submitted attendance message' };
    const language = { value: 'en' };
    const cards = new Map((options.existingCardIds || []).map((id) => [
        `attendance-card-${id}`, { id: `attendance-card-${id}`, querySelector: () => null },
    ]));
    let grid = options.hasGrid ? {
        className: 'attendance-grid',
        insertedHtml: [],
        insertAdjacentHTML(_position, html) {
            if (options.failInsertion) throw new Error('simulated DOM insertion failure');
            this.insertedHtml.unshift(html);
            const id = html.match(/id="(attendance-card-\d+)"/)?.[1];
            if (id) cards.set(id, { id, querySelector: () => null });
        },
    } : null;
    const emptyState = { removed: false, remove() { this.removed = true; } };
    const feed = {
        querySelector(selector) {
            if (selector === '.attendance-grid') return grid;
            if (selector === '.attendance-empty') return grid ? null : emptyState;
            return null;
        },
        insertBefore(element) { grid = element; },
        appendChild(element) { grid = element; },
    };
    const form = {
        dataset: { endpoint: '/attendance', csrf: 'csrf', completedLabel: 'Checked in today' },
        elements: { content, language_code: language },
        isConnected: true,
        classList: createClassList(),
        querySelector(selector) { return selector === '[data-attendance-submit]' ? submit : result; },
        querySelectorAll() { return []; },
        addEventListener(_name, callback) { this.onSubmit = callback; },
        replaceWith(element) { this.replacedWith = element; this.isConnected = false; },
    };
    const notifications = [];
    const document = {
        querySelector(selector) { return selector === '[data-attendance-feed]' ? feed : form; },
        querySelectorAll() { return []; },
        createElement(tagName) {
            const element = createElement(tagName);
            if (tagName === 'div') {
                element.insertedHtml = [];
                element.insertAdjacentHTML = (_position, html) => {
                    if (options.failInsertion) throw new Error('simulated DOM insertion failure');
                    element.insertedHtml.unshift(html);
                    const id = html.match(/id="(attendance-card-\d+)"/)?.[1];
                    if (id) cards.set(id, { id, querySelector: () => null });
                };
            }
            return element;
        },
        getElementById(id) { return cards.get(id) || null; },
        dispatchEvent(event) { notifications.push(event); },
    };
    const context = {
        document,
        window: { idolRankingT: (key) => key },
        fetch: fetchRequest,
        CustomEvent: class { constructor(name, options) { this.name = name; this.detail = options.detail; } },
        URL,
        console,
    };

    vm.runInNewContext(source, context);
    let prevented = false;
    await form.onSubmit({ preventDefault() { prevented = true; } });

    return { form, submit, result, notifications, prevented, feed, emptyState, getGrid: () => grid };
}

test('successful check-in replaces the active form with the saved completion and reward state', async () => {
    const ui = await submitWith({
        success: true,
        message: 'Daily check-in complete! +57 Hearts',
        data: {
            content: 'Saved message', language_code: 'en', reward_awarded: true, amount: 57,
            check_in_id: 41, card_html: '<article id="attendance-card-41" class="attendance-card">Saved message</article>',
        },
    });

    assert.equal(ui.prevented, true);
    assert.equal(ui.form.isConnected, false);
    assert.equal(ui.submit.textContent, 'Post Check-in');
    assert.equal(ui.form.replacedWith.className, 'attendance-complete');
    assert.equal(ui.form.replacedWith.children[0].textContent, 'Checked in today');
    assert.equal(ui.form.replacedWith.children[1].textContent, '+57 Heart');
    assert.equal(ui.form.replacedWith.children[2].textContent, 'Saved message');
    assert.match(ui.getGrid().insertedHtml[0], /attendance-card-41/);
});

test('already-claimed response completes the form without duplicating an unawarded badge', async () => {
    const ui = await submitWith({
        success: false,
        already_claimed: true,
        message: 'Already checked in.',
        data: {
            content: 'Previously saved message', language_code: 'ko', reward_awarded: false, amount: null,
            check_in_id: 42, card_html: '<article id="attendance-card-42" class="attendance-card">Previously saved</article>',
        },
    }, false);

    assert.equal(ui.form.isConnected, false);
    assert.equal(ui.form.replacedWith.children.length, 2);
    assert.equal(ui.form.replacedWith.children[1].textContent, 'Previously saved message');
    assert.equal(ui.notifications[0].detail.type, 'success');
    assert.match(ui.getGrid().insertedHtml[0], /attendance-card-42/);
});

test('recorded zero-Heart reward remains visible as +0 Heart', async () => {
    const ui = await submitWith({
        success: true,
        message: 'Daily check-in complete! +0 Hearts',
        data: { content: 'Zero reward message', reward_awarded: true, amount: 0 },
    });

    assert.equal(ui.form.replacedWith.children[1].textContent, '+0 Heart');
});

test('failed request restores the submit button and shows an error', async () => {
    const ui = await submitWith({ success: false, message: 'Try again.' }, false);

    assert.equal(ui.form.isConnected, true);
    assert.equal(ui.submit.disabled, false);
    assert.equal(ui.submit.textContent, 'Post Check-in');
    assert.equal(ui.result.hidden, false);
    assert.equal(ui.result.classList.contains('is-error'), true);
});

test('network failure restores the active button and shows an error', async () => {
    const ui = await submitWith({}, false, async () => { throw new Error('network unavailable'); });

    assert.equal(ui.form.isConnected, true);
    assert.equal(ui.submit.disabled, false);
    assert.equal(ui.submit.textContent, 'Post Check-in');
    assert.equal(ui.result.hidden, false);
    assert.equal(ui.result.classList.contains('is-error'), true);
});

test('empty feed removes its empty state and creates the grid for the first card', async () => {
    const ui = await submitWith({
        success: true,
        data: { content: 'First public post', reward_awarded: false, amount: null, check_in_id: 43,
            card_html: '<article id="attendance-card-43" class="attendance-card">First public post</article>' },
    });

    assert.equal(ui.emptyState.removed, true);
    assert.equal(ui.getGrid().className, 'attendance-grid');
    assert.match(ui.getGrid().insertedHtml[0], /attendance-card-43/);
});

test('an existing card is not inserted a second time', async () => {
    const ui = await submitWith({
        success: true,
        data: { content: 'Already in the feed', reward_awarded: true, amount: 9, check_in_id: 44,
            card_html: '<article id="attendance-card-44" class="attendance-card">Already in the feed</article>' },
    }, true, undefined, { hasGrid: true, existingCardIds: [44] });

    assert.equal(ui.getGrid().insertedHtml.length, 0);
});

test('feed insertion failure does not undo the completed check-in state', async () => {
    const ui = await submitWith({
        success: true,
        data: { content: 'Saved despite UI failure', reward_awarded: true, amount: 12, check_in_id: 45,
            card_html: '<article id="attendance-card-45" class="attendance-card">Saved despite UI failure</article>' },
    }, true, undefined, { hasGrid: true, failInsertion: true });

    assert.equal(ui.form.isConnected, false);
    assert.equal(ui.form.replacedWith.children[1].textContent, '+12 Heart');
});
