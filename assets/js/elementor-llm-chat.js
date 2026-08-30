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
    copy.className = 'wpae-llm-icon-button wpae-llm-copy';
    copy.type = 'button';
    addIcon(copy, 'eicon-code', strings.copyLog);
    var copySelection = document.createElement('button');
    copySelection.className = 'wpae-llm-icon-button wpae-llm-copy-selection';
    copySelection.type = 'button';
    addIcon(copySelection, 'eicon-copy', strings.copySelection);
    var headActions = document.createElement('div');
    headActions.className = 'wpae-llm-head-actions';
    headActions.appendChild(copy);
    headActions.appendChild(copySelection);
    headActions.appendChild(close);
    head.appendChild(heading);
    head.appendChild(headActions);

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
    panel.appendChild(messages);
    panel.appendChild(form);
    root.appendChild(panel);
    root.appendChild(pill);
    document.body.appendChild(root);

    function setOpen(value) {
        root.classList.toggle('wpae-llm-chat-root--open', value);
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
        ['received_action', 'received_post_id', 'decoded_action', 'decoded_post_id', 'decoded_element_count', 'expected_action', 'expected_post_id', 'element_count', 'widget_count', 'existing_element_count', 'http_status', 'response_type', 'json_decoded', 'response_keys', 'reply_preview', 'reply_length', 'json_error', 'likely_truncated', 'finish_reason', 'provider_error_code', 'provider_message', 'guide_version', 'custom_skills_count', 'elementor_writes', 'failed_checks', 'failure_details', 'operation_id', 'inserted_ids', 'diff'].forEach(function (key) {
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
    var providerRetryKey = 'wpae_llm_provider_retry:' + String(config.postId || '0');
    var providerRetryTtl = 120000;
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
        return providerStatus === 408 || providerStatus === 425 || providerStatus === 429 || providerStatus >= 500;
    }
    function scheduleProviderRetry(message) {
        if (readProviderRetry()) return false;
        try {
            window.sessionStorage.setItem(providerRetryKey, JSON.stringify({ message: String(message).slice(0, 4000), createdAt: Date.now() }));
        } catch (error) {
            return false;
        }
        addMessage('assistant', 'LLM-провайдер недоступен. Перезагружаю страницу и повторю запрос один раз.');
        status.textContent = 'Перезагрузка страницы…';
        window.setTimeout(function () {
            window.location.reload();
            // ponytail: embedded browsers may ignore reload; retry locally once instead of looping.
            window.setTimeout(function () {
                if (readProviderRetry()) retryProviderRequestAfterReload();
            }, 1800);
        }, 250);
        return true;
    }
    function retryProviderRequestAfterReload() {
        var pending = readProviderRetry();
        if (!pending) return;
        clearProviderRetry();
        if (!config.ready) return;
        setOpen(true);
        status.textContent = strings.sending;
        addMessage('user', pending.message);
        addMessage('assistant', 'Повторяю запрос после перезагрузки страницы.');
        window.setTimeout(function () { request(pending.message, true); }, 0);
    }
    function copyText(text) {
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            return navigator.clipboard.writeText(text);
        }
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
    }
    function copyChatLog() {
        copyText(chatLog()).then(function () {
            setButtonLabel(copy, strings.copied);
            window.setTimeout(function () { setButtonLabel(copy, strings.copyLog); }, 1600);
        }).catch(function () {});
    }
    function selectedModels() {
        var selection = window.elementor && window.elementor.selection;
        var models = selection && typeof selection.getElements === 'function' ? selection.getElements() : [];
        return Array.prototype.slice.call(models || [], 0, 8).map(function (container) {
            return container && container.model ? container.model : container;
        }).filter(function (model) {
            return Boolean(model && (typeof model.toJSON === 'function' || model.attributes));
        });
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
        var models = selectedModels();
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
    function selectedElements() {
        return selectedModels().map(function (model) {
            var attributes = model && model.attributes ? model.attributes : {};
            return {
                id: String(attributes.id || ''),
                elType: String(attributes.elType || ''),
                widgetType: String(attributes.widgetType || '')
            };
        });
    }
    function buildVisionRepairMessage(review, originalBrief) {
        var report = review && review.report ? review.report : {};
        var findings = Array.isArray(report.findings) ? report.findings.slice(0, 6).map(function (finding) {
            var message = finding.message || 'исправление визуальной проблемы';
            var fix = finding.fix ? ' Исправление: ' + finding.fix : '';
            return (finding.severity || 'info') + ': ' + message + fix;
        }).join('; ') : '';
        return 'Перегенерируй текущий дизайн по исходному запросу пользователя с учетом замечаний AI Vision. Создай полноценный красивый блок заново, не урезай композицию и не оставляй placeholder-тексты. Исходный запрос пользователя: «' + String(originalBrief || '').slice(0, 4000) + '». ' + (findings || 'Устрани нарушения композиции, типографики, отступов и переполнения.');
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
        return editorSync && Array.isArray(editorSync.elements) ? editorSync.elements.map(function (element) {
            return element && element.id ? String(element.id) : '';
        }).filter(Boolean) : [];
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
    function getPreviewRenderContext(targetElementIds) {
        var iframe = getPreviewIframe();
        var doc = iframe && iframe.contentDocument;
        if (!doc) return {};
        var body = doc.body;
        var target = findPreviewTarget(doc, targetElementIds);
        var scope = target || body || doc.documentElement;
        var ids = Array.prototype.slice.call(scope.querySelectorAll('[data-id]')).filter(function (element) {
            var style = doc.defaultView.getComputedStyle(element);
            return style.display !== 'none' && style.visibility !== 'hidden';
        }).slice(0, 40).map(function (element) { return element.getAttribute('data-id'); });
        if (target && target.getAttribute('data-id')) ids.unshift(target.getAttribute('data-id'));
        ids = Array.from(new Set(ids)).filter(function (id) { return !!id; });
        return {
            source: 'elementor_editor_preview',
            editor_chrome_excluded: true,
            target_found: !!target,
            widget_count: scope ? scope.querySelectorAll('.elementor-widget').length : 0,
            text_length: scope ? (scope.innerText || '').trim().length : 0,
            text_excerpt: scope ? (scope.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 4000) : '',
            viewport_width: doc.documentElement.clientWidth || iframe.clientWidth || 0,
            viewport_height: iframe.clientHeight || doc.documentElement.clientHeight || 0,
            horizontal_overflow: !!(scope && scope.scrollWidth > scope.clientWidth + 2),
            visible_element_ids: ids,
            target_element_ids: Array.isArray(targetElementIds) ? targetElementIds.slice(0, 8) : []
        };
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
    function syncEditorElements(editorSync) {
        if (!editorSync || !Array.isArray(editorSync.elements) || !editorSync.elements.length) return Promise.resolve(false);
        if (!window.$e || typeof window.$e.run !== 'function' || !window.elementor || typeof window.elementor.getPreviewContainer !== 'function') return Promise.resolve(false);
        var container = window.elementor.getPreviewContainer();
        if (!container) return Promise.resolve(false);
        var elements = editorSync.elements.slice();
        var position = editorSync.position === 'start' ? 'start' : 'end';
        if (position === 'start') elements.reverse();
        try {
            return Promise.all(elements.map(function (model) {
                return Promise.resolve(window.$e.run('document/elements/create', {
                    container: container,
                    model: model,
                    options: { at: position === 'start' ? 0 : null, clone: false }
                }));
            })).then(function () { return true; }, function () { return false; });
        } catch (error) {
            return Promise.resolve(false);
        }
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
    function capturePreviewScreenshot(targetElementIds) {
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
                return new Promise(function (resolve) { image.addEventListener('load', resolve, { once: true }); image.addEventListener('error', resolve, { once: true }); });
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
            var captureWidth = target ? Math.ceil(targetRect.width) : width;
            var captureHeight = target ? Math.ceil(targetRect.height) : height;
            if (captureWidth < 1 || captureHeight < 1) {
                hidden.forEach(function (item) { item.element.style.display = item.display; });
                throw new Error('Сгенерированный блок имеет нулевой размер в preview Elementor.');
            }
            captureWidth = Math.max(320, Math.min(captureWidth, 4000));
            captureHeight = Math.max(320, Math.min(captureHeight, 4000));
            var targetBackground = doc.defaultView.getComputedStyle(captureTarget).backgroundColor;
            if (!targetBackground || targetBackground === 'rgba(0, 0, 0, 0)') targetBackground = doc.defaultView.getComputedStyle(doc.body || doc.documentElement).backgroundColor;
            if (!targetBackground || targetBackground === 'rgba(0, 0, 0, 0)') targetBackground = '#ffffff';
            var restore = function () {
                hidden.forEach(function (item) { item.element.style.display = item.display; });
            };
            return capture(captureTarget, {
                backgroundColor: targetBackground,
                useCORS: true,
                logging: false,
                scale: 1,
                width: captureWidth,
                height: captureHeight,
                windowWidth: width,
                windowHeight: height,
                x: 0,
                y: 0
            }).then(function (canvas) {
                restore();
                var imageBase64 = canvas.toDataURL('image/jpeg', 0.72);
                if (imageBase64.length > 5600000) throw new Error('Screenshot preview превышает допустимый размер AI Vision.');
                return { image_base64: imageBase64, mime_type: 'image/jpeg', viewport: captureWidth + 'x' + captureHeight, render_context: getPreviewRenderContext(targetElementIds) };
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
    function requestVisionReview(snapshotId, captureError, brief, editorSync) {
        return postVisionReview({
            post_id: Number(config.postId) || 0,
            rollback_snapshot_id: snapshotId,
            vision_capture_error: captureError,
            brief: brief || '',
            render_context: getPreviewRenderContext(getEditorSyncIds(editorSync))
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
    function runVisionReview(snapshotId, minimumWidgetCount, alreadySynced, brief, editorSync) {
        addMessage('assistant', 'Выполняется: Обновляю preview и проверяю результат через AI Vision.');
        return waitForPreviewRefresh(alreadySynced ? Promise.resolve(true) : refreshElementorPreview(), minimumWidgetCount).then(function () {
            return focusEditorSync(editorSync).then(function (focused) {
                if (focused) return true;
                return refreshElementorPreview().then(function (refreshed) {
                    if (!refreshed) throw new Error('Новый блок не найден в preview Elementor после realtime-вставки.');
                    return focusEditorSync(editorSync).then(function (refocused) {
                        if (!refocused) throw new Error('Новый блок не найден в preview Elementor после обновления.');
                        return true;
                    });
                });
            }).then(function () { return capturePreviewScreenshot(getEditorSyncIds(editorSync)); }).then(function (capture) {
                return postVisionReview({
                    post_id: Number(config.postId) || 0,
                    rollback_snapshot_id: snapshotId,
                    image_base64: capture.image_base64,
                    mime_type: capture.mime_type,
                    viewport: capture.viewport,
                    brief: brief || '',
                    render_context: capture.render_context
                });
            }, function (error) {
                return requestVisionReview(snapshotId, error.message, brief, editorSync);
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
                    return refreshElementorPreview();
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
        if (!snapshotId || !config.undoEndpoint) return Promise.resolve(false);
        return fetch(config.undoEndpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
            body: JSON.stringify({ post_id: Number(config.postId) || 0, rollback_snapshot_id: snapshotId })
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (body) {
                return !!response.ok && !!body.ok;
            });
        }, function () { return false; });
    }
    function request(message, retried, options) {
        options = options || {};
        var repairDepth = Number(options.repairDepth) || 0;
        var originalBrief = options.originalBrief || message;
        var beforeWidgetCount = getPreviewWidgetCount();
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
        var requestContext = {
            post_id: config.postId,
            selected_elements: options.selectedElements || selectedElements()
        };
        if (options.visionRepair) requestContext.vision_repair = true;
        if (options.visionRegenerate) requestContext.vision_regenerate = true;
        if (options.visionFindings) requestContext.vision_findings = String(options.visionFindings).slice(0, 3600);
        return fetch(config.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
            body: JSON.stringify({ message: message, history: history, context: requestContext })
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (body) {
                if (!response.ok) {
                    var detail = body.message || body.code || ('HTTP ' + response.status);
                    var errorData = body.data || {};
                    var errorCode = body.code || errorData.code || '';
                    var diagnostics = body.details || errorData.details || {};
                    if (typeof errorData.details === 'string' && errorData.details !== detail) detail += ': ' + errorData.details;
                    if (typeof diagnostics === 'string' && diagnostics !== detail && diagnostics !== errorData.details) detail += ': ' + diagnostics;
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
                        stepError.wpaeCode = errorCode;
                        stepError.httpStatus = response.status;
                        stepError.steps = diagnostics.steps;
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
                    throw requestError;
                }
                return body;
            });
        }).then(function (body) {
            window.clearInterval(progressTimer);
            if (Array.isArray(body.steps) && body.steps.length) addStepMessages(body.steps);
            var visionPromise = Promise.resolve(null);
            if (body.ok && body.write && Number(body.write.post_id) === Number(config.postId)) {
                var expectedWidgetCount = beforeWidgetCount + Number(body.write.inserted_widget_count || body.write.inserted_count || 0);
                var editorSyncedState = false;
                var editorSyncData = body.write.editor_sync;
                visionPromise = Promise.resolve(syncEditorElements(body.write.editor_sync)).then(function (editorSynced) {
                    editorSyncedState = editorSynced;
                    if (editorSynced) {
                        return waitForPreviewRefresh(Promise.resolve(true), expectedWidgetCount).then(function () { return focusEditorSync(editorSyncData); }).then(function () {
                            addMessage('assistant', 'Новые элементы добавлены в открытый Elementor без перезагрузки редактора.');
                            return true;
                        }).catch(function () {
                            return waitForPreviewRefresh(refreshElementorPreview(), expectedWidgetCount).then(function () { return focusEditorSync(editorSyncData); }).then(function () {
                                addMessage('assistant', 'Canvas не подтвердил realtime-вставку, preview обновлён из сохранённых данных.');
                                return false;
                            });
                        });
                    }
                    return waitForPreviewRefresh(refreshElementorPreview(), expectedWidgetCount).then(function () { return focusEditorSync(editorSyncData); }).then(function () {
                        addMessage('assistant', 'Предпросмотр Elementor обновлён из сохранённых данных.');
                        return false;
                    }).catch(function (error) {
                        addMessage('assistant', 'Данные сохранены, но preview Elementor не обновился: ' + error.message);
                        return false;
                    });
                }).then(function () {
                    if (!options.skipVision && config.vision && config.vision.ready && body.write.rollback_snapshot_id) {
                        return runVisionReview(body.write.rollback_snapshot_id, expectedWidgetCount, editorSyncedState, originalBrief, editorSyncData);
                    }
                    return true;
                });
            }
            return visionPromise.then(function (review) {
                if (review && review.gate && review.gate.quality_failed) {
                    addMessage('assistant', describeVisionReview(review) + ' Передаю замечания Vision агенту для полной регенерации дизайна.');
                    if (repairDepth >= 2) {
                        return rollbackVisionFailure(body.write.rollback_snapshot_id).then(function (rolledBack) {
                            if (rolledBack) refreshElementorPreview();
                            addMessage('assistant', 'Vision повторно обнаружил проблемы после двух bounded repair-проходов. Последняя неудачная версия отменена; автоматическая правка остановлена.');
                            status.textContent = strings.error;
                        });
                    }
                    addMessage('assistant', 'Выполняется: Откатываю неудачную версию и заново генерирую полноценный дизайн по исходному запросу.');
                    return rollbackVisionFailure(body.write.rollback_snapshot_id).then(function (rolledBack) {
                        if (!rolledBack) throw new Error('Не удалось откатить неудачную версию перед повторной генерацией.');
                        return refreshElementorPreview().then(function () {
                            return request(originalBrief, false, { visionRepair: true, visionRegenerate: true, repairDepth: repairDepth + 1, originalBrief: originalBrief, visionFindings: buildVisionRepairMessage(review, originalBrief) });
                        });
                    });
                }
                if (review && review.rolled_back) {
                    addMessage('assistant', 'AI Vision обнаружил критические дефекты. Изменения откатены.');
                    refreshElementorPreview();
                    status.textContent = strings.error;
                    return;
                }
                if (review && review.report) addMessage('assistant', describeVisionReview(review));
                addActionControls(body.write);
                addMessage('assistant', body.message || strings.error);
                status.textContent = strings.done;
            });
        }).catch(function (error) {
            window.clearInterval(progressTimer);
            if (Array.isArray(error.steps) && error.steps.length) addStepMessages(error.steps);
            if (!retried && isProviderUnavailable(error) && scheduleProviderRetry(message)) return;
            clearProviderRetry();
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
    copySelection.addEventListener('click', copySelectedJson);
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
    retryProviderRequestAfterReload();
}());
