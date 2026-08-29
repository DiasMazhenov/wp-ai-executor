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
    function formatStep(step, index) {
        var labels = {
            received_action: 'полученная команда',
            received_post_id: 'полученный post_id',
            decoded_action: 'распознанная команда',
            decoded_post_id: 'распознанный post_id',
            decoded_element_count: 'распознано элементов',
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
            failure_details: 'детали проверок'
        };
        var line = 'Шаг ' + (index + 1) + ': ' + String(step.message || step.id || 'Операция выполнена');
        if (step.status === 'failed') line += ' [ошибка]';
        if (step.status === 'skipped') line += ' [пропущено]';
        var details = step.details || {};
        var parts = [];
        ['received_action', 'received_post_id', 'decoded_action', 'decoded_post_id', 'decoded_element_count', 'expected_action', 'expected_post_id', 'element_count', 'widget_count', 'existing_element_count', 'http_status', 'response_type', 'json_decoded', 'response_keys', 'reply_preview', 'reply_length', 'json_error', 'likely_truncated', 'finish_reason', 'provider_error_code', 'provider_message', 'guide_version', 'custom_skills_count', 'elementor_writes', 'failed_checks', 'failure_details'].forEach(function (key) {
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
    function refreshElementorPreview() {
        if (window.$e && window.$e.components && typeof window.$e.components.get === 'function') {
            try {
                var saveComponent = window.$e.components.get('document/save');
                var footerSaver = saveComponent && saveComponent.footerSaver;
                if (footerSaver && typeof footerSaver.refreshWpPreview === 'function') {
                    footerSaver.refreshWpPreview();
                    return true;
                }
            } catch (error) {}
        }
        if (window.elementor && typeof window.elementor.reloadPreview === 'function') {
            window.elementor.reloadPreview();
            return true;
        }
        var iframe = document.querySelector('#elementor-preview-iframe');
        if (iframe && iframe.contentWindow) {
            iframe.contentWindow.location.reload();
            return true;
        }
        return false;
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
    function capturePreviewScreenshot() {
        var iframe = document.querySelector('#elementor-preview-iframe');
        if (!iframe || !iframe.contentDocument) return Promise.reject(new Error('Текущий Elementor preview недоступен для screenshot.'));
        var doc = iframe.contentDocument;
        var width = doc.documentElement.clientWidth || iframe.clientWidth || 1280;
        var height = iframe.clientHeight || doc.documentElement.clientHeight || 900;
        height = Math.max(320, Math.min(height, 4000));
        return loadVisionCapture().then(function (capture) {
            return capture(doc.documentElement, {
                backgroundColor: '#ffffff',
                useCORS: true,
                logging: false,
                scale: 1,
                width: width,
                height: height,
                windowWidth: width,
                windowHeight: height,
                x: 0,
                y: doc.defaultView ? doc.defaultView.scrollY : 0
            }).then(function (canvas) {
                var imageBase64 = canvas.toDataURL('image/jpeg', 0.72);
                if (imageBase64.length > 5600000) throw new Error('Screenshot preview превышает допустимый размер AI Vision.');
                return { image_base64: imageBase64, mime_type: 'image/jpeg', viewport: width + 'x' + height };
            });
        });
    }
    function waitForPreviewRefresh() {
        return new Promise(function (resolve) { window.setTimeout(resolve, 1800); });
    }
    function requestVisionReview(snapshotId, captureError) {
        return postVisionReview({
            post_id: Number(config.postId) || 0,
            rollback_snapshot_id: snapshotId,
            vision_capture_error: captureError
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
                    var detail = body.error || body.message || ('HTTP ' + response.status);
                    if (body.code) detail += ' [' + body.code + ']';
                    var visionDetails = body.details || {};
                    if (visionDetails.analysis_error) detail += ': ' + visionDetails.analysis_error;
                    if (visionDetails.analysis_code) detail += ' [' + visionDetails.analysis_code + ']';
                    if (visionDetails.provider) detail += ' (provider: ' + visionDetails.provider + ')';
                    if (visionDetails.provider_http_status) detail += ' (HTTP provider: ' + visionDetails.provider_http_status + ')';
                    if (visionDetails.provider_message) detail += ': ' + visionDetails.provider_message;
                    if (visionDetails.rollback) detail += ' (rollback: ' + (visionDetails.rollback.ok ? 'выполнен' : 'не выполнен') + ')';
                    throw new Error(detail);
                }
                return body;
            });
        });
    }
    function runVisionReview(snapshotId) {
        addMessage('assistant', 'Выполняется: Обновляю preview и проверяю результат через AI Vision.');
        refreshElementorPreview();
        return waitForPreviewRefresh().then(function () {
            return capturePreviewScreenshot().then(function (capture) {
                return postVisionReview({
                    post_id: Number(config.postId) || 0,
                    rollback_snapshot_id: snapshotId,
                    image_base64: capture.image_base64,
                    mime_type: capture.mime_type,
                    viewport: capture.viewport
                });
            }, function (error) {
                return requestVisionReview(snapshotId, error.message);
            });
        });
    }
    function describeVisionReview(review) {
        var report = review.report || {};
        var summary = report.summary ? ' ' + report.summary : '';
        var findings = Array.isArray(report.findings) ? report.findings.slice(0, 3).map(function (finding) {
            return (finding.severity || 'info') + ': ' + (finding.message || 'наблюдение');
        }).join('; ') : '';
        return 'AI Vision: score ' + (report.vision_score === undefined ? 'n/a' : report.vision_score) + '.' + summary + (findings ? ' ' + findings : '');
    }
    function request(message) {
        var history = Array.prototype.slice.call(messages.querySelectorAll('.wpae-llm-message')).slice(-12).map(function (item) {
            return { role: item.classList.contains('wpae-llm-message--user') ? 'user' : 'assistant', content: item.textContent };
        });
        status.textContent = strings.sending;
        send.disabled = true;
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
                    if (diagnostics.update_error) detail += ': ' + diagnostics.update_error;
                    if (Array.isArray(diagnostics.blocking_errors) && diagnostics.blocking_errors.length) detail += ': ' + diagnostics.blocking_errors.join('; ');
                    if (diagnostics.received_action || diagnostics.received_post_id) detail += ' (получено: action=' + (diagnostics.received_action || 'не указано') + ', post_id=' + (diagnostics.received_post_id || 'не указан') + ')';
                    if (diagnostics.model_response && diagnostics.model_response.response_keys) detail += ' (ключи ответа: ' + diagnostics.model_response.response_keys.join(', ') + ')';
                    var failedChecks = diagnostics.failed_checks || (diagnostics.details && diagnostics.details.failed_checks) || (diagnostics.details && diagnostics.details.transaction && diagnostics.details.transaction.failed_checks) || [];
                    if (Array.isArray(failedChecks) && failedChecks.length) detail += ' (непройденные проверки: ' + failedChecks.join(', ') + ')';
                    if (Array.isArray(diagnostics.failure_details) && diagnostics.failure_details.length) detail += ' ' + diagnostics.failure_details.map(function (item) { return (item.code || 'check') + ': ' + (item.message || 'проверка не пройдена'); }).join('; ');
                    if (Array.isArray(diagnostics.steps) && diagnostics.steps.length) {
                        var stepError = new Error(detail);
                        stepError.steps = diagnostics.steps;
                        throw stepError;
                    }
                    if (errorData.provider_message) detail += ': ' + errorData.provider_message;
                    if (errorData.provider_error_code) detail += ' [код провайдера: ' + errorData.provider_error_code + ']';
                    if (diagnostics.provider_message && diagnostics.provider_message !== errorData.provider_message) detail += ': ' + diagnostics.provider_message;
                    if (diagnostics.provider_error_code && diagnostics.provider_error_code !== errorData.provider_error_code) detail += ' [код провайдера: ' + diagnostics.provider_error_code + ']';
                    if (diagnostics.finish_reason) detail += ' (finish_reason: ' + diagnostics.finish_reason + ')';
                    if (diagnostics.status && !diagnostics.error) detail += ' (HTTP ' + diagnostics.status + ')';
                    throw new Error(detail);
                }
                return body;
            });
        }).then(function (body) {
            window.clearInterval(progressTimer);
            if (Array.isArray(body.steps) && body.steps.length) addStepMessages(body.steps);
            var visionPromise = Promise.resolve(null);
            if (body.ok && body.write && Number(body.write.post_id) === Number(config.postId)) {
                if (config.vision && config.vision.ready && body.write.rollback_snapshot_id) {
                    visionPromise = runVisionReview(body.write.rollback_snapshot_id);
                } else {
                    addMessage('assistant', refreshElementorPreview() ? 'Предпросмотр Elementor обновляется из сохранённых данных.' : 'Данные сохранены. Не удалось автоматически обновить preview Elementor.');
                }
            }
            return visionPromise.then(function (review) {
                if (review && review.rolled_back) {
                    addMessage('assistant', 'AI Vision обнаружил критические дефекты. Изменения откатены.');
                    refreshElementorPreview();
                    status.textContent = strings.error;
                    return;
                }
                if (review && review.report) addMessage('assistant', describeVisionReview(review));
                addMessage('assistant', body.message || strings.error);
                status.textContent = strings.done;
            });
        }).catch(function (error) {
            window.clearInterval(progressTimer);
            if (Array.isArray(error.steps) && error.steps.length) addStepMessages(error.steps);
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
