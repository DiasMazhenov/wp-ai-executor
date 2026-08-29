(function () {
    'use strict';

    var config = window.WPAELLMChat;
    if (!config || document.getElementById('wpae-llm-chat-root')) return;

    var strings = config.strings || {};
    var root = document.createElement('div');
    root.id = 'wpae-llm-chat-root';
    root.className = config.ready ? '' : 'wpae-llm-chat-root--disabled';

    var pill = document.createElement('div');
    pill.className = 'wpae-llm-pill';
    var mark = document.createElement('span');
    mark.className = 'wpae-llm-mark';
    mark.textContent = 'LLM';
    var status = document.createElement('span');
    status.className = 'wpae-llm-status';
    status.textContent = config.ready ? strings.placeholder : strings.disabled;
    var open = document.createElement('button');
    open.className = 'wpae-llm-open';
    open.type = 'button';
    open.textContent = strings.open;
    open.setAttribute('aria-label', strings.open);
    pill.appendChild(mark);
    pill.appendChild(status);
    pill.appendChild(open);

    var panel = document.createElement('section');
    panel.className = 'wpae-llm-panel';
    panel.setAttribute('aria-label', strings.title);
    var head = document.createElement('div');
    head.className = 'wpae-llm-panel-head';
    var heading = document.createElement('div');
    var title = document.createElement('strong');
    title.textContent = strings.title;
    var subtitle = document.createElement('small');
    subtitle.textContent = strings.subtitle;
    heading.appendChild(title);
    heading.appendChild(subtitle);
    var close = document.createElement('button');
    close.className = 'wpae-llm-close';
    close.type = 'button';
    close.textContent = '×';
    close.setAttribute('aria-label', strings.close);
    var copy = document.createElement('button');
    copy.className = 'wpae-llm-copy';
    copy.type = 'button';
    copy.textContent = strings.copyLog;
    copy.setAttribute('aria-label', strings.copyLog);
    head.appendChild(heading);
    head.appendChild(copy);
    head.appendChild(close);

    var messages = document.createElement('div');
    messages.className = 'wpae-llm-messages';
    var welcome = document.createElement('div');
    welcome.className = 'wpae-llm-message wpae-llm-message--assistant';
    welcome.textContent = strings.welcome;
    messages.appendChild(welcome);

    var form = document.createElement('form');
    form.className = 'wpae-llm-form';
    var input = document.createElement('textarea');
    input.className = 'wpae-llm-input';
    input.rows = 2;
    input.maxLength = 4000;
    input.placeholder = strings.placeholder;
    var send = document.createElement('button');
    send.className = 'wpae-llm-send';
    send.type = 'submit';
    send.textContent = strings.send;
    form.appendChild(input);
    form.appendChild(send);
    panel.appendChild(head);
    panel.appendChild(messages);
    panel.appendChild(form);
    root.appendChild(panel);
    root.appendChild(pill);
    document.body.appendChild(root);

    function setOpen(value) {
        root.classList.toggle('wpae-llm-chat-root--open', value);
        if (value) input.focus();
    }
    function addMessage(role, text) {
        var item = document.createElement('div');
        item.className = 'wpae-llm-message wpae-llm-message--' + role;
        item.textContent = text;
        messages.appendChild(item);
        messages.scrollTop = messages.scrollHeight;
    }
    function chatLog() {
        return JSON.stringify({
            format: 'wpae-llm-chat-log-v1',
            post_id: Number(config.postId) || 0,
            captured_at: new Date().toISOString(),
            messages: Array.prototype.slice.call(messages.querySelectorAll('.wpae-llm-message')).map(function (item) {
                return {
                    role: item.classList.contains('wpae-llm-message--user') ? 'user' : 'assistant',
                    content: item.textContent
                };
            })
        }, null, 2);
    }
    function copyChatLog() {
        var text = chatLog();
        var copied = function () {
            copy.textContent = strings.copied;
            window.setTimeout(function () { copy.textContent = strings.copyLog; }, 1600);
        };
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            navigator.clipboard.writeText(text).then(copied).catch(function () {});
            return;
        }
        var fallback = document.createElement('textarea');
        fallback.value = text;
        fallback.setAttribute('readonly', '');
        fallback.style.position = 'fixed';
        fallback.style.opacity = '0';
        document.body.appendChild(fallback);
        fallback.select();
        try { document.execCommand('copy'); copied(); } catch (error) {}
        document.body.removeChild(fallback);
    }
    function selectedElements() {
        var selection = window.elementor && window.elementor.selection;
        var models = selection && typeof selection.getElements === 'function' ? selection.getElements() : [];
        return Array.prototype.slice.call(models || [], 0, 8).map(function (model) {
            var attributes = model && model.attributes ? model.attributes : {};
            return {
                id: String(attributes.id || ''),
                elType: String(attributes.elType || ''),
                widgetType: String(attributes.widgetType || '')
            };
        });
    }
    function request(message) {
        var history = Array.prototype.slice.call(messages.querySelectorAll('.wpae-llm-message')).slice(-12).map(function (item) {
            return { role: item.classList.contains('wpae-llm-message--user') ? 'user' : 'assistant', content: item.textContent };
        });
        status.textContent = strings.sending;
        send.disabled = true;
        return fetch(config.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
            body: JSON.stringify({ message: message, history: history, context: { post_id: config.postId, selected_elements: selectedElements() } })
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (body) {
                if (!response.ok) {
                    var detail = body.message || body.code || ('HTTP ' + response.status);
                    var errorData = body.data || {};
                    var diagnostics = body.details || errorData.details || {};
                    if (body.details && body.details.error) detail += ': ' + body.details.error;
                    if (diagnostics.error && (!body.details || !body.details.error)) detail += ': ' + diagnostics.error;
                    if (diagnostics.details && diagnostics.details.error) detail += ': ' + diagnostics.details.error;
                    if (errorData.provider_message) detail += ': ' + errorData.provider_message;
                    if (diagnostics.finish_reason) detail += ' (finish_reason: ' + diagnostics.finish_reason + ')';
                    if (diagnostics.status && !diagnostics.error) detail += ' (HTTP ' + diagnostics.status + ')';
                    throw new Error(detail);
                }
                return body;
            });
        }).then(function (body) {
            addMessage('assistant', body.message || strings.error);
            status.textContent = strings.done;
        }).catch(function (error) {
            addMessage('assistant', strings.error + ': ' + error.message);
            status.textContent = strings.error;
        }).finally(function () {
            send.disabled = false;
        });
    }

    open.addEventListener('click', function () { setOpen(true); });
    pill.addEventListener('click', function (event) { if (event.target !== open) setOpen(true); });
    close.addEventListener('click', function () { setOpen(false); });
    copy.addEventListener('click', copyChatLog);
    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            form.requestSubmit();
        }
    });
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var message = input.value.trim();
        if (!message) {
            status.textContent = strings.empty;
            return;
        }
        if (!config.ready) {
            addMessage('assistant', strings.disabled);
            status.textContent = strings.disabled;
            return;
        }
        addMessage('user', message);
        input.value = '';
        request(message);
    });
}());
