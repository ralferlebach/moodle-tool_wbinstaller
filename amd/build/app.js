define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    const state = {
        contextid: null,
        userid: null,
        filename: '',
        summary: [],
        optionalplugins: []
    };

    const bySelector = (root, selector) => root.querySelector(selector);

    const parseJson = function(value, fallback) {
        try {
            return JSON.parse(value);
        } catch (e) {
            return fallback;
        }
    };

    const getDraftItemId = function(root, selectors) {
        const input = bySelector(root, selectors.draftitemid);
        return input ? parseInt(input.value, 10) || 0 : 0;
    };

    const selectedOptionalPlugins = function(root) {
        return Array.from(root.querySelectorAll('[data-role="optional-plugin"]:checked')).map(function(node) {
            return node.value;
        });
    };

    const setBusy = function(root, selectors, busy) {
        const loader = bySelector(root, selectors.loader);
        const check = bySelector(root, selectors.check);
        const install = bySelector(root, selectors.install);
        if (loader) {
            loader.hidden = !busy;
        }
        if (check) {
            check.disabled = busy;
        }
        if (install) {
            install.disabled = busy || !state.filename;
        }
    };

    const renderStatus = function(root, selectors, type, text) {
        const region = bySelector(root, selectors.status);
        if (!region) {
            return;
        }
        region.innerHTML = '<div class="col-12"><div class="alert alert-' + type + '">' + text + '</div></div>';
    };

    const renderFeedback = function(root, selectors, payload) {
        const region = bySelector(root, selectors.feedback);
        if (region) {
            region.textContent = JSON.stringify(payload, null, 2);
        }
    };

    const renderSummary = function(root, selectors) {
        const region = bySelector(root, selectors.summary);
        if (!region) {
            return;
        }
        if (!state.summary.length) {
            region.innerHTML = '';
            return;
        }
        region.innerHTML = state.summary.map(function(section) {
            const items = section.items.map(function(item) {
                return '<li>' + item.text + '</li>';
            }).join('');
            return '' +
                '<div class="col-12 col-lg-6 mb-3">' +
                    '<div class="card h-100">' +
                        '<div class="card-header d-flex justify-content-between align-items-center">' +
                            '<strong>' + section.title + '</strong>' +
                            '<span class="badge badge-secondary">' + section.count + '</span>' +
                        '</div>' +
                        '<div class="card-body"><ul class="mb-0">' + items + '</ul></div>' +
                    '</div>' +
                '</div>';
        }).join('');
    };

    const renderOptionalPlugins = function(root, selectors) {
        const region = bySelector(root, selectors.optionalplugins);
        if (!region) {
            return;
        }
        if (!state.optionalplugins.length) {
            region.innerHTML = '';
            return;
        }
        region.innerHTML = '' +
            '<div class="col-12">' +
                '<div class="card">' +
                    '<div class="card-header"><strong>Optionale Plugins</strong></div>' +
                    '<div class="card-body">' +
                        state.optionalplugins.map(function(plugin, index) {
                            return '' +
                                '<div class="custom-control custom-checkbox mb-2">' +
                                    '<input class="custom-control-input" type="checkbox" checked id="wbinstaller-optional-' + index + '" data-role="optional-plugin" value="' + plugin + '">' +
                                    '<label class="custom-control-label" for="wbinstaller-optional-' + index + '">' + plugin + '</label>' +
                                '</div>';
                        }).join('') +
                    '</div>' +
                '</div>' +
            '</div>';
    };

    const updateFilename = function(root, selectors) {
        const region = bySelector(root, selectors.filename);
        if (region) {
            region.textContent = state.filename || '';
        }
    };

    const call = function(methodname, args) {
        return Ajax.call([{methodname: methodname, args: args}])[0];
    };

    const runCheck = function(root, selectors) {
        const draftitemid = getDraftItemId(root, selectors);
        if (!draftitemid) {
            renderStatus(root, selectors, 'warning', 'Bitte zuerst eine ZIP-Datei im Filepicker hochladen.');
            return;
        }

        setBusy(root, selectors, true);
        call('tool_wbinstaller_check_recipe', {
            userid: state.userid,
            contextid: state.contextid,
            draftitemid: draftitemid
        }).then(function(response) {
            state.filename = response.filename;
            state.summary = response.summary || [];
            state.optionalplugins = parseJson(response.optionalplugins, []);
            updateFilename(root, selectors);
            renderSummary(root, selectors);
            renderOptionalPlugins(root, selectors);
            renderFeedback(root, selectors, parseJson(response.feedback, response.feedback));
            renderStatus(root, selectors, 'info', 'Rezept erfolgreich geprüft.');
            setBusy(root, selectors, false);
            bySelector(root, selectors.install).disabled = false;
            return null;
        }).catch(Notification.exception);
    };

    const runInstall = function(root, selectors) {
        const draftitemid = getDraftItemId(root, selectors);
        if (!draftitemid) {
            renderStatus(root, selectors, 'warning', 'Bitte zuerst eine ZIP-Datei im Filepicker hochladen.');
            return;
        }

        setBusy(root, selectors, true);
        call('tool_wbinstaller_install_recipe', {
            userid: state.userid,
            contextid: state.contextid,
            draftitemid: draftitemid,
            optionalplugins: JSON.stringify(selectedOptionalPlugins(root))
        }).then(function(response) {
            const finished = parseJson(response.finished, {});
            renderFeedback(root, selectors, {
                feedback: parseJson(response.feedback, response.feedback),
                finished: finished,
                status: response.status
            });
            renderStatus(
                root,
                selectors,
                response.status > 1 ? 'danger' : 'success',
                finished.status ? 'Installation abgeschlossen.' : 'Installationsschritt ausgeführt.'
            );
            setBusy(root, selectors, false);
            return null;
        }).catch(Notification.exception);
    };

    return {
        init: function(config) {
            state.contextid = config.contextid;
            state.userid = config.userid;
            const root = document.querySelector(config.selectors.root);
            if (!root) {
                return;
            }

            bySelector(root, config.selectors.check).addEventListener('click', function() {
                runCheck(root, config.selectors);
            });
            bySelector(root, config.selectors.install).addEventListener('click', function() {
                runInstall(root, config.selectors);
            });
        }
    };
});
