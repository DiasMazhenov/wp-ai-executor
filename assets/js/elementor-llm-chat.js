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
    var meta = document.createElement('small');
    meta.className = 'wpae-llm-meta';
    meta.textContent = (strings.meta || 'Модель: {model} · Версия: {version}')
        .replace('{model}', config.model || 'не указана')
        .replace('{version}', config.pluginVersion || 'неизвестна');
    heading.appendChild(title);
    heading.appendChild(subtitle);
    heading.appendChild(meta);
    var selectionHint = document.createElement('small');
    selectionHint.className = 'wpae-llm-selection-hint';
    selectionHint.setAttribute('aria-live', 'polite');
    selectionHint.textContent = 'Выделение: нет';
    heading.appendChild(selectionHint);
    var close = document.createElement('button');
    close.className = 'wpae-llm-close';
    close.type = 'button';
    close.textContent = '×';
    close.setAttribute('aria-label', strings.close);
    var copy = document.createElement('button');
    copy.className = 'wpae-llm-icon-button wpae-llm-copy';
    copy.type = 'button';
    addIcon(copy, 'eicon-code', strings.copyLog);
    var copySelection = document.createElement('button');
    copySelection.className = 'wpae-llm-icon-button wpae-llm-copy-selection';
    copySelection.type = 'button';
    addIcon(copySelection, 'eicon-copy', strings.copySelection);
    var copySelectionPasteReady = document.createElement('button');
    copySelectionPasteReady.className = 'wpae-llm-icon-button wpae-llm-copy-selection-paste-ready';
    copySelectionPasteReady.type = 'button';
    addIcon(copySelectionPasteReady, 'eicon-library-open', strings.copyPasteReady);
    var regenerate = document.createElement('button');
    regenerate.className = 'wpae-llm-icon-button wpae-llm-regenerate';
    regenerate.type = 'button';
    addIcon(regenerate, 'eicon-sync', strings.regenerate || 'Перегенерировать последний запрос');
    var reviewPending = document.createElement('button');
    reviewPending.className = 'wpae-llm-icon-button wpae-llm-review-pending';
    reviewPending.type = 'button';
    addIcon(reviewPending, 'eicon-eye', strings.reviewPending || 'Проверить сохранённый результат');
    reviewPending.title = strings.reviewPending || 'Проверить сохранённый результат';
    // The last brief also survives page reloads: after a final provider failure
    // the chat history is gone, and that is exactly when regeneration is needed.
    var lastBriefKey = 'wpae_llm_last_brief:' + String(config.postId || '0');
    var operationIdentityKey = 'wpae_llm_operation_identity:' + String(config.postId || '0');
    var operationRootsKey = 'wpae_llm_operation_roots:' + String(config.postId || '0');
    var operationRootsTtl = 600000;
    var readLastBrief = function () {
        try {
            var raw = window.sessionStorage.getItem(lastBriefKey);
            if (!raw) { return ''; }
            var parsed = JSON.parse(raw);
            var value = parsed && parsed.message ? String(parsed.message) : '';
            return value.trim();
        } catch (error) { return ''; }
    };
    function newOperationIdentity() {
        try {
            if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
        } catch (error) {}
        return 'client-' + String(Date.now()) + '-' + String(Math.random()).slice(2, 12);
    }
    function readOperationIdentity() {
        try { return String(window.sessionStorage.getItem(operationIdentityKey) || '').slice(0, 120); } catch (error) { return ''; }
    }
    function rememberOperationIdentity(identity) {
        try { window.sessionStorage.setItem(operationIdentityKey, String(identity || '').slice(0, 120)); } catch (error) {}
    }
    function readOperationRoots() {
        try {
            var raw = window.sessionStorage.getItem(operationRootsKey);
            if (!raw) return [];
            var state = JSON.parse(raw);
            if (!state || Date.now() - Number(state.createdAt || 0) > operationRootsTtl) {
                window.sessionStorage.removeItem(operationRootsKey);
                return [];
            }
            return Array.isArray(state.ids) ? state.ids.map(String).filter(Boolean).slice(0, 12) : [];
        } catch (error) { return []; }
    }
    function rememberOperationRoots(ids) {
        var uniqueIds = Array.from(new Set((Array.isArray(ids) ? ids : []).map(String).filter(Boolean))).slice(0, 12);
        if (!uniqueIds.length) return;
        try { window.sessionStorage.setItem(operationRootsKey, JSON.stringify({ ids: uniqueIds, createdAt: Date.now() })); } catch (error) {}
    }
    function clearOperationRoots() {
        try { window.sessionStorage.removeItem(operationRootsKey); } catch (error) {}
    }
    regenerate.addEventListener('click', function () {
        // Regeneration replays the most recent user brief through the normal
        // request path, so server gates and Vision review stay identical.
        if (send.disabled) { addMessage('assistant', strings.regenerateBusy || 'Дождитесь завершения текущего запроса.'); return; }
        var userMessages = messages.querySelectorAll('.wpae-llm-message--user');
        var last = userMessages.length ? messageContent(userMessages[userMessages.length - 1]).trim() : '';
        if (!last) { last = readLastBrief(); }
        if (!last) {
            addMessage('assistant', strings.regenerateEmpty || 'Нет предыдущего запроса для перегенерации.');
            return;
        }
        request(last, false, { retryCurrentOperation: true });
    });
    reviewPending.addEventListener('click', function () {
        if (send.disabled) { addMessage('assistant', strings.regenerateBusy || 'Дождитесь завершения текущего запроса.'); return; }
        if (!config.pendingOperation || !config.pendingOperation.operation_id) {
            addMessage('assistant', 'Для этой страницы нет незавершенной durable operation.');
            return;
        }
        reviewPending.disabled = true;
        setPipelinePhase('render', 'active');
        reviewPendingOperation(config.pendingOperation, config.pendingOperation.brief_text || '').then(function (review) {
            setPipelinePhase('render', 'done');
            setPipelinePhase('review', review && review.report ? 'done' : 'skipped');
            if (review && review.report) addMessage('assistant', describeVisionReview(review));
            status.textContent = strings.done;
        }).catch(function (error) {
            addMessage('assistant', 'Операция сохранена, но reconcile оставлен pending: ' + error.message);
            status.textContent = strings.error;
        }).finally(function () { reviewPending.disabled = false; });
    });
    var headActions = document.createElement('div');
    headActions.className = 'wpae-llm-head-actions';
    headActions.appendChild(copy);
    headActions.appendChild(copySelection);
    headActions.appendChild(copySelectionPasteReady);
    headActions.appendChild(regenerate);
    if (config.pendingOperation && config.pendingOperation.operation_id) headActions.appendChild(reviewPending);
    headActions.appendChild(close);
    head.appendChild(heading);
    head.appendChild(headActions);

    var pipelinePhaseDefinitions = [
        { id: 'parse', label: 'Разбор запроса' },
        { id: 'plan', label: 'Планирование дизайна' },
        { id: 'components', label: 'Подбор компонентов' },
        { id: 'responsive', label: 'Проверка responsive' },
        { id: 'compile', label: 'Сборка Elementor' },
        { id: 'write', label: 'Запись' },
        { id: 'render', label: 'Рендер' },
        { id: 'review', label: 'Visual review' }
    ];
    var pipelinePhaseNodes = {};
    var pipeline = document.createElement('ol');
    pipeline.className = 'wpae-llm-pipeline';
    pipeline.setAttribute('aria-label', 'Фазы генерации дизайна');
    pipelinePhaseDefinitions.forEach(function (phase) {
        var item = document.createElement('li');
        item.className = 'wpae-llm-pipeline__phase wpae-llm-pipeline__phase--pending';
        item.dataset.phase = phase.id;
        item.textContent = phase.label;
        pipeline.appendChild(item);
        pipelinePhaseNodes[phase.id] = item;
    });
    function resetPipelinePhases() {
        pipelinePhaseDefinitions.forEach(function (phase) {
            var item = pipelinePhaseNodes[phase.id];
            if (item) item.className = 'wpae-llm-pipeline__phase wpae-llm-pipeline__phase--pending';
        });
    }
    function setPipelinePhase(id, state) {
        var item = pipelinePhaseNodes[id];
        if (!item) return;
        item.className = 'wpae-llm-pipeline__phase wpae-llm-pipeline__phase--' + (state || 'pending');
    }
    function updatePipelineFromSteps(steps) {
        (Array.isArray(steps) ? steps : []).forEach(function (step) {
            var id = String(step && step.id || '');
            var state = step && step.status === 'failed' ? 'failed' : (step && step.status === 'skipped' ? 'skipped' : 'done');
            if (id === 'brief_ir') setPipelinePhase('parse', state);
            if (id === 'design_plan') setPipelinePhase('plan', state);
            if (id === 'capabilities') setPipelinePhase('components', state);
            if (id === 'layout_report') setPipelinePhase('responsive', state);
            if (id === 'elementor_ir') setPipelinePhase('compile', state);
            if (id === 'elementor_update' || id === 'preview') setPipelinePhase('write', state);
            if (id === 'render_cache') setPipelinePhase('render', state);
            if (id === 'vision_review' || id === 'vision_feedback_prompt') setPipelinePhase('review', state);
        });
    }

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
    send.className = 'wpae-llm-icon-button wpae-llm-send';
    send.type = 'submit';
    addIcon(send, 'eicon-arrow-right', strings.send);
    form.appendChild(input);
    form.appendChild(send);
    panel.appendChild(head);
    panel.appendChild(pipeline);
    panel.appendChild(messages);
    panel.appendChild(form);
    root.appendChild(panel);
    root.appendChild(pill);
    document.body.appendChild(root);

    function setOpen(value) {
        root.classList.toggle('wpae-llm-chat-root--open', value);
        refreshSelectionHint();
        if (value) input.focus();
    }
    function addIcon(button, iconClass, label) {
        var icon = document.createElement('i');
        icon.className = iconClass;
        icon.setAttribute('aria-hidden', 'true');
        button.appendChild(icon);
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
    }
    function setButtonLabel(button, label) {
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
    }
    function messageContent(item) {
        if (!item) return '';
        if (item.dataset && item.dataset.message !== undefined) return String(item.dataset.message);
        return String(item.textContent || '');
    }
    function addMessage(role, text) {
        var content = String(text || '');
        var item = document.createElement('div');
        item.className = 'wpae-llm-message wpae-llm-message--' + role;
        item.dataset.message = content;
        if (role === 'user') {
            var messageText = document.createElement('span');
            messageText.className = 'wpae-llm-message__text';
            messageText.textContent = content;
            var copyPrompt = document.createElement('button');
            copyPrompt.type = 'button';
            copyPrompt.className = 'wpae-llm-icon-button wpae-llm-copy-prompt';
            addIcon(copyPrompt, 'eicon-copy', strings.copyPrompt || 'Копировать промт');
            copyPrompt.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                copyText(content).then(function () {
                    setButtonLabel(copyPrompt, strings.promptCopied || 'Промт скопирован');
                    window.setTimeout(function () { setButtonLabel(copyPrompt, strings.copyPrompt || 'Копировать промт'); }, 1600);
                }).catch(function () {
                    setButtonLabel(copyPrompt, strings.copyError || 'Не удалось скопировать текст.');
                });
            });
            item.appendChild(messageText);
            item.appendChild(copyPrompt);
        } else {
            item.textContent = content;
        }
        messages.appendChild(item);
        messages.scrollTop = messages.scrollHeight;
    }
    function formatStep(step, index) {
        var labels = {
            received_action: 'полученная команда',
            received_post_id: 'полученный post_id',
            decoded_action: 'распознанная команда',
            decoded_post_id: 'распознанный post_id',
            decoded_element_count: 'распознано элементов',
            decoded_patch_count: 'распознано patch-операций',
            patch_count: 'patch-операций',
            selected_scope_count: 'элементов в выбранной области',
            changed_ids: 'измененные element_id',
            widget_count: 'native widgets',
            expected_action: 'ожидаемая команда',
            expected_post_id: 'ожидаемый post_id',
            element_count: 'элементов',
            existing_element_count: 'элементов на странице',
            http_status: 'HTTP',
            response_type: 'тип ответа',
            json_decoded: 'JSON разобран',
            response_keys: 'ключи ответа',
            reply_preview: 'фрагмент ответа',
            reply_length: 'длина ответа',
            json_error: 'ошибка JSON',
            likely_truncated: 'возможен обрыв ответа',
            finish_reason: 'причина завершения',
            provider_error_code: 'код провайдера',
            provider_message: 'сообщение провайдера',
            guide_version: 'версия guide',
            custom_skills_count: 'подключено skills',
            elementor_writes: 'запись Elementor',
            failed_checks: 'непройденные проверки',
            failure_details: 'детали проверок',
            operation_id: 'operation ID',
            inserted_ids: 'добавленные ID',
            diff: 'diff'
        };
        var line = 'Шаг ' + (index + 1) + ': ' + String(step.message || step.id || 'Операция выполнена');
        if (step.status === 'failed') line += ' [ошибка]';
        if (step.status === 'skipped') line += ' [пропущено]';
        var details = step.details || {};
        var parts = [];
        ['received_action', 'received_post_id', 'decoded_action', 'decoded_post_id', 'decoded_element_count', 'decoded_patch_count', 'patch_count', 'selected_scope_count', 'changed_ids', 'expected_action', 'expected_post_id', 'element_count', 'widget_count', 'existing_element_count', 'http_status', 'response_type', 'json_decoded', 'response_keys', 'reply_preview', 'reply_length', 'json_error', 'likely_truncated', 'finish_reason', 'provider_error_code', 'provider_message', 'guide_version', 'custom_skills_count', 'elementor_writes', 'failed_checks', 'failure_details', 'operation_id', 'inserted_ids', 'diff'].forEach(function (key) {
            if (details[key] !== undefined && details[key] !== null && details[key] !== '') {
                var value = details[key];
                if (Array.isArray(value)) {
                    value = value.map(function (item) {
                        return item && typeof item === 'object' ? ((item.code || 'check') + ': ' + (item.message || 'проверка не пройдена')) : String(item);
                    }).join(', ');
                }
                parts.push((labels[key] || key) + ': ' + String(value));
            }
        });
        return parts.length ? line + ' (' + parts.join('; ') + ')' : line;
    }
    function addStepMessages(steps) {
        steps.forEach(function (step, index) { addMessage('assistant', formatStep(step, index)); });
    }
    function chatLog() {
        return JSON.stringify({
            format: 'wpae-llm-chat-log-v1',
            post_id: Number(config.postId) || 0,
            captured_at: new Date().toISOString(),
            messages: Array.prototype.slice.call(messages.querySelectorAll('.wpae-llm-message')).map(function (item) {
                return {
                    role: item.classList.contains('wpae-llm-message--user') ? 'user' : 'assistant',
                    content: messageContent(item)
                };
            })
        }, null, 2);
    }
    var providerRetryKey = 'wpae_llm_provider_retry:' + String(config.postId || '0');
    var providerRetryTtl = 600000;
    function readProviderRetry() {
        try {
            var raw = window.sessionStorage.getItem(providerRetryKey);
            if (!raw) return null;
            var state = JSON.parse(raw);
            if (!state || !state.message || Date.now() - Number(state.createdAt || 0) > providerRetryTtl) {
                window.sessionStorage.removeItem(providerRetryKey);
                return null;
            }
            return state;
        } catch (error) {
            return null;
        }
    }
    function clearProviderRetry() {
        try { window.sessionStorage.removeItem(providerRetryKey); } catch (error) {}
    }
    function isProviderUnavailable(error) {
        if (!error) return false;
        if (error.wpaeCode === 'wpae_llm_provider_request_failed' || String(error.message || '').indexOf('LLM-провайдер недоступен') !== -1) return true;
        var providerStatus = Number(error.providerStatus || 0);
        var httpStatus = Number(error.httpStatus || 0);
        if (httpStatus === 408 || httpStatus === 425 || httpStatus === 429 || httpStatus >= 500) return true;
        if (providerStatus === 408 || providerStatus === 425 || providerStatus === 429 || providerStatus >= 500) return true;
        return error.wpaeCode === 'wpae_llm_provider_error' && String(error.message || '').indexOf('finish_reason: error') !== -1;
    }
    function isProviderRateLimited(error) {
        if (!error) return false;
        if (error.wpaeCode === 'wpae_llm_provider_rate_limited') return true;
        var message = String(error.message || '').toLowerCase();
        return Number(error.providerStatus || 0) === 429 && (message.indexOf('rate limit') !== -1 || message.indexOf('rate-limited') !== -1 || message.indexOf('ограничен по лимиту') !== -1);
    }
    function scheduleRateLimitedRetry(message, options, retryAfter) {
        // A shared free pool asked for a delayed retry. Nothing was written and
        // the editor state is healthy, so wait once and retry in place instead
        // of reloading the whole Elementor editor.
        var delay = Number(retryAfter) > 0 ? Math.max(15000, Math.min(60000, Number(retryAfter) * 1000)) : 30000;
        addMessage('assistant', 'Пул модели временно ограничен по лимиту (rate limit). Повторяю запрос один раз через ' + Math.round(delay / 1000) + ' секунд без перезагрузки.');
        status.textContent = 'Ожидание сброса лимита…';
        window.setTimeout(function () {
            status.textContent = strings.sending;
            request(message, true, options || {});
        }, delay);
    }
    function scheduleProviderRetry(message, options) {
        if (readProviderRetry()) return false;
        try {
            var retryOptions = options && typeof options === 'object' ? {
                repairDepth: Number(options.repairDepth) || 0,
                originalBrief: String(options.originalBrief || '').slice(0, 4000),
                visionRepair: Boolean(options.visionRepair),
                visionRegenerate: Boolean(options.visionRegenerate),
                visionFindings: String(options.visionFindings || '').slice(0, 3600),
                skipVision: Boolean(options.skipVision),
                retryCurrentOperation: Boolean(options.retryCurrentOperation),
                selectedElements: Array.isArray(options.selectedElements) ? options.selectedElements.slice(0, 8) : undefined
            } : {};
            window.sessionStorage.setItem(providerRetryKey, JSON.stringify({ message: String(message).slice(0, 4000), options: retryOptions, createdAt: Date.now() }));
        } catch (error) {
            return false;
        }
        addMessage('assistant', 'LLM-провайдер недоступен. Повторяю запрос один раз через 10 секунд без перезагрузки.');
        status.textContent = 'Ожидание провайдера…';
        // Transport failures write nothing, so the editor state stays healthy:
        // retry in place instead of reloading the whole Elementor editor.
        window.setTimeout(function () {
            clearProviderRetry();
            status.textContent = strings.sending;
            request(message, true, options || {});
        }, 10000);
        return true;
    }
    function retryProviderRequestAfterReload() {
        var pending = readProviderRetry();
        if (!pending) return;
        if (!config.ready) {
            window.setTimeout(retryProviderRequestAfterReload, 1000);
            return;
        }
        clearProviderRetry();
        setOpen(true);
        status.textContent = strings.sending;
        addMessage('user', pending.message);
        addMessage('assistant', 'Повторяю запрос после перезагрузки страницы.');
        window.setTimeout(function () { request(pending.message, true, pending.options || {}); }, 0);
    }
    var visionRepairKey = 'wpae_llm_vision_repair:' + String(config.postId || '0');
    var visionRepairTtl = 600000;
    function readVisionRepair() {
        try {
            var raw = window.sessionStorage.getItem(visionRepairKey);
            if (!raw) return null;
            var state = JSON.parse(raw);
            if (!state || !state.message || Date.now() - Number(state.createdAt || 0) > visionRepairTtl) {
                window.sessionStorage.removeItem(visionRepairKey);
                return null;
            }
            return state;
        } catch (error) {
            return null;
        }
    }
    function clearVisionRepair() {
        try { window.sessionStorage.removeItem(visionRepairKey); } catch (error) {}
    }
    function scheduleVisionRepairAfterReload(message, options) {
        if (readVisionRepair()) return false;
        try {
            var repairOptions = options && typeof options === 'object' ? {
                repairDepth: Number(options.repairDepth) || 0,
                originalBrief: String(options.originalBrief || '').slice(0, 4000),
                visionRepair: true,
                visionRegenerate: Boolean(options.visionRegenerate),
                visionFindings: String(options.visionFindings || '').slice(0, 3600),
                ownedRootIds: Array.isArray(options.ownedRootIds) ? options.ownedRootIds.map(String).filter(Boolean).slice(0, 12) : liveGeneratedRootIds.slice(0, 12),
                selectedElements: Array.isArray(options.selectedElements) ? options.selectedElements.slice(0, 8) : undefined
            } : {};
            window.sessionStorage.setItem(visionRepairKey, JSON.stringify({ message: String(message).slice(0, 4000), options: repairOptions, createdAt: Date.now() }));
        } catch (error) {
            return false;
        }
        status.textContent = 'Перезагрузка страницы…';
        window.setTimeout(function () {
            window.location.reload();
            window.setTimeout(function () {
                if (readVisionRepair()) retryVisionRepairAfterReload();
            }, 1800);
        }, 250);
        return true;
    }
    function retryVisionRepairAfterReload() {
        var pending = readVisionRepair();
        if (!pending) return;
        if (!config.ready) {
            window.setTimeout(retryVisionRepairAfterReload, 1000);
            return;
        }
        clearVisionRepair();
        liveGeneratedRootIds = Array.isArray(pending.options && pending.options.ownedRootIds)
            ? pending.options.ownedRootIds.map(String).filter(Boolean).slice(0, 12)
            : [];
        rememberOperationRoots(liveGeneratedRootIds);
        setOpen(true);
        status.textContent = strings.sending;
        addMessage('user', pending.message);
        addMessage('assistant', 'Повторяю генерацию после полной перезагрузки Elementor.');
        // Embedded editors may ignore window.location.reload(); refresh the
        // preview first so rolled-back roots cannot survive into the repair.
        window.setTimeout(function () {
            refreshSavedElementorPreview().catch(function () { return false; }).then(function () {
                return clearEditorRoots(true);
            }).then(function () {
                request(pending.message, false, pending.options || {});
            });
        }, 0);
    }
    function copyText(text) {
        var fallbackCopy = function () {
            return new Promise(function (resolve, reject) {
                var fallback = document.createElement('textarea');
                fallback.value = text;
                fallback.setAttribute('readonly', '');
                fallback.style.position = 'fixed';
                fallback.style.opacity = '0';
                document.body.appendChild(fallback);
                fallback.select();
                var copied = false;
                try { copied = document.execCommand('copy'); } catch (error) {}
                document.body.removeChild(fallback);
                if (copied) resolve();
                else reject(new Error(strings.copyError || 'Не удалось скопировать текст.'));
            });
        };
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            return navigator.clipboard.writeText(text).catch(fallbackCopy);
        }
        return fallbackCopy();
    }
    function copyChatLog() {
        copyText(chatLog()).then(function () {
            setButtonLabel(copy, strings.copied);
            window.setTimeout(function () { setButtonLabel(copy, strings.copyLog); }, 1600);
        }).catch(function () {});
    }
    function addGeneratedJsonSpoiler(elements) {
        if (!Array.isArray(elements) || !elements.length) return;
        var payload = {
            format: 'wpae-elementor-generated-v1',
            post_id: Number(config.postId) || 0,
            captured_at: new Date().toISOString(),
            elements: elements
        };
        var json = JSON.stringify(payload, null, 2);
        var spoiler = document.createElement('details');
        spoiler.className = 'wpae-llm-json-spoiler';
        var summary = document.createElement('summary');
        summary.textContent = 'JSON сгенерированного дизайна';
        var content = document.createElement('div');
        content.className = 'wpae-llm-json-content';
        var code = document.createElement('pre');
        code.textContent = json;
        var copyButton = document.createElement('button');
        copyButton.type = 'button';
        copyButton.className = 'wpae-llm-icon-button wpae-llm-copy-generated';
        addIcon(copyButton, 'eicon-copy', 'Копировать JSON дизайна');
        copyButton.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            copyText(json).then(function () {
                setButtonLabel(copyButton, 'JSON дизайна скопирован');
                window.setTimeout(function () { setButtonLabel(copyButton, 'Копировать JSON дизайна'); }, 1600);
            }).catch(function () {
                setButtonLabel(copyButton, strings.copyError || 'Ошибка копирования');
            });
        });
        var pasteReadyButton = document.createElement('button');
        pasteReadyButton.type = 'button';
        pasteReadyButton.className = 'wpae-llm-icon-button wpae-llm-copy-generated wpae-llm-copy-paste-ready';
        addIcon(pasteReadyButton, 'eicon-library-open', strings.copyPasteReady);
        pasteReadyButton.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            var pasteReady = buildPasteReadyPayload(elements);
            if (!pasteReady) return;
            copyText(JSON.stringify(pasteReady, null, 2)).then(function () {
                setButtonLabel(pasteReadyButton, strings.pasteReadyCopied);
                window.setTimeout(function () { setButtonLabel(pasteReadyButton, strings.copyPasteReady); }, 1600);
            }).catch(function () {
                setButtonLabel(pasteReadyButton, strings.copyError || 'Ошибка копирования');
            });
        });
        content.appendChild(code);
        content.appendChild(copyButton);
        content.appendChild(pasteReadyButton);
        spoiler.appendChild(summary);
        spoiler.appendChild(content);
        messages.appendChild(spoiler);
        messages.scrollTop = messages.scrollHeight;
    }
    function addDiagnosticJsonMessage(diagnostics) {
        if (!diagnostics || typeof diagnostics !== 'object') return;
        var json;
        try { json = JSON.stringify(diagnostics, null, 2); } catch (error) { return; }
        var item = document.createElement('div');
        item.className = 'wpae-llm-message wpae-llm-message--assistant wpae-llm-message--diagnostic';
        item.dataset.message = 'JSON диагностики LLM:\n' + json;
        var spoiler = document.createElement('details');
        spoiler.className = 'wpae-llm-json-spoiler';
        var summary = document.createElement('summary');
        summary.textContent = 'JSON диагностики LLM';
        var content = document.createElement('div');
        content.className = 'wpae-llm-json-content';
        var code = document.createElement('pre');
        code.textContent = json;
        var copyButton = document.createElement('button');
        copyButton.type = 'button';
        copyButton.className = 'wpae-llm-icon-button wpae-llm-copy-generated';
        addIcon(copyButton, 'eicon-copy', 'Копировать JSON диагностики');
        copyButton.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            copyText(json).then(function () {
                setButtonLabel(copyButton, 'JSON диагностики скопирован');
                window.setTimeout(function () { setButtonLabel(copyButton, 'Копировать JSON диагностики'); }, 1600);
            }).catch(function () {
                setButtonLabel(copyButton, strings.copyError || 'Ошибка копирования');
            });
        });
        content.appendChild(code);
        content.appendChild(copyButton);
        spoiler.appendChild(summary);
        spoiler.appendChild(content);
        item.appendChild(spoiler);
        messages.appendChild(item);
        messages.scrollTop = messages.scrollHeight;
    }
    var selectedModelCache = [];
    function liveSelectedModels() {
        var editor = window.elementor;
        var selection = editor && editor.selection;
        var models = selection && typeof selection.getElements === 'function' ? selection.getElements() : [];
        if (!models.length && editor && editor.channels && editor.channels.editor && typeof editor.channels.editor.get === 'function') {
            var activeModel = editor.channels.editor.get('activeModel');
            if (activeModel) models = [activeModel];
        }
        return Array.prototype.slice.call(models || [], 0, 8).map(function (container) {
            return container && container.model ? container.model : container;
        }).filter(function (model) {
            return Boolean(model && (typeof model.toJSON === 'function' || model.attributes));
        });
    }
    function selectedModels() {
        return liveSelectedModels();
    }
    function copySelectionModels() {
        var models = liveSelectedModels();
        if (models.length) selectedModelCache = models;
        return models.length ? models : selectedModelCache;
    }
    function serializeSelectedModel(model) {
        var raw = model && typeof model.toJSON === 'function' ? model.toJSON() : (model && model.attributes ? model.attributes : {});
        var data = {};
        Object.keys(raw || {}).forEach(function (key) {
            if (key !== 'elements') data[key] = raw[key];
        });
        var children = model && typeof model.get === 'function' ? model.get('elements') : null;
        if (children && Array.isArray(children.models)) data.elements = children.models.map(serializeSelectedModel);
        else if (raw && Array.isArray(raw.elements)) data.elements = raw.elements;
        else data.elements = [];
        return data;
    }
    function copySelectedJson() {
        var models = copySelectionModels();
        if (!models.length) {
            addMessage('assistant', strings.selectionEmpty);
            return;
        }
        var payload = {
            format: 'wpae-elementor-selection-v1',
            post_id: Number(config.postId) || 0,
            captured_at: new Date().toISOString(),
            elements: models.map(serializeSelectedModel)
        };
        copyText(JSON.stringify(payload, null, 2)).then(function () {
            setButtonLabel(copySelection, strings.selectionCopied);
            addMessage('assistant', strings.selectionCopied);
            window.setTimeout(function () { setButtonLabel(copySelection, strings.copySelection); }, 1600);
        }).catch(function () {
            addMessage('assistant', strings.selectionCopyError || strings.copyError);
        });
    }
    function buildPasteReadyPayload(elements) {
        if (!Array.isArray(elements) || !elements.length) return null;
        return {
            type: 'elementor',
            siteurl: '',
            elements: elements
        };
    }
    function copySelectedPasteReadyJson() {
        var models = copySelectionModels();
        if (!models.length) {
            addMessage('assistant', strings.selectionEmpty);
            return;
        }
        var pasteReady = buildPasteReadyPayload(models.map(serializeSelectedModel));
        if (!pasteReady) return;
        copyText(JSON.stringify(pasteReady, null, 2)).then(function () {
            setButtonLabel(copySelectionPasteReady, strings.pasteReadyCopied);
            addMessage('assistant', strings.pasteReadyCopied);
            window.setTimeout(function () { setButtonLabel(copySelectionPasteReady, strings.copyPasteReady); }, 1600);
        }).catch(function () {
            addMessage('assistant', strings.copyError || 'Ошибка копирования');
        });
    }
    function selectedElements() {
        return selectedModels().map(serializeSelectedModel);
    }
    function refreshSelectionHint() {
        if (!selectionHint) return;
        var models = selectedModels();
        selectionHint.textContent = models.length ? 'Выбрано: ' + models.length + (models.length === 1 ? ' объект' : ' объекта') : 'Выделение: нет';
    }
    function buildVisionRepairMessage(review, originalBrief, targetedPatch) {
        var report = review && review.report ? review.report : {};
        var findings = Array.isArray(report.findings) ? report.findings.slice(0, 6).map(function (finding) {
            var message = finding.message || 'исправление визуальной проблемы';
            var fix = finding.fix ? ' Исправление: ' + finding.fix : '';
            return (finding.severity || 'info') + ': ' + message + fix;
        }).join('; ') : '';
        var instruction = targetedPatch
            ? 'Исправь выбранный Elementor-элемент или его дочернее содержимое по исходному запросу пользователя с учетом замечаний AI Vision. Сохрани место элемента и все корректные настройки, не пересобирай страницу и не добавляй новый блок.'
            : 'Перегенерируй текущий дизайн по исходному запросу пользователя с учетом замечаний AI Vision. Создай полноценный красивый блок заново, не урезай композицию и не оставляй placeholder-тексты.';
        return instruction + ' Исходный запрос пользователя: «' + String(originalBrief || '').slice(0, 4000) + '». ' + (findings || 'Устрани нарушения композиции, типографики, отступов и переполнения.');
    }
    function getPreviewIframe() {
        var iframe = document.querySelector('#elementor-preview-iframe');
        return iframe && iframe.contentWindow ? iframe : null;
    }
    function getPreviewWidgetCount() {
        var iframe = getPreviewIframe();
        return iframe && iframe.contentDocument ? iframe.contentDocument.querySelectorAll('.elementor-widget').length : 0;
    }
    function getEditorSyncIds(editorSync) {
        if (!editorSync) return [];
        var ids = Array.isArray(editorSync.elements) ? editorSync.elements.map(function (element) {
            return element && element.id ? String(element.id) : '';
        }).filter(Boolean) : [];
        if (!ids.length && Array.isArray(editorSync.target_element_ids)) ids = editorSync.target_element_ids.map(String).filter(Boolean);
        if (!ids.length && Array.isArray(editorSync.changed_ids)) ids = editorSync.changed_ids.map(String).filter(Boolean);
        return ids;
    }
    function getVisionSyncIds(editorSync) {
        if (editorSync && ['patch', 'replace'].indexOf(editorSync.mode) !== -1 && Array.isArray(editorSync.selected_scope_ids) && editorSync.selected_scope_ids.length) {
            return editorSync.selected_scope_ids.map(function (id) { return String(id || ''); }).filter(Boolean).slice(0, 8);
        }
        return getEditorSyncIds(editorSync);
    }
    function findPreviewTarget(doc, targetElementIds) {
        if (!doc || !Array.isArray(targetElementIds) || !targetElementIds.length) return null;
        var ids = targetElementIds.map(function (id) { return String(id || ''); }).filter(Boolean);
        return Array.prototype.slice.call(doc.querySelectorAll('[data-id]')).find(function (element) {
            return ids.indexOf(element.getAttribute('data-id')) !== -1;
        }) || null;
    }
    function focusEditorSync(editorSync) {
        var ids = getEditorSyncIds(editorSync);
        if (!ids.length) return Promise.resolve(false);
        return new Promise(function (resolve) {
            var started = Date.now();
            var findTarget = function () {
                var iframe = getPreviewIframe();
                var doc = iframe && iframe.contentDocument;
                var target = findPreviewTarget(doc, ids);
                if (target) {
                    target.scrollIntoView({ block: 'start', inline: 'nearest' });
                    resolve(true);
                    return;
                }
                if (Date.now() - started >= 6000) {
                    resolve(false);
                    return;
                }
                window.setTimeout(findTarget, 200);
            };
            findTarget();
        });
    }
    function getPreviewRenderContext(targetElementIds, reviewScope, captureMeta) {
        var iframe = getPreviewIframe();
        var doc = iframe && iframe.contentDocument;
        if (!doc) return {};
        var body = doc.body;
        var target = findPreviewTarget(doc, targetElementIds);
        var scope = target || body || doc.documentElement;
        var isVisible = function (element) {
            if (!element) return false;
            var style = doc.defaultView.getComputedStyle(element);
            return style.display !== 'none' && style.visibility !== 'hidden' && Number(style.opacity || 1) > 0;
        };
        var ids = Array.prototype.slice.call(scope.querySelectorAll('[data-id]')).filter(function (element) {
            return isVisible(element);
        }).slice(0, 40).map(function (element) { return element.getAttribute('data-id'); });
        if (target && target.getAttribute('data-id')) ids.unshift(target.getAttribute('data-id'));
        ids = Array.from(new Set(ids)).filter(function (id) { return !!id; });
        var visibleMediaCount = 0;
        if (isVisible(scope) && doc.defaultView.getComputedStyle(scope).backgroundImage !== 'none') visibleMediaCount += 1;
        Array.prototype.slice.call(scope.querySelectorAll('img,video,picture')).forEach(function (element) {
            if (isVisible(element)) visibleMediaCount += 1;
        });
        Array.prototype.slice.call(scope.querySelectorAll('[data-id]')).forEach(function (element) {
            if (isVisible(element) && doc.defaultView.getComputedStyle(element).backgroundImage !== 'none') visibleMediaCount += 1;
        });
        var labeledCtaCount = Array.prototype.slice.call(scope.querySelectorAll('.elementor-button,a[href],button')).filter(function (element) {
            return isVisible(element) && (element.innerText || '').replace(/\s+/g, ' ').trim() !== '';
        }).length;
        var headingCount = Array.prototype.slice.call(scope.querySelectorAll('h1,h2,h3,h4,h5,h6')).filter(isVisible).length;
        captureMeta = captureMeta && typeof captureMeta === 'object' ? captureMeta : {};
        return {
            source: 'elementor_editor_preview',
            editor_chrome_excluded: true,
            review_scope: reviewScope || 'generated_block',
            target_found: !!target,
            widget_count: scope ? scope.querySelectorAll('.elementor-widget').length : 0,
            visible_media_count: visibleMediaCount,
            labeled_cta_count: labeledCtaCount,
            heading_count: headingCount,
            text_length: scope ? (scope.innerText || '').trim().length : 0,
            text_excerpt: scope ? (scope.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 4000) : '',
            viewport_width: doc.documentElement.clientWidth || iframe.clientWidth || 0,
            viewport_height: iframe.clientHeight || doc.documentElement.clientHeight || 0,
            horizontal_overflow: !!(scope && scope.scrollWidth > scope.clientWidth + 2),
            capture_scope: String(captureMeta.capture_scope || (target ? 'element' : 'document')),
            capture_complete: captureMeta.capture_complete !== false,
            capture_width: Number(captureMeta.capture_width || 0),
            capture_height: Number(captureMeta.capture_height || 0),
            target_scroll_width: Number(captureMeta.target_scroll_width || 0),
            target_scroll_height: Number(captureMeta.target_scroll_height || 0),
            page_scroll_x: Number(captureMeta.page_scroll_x || 0),
            page_scroll_y: Number(captureMeta.page_scroll_y || 0),
            target_rect: captureMeta.target_rect || null,
            visible_element_ids: ids,
            target_element_ids: Array.isArray(targetElementIds) ? targetElementIds.slice(0, 8) : []
        };
    }
    function getPreviewBackgroundImageUrls() {
        var iframe = getPreviewIframe();
        var doc = iframe && iframe.contentDocument;
        if (!doc) return [];
        var urls = [];
        var collect = function (element) {
            var image = doc.defaultView.getComputedStyle(element).backgroundImage || '';
            var match = image.match(/url\((['"]?)(.*?)\1\)/);
            var url = match && match[2] ? match[2] : '';
            if (/^https?:\/\//i.test(url)) urls.push(url);
        };
        Array.prototype.slice.call(doc.querySelectorAll('[data-element_type="container"], [data-id]')).forEach(collect);
        return Array.from(new Set(urls));
    }
    function reloadPreviewIframe() {
        var iframe = getPreviewIframe();
        if (!iframe) return Promise.resolve(false);
        var source = iframe.getAttribute('src') || iframe.src;
        if (!source) return Promise.resolve(false);
        try {
            var url = new URL(source, window.location.href);
            url.searchParams.set('ver', String(Date.now()));
            url.searchParams.set('wpae_editor_refresh', String(Date.now()));
            source = url.toString();
        } catch (error) {
            source += (source.indexOf('?') === -1 ? '?' : '&') + 'wpae_editor_refresh=' + Date.now();
        }
        return new Promise(function (resolve) {
            var settled = false;
            var finish = function (result) {
                if (settled) return;
                settled = true;
                iframe.removeEventListener('load', onLoad);
                resolve(result);
            };
            var onLoad = function () { finish(true); };
            iframe.addEventListener('load', onLoad);
            window.setTimeout(function () { finish(false); }, 10000);
            iframe.src = source;
        });
    }
    function refreshElementorPreview() {
        var officialRefresh = false;
        if (window.$e && window.$e.components && typeof window.$e.components.get === 'function') {
            try {
                var saveComponent = window.$e.components.get('document/save');
                var footerSaver = saveComponent && saveComponent.footerSaver;
                if (footerSaver && typeof footerSaver.refreshWpPreview === 'function') {
                    footerSaver.refreshWpPreview();
                    officialRefresh = true;
                }
            } catch (error) {}
        }
        if (window.elementor && typeof window.elementor.reloadPreview === 'function') {
            try {
                window.elementor.reloadPreview();
                officialRefresh = true;
            } catch (error) {}
        }
        return new Promise(function (resolve) {
            window.setTimeout(function () {
                reloadPreviewIframe().then(function (reloaded) { resolve(officialRefresh || reloaded); });
            }, officialRefresh ? 250 : 0);
        });
    }
    function refreshSavedElementorPreview() {
        return reloadPreviewIframe();
    }
    function waitForEditorRuntime() {
        return new Promise(function (resolve) {
            var deadline = Date.now() + 6000;
            var check = function () {
                var ready = window.$e && typeof window.$e.run === 'function' && window.elementor && typeof window.elementor.getPreviewContainer === 'function';
                if (ready || Date.now() >= deadline) {
                    resolve(Boolean(ready));
                    return;
                }
                window.setTimeout(check, 150);
            };
            check();
        });
    }
    function clearEditorRoots(preserveOwnership) {
        return waitForEditorRuntime().then(function (ready) {
            if (!ready) return false;
            return removeLiveGeneratedRoots(Boolean(preserveOwnership)).then(function (removed) {
                if (!removed) return false;
                // Vision repair carries operation-owned IDs through the
                // preview reload so the server can replace the saved root.
                if (!preserveOwnership) liveGeneratedRootIds = [];
                return true;
            });
        });
    }
    var liveGeneratedRootIds = readOperationRoots();
    var editorSyncConflict = null;

    function getEditorModelId(model) {
        if (!model) return '';
        if (model.id) return String(model.id);
        if (typeof model.get === 'function') {
            var id = model.get('id');
            if (id) return String(id);
        }
        if (model.attributes && model.attributes.id) return String(model.attributes.id);
        return '';
    }
    function getEditorContainerById(elementId) {
        if (!elementId) return null;
        if (window.elementor && typeof window.elementor.getContainer === 'function') {
            try {
                var legacyContainer = window.elementor.getContainer(String(elementId));
                if (legacyContainer) return legacyContainer;
            } catch (error) {}
        }
        try {
            var documentComponent = window.$e && window.$e.components && typeof window.$e.components.get === 'function'
                ? window.$e.components.get('document')
                : null;
            var findContainerById = documentComponent && documentComponent.utils && documentComponent.utils.findContainerById;
            return typeof findContainerById === 'function' ? findContainerById(String(elementId)) : null;
        } catch (error) {
            return null;
        }
    }
    function findLiveGeneratedRoots() {
        if (!liveGeneratedRootIds.length || !window.elementor || typeof window.elementor.getPreviewContainer !== 'function') return [];
        var container = window.elementor.getPreviewContainer();
        if (!container) return [];
        var wanted = liveGeneratedRootIds.slice();
        return getEditorModelChildren(container).filter(function (model) {
            return wanted.indexOf(getEditorModelId(model)) !== -1;
        });
    }
    function removeLiveGeneratedRoots(preserveOwnership) {
        var roots = findLiveGeneratedRoots();
        if (!roots.length) {
            // A preview reload can briefly expose no editor models even though
            // the saved root is still owned by this operation. Do not erase
            // that ownership before the repair request reaches the server.
            if (!preserveOwnership) liveGeneratedRootIds = [];
            return Promise.resolve(true);
        }
        if (!window.$e || typeof window.$e.run !== 'function') return Promise.resolve(false);
        function deleteModel(model, attempt) {
            var modelContainer = getEditorContainerById(getEditorModelId(model));
            if (!modelContainer) return Promise.resolve(false);
            try {
                return Promise.resolve(window.$e.run('document/elements/delete', { container: modelContainer })).then(function () {
                    if (getEditorModelChildren(window.elementor.getPreviewContainer()).indexOf(model) === -1) return true;
                    if (attempt >= 2) return false;
                    return new Promise(function (resolve) { window.setTimeout(resolve, 160); }).then(function () { return deleteModel(model, attempt + 1); });
                }, function () { return false; });
            } catch (error) {
                return Promise.resolve(false);
            }
        }
        return roots.reduce(function (promise, model) {
            return promise.then(function (ok) {
                if (!ok) return false;
                return deleteModel(model, 0);
            });
        }, Promise.resolve(true));
    }
    function reconcileEditorRoots(expectedRootIds, ownedRootIds) {
        if (!window.$e || typeof window.$e.run !== 'function' || !window.elementor || typeof window.elementor.getPreviewContainer !== 'function') return Promise.resolve(false);
        var container = window.elementor.getPreviewContainer();
        var roots = getEditorModelChildren(container);
        if (!container || !roots.length || !Array.isArray(expectedRootIds)) return Promise.resolve(true);
        var expected = expectedRootIds.map(String).filter(Boolean);
        // Server IDs describe the saved document, not every local model. Only
        // explicitly operation-owned roots may be removed; a root created or
        // edited by the user while the provider was waiting is never inferred
        // to be stale from the server response.
        var owned = Array.isArray(ownedRootIds) ? ownedRootIds.map(String).filter(Boolean) : [];
        var stale = roots.filter(function (model) {
            var id = getEditorModelId(model);
            return expected.indexOf(id) === -1 && owned.indexOf(id) !== -1;
        });
        if (!stale.length) return Promise.resolve(true);
        function deleteStaleModel(model, attempt) {
            var modelContainer = getEditorContainerById(getEditorModelId(model));
            if (!modelContainer) return Promise.resolve(false);
            try {
                return Promise.resolve(window.$e.run('document/elements/delete', { container: modelContainer })).then(function () {
                    if (getEditorModelChildren(container).indexOf(model) === -1) return true;
                    if (attempt >= 2) return false;
                    return new Promise(function (resolve) { window.setTimeout(resolve, 160); }).then(function () { return deleteStaleModel(model, attempt + 1); });
                }, function () { return false; });
            } catch (error) {
                return Promise.resolve(false);
            }
        }
        return stale.reduce(function (promise, model) {
            return promise.then(function (ok) {
                if (!ok) return false;
                return deleteStaleModel(model, 0);
            });
        }, Promise.resolve(true));
    }
    function getEditorModelFingerprint(model) {
        if (!model) return '';
        var value = null;
        try {
            value = typeof model.toJSON === 'function' ? model.toJSON() : (model.attributes || model);
            return JSON.stringify(value);
        } catch (error) {
            return getEditorModelId(model);
        }
    }
    function captureEditorRootSnapshot() {
        if (!window.elementor || typeof window.elementor.getPreviewContainer !== 'function') return { ids: [], fingerprints: {} };
        var container = window.elementor.getPreviewContainer();
        var fingerprints = {};
        var parents = {};
        var models = getEditorModelChildren(container);
        var visit = function (model, parentId) {
            var id = getEditorModelId(model);
            if (id) {
                fingerprints[id] = getEditorModelFingerprint(model);
                if (parentId) parents[id] = parentId;
            }
            getEditorModelChildren(model).forEach(function (child) { visit(child, id); });
        };
        var ids = models.map(function (model) {
            var id = getEditorModelId(model);
            visit(model, '');
            return id;
        }).filter(Boolean);
        return { ids: ids, fingerprints: fingerprints, parents: parents };
    }
    function editorModelMatchesSnapshot(model, snapshot) {
        if (!snapshot || !snapshot.fingerprints) return true;
        var id = getEditorModelId(model);
        if (!id || !Object.prototype.hasOwnProperty.call(snapshot.fingerprints, id)) return true;
        return snapshot.fingerprints[id] === getEditorModelFingerprint(model);
    }
    function editorSyncMutationIds(editorSync) {
        var ids = {};
        var add = function (id) { if (id) ids[String(id)] = true; };
        var walk = function (nodes) {
            (Array.isArray(nodes) ? nodes : []).forEach(function (node) {
                if (!node || typeof node !== 'object') return;
                add(node.id);
                walk(node.elements);
            });
        };
        if (!editorSync || typeof editorSync !== 'object') return ids;
        [editorSync.replace_element_id].concat(editorSync.changed_ids || [], editorSync.target_element_ids || [], editorSync.selected_scope_ids || []).forEach(add);
        walk(editorSync.elements);
        (editorSync.patches || []).forEach(function (patch) { add(patch && (patch.element_id || patch.id)); });
        return ids;
    }
    function hasUnsavedEditorChanges(snapshot, editorSync) {
        if (!snapshot || !snapshot.fingerprints || !window.elementor || typeof window.elementor.getPreviewContainer !== 'function') return false;
        var mutationIds = editorSyncMutationIds(editorSync);
        var current = {};
        var visit = function (model) {
            var id = getEditorModelId(model);
            if (id) current[id] = model;
            getEditorModelChildren(model).forEach(visit);
        };
        getEditorModelChildren(window.elementor.getPreviewContainer()).forEach(visit);
        var isMutationRelated = function (id) {
            var seen = {};
            while (id && !seen[id]) {
                seen[id] = true;
                if (mutationIds[id]) return true;
                id = snapshot.parents && snapshot.parents[id] ? snapshot.parents[id] : '';
            }
            return false;
        };
        return Object.keys(snapshot.fingerprints).some(function (id) {
            if (isMutationRelated(id)) return false;
            return !current[id] || snapshot.fingerprints[id] !== getEditorModelFingerprint(current[id]);
        }) || Object.keys(current).some(function (id) {
            return !snapshot.fingerprints[id] && !mutationIds[id];
        });
    }
    function refreshElementorPreviewSafely(snapshot, editorSync) {
        // A full iframe reload hydrates from saved _elementor_data and would
        // discard unrelated local Elementor edits. Realtime sync already
        // painted the changed tree, so keep that canvas when another model is
        // dirty and only reload when the snapshot is still clean.
        if (hasUnsavedEditorChanges(snapshot, editorSync)) return Promise.resolve(true);
        return refreshElementorPreview();
    }
    function syncEditorElements(editorSync, repairDepth, rootSnapshot) {
        if (!editorSync || !Array.isArray(editorSync.elements) || !editorSync.elements.length) return Promise.resolve(false);
        if (Number(repairDepth) > 0 && (!window.$e || typeof window.$e.run !== 'function' || !window.elementor || typeof window.elementor.getPreviewContainer !== 'function')) {
            return waitForEditorRuntime().then(function (ready) {
                return ready ? syncEditorElements(editorSync, repairDepth, rootSnapshot) : false;
            });
        }
        if (!window.$e || typeof window.$e.run !== 'function' || !window.elementor || typeof window.elementor.getPreviewContainer !== 'function') return Promise.resolve(false);
        var container = window.elementor.getPreviewContainer();
        if (!container) return Promise.resolve(false);
        if (editorSync.mode === 'replace') {
            var replaceId = String(editorSync.replace_element_id || editorSync.elements[0].id || '');
            var rootModels = getEditorModelChildren(container);
            var target = rootModels.find(function (model) { return getEditorModelId(model) === replaceId; });
            var targetContainer = getEditorContainerById(replaceId);
            var expectedRootIds = Array.isArray(editorSync.after_top_level_ids) ? editorSync.after_top_level_ids : null;
            if (!target || !targetContainer || !replaceId) return Promise.resolve(false);
            if (!editorModelMatchesSnapshot(target, rootSnapshot)) {
                editorSyncConflict = {
                    type: 'selected_element_changed',
                    element_id: replaceId,
                    message: 'Выбранный элемент изменился в редакторе, пока выполнялся запрос. Локальная правка сохранена; автоматическая замена пропущена.'
                };
                return Promise.resolve(false);
            }
            var targetIndex = rootModels.indexOf(target);
            try {
                return Promise.resolve(window.$e.run('document/elements/delete', { container: targetContainer })).then(function () {
                    return window.$e.run('document/elements/create', {
                        container: container,
                        model: editorSync.elements[0],
                        options: { at: targetIndex >= 0 ? targetIndex : null, clone: false }
                    });
                }).then(function () {
                    var created = getEditorModelChildren(container).some(function (model) {
                        return getEditorModelId(model) === replaceId;
                    });
                    if (!created) return false;
                    var reconcile = expectedRootIds ? reconcileEditorRoots(expectedRootIds, editorSync.operation_owned_root_ids) : Promise.resolve(true);
                    return reconcile.then(function (reconciled) {
                        if (!reconciled) return false;
                        return waitForPreviewPaint().then(function () { return refreshElementorPreviewSafely(rootSnapshot, editorSync); });
                    });
                }, function () { return false; });
            } catch (error) {
                return Promise.resolve(false);
            }
        }
        var elements = editorSync.elements.slice();
        var position = editorSync.position === 'start' ? 'start' : 'end';
        if (position === 'start') elements.reverse();
        var expectedRootIds = Array.isArray(editorSync.before_top_level_ids) ? editorSync.before_top_level_ids : null;
        var prepare = Number(repairDepth) > 0
            ? removeLiveGeneratedRoots().then(function (removed) { return removed ? reconcileEditorRoots(expectedRootIds, editorSync.operation_owned_root_ids) : false; })
            : reconcileEditorRoots(expectedRootIds, editorSync.operation_owned_root_ids);
        return prepare.then(function (ready) {
            if (!ready) return false;
            try {
                return Promise.all(elements.map(function (model) {
                    return Promise.resolve(window.$e.run('document/elements/create', {
                        container: container,
                        model: model,
                        options: { at: position === 'start' ? 0 : null, clone: false }
                    }));
                })).then(function () {
                    liveGeneratedRootIds = liveGeneratedRootIds.concat(elements.map(getEditorModelId).filter(Boolean));
                    return true;
                }, function () { return false; });
            } catch (error) {
                return Promise.resolve(false);
            }
        });
    }
    function getEditorModelChildren(model) {
        var children = model && typeof model.get === 'function' ? model.get('elements') : null;
        if (children && Array.isArray(children.models)) return children.models;
        return Array.isArray(children) ? children : [];
    }
    function collectEditorModelTree(model, result) {
        if (!model) return;
        result.push(model);
        getEditorModelChildren(model).forEach(function (child) { collectEditorModelTree(child, result); });
    }
    function cloneEditorValue(value) {
        if (value === undefined || value === null) return value;
        if (typeof value !== 'object') return value;
        try { return JSON.parse(JSON.stringify(value)); } catch (error) { return value; }
    }
    function setEditorNestedValue(target, parts, value, operation) {
        if (!parts.length) return target;
        var cursor = target;
        for (var index = 0; index < parts.length - 1; index += 1) {
            var key = /^\d+$/.test(parts[index]) ? Number(parts[index]) : parts[index];
            if (!cursor[key] || typeof cursor[key] !== 'object') cursor[key] = {};
            cursor = cursor[key];
        }
        var last = /^\d+$/.test(parts[parts.length - 1]) ? Number(parts[parts.length - 1]) : parts[parts.length - 1];
        if (operation === 'delete') {
            if (Array.isArray(cursor)) cursor.splice(last, 1);
            else delete cursor[last];
        } else {
            cursor[last] = value;
        }
        return target;
    }
    function applyEditorPatch(model, patch) {
        var path = String(patch && patch.path || '');
        if (path.indexOf('settings.') !== 0) return false;
        var settings = model && typeof model.get === 'function' ? model.get('settings') : null;
        if (!settings) return false;
        var settingsModel = settings && typeof settings.set === 'function' ? settings : null;
        var settingsData = settingsModel && typeof settingsModel.toJSON === 'function' ? settingsModel.toJSON() : cloneEditorValue(settings);
        if (!settingsData || typeof settingsData !== 'object') settingsData = {};
        var parts = path.split('.').slice(1);
        if (!parts.length) return false;
        var operation = String(patch.op || 'set');
        var value = patch.value;
        if (operation === 'replace_text') {
            var current = settingsData;
            parts.forEach(function (part) { if (current !== undefined && current !== null) current = current[part]; });
            if (typeof current !== 'string' || !String(patch.search || '')) return false;
            value = current.split(String(patch.search)).join(String(patch.replace || ''));
            operation = 'set';
        }
        setEditorNestedValue(settingsData, parts, value, operation);
        if (settingsModel) {
            var topKey = parts[0];
            settingsModel.set(topKey, settingsData[topKey]);
            return true;
        }
        if (typeof model.set === 'function') {
            model.set('settings', settingsData);
            return true;
        }
        return false;
    }
    function syncEditorPatches(editorSync, rootSnapshot) {
        if (!editorSync || !Array.isArray(editorSync.patches) || !editorSync.patches.length) return Promise.resolve(false);
        var models = [];
        selectedModels().forEach(function (model) { collectEditorModelTree(model, models); });
        var applied = 0;
        editorSync.patches.forEach(function (patch) {
            var id = String(patch && (patch.element_id || patch.id) || '');
            var model = models.find(function (candidate) {
                var modelId = candidate && typeof candidate.get === 'function' ? candidate.get('id') : candidate && candidate.id;
                return String(modelId || '') === id;
            });
            if (model && applyEditorPatch(model, patch)) applied += 1;
        });
        return applied === editorSync.patches.length
            ? waitForPreviewPaint()
                .then(function () { return refreshElementorPreviewSafely(rootSnapshot, editorSync); })
                .then(function (refreshed) { return refreshed === true; })
            : Promise.resolve(false);
    }
    var visionCapturePromise = null;
    function loadVisionCapture() {
        if (typeof window.html2canvas === 'function') return Promise.resolve(window.html2canvas);
        if (!config.vision || !config.vision.captureScript) return Promise.reject(new Error('В редакторе недоступен модуль screenshot для AI Vision.'));
        if (visionCapturePromise) return visionCapturePromise;
        visionCapturePromise = new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            script.src = config.vision.captureScript;
            script.async = true;
            script.onload = function () {
                typeof window.html2canvas === 'function' ? resolve(window.html2canvas) : reject(new Error('Модуль screenshot загрузился без html2canvas.'));
            };
            script.onerror = function () { reject(new Error('Не удалось загрузить модуль screenshot для AI Vision.')); };
            document.head.appendChild(script);
        });
        return visionCapturePromise;
    }
    function capturePreviewScreenshot(targetElementIds, reviewScope) {
        var iframe = document.querySelector('#elementor-preview-iframe');
        if (!iframe || !iframe.contentDocument) return Promise.reject(new Error('Текущий Elementor preview недоступен для screenshot.'));
        var doc = iframe.contentDocument;
        var width = doc.documentElement.clientWidth || iframe.clientWidth || 1280;
        var height = iframe.clientHeight || doc.documentElement.clientHeight || 900;
        height = Math.max(320, Math.min(height, 4000));
        var stable = Promise.resolve();
        if (doc.fonts && doc.fonts.ready) stable = stable.then(function () { return doc.fonts.ready; });
        stable = stable.then(function () {
            var pending = Array.prototype.slice.call(doc.images || []).filter(function (image) { return !image.complete; }).map(function (image) {
                return new Promise(function (resolve) {
                    var settled = false;
                    var settle = function () {
                        if (settled) return;
                        settled = true;
                        resolve();
                    };
                    image.addEventListener('load', settle, { once: true });
                    image.addEventListener('error', settle, { once: true });
                    window.setTimeout(settle, 2500);
                });
            });
            return Promise.all(pending);
        });
        return stable.then(function () { return loadVisionCapture(); }).then(function (capture) {
            var hidden = [];
            var editorOnly = '.elementor-add-section,.elementor-add-new-section,.elementor-empty-view,.elementor-widget-empty,.elementor-editor-element-settings,.elementor-editor-section-settings,.elementor-editor-container-settings,.elementor-editor-column-settings,.elementor-editor-widget-settings,.elementor-editor-element-overlay,.elementor-editor-elementor-panel,.elementor-controls,.elementor-control-dynamic-switcher';
            doc.querySelectorAll(editorOnly).forEach(function (element) {
                hidden.push({ element: element, display: element.style.display });
                element.style.display = 'none';
            });
            var target = findPreviewTarget(doc, targetElementIds);
            if (Array.isArray(targetElementIds) && targetElementIds.length && !target) {
                hidden.forEach(function (item) { item.element.style.display = item.display; });
                throw new Error('Новый блок не найден в preview Elementor для Vision screenshot.');
            }
            var captureTarget = target || doc.body || doc.documentElement;
            var targetRect = captureTarget.getBoundingClientRect();
            var documentElement = doc.documentElement;
            var documentBody = doc.body || documentElement;
            var targetScrollWidth = target
                ? Math.max(target.scrollWidth || 0, targetRect.width)
                : Math.max(documentElement.scrollWidth || 0, documentBody.scrollWidth || 0, width);
            var targetScrollHeight = target
                ? Math.max(target.scrollHeight || 0, targetRect.height)
                : Math.max(documentElement.scrollHeight || 0, documentBody.scrollHeight || 0, height);
            var captureWidth = Math.ceil(targetScrollWidth);
            var captureHeight = Math.ceil(targetScrollHeight);
            if (captureWidth < 1 || captureHeight < 1) {
                hidden.forEach(function (item) { item.element.style.display = item.display; });
                throw new Error('Сгенерированный блок имеет нулевой размер в preview Elementor.');
            }
            captureWidth = Math.max(320, Math.min(captureWidth, 4000));
            captureHeight = Math.max(320, Math.min(captureHeight, 4000));
            var pageView = doc.defaultView || window;
            var captureMeta = {
                capture_scope: target ? 'element' : 'document',
                capture_complete: captureWidth >= Math.ceil(targetScrollWidth) && captureHeight >= Math.ceil(targetScrollHeight),
                capture_width: captureWidth,
                capture_height: captureHeight,
                target_scroll_width: Math.ceil(targetScrollWidth),
                target_scroll_height: Math.ceil(targetScrollHeight),
                page_scroll_x: Number(pageView.scrollX || 0),
                page_scroll_y: Number(pageView.scrollY || 0),
                target_rect: {
                    x: Number(targetRect.left || 0),
                    y: Number(targetRect.top || 0),
                    width: Number(targetRect.width || 0),
                    height: Number(targetRect.height || 0),
                    top: Number(targetRect.top || 0),
                    bottom: Number(targetRect.bottom || 0)
                }
            };
            var targetBackground = doc.defaultView.getComputedStyle(captureTarget).backgroundColor;
            if (!targetBackground || targetBackground === 'rgba(0, 0, 0, 0)') targetBackground = doc.defaultView.getComputedStyle(doc.body || doc.documentElement).backgroundColor;
            if (!targetBackground || targetBackground === 'rgba(0, 0, 0, 0)') targetBackground = '#ffffff';
            var restore = function () {
                hidden.forEach(function (item) { item.element.style.display = item.display; });
            };
            var captureOptions = {
                backgroundColor: targetBackground,
                useCORS: true,
                imageTimeout: 2500,
                logging: false,
                scale: 1,
                width: captureWidth,
                height: captureHeight,
                windowWidth: Math.max(width, captureWidth),
                windowHeight: Math.max(height, captureHeight),
                x: 0,
                y: 0
            };
            var captureTimeout = new Promise(function (_, reject) {
                window.setTimeout(function () { reject(new Error('Screenshot preview capture exceeded 12 seconds.')); }, 12000);
            });
            return Promise.race([capture(captureTarget, captureOptions), captureTimeout]).then(function (canvas) {
                restore();
                var imageBase64 = canvas.toDataURL('image/jpeg', 0.72);
                if (imageBase64.length > 5600000) throw new Error('Screenshot preview превышает допустимый размер AI Vision.');
                return { image_base64: imageBase64, mime_type: 'image/jpeg', viewport: captureWidth + 'x' + captureHeight, render_context: getPreviewRenderContext(targetElementIds, reviewScope, captureMeta) };
            }, function (error) {
                restore();
                throw error;
            });
        });
    }
    function hidePreviewLoading() {
        var loading = document.querySelector('#elementor-preview-loading');
        if (!loading) return;
        loading.style.display = 'none';
        loading.setAttribute('aria-hidden', 'true');
    }
    function waitForPreviewRefresh(refreshPromise, minimumWidgetCount) {
        return Promise.resolve(refreshPromise).then(function (refreshed) {
            if (!refreshed) throw new Error('Не удалось обновить текущий preview Elementor.');
            minimumWidgetCount = Number(minimumWidgetCount) || 1;
            return new Promise(function (resolve, reject) {
                var started = Date.now();
                var check = function () {
                    var iframe = getPreviewIframe();
                    var doc = iframe && iframe.contentDocument;
                    if (doc && doc.querySelectorAll('.elementor-widget').length >= minimumWidgetCount) {
                        hidePreviewLoading();
                        resolve(true);
                        return;
                    }
                    if (Date.now() - started >= 8000) {
                        reject(new Error('После обновления preview в canvas не найдено ни одного Elementor widget.'));
                        return;
                    }
                    window.setTimeout(check, 250);
                };
                check();
            });
        });
    }
    function waitForPreviewPaint() {
        return new Promise(function (resolve) {
            var settle = function () { window.setTimeout(resolve, 450); };
            if (typeof window.requestAnimationFrame === 'function') {
                window.requestAnimationFrame(function () {
                    window.requestAnimationFrame(settle);
                });
                return;
            }
            settle();
        });
    }
    function visionReviewScope(editorSync) {
        return editorSync && ['patch', 'replace'].indexOf(editorSync.mode) !== -1 ? 'selected_patch' : 'generated_block';
    }
    function isTargetedEditorSync(editorSync) {
        return Boolean(editorSync && ['patch', 'replace'].indexOf(editorSync.mode) !== -1);
    }
    function getVisionGeneratedJson(editorSync) {
        if (!editorSync || !Array.isArray(editorSync.elements)) return '';
        try {
            return JSON.stringify({ mode: editorSync.mode || 'insert', elements: editorSync.elements.slice(0, 8) }).slice(0, 12000);
        } catch (error) {
            return '';
        }
    }
    function buildVisionOperationContext(body, requestContext, editorSync) {
        var ledger = body && body.diagnostics && body.diagnostics.operation_ledger ? body.diagnostics.operation_ledger : {};
        var roots = editorSync && Array.isArray(editorSync.operation_owned_root_ids)
            ? editorSync.operation_owned_root_ids.slice(0, 12)
            : (Array.isArray(ledger.root_ids) ? ledger.root_ids.slice(0, 12) : liveGeneratedRootIds.slice(0, 12));
        return {
            operation_id: String(body && body.operation_id || ledger.operation_id || ''),
            operation_identity: String(requestContext && requestContext.operation_identity || ledger.operation_identity || readOperationIdentity()).slice(0, 120),
            operation_revision: Number(ledger.revision || 0),
            operation_saved_hash: String(ledger.saved_hash || '').slice(0, 128),
            operation_target_fingerprint: String(ledger.target_fingerprint || '').slice(0, 128),
            operation_root_ids: roots
        };
    }
    function withVisionOperationContext(renderContext, operationContext) {
        var context = renderContext && typeof renderContext === 'object' ? Object.assign({}, renderContext) : {};
        if (!operationContext || !operationContext.operation_id) return context;
        Object.keys(operationContext).forEach(function (key) {
            context[key] = operationContext[key];
        });
        return context;
    }
    function requestVisionReview(snapshotId, captureError, brief, editorSync, operationContext) {
        var reviewScope = visionReviewScope(editorSync);
        return postVisionReview({
            post_id: Number(config.postId) || 0,
            rollback_snapshot_id: snapshotId,
            vision_capture_error: captureError,
            brief: brief || '',
            render_context: withVisionOperationContext(getPreviewRenderContext(getVisionSyncIds(editorSync), reviewScope), operationContext),
            generated_json: getVisionGeneratedJson(editorSync)
        });
    }
    function postVisionReview(payload) {
        return fetch(config.vision.reviewEndpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
            body: JSON.stringify(payload)
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (body) {
                if (!response.ok) {
                    if (body.gate && body.gate.quality_failed) return body;
                    var detail = body.error || body.message || ('HTTP ' + response.status);
                    if (body.code) detail += ' [' + body.code + ']';
                    var visionDetails = body.details || {};
                    if (visionDetails.analysis_error) detail += ': ' + visionDetails.analysis_error;
                    if (visionDetails.analysis_code) detail += ' [' + visionDetails.analysis_code + ']';
                    if (visionDetails.provider) detail += ' (provider: ' + visionDetails.provider + ')';
                    if (visionDetails.provider_http_status) detail += ' (HTTP provider: ' + visionDetails.provider_http_status + ')';
                    if (visionDetails.provider_message) detail += ': ' + visionDetails.provider_message;
                    if (body.report) {
                        if (body.report.vision_score !== undefined) detail += ' (score: ' + body.report.vision_score + ')';
                        if (body.report.summary) detail += ': ' + body.report.summary;
                        if (Array.isArray(body.report.findings) && body.report.findings.length) detail += ' ' + body.report.findings.slice(0, 3).map(function (finding) { return (finding.severity || 'info') + ': ' + (finding.message || 'наблюдение'); }).join('; ');
                    }
                    if (visionDetails.rollback) detail += ' (rollback: ' + (visionDetails.rollback.ok ? 'выполнен' : 'не выполнен') + ')';
                    throw new Error(detail);
                }
                return body;
            });
        });
    }
    function reviewPendingOperation(operation, brief) {
        operation = operation && typeof operation === 'object' ? operation : {};
        var operationId = String(operation.operation_id || '');
        var roots = Array.isArray(operation.root_ids) ? operation.root_ids.map(String).filter(Boolean).slice(0, 12) : [];
        if (!operationId || !roots.length) return Promise.reject(new Error('Для pending operation не найден сохраненный operation_id или root.'));
        var editorSync = {
            mode: 'insert',
            elements: roots.map(function (id) { return { id: id }; }),
            operation_owned_root_ids: roots,
            target_element_ids: roots
        };
        var requestContext = {
            operation_identity: String(operation.operation_identity || readOperationIdentity()).slice(0, 120),
            operation_owned_root_ids: roots,
            editor_root_snapshot: captureEditorRootSnapshot()
        };
        var body = { operation_id: operationId, diagnostics: { operation_ledger: operation } };
        var operationContext = buildVisionOperationContext(body, requestContext, editorSync);
        liveGeneratedRootIds = roots.slice();
        rememberOperationRoots(roots);
        return runVisionReview('', 1, true, brief || '', editorSync, requestContext.editor_root_snapshot, operationContext).then(function (review) {
            return reconcileDesignOperation(body, requestContext, editorSync, review).then(function (reconciled) {
                if (reconciled && reconciled.operation) {
                    addMessage('assistant', 'Актуальный preview и Vision привязаны к существующей операции. Состояние журнала: ' + String(reconciled.operation.current_state || 'written') + '.');
                }
                return review;
            });
        });
    }
    function reconcileDesignOperation(body, requestContext, editorSync, review) {
        var operationId = String(body && body.operation_id || '');
        var ledger = body && body.diagnostics && body.diagnostics.operation_ledger ? body.diagnostics.operation_ledger : {};
        var endpoint = config.reconcileEndpoint || ((window.wpApiSettings && window.wpApiSettings.root) ? window.wpApiSettings.root + 'ai-executor/v1/design-operations/reconcile' : '/wp-json/ai-executor/v1/design-operations/reconcile');
        // Scoped patches and legacy repairs do not have a durable operation
        // record. Do not send them to the design-operation reconcile endpoint;
        // the absence of a ledger entry is a supported path, not a failure.
        if (!operationId || !endpoint || !ledger || String(ledger.operation_id || '') !== operationId) return Promise.resolve({ skipped: true, reason: 'no_durable_ledger' });
        var report = review && review.report ? review.report : {};
        var state = report.report_id ? 'completed' : (review && review.vision_unavailable ? 'written' : 'rendered');
        var roots = editorSync && Array.isArray(editorSync.operation_owned_root_ids) ? editorSync.operation_owned_root_ids.slice(0, 12) : liveGeneratedRootIds.slice(0, 12);
        var evidence = JSON.stringify({ operation_id: operationId, roots: roots, viewport: window.innerWidth || 0, state: state }).slice(0, 4000);
        return fetch(endpoint, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
            body: JSON.stringify({
                post_id: Number(config.postId) || 0,
                operation_id: operationId,
                operation_identity: requestContext && requestContext.operation_identity ? requestContext.operation_identity : readOperationIdentity(),
                revision: Number(ledger.revision || 0),
                state: state,
                evidence_source: state === 'written' ? 'server_readback' : 'preview',
                evidence_hash: evidence,
                vision_report_id: report.report_id || '',
                root_ids: roots
            })
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (result) {
                if (!response.ok) throw new Error(result.message || result.code || ('HTTP ' + response.status));
                return result;
            });
        });
    }
    function runVisionReview(snapshotId, minimumWidgetCount, alreadySynced, brief, editorSync, rootSnapshot, operationContext) {
        var reviewScope = visionReviewScope(editorSync);
        var visionSyncIds = getVisionSyncIds(editorSync);
        addMessage('assistant', 'Выполняется: Обновляю preview и проверяю результат через AI Vision.');
        return waitForPreviewRefresh(alreadySynced ? Promise.resolve(true) : refreshElementorPreviewSafely(rootSnapshot, editorSync), minimumWidgetCount).then(function () {
            return focusEditorSync(editorSync).then(function (focused) {
                if (focused) return true;
                return refreshElementorPreviewSafely(rootSnapshot, editorSync).then(function (refreshed) {
                    if (!refreshed) throw new Error('Новый блок не найден в preview Elementor после realtime-вставки.');
                    return focusEditorSync(editorSync).then(function (refocused) {
                        if (!refocused) throw new Error('Новый блок не найден в preview Elementor после обновления.');
                        return true;
                    });
                });
            }).then(function () { return waitForPreviewPaint(); }).then(function () { return capturePreviewScreenshot(visionSyncIds, reviewScope); }).then(function (capture) {
                return postVisionReview({
                    post_id: Number(config.postId) || 0,
                    rollback_snapshot_id: snapshotId,
                    image_base64: capture.image_base64,
                    mime_type: capture.mime_type,
                    viewport: capture.viewport,
                    brief: brief || '',
                    render_context: withVisionOperationContext(capture.render_context, operationContext),
                    generated_json: getVisionGeneratedJson(editorSync)
                });
            }, function (error) {
                return requestVisionReview(snapshotId, error.message, brief, editorSync, operationContext);
            });
        });
    }
    function addActionControls(write) {
        if (!write || !write.rollback_snapshot_id || !config.undoEndpoint) return;
        var row = document.createElement('div');
        row.className = 'wpae-llm-action-row';
        var label = document.createElement('span');
        label.textContent = 'Изменения применены';
        var undo = document.createElement('button');
        undo.type = 'button';
        undo.className = 'wpae-llm-icon-button wpae-llm-undo';
        addIcon(undo, 'eicon-undo', 'Отменить');
        undo.addEventListener('click', function () {
            undo.disabled = true;
            setButtonLabel(undo, 'Отмена…');
            fetch(config.undoEndpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
                body: JSON.stringify({ post_id: Number(config.postId) || 0, rollback_snapshot_id: write.rollback_snapshot_id })
            }).then(function (response) {
                return response.json().catch(function () { return {}; }).then(function (body) {
                    if (!response.ok || !body.ok) throw new Error(body.error || ('HTTP ' + response.status));
                    addMessage('assistant', 'Последнее изменение отменено.');
                    row.remove();
                    window.location.reload();
                });
            }).catch(function (error) {
                undo.disabled = false;
                setButtonLabel(undo, 'Отменить');
                addMessage('assistant', 'Не удалось отменить изменение: ' + error.message);
            });
        });
        row.appendChild(label);
        row.appendChild(undo);
        messages.appendChild(row);
        messages.scrollTop = messages.scrollHeight;
    }
    function describeVisionReview(review) {
        var report = review.report || {};
        var gate = review.gate || {};
        var summary = report.summary ? ' ' + report.summary : '';
        var findings = Array.isArray(report.findings) ? report.findings.slice(0, 3).map(function (finding) {
            return (finding.severity || 'info') + ': ' + (finding.message || 'наблюдение');
        }).join('; ') : '';
        var confidence = report.confidence === undefined ? '' : ' confidence ' + Math.round(Number(report.confidence) * 100) + '%.';
        var warning = gate.quality_warning || gate.score_below_floor ? ' Требуется дополнительная визуальная проверка.' : '';
        return 'AI Vision: score ' + (report.vision_score === undefined ? 'n/a' : report.vision_score) + '.' + confidence + warning + summary + (findings ? ' ' + findings : '');
    }
    function rollbackVisionFailure(snapshotId) {
        if (!snapshotId || !config.undoEndpoint) return Promise.resolve({ ok: false, error: 'Rollback endpoint or snapshot is unavailable.' });
        return fetch(config.undoEndpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
            body: JSON.stringify({ post_id: Number(config.postId) || 0, rollback_snapshot_id: snapshotId })
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (body) {
                return {
                    ok: !!response.ok && !!body.ok,
                    status: response.status,
                    error: body.error || body.message || ('HTTP ' + response.status)
                };
            });
        }, function (error) {
            return { ok: false, status: 0, error: error.message || 'Network error.' };
        });
    }
    var requestInFlight = false;
    function request(message, retried, options) {
        options = options || {};
        if (options.retryCurrentOperation && !readOperationIdentity()) {
            addMessage('assistant', 'Не найден идентификатор текущей операции; повтор доставки остановлен, чтобы не создать дубликат.');
            status.textContent = strings.error;
            return Promise.resolve(false);
        }
        if (requestInFlight) return Promise.resolve(false);
        requestInFlight = true;
        editorSyncConflict = null;
        var repairDepth = Number(options.repairDepth) || 0;
        var operationIdentity = readOperationIdentity();
        if (repairDepth === 0) {
            if (!options.retryCurrentOperation) {
                liveGeneratedRootIds = [];
                clearOperationRoots();
                operationIdentity = newOperationIdentity();
                rememberOperationIdentity(operationIdentity);
            }
            // Remember the original brief so the regenerate button can replay
            // it even after a full editor reload cleared the chat history.
            try {
                window.sessionStorage.setItem(lastBriefKey, JSON.stringify({ message: String(message).slice(0, 4000), createdAt: Date.now() }));
            } catch (error) {}
        }
        if (!operationIdentity) {
            operationIdentity = newOperationIdentity();
            rememberOperationIdentity(operationIdentity);
        }
        var originalBrief = options.originalBrief || message;
        var beforeWidgetCount = getPreviewWidgetCount();
        var history = Array.prototype.slice.call(messages.querySelectorAll('.wpae-llm-message')).slice(-12).map(function (item) {
            return { role: item.classList.contains('wpae-llm-message--user') ? 'user' : 'assistant', content: messageContent(item) };
        });
        status.textContent = strings.sending;
        send.disabled = true;
        resetPipelinePhases();
        setPipelinePhase('parse', 'active');
        var progressMessages = [
            'Запрос принят. Проверяю текущий контекст Elementor.',
            'Отправляю задачу настроенному LLM-провайдеру.',
            'Ожидаю структурированный Elementor JSON и результат проверки.'
        ];
        var progressIndex = 0;
        addMessage('assistant', 'Выполняется: ' + progressMessages[progressIndex++]);
        var progressTimer = window.setInterval(function () {
            if (progressIndex < progressMessages.length) {
                addMessage('assistant', 'Выполняется: ' + progressMessages[progressIndex++]);
            }
        }, 900);
        var requestContext = {
            post_id: config.postId,
            original_message: String(originalBrief || message || '').slice(0, 4000),
            selected_elements: options.selectedElements || selectedElements(),
            background_image_urls: getPreviewBackgroundImageUrls(),
            editor_root_snapshot: captureEditorRootSnapshot(),
            operation_owned_root_ids: liveGeneratedRootIds.slice(0, 12),
            operation_identity: operationIdentity
        };
        if (options.retryCurrentOperation) requestContext.retry_current_operation = true;
        if (options.visionRepair) requestContext.vision_repair = true;
        if (options.visionRegenerate) requestContext.vision_regenerate = true;
        if (options.visionFindings) requestContext.vision_findings = String(options.visionFindings).slice(0, 3600);
        var editorSyncDataForReview = null;
        var requestController = typeof window.AbortController === 'function' ? new window.AbortController() : null;
        var requestTimer = requestController ? window.setTimeout(function () {
            requestController.abort();
        }, Number(config.requestTimeoutMs) || 180000) : null;
        var requestOptions = {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
            body: JSON.stringify({ message: message, history: history, context: requestContext })
        };
        if (requestController) requestOptions.signal = requestController.signal;
        return fetch(config.endpoint, requestOptions).then(function (response) {
            if (requestTimer) window.clearTimeout(requestTimer);
            return response.json().catch(function () { return {}; }).then(function (body) {
                if (!response.ok) {
                    var detail = body.message || body.code || ('HTTP ' + response.status);
                    var errorData = body.data || {};
                    var errorCode = body.code || errorData.code || '';
                    var diagnostics = body.details || errorData.details || {};
                    var providerDiagnostics = errorData.diagnostics || body.diagnostics || (diagnostics && typeof diagnostics === 'object' ? diagnostics.diagnostics : null);
                    if (typeof errorData.details === 'string' && errorData.details !== detail) detail += ': ' + errorData.details;
                    if (typeof diagnostics === 'string' && diagnostics !== detail && diagnostics !== errorData.details) detail += ': ' + diagnostics;
                    if (body.details && body.details.error) detail += ': ' + body.details.error;
                    if (diagnostics.error && (!body.details || !body.details.error)) detail += ': ' + diagnostics.error;
                    if (diagnostics.details && diagnostics.details.error) detail += ': ' + diagnostics.details.error;
                    if (diagnostics.exception) detail += ': ' + diagnostics.exception;
                    if (diagnostics.update_error) detail += ': ' + diagnostics.update_error;
                    if (Array.isArray(diagnostics.blocking_errors) && diagnostics.blocking_errors.length) detail += ': ' + diagnostics.blocking_errors.join('; ');
                    if (diagnostics.received_action || diagnostics.received_post_id) detail += ' (получено: action=' + (diagnostics.received_action || 'не указано') + ', post_id=' + (diagnostics.received_post_id || 'не указан') + ')';
                    if (diagnostics.model_response && diagnostics.model_response.response_keys) detail += ' (ключи ответа: ' + diagnostics.model_response.response_keys.join(', ') + ')';
                    var failedChecks = diagnostics.failed_checks || (diagnostics.details && diagnostics.details.failed_checks) || (diagnostics.details && diagnostics.details.transaction && diagnostics.details.transaction.failed_checks) || [];
                    if (Array.isArray(failedChecks) && failedChecks.length) detail += ' (непройденные проверки: ' + failedChecks.join(', ') + ')';
                    if (Array.isArray(diagnostics.failure_details) && diagnostics.failure_details.length) detail += ' ' + diagnostics.failure_details.map(function (item) { return (item.code || 'check') + ': ' + (item.message || 'проверка не пройдена'); }).join('; ');
                    if (Array.isArray(diagnostics.steps) && diagnostics.steps.length) {
                        var stepError = new Error(detail);
                        stepError.wpaeCode = errorCode;
                        stepError.httpStatus = response.status;
                        stepError.steps = diagnostics.steps;
                        // Preserve the complete sanitized REST diagnostics for
                        // semantic/contract failures. Provider-only metadata
                        // hides the actual failed plan and makes live repair
                        // needlessly speculative.
                        stepError.diagnostics = diagnostics && typeof diagnostics === 'object' ? diagnostics : providerDiagnostics;
                        throw stepError;
                    }
                    if (errorData.provider_message) detail += ': ' + errorData.provider_message;
                    if (errorData.provider_error_code) detail += ' [код провайдера: ' + errorData.provider_error_code + ']';
                    if (diagnostics.provider_message && diagnostics.provider_message !== errorData.provider_message) detail += ': ' + diagnostics.provider_message;
                    if (diagnostics.provider_error_code && diagnostics.provider_error_code !== errorData.provider_error_code) detail += ' [код провайдера: ' + diagnostics.provider_error_code + ']';
                    if (diagnostics.finish_reason) detail += ' (finish_reason: ' + diagnostics.finish_reason + ')';
                    if (diagnostics.status && !diagnostics.error) detail += ' (HTTP ' + diagnostics.status + ')';
                    var requestError = new Error(detail);
                    requestError.wpaeCode = errorCode;
                    requestError.httpStatus = response.status;
                    requestError.providerStatus = Number(errorData.provider_status || diagnostics.provider_status || errorData.status || diagnostics.status || 0);
                    requestError.retryAfter = Number(errorData.retry_after || diagnostics.retry_after || 0);
                    requestError.diagnostics = providerDiagnostics;
                    requestError.pendingOperation = (diagnostics && diagnostics.operation)
                        || (diagnostics && diagnostics.details && diagnostics.details.operation)
                        || (body && body.details && body.details.operation)
                        || (errorData && errorData.operation)
                        || null;
                    throw requestError;
                }
                return body;
            });
        }, function (error) {
            if (requestTimer) window.clearTimeout(requestTimer);
            if (error && error.name === 'AbortError') {
                var timeoutError = new Error('Время ожидания ответа сервера истекло. Результат операции пока неизвестен. Обновите редактор, чтобы проверить сохранённую страницу перед новым запросом.');
                timeoutError.wpaeCode = 'wpae_llm_request_status_unknown';
                timeoutError.httpStatus = 504;
                timeoutError.diagnostics = {
                    schema: 'wpae-llm-provider-diagnostics-v1',
                    source: 'browser',
                    attempt: retried ? 'retry' : 'primary',
                    provider: String(config.providerLabel || ''),
                    model: String(config.model || ''),
                    endpoint: 'ai-executor/v1/llm/chat',
                    response: { http_status: 0, body_type: 'none', top_level_keys: [] },
                    transport: { error_code: 'browser_timeout', error_message: timeoutError.message }
                };
                throw timeoutError;
            }
            throw error;
        }).then(function (body) {
            window.clearInterval(progressTimer);
            if (Array.isArray(body.steps) && body.steps.length) {
                updatePipelineFromSteps(body.steps);
                addStepMessages(body.steps);
            }
            if (body.diagnostics && typeof body.diagnostics === 'object') addDiagnosticJsonMessage(body.diagnostics);
            var visionPromise = Promise.resolve(null);
                if (body.ok && body.write && Number(body.write.post_id) === Number(config.postId)) {
                var expectedWidgetCount = beforeWidgetCount + Number(body.write.inserted_widget_count || body.write.inserted_count || 0);
                var editorSyncedState = false;
                var editorSyncData = body.write.editor_sync;
                editorSyncDataForReview = editorSyncData;
                var operationRootIds = editorSyncData && Array.isArray(editorSyncData.operation_owned_root_ids)
                    ? editorSyncData.operation_owned_root_ids
                    : getEditorSyncIds(editorSyncData);
                if (operationRootIds.length) {
                    liveGeneratedRootIds = Array.from(new Set(liveGeneratedRootIds.concat(operationRootIds.map(String).filter(Boolean)))).slice(0, 12);
                    rememberOperationRoots(liveGeneratedRootIds);
                }
                addGeneratedJsonSpoiler(editorSyncData && Array.isArray(editorSyncData.elements) ? editorSyncData.elements : []);
                var editorSyncPromise = body.write.editor_sync && body.write.editor_sync.mode === 'patch'
                    ? syncEditorPatches(body.write.editor_sync, requestContext.editor_root_snapshot)
                    : syncEditorElements(body.write.editor_sync, repairDepth, requestContext.editor_root_snapshot);
                visionPromise = Promise.resolve(editorSyncPromise).then(function (editorSynced) {
                    editorSyncedState = editorSynced;
                    if (editorSynced) {
                        setPipelinePhase('write', 'done');
                        setPipelinePhase('render', 'active');
                        var syncMessage = isTargetedEditorSync(editorSyncData)
                            ? 'Выбранный элемент обновлен в открытом Elementor без перезагрузки редактора.'
                            : 'Новые элементы добавлены в открытом Elementor без перезагрузки редактора.';
                        var paintPromise = isTargetedEditorSync(editorSyncData)
                            ? waitForPreviewPaint()
                            : waitForPreviewRefresh(Promise.resolve(true), expectedWidgetCount);
                        return paintPromise.then(function () {
                            setPipelinePhase('render', 'done');
                            return focusEditorSync(editorSyncData);
                        }).then(function () {
                            addMessage('assistant', syncMessage);
                            return true;
                        }).catch(function () {
                            return waitForPreviewRefresh(refreshElementorPreviewSafely(requestContext.editor_root_snapshot, editorSyncData), expectedWidgetCount).then(function () {
                                setPipelinePhase('render', 'done');
                                return focusEditorSync(editorSyncData);
                            }).then(function () {
                                addMessage('assistant', isTargetedEditorSync(editorSyncData) ? 'Canvas не подтвердил realtime-правку, preview обновлен из сохраненных данных.' : 'Canvas не подтвердил realtime-вставку, preview обновлен из сохраненных данных.');
                                return false;
                            });
                        });
                    }
                    if (editorSyncConflict) {
                        addMessage('assistant', editorSyncConflict.message);
                        return false;
                    }
                    return waitForPreviewRefresh(refreshElementorPreviewSafely(requestContext.editor_root_snapshot, editorSyncData), expectedWidgetCount).then(function () {
                        setPipelinePhase('render', 'done');
                        return focusEditorSync(editorSyncData);
                    }).then(function () {
                        addMessage('assistant', isTargetedEditorSync(editorSyncData) ? 'Предпросмотр измененного элемента обновлен из сохраненных данных.' : 'Предпросмотр Elementor обновлен из сохраненных данных.');
                        return false;
                    }).catch(function (error) {
                        addMessage('assistant', 'Данные сохранены, но preview Elementor не обновился: ' + error.message);
                        return false;
                    });
                }).then(function () {
                    if (!options.skipVision && config.vision && config.vision.ready && body.write.rollback_snapshot_id) {
                        return runVisionReview(body.write.rollback_snapshot_id, expectedWidgetCount, editorSyncedState, originalBrief, editorSyncData, requestContext.editor_root_snapshot, buildVisionOperationContext(body, requestContext, editorSyncData)).catch(function (error) {
                            return { vision_unavailable: true, error: error && error.message ? error.message : 'Проверка Vision недоступна.' };
                        });
                    }
                    return true;
                });
            }
            return visionPromise.then(function (review) {
				return reconcileDesignOperation(body, requestContext, editorSyncDataForReview, review).catch(function (error) {
					addMessage('assistant', 'Ledger reconcile требует read-back: ' + error.message);
					return null;
				}).then(function () { return review; });
            }).then(function (review) {
                var reviewTargetedPatch = isTargetedEditorSync(editorSyncDataForReview);
                if (review && review.vision_unavailable) {
                    addMessage('assistant', (reviewTargetedPatch ? 'AI Vision временно недоступен; точечная правка сохранена и требует ручной проверки: ' : 'AI Vision временно недоступен; новая генерация сохранена и требует ручной проверки: ') + review.error);
                }
                if (review && review.gate && review.gate.quality_failed && (reviewTargetedPatch ? !review.gate.advisory : true)) {
                    var targetedPatch = reviewTargetedPatch;
                    addMessage('assistant', describeVisionReview(review) + (targetedPatch ? ' Передаю анализ Vision агенту отдельным дополнительным промтом для повторной правки выбранного дерева.' : ' Передаю анализ Vision агенту отдельным дополнительным промтом для полной регенерации дизайна.'));
                    if (repairDepth >= 2) {
                        return rollbackVisionFailure(body.write.rollback_snapshot_id).then(function (rollback) {
                            if (!rollback.ok) throw new Error('Не удалось откатить неудачную версию: ' + rollback.error);
                            addMessage('assistant', targetedPatch ? 'Vision повторно обнаружил проблемы после двух точечных repair-проходов. Последняя правка отменена; перезагружаю Elementor из сохраненного состояния.' : 'Vision повторно обнаружил проблемы после двух bounded repair-проходов. Последняя неудачная версия отменена; перезагружаю Elementor из сохраненного состояния.');
                            status.textContent = strings.error;
                            return clearEditorRoots().then(function () {
                                return refreshSavedElementorPreview();
                            }).catch(function () { return false; }).then(function () {
                                window.setTimeout(function () { window.location.reload(); }, 250);
                                return true;
                            });
                        });
                    }
                    addMessage('assistant', targetedPatch ? 'Выполняется: Откатываю неудачную точечную правку и повторяю ее в выбранном дереве.' : 'Выполняется: Откатываю неудачную версию и заново генерирую полноценный дизайн по исходному запросу.');
                    return rollbackVisionFailure(body.write.rollback_snapshot_id).then(function (rollback) {
                        if (!rollback.ok) throw new Error('Не удалось откатить неудачную версию перед повторной генерацией: ' + rollback.error);
                        var repairOptions = { visionRepair: true, visionRegenerate: !targetedPatch, repairDepth: repairDepth + 1, originalBrief: originalBrief, visionFindings: buildVisionRepairMessage(review, originalBrief, targetedPatch), ownedRootIds: liveGeneratedRootIds.slice(0, 12), selectedElements: requestContext.selected_elements };
                        if (!scheduleVisionRepairAfterReload(originalBrief, repairOptions)) throw new Error('Не удалось сохранить Vision repair перед перезагрузкой Elementor.');
                        return true;
                    });
                }
                if (review && review.rolled_back) {
                    addMessage('assistant', 'AI Vision обнаружил критические дефекты. Изменения откатены.');
                    refreshElementorPreview();
                    status.textContent = strings.error;
                    return;
                }
                if (review && review.report) addMessage('assistant', describeVisionReview(review));
                if (review && review.report) setPipelinePhase('review', 'done');
                addActionControls(body.write);
                addMessage('assistant', body.message || strings.error);
                if (!review) setPipelinePhase('review', 'skipped');
                status.textContent = strings.done;
            });
        }).catch(function (error) {
            window.clearInterval(progressTimer);
            if (Array.isArray(error.steps) && error.steps.length) addStepMessages(error.steps);
            if (Array.isArray(error.steps) && error.steps.length) updatePipelineFromSteps(error.steps);
            if (error.diagnostics) addDiagnosticJsonMessage(error.diagnostics);
            if (!retried && isProviderRateLimited(error)) { scheduleRateLimitedRetry(message, options, error.retryAfter); return; }
            if (!retried && isProviderUnavailable(error) && scheduleProviderRetry(message, options)) return;
            if (error.wpaeCode === 'wpae_design_operation_pending' && error.pendingOperation) {
                addMessage('assistant', 'Эта операция уже записана без нового root. Проверяю актуальный preview и Vision, затем выполняю reconcile.');
                setPipelinePhase('render', 'active');
                reviewPendingOperation(error.pendingOperation, options.originalBrief || message).then(function (review) {
                    setPipelinePhase('render', 'done');
                    setPipelinePhase('review', review && review.report ? 'done' : 'skipped');
                    if (review && review.report) addMessage('assistant', describeVisionReview(review));
                    status.textContent = strings.done;
                }).catch(function (reviewError) {
                    addMessage('assistant', 'Операция сохранена, но reconcile оставлен pending: ' + reviewError.message);
                    status.textContent = strings.error;
                });
                return;
            }
            clearProviderRetry();
            addMessage('assistant', strings.error + ': ' + error.message);
            status.textContent = strings.error;
        }).finally(function () {
            requestInFlight = false;
            send.disabled = Boolean(readProviderRetry() || readVisionRepair());
        });
    }

    open.addEventListener('click', function () { setOpen(true); });
    pill.addEventListener('click', function (event) { if (event.target !== open) setOpen(true); });
    close.addEventListener('click', function () { setOpen(false); });
    copy.addEventListener('click', copyChatLog);
    copySelection.addEventListener('click', copySelectedJson);
    copySelectionPasteReady.addEventListener('click', copySelectedPasteReadyJson);
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
        if (readProviderRetry() || readVisionRepair()) {
            addMessage('assistant', 'Ожидаю завершения автоматической проверки и перезагрузки Elementor.');
            return;
        }
        refreshSelectionHint();
        addMessage('user', message);
        input.value = '';
        request(message);
    });
    retryProviderRequestAfterReload();
    retryVisionRepairAfterReload();
}());
