define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    var state = {
        contextid: null,
        userid: null,
        filename: '',
        summary: [],
        optionalplugins: []
    };

    var bySelector = function(root, selector) {
        return root ? root.querySelector(selector) : null;
    };

    var parseJson = function(value, fallback) {
        if (typeof value !== 'string') {
            return (typeof value === 'undefined' || value === null) ? fallback : value;
        }
        try {
            return JSON.parse(value);
        } catch (e) {
            return fallback;
        }
    };

    var getDraftItemId = function(root, selectors) {
        var input = bySelector(root, selectors.draftitemid);
        return input ? (parseInt(input.value, 10) || 0) : 0;
    };

    var selectedOptionalPlugins = function(root) {
        return Array.prototype.slice.call(root.querySelectorAll('[data-role="optional-plugin"]:checked')).map(function(node) {
            return node.value;
        });
    };

    var setBusy = function(root, selectors, busy) {
        var loader = bySelector(root, selectors.loader);
        var check = bySelector(root, selectors.check);
        var install = bySelector(root, selectors.install);

        if (loader) {
            loader.hidden = !busy;
        }
        if (check) {
            check.disabled = !!busy;
        }
        if (install) {
            install.disabled = !!busy || !state.filename;
        }
    };

    var renderStatus = function(root, selectors, type, text) {
        var region = bySelector(root, selectors.status);
        if (!region) {
            return;
        }
        region.innerHTML = '<div class="col-12"><div class="alert alert-' + type + '">' + text + '</div></div>';
    };

    var renderFeedback = function(root, selectors, payload) {
        var region = bySelector(root, selectors.feedback);
        if (region) {
            region.textContent = (typeof payload === 'string') ? payload : JSON.stringify(payload, null, 2);
        }
    };

    var escapeHtml = function(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };

    var renderSummary = function(root, selectors) {
        var region = bySelector(root, selectors.summary);
        if (!region) {
            return;
        }
        if (!state.summary.length) {
            region.innerHTML = '';
            return;
        }

        region.innerHTML = state.summary.map(function(section) {
            var items = (section.items || []).map(function(item) {
                return '<li>' + escapeHtml(item.text || '') + '</li>';
            }).join('');

            return '' +
                '<div class="col-12 col-lg-6 mb-3">' +
                    '<div class="card h-100">' +
                        '<div class="card-header d-flex justify-content-between align-items-center">' +
                            '<strong>' + escapeHtml(section.title || '') + '</strong>' +
                            '<span class="badge bg-secondary text-white">' + (section.count || 0) + '</span>' +
                        '</div>' +
                        '<div class="card-body"><ul class="mb-0">' + items + '</ul></div>' +
                    '</div>' +
                '</div>';
        }).join('');
    };

    var renderOptionalPlugins = function(root, selectors) {
        var region = bySelector(root, selectors.optionalplugins);
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
                            var cleanplugin = escapeHtml(plugin);
                            return '' +
                                '<div class="form-check mb-2">' +
                                    '<input class="form-check-input" type="checkbox" checked id="wbinstaller-optional-' + index + '" data-role="optional-plugin" value="' + cleanplugin + '">' +
                                    '<label class="form-check-label" for="wbinstaller-optional-' + index + '">' + cleanplugin + '</label>' +
                                '</div>';
                        }).join('') +
                    '</div>' +
                '</div>' +
            '</div>';
    };

    var updateFilename = function(root, selectors) {
        var region = bySelector(root, selectors.filename);
        if (region) {
            region.textContent = state.filename || '';
        }
    };

    var call = function(methodname, args) {
        return Ajax.call([{methodname: methodname, args: args}])[0];
    };

    var handleException = function(root, selectors, exception) {
        setBusy(root, selectors, false);
        state.filename = '';
        renderStatus(root, selectors, 'danger', (exception && exception.message) ? exception.message : 'Unbekannter Fehler');
        renderFeedback(root, selectors, exception);
        Notification.exception(exception);
    };

    var runCheck = function(root, selectors) {
        var draftitemid = getDraftItemId(root, selectors);
        var installbutton;

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
            installbutton = bySelector(root, selectors.install);
            state.filename = response.filename || '';
            state.summary = response.summary || [];
            state.optionalplugins = parseJson(response.optionalplugins, []);
            updateFilename(root, selectors);
            renderSummary(root, selectors);
            renderOptionalPlugins(root, selectors);
            renderFeedback(root, selectors, parseJson(response.feedback, response.feedback));
            renderStatus(root, selectors, 'info', 'Rezept erfolgreich geprüft.');
            setBusy(root, selectors, false);
            if (installbutton) {
                installbutton.disabled = false;
            }
            return null;
        }).catch(function(exception) {
            handleException(root, selectors, exception);
        });
    };

    var runInstall = function(root, selectors) {
        var draftitemid = getDraftItemId(root, selectors);

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
            var finished = parseJson(response.finished, {});
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
        }).catch(function(exception) {
            handleException(root, selectors, exception);
        });
    };

    var bindEvents = function(root, selectors) {
        var check = bySelector(root, selectors.check);
        var install = bySelector(root, selectors.install);
        var draftinput = bySelector(root, selectors.draftitemid);

        if (check) {
            check.addEventListener('click', function(e) {
                e.preventDefault();
                runCheck(root, selectors);
            });
        }
        if (install) {
            install.addEventListener('click', function(e) {
                e.preventDefault();
                runInstall(root, selectors);
            });
        }
        if (draftinput) {
            draftinput.addEventListener('change', function() {
                state.filename = '';
                state.summary = [];
                state.optionalplugins = [];
                updateFilename(root, selectors);
                renderSummary(root, selectors);
                renderOptionalPlugins(root, selectors);
                renderStatus(root, selectors, 'secondary', 'Neue Datei ausgewählt. Bitte Rezept analysieren.');
                renderFeedback(root, selectors, '');
                setBusy(root, selectors, false);
            });
        }
    };

    return {
        init: function(config) {
            var root = document.querySelector(config.selectors.root);
            if (!root) {
                return;
            }
            state.contextid = config.contextid;
            state.userid = config.userid;
            bindEvents(root, config.selectors);
        }
    };
});
